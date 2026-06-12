<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ShoppingBag, Upload, RefreshCw, Unplug, Eye, Check, AlertCircle } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    credentials: { type: Object, default: () => ({}) },
    orderCounts: { type: Object, default: () => ({}) },
    marketplaces: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const activeModal = ref(null);
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));

// API connect form
const connectForm = useForm({ marketplace: '', credentials: {} });

function openConnect(mp) {
    connectForm.marketplace = mp.id;
    connectForm.credentials = {};
    mp.fields.forEach(f => { connectForm.credentials[f] = ''; });
    activeModal.value = { type: 'connect', mp };
}
function submitConnect() {
    connectForm.post(route('tenant.marketplaces.connect', { tenant: slug }), {
        onSuccess: () => { activeModal.value = null; },
    });
}

// CSV import form
const csvForm = useForm({ marketplace: '', file: null });

function openImport(mp) {
    csvForm.marketplace = mp.id;
    csvForm.file = null;
    activeModal.value = { type: 'import', mp };
}
function submitImport() {
    csvForm.post(route('tenant.marketplaces.import-csv', { tenant: slug }), {
        forceFormData: true,
        onSuccess: () => { activeModal.value = null; },
    });
}

function syncMarketplace(mpId) {
    router.post(route('tenant.marketplaces.sync', { tenant: slug, marketplace: mpId }));
}
function disconnectMarketplace(mpId) {
    if (confirm(`Disconnect ${mpId}?`)) {
        router.delete(route('tenant.marketplaces.disconnect', { tenant: slug, marketplace: mpId }));
    }
}

function getCred(mpId) { return props.credentials[mpId] || null; }
function getOrders(mpId) { return props.orderCounts[mpId] || null; }

const fieldLabels = {
    client_id: 'Client ID', client_secret: 'Client Secret', refresh_token: 'Refresh Token (LWA)',
    app_id: 'App ID', app_secret: 'App Secret',
};
</script>

<template>
<Head title="Marketplaces" />
<TenantLayout>
    <div class="max-w-3xl">
        <div class="mb-5">
            <h2 class="text-[20px] font-bold text-white">Marketplace Integrations</h2>
            <p class="text-[12px] text-ink-3 mt-1">Connect Amazon & Flipkart via API, or import Myntra & Nykaa orders via CSV</p>
        </div>

        <!-- Marketplace Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div v-for="mp in marketplaces" :key="mp.id" class="card hover:border-brand-600/40 transition">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="h-11 w-11 rounded-xl bg-bg-3 flex items-center justify-center flex-shrink-0">
                            <ShoppingBag :size="20" :class="mp.color" />
                        </div>
                        <div>
                            <div class="text-[15px] font-bold text-white">{{ mp.name }}</div>
                            <div class="text-[11px] text-ink-3">{{ mp.description }}</div>
                        </div>
                    </div>
                    <span v-if="getCred(mp.id)?.status === 'connected'"
                        class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-500/20 text-emerald-400 flex items-center gap-1">
                        <Check :size="9" /> Connected
                    </span>
                    <span v-else-if="getCred(mp.id)?.last_error"
                        class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-rose-500/20 text-rose-400 flex items-center gap-1">
                        <AlertCircle :size="9" /> Error
                    </span>
                </div>

                <!-- Stats -->
                <div v-if="getOrders(mp.id)" class="grid grid-cols-2 gap-2 mb-3">
                    <div class="rounded-lg bg-bg-3 border border-frost-1 px-3 py-2 text-center">
                        <div class="text-[16px] font-bold text-white">{{ getOrders(mp.id).total }}</div>
                        <div class="text-[9px] text-ink-3">Orders</div>
                    </div>
                    <div class="rounded-lg bg-bg-3 border border-frost-1 px-3 py-2 text-center">
                        <div class="text-[14px] font-bold text-emerald-400">{{ fmt(getOrders(mp.id).revenue) }}</div>
                        <div class="text-[9px] text-ink-3">Revenue</div>
                    </div>
                </div>

                <!-- Last synced -->
                <div v-if="getCred(mp.id)?.last_synced_at" class="text-[10px] text-ink-3 mb-3">
                    Last synced: {{ new Date(getCred(mp.id).last_synced_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) }}
                </div>
                <div v-if="getCred(mp.id)?.last_error" class="text-[10px] text-rose mb-3 truncate" :title="getCred(mp.id).last_error">
                    Error: {{ getCred(mp.id).last_error }}
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 flex-wrap">
                    <template v-if="mp.type === 'api'">
                        <template v-if="getCred(mp.id)?.status === 'connected'">
                            <button @click="syncMarketplace(mp.id)" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer">
                                <RefreshCw :size="12" /> Sync Now
                            </button>
                            <button @click="$inertia.visit(route('tenant.marketplaces.orders', { tenant: slug, marketplace: mp.id }))" class="btn btn-ghost btn-sm flex items-center gap-1 cursor-pointer" v-if="getOrders(mp.id)">
                                <Eye :size="12" /> View Orders
                            </button>
                            <button @click="disconnectMarketplace(mp.id)" class="btn btn-ghost btn-sm text-rose cursor-pointer">
                                <Unplug :size="12" />
                            </button>
                        </template>
                        <button v-else @click="openConnect(mp)" class="btn btn-primary btn-sm cursor-pointer">Connect API</button>
                    </template>
                    <template v-else>
                        <button @click="openImport(mp)" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer">
                            <Upload :size="12" /> Import CSV
                        </button>
                        <button @click="router.visit(route('tenant.marketplaces.orders', { tenant: slug, marketplace: mp.id }))" class="btn btn-ghost btn-sm flex items-center gap-1 cursor-pointer" v-if="getOrders(mp.id)">
                            <Eye :size="12" /> View Orders
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Connect API Modal -->
        <div v-if="activeModal?.type === 'connect'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="activeModal = null">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-1">Connect {{ activeModal.mp.name }}</h3>
                <p class="text-[12px] text-ink-3 mb-4">Enter your API credentials from the {{ activeModal.mp.name }} Seller Portal</p>
                <form @submit.prevent="submitConnect" class="space-y-3">
                    <div v-for="field in activeModal.mp.fields" :key="field">
                        <label class="heyd2c-label">{{ fieldLabels[field] || field }}</label>
                        <input v-model="connectForm.credentials[field]" class="heyd2c-input font-mono text-[12px]" :placeholder="field" required />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1" :disabled="connectForm.processing">
                            {{ connectForm.processing ? 'Connecting…' : 'Connect' }}
                        </button>
                        <button type="button" class="btn btn-ghost flex-1" @click="activeModal = null">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import CSV Modal -->
        <div v-if="activeModal?.type === 'import'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="activeModal = null">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-1">Import {{ activeModal.mp.name }} Orders</h3>
                <p class="text-[12px] text-ink-3 mb-4">
                    Export orders from the {{ activeModal.mp.name }} seller portal as CSV and upload here.
                    The system will auto-detect column mapping.
                </p>
                <form @submit.prevent="submitImport" class="space-y-3">
                    <div>
                        <label class="heyd2c-label">CSV / Excel File</label>
                        <input type="file" accept=".csv,.xlsx,.xls,.txt" @change="csvForm.file = $event.target.files[0]"
                            class="heyd2c-input text-[12px]" required />
                        <p class="mt-1 text-[10px] text-ink-3">Supported: CSV, XLSX, XLS — max 10MB</p>
                    </div>
                    <div class="rounded-[10px] bg-bg-3 border border-frost-1 p-3">
                        <div class="text-[11px] font-mono uppercase tracking-widest text-ink-3 mb-2">Expected Columns</div>
                        <div class="text-[11px] text-ink-2 space-y-0.5">
                            <div>Order ID, SKU, Product Name, Quantity, Selling Price</div>
                            <div>Status, Customer Name, City, State, Pincode, Order Date</div>
                            <div class="text-ink-3 italic">Column names are auto-detected — exact names may vary</div>
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1" :disabled="csvForm.processing">
                            {{ csvForm.processing ? 'Importing…' : 'Import Orders' }}
                        </button>
                        <button type="button" class="btn btn-ghost flex-1" @click="activeModal = null">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
