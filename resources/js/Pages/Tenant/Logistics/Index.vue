<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Truck, Package, RotateCcw, AlertTriangle, IndianRupee, ArrowRight, Upload, FileText } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    partners: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const showUpload = ref(false);

const uploadForm = useForm({ invoice_pdf: null, invoice_csv: null });
function submitUpload() {
    uploadForm.post(route('tenant.logistics.smart-upload', { tenant: slug }), {
        forceFormData: true,
        onSuccess: () => { showUpload.value = false; uploadForm.reset(); },
    });
}
</script>

<template>
<Head title="Logistics" />
<TenantLayout>
    <div class="max-w-4xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white flex items-center gap-2"><Truck :size="20" /> Logistics</h2>
                <p class="text-[12px] text-ink-3 mt-1">Delivery partners, shipments, invoices & RTO tracking</p>
            </div>
            <button @click="showUpload = true" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer"><Upload :size="12" /> Upload Invoice</button>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="card text-center">
                <Package :size="16" class="text-brand-300 mx-auto mb-1" />
                <div class="text-[20px] font-bold text-white">{{ stats.total_shipments || 0 }}</div>
                <div class="text-[10px] text-ink-3 uppercase tracking-wider">Total Shipments</div>
            </div>
            <div class="card text-center">
                <div class="text-[20px] font-bold text-emerald-400">{{ stats.total_delivered || 0 }}</div>
                <div class="text-[10px] text-ink-3 uppercase tracking-wider">Delivered</div>
            </div>
            <div class="card text-center">
                <RotateCcw :size="16" class="text-rose-400 mx-auto mb-1" />
                <div class="text-[20px] font-bold text-rose-400">{{ stats.total_rto || 0 }}</div>
                <div class="text-[10px] text-ink-3 uppercase tracking-wider">RTO / DTO</div>
                <div class="text-[11px] font-bold mt-0.5" :class="stats.rto_rate > 5 ? 'text-rose-400' : 'text-emerald-400'">{{ stats.rto_rate }}% RTO Rate</div>
            </div>
            <div class="card text-center">
                <IndianRupee :size="16" class="text-amber-400 mx-auto mb-1" />
                <div class="text-[20px] font-bold text-white">{{ fmt(stats.total_freight) }}</div>
                <div class="text-[10px] text-ink-3 uppercase tracking-wider">Total Freight Cost</div>
                <div class="text-[11px] text-ink-3 mt-0.5">Avg {{ fmt(stats.avg_shipping_cost) }}/shipment</div>
            </div>
        </div>

        <!-- Delivery Partners -->
        <div class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-frost-1">
                <h3 class="text-[14px] font-semibold text-white">Delivery Partners</h3>
            </div>
            <div v-for="p in partners" :key="p.id"
                class="px-5 py-3.5 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition cursor-pointer flex items-center justify-between"
                @click="router.visit(route('tenant.logistics.partner', { tenant: slug, partnerId: p.id }))">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-brand-600/15 flex items-center justify-center">
                        <Truck :size="18" class="text-brand-300" />
                    </div>
                    <div>
                        <div class="text-[14px] font-medium text-white">{{ p.name }}</div>
                        <div class="text-[11px] text-ink-3">
                            {{ p.shipments_count || 0 }} shipments · {{ p.invoices_count || 0 }} invoices
                            <span v-if="p.api_connected" class="text-emerald-400 ml-1">· API Connected</span>
                        </div>
                    </div>
                </div>
                <ArrowRight :size="16" class="text-ink-3" />
            </div>
        </div>
        <!-- Smart Upload Modal -->
        <div v-if="showUpload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showUpload = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-lg mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-1">Upload Logistics Invoice</h3>
                <p class="text-[12px] text-ink-3 mb-4">Drop your invoice PDF and/or CSV. Partner auto-detected (Delhivery, Shiprocket, etc.)</p>
                <form @submit.prevent="submitUpload" class="space-y-3">
                    <div class="border-2 border-dashed border-frost-1 rounded-xl p-5 text-center hover:border-brand-600/40 transition">
                        <Upload :size="22" class="text-ink-3 mx-auto mb-1" />
                        <div class="text-[11px] text-ink-3 mb-2">Invoice PDF</div>
                        <input type="file" accept=".pdf" class="heyd2c-input" @change="uploadForm.invoice_pdf = $event.target.files[0]" />
                    </div>
                    <div class="border-2 border-dashed border-frost-1 rounded-xl p-5 text-center hover:border-brand-600/40 transition">
                        <FileText :size="22" class="text-ink-3 mx-auto mb-1" />
                        <div class="text-[11px] text-ink-3 mb-2">Shipment CSV (optional)</div>
                        <input type="file" accept=".csv,.txt" class="heyd2c-input" @change="uploadForm.invoice_csv = $event.target.files[0]" />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1 cursor-pointer" :disabled="uploadForm.processing">
                            {{ uploadForm.processing ? 'Reading & Uploading…' : 'Upload & Auto-Detect' }}
                        </button>
                        <button type="button" class="btn btn-ghost flex-1 cursor-pointer" @click="showUpload = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
