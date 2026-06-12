<?php

declare(strict_types=1);

namespace App\Services\Marketplaces;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CsvOrderImporter
{
    /**
     * Parse CSV/Excel file and return normalized orders.
     * Supports Myntra and Nykaa seller portal exports.
     */
    public static function import(UploadedFile $file, string $marketplace): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = [];

        if ($extension === 'csv') {
            $rows = self::parseCsv($file->getRealPath());
        } elseif (in_array($extension, ['xlsx', 'xls'])) {
            // For Excel, convert to CSV first using a simple read
            $rows = self::parseCsv($file->getRealPath());
        }

        if (empty($rows)) {
            return ['orders' => [], 'errors' => ['No data found in file']];
        }

        $orders = [];
        $errors = [];
        $headers = array_map('strtolower', array_map('trim', $rows[0]));

        // Detect marketplace format and map columns
        $mapping = self::detectMapping($headers, $marketplace);

        for ($i = 1; $i < count($rows); $i++) {
            try {
                $row = array_combine($headers, array_pad($rows[$i], count($headers), ''));
                $order = self::normalizeRow($row, $mapping, $marketplace);
                if ($order) $orders[] = $order;
            } catch (\Throwable $e) {
                $errors[] = "Row {$i}: " . $e->getMessage();
            }
        }

        return ['orders' => $orders, 'errors' => $errors, 'total' => count($orders)];
    }

    /**
     * Detect column mapping based on headers.
     */
    protected static function detectMapping(array $headers, string $marketplace): array
    {
        // Common Myntra seller portal columns
        $myntraMap = [
            'order_id'     => self::findHeader($headers, ['order id', 'order_id', 'orderid', 'order no']),
            'sku'          => self::findHeader($headers, ['sku', 'style id', 'article no', 'product_sku']),
            'product_name' => self::findHeader($headers, ['product name', 'product_name', 'item name', 'description', 'style name']),
            'quantity'     => self::findHeader($headers, ['quantity', 'qty', 'units']),
            'selling_price'=> self::findHeader($headers, ['selling price', 'mrp', 'price', 'amount', 'total price', 'order value']),
            'discount'     => self::findHeader($headers, ['discount', 'discount amount']),
            'status'       => self::findHeader($headers, ['status', 'order status', 'shipment status']),
            'customer_name'=> self::findHeader($headers, ['customer name', 'buyer name', 'customer']),
            'city'         => self::findHeader($headers, ['city', 'delivery city', 'shipping city']),
            'state'        => self::findHeader($headers, ['state', 'delivery state', 'shipping state']),
            'pincode'      => self::findHeader($headers, ['pincode', 'pin code', 'zip', 'postal code']),
            'order_date'   => self::findHeader($headers, ['order date', 'order_date', 'created on', 'date', 'placed on']),
            'commission'   => self::findHeader($headers, ['commission', 'platform fee', 'marketplace fee']),
        ];

        // Nykaa may have slightly different column names
        if ($marketplace === 'nykaa') {
            $myntraMap['order_id'] = self::findHeader($headers, ['order id', 'sub order id', 'nykaa order id', 'order_id']);
            $myntraMap['selling_price'] = self::findHeader($headers, ['selling price', 'sp', 'net amount', 'total', 'order value']);
        }

        return $myntraMap;
    }

    protected static function findHeader(array $headers, array $candidates): ?string
    {
        foreach ($candidates as $c) {
            $key = array_search(strtolower($c), $headers);
            if ($key !== false) return $headers[$key];
        }
        return null;
    }

    /**
     * Normalize a CSV row to heyd2c order format.
     */
    protected static function normalizeRow(array $row, array $mapping, string $marketplace): ?array
    {
        $orderId = trim($row[$mapping['order_id']] ?? '');
        if (empty($orderId)) return null;

        $price = (float) str_replace([',', '₹', ' '], '', $row[$mapping['selling_price'] ?? ''] ?? '0');
        $qty = max(1, (int) ($row[$mapping['quantity'] ?? ''] ?? 1));
        $discount = (float) str_replace([',', '₹', ' '], '', $row[$mapping['discount'] ?? ''] ?? '0');
        $commission = (float) str_replace([',', '₹', ' '], '', $row[$mapping['commission'] ?? ''] ?? '0');

        $address = array_filter([
            'city'    => $row[$mapping['city'] ?? ''] ?? null,
            'state'   => $row[$mapping['state'] ?? ''] ?? null,
            'pincode' => $row[$mapping['pincode'] ?? ''] ?? null,
        ]);

        return [
            'external_id'           => $marketplace . '_' . $orderId,
            'provider'              => $marketplace,
            'marketplace'           => $marketplace,
            'marketplace_order_id'  => $orderId,
            'order_number'          => $orderId,
            'channel_sku'           => $row[$mapping['sku'] ?? ''] ?? null,
            'status'                => strtolower(trim($row[$mapping['status'] ?? ''] ?? 'pending')),
            'financial_status'      => 'paid',
            'currency'              => 'INR',
            'subtotal'              => $price,
            'total_discount'        => $discount,
            'total_amount'          => $price - $discount,
            'marketplace_commission'=> $commission,
            'net_amount'            => $price - $discount - $commission,
            'customer_name'         => $row[$mapping['customer_name'] ?? ''] ?? null,
            'shipping_address'      => !empty($address) ? $address : null,
            'line_item_count'       => $qty,
            'placed_at'             => self::parseDate($row[$mapping['order_date'] ?? ''] ?? null),
            'raw_payload'           => $row,
        ];
    }

    protected static function parseDate(?string $date): ?string
    {
        if (!$date) return null;
        try {
            return \Carbon\Carbon::parse($date)->toDateTimeString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }
        return $rows;
    }
}
