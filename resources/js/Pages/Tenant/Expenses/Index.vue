<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Plus, Receipt, X, Filter } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    expenses: { type: Object, default: () => ({ data: [
        { id: 1, category: 'ads', label: 'Meta Ads · Apr 2026', amount: 65000, source: 'manual', occurred_at: '2026-04-15', currency: 'INR' },
        { id: 2, category: 'ads', label: 'Google Ads · Apr 2026', amount: 42000, source: 'manual', occurred_at: '2026-04-14', currency: 'INR' },
        { id: 3, category: 'payroll', label: 'Payroll · Apr 2026', amount: 185000, source: 'manual', occurred_at: '2026-04-01', currency: 'INR' },
        { id: 4, category: 'shipping', label: 'Shipping & Fulfillment · Apr', amount: 38000, source: 'manual', occurred_at: '2026-04-10', currency: 'INR' },
        { id: 5, category: 'tools', label: 'SaaS Tools · Apr', amount: 9500, source: 'manual', occurred_at: '2026-04-05', currency: 'INR' },
        { id: 6, category: 'rent', label: 'Office Rent · Apr', amount: 35000, source: 'manual', occurred_at: '2026-04-01', currency: 'INR' },
        { id: 7, category: 'ads', label: 'Meta Ads · Mar 2026', amount: 58000, source: 'manual', occurred_at: '2026-03-15', currency: 'INR' },
        { id: 8, category: 'payroll', label: 'Payroll · Mar 2026', amount: 185000, source: 'manual', occurred_at: '2026-03-01', currency: 'INR' },
    ] }) },
    filters: { type: Object, default: () => ({}) },
    totals: { type: Object, default: () => ({ this_month: 374500 }) },
});

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v));
const showForm = ref(false);

const categories = ['ads', 'payroll', 'inventory', 'shipping', 'tools', 'rent', 'other'];
const categoryColors = {
    ads: 'pill-info', payroll: 'pill-good', inventory: 'pill-info',
    shipping: 'pill-info', tools: 'pill-info', rent: 'pill-bad', other: 'pill-info'
};

const form = useForm({
    category: 'ads',
    label: '',
    amount: '',
    occurred_at: new Date().toISOString().split('T')[0],
    source: 'manual',
});

function submit() {
    // form.post(route('tenant.expenses.store', { tenant: ... }));
    showForm.value = false;
    form.reset();
}
</script>

<template>
<Head title="Expenses" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Expenses</h2>
            <p class="text-[12px] text-ink-3 mt-1">Track all your operating costs</p>
        </div>
        <button class="btn btn-primary" @click="showForm = true">
            <Plus :size="15" /> Add Expense
        </button>
    </div>

    <!-- KPI row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <KpiCard label="This Month" :value="totals.this_month" format="currency" :delta="-9" tone="bad" />
        <KpiCard label="Avg Monthly" :value="312000" format="currency" />
        <KpiCard label="Top Category" value="Payroll" format="number" />
    </div>

    <!-- Add form modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showForm = false">
        <div class="card w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">Add Expense</h3>
                <button class="text-ink-3 hover:text-ink" @click="showForm = false"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submit" class="space-y-3">
                <div>
                    <label class="pulsara-label">Category</label>
                    <select v-model="form.category" class="pulsara-input">
                        <option v-for="c in categories" :key="c" :value="c">{{ c.charAt(0).toUpperCase() + c.slice(1) }}</option>
                    </select>
                </div>
                <div>
                    <label class="pulsara-label">Label</label>
                    <input v-model="form.label" class="pulsara-input" placeholder="Meta Ads · April 2026" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="pulsara-label">Amount (₹)</label>
                        <input v-model="form.amount" type="number" class="pulsara-input" placeholder="12000" />
                    </div>
                    <div>
                        <label class="pulsara-label">Date</label>
                        <input v-model="form.occurred_at" type="date" class="pulsara-input" />
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-full py-2.5">Save Expense</button>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3 border-b border-frost-1 flex items-center gap-3">
            <Filter :size="14" class="text-ink-3" />
            <button v-for="c in ['all', ...categories]" :key="c"
                class="px-2.5 py-1 text-[11px] font-mono rounded-full transition"
                :class="(filters.category || 'all') === c ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'"
            >{{ c }}</button>
        </div>
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">Label</th>
                    <th class="text-left px-5 py-3">Category</th>
                    <th class="text-left px-5 py-3">Source</th>
                    <th class="text-right px-5 py-3">Amount</th>
                    <th class="text-left px-5 py-3">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="e in expenses.data" :key="e.id" class="hover:bg-brand-600/5 transition">
                    <td class="px-5 py-3 font-medium text-white">{{ e.label }}</td>
                    <td class="px-5 py-3"><span class="pill" :class="categoryColors[e.category] || 'pill-info'">{{ e.category }}</span></td>
                    <td class="px-5 py-3 text-ink-3 font-mono text-[11px]">{{ e.source }}</td>
                    <td class="px-5 py-3 text-right font-mono text-rose">{{ fmt(e.amount) }}</td>
                    <td class="px-5 py-3 text-ink-3">{{ e.occurred_at }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
