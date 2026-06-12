<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, ShoppingBag } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    orders: { type: Object, default: () => ({ data: [] }) },
    marketplace: { type: String, default: '' },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const statusColors = {
    delivered: 'bg-emerald-500/20 text-emerald-400', shipped: 'bg-blue-500/20 text-blue-400',
    approved: 'bg-teal-500/20 text-teal-400', pending: 'bg-amber-500/20 text-amber-400',
    cancelled: 'bg-rose-500/20 text-rose-400', returned: 'bg-red-500/20 text-red-400',
};
</script>

<template>
<Head :title="marketplace.charAt(0).toUpperCase() + marketplace.slice(1) + ' Orders'" />
<TenantLayout>
    <div class="max-w-4xl">
        <div class="flex items-center gap-3 mb-5">
            <button @click="router.visit(route('tenant.marketplaces.index', { tenant: slug }))" class="text-ink-3 hover:text-white cursor-pointer"><ArrowLeft :size="18" /></button>
            <div>
                <h2 class="text-[20px] font-bold text-white capitalize">{{ marketplace }} Orders</h2>
                <p class="text-[12px] text-ink-3 mt-0.5">{{ orders.total || orders.data?.length || 0 }} orders</p>
            </div>
        </div>

        <div class="card overflow-hidden">
            <table class="w-full text-[12px]">
                <thead>
                    <tr class="border-b border-frost-1">
                        <th class="text-left px-4 py-3 text-ink-3 font-medium">Order ID</th>
                        <th class="text-left px-3 py-3 text-ink-3 font-medium">Customer</th>
                        <th class="text-center px-3 py-3 text-ink-3 font-medium">Date</th>
                        <th class="text-center px-3 py-3 text-ink-3 font-medium">Status</th>
                        <th class="text-right px-3 py-3 text-ink-3 font-medium">Amount</th>
                        <th class="text-right px-3 py-3 text-ink-3 font-medium">Commission</th>
                        <th class="text-right px-4 py-3 text-ink-3 font-medium">Net</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="o in orders.data" :key="o.id" class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition">
                        <td class="px-4 py-2.5">
                            <div class="text-[12px] font-mono text-white">{{ o.marketplace_order_id || o.order_number }}</div>
                            <div v-if="o.channel_sku" class="text-[9px] text-ink-3">SKU: {{ o.channel_sku }}</div>
                        </td>
                        <td class="px-3 py-2.5 text-ink-2">{{ o.customer_name || '—' }}</td>
                        <td class="text-center px-3 py-2.5 font-mono text-ink-3">{{ dateFmt(o.placed_at) }}</td>
                        <td class="text-center px-3 py-2.5">
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold"
                                :class="statusColors[o.status] || 'bg-bg-3 text-ink-3'">{{ o.status }}</span>
                        </td>
                        <td class="text-right px-3 py-2.5 font-mono text-white">{{ fmt(o.total_amount) }}</td>
                        <td class="text-right px-3 py-2.5 font-mono text-rose-400">{{ fmt(o.marketplace_commission + o.marketplace_fees) }}</td>
                        <td class="text-right px-4 py-2.5 font-mono font-bold text-emerald-400">{{ fmt(o.net_amount || (o.total_amount - o.marketplace_commission - o.marketplace_fees)) }}</td>
                    </tr>
                    <tr v-if="!orders.data?.length">
                        <td colspan="7" class="px-4 py-8 text-center text-[13px] text-ink-3">No orders yet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</TenantLayout>
</template>
