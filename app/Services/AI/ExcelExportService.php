<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Generates a real .xlsx file from a SQL result set produced by AiCopilot,
 * stores it under the requesting user's private exports directory, and
 * returns a short-lived signed download URL safe to drop into a chat message.
 *
 * Files live at storage/app/exports/{user_id}/{uuid}.xlsx — not publicly
 * accessible, served only through the signed AiExportController route.
 */
class ExcelExportService
{
    /** Signed URL TTL: long enough for a slow connection, short enough that
     *  a leaked link is mostly useless. */
    private const DOWNLOAD_TTL_MINUTES = 30;

    /** Max rows we'll write to a single export. Generous, but a guard against
     *  someone asking the model to dump an entire 19k-order table — at that
     *  size the user wants real reporting tools, not an in-chat download. */
    private const MAX_ROWS = 50000;

    /**
     * Build an xlsx from $rows (array of associative arrays / stdClass objects)
     * and return a structured descriptor for embedding in a chat message.
     *
     * @return array{
     *     download_url: string,
     *     filename: string,
     *     row_count: int,
     *     expires_at: string,
     *     truncated: bool
     * }
     */
    public function generate(string $companyName, string $description, array $rows): array
    {
        if (empty($rows)) {
            throw new RuntimeException('Nothing to export — the query returned no rows.');
        }

        $userId = Auth::id();
        if (!$userId) {
            throw new RuntimeException('Cannot generate export without an authenticated user.');
        }

        $truncated = false;
        if (count($rows) > self::MAX_ROWS) {
            $rows = array_slice($rows, 0, self::MAX_ROWS);
            $truncated = true;
        }

        // Normalize to associative arrays (SafeSqlRunner may return stdClass)
        $rows = array_map(fn ($r) => (array) $r, $rows);

        $headers = array_keys($rows[0]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Sheet title: derived from description, sanitized for Excel's rules
        // (max 31 chars, no : \ / ? * [ ])
        $sheet->setTitle($this->sanitizeSheetTitle($description));

        // Header row
        foreach ($headers as $col => $header) {
            $sheet->getCell([$col + 1, 1])->setValue($this->humanizeHeader((string) $header));
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $headerStyle = $sheet->getStyle("A1:{$lastCol}1");
        $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('4F46E5'); // brand purple
        $headerStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Data rows
        $rowNum = 2;
        foreach ($rows as $row) {
            foreach ($headers as $col => $header) {
                $value = $row[$header] ?? null;
                $cell = $sheet->getCell([$col + 1, $rowNum]);

                if ($value === null) {
                    $cell->setValue('');
                } elseif (is_bool($value)) {
                    $cell->setValue($value ? 'Yes' : 'No');
                } elseif (is_numeric($value) && !$this->looksLikePhoneOrId((string) $header, (string) $value)) {
                    // Real numerics get stored as numbers so Excel can sum/avg them.
                    // Phone numbers and IDs stay as strings — Excel would otherwise
                    // strip leading zeros or convert to scientific notation.
                    $cell->setValue($value + 0);
                } else {
                    $cell->setValue((string) $value);
                }
            }
            $rowNum++;
        }

        // Set column widths from data — done manually rather than via
        // setAutoSize(), which depends on GD font-metric calculation that
        // can fail or be very slow on Alpine containers. A simple "max
        // character length across rows, clamped to sensible bounds"
        // approximation gives a clean-looking spreadsheet without the
        // fragility.
        foreach ($headers as $col => $header) {
            $colIndex = $col + 1;
            $maxLen = mb_strlen($this->humanizeHeader((string) $header));

            // Sample up to 100 rows for width estimation to keep this fast
            // on large exports
            $sample = array_slice($rows, 0, 100);
            foreach ($sample as $row) {
                $value = (string) ($row[$header] ?? '');
                $len = mb_strlen($value);
                if ($len > $maxLen) $maxLen = $len;
            }

            // Clamp: minimum 10 (so single-letter columns aren't unreadable),
            // maximum 50 (so a long raw_payload doesn't push the layout
            // off-screen). The 1.15 multiplier roughly compensates for
            // proportional font width in Excel's default Calibri.
            $width = min(50, max(10, (int) ($maxLen * 1.15)));

            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->getColumnDimension($colLetter)->setWidth((float) $width);
        }

        // Freeze the header row so it stays visible when scrolling
        $sheet->freezePane('A2');

        // Filename + storage path. Stored under a per-user subdir so the
        // download route can verify ownership cheaply via path inspection.
        $uuid = Str::uuid()->toString();
        $safeDesc = Str::slug(Str::limit($description, 40, ''));
        $filename = ($safeDesc ?: 'export') . '-' . Carbon::now('Asia/Kolkata')->format('Y-m-d') . '.xlsx';
        $storagePath = "exports/{$userId}/{$uuid}.xlsx";

        // Write to a temp file first, then move into Laravel's storage disk
        // — phpspreadsheet's writer wants a real filesystem path, but we
        // want the final file to live on the configured `local` disk so the
        // download controller can serve it through Storage::disk().
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($tmp);
            Storage::disk('local')->put($storagePath, file_get_contents($tmp));
        } finally {
            if (is_file($tmp)) @unlink($tmp);
            // Free memory — Spreadsheet objects can be sizeable for big exports
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }

        $expiresAt = Carbon::now()->addMinutes(self::DOWNLOAD_TTL_MINUTES);

        $signedUrl = URL::temporarySignedRoute(
            'ai.export.download',
            $expiresAt,
            ['path' => $uuid, 'filename' => $filename],
        );

        return [
            'download_url' => $signedUrl,
            'filename'     => $filename,
            'row_count'    => count($rows),
            'expires_at'   => $expiresAt->toIso8601String(),
            'truncated'    => $truncated,
        ];
    }

    /**
     * Excel rejects : \ / ? * [ ] in sheet titles and caps them at 31 chars.
     */
    private function sanitizeSheetTitle(string $raw): string
    {
        $clean = preg_replace('/[:\\\\\/\?\*\[\]]/', ' ', $raw) ?? 'Export';
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? 'Export');
        if ($clean === '') $clean = 'Export';
        return mb_substr($clean, 0, 31);
    }

    /**
     * Turn snake_case DB column names into "Snake Case" headers — purely
     * cosmetic, makes the spreadsheet feel finished rather than raw.
     */
    private function humanizeHeader(string $header): string
    {
        $h = str_replace(['_', '.'], ' ', $header);
        return ucwords(trim($h));
    }

    /**
     * Heuristic for "this looks like a numeric string we should NOT convert
     * to a real number in Excel" — phone numbers, GST IDs, AWB codes, pin
     * codes, anything that's digits but shouldn't be summed or auto-rounded.
     */
    private function looksLikePhoneOrId(string $header, string $value): bool
    {
        $lower = strtolower($header);

        foreach (['phone', 'mobile', 'gst', 'pan', 'awb', 'tracking', 'order_number', 'pincode', 'zip', '_id', 'uuid'] as $needle) {
            if (str_contains($lower, $needle)) return true;
        }

        // Leading-zero strings are almost always IDs/codes, never real numbers
        if (strlen($value) > 1 && str_starts_with($value, '0')) return true;

        // Very long integer strings (>11 digits) are typically AWBs / phone /
        // order IDs, not real quantities — Excel would render them in
        // scientific notation and lose precision.
        if (preg_match('/^\d{12,}$/', $value)) return true;

        return false;
    }
}
