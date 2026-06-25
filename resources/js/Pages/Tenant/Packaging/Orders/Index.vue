<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Filter } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    orders:         { type: Object, default: () => ({ data: [] }) },
    totals:         { type: Object, default: () => ({ total: 0, pending: 0, total_value: 0 }) },
    filters:        { type: Object, default: () => ({}) },
    packagingItems: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const statuses = ['all', 'draft', 'sent', 'received', 'cancelled'];
const statusMap = { draft: 'pill-info', sent: 'pill-info', received: 'pill-good', cancelled: 'pill-bad' };

function filterStatus(s) {
    router.get(route('tenant.packaging.orders.index', { tenant: slug }), { status: s === 'all' ? '' : s }, { preserveState: true });
}

function updateStatus(order, status) {
    router.patch(route('tenant.packaging.orders.update-status', { tenant: slug, id: order.id }), { status }, { preserveScroll: true });
}

function goToPage(url) {
    if (!url) return;
    try { const p = new URL(url); router.get(p.pathname + p.search, {}, { preserveState: true, preserveScroll: true }); }
    catch (e) { router.visit(url, { preserveState: true, preserveScroll: true }); }
}
</script>

<template>
<Head title="Packaging — Purchase Orders" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Purchase Orders</h2>
            <p class="text-[12px] text-ink-3 mt-1">Order packaging materials from suppliers</p>
        </div>
        <Link :href="route('tenant.packaging.orders.create', { tenant: slug })" class="btn btn-primary">
            <Plus :size="14" /> New PO
        </Link>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <KpiCard label="Total POs" :value="totals.total" format="number" />
        <KpiCard label="Pending" :value="totals.pending" format="number" />
        <KpiCard label="Total Value" :value="totals.total_value" format="currency" />
    </div>

    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3 border-b border-frost-1 flex items-center gap-3">
            <Filter :size="14" class="text-ink-3" />
            <button v-for="s in statuses" :key="s"
                class="px-2.5 py-1 text-[11px] font-mono rounded-full transition cursor-pointer"
                :class="(filters.status || 'all') === s ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'"
                @click="filterStatus(s)">{{ s }}</button>
        </div>

        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">PO #</th>
                    <th class="text-left px-5 py-3">Supplier</th>
                    <th class="text-left px-5 py-3">Date</th>
                    <th class="text-left px-5 py-3">Expected</th>
                    <th class="text-right px-5 py-3">Amount</th>
                    <th class="text-left px-5 py-3">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="order in orders.data" :key="order.id" class="hover:bg-brand-600/5 transition">
                    <td class="px-5 py-3 font-mono text-white font-semibold">{{ order.po_number }}</td>
                    <td class="px-5 py-3 text-ink">{{ order.supplier_name || '—' }}</td>
                    <td class="px-5 py-3 text-ink-3">{{ dateFmt(order.order_date) }}</td>
                    <td class="px-5 py-3 text-ink-3">{{ dateFmt(order.expected_date) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-white">{{ fmt(order.total_amount) }}</td>
                    <td class="px-5 py-3">
                        <span :class="statusMap[order.status] || 'pill-info'" class="pill text-[11px]">{{ order.status }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <select v-if="order.status !== 'received' && order.status !== 'cancelled'"
                                @change="updateStatus(order, $event.target.value)"
                                class="heyd2c-input py-1 text-[11px] cursor-pointer">
                            <option value="">Update status…</option>
                            <option v-for="s in ['draft','sent','received','cancelled']" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </td>
                </tr>
                <tr v-if="!orders.data?.length">
                    <td colspan="7" class="px-5 py-10 text-center text-ink-3">No packaging POs yet. Create one to get started.</td>
                </tr>
            </tbody>
        </table>

        <div v-if="orders.last_page > 1" class="px-5 py-3 border-t border-frost-1 text-[12px] text-ink-3 flex items-center justify-between">
            <span>Page {{ orders.current_page }} of {{ orders.last_page }}</span>
            <div class="flex gap-1">
                <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!orders.prev_page_url" @click="goToPage(orders.prev_page_url)">← Prev</button>
                <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!orders.next_page_url" @click="goToPage(orders.next_page_url)">Next →</button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
