<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, TrendingUp, TrendingDown } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    material:     { type: Object, required: true },
    transactions: { type: Object, default: () => ({ data: [] }) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt  = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const fmtQ = (v, u) => (parseFloat(v) || 0).toFixed(3).replace(/\.?0+$/, '') + ' ' + (u || '');
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

function goToPage(url) {
    if (!url) return;
    try { const p = new URL(url); router.get(p.pathname + p.search, {}, { preserveState: true, preserveScroll: true }); }
    catch (e) { router.visit(url, { preserveState: true, preserveScroll: true }); }
}
</script>

<template>
<Head :title="`${material.name} — Transactions`" />
<TenantLayout>
    <div class="flex items-center gap-3 mb-6">
        <button @click="router.visit(route('tenant.raw-materials.index', { tenant: slug }))"
                class="text-ink-3 hover:text-white transition cursor-pointer">
            <ArrowLeft :size="18" />
        </button>
        <div>
            <h2 class="text-[20px] font-bold text-white">{{ material.name }}</h2>
            <p class="text-[12px] text-ink-3 mt-0.5">
                Current Stock: <span class="text-white font-semibold">{{ fmtQ(material.quantity, material.unit) }}</span>
                · Cost/Unit: <span class="text-white">{{ fmt(material.cost_per_unit) }}</span>
                · Value: <span class="text-emerald-400">{{ fmt(material.quantity * material.cost_per_unit) }}</span>
                <span v-if="material.supplier"> · {{ material.supplier }}</span>
            </p>
        </div>
    </div>

    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3 border-b border-frost-1 flex items-center justify-between">
            <span class="text-[12px] text-ink-3">Transaction History</span>
            <button @click="router.visit(route('tenant.raw-materials.index', { tenant: slug }))"
                    class="btn btn-primary btn-sm cursor-pointer">
                + Add Transaction
            </button>
        </div>

        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">Date</th>
                    <th class="text-left px-5 py-3">Type</th>
                    <th class="text-right px-5 py-3">Quantity</th>
                    <th class="text-right px-5 py-3">Cost/Unit</th>
                    <th class="text-right px-5 py-3">Total Cost</th>
                    <th class="text-left px-5 py-3">Reason</th>
                    <th class="text-left px-5 py-3">Reference</th>
                    <th class="text-left px-5 py-3">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="tx in transactions.data" :key="tx.id" class="hover:bg-brand-600/5 transition">
                    <td class="px-5 py-3 text-ink-3">{{ dateFmt(tx.transaction_date) }}</td>
                    <td class="px-5 py-3">
                        <span class="flex items-center gap-1.5 font-medium"
                            :class="tx.type === 'in' ? 'text-emerald-400' : 'text-rose-400'">
                            <TrendingUp v-if="tx.type === 'in'" :size="13" />
                            <TrendingDown v-else :size="13" />
                            {{ tx.type === 'in' ? 'Stock In' : 'Stock Out' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right font-mono"
                        :class="tx.type === 'in' ? 'text-emerald-400' : 'text-rose-400'">
                        {{ tx.type === 'in' ? '+' : '-' }}{{ fmtQ(tx.quantity, material.unit) }}
                    </td>
                    <td class="px-5 py-3 text-right font-mono text-ink-2">{{ fmt(tx.cost_per_unit) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-white">{{ fmt(tx.total_cost) }}</td>
                    <td class="px-5 py-3 text-ink-2 text-[11px]">{{ tx.reason?.replace('_', ' ') || '—' }}</td>
                    <td class="px-5 py-3 text-ink-3 font-mono text-[11px]">{{ tx.reference || '—' }}</td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ tx.notes || '—' }}</td>
                </tr>
                <tr v-if="!transactions.data?.length">
                    <td colspan="8" class="px-5 py-10 text-center text-ink-3">No transactions yet. Add stock in/out from the Raw Materials list.</td>
                </tr>
            </tbody>
        </table>

        <div v-if="transactions.last_page > 1" class="px-5 py-3 border-t border-frost-1 text-[12px] text-ink-3 flex items-center justify-between">
            <span>Page {{ transactions.current_page }} of {{ transactions.last_page }}</span>
            <div class="flex gap-1">
                <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!transactions.prev_page_url" @click="goToPage(transactions.prev_page_url)">← Prev</button>
                <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!transactions.next_page_url" @click="goToPage(transactions.next_page_url)">Next →</button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
