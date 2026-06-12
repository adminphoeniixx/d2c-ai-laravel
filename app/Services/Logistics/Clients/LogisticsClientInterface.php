<?php

declare(strict_types=1);

namespace App\Services\Logistics\Clients;

/**
 * Common interface for all delivery partner API clients.
 * Each partner implements this with their specific auth and endpoints.
 */
interface LogisticsClientInterface
{
    /** Authenticate and return true if credentials are valid */
    public function testConnection(): bool;

    /**
     * Track a single shipment by AWB number.
     * Returns normalized tracking data.
     */
    public function track(string $waybill): ?array;

    /**
     * Track multiple AWBs in one call (if supported).
     * Returns array keyed by AWB.
     */
    public function trackBulk(array $waybills): array;

    /**
     * Fetch all shipments/orders for a date range.
     * Returns array of normalized shipment data.
     */
    public function fetchShipments(string $fromDate, string $toDate): array;
}
