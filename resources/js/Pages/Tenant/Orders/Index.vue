<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import { Search, Download, Tag, Truck, RefreshCw, Trash2, X } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    orders:  { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    totals:  { type: Object, default: () => ({}) },
});

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const q = ref(props.filters.q || '');
const activeStatus = ref(props.filters.status || 'all');
const activeProvider = ref(props.filters.provider || 'all');
const activeRange = ref(props.filters.range || (props.filters.from ? 'custom' : 'all'));
const fromDate = ref(props.filters.from || '');
const toDate = ref(props.filters.to || '');
const selected = ref([]);
const selectAll = ref(false);
let searchTimeout = null;

// Auto-sync
const syncing = ref(false);
const syncToast = ref(null);

onMounted(() => { autoSync(); });

function autoSync() {
    syncing.value = true;
    syncToast.value = null;
    fetch(route('tenant.orders.auto-sync', { tenant: slug }), {
        method: 'POST',
        headers: {
            'X-XSRF-TOKEN': document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]?.replace(/%3D/g, '=') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(r => r.json())
    .then(data => {
        syncing.value = false;
        syncToast.value = { type: data.new_orders > 0 ? 'success' : 'info', message: data.message };
        if (data.new_orders > 0) router.reload();
        setTimeout(() => { syncToast.value = null; }, 5000);
    })
    .catch(() => {
        syncing.value = false;
        syncToast.value = { type: 'error', message: 'Sync failed' };
        setTimeout(() => { syncToast.value = null; }, 5000);
    });
}

function applyFilters(overrides = {}) {
    const params = {
        q: q.value || undefined,
        status: activeStatus.value !== 'all' ? activeStatus.value : undefined,
        provider: activeProvider.value !== 'all' ? activeProvider.value : undefined,
        range: (activeRange.value !== 'all' && activeRange.value !== 'custom') ? activeRange.value : undefined,
        from: fromDate.value || undefined,
        to: toDate.value || undefined,
        ...overrides,
    };
    Object.keys(params).forEach(k => params[k] === undefined && delete params[k]);
    selected.value = [];
    selectAll.value = false;
    router.get(route('tenant.orders', { tenant: slug }), params, { preserveState: true, preserveScroll: true });
}

function filterStatus(s) { activeStatus.value = s; applyFilters(); }
function filterProvider(p) { activeProvider.value = p; applyFilters(); }
function filterRange(r) {
    activeRange.value = r;
    fromDate.value = '';
    toDate.value = '';
    applyFilters({ from: undefined, to: undefined });
}
function applyCustomDates() {
    if (fromDate.value) {
        activeRange.value = 'custom';
        applyFilters({ range: undefined });
    }
}
watch(q, () => { clearTimeout(searchTimeout); searchTimeout = setTimeout(() => applyFilters(), 400); });

function goToPage(url) {
    if (!url) return;
    try { const p = new URL(url); router.get(p.pathname + p.search, {}, { preserveState: true, preserveScroll: true }); }
    catch (e) { router.visit(url, { preserveState: true, preserveScroll: true }); }
}

function toggleSelectAll() {
    selectAll.value = !selectAll.value;
    selected.value = selectAll.value ? props.orders.data.map(o => o.id) : [];
}

function toggleSelect(id) {
    const idx = selected.value.indexOf(id);
    if (idx >= 0) selected.value.splice(idx, 1);
    else selected.value.push(id);
}

function deleteOrder(id, number) {
    if (confirm(`Delete order ${number}?`)) {
        router.delete(route('tenant.orders.destroy', { tenant: slug, order: id }));
    }
}

function bulkDelete() {
    if (!selected.value.length) return;
    if (confirm(`Delete ${selected.value.length} selected orders?`)) {
        router.post(route('tenant.orders.bulk-delete', { tenant: slug }), {
            ids: selected.value,
        }, {
            onSuccess: () => { selected.value = []; selectAll.value = false; },
        });
    }
}

const statusMap = {
    paid: { class: 'bg-emerald-500/15 text-emerald-400', label: 'Paid' },
    fulfilled: { class: 'bg-emerald-500/15 text-emerald-400', label: 'Fulfilled' },
    completed: { class: 'bg-emerald-500/15 text-emerald-400', label: 'Completed' },
    pending: { class: 'bg-amber-500/15 text-amber-400', label: 'Pending' },
    processing: { class: 'bg-blue-500/15 text-blue-400', label: 'Processing' },
    refunded: { class: 'bg-rose-500/15 text-rose-400', label: 'Refunded' },
    cancelled: { class: 'bg-rose-500/15 text-rose-400', label: 'Cancelled' },
    failed: { class: 'bg-rose-500/15 text-rose-400', label: 'Failed' },
    'on-hold': { class: 'bg-orange-500/15 text-orange-400', label: 'On Hold' },
    on_hold: { class: 'bg-orange-500/15 text-orange-400', label: 'On Hold' },
    'order-confirmed': { class: 'bg-blue-500/15 text-blue-400', label: 'Confirmed' },
};
const providerMap = {
    shopify: { class: 'bg-emerald-500/10 text-emerald-400', label: 'Shopify' },
    woocommerce: { class: 'bg-brand-600/10 text-brand-300', label: 'WooCommerce' },
};
const ranges = [
    { key: 'today',     label: 'Today' },
    { key: 'yesterday', label: 'Yesterday' },
    { key: 'week',      label: 'This Week' },
    { key: 'month',     label: 'This Month' },
    { key: '3months', label: '3 Months' },
    { key: '6months', label: '6 Months' },
    { key: 'year', label: 'This Year' },
    { key: 'all', label: 'All Time' },
];
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
<Head title="Orders" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Orders</h2>
            <p class="text-[12px] text-ink-3 mt-1">
                <span v-if="syncing" class="text-brand-300"><RefreshCw :size="10" class="inline animate-spin mr-1" />Checking for new orders…</span>
                <span v-else>Synced from Shopify & WooCommerce</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <button class="btn btn-ghost btn-sm flex items-center gap-1 cursor-pointer" :disabled="syncing" @click="autoSync">
                <RefreshCw :size="12" :class="{ 'animate-spin': syncing }" /> Sync
            </button>
            <button v-if="selected.length" class="btn btn-ghost btn-sm text-rose-400 flex items-center gap-1 cursor-pointer" @click="bulkDelete">
                <Trash2 :size="12" /> Delete {{ selected.length }}
            </button>
            <button class="btn btn-ghost btn-sm" @click="window.location.href = route('tenant.orders.export', { tenant: slug })"><Download :size="12" /> Export</button>
        </div>
    </div>

    <!-- Sync Toast -->
    <transition enter-active-class="transition ease-out duration-300" enter-from-class="translate-y-2 opacity-0" enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <div v-if="syncToast" class="mb-4 px-4 py-2.5 rounded-xl border text-[13px] flex items-center justify-between"
            :class="syncToast.type === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : syncToast.type === 'error' ? 'bg-rose-500/10 border-rose-500/30 text-rose-400' : 'bg-brand-600/10 border-brand-600/30 text-brand-300'">
            <span>{{ syncToast.message }}</span>
            <button @click="syncToast = null" class="cursor-pointer"><X :size="14" /></button>
        </div>
    </transition>

    <!-- Date Range Filter -->
    <div class="flex items-center gap-1.5 mb-4 overflow-x-auto flex-wrap">
        <button v-for="r in ranges" :key="r.key" @click="filterRange(r.key)"
            class="px-3 py-1.5 text-[11px] font-mono rounded-full transition cursor-pointer whitespace-nowrap"
            :class="activeRange === r.key ? 'bg-brand-600/20 text-brand-300 border border-brand-600/40' : 'text-ink-3 hover:text-ink border border-transparent'">
            {{ r.label }}
        </button>
        <span class="text-frost-2 mx-1">|</span>
        <div class="flex items-center gap-1.5">
            <input v-model="fromDate" type="date" class="heyd2c-input !py-1 !px-2 text-[11px] w-[120px]" @change="applyCustomDates" />
            <span class="text-ink-3 text-[11px]">to</span>
            <input v-model="toDate" type="date" class="heyd2c-input !py-1 !px-2 text-[11px] w-[120px]" @change="applyCustomDates" />
        </div>
        <button v-if="activeRange !== 'all'" @click="filterRange('all')" class="text-[10px] text-ink-3 hover:text-white cursor-pointer ml-1">✕ Clear</button>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-3">
        <div class="card text-center">
            <div class="text-[10px] text-ink-3 uppercase tracking-wider font-mono">Gross Orders</div>
            <div class="text-[22px] font-bold text-white mt-1">{{ totals.gross_orders || 0 }}</div>
        </div>
        <div class="card text-center">
            <div class="text-[10px] text-ink-3 uppercase tracking-wider font-mono">Gross Revenue</div>
            <div class="text-[22px] font-bold text-white mt-1">{{ fmt(totals.gross_revenue) }}</div>
        </div>
        <div class="card text-center">
            <div class="text-[10px] text-ink-3 uppercase tracking-wider font-mono">Net Revenue</div>
            <div class="text-[22px] font-bold text-emerald-400 mt-1">{{ fmt(totals.net_revenue) }}</div>
            <div class="text-[9px] text-ink-3">After refunds & cancellations</div>
        </div>
        <div class="card text-center">
            <div class="text-[10px] text-ink-3 uppercase tracking-wider font-mono flex items-center justify-center gap-1"><Tag :size="10" /> Discounts</div>
            <div class="text-[22px] font-bold text-amber-400 mt-1">{{ fmt(totals.total_discount) }}</div>
        </div>
        <div class="card text-center">
            <div class="text-[10px] text-ink-3 uppercase tracking-wider font-mono flex items-center justify-center gap-1"><Truck :size="10" /> Shipping</div>
            <div class="text-[22px] font-bold text-blue-400 mt-1">{{ fmt(totals.total_shipping) }}</div>
        </div>
    </div>

    <!-- Fulfillment tiles -->
    <div class="grid grid-cols-2 gap-3 mb-3">
        <button @click="filterStatus('fulfilled')"
            class="rounded-xl bg-bg-2 border px-4 py-3 text-center cursor-pointer hover:border-brand-600/40 transition"
            :class="activeStatus === 'fulfilled' ? 'border-emerald-500/60 bg-emerald-500/5' : 'border-frost-1'">
            <div class="text-[24px] font-bold text-emerald-400">{{ totals.fulfilled || 0 }}</div>
            <div class="text-[11px] text-ink-3 uppercase tracking-wider mt-0.5">Fulfilled</div>
        </button>
        <button @click="filterStatus('unfulfilled')"
            class="rounded-xl bg-bg-2 border px-4 py-3 text-center cursor-pointer hover:border-brand-600/40 transition"
            :class="activeStatus === 'unfulfilled' ? 'border-amber-500/60 bg-amber-500/5' : 'border-frost-1'">
            <div class="text-[24px] font-bold text-amber-400">{{ totals.unfulfilled || 0 }}</div>
            <div class="text-[11px] text-ink-3 uppercase tracking-wider mt-0.5">Unfulfilled</div>
        </button>
    </div>

    <!-- Status small boxes -->
    <div class="grid grid-cols-3 sm:grid-cols-7 gap-2 mb-5">
        <button v-for="s in [{k:'paid',c:'text-emerald-400'},{k:'pending',c:'text-amber-400'},{k:'cancelled',c:'text-rose-400'},{k:'refunded',c:'text-rose-400'},{k:'failed',c:'text-rose-400'},{k:'on_hold',c:'text-orange-400'}]"
            :key="s.k" @click="filterStatus(s.k)" class="rounded-xl bg-bg-2 border border-frost-1 px-2 py-2 text-center cursor-pointer hover:border-brand-600/40 transition"
            :class="activeStatus === s.k ? 'border-brand-600/60' : ''">
            <div class="text-[14px] font-bold" :class="s.c">{{ totals[s.k] || 0 }}</div>
            <div class="text-[8px] text-ink-3 uppercase">{{ s.k.replace('_',' ') }}</div>
        </button>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3 border-b border-frost-1 flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <div class="relative flex-1 max-w-sm">
                <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
                <input v-model="q" placeholder="Search orders, customers…" class="heyd2c-input pl-9 text-[12px]" />
            </div>
            <div class="flex items-center gap-1 flex-wrap">
                <button v-for="s in ['all','paid','fulfilled','unfulfilled','pending','cancelled','refunded','failed']" :key="s"
                    @click="filterStatus(s)" class="px-2.5 py-1 text-[11px] font-mono rounded-full transition cursor-pointer"
                    :class="activeStatus === s ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'">{{ s }}</button>
                <span class="text-frost-2 mx-1">|</span>
                <button v-for="p in ['all','shopify','woocommerce']" :key="p" @click="filterProvider(p)"
                    class="px-2.5 py-1 text-[11px] font-mono rounded-full transition cursor-pointer"
                    :class="activeProvider === p ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'">{{ p === 'woocommerce' ? 'woo' : p }}</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                    <tr>
                        <th class="text-left px-3 py-3 w-8"><input type="checkbox" :checked="selectAll" @change="toggleSelectAll" class="cursor-pointer" /></th>
                        <th class="text-left px-3 py-3">Order</th>
                        <th class="text-left px-3 py-3">Customer</th>
                        <th class="text-left px-3 py-3">Status</th>
                        <th class="text-left px-3 py-3">Fulfillment</th>
                        <th class="text-left px-3 py-3">Delivery</th>
                        <th class="text-left px-3 py-3">Source</th>
                        <th class="text-right px-3 py-3">Items</th>
                        <th class="text-right px-3 py-3">Discount</th>
                        <th class="text-right px-3 py-3">Shipping</th>
                        <th class="text-right px-3 py-3">Amount</th>
                        <th class="text-left px-3 py-3">Date</th>
                        <th class="text-right px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-frost-1">
                    <tr v-for="o in orders.data" :key="o.id" class="hover:bg-brand-600/5 transition"
                        :class="selected.includes(o.id) ? 'bg-brand-600/10' : ''">
                        <td class="px-3 py-3" @click.stop><input type="checkbox" :checked="selected.includes(o.id)" @change="toggleSelect(o.id)" class="cursor-pointer" /></td>
                        <td class="px-3 py-3 font-mono font-semibold text-brand-300 cursor-pointer" @click="router.visit(route('tenant.orders.show', { tenant: slug, order: o.id }))">{{ o.order_number }}</td>
                        <td class="px-3 py-3 cursor-pointer" @click="router.visit(route('tenant.orders.show', { tenant: slug, order: o.id }))">
                            <div class="font-medium text-white">{{ o.customer_name || '—' }}</div>
                            <div class="text-[11px] text-ink-3">{{ o.customer_email }}</div>
                        </td>
                        <td class="px-3 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="statusMap[o.status]?.class || 'bg-ink-3/15 text-ink-3'">{{ statusMap[o.status]?.label || o.status }}</span></td>
                        <td class="px-3 py-3">
                            <span v-if="o.fulfillment_status" class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                :class="o.fulfillment_status === 'fulfilled' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-amber-500/15 text-amber-400'">
                                {{ o.fulfillment_status }}
                            </span>
                            <span v-else class="text-[11px] text-ink-3">unfulfilled</span>
                        </td>
                        <td class="px-3 py-3">
                            <span v-if="o.delivery_status" class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                :class="{
                                    'bg-emerald-500/15 text-emerald-400': o.delivery_status === 'Delivered',
                                    'bg-rose-500/15 text-rose-400': o.delivery_status === 'Failed',
                                    'bg-blue-500/15 text-blue-400': o.delivery_status === 'In Transit',
                                    'bg-purple-500/15 text-purple-400': o.delivery_status === 'Out for Delivery',
                                    'bg-amber-500/15 text-amber-400': o.delivery_status === 'Attempted',
                                    'bg-ink-3/15 text-ink-3': !['Delivered','Failed','In Transit','Out for Delivery','Attempted'].includes(o.delivery_status),
                                }">{{ o.delivery_status }}</span>
                            <span v-else class="text-[11px] text-ink-3">—</span>
                        </td>
                        <td class="px-3 py-3"><span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="providerMap[o.provider]?.class || ''">{{ providerMap[o.provider]?.label || o.provider }}</span></td>
                        <td class="px-3 py-3 text-right font-mono text-ink-2">{{ o.line_item_count }}</td>
                        <td class="px-3 py-3 text-right font-mono" :class="parseFloat(o.total_discount) > 0 ? 'text-amber-400' : 'text-ink-3'">{{ parseFloat(o.total_discount) > 0 ? fmt(o.total_discount) : '—' }}</td>
                        <td class="px-3 py-3 text-right font-mono" :class="parseFloat(o.total_shipping) > 0 ? 'text-blue-400' : 'text-ink-3'">{{ parseFloat(o.total_shipping) > 0 ? fmt(o.total_shipping) : '—' }}</td>
                        <td class="px-3 py-3 text-right font-mono font-semibold text-white">{{ fmt(o.total_amount) }}</td>
                        <td class="px-3 py-3 text-ink-3 text-[12px]">{{ dateFmt(o.placed_at) }}</td>
                        <td class="px-3 py-3 text-right" @click.stop>
                            <button @click="deleteOrder(o.id, o.order_number)" class="text-ink-3 hover:text-rose-400 cursor-pointer " title="Delete"><Trash2 :size="13" /></button>
                        </td>
                    </tr>
                    <tr v-if="!orders.data?.length"><td colspan="11" class="px-5 py-12 text-center text-[13px] text-ink-3">No orders found</td></tr>
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-frost-1 text-[12px] text-ink-3 flex items-center justify-between">
            <span>Page {{ orders.current_page || 1 }} of {{ orders.last_page || 1 }} · {{ orders.total || 0 }} orders</span>
            <div class="flex gap-1">
                <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!orders.prev_page_url" @click="goToPage(orders.prev_page_url)">← Prev</button>
                <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!orders.next_page_url" @click="goToPage(orders.next_page_url)">Next →</button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
