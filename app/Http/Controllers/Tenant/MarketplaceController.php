<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\Integrations\SyncAmazonOrders;
use App\Jobs\Integrations\SyncFlipkartOrders;
use App\Models\Tenant\MarketplaceCredential;
use App\Models\Tenant\Order;
use App\Services\Marketplaces\CsvOrderImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    /**
     * Marketplace overview page — all 4 marketplaces in one view.
     */
    public function index(): Response
    {
        $marketplaces = ['amazon', 'flipkart', 'myntra', 'nykaa'];
        $credentials = MarketplaceCredential::whereIn('marketplace', $marketplaces)->get()->keyBy('marketplace');

        // Order counts per marketplace
        $orderCounts = Order::selectRaw("marketplace, COUNT(*) as total, SUM(total_amount) as revenue")
            ->whereNotNull('marketplace')
            ->whereIn('marketplace', $marketplaces)
            ->groupBy('marketplace')
            ->get()
            ->keyBy('marketplace');

        return Inertia::render('Tenant/Integrations/Marketplaces', [
            'credentials' => $credentials,
            'orderCounts' => $orderCounts,
            'marketplaces' => [
                [
                    'id'          => 'amazon',
                    'name'        => 'Amazon',
                    'description' => 'Amazon Seller Central (SP-API)',
                    'type'        => 'api',
                    'fields'      => ['client_id', 'client_secret', 'refresh_token'],
                    'color'       => 'text-orange-400',
                ],
                [
                    'id'          => 'flipkart',
                    'name'        => 'Flipkart',
                    'description' => 'Flipkart Seller Marketplace API',
                    'type'        => 'api',
                    'fields'      => ['app_id', 'app_secret'],
                    'color'       => 'text-yellow-400',
                ],
                [
                    'id'          => 'myntra',
                    'name'        => 'Myntra',
                    'description' => 'Import orders via CSV export from Myntra Partner Portal',
                    'type'        => 'csv',
                    'fields'      => [],
                    'color'       => 'text-pink-400',
                ],
                [
                    'id'          => 'nykaa',
                    'name'        => 'Nykaa',
                    'description' => 'Import orders via CSV export from Nykaa Seller Portal',
                    'type'        => 'csv',
                    'fields'      => [],
                    'color'       => 'text-fuchsia-400',
                ],
            ],
        ]);
    }

    /**
     * Connect API-based marketplace (Amazon, Flipkart).
     */
    public function connect(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'marketplace' => ['required', 'in:amazon,flipkart'],
            'credentials' => ['required', 'array'],
        ]);

        $cred = MarketplaceCredential::firstOrNew(['marketplace' => $validated['marketplace']]);
        $cred->setEncryptedCredentials($validated['credentials']);
        $cred->status = 'connected';
        $cred->last_error = null;
        $cred->save();

        return back()->with('success', ucfirst($validated['marketplace']) . ' connected successfully.');
    }

    /**
     * Disconnect a marketplace.
     */
    public function disconnect(Request $request, string $tenant, string $marketplace): RedirectResponse
    {
        MarketplaceCredential::where('marketplace', $marketplace)->update([
            'status'     => 'disconnected',
            'last_error' => null,
        ]);

        return back()->with('success', ucfirst($marketplace) . ' disconnected.');
    }

    /**
     * Sync orders from API-based marketplace.
     */
    public function sync(Request $request, string $tenant, string $marketplace): RedirectResponse
    {
        $cred = MarketplaceCredential::where('marketplace', $marketplace)->where('status', 'connected')->first();
        if (!$cred) {
            return back()->with('error', ucfirst($marketplace) . ' is not connected.');
        }

        $company = app('current_company');
        $schema = 'tenant_' . $company->id;
        $sinceDate = $request->input('since_date', now()->subDays(7)->toIso8601String());

        match ($marketplace) {
            'amazon'   => SyncAmazonOrders::dispatch($schema, $sinceDate),
            'flipkart' => SyncFlipkartOrders::dispatch($schema, $sinceDate),
            default    => null,
        };

        return back()->with('success', ucfirst($marketplace) . ' sync started. Orders will appear shortly.');
    }

    /**
     * Import orders via CSV upload (Myntra, Nykaa).
     */
    public function importCsv(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'marketplace' => ['required', 'in:myntra,nykaa'],
            'file'        => ['required', 'file', 'mimes:csv,xlsx,xls,txt', 'max:10240'],
        ]);

        $result = CsvOrderImporter::import($request->file('file'), $validated['marketplace']);

        $imported = 0;
        foreach ($result['orders'] as $orderData) {
            Order::updateOrCreate(
                ['provider' => $orderData['provider'], 'external_id' => $orderData['external_id']],
                $orderData
            );
            $imported++;
        }

        // Create/update credential entry for tracking
        MarketplaceCredential::updateOrCreate(
            ['marketplace' => $validated['marketplace']],
            ['status' => 'connected', 'last_synced_at' => now(), 'last_error' => null]
        );

        $msg = "{$imported} orders imported from " . ucfirst($validated['marketplace']) . ".";
        if (!empty($result['errors'])) {
            $msg .= ' ' . count($result['errors']) . ' rows had issues.';
        }

        return back()->with('success', $msg);
    }

    /**
     * Marketplace orders list filtered by marketplace.
     */
    public function orders(Request $request, string $tenant, string $marketplace): Response
    {
        $orders = Order::where('marketplace', $marketplace)
            ->orderByDesc('placed_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Tenant/Integrations/MarketplaceOrders', [
            'orders'      => $orders,
            'marketplace' => $marketplace,
        ]);
    }
}
