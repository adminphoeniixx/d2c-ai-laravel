<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Calendar, ChevronDown, ChevronUp, BarChart3 } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    period:        { type: Object, default: () => ({ range: 'this_month' }) },
    revenue:       { type: Number, default: 0 },
    cogs:          { type: Number, default: 0 },
    expenses:      { type: Object, default: () => ({}) },
    totalExpenses: { type: Number, default: 0 },
    netProfit:     { type: Number, default: 0 },
    margin:        { type: Number, default: 0 },
    monthly:       { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const pct = (v, total) => total > 0 ? ((v / total) * 100).toFixed(1) + '%' : '0%';

const grossProfit = computed(() => props.revenue - props.cogs);

const rows = computed(() => {
    const list = [
        { label: 'Gross Revenue', value: props.revenue, type: 'income', bold: true },
        { label: 'Cost of Goods Sold (est. 35%)', value: -props.cogs, type: 'expense' },
        { label: 'Gross Profit', value: grossProfit.value, type: 'subtotal', bold: true },
        { divider: true },
    ];

    const entries = Object.entries(props.expenses || {});
    if (entries.length) {
        for (const [label, amount] of entries) {
            list.push({ label, value: -(amount || 0), type: 'expense' });
        }
    } else {
        list.push({ label: 'No expenses recorded for this period', value: 0, type: 'expense', empty: true });
    }

    list.push({ label: 'Total Operating Expenses', value: -props.totalExpenses, type: 'subtotal', bold: true });
    list.push({ divider: true });
    list.push({ label: 'Net Profit', value: props.netProfit, type: 'total', bold: true });
    list.push({ label: 'Net Margin', value: props.margin, type: 'percent', bold: true });

    return list;
});

// --- Date range controls ---
const ranges = [
    { key: 'this_month', label: 'This Month' },
    { key: 'last_month', label: 'Last Month' },
    { key: 'ytd',        label: 'YTD' },
];

const customFrom = ref(props.period?.range === 'custom' ? props.period.from : '');
const customTo   = ref(props.period?.range === 'custom' ? props.period.to   : '');

function setRange(key) {
    customFrom.value = '';
    customTo.value = '';
    router.get(`/app/${slug}/p-and-l`, { range: key }, { preserveState: true, preserveScroll: true });
}

function applyCustomRange() {
    if (!customFrom.value || !customTo.value) return;
    router.get(`/app/${slug}/p-and-l`, {
        range: 'custom',
        from: customFrom.value,
        to: customTo.value,
    }, { preserveState: true, preserveScroll: true });
}

const periodLabel = computed(() => {
    if (!props.period?.from || !props.period?.to) return '';
    const from = new Date(props.period.from).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    const to   = new Date(props.period.to).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
    return `${from} – ${to}`;
});

// --- Monthly breakdown table ---
const showMonthly = ref(false);

// Union of all expense head labels across months, ordered by total descending
const expenseHeadOrder = computed(() => {
    const totals = {};
    for (const m of props.monthly) {
        for (const [label, amount] of Object.entries(m.expenses || {})) {
            totals[label] = (totals[label] || 0) + (amount || 0);
        }
    }
    return Object.entries(totals).sort((a, b) => b[1] - a[1]).map(([label]) => label);
});

const monthlyRows = computed(() => {
    const list = [
        { label: 'Gross Revenue', key: 'revenue', type: 'income', bold: true },
        { label: 'Cost of Goods Sold (est. 35%)', key: 'cogs', negate: true, type: 'expense' },
        { label: 'Gross Profit', key: '__grossProfit', type: 'subtotal', bold: true },
        { divider: true },
    ];
    for (const head of expenseHeadOrder.value) {
        list.push({ label: head, key: `__expense:${head}`, negate: true, type: 'expense' });
    }
    list.push({ label: 'Total Operating Expenses', key: 'totalExpenses', negate: true, type: 'subtotal', bold: true });
    list.push({ divider: true });
    list.push({ label: 'Net Profit', key: 'netProfit', type: 'total', bold: true });
    list.push({ label: 'Net Margin', key: 'margin', type: 'percent', bold: true });
    return list;
});

function monthlyCellValue(month, row) {
    if (row.key === '__grossProfit') return (month.revenue || 0) - (month.cogs || 0);
    if (row.key.startsWith('__expense:')) {
        const head = row.key.slice('__expense:'.length);
        return (month.expenses && month.expenses[head]) || 0;
    }
    const raw = month[row.key] ?? 0;
    return row.negate ? -raw : raw;
}
</script>

<template>
<Head title="P&L Report" />
<TenantLayout>
    <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-white">Profit & Loss Statement</h2>
            <p class="text-[12px] text-ink-3 mt-1">Auto-calculated from orders, expenses, payment gateway, ads, logistics & payroll</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button v-for="r in ranges" :key="r.key"
                class="btn cursor-pointer"
                :class="period.range === r.key ? 'btn-primary' : 'btn-ghost'"
                @click="setRange(r.key)">
                <Calendar v-if="r.key === 'this_month'" :size="14" />
                {{ r.label }}
            </button>
            <div class="flex items-center gap-1.5">
                <input type="date" v-model="customFrom" class="heyd2c-input text-[12px] py-1.5 px-2 w-[130px]" />
                <span class="text-ink-3 text-[12px]">to</span>
                <input type="date" v-model="customTo" class="heyd2c-input text-[12px] py-1.5 px-2 w-[130px]" />
                <button class="btn btn-ghost cursor-pointer" :class="period.range === 'custom' ? 'btn-primary' : ''" @click="applyCustomRange">
                    Apply
                </button>
            </div>
        </div>
    </div>

    <p class="text-[11px] text-ink-3 font-mono mb-5">{{ periodLabel }}</p>

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
                        <td class="px-5 py-3" :class="{ 'font-semibold text-white': row.bold, 'text-ink-2 pl-8': !row.bold && row.type === 'expense', 'text-ink-3 italic': row.empty }">
                            {{ row.label }}
                        </td>
                        <td class="px-5 py-3 text-right font-mono"
                            :class="{
                                'text-emerald font-semibold': row.value > 0 && row.bold,
                                'text-rose': row.value < 0,
                                'text-ink': row.value >= 0 && !row.bold,
                                'text-[18px] font-bold': row.type === 'total',
                                'text-ink-3': row.empty,
                            }"
                        >
                            <template v-if="row.empty">—</template>
                            <template v-else-if="row.type === 'percent'">{{ row.value }}%</template>
                            <template v-else>{{ fmt(Math.abs(row.value)) }}</template>
                        </td>
                        <td class="px-5 py-3 text-right font-mono text-ink-3"
                            :class="{ 'text-[14px]': row.type === 'total' }"
                        >
                            <template v-if="row.type !== 'percent' && !row.empty">{{ pct(Math.abs(row.value), revenue) }}</template>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5 mb-5">
        <div class="kpi-card">
            <div class="kpi-label">Gross Margin</div>
            <div class="kpi-value text-emerald">{{ revenue > 0 ? ((revenue - cogs) / revenue * 100).toFixed(1) : '0.0' }}%</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Operating Margin</div>
            <div class="kpi-value text-amber">{{ revenue > 0 ? ((revenue - cogs - totalExpenses) / revenue * 100).toFixed(1) : '0.0' }}%</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Operating Expenses</div>
            <div class="kpi-value">{{ fmt(totalExpenses) }}</div>
        </div>
    </div>

    <!-- Monthly breakdown -->
    <div v-if="monthly.length > 1" class="card overflow-hidden p-0">
        <button class="w-full flex items-center justify-between px-5 py-3.5 cursor-pointer hover:bg-brand-600/5 transition"
            @click="showMonthly = !showMonthly">
            <div class="flex items-center gap-2.5">
                <BarChart3 :size="15" class="text-brand-400" />
                <span class="text-[13px] font-semibold text-white">Monthly Breakdown</span>
                <span class="text-[11px] text-ink-3">({{ monthly.length }} months)</span>
            </div>
            <ChevronDown v-if="!showMonthly" :size="16" class="text-ink-3" />
            <ChevronUp v-else :size="16" class="text-ink-3" />
        </button>

        <div v-if="showMonthly" class="overflow-x-auto border-t border-frost-1">
            <table class="w-full text-[12px]">
                <thead class="bg-bg-3">
                    <tr>
                        <th class="text-left px-5 py-2.5 text-[10px] font-mono uppercase tracking-wider text-ink-3 sticky left-0 bg-bg-3">Item</th>
                        <th v-for="m in monthly" :key="m.month" class="text-right px-4 py-2.5 text-[10px] font-mono uppercase tracking-wider text-ink-3 whitespace-nowrap">
                            {{ m.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, i) in monthlyRows" :key="i">
                        <tr v-if="row.divider" class="border-t border-frost-2">
                            <td :colspan="monthly.length + 1" class="py-1"></td>
                        </tr>
                        <tr v-else class="border-b border-frost-1 last:border-0"
                            :class="{ 'bg-brand-600/5': row.type === 'total' }">
                            <td class="px-5 py-2.5 sticky left-0 bg-bg-2"
                                :class="{ 'font-semibold text-white': row.bold, 'text-ink-2 pl-8': !row.bold && row.type === 'expense' }">
                                {{ row.label }}
                            </td>
                            <td v-for="m in monthly" :key="m.month" class="px-4 py-2.5 text-right font-mono whitespace-nowrap"
                                :class="{
                                    'text-emerald font-semibold': monthlyCellValue(m, row) > 0 && row.bold && row.type !== 'percent',
                                    'text-rose': monthlyCellValue(m, row) < 0,
                                    'text-ink': monthlyCellValue(m, row) >= 0 && !row.bold,
                                    'font-bold': row.type === 'total',
                                }">
                                <template v-if="row.type === 'percent'">{{ m.margin }}%</template>
                                <template v-else>{{ fmt(Math.abs(monthlyCellValue(m, row))) }}</template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</TenantLayout>
</template>
