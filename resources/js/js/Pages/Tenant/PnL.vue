<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { TrendingUp, TrendingDown, Calendar } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    period: { type: Object, default: () => ({}) },
    revenue: { type: Number, default: 824340 },
    cogs: { type: Number, default: 288519 },
    expenses: { type: Object, default: () => ({ ads: 107000, payroll: 185000, shipping: 38000, tools: 9500, rent: 35000 }) },
    totalExpenses: { type: Number, default: 374500 },
    netProfit: { type: Number, default: 161321 },
    margin: { type: Number, default: 19.6 },
});

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v));
const pct = (v, total) => total > 0 ? ((v / total) * 100).toFixed(1) + '%' : '0%';

const rows = [
    { label: 'Gross Revenue', value: props.revenue, type: 'income', bold: true },
    { label: 'Cost of Goods Sold (35%)', value: -props.cogs, type: 'expense' },
    { label: 'Gross Profit', value: props.revenue - props.cogs, type: 'subtotal', bold: true },
    { divider: true },
    { label: 'Ad Spend (Meta + Google)', value: -(props.expenses.ads || 0), type: 'expense' },
    { label: 'Payroll', value: -(props.expenses.payroll || 0), type: 'expense' },
    { label: 'Shipping & Fulfillment', value: -(props.expenses.shipping || 0), type: 'expense' },
    { label: 'SaaS Tools', value: -(props.expenses.tools || 0), type: 'expense' },
    { label: 'Rent & Utilities', value: -(props.expenses.rent || 0), type: 'expense' },
    { label: 'Total Operating Expenses', value: -props.totalExpenses, type: 'subtotal', bold: true },
    { divider: true },
    { label: 'Net Profit', value: props.netProfit, type: 'total', bold: true },
    { label: 'Net Margin', value: props.margin, type: 'percent', bold: true },
];
</script>

<template>
<Head title="P&L Report" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Profit & Loss Statement</h2>
            <p class="text-[12px] text-ink-3 mt-1">Current month · Auto-calculated from orders + expenses</p>
        </div>
        <div class="flex items-center gap-2">
            <button class="btn btn-ghost"><Calendar :size="14" /> This Month</button>
            <button class="btn btn-ghost">Last Month</button>
            <button class="btn btn-ghost">YTD</button>
        </div>
    </div>

    <div class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3">
                <tr>
                    <th class="text-left px-5 py-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">Item</th>
                    <th class="text-right px-5 py-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">Amount</th>
                    <th class="text-right px-5 py-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">% of Revenue</th>
                </tr>
            </thead>
            <tbody>
                <template v-for="(row, i) in rows" :key="i">
                    <tr v-if="row.divider" class="border-t border-frost-2">
                        <td colspan="3" class="py-1"></td>
                    </tr>
                    <tr v-else
                        class="border-b border-frost-1 transition"
                        :class="{
                            'bg-brand-600/5': row.type === 'total',
                            'hover:bg-brand-600/5': row.type !== 'total'
                        }"
                    >
                        <td class="px-5 py-3" :class="{ 'font-semibold text-white': row.bold, 'text-ink-2 pl-8': !row.bold && row.type === 'expense' }">
                            {{ row.label }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono"
                            :class="{
                                'text-emerald font-semibold': row.value > 0 && row.bold,
                                'text-rose': row.value < 0,
                                'text-ink': row.value >= 0 && !row.bold,
                                'text-[18px] font-bold': row.type === 'total',
                            }"
                        >
                            <template v-if="row.type === 'percent'">{{ row.value }}%</template>
                            <template v-else>{{ fmt(Math.abs(row.value)) }}</template>
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-ink-3"
                            :class="{ 'text-[14px]': row.type === 'total' }"
                        >
                            <template v-if="row.type !== 'percent'">{{ pct(Math.abs(row.value), revenue) }}</template>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
        <div class="kpi-card">
            <div class="kpi-label">Gross Margin</div>
            <div class="kpi-value text-emerald">{{ revenue > 0 ? ((revenue - cogs) / revenue * 100).toFixed(1) : '0.0' }}%</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Operating Margin</div>
            <div class="kpi-value text-amber">{{ revenue > 0 ? ((revenue - cogs - totalExpenses) / revenue * 100).toFixed(1) : '0.0' }}%</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Ad Spend / Revenue</div>
            <div class="kpi-value">{{ revenue > 0 ? ((expenses.ads || 0) / revenue * 100).toFixed(1) : '0.0' }}%</div>
        </div>
    </div>
</TenantLayout>
</template>
