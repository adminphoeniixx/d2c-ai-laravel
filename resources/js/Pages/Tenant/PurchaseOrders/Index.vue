<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Filter, Package } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    orders: { type: Object, default: () => ({ data: [] }) },
    totals: { type: Object, default: () => ({ total: 0, pending: 0, total_value: 0 }) },
    filters: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const statusMap = { draft: 'pill-info', sent: 'pill-good', partial: 'pill-info', received: 'pill-good', cancelled: 'pill-bad' };
const statuses = ['all', 'draft', 'sent', 'partial', 'received', 'cancelled'];

function filterStatus(s) {
    router.get(route('tenant.purchase-orders.index', { tenant: slug }), { status: s === 'all' ? '' : s }, { preserveState: true });
}
</script>

<template>
<Head title="Purchase Orders" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Purchase Orders</h2>
            <p class="text-[12px] text-ink-3 mt-1">Manage vendor purchase orders</p>
        </div>
        <Link :href="route('tenant.purchase-orders.create', { tenant: slug })" class="btn btn-primary"><Plus :size="14" /> New PO</Link>
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
                    <th class="text-left px-5 py-3">Vendor</th>
                    <th class="text-left px-5 py-3">Date</th>
                    <th class="text-left px-5 py-3">Expected</th>
                    <th class="text-right px-5 py-3">Amount</th>
                    <th class="text-left px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="po in orders.data" :key="po.id" class="hover:bg-brand-600/5 transition cursor-pointer"
                    @click="router.visit(route('tenant.purchase-orders.show', { tenant: slug, id: po.id }))">
                    <td class="px-5 py-3 font-mono font-semibold text-brand-300">{{ po.po_number }}</td>
                    <td class="px-5 py-3 text-white">{{ po.vendor?.name }}</td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ dateFmt(po.order_date) }}</td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ dateFmt(po.expected_date) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-white">{{ fmt(po.total_amount) }}</td>
                    <td class="px-5 py-3"><span class="pill" :class="statusMap[po.status]">{{ po.status }}</span></td>
                </tr>
                <tr v-if="!orders.data?.length">
                    <td colspan="6" class="px-5 py-8 text-center text-ink-3">No purchase orders yet.</td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
