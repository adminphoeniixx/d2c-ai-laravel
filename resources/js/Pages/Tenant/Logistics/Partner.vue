<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { ArrowLeft, Upload, FileText, Truck, RotateCcw, IndianRupee, Trash2, Eye, Plug, Unplug, RefreshCw, Search, BarChart2, MapPin, AlertTriangle } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    partner:       { type: Object, default: () => ({}) },
    invoices:      { type: Array,  default: () => [] },
    shipmentStats: { type: Object, default: () => ({}) },
    invoiceStats:  { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showUpload     = ref(false);
const showApiConnect = ref(false);
const showTrack      = ref(false);
const trackResult    = ref(null);
const trackLoading   = ref(false);
const trackAwb       = ref('');
const syncing        = ref(false);
const fetching       = ref(false);
const importing      = ref(false);
const importMessage  = ref('');
const importError    = ref(false);
const csvDragging    = ref(false);
const csvShipments   = ref([]);

const filteredCsvShipments = computed(() => {
    let list = csvShipments.value;
    if (searchAwbFilter.value) list = list.filter(s => s.waybill?.toLowerCase().includes(searchAwbFilter.value.toLowerCase()));
    if (statusFilter.value)    list = list.filter(s => s.status === statusFilter.value);
    return list;
});
const fmt     = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const fmtNum  = (v) => new Intl.NumberFormat('en-IN').format(v || 0);
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

// ── Tab state (only shown for Delhivery when API connected)
const activeTab = ref('invoices');
const isDelhivery = computed(() => props.partner.slug === 'delhivery');
const tabs = computed(() => {
    if (!isDelhivery.value) return [];
    return [
        { id: 'invoices',  label: 'Invoices',         icon: FileText },
        { id: 'overview',  label: 'Overview',          icon: BarChart2 },
        { id: 'shipments', label: 'Shipments',         icon: Truck },
        { id: 'rto',       label: 'RTO Analysis',      icon: RotateCcw },
        { id: 'pincode',   label: 'Pincode Analytics', icon: MapPin },
    ];
});

// ── Analytics
const analytics     = ref(null);
const analyticsLoading = ref(false);
const dateFrom      = ref('');
const dateTo        = ref('');
const shipments     = ref([]);
const shipmentsLoading = ref(false);
const searchAwbFilter  = ref('');
const statusFilter     = ref('');

async function loadAnalytics() {
    analyticsLoading.value = true;
    try {
        const params = new URLSearchParams();
        if (dateFrom.value) params.append('from', dateFrom.value);
        if (dateTo.value)   params.append('to',   dateTo.value);
        const res = await fetch(`/app/${slug}/logistics/delhivery/order-analytics?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        analytics.value = await res.json();
    } catch(e) { analytics.value = null; }
    finally { analyticsLoading.value = false; }
}

async function loadShipments() {
    shipmentsLoading.value = true;
    try {
        const res = await fetch(`/app/${slug}/logistics/partner/${props.partner.id}?page=1`, {
            headers: { 'X-Inertia': 'true', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        const data = await res.json();
        shipments.value = data?.props?.shipmentStats ? [] : [];
    } catch(e) {}
    finally { shipmentsLoading.value = false; }
}

onMounted(() => {
    if (isDelhivery.value) loadAnalytics();
});

watch(activeTab, (tab) => {
    if (isDelhivery.value && (tab === 'overview' || tab === 'rto' || tab === 'pincode') && !analytics.value) {
        loadAnalytics();
    }
});

const pct = (a, b) => b ? ((a / b) * 100).toFixed(1) + '%' : '0%';
const statusBg = (s) => {
    if (!s) return 'bg-frost-1 text-ink-3';
    const sl = s.toLowerCase();
    if (sl.includes('deliver')) return 'bg-emerald-500/15 text-emerald-400';
    if (sl.includes('rto') || sl.includes('return')) return 'bg-rose-500/15 text-rose-400';
    if (sl.includes('transit') || sl.includes('out')) return 'bg-amber-500/15 text-amber-400';
    return 'bg-brand-600/15 text-brand-300';
};

// ── Forms
const form = useForm({
    invoice_number: '', invoice_date: '', type: 'freight', period_from: '', period_to: '',
    invoice_pdf: null, invoice_csv: null,
});

const apiForm = useForm(
    Object.fromEntries(
        Object.entries(props.partner.credential_fields || {}).map(([k]) => [k, k === 'environment' ? 'production' : ''])
    )
);

function submitUpload() {
    form.post(route('tenant.logistics.upload-invoice', { tenant: slug, partnerId: props.partner.id }), {
        forceFormData: true,
        onSuccess: () => { showUpload.value = false; form.reset(); },
    });
}

function connectApi() {
    apiForm.post(route('tenant.logistics.connect-api', { tenant: slug, partnerId: props.partner.id }), {
        onSuccess: () => { showApiConnect.value = false; },
    });
}

function disconnectApi() {
    if (confirm('Disconnect API? You can reconnect later.')) {
        router.delete(route('tenant.logistics.disconnect-api', { tenant: slug, partnerId: props.partner.id }));
    }
}

function syncTracking() {
    syncing.value = true;
    router.post(route('tenant.logistics.sync-tracking', { tenant: slug, partnerId: props.partner.id }), {}, {
        preserveScroll: true,
        onFinish: () => { syncing.value = false; },
    });
}

function fetchShipments() {
    fetching.value = true;
    router.post(route('tenant.logistics.fetch-shipments', { tenant: slug, partnerId: props.partner.id }), {}, {
        preserveScroll: true,
        onFinish: () => { fetching.value = false; },
    });
}

async function trackSingle() {
    if (!trackAwb.value) return;
    trackLoading.value = true;
    trackResult.value  = null;
    try {
        const res = await fetch(route('tenant.logistics.track', { tenant: slug, partnerId: props.partner.id }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ waybill: trackAwb.value }),
        });
        trackResult.value = await res.json();
    } catch(e) {
        trackResult.value = { error: 'Network error' };
    }
    trackLoading.value = false;
}

async function onCsvDrop(e) {
    csvDragging.value = false;
    const file = e.dataTransfer?.files[0] || e.target?.files[0];
    if (!file) return;
    importing.value = true;
    importMessage.value = '';
    importError.value = false;
    const formData = new FormData();
    formData.append('csv', file);
    formData.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '');
    try {
        const res = await fetch(`/app/${slug}/logistics/delhivery/import-csv`, {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                'Accept': 'application/json',
            },
            body: formData,
        });
        const data = await res.json();
        if (data.success) {
            importMessage.value = data.message;
            importError.value = false;
            // Reload page to refresh shipment stats
            setTimeout(() => router.reload({ preserveScroll: true }), 800);
        } else {
            importMessage.value = data.error || 'Import failed';
            importError.value = true;
        }
    } catch(err) {
        importMessage.value = 'Upload failed';
        importError.value = true;
    } finally {
        importing.value = false;
        if (e.target) e.target.value = '';
    }
}

function deleteInvoice(inv) {
    if (confirm(`Delete invoice ${inv.invoice_number}?`)) {
        router.delete(route('tenant.logistics.delete-invoice', { tenant: slug, invoiceId: inv.id }));
    }
}
</script>

<template>
<Head :title="partner.name" />
<TenantLayout>
    <div class="max-w-4xl">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-5">
            <button @click="router.visit(route('tenant.logistics.index', { tenant: slug }))" class="text-ink-3 hover:text-white cursor-pointer">
                <ArrowLeft :size="18" />
            </button>
            <div class="flex-1">
                <h2 class="text-[20px] font-bold text-white">{{ partner.name }}</h2>
                <p class="text-[12px] text-ink-3 mt-0.5">
                    <span v-if="partner.api_connected" class="text-emerald-400">API Connected ✓</span>
                    <span v-else-if="partner.has_api" class="text-amber-400">API Available — Not Connected</span>
                    <span v-else class="text-ink-3">Manual (CSV upload only)</span>
                </p>
            </div>
            <div class="flex items-center gap-2">
                <template v-if="partner.api_connected">
                    <button @click="fetchShipments" :disabled="fetching" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer">
                        <RefreshCw :size="12" :class="fetching ? 'animate-spin' : ''" /> {{ fetching ? 'Fetching…' : 'Fetch Shipments' }}
                    </button>
                    <button @click="syncTracking" :disabled="syncing" class="btn btn-ghost btn-sm flex items-center gap-1 cursor-pointer">
                        <RefreshCw :size="12" :class="syncing ? 'animate-spin' : ''" /> {{ syncing ? 'Syncing…' : 'Sync Status' }}
                    </button>
                    <button @click="showTrack = !showTrack" class="btn btn-ghost btn-sm flex items-center gap-1 cursor-pointer">
                        <Search :size="12" /> Track AWB
                    </button>
                    <button @click="disconnectApi" class="btn btn-ghost btn-sm text-rose-400 flex items-center gap-1 cursor-pointer">
                        <Unplug :size="12" /> Disconnect
                    </button>
                </template>
                <button v-else-if="partner.has_api" @click="showApiConnect = true" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer">
                    <Plug :size="12" /> Connect API
                </button>
                <button @click="showUpload = true" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer">
                    <Upload :size="14" /> Upload Invoice
                </button>
            </div>
        </div>

        <!-- Track AWB bar -->
        <div v-if="showTrack && partner.api_connected" class="card mb-4">
            <div class="flex gap-2">
                <input v-model="trackAwb" class="heyd2c-input flex-1 font-mono" placeholder="Enter AWB number" @keyup.enter="trackSingle" />
                <button @click="trackSingle" class="btn btn-primary btn-sm cursor-pointer" :disabled="trackLoading">
                    {{ trackLoading ? 'Tracking…' : 'Track' }}
                </button>
            </div>
            <div v-if="trackResult" class="mt-3">
                <div v-if="trackResult.error" class="text-rose-400 text-[13px]">{{ trackResult.error }}</div>
                <div v-else class="space-y-2">
                    <div class="flex items-center gap-3">
                        <span class="text-[14px] font-mono font-bold text-brand-300">{{ trackResult.waybill }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="statusBg(trackResult.status)">
                            {{ trackResult.status }}
                        </span>
                    </div>
                    <div class="text-[11px] text-ink-3 space-y-0.5">
                        <div v-if="trackResult.origin">Origin: {{ trackResult.origin }} → {{ trackResult.destination }}</div>
                        <div v-if="trackResult.expected_date">Expected: {{ trackResult.expected_date }}</div>
                        <div v-if="trackResult.attempt_count">Attempts: {{ trackResult.attempt_count }}</div>
                    </div>
                    <div v-if="trackResult.scans?.length" class="mt-2 border-t border-frost-1 pt-2">
                        <div class="text-[10px] text-ink-3 uppercase tracking-wider mb-1">Scan History</div>
                        <div v-for="(s, i) in trackResult.scans.slice(0, 10)" :key="i" class="flex gap-3 text-[11px] py-1 border-b border-frost-1 last:border-0">
                            <span class="text-ink-3 w-32 flex-shrink-0">{{ s.timestamp?.replace('T',' ').slice(0,19) }}</span>
                            <span class="text-ink-2">{{ s.status }}</span>
                            <span class="text-ink-3">{{ s.location }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoice Summary KPIs -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
            <div class="card text-center">
                <div class="text-[18px] font-bold text-white">{{ invoiceStats.total_invoices || 0 }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Invoices</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-white">{{ fmt(invoiceStats.total_subtotal) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Subtotal</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-amber-400">{{ fmt(invoiceStats.total_gst) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">GST Paid</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-rose-400">{{ fmt(invoiceStats.total_amount) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Total Billed</div>
            </div>
        </div>


        <!-- ── Delhivery: Tabs ────────────────────────────── -->
        <template v-if="isDelhivery">
            <div class="flex gap-1 mb-4 border-b border-frost-1 overflow-x-auto">
                <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                    :class="activeTab === tab.id ? 'text-white border-b-2 border-brand-400' : 'text-ink-3 hover:text-ink-2'"
                    class="flex items-center gap-1.5 px-3 py-2 text-[12px] font-medium transition whitespace-nowrap cursor-pointer -mb-px">
                    <component :is="tab.icon" :size="12" />
                    {{ tab.label }}
                </button>
            </div>

            <!-- INVOICES TAB -->
            <div v-if="activeTab === 'invoices'">
                <div class="card overflow-hidden p-0">
                    <div class="px-5 py-3 border-b border-frost-1"><h3 class="text-[14px] font-semibold text-white">Invoices</h3></div>
                    <div v-for="inv in invoices" :key="inv.id"
                        class="px-5 py-3.5 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition flex items-center justify-between">
                        <div class="flex items-center gap-3 cursor-pointer flex-1"
                            @click="router.visit(route('tenant.logistics.invoice-detail', { tenant: slug, invoiceId: inv.id }))">
                            <div class="h-10 w-10 rounded-xl flex items-center justify-center"
                                :class="inv.type === 'freight' ? 'bg-blue-500/15' : inv.type === 'vas' ? 'bg-purple-500/15' : 'bg-amber-500/15'">
                                <FileText :size="16" :class="inv.type === 'freight' ? 'text-blue-400' : inv.type === 'vas' ? 'text-purple-400' : 'text-amber-400'" />
                            </div>
                            <div>
                                <div class="text-[14px] font-medium text-white">{{ inv.invoice_number }}</div>
                                <div class="text-[11px] text-ink-3">{{ dateFmt(inv.invoice_date) }} · {{ inv.shipments_count || 0 }} shipments · <span class="capitalize">{{ inv.type }}</span></div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="text-[14px] font-bold text-white">{{ fmt(inv.total_amount) }}</div>
                            <a v-if="inv.file_url" :href="inv.file_url" target="_blank" class="text-ink-3 hover:text-white"><Eye :size="14" /></a>
                            <button @click.stop="deleteInvoice(inv)" class="text-ink-3 hover:text-rose cursor-pointer"><Trash2 :size="14" /></button>
                        </div>
                    </div>
                    <div v-if="!invoices.length" class="px-5 py-8 text-center text-[13px] text-ink-3">No invoices yet.</div>
                </div>
            </div>

            <!-- OVERVIEW TAB -->
            <div v-else-if="activeTab === 'overview'">
                <!-- Date filter -->
                <div class="flex items-center gap-2 mb-4 flex-wrap">
                    <input type="date" v-model="dateFrom" @change="loadAnalytics" class="heyd2c-input text-[12px] w-36" />
                    <span class="text-ink-3 text-[12px]">to</span>
                    <input type="date" v-model="dateTo" @change="loadAnalytics" class="heyd2c-input text-[12px] w-36" />
                    <button v-if="dateFrom||dateTo" @click="dateFrom='';dateTo='';loadAnalytics()" class="text-[11px] text-ink-3 hover:text-white underline cursor-pointer">Clear</button>
                </div>

                <div v-if="analyticsLoading" class="grid grid-cols-2 gap-3">
                    <div v-for="i in 4" :key="i" class="card h-32 animate-pulse"></div>
                </div>

                <div v-else-if="analytics" class="space-y-4">
                    <!-- KPI row -->
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-white">{{ fmtNum(analytics.total) }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">Total Orders</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-emerald-400">{{ fmtNum(analytics.delivered) }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">Delivered</div>
                            <div class="text-[10px] text-emerald-400 mt-0.5">{{ analytics.delivery_rate }}%</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-amber-400">{{ fmtNum(analytics.in_transit) }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">In Transit</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-blue-400">{{ fmtNum(analytics.out_for_delivery) }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">Out For Delivery</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-rose-400">{{ fmtNum(analytics.rto_count) }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">RTO</div>
                            <div class="text-[10px] text-rose-400 mt-0.5">{{ analytics.rto_rate }}%</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-slate-300">{{ fmtNum(analytics.pending) }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">Pending</div>
                        </div>
                    </div>

                    <!-- COD KPIs -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-amber-400">{{ fmtNum(analytics.cod_count) }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">COD Orders</div>
                            <div class="text-[10px] text-ink-3 mt-0.5">{{ fmt(analytics.cod_total) }}</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-brand-300">{{ fmtNum(analytics.prepaid_count) }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">Prepaid Orders</div>
                            <div class="text-[10px] text-ink-3 mt-0.5">{{ fmt(analytics.prepaid_total) }}</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-rose-400">{{ fmt(analytics.cod_at_risk) }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">COD At Risk</div>
                            <div class="text-[10px] text-ink-3 mt-0.5">Not yet collected</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-rose-400">{{ analytics.cod_rto_rate }}%</div>
                            <div class="text-[9px] text-ink-3 uppercase">COD RTO Rate</div>
                        </div>
                    </div>

                    <!-- Status breakdown -->
                    <div class="card">
                        <h3 class="text-[13px] font-semibold text-white mb-3">Status Breakdown</h3>
                        <div class="space-y-2">
                            <div v-for="item in (analytics.status_breakdown||[])" :key="item.status" class="flex items-center gap-3">
                                <span class="text-[11px] text-ink-3 w-32 truncate">{{ item.status || 'Unknown' }}</span>
                                <div class="flex-1 bg-frost-1 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full bg-brand-400 transition-all"
                                        :style="{ width: analytics.total ? (item.count/analytics.total*100)+'%' : '0%' }"></div>
                                </div>
                                <span class="text-[11px] text-ink-2 w-8 text-right">{{ item.count }}</span>
                            </div>
                            <p v-if="!analytics.status_breakdown?.length" class="text-[12px] text-ink-3 text-center py-3">No data yet</p>
                        </div>
                    </div>

                    <!-- Zone table -->
                    <div class="card overflow-hidden p-0">
                        <div class="px-5 py-3 border-b border-frost-1"><h3 class="text-[13px] font-semibold text-white">Top States</h3></div>
                        <table class="w-full text-[12px]">
                            <thead><tr class="border-b border-frost-1">
                                <th class="text-left px-5 py-2 text-[10px] text-ink-3 font-medium">State</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">Orders</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">Delivered</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">RTO</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">Revenue</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="s in (analytics.state_breakdown||[]).slice(0,10)" :key="s.state" class="border-b border-frost-1 last:border-0">
                                    <td class="px-5 py-2.5 text-white font-medium">{{ s.state || 'Unknown' }}</td>
                                    <td class="px-5 py-2.5 text-ink-2 text-right">{{ fmtNum(s.count) }}</td>
                                    <td class="px-5 py-2.5 text-emerald-400 text-right">{{ s.success_rate }}%</td>
                                    <td class="px-5 py-2.5 text-rose-400 text-right">{{ fmtNum(s.rto) }}</td>
                                    <td class="px-5 py-2.5 text-brand-300 text-right">{{ fmt(s.revenue) }}</td>
                                </tr>
                                <tr v-if="!analytics.state_breakdown?.length">
                                    <td colspan="5" class="px-5 py-6 text-center text-ink-3">No data</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Monthly trend -->
                    <div class="card overflow-hidden p-0">
                        <div class="px-5 py-3 border-b border-frost-1"><h3 class="text-[13px] font-semibold text-white">Monthly Trend</h3></div>
                        <table class="w-full text-[12px]">
                            <thead><tr class="border-b border-frost-1">
                                <th class="text-left px-5 py-2 text-[10px] text-ink-3 font-medium">Month</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">Total</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">Delivered</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">RTO</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">COD</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">RTO%</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="m in [...(analytics.monthly_trend||[])].reverse().slice(0,12)" :key="m.month" class="border-b border-frost-1 last:border-0">
                                    <td class="px-5 py-2.5 text-white font-medium">{{ m.month }}</td>
                                    <td class="px-5 py-2.5 text-ink-2 text-right">{{ fmtNum(m.total) }}</td>
                                    <td class="px-5 py-2.5 text-emerald-400 text-right">{{ fmtNum(m.delivered) }}</td>
                                    <td class="px-5 py-2.5 text-rose-400 text-right">{{ fmtNum(m.rto) }}</td>
                                    <td class="px-5 py-2.5 text-amber-400 text-right">{{ fmtNum(m.cod) }}</td>
                                    <td class="px-5 py-2.5 text-right font-semibold" :class="m.rto_rate > 10 ? 'text-rose-400' : m.rto_rate > 5 ? 'text-amber-400' : 'text-emerald-400'">{{ m.rto_rate }}%</td>
                                </tr>
                                <tr v-if="!analytics.monthly_trend?.length">
                                    <td colspan="6" class="px-5 py-6 text-center text-ink-3">No data</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-else class="card text-center py-8 text-[13px] text-ink-3">No analytics data. Sync shipments first.</div>
            </div>

            <!-- SHIPMENTS TAB -->
            <div v-else-if="activeTab === 'shipments'">
                <!-- CSV Import zone -->
                <div class="card mb-4">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <p class="text-[13px] font-semibold text-white">Import Shipment CSV</p>
                            <p class="text-[11px] text-ink-3 mt-0.5">Download from Delhivery dashboard → Reports → Shipment Report, then drop here</p>
                        </div>
                        <span v-if="importMessage" class="text-[11px]" :class="importError ? 'text-rose-400' : 'text-emerald-400'">{{ importMessage }}</span>
                    </div>
                    <div @dragover.prevent="csvDragging = true" @dragleave="csvDragging = false" @drop.prevent="onCsvDrop"
                        :class="csvDragging ? 'border-brand-400 bg-brand-600/10' : 'border-frost-1 hover:border-brand-600/40'"
                        class="border-2 border-dashed rounded-xl p-5 text-center transition cursor-pointer"
                        @click="$refs.csvInput.click()">
                        <input ref="csvInput" type="file" accept=".csv,.txt" class="hidden" @change="onCsvDrop" />
                        <div v-if="importing" class="flex items-center justify-center gap-2 text-brand-300 text-[12px]">
                            <RefreshCw :size="14" class="animate-spin" /> Importing…
                        </div>
                        <div v-else>
                            <FileText :size="20" class="text-ink-3 mx-auto mb-1" />
                            <p class="text-[12px] text-ink-3">Drop Delhivery shipment CSV here</p>
                        </div>
                    </div>
                </div>

                <!-- Shipments table (populated after import) -->
                <div class="flex gap-2 mb-3">
                    <input v-model="searchAwbFilter" placeholder="Search AWB…" class="heyd2c-input text-[12px] w-48 font-mono" />
                    <select v-model="statusFilter" class="heyd2c-input text-[12px]">
                        <option value="">All Statuses</option>
                        <option>Delivered</option><option>In Transit</option>
                        <option>Out for Delivery</option><option>RTO</option><option>Pending Pickup</option>
                    </select>
                </div>
                <div class="card overflow-hidden p-0">
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12px]">
                            <thead><tr class="border-b border-frost-1">
                                <th class="text-left px-4 py-2.5 text-[10px] text-ink-3 font-medium">AWB</th>
                                <th class="text-left px-4 py-2.5 text-[10px] text-ink-3 font-medium">Status</th>
                                <th class="text-left px-4 py-2.5 text-[10px] text-ink-3 font-medium">Dest PIN</th>
                                <th class="text-left px-4 py-2.5 text-[10px] text-ink-3 font-medium">Zone</th>
                                <th class="text-right px-4 py-2.5 text-[10px] text-ink-3 font-medium">Freight</th>
                                <th class="text-right px-4 py-2.5 text-[10px] text-ink-3 font-medium">Total</th>
                                <th class="text-left px-4 py-2.5 text-[10px] text-ink-3 font-medium">Delivered</th>
                            </tr></thead>
                            <tbody>
                                <template v-if="csvShipments.length">
                                    <tr v-for="s in filteredCsvShipments" :key="s.waybill"
                                        class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition">
                                        <td class="px-4 py-2.5 font-mono text-brand-300 text-[11px]">{{ s.waybill }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="px-1.5 py-0.5 rounded text-[10px]" :class="statusBg(s.status)">{{ s.status }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-ink-3 font-mono">{{ s.dest_pincode || s.destination_pin || '—' }}</td>
                                        <td class="px-4 py-2.5 text-ink-3">{{ s.zone || '—' }}</td>
                                        <td class="px-4 py-2.5 text-right text-ink-2">{{ s.charge_freight ? '₹'+s.charge_freight : '—' }}</td>
                                        <td class="px-4 py-2.5 text-right text-white font-medium">{{ s.total_amount ? '₹'+s.total_amount : '—' }}</td>
                                        <td class="px-4 py-2.5 text-ink-3 text-[10px]">{{ s.delivered_date ? new Date(s.delivered_date).toLocaleDateString('en-IN') : '—' }}</td>
                                    </tr>
                                </template>
                                <tr v-else>
                                    <td colspan="7" class="px-4 py-8 text-center text-ink-3 text-[12px]">
                                        Import a CSV above to see shipments, then use <strong class="text-white">Sync Status</strong> to get live updates.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- RTO ANALYSIS TAB -->
            <div v-else-if="activeTab === 'rto'">
                <div v-if="analyticsLoading" class="grid grid-cols-2 gap-3">
                    <div v-for="i in 4" :key="i" class="card h-28 animate-pulse"></div>
                </div>
                <div v-else-if="analytics" class="space-y-4">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-rose-400">{{ fmtNum(analytics.rto_count) }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">Total RTO</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-rose-400">{{ (analytics.rto_rate||0).toFixed(1) }}%</div>
                            <div class="text-[9px] text-ink-3 uppercase">RTO Rate</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-amber-400">{{ analytics.cod_rto_rate != null ? analytics.cod_rto_rate.toFixed(1)+'%' : '—' }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">COD RTO Rate</div>
                        </div>
                        <div class="card text-center">
                            <div class="text-[18px] font-bold text-brand-300">{{ analytics.prepaid_rto_rate != null ? analytics.prepaid_rto_rate.toFixed(1)+'%' : '—' }}</div>
                            <div class="text-[9px] text-ink-3 uppercase">Prepaid RTO Rate</div>
                        </div>
                    </div>

                    <!-- Top RTO pincodes -->
                    <div class="card overflow-hidden p-0">
                        <div class="px-5 py-3 border-b border-frost-1"><h3 class="text-[13px] font-semibold text-white">Top RTO Pincodes</h3></div>
                        <table class="w-full text-[12px]">
                            <thead><tr class="border-b border-frost-1">
                                <th class="text-left px-5 py-2 text-[10px] text-ink-3 font-medium">Pincode</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">RTO Count</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">% of Total RTO</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="p in (analytics.top_rto_pincodes||[])" :key="p.pincode" class="border-b border-frost-1 last:border-0">
                                    <td class="px-5 py-2.5 font-mono text-white">{{ p.pincode||'—' }}</td>
                                    <td class="px-5 py-2.5 text-rose-400 text-right font-medium">{{ p.count }}</td>
                                    <td class="px-5 py-2.5 text-ink-2 text-right">{{ analytics.rto_count ? ((p.count/analytics.rto_count)*100).toFixed(1)+'%' : '—' }}</td>
                                </tr>
                                <tr v-if="!analytics.top_rto_pincodes?.length">
                                    <td colspan="3" class="px-5 py-6 text-center text-ink-3">No RTO data yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- COD vs Prepaid RTO -->
                    <div class="card">
                        <h3 class="text-[13px] font-semibold text-white mb-3">RTO by Payment Mode</h3>
                        <div class="space-y-4">
                            <div v-for="item in (analytics.payment_breakdown||[])" :key="item.mode">
                                <div class="flex justify-between text-[11px] mb-1">
                                    <span class="text-ink-2">{{ item.mode }}</span>
                                    <span class="text-ink-3">{{ item.rto }} RTO / {{ item.count }} total</span>
                                </div>
                                <div class="bg-frost-1 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all"
                                        :class="item.mode === 'COD' ? 'bg-amber-500' : 'bg-brand-400'"
                                        :style="{ width: item.count ? (item.rto/item.count*100)+'%' : '0%' }"></div>
                                </div>
                                <p class="text-[10px] text-ink-3 mt-0.5">{{ item.count ? ((item.rto/item.count)*100).toFixed(1) : 0 }}% RTO rate</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="card text-center py-8 text-[13px] text-ink-3">
                    {{ partner.api_connected ? 'Sync shipments to see RTO analysis.' : 'Connect API first.' }}
                </div>
            </div>

            <!-- PINCODE ANALYTICS TAB -->
            <div v-else-if="activeTab === 'pincode'">
                <div v-if="analyticsLoading" class="card h-48 animate-pulse"></div>
                <div v-else-if="analytics" class="card overflow-hidden p-0">
                    <div class="px-5 py-3 border-b border-frost-1">
                        <h3 class="text-[13px] font-semibold text-white">Delivery Success by Pincode</h3>
                        <p class="text-[11px] text-ink-3 mt-0.5">Top 20 destination pincodes</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12px]">
                            <thead><tr class="border-b border-frost-1">
                                <th class="text-left px-5 py-2 text-[10px] text-ink-3 font-medium">#</th>
                                <th class="text-left px-5 py-2 text-[10px] text-ink-3 font-medium">Pincode</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">Total</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">Delivered</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">RTO</th>
                                <th class="text-right px-5 py-2 text-[10px] text-ink-3 font-medium">Success %</th>
                            </tr></thead>
                            <tbody>
                                <tr v-for="(p, i) in (analytics.pincode_breakdown||[])" :key="p.pincode" class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition">
                                    <td class="px-5 py-2.5 text-ink-3">{{ i+1 }}</td>
                                    <td class="px-5 py-2.5 font-mono text-white">{{ p.pincode }}</td>
                                    <td class="px-5 py-2.5 text-ink-2 text-right">{{ fmtNum(p.count) }}</td>
                                    <td class="px-5 py-2.5 text-emerald-400 text-right">{{ fmtNum(p.delivered) }}</td>
                                    <td class="px-5 py-2.5 text-rose-400 text-right">{{ fmtNum(p.rto) }}</td>
                                    <td class="px-5 py-2.5 text-right font-semibold"
                                        :class="p.success_rate>=80 ? 'text-emerald-400' : p.success_rate>=60 ? 'text-amber-400' : 'text-rose-400'">
                                        {{ p.success_rate?.toFixed(1) }}%
                                    </td>
                                </tr>
                                <tr v-if="!analytics.pincode_breakdown?.length">
                                    <td colspan="6" class="px-5 py-6 text-center text-ink-3">No pincode data yet</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div v-else class="card text-center py-8 text-[13px] text-ink-3">
                    {{ partner.api_connected ? 'Sync shipments to see pincode analytics.' : 'Connect API first.' }}
                </div>
            </div>
        </template>

        <!-- ── Non-Delhivery: just invoices (original layout) ── -->
        <template v-else>
            <div class="card overflow-hidden p-0">
                <div class="px-5 py-3 border-b border-frost-1"><h3 class="text-[14px] font-semibold text-white">Invoices</h3></div>
                <div v-for="inv in invoices" :key="inv.id"
                    class="px-5 py-3.5 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition flex items-center justify-between">
                    <div class="flex items-center gap-3 cursor-pointer flex-1"
                        @click="router.visit(route('tenant.logistics.invoice-detail', { tenant: slug, invoiceId: inv.id }))">
                        <div class="h-10 w-10 rounded-xl flex items-center justify-center"
                            :class="inv.type === 'freight' ? 'bg-blue-500/15' : inv.type === 'vas' ? 'bg-purple-500/15' : 'bg-amber-500/15'">
                            <FileText :size="16" :class="inv.type === 'freight' ? 'text-blue-400' : inv.type === 'vas' ? 'text-purple-400' : 'text-amber-400'" />
                        </div>
                        <div>
                            <div class="text-[14px] font-medium text-white">{{ inv.invoice_number }}</div>
                            <div class="text-[11px] text-ink-3">{{ dateFmt(inv.invoice_date) }} · {{ inv.shipments_count || 0 }} shipments · <span class="capitalize">{{ inv.type }}</span></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-[14px] font-bold text-white">{{ fmt(inv.total_amount) }}</div>
                        <a v-if="inv.file_url" :href="inv.file_url" target="_blank" class="text-ink-3 hover:text-white"><Eye :size="14" /></a>
                        <button @click.stop="deleteInvoice(inv)" class="text-ink-3 hover:text-rose cursor-pointer"><Trash2 :size="14" /></button>
                    </div>
                </div>
                <div v-if="!invoices.length" class="px-5 py-8 text-center text-[13px] text-ink-3">No invoices yet.</div>
            </div>
        </template>

        <!-- ── API Connect Modal ── -->
        <div v-if="showApiConnect" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showApiConnect = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-1">Connect {{ partner.name }} API</h3>
                <p class="text-[12px] text-ink-3 mb-4">Enter your API credentials to enable auto-tracking and shipment sync.</p>
                <form @submit.prevent="connectApi" class="space-y-3">
                    <div v-for="(label, key) in partner.credential_fields" :key="key">
                        <template v-if="key === 'environment'">
                            <label class="heyd2c-label">Environment</label>
                            <select v-model="apiForm[key]" class="heyd2c-input">
                                <option value="production">Production</option>
                                <option value="staging">Staging / Test</option>
                            </select>
                        </template>
                        <template v-else>
                            <label class="heyd2c-label">{{ label }}</label>
                            <input v-model="apiForm[key]" class="heyd2c-input font-mono"
                                :type="key.includes('password')||key.includes('secret') ? 'password' : 'text'" required />
                        </template>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1 cursor-pointer" :disabled="apiForm.processing">
                            {{ apiForm.processing ? 'Testing…' : 'Connect & Test' }}
                        </button>
                        <button type="button" class="btn btn-ghost flex-1 cursor-pointer" @click="showApiConnect = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ── Upload Invoice Modal ── -->
        <div v-if="showUpload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showUpload = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-lg mx-4 p-5 max-h-[90vh] overflow-y-auto">
                <h3 class="text-[16px] font-bold text-white mb-1">Upload Invoice — {{ partner.name }}</h3>
                <p class="text-[12px] text-ink-3 mb-4">Drop your invoice PDF and/or CSV. Details auto-detected by AI.</p>
                <form @submit.prevent="submitUpload" class="space-y-3">
                    <div class="border-2 border-dashed border-frost-1 rounded-xl p-5 text-center hover:border-brand-600/40 transition">
                        <Upload :size="22" class="text-ink-3 mx-auto mb-1" />
                        <div class="text-[11px] text-ink-3 mb-2">Invoice PDF</div>
                        <input type="file" accept=".pdf" class="heyd2c-input" @change="form.invoice_pdf = $event.target.files[0]" />
                    </div>
                    <div class="border-2 border-dashed border-frost-1 rounded-xl p-5 text-center hover:border-brand-600/40 transition">
                        <FileText :size="22" class="text-ink-3 mx-auto mb-1" />
                        <div class="text-[11px] text-ink-3 mb-2">Transaction CSV (optional)</div>
                        <input type="file" accept=".csv,.txt" class="heyd2c-input" @change="form.invoice_csv = $event.target.files[0]" />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1 cursor-pointer" :disabled="form.processing">
                            {{ form.processing ? 'Reading & Uploading…' : 'Upload & Auto-Detect' }}
                        </button>
                        <button type="button" class="btn btn-ghost flex-1 cursor-pointer" @click="showUpload = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
