<?php

declare(strict_types=1);

namespace App\Services\Ads;

use Illuminate\Support\Carbon;

class AdSpendCsvParser
{
    /**
     * Parse an ad spend CSV. Auto-detects Meta Ads / Google Ads / generic format.
     */
    public function parse(string $csvPath, string $hintPlatform = 'auto'): array
    {
        $rows = $this->readCsv($csvPath);
        if (empty($rows)) return ['entries' => [], 'format' => 'unknown', 'errors' => ['Empty or unreadable CSV']];

        $headers = array_keys($rows[0]);
        $format = $hintPlatform !== 'auto' ? $hintPlatform : $this->detectFormat($headers);

        $entries = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            try {
                $parsed = match ($format) {
                    'meta'    => $this->parseMeta($row),
                    'google'  => $this->parseGoogle($row),
                    default   => $this->parseGeneric($row),
                };
                if ($parsed && $parsed['spend'] > 0) {
                    $parsed['platform'] = in_array($format, ['meta', 'google']) ? $format : ($hintPlatform !== 'auto' ? $hintPlatform : 'other');
                    $entries[] = $parsed;
                }
            } catch (\Throwable $e) {
                $errors[] = "Row " . ($i + 2) . ": " . $e->getMessage();
            }
        }

        return ['entries' => $entries, 'format' => $format, 'total_rows' => count($rows), 'parsed' => count($entries), 'errors' => $errors];
    }

    protected function detectFormat(array $headers): string
    {
        $h = strtolower(implode('|', $headers));

        // Meta Ads exports
        if (str_contains($h, 'campaign name') && str_contains($h, 'amount spent')) return 'meta';
        if (str_contains($h, 'ad set name') && str_contains($h, 'reach')) return 'meta';
        if (str_contains($h, 'reporting starts') || str_contains($h, 'reporting ends')) return 'meta';

        // Google Ads exports
        if (str_contains($h, 'campaign') && str_contains($h, 'cost')) return 'google';
        if (str_contains($h, 'avg. cpc') || str_contains($h, 'search impr')) return 'google';
        if (str_contains($h, 'campaign') && str_contains($h, 'clicks') && str_contains($h, 'impr')) return 'google';

        return 'generic';
    }

    /**
     * Meta Ads Manager CSV export format.
     * Columns: Day, Campaign name, Amount spent (INR), Impressions, Link clicks, Results, Cost per result, Reach, etc.
     */
    protected function parseMeta(array $r): ?array
    {
        $date = $this->parseDate(
            $r['Day'] ?? $r['day'] ?? $r['Date'] ?? $r['date']
            ?? $r['Reporting starts'] ?? $r['reporting starts'] ?? ''
        );
        if (!$date) return null;

        $spend = $this->toFloat($r['Amount spent (INR)'] ?? $r['Amount Spent (INR)'] ?? $r['Amount spent'] ?? $r['Spend'] ?? $r['spend'] ?? $r['Cost'] ?? 0);

        return [
            'date'             => $date,
            'campaign_name'    => $r['Campaign name'] ?? $r['Campaign Name'] ?? $r['campaign name'] ?? $r['Ad set name'] ?? 'Unknown',
            'spend'            => $spend,
            'impressions'      => (int) $this->toFloat($r['Impressions'] ?? $r['impressions'] ?? 0),
            'clicks'           => (int) $this->toFloat($r['Link clicks'] ?? $r['Clicks (all)'] ?? $r['clicks'] ?? $r['Clicks'] ?? 0),
            'conversions'      => (int) $this->toFloat($r['Results'] ?? $r['Purchases'] ?? $r['conversions'] ?? 0),
            'conversion_value' => $this->toFloat($r['Purchase ROAS'] ?? $r['Conversion value'] ?? $r['Website purchase ROAS'] ?? 0) * $spend,
            'raw_data'         => $r,
        ];
    }

    /**
     * Google Ads CSV export format.
     * Columns: Campaign, Day, Cost, Impr., Clicks, Conversions, Conv. value, etc.
     */
    protected function parseGoogle(array $r): ?array
    {
        $date = $this->parseDate(
            $r['Day'] ?? $r['day'] ?? $r['Date'] ?? $r['date'] ?? ''
        );
        if (!$date) return null;

        return [
            'date'             => $date,
            'campaign_name'    => $r['Campaign'] ?? $r['campaign'] ?? $r['Campaign name'] ?? 'Unknown',
            'spend'            => $this->toFloat($r['Cost'] ?? $r['cost'] ?? $r['Amount'] ?? 0),
            'impressions'      => (int) $this->toFloat($r['Impr.'] ?? $r['Impressions'] ?? $r['impr'] ?? 0),
            'clicks'           => (int) $this->toFloat($r['Clicks'] ?? $r['clicks'] ?? 0),
            'conversions'      => (int) $this->toFloat($r['Conversions'] ?? $r['conversions'] ?? $r['Conv.'] ?? 0),
            'conversion_value' => $this->toFloat($r['Conv. value'] ?? $r['Conversion value'] ?? $r['Total conv. value'] ?? 0),
            'raw_data'         => $r,
        ];
    }

    /**
     * Generic CSV — tries to find spend, date, campaign from any column names.
     */
    protected function parseGeneric(array $r): ?array
    {
        $date = null; $spend = 0; $campaign = ''; $impressions = 0; $clicks = 0; $conversions = 0;

        foreach ($r as $key => $val) {
            $k = strtolower($key);
            if (!$date && preg_match('/date|day|period/i', $k)) $date = $this->parseDate($val);
            if (preg_match('/spend|cost|amount/i', $k) && !preg_match('/per|cpc|cpm/i', $k)) $spend = $this->toFloat($val);
            if (preg_match('/campaign|ad.?set|ad.?group/i', $k)) $campaign = $val;
            if (preg_match('/^impr|impression/i', $k)) $impressions = (int) $this->toFloat($val);
            if (preg_match('/^click/i', $k)) $clicks = (int) $this->toFloat($val);
            if (preg_match('/^conv|result|purchase/i', $k) && !preg_match('/value|cost/i', $k)) $conversions = (int) $this->toFloat($val);
        }

        if (!$date || $spend <= 0) return null;
        return [
            'date' => $date, 'campaign_name' => $campaign ?: 'Unknown', 'spend' => $spend,
            'impressions' => $impressions, 'clicks' => $clicks, 'conversions' => $conversions,
            'conversion_value' => 0, 'raw_data' => $r,
        ];
    }

    protected function readCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $headers = null;
            while (($data = fgetcsv($handle)) !== false) {
                $filtered = array_filter($data, fn ($v) => trim($v ?? '') !== '');
                if (count($filtered) < 2) continue;
                if (!$headers) {
                    $headers = array_map(fn ($h) => trim(str_replace("\xEF\xBB\xBF", '', $h ?? '')), $data);
                    continue;
                }
                if (count($data) === count($headers)) {
                    $rows[] = array_combine($headers, $data);
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    protected function parseDate(string $val): ?string
    {
        if (empty(trim($val))) return null;
        try {
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'M d, Y', 'd M Y', 'Y/m/d'] as $fmt) {
                $date = \DateTime::createFromFormat($fmt, trim($val));
                if ($date) return $date->format('Y-m-d');
            }
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable $e) { return null; }
    }

    protected function toFloat(mixed $val): float
    {
        return (float) str_replace([',', '"', '=', '₹', '$', ' '], '', (string) ($val ?? '0'));
    }
}
