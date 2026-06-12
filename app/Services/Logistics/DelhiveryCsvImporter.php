<?php

declare(strict_types=1);

namespace App\Services\Logistics;

use App\Models\Tenant\DeliveryPartner;
use App\Models\Tenant\LogisticsInvoice;
use App\Models\Tenant\LogisticsShipment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class DelhiveryCsvImporter
{
    /**
     * Import a Delhivery freight CSV (shipment-level breakdown).
     */
    public function importFreightCsv(string $csvPath, LogisticsInvoice $invoice, DeliveryPartner $partner): array
    {
        $rows = $this->parseCsv($csvPath);
        $imported = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $waybill = $this->cleanExcelString($row['waybill_num'] ?? '');
                if (empty($waybill)) continue;

                LogisticsShipment::updateOrCreate(
                    ['delivery_partner_id' => $partner->id, 'waybill' => $waybill, 'logistics_invoice_id' => $invoice->id],
                    [
                        'order_id'               => $this->cleanExcelString($row['order_id'] ?? ''),
                        'status'                 => $row['status'] ?? 'Unknown',
                        'payment_mode'           => $row['package_type'] ?? null,
                        'zone'                   => $row['zone'] ?? null,
                        'product_value'          => $this->toFloat($row['product_value'] ?? 0),
                        'cod_amount'             => $this->toFloat($row['cod_amount'] ?? 0),
                        'charged_weight'         => $this->toFloat($row['charged_weight'] ?? 0),
                        'destination_pin'        => $row['destination_pin'] ?? null,
                        'origin_center'          => $this->cleanExcelString($row['origin_center'] ?? ''),

                        'charge_freight'         => $this->toFloat($row['charge_DL'] ?? 0),
                        'charge_cod'             => $this->toFloat($row['charge_COD'] ?? 0),
                        'charge_rto'             => $this->toFloat($row['charge_RTO'] ?? 0) + $this->toFloat($row['charge_DTO'] ?? 0),
                        'charge_fuel'            => $this->toFloat($row['charge_FSC'] ?? 0),
                        'charge_pickup'          => $this->toFloat($row['charge_pickup'] ?? 0),
                        'charge_vas'             => $this->toFloat($row['charge_LABEL'] ?? 0) + $this->toFloat($row['charge_DOCUMENT'] ?? 0),
                        'charge_other'           => $this->sumOtherCharges($row),
                        'gross_amount'           => $this->toFloat($row['gross_amount'] ?? 0),
                        'cgst'                   => $this->toFloat($row['CGST'] ?? 0),
                        'sgst'                   => $this->toFloat($row['SGST/UGST'] ?? $row['sgst'] ?? 0),
                        'igst'                   => $this->toFloat($row['IGST'] ?? 0),
                        'total_amount'           => $this->toFloat($row['total_amount'] ?? 0),

                        'pickup_date'            => $this->parseDate($row['pickup_date'] ?? null),
                        'first_delivery_attempt' => $this->parseDate($row['fpd'] ?? null),
                        'delivered_date'         => $this->parseDate($row['frd'] ?? null),
                        'pdd'                    => $this->parseDate($row['pdd'] ?? null),
                        'attempt_count'          => (int) ($row['atc'] ?? 0),
                        'item_shipped'           => $this->cleanExcelString($row['item_shipped'] ?? ''),
                        'qty'                    => (int) ($row['qty'] ?? 1),
                        'raw_data'               => $row,
                    ]
                );
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Delhivery freight import row failed', ['waybill' => $row['waybill_num'] ?? 'unknown', 'error' => $e->getMessage()]);
            }
        }

        // Update invoice totals from shipments
        $this->updateInvoiceTotals($invoice);

        return ['imported' => $imported, 'failed' => $failed, 'total' => count($rows)];
    }

    /**
     * Import a Delhivery VAS CSV (WhatsApp, label charges etc).
     */
    public function importVasCsv(string $csvPath, LogisticsInvoice $invoice, DeliveryPartner $partner): array
    {
        $rows = $this->parseCsv($csvPath);
        $imported = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $waybill = $this->cleanExcelString($row['waybill_order_id'] ?? '');
                if (empty($waybill)) continue;

                // VAS charges get added to existing shipment or create new
                $shipment = LogisticsShipment::where('delivery_partner_id', $partner->id)
                    ->where('waybill', $waybill)
                    ->first();

                if ($shipment) {
                    // Add VAS charge to existing
                    $shipment->update([
                        'charge_vas'   => $shipment->charge_vas + $this->toFloat($row['charge_vas'] ?? $row['gross_amt'] ?? 0),
                        'gross_amount' => $shipment->gross_amount + $this->toFloat($row['gross_amt'] ?? 0),
                        'cgst'         => $shipment->cgst + $this->toFloat($row['cgst'] ?? 0),
                        'sgst'         => $shipment->sgst + $this->toFloat($row['sgst'] ?? 0),
                        'total_amount' => $shipment->total_amount + $this->toFloat($row['total_amt'] ?? 0),
                    ]);
                } else {
                    LogisticsShipment::create([
                        'delivery_partner_id'  => $partner->id,
                        'logistics_invoice_id' => $invoice->id,
                        'waybill'              => $waybill,
                        'status'               => $row['status_type'] ?? 'VAS',
                        'charge_vas'           => $this->toFloat($row['charge_vas'] ?? $row['gross_amt'] ?? 0),
                        'gross_amount'         => $this->toFloat($row['gross_amt'] ?? 0),
                        'cgst'                 => $this->toFloat($row['cgst'] ?? 0),
                        'sgst'                 => $this->toFloat($row['sgst'] ?? 0),
                        'total_amount'         => $this->toFloat($row['total_amt'] ?? 0),
                        'raw_data'             => $row,
                    ]);
                }
                $imported++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Delhivery VAS import row failed', ['error' => $e->getMessage()]);
            }
        }

        $this->updateInvoiceTotals($invoice);
        return ['imported' => $imported, 'failed' => $failed, 'total' => count($rows)];
    }

    protected function updateInvoiceTotals(LogisticsInvoice $invoice): void
    {
        $totals = LogisticsShipment::where('logistics_invoice_id', $invoice->id)
            ->selectRaw('SUM(gross_amount) as subtotal, SUM(cgst) as cgst, SUM(sgst) as sgst, SUM(igst) as igst, SUM(total_amount) as total, COUNT(*) as cnt')
            ->first();

        $invoice->update([
            'subtotal'       => $totals->subtotal ?? 0,
            'cgst'           => $totals->cgst ?? 0,
            'sgst'           => $totals->sgst ?? 0,
            'igst'           => $totals->igst ?? 0,
            'total_amount'   => $totals->total ?? 0,
            'shipment_count' => $totals->cnt ?? 0,
        ]);
    }

    protected function sumOtherCharges(array $row): float
    {
        $fields = ['charge_DPH', 'charge_QC', 'charge_CWH', 'charge_E2E', 'charge_LM',
                    'charge_DEMUR', 'charge_REATTEMPT', 'charge_ROV', 'charge_PEAK',
                    'charge_POD', 'charge_COVID', 'charge_FOV', 'charge_CCOD', 'charge_WOD', 'charge_AIR'];
        $sum = 0;
        foreach ($fields as $f) $sum += $this->toFloat($row[$f] ?? 0);
        return $sum;
    }

    protected function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            $headers = fgetcsv($handle);
            if (!$headers) return [];
            // Clean BOM and whitespace
            $headers = array_map(fn ($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $headers);

            while (($data = fgetcsv($handle)) !== false) {
                if (count($data) === count($headers)) {
                    $rows[] = array_combine($headers, $data);
                }
            }
            fclose($handle);
        }
        return $rows;
    }

    protected function cleanExcelString(string $val): string
    {
        // Delhivery CSVs wrap values in ="..." format
        return trim(str_replace(['"', '='], '', $val));
    }

    protected function toFloat($val): float
    {
        return (float) str_replace([',', '"', '='], '', (string) $val);
    }

    protected function parseDate($val): ?\Carbon\Carbon
    {
        if (empty($val) || $val === '=""') return null;
        $clean = $this->cleanExcelString((string) $val);
        if (empty($clean)) return null;
        try { return Carbon::parse($clean); } catch (\Throwable $e) { return null; }
    }
}
