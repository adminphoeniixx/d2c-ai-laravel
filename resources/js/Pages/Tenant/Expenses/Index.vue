<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Receipt, X, Filter, Megaphone, Target, Zap } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    expenses: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    totals: { type: Object, default: () => ({ this_month: 0, ads_month: 0, auto_synced: 0, manual_month: 0 }) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const showForm = ref(false);

const categories = ['ads', 'payroll', 'inventory', 'shipping', 'tools', 'rent', 'other'];
const categoryColors = { ads: 'pill-info', payroll: 'pill-good', inventory: 'pill-info', shipping: 'pill-info', tools: 'pill-info', rent: 'pill-bad', other: 'pill-info' };

const form = useForm({
    category: 'ads', label: '', amount: '', occurred_at: new Date().toISOString().split('T')[0], source: 'manual',
});

function submit() {
    form.post(route('tenant.expenses.store', { tenant: slug }), {
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
}

function filterCategory(c) {
    router.get(route('tenant.expenses', { tenant: slug }), { category: c === 'all' ? '' : c }, { preserveState: true });
}

function filterSource(s) {
    router.get(route('tenant.expenses', { tenant: slug }), { ...props.filters, source: s === 'all' ? '' : s }, { preserveState: true });
}

const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

function isAdSync(e) { return e.source === 'auto' && e.category === 'ads'; }
function platformIcon(e) {
    if (e.label?.toLowerCase().includes('meta')) return Megaphone;
    if (e.label?.toLowerCase().includes('google')) return Target;
    return Zap;
}
function platformColor(e) {
    if (e.label?.toLowerCase().includes('meta')) return 'text-[#1877F2]';
    if (e.label?.toLowerCase().includes('google')) return 'text-[#4285F4]';
    return 'text-brand-400';
}
</script>

<template>
<Head title="Expenses" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Expenses</h2>
            <p class="text-[12px] text-ink-3 mt-1">Track all your operating costs · Auto-synced ad spend included</p>
        </div>
        <button class="btn btn-primary" @click="showForm = true"><Plus :size="15" /> Add Expense</button>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">
        <KpiCard label="This Month" :value="totals.this_month" format="currency" />
        <KpiCard label="Ad Spend" :value="totals.ads_month" format="currency" />
        <KpiCard label="Auto-Synced" :value="totals.auto_synced" format="currency" />
        <KpiCard label="Manual" :value="totals.manual_month" format="currency" />
    </div>

    <!-- Add form modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showForm = false">
        <div class="card w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">Add Expense</h3>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showForm = false"><X :size="18" /></button>
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
                    <input v-model="form.label" class="pulsara-input" placeholder="Meta Ads · May 2026" />
                    <div v-if="form.errors.label" class="mt-1 text-[11px] text-rose">{{ form.errors.label }}</div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="pulsara-label">Amount (₹)</label>
                        <input v-model="form.amount" type="number" class="pulsara-input" placeholder="12000" />
                        <div v-if="form.errors.amount" class="mt-1 text-[11px] text-rose">{{ form.errors.amount }}</div>
                    </div>
                    <div>
                        <label class="pulsara-label">Date</label>
                        <input v-model="form.occurred_at" type="date" class="pulsara-input" />
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-full py-2.5" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save Expense' }}</button>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3 border-b border-frost-1 flex flex-wrap items-center gap-3">
            <Filter :size="14" class="text-ink-3" />
            <button v-for="c in ['all', ...categories]" :key="c"
                class="px-2.5 py-1 text-[11px] font-mono rounded-full transition cursor-pointer"
                :class="(filters.category || 'all') === c ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'"
                @click="filterCategory(c)">{{ c }}</button>
            <span class="text-frost-1">|</span>
            <button v-for="s in ['all', 'manual', 'auto']" :key="s"
                class="px-2.5 py-1 text-[11px] font-mono rounded-full transition cursor-pointer"
                :class="(filters.source || 'all') === s ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'"
                @click="filterSource(s)">{{ s }}</button>
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
                    <td class="px-5 py-3 font-medium text-white flex items-center gap-2">
                        <component v-if="isAdSync(e)" :is="platformIcon(e)" :size="14" :class="platformColor(e)" />
                        {{ e.label }}
                    </td>
                    <td class="px-5 py-3"><span class="pill" :class="categoryColors[e.category] || 'pill-info'">{{ e.category }}</span></td>
                    <td class="px-5 py-3">
                        <span v-if="e.source === 'auto'" class="inline-flex items-center gap-1 text-[11px] font-mono text-brand-300">
                            <Zap :size="10" /> auto-synced
                        </span>
                        <span v-else class="text-ink-3 font-mono text-[11px]">{{ e.source }}</span>
                    </td>
                    <td class="px-5 py-3 text-right font-mono text-rose">{{ fmt(e.amount) }}</td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ dateFmt(e.occurred_at) }}</td>
                </tr>
                <tr v-if="!expenses.data?.length">
                    <td colspan="5" class="px-5 py-8 text-center text-ink-3">No expenses yet. Click "Add Expense" to get started, or connect Meta/Google Ads for automatic tracking.</td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
