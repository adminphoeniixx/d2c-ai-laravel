<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SchemaIntrospector
{
    /**
     * Tables that are operational/Laravel internals, not relevant for business
     * questions and should never be exposed to the AI.
     */
    protected const EXCLUDED_TABLES = [
        'migrations', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks',
        'sessions', 'password_reset_tokens', 'personal_access_tokens',
        'notifications', 'notifications_log', 'activity_log',
        'model_has_permissions', 'model_has_roles', 'role_has_permissions',
        'roles', 'permissions', 'team_invitations', 'ai_conversations', 'ai_messages',
        'letter_templates', 'letters', 'employee_documents',
    ];

    /**
     * A short hint of what each table contains, to help the AI pick the
     * right tables without guessing from column names alone.
     */
    protected const TABLE_HINTS = [
        'orders'              => 'Customer orders synced from Shopify/WooCommerce/marketplaces. One row per order.',
        'order_items'         => 'Line items within an order (product, qty, price). Join to orders via order_id.',
        'expenses'            => 'Business expenses with category, amount, GST, vendor.',
        'bank_accounts'       => 'Bank accounts connected for this company.',
        'bank_transactions'   => 'Bank statement transactions (debit/credit), categorized by AI.',
        'inventory_items'     => 'Product inventory: SKU, stock on hand, cost price, selling price.',
        'inventory_movements' => 'Stock in/out history for inventory_items.',
        'ad_campaigns'        => 'Ad campaigns from Meta/Google Ads with spend and performance.',
        'ad_spend_daily'      => 'Daily ad spend totals per channel.',
        'ad_spend_manual'     => 'Manually entered ad spend records.',
        'ad_invoices'         => 'Uploaded ad platform invoices.',
        'pg_invoices'         => 'Payment gateway (Razorpay/PayU/etc) invoices: charges, GST, settlements.',
        'logistics_shipments' => 'Shipment tracking records (Delhivery etc): status, RTO, costs.',
        'logistics_invoices'  => 'Logistics partner invoices.',
        'delivery_partners'   => 'Configured logistics/delivery partners.',
        'employees'           => 'Employee/staff records: name, role, salary, joining date.',
        'workers'             => 'Daily-wage workers with attendance-based pay.',
        'attendances'         => 'Daily attendance records for employees/workers.',
        'payroll_runs'        => 'Monthly payroll batches.',
        'payslips'            => 'Individual employee payslips within a payroll run.',
        'leave_requests'      => 'Employee leave requests with status.',
        'leave_balances'      => 'Remaining leave balance per employee per leave type.',
        'leave_types'         => 'Configured leave types (e.g. Casual, Sick).',
        'holidays'            => 'Company holiday calendar.',
        'purchase_orders'     => 'Purchase orders placed with vendors.',
        'purchase_order_items'=> 'Line items within a purchase order.',
        'vendors'             => 'Supplier/vendor master records.',
        'products'            => 'Product catalog synced from Shopify/WooCommerce (price, cost, compare_at_price, inventory_quantity). May be sparsely populated for some tenants.',
        'support_tickets'     => 'Customer support tickets.',
        'company_kyc'         => 'KYC verification status and documents for this company.',
        'marketplace_credentials' => 'Connected marketplace integration credentials (do not expose secrets).',
        'integration_accounts'    => 'Connected integration accounts (Shopify, WooCommerce, etc).',
    ];

    /**
     * Columns that should never be described to the AI (secrets/tokens).
     */
    protected const EXCLUDED_COLUMNS = [
        'api_token', 'api_secret', 'access_token', 'refresh_token', 'client_secret',
        'webhook_secret', 'password', 'two_factor_secret', 'two_factor_recovery_codes',
        'remember_token', 'credentials', 'api_credentials',
    ];

    /**
     * Build (and cache) a textual schema description for the AI prompt.
     */
    public function describe(string $schema): string
    {
        return Cache::store('file')->remember(
            "ai_schema_desc:{$schema}",
            now()->addHours(6),
            fn () => $this->build($schema)
        );
    }

    protected function build(string $schema): string
    {
        $tables = DB::select("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = ?
              AND table_type = 'BASE TABLE'
            ORDER BY table_name
        ", [$schema]);

        $lines = [];
        $tableNames = [];

        foreach ($tables as $t) {
            $name = $t->table_name;
            if (in_array($name, self::EXCLUDED_TABLES, true)) continue;

            $columns = DB::select("
                SELECT column_name, data_type
                FROM information_schema.columns
                WHERE table_schema = ? AND table_name = ?
                ORDER BY ordinal_position
            ", [$schema, $name]);

            $colParts = [];
            foreach ($columns as $c) {
                if (in_array($c->column_name, self::EXCLUDED_COLUMNS, true)) continue;
                $colParts[] = "{$c->column_name} ({$c->data_type})";
            }

            if (empty($colParts)) continue;

            $tableNames[] = $name;
            $hint = self::TABLE_HINTS[$name] ?? null;
            $line = "TABLE {$name}: " . implode(', ', $colParts);
            if ($hint) $line .= "\n  -- {$hint}";

            $lines[] = $line;
        }

        $output = implode("\n\n", $lines);

        $hints = $this->relationshipHints($tableNames);
        if ($hints) {
            $output .= "\n\nRELATIONSHIPS & CALCULATION HINTS:\n" . $hints;
        }

        return $output;
    }

    /**
     * Extra guidance on joins and derived calculations that aren't obvious
     * from column names alone. Only include hints for tables that exist
     * in this tenant's schema.
     */
    protected function relationshipHints(array $tableNames): string
    {
        $available = array_flip($tableNames);
        $hints = [];

        if (isset($available['orders']) && isset($available['order_items'])) {
            $hints[] = "- order_items.order_id joins orders.id. orders.placed_at is the order date/timestamp for date filtering. orders.total_amount is the order's grand total (use for revenue); order_items.total_price is the line-item total.";
        }

        if (isset($available['inventory_items'])) {
            $hints[] = "- For 'pricing', 'margin', or 'profitability' questions about products, prefer inventory_items (selling_price, cost_price, category) — this is the data tenants actively manage. Only use 'products' if inventory_items doesn't have relevant rows, since 'products' may be empty/unsynced for some tenants. MARGIN % = (selling_price - cost_price) / selling_price * 100.";
        }

        if (isset($available['order_items']) && isset($available['inventory_items'])) {
            $hints[] = "- order_items.sku joins inventory_items.sku (text match, not a foreign key) to get cost_price/selling_price/category for a product.";
            $hints[] = "- PROFIT PER LINE ITEM = order_items.total_price - (inventory_items.cost_price * order_items.quantity). To rank products/SKUs by profit, join order_items to inventory_items on sku, join order_items to orders for date filtering, then GROUP BY order_items.sku (and product_name) summing that expression.";
        }

        if (isset($available['expenses'])) {
            $hints[] = "- expenses.category groups expenses (e.g. Inventory, Marketing, Logistics, Salaries). expenses.expense_date is the date column for date filtering.";
        }

        if (isset($available['bank_transactions'])) {
            $hints[] = "- bank_transactions.transaction_date is the date column. Use transaction_type ('debit'/'credit') and category for spend breakdowns.";
        }

        if (isset($available['ad_spend_daily'])) {
            $hints[] = "- ad_spend_daily has one row per channel per day. Sum 'spend' grouped by channel/date for ad spend questions.";
        }

        if (isset($available['logistics_shipments']) && isset($available['orders'])) {
            $hints[] = "- logistics_shipments relates to orders (commonly via order_id or order_number) and has shipping_cost/status/RTO fields for logistics spend and RTO analysis.";
        }

        // Period-over-period comparisons (month-over-month, week-over-week, etc.)
        if (isset($available['orders'])) {
            $hints[] = "- IMPORTANT: 'this month' is usually still in progress (partial/month-to-date), so comparing its total directly to a FULL last month is misleading. For period-over-period comparisons (e.g. \"month over month growth\"), compute THREE numbers in one query: this_month_mtd (this month so far), last_month_mtd (last month, same number of days so far — for a fair like-for-like comparison), and last_month_full (all of last month, for context). Example:\n"
                . "    SELECT\n"
                . "      SUM(CASE WHEN placed_at >= date_trunc('month', CURRENT_DATE) THEN total_amount ELSE 0 END) AS this_month_mtd,\n"
                . "      SUM(CASE WHEN placed_at >= date_trunc('month', CURRENT_DATE) - INTERVAL '1 month'\n"
                . "               AND placed_at < date_trunc('month', CURRENT_DATE) - INTERVAL '1 month' + (CURRENT_DATE - date_trunc('month', CURRENT_DATE)) + INTERVAL '1 day'\n"
                . "               THEN total_amount ELSE 0 END) AS last_month_mtd,\n"
                . "      SUM(CASE WHEN placed_at >= date_trunc('month', CURRENT_DATE) - INTERVAL '1 month'\n"
                . "               AND placed_at < date_trunc('month', CURRENT_DATE) THEN total_amount ELSE 0 END) AS last_month_full\n"
                . "    FROM orders;\n"
                . "  In the answer, compute % growth from this_month_mtd vs last_month_mtd (the fair comparison), explicitly note both figures are 'month-to-date', and mention last_month_full only as additional context for the full month.";
        }

        return implode("\n", $hints);
    }

    /**
     * Force-refresh the cached schema description (call after running new
     * tenant migrations if needed).
     */
    public function forget(string $schema): void
    {
        Cache::store('file')->forget("ai_schema_desc:{$schema}");
    }
}
