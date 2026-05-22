<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Package, Truck, CheckCircle } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    po: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const statusMap = { draft: 'pill-info', sent: 'pill-good', partial: 'pill-info', received: 'pill-good', cancelled: 'pill-bad' };
const nextStatus = { draft: 'sent', sent: 'received', partial: 'received' };
const statusLabels = { sent: 'Mark as Sent', received: 'Mark as Received' };

function changeStatus(status) {
    if (confirm(`Change status to "${status}"?`)) {
        router.put(route('tenant.purchase-orders.status', { tenant: slug, id: props.po.id }), { status });
    }
}
</script>

<template>
<Head :title="'PO ' + po.po_number" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <Link :href="route('tenant.purchase-orders.index', { tenant: slug })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> Back</Link>
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-[20px] font-bold text-white font-mono">{{ po.po_number }}</h2>
                    <span class="pill" :class="statusMap[po.status]">{{ po.status }}</span>
                </div>
                <p class="text-[12px] text-ink-3">{{ po.vendor?.name }} · {{ dateFmt(po.order_date) }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <button v-if="nextStatus[po.status]" class="btn btn-primary" @click="changeStatus(nextStatus[po.status])">
                <CheckCircle :size="14" /> {{ statusLabels[nextStatus[po.status]] }}
            </button>
            <button v-if="po.status !== 'cancelled' && po.status !== 'received'" class="btn btn-ghost text-rose" @click="changeStatus('cancelled')">Cancel</button>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        <div class="card"><div class="pulsara-label">Vendor</div><div class="text-white text-[14px]">{{ po.vendor?.name }}</div><div v-if="po.vendor?.gstin" class="text-[11px] text-ink-3 font-mono mt-1">GSTIN: {{ po.vendor.gstin }}</div></div>
        <div class="card"><div class="pulsara-label">Expected</div><div class="text-white text-[14px]">{{ dateFmt(po.expected_date) }}</div></div>
        <div class="card"><div class="pulsara-label">Total Amount</div><div class="text-emerald text-[18px] font-mono font-bold">{{ fmt(po.total_amount) }}</div></div>
    </div>

    <!-- Items -->
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3 border-b border-frost-1"><h3 class="text-[14px] font-bold text-white">Line Items</h3></div>
        <table class="w-full text-[12px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-2.5">Product</th>
                    <th class="text-left px-5 py-2.5">SKU</th>
                    <th class="text-center px-5 py-2.5">Qty</th>
                    <th class="text-right px-5 py-2.5">Unit Price</th>
                    <th class="text-center px-5 py-2.5">Tax %</th>
                    <th class="text-right px-5 py-2.5">Tax</th>
                    <th class="text-right px-5 py-2.5">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="item in po.items" :key="item.id" class="hover:bg-brand-600/5">
                    <td class="px-5 py-2.5 text-white font-medium">{{ item.product_name }}</td>
                    <td class="px-5 py-2.5 text-ink-3 font-mono">{{ item.sku || '—' }}</td>
                    <td class="px-5 py-2.5 text-center">{{ item.quantity }}</td>
                    <td class="px-5 py-2.5 text-right font-mono">{{ fmt(item.unit_price) }}</td>
                    <td class="px-5 py-2.5 text-center text-ink-3">{{ item.tax_rate }}%</td>
                    <td class="px-5 py-2.5 text-right font-mono text-ink-3">{{ fmt(item.tax_amount) }}</td>
                    <td class="px-5 py-2.5 text-right font-mono text-white font-medium">{{ fmt(item.total_price) }}</td>
                </tr>
            </tbody>
            <tfoot class="bg-bg-3 border-t border-frost-2">
                <tr><td colspan="6" class="px-5 py-2 text-right text-ink-3 font-mono text-[11px]">Subtotal</td><td class="px-5 py-2 text-right font-mono text-ink">{{ fmt(po.subtotal) }}</td></tr>
                <tr><td colspan="6" class="px-5 py-1 text-right text-ink-3 font-mono text-[11px]">Tax</td><td class="px-5 py-1 text-right font-mono text-ink-3">{{ fmt(po.tax_amount) }}</td></tr>
                <tr class="font-semibold text-[14px]"><td colspan="6" class="px-5 py-2.5 text-right text-white font-mono">Total</td><td class="px-5 py-2.5 text-right font-mono text-emerald">{{ fmt(po.total_amount) }}</td></tr>
            </tfoot>
        </table>
    </div>

    <div v-if="po.notes" class="card mt-5"><div class="pulsara-label">Notes</div><p class="text-[13px] text-ink-2 mt-1">{{ po.notes }}</p></div>
</TenantLayout>
</template>
