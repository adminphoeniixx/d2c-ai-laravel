<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Upload, ArrowUpRight, ArrowDownRight, Search } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    account: { type: Object, default: () => ({}) },
    transactions: { type: Object, default: () => ({ data: [] }) },
    stats: { type: Object, default: () => ({}) },
    vendors: { type: Array, default: () => [] },
    months: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const fmtDec = (v) => '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const selectedMonth = ref(props.filters.month || 'all');
const selectedVendor = ref(props.filters.vendor || 'all');
const selectedType = ref(props.filters.type || 'all');
const fromDate = ref(props.filters.from || '');
const toDate = ref(props.filters.to || '');
const showUpload = ref(false);

function applyFilters(overrides = {}) {
    const params = {
        month: selectedMonth.value !== 'all' ? selectedMonth.value : undefined,
        vendor: selectedVendor.value !== 'all' ? selectedVendor.value : undefined,
        type: selectedType.value !== 'all' ? selectedType.value : undefined,
        from: fromDate.value || undefined,
        to: toDate.value || undefined,
        ...overrides,
    };
    // If custom dates set, clear month
    if (params.from || params.to) delete params.month;
    Object.keys(params).forEach(k => params[k] === undefined && delete params[k]);
    router.get(route('tenant.banking.ledger', { tenant: slug, accountId: props.account.id }), params, { preserveState: true });
}

function filterMonth(m) {
    selectedMonth.value = m;
    fromDate.value = ''; toDate.value = '';
    applyFilters({ from: undefined, to: undefined });
}
function filterVendor(v) { selectedVendor.value = v; applyFilters(); }
function filterType(t) { selectedType.value = t; applyFilters(); }
function applyDateRange() { if (fromDate.value) { selectedMonth.value = 'all'; applyFilters(); } }
function clearAll() {
    selectedMonth.value = 'all'; selectedVendor.value = 'all'; selectedType.value = 'all';
    fromDate.value = ''; toDate.value = '';
    applyFilters({ month: undefined, vendor: undefined, type: undefined, from: undefined, to: undefined });
}

function goToPage(url) {
    if (!url) return;
    try { const p = new URL(url); router.get(p.pathname + p.search, {}, { preserveState: true }); }
    catch (e) { router.visit(url); }
}

function uploadStatement(e) {
    const file = e.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('statement', file);
    router.post(route('tenant.banking.upload-statement', { tenant: slug, accountId: props.account.id }), formData, {
        forceFormData: true, onSuccess: () => { showUpload.value = false; },
    });
}

const categoryColors = {
    'Logistics': 'text-cyan-400', 'Payment Gateway': 'text-orange-400', 'Ads': 'text-rose-400',
    'Platform Fee': 'text-violet-400', 'Salary': 'text-blue-400', 'GST & Tax': 'text-amber-400',
    'Rent': 'text-pink-400', 'Utilities': 'text-teal-400', 'Bank Charges': 'text-ink-3',
    'Transfer': 'text-ink-3', 'Refund': 'text-yellow-400', 'Sales': 'text-emerald-400',
    'Inventory': 'text-lime-400', 'Software': 'text-indigo-400', 'UPI': 'text-purple-400',
    'Miscellaneous': 'text-ink-3',
};
</script>

<template>
<Head :title="account.name + ' Ledger'" />
<TenantLayout>
    <div class="max-w-5xl">
        <div class="flex items-center gap-3 mb-5">
            <button @click="router.visit(route('tenant.banking.index', { tenant: slug }))" class="text-ink-3 hover:text-white cursor-pointer"><ArrowLeft :size="18" /></button>
            <div class="flex-1">
                <h2 class="text-[20px] font-bold text-white">{{ account.name }}</h2>
                <div class="text-[12px] text-ink-3">{{ account.bank_name }} · {{ account.account_type }}</div>
            </div>
            <label class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer">
                <Upload :size="12" /> Upload Statement
                <input type="file" accept=".csv,.txt" class="hidden" @change="uploadStatement" />
            </label>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="card text-center">
                <div class="text-[18px] font-bold text-emerald-400">{{ fmt(stats?.total_credit) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Credits ({{ stats?.credit_count || 0 }})</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-rose-400">{{ fmt(stats?.total_debit) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Debits ({{ stats?.debit_count || 0 }})</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-white">{{ fmt(Math.abs((stats?.total_credit||0) - (stats?.total_debit||0))) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Net Flow</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-brand-300">{{ transactions.total || 0 }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Transactions</div>
            </div>
        </div>

        <!-- Vendor filter chips -->
        <div v-if="vendors?.length" class="mb-3">
            <div class="text-[10px] text-ink-3 uppercase tracking-widest mb-1.5">Filter by Vendor</div>
            <div class="flex flex-wrap gap-1.5">
                <button @click="filterVendor('all')"
                    class="px-2.5 py-1 text-[11px] rounded-full cursor-pointer transition"
                    :class="selectedVendor === 'all' ? 'bg-brand-600/20 text-brand-300 border border-brand-600/40' : 'text-ink-3 hover:text-ink border border-transparent'">All</button>
                <button v-for="v in vendors" :key="v.vendor" @click="filterVendor(v.vendor)"
                    class="px-2.5 py-1 text-[11px] rounded-full cursor-pointer transition flex items-center gap-1"
                    :class="selectedVendor === v.vendor ? 'bg-brand-600/20 text-brand-300 border border-brand-600/40' : 'text-ink-3 hover:text-ink border border-frost-1'">
                    {{ v.vendor }} <span class="text-[9px] opacity-60">({{ v.count }})</span>
                </button>
            </div>
        </div>

        <!-- Month + Date + Type filters -->
        <div class="flex items-center gap-2 mb-4 flex-wrap">
            <button @click="filterMonth('all')"
                class="px-2.5 py-1 text-[11px] font-mono rounded-full cursor-pointer transition"
                :class="selectedMonth === 'all' && !fromDate ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'">All</button>
            <button v-for="m in months" :key="m" @click="filterMonth(m)"
                class="px-2.5 py-1 text-[11px] font-mono rounded-full cursor-pointer transition"
                :class="selectedMonth === m ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'">
                {{ new Date(m + '-01').toLocaleDateString('en-IN', { month: 'short', year: '2-digit' }) }}
            </button>
            <span class="text-frost-2 mx-1">|</span>
            <input v-model="fromDate" type="date" class="heyd2c-input !py-1 !px-2 text-[11px] w-[115px]" @change="applyDateRange" />
            <span class="text-ink-3 text-[11px]">to</span>
            <input v-model="toDate" type="date" class="heyd2c-input !py-1 !px-2 text-[11px] w-[115px]" @change="applyDateRange" />
            <span class="text-frost-2 mx-1">|</span>
            <button v-for="t in ['all','credit','debit']" :key="t" @click="filterType(t)"
                class="px-2.5 py-1 text-[11px] font-mono rounded-full cursor-pointer transition"
                :class="selectedType === t ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'">{{ t }}</button>
            <button v-if="selectedMonth !== 'all' || selectedVendor !== 'all' || fromDate" @click="clearAll" class="text-[10px] text-ink-3 hover:text-white cursor-pointer ml-1">✕ Clear</button>
        </div>

        <!-- Transactions Table -->
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead class="bg-bg-3 text-[9px] font-mono uppercase tracking-wider text-ink-3">
                        <tr>
                            <th class="text-left px-4 py-2.5">Date</th>
                            <th class="text-left px-3 py-2.5">Description</th>
                            <th class="text-left px-3 py-2.5">Vendor</th>
                            <th class="text-left px-3 py-2.5">Category</th>
                            <th class="text-right px-3 py-2.5">Credit</th>
                            <th class="text-right px-3 py-2.5">Debit</th>
                            <th class="text-right px-4 py-2.5">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-frost-1">
                        <tr v-for="t in transactions.data" :key="t.id" class="hover:bg-brand-600/5">
                            <td class="px-4 py-2.5 text-ink-3 whitespace-nowrap">{{ dateFmt(t.date) }}</td>
                            <td class="px-3 py-2.5 text-white text-[11px] max-w-[250px] truncate" :title="t.description">{{ t.description }}</td>
                            <td class="px-3 py-2.5 text-[11px]">
                                <button @click="filterVendor(t.vendor)" class="text-brand-300 hover:underline cursor-pointer">{{ t.vendor }}</button>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold" :class="categoryColors[t.category] || 'text-ink-3'">{{ t.category }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-right font-mono" :class="t.type === 'credit' ? 'text-emerald-400 font-bold' : 'text-ink-3'">
                                {{ t.type === 'credit' ? fmtDec(t.amount) : '' }}
                            </td>
                            <td class="px-3 py-2.5 text-right font-mono" :class="t.type === 'debit' ? 'text-rose-400 font-bold' : 'text-ink-3'">
                                {{ t.type === 'debit' ? fmtDec(t.amount) : '' }}
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-ink-2">{{ t.balance ? fmtDec(t.balance) : '—' }}</td>
                        </tr>
                        <tr v-if="!transactions.data?.length"><td colspan="7" class="px-5 py-8 text-center text-[13px] text-ink-3">No transactions for this filter</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-frost-1 text-[12px] text-ink-3 flex items-center justify-between">
                <span>Page {{ transactions.current_page || 1 }} of {{ transactions.last_page || 1 }} · {{ transactions.total || 0 }} transactions</span>
                <div class="flex gap-1">
                    <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!transactions.prev_page_url" @click="goToPage(transactions.prev_page_url)">← Prev</button>
                    <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!transactions.next_page_url" @click="goToPage(transactions.next_page_url)">Next →</button>
                </div>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
