<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Search, ShoppingBag, Download, Filter } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    orders: { type: Object, default: () => ({ data: [
        { id: 1, order_number: '#01001', customer_name: 'Priya Sharma', customer_email: 'priya@example.com', status: 'paid', total_amount: 2698, line_item_count: 2, provider: 'shopify', placed_at: '2026-04-15 14:30' },
        { id: 2, order_number: '#01002', customer_name: 'Rahul Patel', customer_email: 'rahul@example.com', status: 'fulfilled', total_amount: 1999, line_item_count: 1, provider: 'shopify', placed_at: '2026-04-15 12:15' },
        { id: 3, order_number: '#01003', customer_name: 'Anita Desai', customer_email: 'anita@example.com', status: 'paid', total_amount: 4497, line_item_count: 3, provider: 'shopify', placed_at: '2026-04-14 18:42' },
        { id: 4, order_number: '#01004', customer_name: 'Vikram Singh', customer_email: 'vikram@example.com', status: 'pending', total_amount: 899, line_item_count: 1, provider: 'woocommerce', placed_at: '2026-04-14 16:05' },
        { id: 5, order_number: '#01005', customer_name: 'Meera Joshi', customer_email: 'meera@example.com', status: 'refunded', total_amount: 1798, line_item_count: 2, provider: 'shopify', placed_at: '2026-04-14 10:20' },
        { id: 6, order_number: '#01006', customer_name: 'Arjun Kumar', customer_email: 'arjun@example.com', status: 'fulfilled', total_amount: 3498, line_item_count: 2, provider: 'shopify', placed_at: '2026-04-13 20:55' },
        { id: 7, order_number: '#01007', customer_name: 'Kavita Nair', customer_email: 'kavita@example.com', status: 'paid', total_amount: 599, line_item_count: 1, provider: 'woocommerce', placed_at: '2026-04-13 09:30' },
        { id: 8, order_number: '#01008', customer_name: 'Sanjay Gupta', customer_email: 'sanjay@example.com', status: 'fulfilled', total_amount: 5996, line_item_count: 4, provider: 'shopify', placed_at: '2026-04-12 15:12' },
    ] }) },
    filters: { type: Object, default: () => ({}) },
    totals: { type: Object, default: () => ({ count: 240, revenue: 824340 }) },
});

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v));
const q = ref(props.filters.q || '');
const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const statusMap = {
    paid:      { class: 'pill-good', label: 'Paid' },
    fulfilled: { class: 'pill-good', label: 'Fulfilled' },
    pending:   { class: 'pill-info', label: 'Pending' },
    refunded:  { class: 'pill-bad',  label: 'Refunded' },
    cancelled: { class: 'pill-bad',  label: 'Cancelled' },
};

const providerMap = {
    shopify:     { class: 'bg-emerald/10 text-emerald', label: 'Shopify' },
    woocommerce: { class: 'bg-brand-600/10 text-brand-300', label: 'Woo' },
};
</script>

<template>
<Head title="Orders" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Orders</h2>
            <p class="text-[12px] text-ink-3 mt-1">Synced from Shopify & WooCommerce</p>
        </div>
        <button class="btn btn-ghost" @click="window.location.href = route('tenant.orders.export', { tenant: slug })"><Download :size="14" /> Export CSV</button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <KpiCard label="Total Orders" :value="totals.count" format="number" />
        <KpiCard label="Total Revenue" :value="totals.revenue" format="currency" :delta="18" tone="good" />
        <KpiCard label="Avg Order Value" :value="Math.round(totals.revenue / totals.count)" format="currency" :delta="4" tone="good" />
        <KpiCard label="Fulfillment Rate" :value="87" format="percent" :delta="2" tone="good" />
    </div>

    <div class="card overflow-hidden p-0">
        <!-- Search + filters -->
        <div class="px-5 py-3 border-b border-frost-1 flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <div class="relative flex-1 max-w-sm">
                <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
                <input v-model="q" placeholder="Search orders, customers…" class="pulsara-input pl-9 text-[12px]" />
            </div>
            <div class="flex items-center gap-2">
                <button v-for="s in ['all', 'paid', 'fulfilled', 'pending', 'refunded']" :key="s"
                    class="px-2.5 py-1 text-[11px] font-mono rounded-full transition"
                    :class="(filters.status || 'all') === s ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'"
                >{{ s }}</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                    <tr>
                        <th class="text-left px-5 py-3">Order</th>
                        <th class="text-left px-5 py-3">Customer</th>
                        <th class="text-left px-5 py-3">Status</th>
                        <th class="text-left px-5 py-3">Source</th>
                        <th class="text-right px-5 py-3">Items</th>
                        <th class="text-right px-5 py-3">Amount</th>
                        <th class="text-left px-5 py-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-frost-1">
                    <tr v-for="o in orders.data" :key="o.id" class="hover:bg-brand-600/5 transition cursor-pointer" @click="router.visit(route('tenant.orders.show', { tenant: slug, order: o.id }))">
                        <td class="px-5 py-3 font-mono font-semibold text-brand-300">{{ o.order_number }}</td>
                        <td class="px-5 py-3">
                            <div class="font-medium text-white">{{ o.customer_name }}</div>
                            <div class="text-[11px] text-ink-3">{{ o.customer_email }}</div>
                        </td>
                        <td class="px-5 py-3">
                            <span class="pill" :class="statusMap[o.status]?.class || 'pill-info'">{{ statusMap[o.status]?.label || o.status }}</span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="pill" :class="providerMap[o.provider]?.class || ''">{{ providerMap[o.provider]?.label || o.provider }}</span>
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-ink-2">{{ o.line_item_count }}</td>
                        <td class="px-5 py-3 text-right font-mono font-semibold text-white">{{ fmt(o.total_amount) }}</td>
                        <td class="px-5 py-3 text-ink-3 text-[12px]">{{ o.placed_at }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-frost-1 text-[12px] text-ink-3 flex items-center justify-between">
            <span>Showing {{ orders.data.length }} orders</span>
            <div class="flex gap-1">
                <button class="btn btn-ghost btn-sm">← Prev</button>
                <button class="btn btn-ghost btn-sm">Next →</button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
