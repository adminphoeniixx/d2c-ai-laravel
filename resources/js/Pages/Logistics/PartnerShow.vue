<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    partner: Object,
    shipments: Object,
    invoices: Array,
    stats: Object,
    isConnected: Boolean,
    credentials: Object,
})

// ── Tabs
const activeTab = ref('overview')
const tabs = [
    { id: 'overview',   label: 'Overview',          icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { id: 'shipments',  label: 'Shipments',          icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
    { id: 'rto',        label: 'RTO Analysis',       icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' },
    { id: 'pincode',    label: 'Pincode Analytics',  icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z' },
    { id: 'invoices',   label: 'Invoices',           icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
]

// ── Connect modal
const showConnectModal = ref(false)
const apiToken = ref(props.credentials?.api_token ?? '')
const connecting = ref(false)
const connectError = ref('')

async function connectDelhivery() {
    connecting.value = true
    connectError.value = ''
    router.post(route('logistics.delhivery.connect', { tenant: usePage().props.tenant }), {
        api_token: apiToken.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { showConnectModal.value = false },
        onError: (e) => { connectError.value = e.message ?? 'Connection failed' },
        onFinish: () => { connecting.value = false },
    })
}

function disconnectDelhivery() {
    if (!confirm('Disconnect Delhivery? Synced data will be retained.')) return
    router.post(route('logistics.delhivery.disconnect', { tenant: usePage().props.tenant }), {}, { preserveScroll: true })
}

// ── Sync
const syncing = ref(false)
const syncMessage = ref('')
function syncShipments() {
    syncing.value = true
    syncMessage.value = ''
    router.post(route('logistics.delhivery.sync', { tenant: usePage().props.tenant }), {}, {
        preserveScroll: true,
        onSuccess: (page) => { syncMessage.value = `Synced successfully` },
        onError: () => { syncMessage.value = 'Sync failed' },
        onFinish: () => { syncing.value = false; setTimeout(() => syncMessage.value = '', 3000) },
    })
}

// ── Analytics data (fetched separately)
const analytics = ref(null)
const analyticsLoading = ref(false)
const dateFrom = ref('')
const dateTo = ref('')

async function loadAnalytics() {
    analyticsLoading.value = true
    try {
        const params = new URLSearchParams()
        if (dateFrom.value) params.append('from', dateFrom.value)
        if (dateTo.value) params.append('to', dateTo.value)
        const tenantSlug = usePage().props.tenant
        const res = await fetch(`/${tenantSlug}/logistics/delhivery/analytics?${params}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        analytics.value = await res.json()
    } catch(e) {
        analytics.value = null
    } finally {
        analyticsLoading.value = false
    }
}

onMounted(() => {
    if (props.isConnected) loadAnalytics()
})

watch(activeTab, (tab) => {
    if ((tab === 'overview' || tab === 'rto' || tab === 'pincode') && props.isConnected && !analytics.value) {
        loadAnalytics()
    }
})

// ── Track single AWB
const awbInput = ref('')
const trackResult = ref(null)
const tracking = ref(false)

async function trackAWB() {
    if (!awbInput.value.trim()) return
    tracking.value = true
    trackResult.value = null
    try {
        const tenantSlug = usePage().props.tenant
        const res = await fetch(`/${tenantSlug}/logistics/delhivery/track/${awbInput.value.trim()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        trackResult.value = await res.json()
    } catch(e) {
        trackResult.value = { error: 'Track failed' }
    } finally {
        tracking.value = false
    }
}

// ── Invoice upload
const invoiceDragging = ref(false)
const invoiceFile = ref(null)
const invoiceUploading = ref(false)

function onInvoiceDrop(e) {
    invoiceDragging.value = false
    const file = e.dataTransfer?.files[0] || e.target.files[0]
    if (file) { invoiceFile.value = file; uploadInvoice() }
}

function uploadInvoice() {
    if (!invoiceFile.value) return
    invoiceUploading.value = true
    const form = new FormData()
    form.append('invoice', invoiceFile.value)
    form.append('partner_id', props.partner.id)
    router.post(route('logistics.invoices.store', { tenant: usePage().props.tenant }), form, {
        preserveScroll: true,
        onFinish: () => { invoiceUploading.value = false; invoiceFile.value = null },
    })
}

// ── Shipment filters
const searchAWB = ref('')
const statusFilter = ref('')
const filteredShipments = computed(() => {
    let list = props.shipments?.data ?? []
    if (searchAWB.value) list = list.filter(s => s.waybill?.toLowerCase().includes(searchAWB.value.toLowerCase()))
    if (statusFilter.value) list = list.filter(s => s.status === statusFilter.value)
    return list
})

// ── Helpers
const fmt = (n, dec = 0) => n == null ? '—' : Number(n).toLocaleString('en-IN', { maximumFractionDigits: dec })
const fmtCur = (n) => n == null ? '—' : '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 })
const pct = (a, b) => b ? ((a / b) * 100).toFixed(1) + '%' : '0%'

const statusColor = (s) => {
    if (!s) return 'text-slate-400'
    const sl = s.toLowerCase()
    if (sl.includes('deliver')) return 'text-emerald-400'
    if (sl.includes('rto') || sl.includes('return')) return 'text-red-400'
    if (sl.includes('transit') || sl.includes('out')) return 'text-amber-400'
    if (sl.includes('picked')) return 'text-blue-400'
    return 'text-slate-300'
}
const statusBg = (s) => {
    if (!s) return 'bg-slate-700 text-slate-300'
    const sl = s.toLowerCase()
    if (sl.includes('deliver')) return 'bg-emerald-900/50 text-emerald-300'
    if (sl.includes('rto') || sl.includes('return')) return 'bg-red-900/50 text-red-300'
    if (sl.includes('transit') || sl.includes('out')) return 'bg-amber-900/50 text-amber-300'
    if (sl.includes('picked')) return 'bg-blue-900/50 text-blue-300'
    return 'bg-slate-700 text-slate-300'
}

// KPI card data computed from analytics or props.stats
const kpis = computed(() => {
    const a = analytics.value
    const s = props.stats ?? {}
    if (!a) return [
        { label: 'Total Shipments', value: fmt(s.total ?? 0),      icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', color: 'text-purple-400' },
        { label: 'Delivered',       value: fmt(s.delivered ?? 0),   icon: 'M5 13l4 4L19 7', color: 'text-emerald-400' },
        { label: 'In Transit',      value: fmt(s.in_transit ?? 0),  icon: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1', color: 'text-amber-400' },
        { label: 'RTO',             value: fmt(s.rto ?? 0),         icon: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6', color: 'text-red-400' },
        { label: 'RTO Rate',        value: pct(s.rto ?? 0, s.total ?? 1), icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', color: 'text-red-300' },
        { label: 'Avg Delivery',    value: '—',                     icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', color: 'text-blue-400' },
    ]
    return [
        { label: 'Total Shipments', value: fmt(a.total),             icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', color: 'text-purple-400' },
        { label: 'Delivered',       value: fmt(a.delivered),          icon: 'M5 13l4 4L19 7', color: 'text-emerald-400' },
        { label: 'In Transit',      value: fmt(a.in_transit),         icon: 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1', color: 'text-amber-400' },
        { label: 'RTO',             value: fmt(a.rto_count),          icon: 'M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6', color: 'text-red-400' },
        { label: 'RTO Rate',        value: (a.rto_rate ?? 0).toFixed(1) + '%', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', color: 'text-red-300' },
        { label: 'Avg Delivery',    value: a.avg_delivery_days ? a.avg_delivery_days.toFixed(1) + 'd' : '—', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', color: 'text-blue-400' },
    ]
})
</script>

<template>
    <AppLayout :title="`${partner.name} — Logistics`">
        <div class="min-h-screen bg-slate-950 text-slate-100">
            <!-- Header -->
            <div class="border-b border-slate-800 bg-slate-900/50">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div class="flex items-center gap-3">
                            <button @click="router.visit(route('logistics.index', { tenant: $page.props.tenant }))"
                                class="text-slate-400 hover:text-slate-200 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                            <div class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-lg font-bold text-purple-400">
                                {{ partner.name.charAt(0) }}
                            </div>
                            <div>
                                <h1 class="text-lg font-semibold text-white">{{ partner.name }}</h1>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span :class="isConnected ? 'bg-emerald-500' : 'bg-slate-600'" class="w-2 h-2 rounded-full inline-block"></span>
                                    <span class="text-xs text-slate-400">{{ isConnected ? 'API Connected' : 'Not Connected' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span v-if="syncMessage" class="text-xs text-emerald-400 mr-2">{{ syncMessage }}</span>
                            <button v-if="isConnected" @click="syncShipments" :disabled="syncing"
                                class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">
                                <svg :class="syncing ? 'animate-spin' : ''" class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                {{ syncing ? 'Syncing…' : 'Sync Now' }}
                            </button>
                            <button v-if="!isConnected" @click="showConnectModal = true"
                                class="px-4 py-1.5 bg-purple-600 hover:bg-purple-500 rounded-lg text-sm font-medium transition-colors">
                                Connect API
                            </button>
                            <button v-else @click="showConnectModal = true"
                                class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 rounded-lg text-sm font-medium transition-colors">
                                Settings
                            </button>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="flex gap-1 mt-4 overflow-x-auto pb-px">
                        <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
                            :class="activeTab === tab.id
                                ? 'bg-slate-800 text-white border-b-2 border-purple-500'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50'"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-t-lg text-sm font-medium transition-colors whitespace-nowrap">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="tab.icon"/>
                            </svg>
                            {{ tab.label }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab Content -->
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

                <!-- ────────────────────── OVERVIEW ────────────────────── -->
                <div v-if="activeTab === 'overview'">
                    <!-- Date filter -->
                    <div class="flex items-center gap-3 mb-6 flex-wrap">
                        <label class="text-sm text-slate-400">Date Range</label>
                        <input type="date" v-model="dateFrom" @change="loadAnalytics"
                            class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-sm text-slate-200 focus:outline-none focus:border-purple-500"/>
                        <span class="text-slate-500 text-sm">to</span>
                        <input type="date" v-model="dateTo" @change="loadAnalytics"
                            class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-1.5 text-sm text-slate-200 focus:outline-none focus:border-purple-500"/>
                        <button v-if="dateFrom || dateTo" @click="dateFrom=''; dateTo=''; loadAnalytics()"
                            class="text-xs text-slate-400 hover:text-slate-200 underline">Clear</button>
                    </div>

                    <!-- Not connected notice -->
                    <div v-if="!isConnected" class="bg-slate-900 border border-dashed border-slate-700 rounded-2xl p-10 text-center mb-6">
                        <div class="w-14 h-14 bg-slate-800 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <svg class="w-7 h-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                        </div>
                        <h3 class="text-base font-semibold text-slate-200 mb-1">Connect Delhivery API</h3>
                        <p class="text-slate-500 text-sm mb-4">Add your API token to sync shipments, RTO data, and delivery analytics.</p>
                        <button @click="showConnectModal = true" class="px-5 py-2 bg-purple-600 hover:bg-purple-500 rounded-lg text-sm font-medium transition-colors">Connect Now</button>
                    </div>

                    <!-- KPI Grid -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
                        <div v-for="kpi in kpis" :key="kpi.label" class="bg-slate-900 border border-slate-800 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs text-slate-500 font-medium">{{ kpi.label }}</span>
                                <svg :class="kpi.color" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="kpi.icon"/>
                                </svg>
                            </div>
                            <p :class="kpi.color" class="text-2xl font-bold">{{ kpi.value }}</p>
                        </div>
                    </div>

                    <!-- Loading skeleton -->
                    <div v-if="analyticsLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div v-for="i in 4" :key="i" class="bg-slate-900 border border-slate-800 rounded-xl p-5 h-48 animate-pulse"></div>
                    </div>

                    <!-- Analytics charts row -->
                    <div v-else-if="analytics" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- Status Breakdown -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                            <h3 class="text-sm font-semibold text-slate-300 mb-4">Status Breakdown</h3>
                            <div class="space-y-2.5">
                                <div v-for="item in (analytics.status_breakdown ?? [])" :key="item.status" class="flex items-center gap-3">
                                    <span class="text-xs text-slate-400 w-32 shrink-0 truncate">{{ item.status || 'Unknown' }}</span>
                                    <div class="flex-1 bg-slate-800 rounded-full h-2">
                                        <div class="h-2 rounded-full bg-purple-500 transition-all"
                                            :style="{ width: analytics.total ? (item.count / analytics.total * 100) + '%' : '0%' }"></div>
                                    </div>
                                    <span class="text-xs text-slate-300 w-8 text-right shrink-0">{{ item.count }}</span>
                                </div>
                                <p v-if="!analytics.status_breakdown?.length" class="text-xs text-slate-500 text-center py-4">No data yet</p>
                            </div>
                        </div>

                        <!-- COD vs Prepaid -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                            <h3 class="text-sm font-semibold text-slate-300 mb-4">Payment Mode</h3>
                            <div v-if="analytics.payment_breakdown" class="space-y-4">
                                <div v-for="item in analytics.payment_breakdown" :key="item.mode" class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                        :class="item.mode === 'COD' ? 'bg-amber-900/50 text-amber-400' : 'bg-blue-900/50 text-blue-400'">
                                        <span class="text-xs font-bold">{{ item.mode?.charAt(0) }}</span>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="text-slate-300 font-medium">{{ item.mode }}</span>
                                            <span class="text-slate-400">{{ item.count }} shipments</span>
                                        </div>
                                        <div class="bg-slate-800 rounded-full h-2">
                                            <div class="h-2 rounded-full transition-all"
                                                :class="item.mode === 'COD' ? 'bg-amber-500' : 'bg-blue-500'"
                                                :style="{ width: analytics.total ? (item.count / analytics.total * 100) + '%' : '0%' }"></div>
                                        </div>
                                    </div>
                                </div>
                                <p v-if="!analytics.payment_breakdown?.length" class="text-xs text-slate-500 text-center py-4">No data yet</p>
                            </div>
                        </div>

                        <!-- Zone Breakdown -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                            <h3 class="text-sm font-semibold text-slate-300 mb-4">Zone Performance</h3>
                            <div class="space-y-2">
                                <div class="grid grid-cols-4 text-xs text-slate-500 pb-1 border-b border-slate-800">
                                    <span>Zone</span><span class="text-right">Shipments</span><span class="text-right">Delivered</span><span class="text-right">Avg Days</span>
                                </div>
                                <div v-for="z in (analytics.zone_breakdown ?? [])" :key="z.zone" class="grid grid-cols-4 text-xs py-1.5">
                                    <span class="text-slate-300 font-medium">{{ z.zone || 'Unknown' }}</span>
                                    <span class="text-right text-slate-400">{{ fmt(z.count) }}</span>
                                    <span class="text-right text-emerald-400">{{ pct(z.delivered, z.count) }}</span>
                                    <span class="text-right text-blue-400">{{ z.avg_days ? z.avg_days.toFixed(1) + 'd' : '—' }}</span>
                                </div>
                                <p v-if="!analytics.zone_breakdown?.length" class="text-xs text-slate-500 text-center py-4">No zone data yet</p>
                            </div>
                        </div>

                        <!-- Quick Track -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                            <h3 class="text-sm font-semibold text-slate-300 mb-4">Quick Track AWB</h3>
                            <div class="flex gap-2 mb-4">
                                <input v-model="awbInput" @keyup.enter="trackAWB" placeholder="Enter AWB number…"
                                    class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-purple-500"/>
                                <button @click="trackAWB" :disabled="tracking || !awbInput"
                                    class="px-4 py-2 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 rounded-lg text-sm font-medium transition-colors">
                                    {{ tracking ? '…' : 'Track' }}
                                </button>
                            </div>
                            <div v-if="trackResult">
                                <div v-if="trackResult.error" class="text-sm text-red-400">{{ trackResult.error }}</div>
                                <div v-else class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-slate-400">AWB</span>
                                        <span class="text-xs font-mono text-slate-200">{{ trackResult.waybill }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-slate-400">Status</span>
                                        <span :class="statusBg(trackResult.status)" class="text-xs px-2 py-0.5 rounded-full">{{ trackResult.status }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-slate-400">Last Scan</span>
                                        <span class="text-xs text-slate-300">{{ trackResult.last_scan || '—' }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs text-slate-400">Location</span>
                                        <span class="text-xs text-slate-300">{{ trackResult.last_location || '—' }}</span>
                                    </div>
                                </div>
                            </div>
                            <p v-if="!isConnected" class="text-xs text-slate-500 text-center py-2">Connect API to enable live tracking</p>
                        </div>
                    </div>

                    <!-- No data empty -->
                    <div v-else-if="!analyticsLoading && isConnected" class="text-center py-12 text-slate-500 text-sm">
                        No shipment data available. Sync or import shipments to see analytics.
                    </div>
                </div>

                <!-- ────────────────────── SHIPMENTS ────────────────────── -->
                <div v-if="activeTab === 'shipments'">
                    <div class="flex items-center gap-3 mb-4 flex-wrap">
                        <input v-model="searchAWB" placeholder="Search AWB…"
                            class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-purple-500 w-56"/>
                        <select v-model="statusFilter"
                            class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-200 focus:outline-none focus:border-purple-500">
                            <option value="">All Statuses</option>
                            <option>Delivered</option>
                            <option>In Transit</option>
                            <option>Out for Delivery</option>
                            <option>RTO</option>
                            <option>Pending Pickup</option>
                        </select>
                        <span class="text-xs text-slate-500 ml-auto">{{ filteredShipments.length }} shipments</span>
                    </div>

                    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-800">
                                        <th class="text-left px-4 py-3 text-xs font-medium text-slate-400">AWB</th>
                                        <th class="text-left px-4 py-3 text-xs font-medium text-slate-400">Status</th>
                                        <th class="text-left px-4 py-3 text-xs font-medium text-slate-400">Consignee</th>
                                        <th class="text-left px-4 py-3 text-xs font-medium text-slate-400">Origin</th>
                                        <th class="text-left px-4 py-3 text-xs font-medium text-slate-400">Dest Pincode</th>
                                        <th class="text-right px-4 py-3 text-xs font-medium text-slate-400">Weight</th>
                                        <th class="text-right px-4 py-3 text-xs font-medium text-slate-400">Charges</th>
                                        <th class="text-left px-4 py-3 text-xs font-medium text-slate-400">Last Scan</th>
                                        <th class="text-left px-4 py-3 text-xs font-medium text-slate-400">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="s in filteredShipments" :key="s.id"
                                        class="border-b border-slate-800/50 hover:bg-slate-800/30 transition-colors">
                                        <td class="px-4 py-3">
                                            <span class="font-mono text-xs text-purple-300">{{ s.waybill || s.awb_number || '—' }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span :class="statusBg(s.status)" class="text-xs px-2 py-0.5 rounded-full whitespace-nowrap">
                                                {{ s.status || 'Unknown' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-300">{{ s.consignee_name || s.recipient_name || '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-400">{{ s.origin_pincode || '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-400">{{ s.dest_pincode || s.pincode || '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-400 text-right">{{ s.weight ? s.weight + 'kg' : '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-300 text-right">{{ fmtCur(s.freight_charge ?? s.shipping_charge) }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-400 max-w-[180px] truncate">{{ s.last_scan || '—' }}</td>
                                        <td class="px-4 py-3 text-xs text-slate-500">{{ s.created_at ? new Date(s.created_at).toLocaleDateString('en-IN') : '—' }}</td>
                                    </tr>
                                    <tr v-if="!filteredShipments.length">
                                        <td colspan="9" class="px-4 py-12 text-center text-slate-500 text-sm">
                                            No shipments found. Import a CSV or sync via API.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="shipments?.links?.length > 3" class="flex items-center justify-between px-4 py-3 border-t border-slate-800">
                            <span class="text-xs text-slate-500">
                                Showing {{ shipments.from }}–{{ shipments.to }} of {{ shipments.total }}
                            </span>
                            <div class="flex gap-1">
                                <template v-for="link in shipments.links" :key="link.label">
                                    <button v-if="link.url" @click="router.get(link.url, {}, { preserveScroll: true })"
                                        :class="link.active ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'"
                                        class="px-3 py-1.5 rounded text-xs font-medium transition-colors"
                                        v-html="link.label"></button>
                                    <span v-else class="px-3 py-1.5 text-xs text-slate-600" v-html="link.label"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ────────────────────── RTO ANALYSIS ────────────────────── -->
                <div v-if="activeTab === 'rto'">
                    <div v-if="analyticsLoading" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div v-for="i in 4" :key="i" class="bg-slate-900 border border-slate-800 rounded-xl p-5 h-48 animate-pulse"></div>
                    </div>
                    <div v-else-if="analytics" class="space-y-4">
                        <!-- RTO Summary cards -->
                        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="bg-slate-900 border border-red-900/40 rounded-xl p-4">
                                <p class="text-xs text-slate-500 mb-1">Total RTO</p>
                                <p class="text-2xl font-bold text-red-400">{{ fmt(analytics.rto_count) }}</p>
                            </div>
                            <div class="bg-slate-900 border border-red-900/40 rounded-xl p-4">
                                <p class="text-xs text-slate-500 mb-1">RTO Rate</p>
                                <p class="text-2xl font-bold text-red-400">{{ (analytics.rto_rate ?? 0).toFixed(1) }}%</p>
                            </div>
                            <div class="bg-slate-900 border border-amber-900/40 rounded-xl p-4">
                                <p class="text-xs text-slate-500 mb-1">COD RTO Rate</p>
                                <p class="text-2xl font-bold text-amber-400">{{ analytics.cod_rto_rate != null ? analytics.cod_rto_rate.toFixed(1) + '%' : '—' }}</p>
                            </div>
                            <div class="bg-slate-900 border border-blue-900/40 rounded-xl p-4">
                                <p class="text-xs text-slate-500 mb-1">Prepaid RTO Rate</p>
                                <p class="text-2xl font-bold text-blue-400">{{ analytics.prepaid_rto_rate != null ? analytics.prepaid_rto_rate.toFixed(1) + '%' : '—' }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <!-- Top RTO Pincodes -->
                            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                                <h3 class="text-sm font-semibold text-slate-300 mb-4">Top RTO Pincodes</h3>
                                <div class="space-y-2">
                                    <div class="grid grid-cols-3 text-xs text-slate-500 pb-1 border-b border-slate-800">
                                        <span>Pincode</span><span class="text-right">RTO Count</span><span class="text-right">% of Total RTO</span>
                                    </div>
                                    <div v-for="p in (analytics.top_rto_pincodes ?? [])" :key="p.pincode" class="grid grid-cols-3 text-xs py-1.5">
                                        <span class="font-mono text-slate-300">{{ p.pincode || '—' }}</span>
                                        <span class="text-right text-red-400 font-medium">{{ p.count }}</span>
                                        <span class="text-right text-slate-400">{{ analytics.rto_count ? ((p.count / analytics.rto_count) * 100).toFixed(1) + '%' : '—' }}</span>
                                    </div>
                                    <p v-if="!analytics.top_rto_pincodes?.length" class="text-xs text-slate-500 text-center py-4">No RTO data yet</p>
                                </div>
                            </div>

                            <!-- RTO by Payment Mode bar -->
                            <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                                <h3 class="text-sm font-semibold text-slate-300 mb-4">RTO by Payment Mode</h3>
                                <div class="space-y-5 mt-2">
                                    <div v-for="item in (analytics.payment_breakdown ?? [])" :key="item.mode">
                                        <div class="flex justify-between text-xs mb-1.5">
                                            <span class="text-slate-300">{{ item.mode }}</span>
                                            <span class="text-slate-400">{{ item.rto }} RTO / {{ item.count }} total</span>
                                        </div>
                                        <div class="bg-slate-800 rounded-full h-3 relative overflow-hidden">
                                            <div class="h-3 rounded-full transition-all"
                                                :class="item.mode === 'COD' ? 'bg-amber-500' : 'bg-blue-500'"
                                                :style="{ width: item.count ? (item.rto / item.count * 100) + '%' : '0%' }"></div>
                                        </div>
                                        <p class="text-xs text-slate-500 mt-1">{{ item.count ? ((item.rto / item.count) * 100).toFixed(1) : 0 }}% RTO rate</p>
                                    </div>
                                    <p v-if="!analytics.payment_breakdown?.length" class="text-xs text-slate-500 text-center py-4">No data</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-16 text-slate-500 text-sm">
                        <p v-if="!isConnected">Connect Delhivery API to see RTO analytics.</p>
                        <p v-else>No data. Import shipments or sync to get RTO analysis.</p>
                    </div>
                </div>

                <!-- ────────────────────── PINCODE ANALYTICS ────────────────────── -->
                <div v-if="activeTab === 'pincode'">
                    <div v-if="analyticsLoading" class="grid grid-cols-1 gap-4">
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 h-64 animate-pulse"></div>
                    </div>
                    <div v-else-if="analytics" class="space-y-4">
                        <!-- Pincode table -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
                            <div class="px-5 py-4 border-b border-slate-800">
                                <h3 class="text-sm font-semibold text-slate-300">Delivery Success by Pincode</h3>
                                <p class="text-xs text-slate-500 mt-0.5">Top 20 destination pincodes</p>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-800">
                                            <th class="text-left px-5 py-3 text-xs font-medium text-slate-400">#</th>
                                            <th class="text-left px-5 py-3 text-xs font-medium text-slate-400">Pincode</th>
                                            <th class="text-right px-5 py-3 text-xs font-medium text-slate-400">Total</th>
                                            <th class="text-right px-5 py-3 text-xs font-medium text-slate-400">Delivered</th>
                                            <th class="text-right px-5 py-3 text-xs font-medium text-slate-400">RTO</th>
                                            <th class="text-right px-5 py-3 text-xs font-medium text-slate-400">Success Rate</th>
                                            <th class="text-left px-5 py-3 text-xs font-medium text-slate-400">Visual</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="(p, i) in (analytics.pincode_breakdown ?? [])" :key="p.pincode"
                                            class="border-b border-slate-800/50 hover:bg-slate-800/30 transition-colors">
                                            <td class="px-5 py-3 text-xs text-slate-500">{{ i + 1 }}</td>
                                            <td class="px-5 py-3 font-mono text-sm text-slate-200">{{ p.pincode }}</td>
                                            <td class="px-5 py-3 text-xs text-slate-300 text-right">{{ fmt(p.count) }}</td>
                                            <td class="px-5 py-3 text-xs text-emerald-400 text-right">{{ fmt(p.delivered) }}</td>
                                            <td class="px-5 py-3 text-xs text-red-400 text-right">{{ fmt(p.rto) }}</td>
                                            <td class="px-5 py-3 text-right">
                                                <span :class="p.success_rate >= 80 ? 'text-emerald-400' : p.success_rate >= 60 ? 'text-amber-400' : 'text-red-400'"
                                                    class="text-xs font-semibold">{{ p.success_rate?.toFixed(1) }}%</span>
                                            </td>
                                            <td class="px-5 py-3 w-32">
                                                <div class="bg-slate-800 rounded-full h-1.5">
                                                    <div class="h-1.5 rounded-full transition-all"
                                                        :class="p.success_rate >= 80 ? 'bg-emerald-500' : p.success_rate >= 60 ? 'bg-amber-500' : 'bg-red-500'"
                                                        :style="{ width: (p.success_rate ?? 0) + '%' }"></div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr v-if="!analytics.pincode_breakdown?.length">
                                            <td colspan="7" class="px-5 py-10 text-center text-slate-500 text-sm">No pincode data yet</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Serviceability checker -->
                        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
                            <h3 class="text-sm font-semibold text-slate-300 mb-1">Pincode Serviceability Check</h3>
                            <p class="text-xs text-slate-500 mb-4">Check if a pincode is serviceable for delivery</p>
                            <PincodeChecker :tenant="$page.props.tenant" />
                        </div>
                    </div>
                    <div v-else class="text-center py-16 text-slate-500 text-sm">
                        <p v-if="!isConnected">Connect Delhivery API to check pincode serviceability.</p>
                        <p v-else>No data yet. Import shipments to see pincode analytics.</p>
                    </div>
                </div>

                <!-- ────────────────────── INVOICES ────────────────────── -->
                <div v-if="activeTab === 'invoices'">
                    <!-- Upload zone -->
                    <div class="mb-6">
                        <div @dragover.prevent="invoiceDragging = true" @dragleave="invoiceDragging = false" @drop.prevent="onInvoiceDrop"
                            :class="invoiceDragging ? 'border-purple-500 bg-purple-900/10' : 'border-slate-700 hover:border-slate-600'"
                            class="border-2 border-dashed rounded-xl p-8 text-center transition-all cursor-pointer"
                            @click="$refs.invoiceInput.click()">
                            <input ref="invoiceInput" type="file" accept=".pdf,.csv" class="hidden" @change="onInvoiceDrop"/>
                            <div v-if="invoiceUploading" class="flex items-center justify-center gap-2 text-purple-400">
                                <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span class="text-sm">Extracting with AI…</span>
                            </div>
                            <div v-else>
                                <svg class="w-10 h-10 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <p class="text-sm text-slate-400">Drop Delhivery invoice PDF or CSV</p>
                                <p class="text-xs text-slate-600 mt-1">AI will extract all transactions automatically</p>
                            </div>
                        </div>
                    </div>

                    <!-- Invoices list -->
                    <div class="space-y-3">
                        <div v-for="inv in invoices" :key="inv.id"
                            class="bg-slate-900 border border-slate-800 rounded-xl p-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-200 truncate">{{ inv.invoice_number || 'Invoice' }}</p>
                                    <p class="text-xs text-slate-500">{{ inv.invoice_date }} · {{ inv.invoice_type || 'Freight' }}</p>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold text-white">{{ fmtCur(inv.total_amount) }}</p>
                                <p class="text-xs text-slate-500">GST {{ fmtCur((inv.cgst ?? 0) + (inv.sgst ?? 0) + (inv.igst ?? 0)) }}</p>
                            </div>
                            <a v-if="inv.file_url" :href="inv.file_url" target="_blank"
                                class="p-2 rounded-lg bg-slate-800 hover:bg-slate-700 transition-colors shrink-0">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        </div>
                        <p v-if="!invoices?.length" class="text-center py-10 text-slate-500 text-sm">
                            No invoices uploaded yet. Drop a PDF or CSV above.
                        </p>
                    </div>
                </div>

            </div>
        </div>

        <!-- ── Connect Modal -->
        <Teleport to="body">
            <div v-if="showConnectModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4">
                <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-md p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-base font-semibold text-white">{{ isConnected ? 'Delhivery Settings' : 'Connect Delhivery API' }}</h2>
                        <button @click="showConnectModal = false" class="text-slate-400 hover:text-slate-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-400 mb-1.5">API Token</label>
                            <input v-model="apiToken" type="password" placeholder="Your Delhivery API token"
                                class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-purple-500"/>
                            <p class="text-xs text-slate-600 mt-1.5">Find this in Delhivery One → Developer Portal → API Keys</p>
                        </div>
                        <p v-if="connectError" class="text-xs text-red-400">{{ connectError }}</p>
                        <div class="flex items-center gap-3 pt-1">
                            <button @click="connectDelhivery" :disabled="connecting || !apiToken"
                                class="flex-1 py-2.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 rounded-lg text-sm font-medium transition-colors">
                                {{ connecting ? 'Verifying…' : isConnected ? 'Update Token' : 'Connect' }}
                            </button>
                            <button v-if="isConnected" @click="disconnectDelhivery"
                                class="px-4 py-2.5 bg-red-900/40 hover:bg-red-900/60 text-red-400 rounded-lg text-sm font-medium transition-colors">
                                Disconnect
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
