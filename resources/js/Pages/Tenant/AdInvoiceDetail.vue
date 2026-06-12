<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Eye, Trash2 } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    invoice: { type: Object, default: () => ({}) },
    entries: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const fmtDec = (v) => '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
<Head :title="'Invoice ' + (invoice.invoice_number || '')" />
<TenantLayout>
    <div class="max-w-4xl">
        <div class="flex items-center gap-3 mb-5">
            <button @click="router.visit(route('tenant.ads', { tenant: slug }))" class="text-ink-3 hover:text-white cursor-pointer"><ArrowLeft :size="18" /></button>
            <div class="flex-1">
                <h2 class="text-[20px] font-bold text-white">{{ invoice.invoice_number || 'Ad Invoice' }}</h2>
                <div class="text-[12px] text-ink-3 mt-0.5">
                    <span class="capitalize">{{ invoice.platform }}</span> · {{ dateFmt(invoice.invoice_date) }}
                    <span v-if="invoice.period_from"> · {{ dateFmt(invoice.period_from) }} → {{ dateFmt(invoice.period_to) }}</span>
                </div>
            </div>
            <a v-if="invoice.file_url" :href="invoice.file_url" target="_blank" class="btn btn-ghost btn-sm flex items-center gap-1"><Eye :size="14" /> View PDF</a>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="card text-center">
                <div class="text-[18px] font-bold text-rose-400">{{ fmt(invoice.subtotal || invoice.total_amount) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Ad Spend</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-amber-400">{{ fmt(invoice.tax) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">GST / Tax</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-white">{{ fmt(invoice.total_amount) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Total Billed</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-brand-300">{{ summary?.count || 0 }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Entries</div>
            </div>
        </div>

        <!-- Metadata -->
        <div v-if="invoice.metadata" class="card mb-5">
            <h4 class="text-[12px] font-semibold text-ink-3 uppercase tracking-widest mb-3">Invoice Details</h4>
            <div class="grid grid-cols-2 gap-2 text-[13px]">
                <div v-if="invoice.metadata.gstin" class="flex justify-between"><span class="text-ink-3">GSTIN</span><span class="text-white font-mono">{{ invoice.metadata.gstin }}</span></div>
                <div v-if="invoice.metadata.tds" class="flex justify-between"><span class="text-ink-3">TDS</span><span class="text-white font-mono">{{ fmtDec(invoice.metadata.tds) }}</span></div>
                <div v-if="invoice.metadata.source" class="flex justify-between"><span class="text-ink-3">Detected Source</span><span class="text-emerald-400 capitalize">{{ invoice.metadata.source }}</span></div>
            </div>
        </div>

        <!-- Entries Table -->
        <div class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-frost-1">
                <h3 class="text-[14px] font-semibold text-white">Spend Entries</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead class="bg-bg-3 text-[9px] font-mono uppercase tracking-wider text-ink-3">
                        <tr>
                            <th class="text-left px-4 py-2.5">Date</th>
                            <th class="text-left px-3 py-2.5">Campaign</th>
                            <th class="text-right px-3 py-2.5">Spend</th>
                            <th class="text-right px-3 py-2.5">Impressions</th>
                            <th class="text-right px-3 py-2.5">Clicks</th>
                            <th class="text-right px-3 py-2.5">Conversions</th>
                            <th class="text-left px-4 py-2.5">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-frost-1">
                        <tr v-for="e in entries" :key="e.id" class="hover:bg-brand-600/5">
                            <td class="px-4 py-2.5 text-ink-3">{{ dateFmt(e.date) }}</td>
                            <td class="px-3 py-2.5 text-white max-w-[200px] truncate">{{ e.campaign_name }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-rose-400 font-bold">{{ fmtDec(e.spend) }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-ink-2">{{ e.impressions || '—' }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-ink-2">{{ e.clicks || '—' }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-emerald-400">{{ e.conversions || '—' }}</td>
                            <td class="px-4 py-2.5 text-ink-3 text-[10px]">{{ e.source }}</td>
                        </tr>
                        <tr v-if="!entries?.length"><td colspan="7" class="px-5 py-8 text-center text-[13px] text-ink-3">No entries</td></tr>
                    </tbody>
                    <tfoot v-if="entries?.length" class="bg-bg-3 border-t border-frost-1">
                        <tr class="text-[12px] font-bold">
                            <td class="px-4 py-2.5 text-ink-3">Total</td>
                            <td class="px-3 py-2.5"></td>
                            <td class="px-3 py-2.5 text-right font-mono text-rose-400">{{ fmtDec(summary?.total_spend) }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-ink-2">{{ summary?.total_impressions || 0 }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-ink-2">{{ summary?.total_clicks || 0 }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-emerald-400">{{ summary?.total_conversions || 0 }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
