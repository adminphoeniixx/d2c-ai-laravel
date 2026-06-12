<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Package, Truck, RotateCcw, Eye } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    invoice: { type: Object, default: () => ({}) },
    shipments: { type: Object, default: () => ({ data: [] }) },
    breakdown: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const fmtDec = (v) => '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) : '—';

const statusColors = {
    Delivered: 'bg-emerald-500/15 text-emerald-400',
    RTO: 'bg-rose-500/15 text-rose-400',
    DTO: 'bg-rose-500/15 text-rose-400',
    VAS: 'bg-purple-500/15 text-purple-400',
};

function goToPage(url) {
    if (url) router.visit(url, { preserveState: true, preserveScroll: true });
}
</script>

<template>
<Head :title="'Invoice ' + invoice.invoice_number" />
<TenantLayout>
    <div class="max-w-5xl">
        <div class="flex items-center gap-3 mb-5">
            <button @click="router.visit(route('tenant.logistics.partner', { tenant: slug, partnerId: invoice.delivery_partner_id }))"
                class="text-ink-3 hover:text-white cursor-pointer"><ArrowLeft :size="18" /></button>
            <div class="flex-1">
                <h2 class="text-[20px] font-bold text-white">{{ invoice.invoice_number }}</h2>
                <div class="text-[12px] text-ink-3 mt-0.5">
                    {{ invoice.partner?.name }} · {{ dateFmt(invoice.invoice_date) }}
                    <span v-if="invoice.period_from"> · {{ dateFmt(invoice.period_from) }} → {{ dateFmt(invoice.period_to) }}</span>
                    · <span class="capitalize">{{ invoice.type }}</span>
                </div>
            </div>
            <a v-if="invoice.file_url" :href="invoice.file_url" target="_blank" class="btn btn-ghost btn-sm flex items-center gap-1">
                <Eye :size="14" /> View PDF
            </a>
        </div>

        <!-- Invoice Summary -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
            <div class="card text-center">
                <div class="text-[18px] font-bold text-white">{{ invoice.shipment_count || 0 }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Shipments</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-white">{{ fmtDec(invoice.subtotal) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Subtotal</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-amber-400">{{ fmtDec(parseFloat(invoice.cgst || 0) + parseFloat(invoice.sgst || 0) + parseFloat(invoice.igst || 0)) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">GST</div>
            </div>
            <div class="card text-center">
                <div class="text-[18px] font-bold text-brand-300">{{ fmtDec(invoice.total_amount) }}</div>
                <div class="text-[9px] text-ink-3 uppercase">Total Amount</div>
            </div>
        </div>

        <!-- Charge Breakdown + Zone Split -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
            <div class="card">
                <h4 class="text-[12px] font-semibold text-ink-3 uppercase tracking-widest mb-3">Charge Breakdown</h4>
                <div class="space-y-2 text-[13px]">
                    <div class="flex justify-between"><span class="text-ink-3">Freight (Delivery)</span><span class="text-white font-mono">{{ fmtDec(breakdown.freight) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-3">COD Charges</span><span class="text-white font-mono">{{ fmtDec(breakdown.cod) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-3">RTO/DTO Charges</span><span class="text-rose-400 font-mono">{{ fmtDec(breakdown.rto) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-3">Fuel Surcharge</span><span class="text-white font-mono">{{ fmtDec(breakdown.fuel) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-3">Pickup</span><span class="text-white font-mono">{{ fmtDec(breakdown.pickup) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-3">VAS</span><span class="text-white font-mono">{{ fmtDec(breakdown.vas) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-3">Other</span><span class="text-white font-mono">{{ fmtDec(breakdown.other) }}</span></div>
                    <div class="flex justify-between pt-2 border-t border-frost-1">
                        <span class="text-ink-3">Delivered</span><span class="text-emerald-400 font-bold">{{ breakdown.delivered || 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-3">RTO/DTO Count</span><span class="text-rose-400 font-bold">{{ breakdown.rto_count || 0 }}</span>
                    </div>
                </div>
            </div>
            <div class="card">
                <h4 class="text-[12px] font-semibold text-ink-3 uppercase tracking-widest mb-3">Zone Split</h4>
                <div class="space-y-2 text-[13px]">
                    <div v-for="z in breakdown.by_zone" :key="z.zone" class="flex justify-between">
                        <span class="text-ink-3">Zone {{ z.zone }}</span>
                        <span class="text-white"><span class="text-ink-3 mr-2">{{ z.count }} shipments</span><span class="font-mono font-bold">{{ fmtDec(z.total) }}</span></span>
                    </div>
                    <div v-if="!breakdown.by_zone?.length" class="text-ink-3 text-[12px]">No zone data</div>
                </div>
            </div>
        </div>

        <!-- Shipments Table -->
        <div class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-frost-1">
                <h3 class="text-[14px] font-semibold text-white">Shipments</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[11px]">
                    <thead class="bg-bg-3 text-[9px] font-mono uppercase tracking-wider text-ink-3">
                        <tr>
                            <th class="text-left px-4 py-2.5">AWB</th>
                            <th class="text-left px-3 py-2.5">Order</th>
                            <th class="text-left px-3 py-2.5">Status</th>
                            <th class="text-left px-3 py-2.5">Mode</th>
                            <th class="text-left px-3 py-2.5">Zone</th>
                            <th class="text-right px-3 py-2.5">Weight</th>
                            <th class="text-right px-3 py-2.5">Freight</th>
                            <th class="text-right px-3 py-2.5">COD</th>
                            <th class="text-right px-3 py-2.5">RTO</th>
                            <th class="text-right px-3 py-2.5">GST</th>
                            <th class="text-right px-4 py-2.5">Total</th>
                            <th class="text-left px-3 py-2.5">Pickup</th>
                            <th class="text-left px-3 py-2.5">Delivered</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-frost-1">
                        <tr v-for="s in shipments.data" :key="s.id" class="hover:bg-brand-600/5 transition">
                            <td class="px-4 py-2 font-mono text-brand-300">{{ s.waybill }}</td>
                            <td class="px-3 py-2 text-ink-2">{{ s.order_id || '—' }}</td>
                            <td class="px-3 py-2">
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-bold" :class="statusColors[s.status] || 'bg-ink-3/15 text-ink-3'">{{ s.status }}</span>
                            </td>
                            <td class="px-3 py-2 text-ink-3">{{ s.payment_mode || '—' }}</td>
                            <td class="px-3 py-2 text-ink-3">{{ s.zone || '—' }}</td>
                            <td class="px-3 py-2 text-right text-ink-2">{{ s.charged_weight }}g</td>
                            <td class="px-3 py-2 text-right font-mono text-ink-2">{{ fmtDec(s.charge_freight) }}</td>
                            <td class="px-3 py-2 text-right font-mono" :class="parseFloat(s.charge_cod) > 0 ? 'text-amber-400' : 'text-ink-3'">{{ parseFloat(s.charge_cod) > 0 ? fmtDec(s.charge_cod) : '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono" :class="parseFloat(s.charge_rto) > 0 ? 'text-rose-400' : 'text-ink-3'">{{ parseFloat(s.charge_rto) > 0 ? fmtDec(s.charge_rto) : '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono text-ink-3">{{ fmtDec(parseFloat(s.cgst || 0) + parseFloat(s.sgst || 0)) }}</td>
                            <td class="px-4 py-2 text-right font-mono font-bold text-white">{{ fmtDec(s.total_amount) }}</td>
                            <td class="px-3 py-2 text-ink-3">{{ dateFmt(s.pickup_date) }}</td>
                            <td class="px-3 py-2 text-ink-3">{{ dateFmt(s.delivered_date) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-frost-1 text-[12px] text-ink-3 flex items-center justify-between">
                <span>Page {{ shipments.current_page || 1 }} of {{ shipments.last_page || 1 }}</span>
                <div class="flex gap-1">
                    <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!shipments.prev_page_url" @click="goToPage(shipments.prev_page_url)">← Prev</button>
                    <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!shipments.next_page_url" @click="goToPage(shipments.next_page_url)">Next →</button>
                </div>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
