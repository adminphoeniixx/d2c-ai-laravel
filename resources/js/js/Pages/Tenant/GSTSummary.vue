<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Receipt, MapPin, Download, IndianRupee, Calendar, Filter } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    company: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({ cgst: 0, sgst: 0, igst: 0, total_gst: 0, taxable_amount: 0, order_count: 0, total_revenue: 0 }) },
    monthlySummary: { type: Array, default: () => [] },
    stateWise: { type: Array, default: () => [] },
    orders: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ from: '', to: '' }) },
});

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v));
const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const fromDate = ref(props.filters.from);
const toDate = ref(props.filters.to);

const gstConfigured = computed(() => !!props.company.gstin);

function applyFilter() {
    router.get(route('tenant.gst', { tenant: slug }), {
        from: fromDate.value,
        to: toDate.value,
    }, { preserveState: true });
}

function setPreset(preset) {
    const today = new Date();
    let from, to;

    switch (preset) {
        case 'this_month':
            from = new Date(today.getFullYear(), today.getMonth(), 1);
            to = today;
            break;
        case 'last_month':
            from = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            to = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'this_quarter':
            const qMonth = Math.floor(today.getMonth() / 3) * 3;
            from = new Date(today.getFullYear(), qMonth, 1);
            to = today;
            break;
        case 'last_quarter':
            const lqMonth = Math.floor(today.getMonth() / 3) * 3 - 3;
            from = new Date(today.getFullYear(), lqMonth, 1);
            to = new Date(today.getFullYear(), lqMonth + 3, 0);
            break;
        case 'this_fy':
            const fyStart = today.getMonth() >= 3 ? today.getFullYear() : today.getFullYear() - 1;
            from = new Date(fyStart, 3, 1);
            to = today;
            break;
        case 'last_fy':
            const lfyStart = today.getMonth() >= 3 ? today.getFullYear() - 1 : today.getFullYear() - 2;
            from = new Date(lfyStart, 3, 1);
            to = new Date(lfyStart + 1, 2, 31);
            break;
    }

    fromDate.value = from.toISOString().split('T')[0];
    toDate.value = to.toISOString().split('T')[0];
    applyFilter();
}

function exportGstr1() {
    window.location.href = route('tenant.gst.export', { tenant: slug }) + '?from=' + fromDate.value + '&to=' + toDate.value;
}
</script>

<template>
<Head title="GST Reports" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">GST Reports</h2>
            <p class="text-[12px] text-ink-3 mt-1">CGST · SGST · IGST breakdown · Auto-calculated from orders</p>
        </div>
        <div class="flex items-center gap-2">
            <Link :href="route('tenant.gst.recalculate', { tenant: slug })" method="post" as="button" class="btn btn-ghost">Recalculate All Orders</Link>
            <button class="btn btn-primary" @click="exportGstr1"><Download :size="14" /> Export GSTR-1</button>
        </div>
    </div>

    <!-- GSTIN not configured -->
    <div v-if="!gstConfigured" class="card border-amber/30 bg-amber/5 mb-5 flex items-start gap-3">
        <Receipt :size="18" class="text-amber mt-0.5 flex-shrink-0" />
        <div>
            <div class="font-semibold text-white text-[14px]">GSTIN Not Configured</div>
            <p class="text-[12px] text-ink-2 mt-1">
                Add your GSTIN in Settings to enable automatic CGST/SGST/IGST calculation.
            </p>
        </div>
    </div>

    <!-- Company GST Profile -->
    <div v-if="gstConfigured" class="card mb-5">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-[13px]">
            <div>
                <span class="heyd2c-label">GSTIN</span>
                <div class="font-mono text-brand-300 text-[14px]">{{ company.gstin }}</div>
            </div>
            <div>
                <span class="heyd2c-label">Registered State</span>
                <div class="text-ink">{{ company.registered_state_code }}</div>
            </div>
            <div>
                <span class="heyd2c-label">Business Category</span>
                <div class="text-ink capitalize">{{ company.business_category }}</div>
            </div>
            <div>
                <span class="heyd2c-label">Default GST Rate</span>
                <div class="text-ink">{{ company.default_gst_rate }}%</div>
            </div>
        </div>
    </div>

    <!-- Date Filter -->
    <div class="card mb-5">
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <div class="flex items-center gap-2">
                <Calendar :size="14" class="text-ink-3" />
                <span class="text-[12px] text-ink-3 font-mono">FROM</span>
                <input v-model="fromDate" type="date" class="heyd2c-input w-40 text-[12px]" @change="applyFilter" />
                <span class="text-[12px] text-ink-3 font-mono">TO</span>
                <input v-model="toDate" type="date" class="heyd2c-input w-40 text-[12px]" @change="applyFilter" />
            </div>
            <div class="flex flex-wrap gap-1.5">
                <button v-for="p in [
                    { key: 'this_month', label: 'This Month' },
                    { key: 'last_month', label: 'Last Month' },
                    { key: 'this_quarter', label: 'This Quarter' },
                    { key: 'last_quarter', label: 'Last Quarter' },
                    { key: 'this_fy', label: 'This FY' },
                    { key: 'last_fy', label: 'Last FY' },
                ]" :key="p.key"
                    class="px-2.5 py-1 text-[10px] font-mono rounded-full bg-bg-3 border border-frost-1 text-ink-3 hover:border-frost-3 hover:text-ink transition cursor-pointer"
                    @click="setPreset(p.key)"
                >{{ p.label }}</button>
            </div>
        </div>
    </div>

    <!-- KPI row -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-5">
        <KpiCard label="Taxable Amount" :value="summary.taxable_amount" format="currency" />
        <KpiCard label="CGST" :value="summary.cgst" format="currency" />
        <KpiCard label="SGST" :value="summary.sgst" format="currency" />
        <KpiCard label="IGST" :value="summary.igst" format="currency" />
        <KpiCard label="Total GST" :value="summary.total_gst" format="currency" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <!-- Monthly GST Trend -->
        <div class="card">
            <h3 class="text-[16px] font-bold text-white mb-4">Monthly GST Summary</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="text-[10px] font-mono uppercase tracking-wider text-ink-3">
                        <tr>
                            <th class="text-left pb-3">Month</th>
                            <th class="text-right pb-3">Orders</th>
                            <th class="text-right pb-3">Taxable</th>
                            <th class="text-right pb-3">CGST</th>
                            <th class="text-right pb-3">SGST</th>
                            <th class="text-right pb-3">IGST</th>
                            <th class="text-right pb-3">Total GST</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-frost-1">
                        <tr v-for="m in monthlySummary" :key="m.month" class="hover:bg-brand-600/5 transition">
                            <td class="py-3 font-medium text-white">{{ m.month_short }}</td>
                            <td class="py-3 text-right font-mono text-ink-2">{{ m.order_count }}</td>
                            <td class="py-3 text-right font-mono text-ink">{{ fmt(m.taxable_amount) }}</td>
                            <td class="py-3 text-right font-mono text-emerald">{{ fmt(m.cgst) }}</td>
                            <td class="py-3 text-right font-mono text-emerald">{{ fmt(m.sgst) }}</td>
                            <td class="py-3 text-right font-mono text-brand-300">{{ fmt(m.igst) }}</td>
                            <td class="py-3 text-right font-mono font-semibold text-white">{{ fmt(m.total_gst) }}</td>
                        </tr>
                        <tr v-if="!monthlySummary.length">
                            <td colspan="7" class="py-6 text-center text-ink-3">No data for selected period</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- State-wise Breakdown -->
        <div class="card">
            <h3 class="text-[16px] font-bold text-white mb-4 flex items-center gap-2">
                <MapPin :size="16" class="text-brand-400" /> State-wise Tax
            </h3>
            <div v-if="stateWise.length" class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="text-[10px] font-mono uppercase tracking-wider text-ink-3">
                        <tr>
                            <th class="text-left pb-3">State</th>
                            <th class="text-left pb-3">Type</th>
                            <th class="text-right pb-3">Orders</th>
                            <th class="text-right pb-3">CGST</th>
                            <th class="text-right pb-3">SGST</th>
                            <th class="text-right pb-3">IGST</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-frost-1">
                        <tr v-for="s in stateWise" :key="s.state" class="hover:bg-brand-600/5 transition">
                            <td class="py-3 text-white">{{ s.state }}</td>
                            <td class="py-3">
                                <span class="pill" :class="s.is_intra_state ? 'pill-good' : 'pill-info'">{{ s.type }}</span>
                            </td>
                            <td class="py-3 text-right font-mono text-ink-2">{{ s.order_count }}</td>
                            <td class="py-3 text-right font-mono" :class="s.cgst > 0 ? 'text-emerald' : 'text-ink-3'">{{ fmt(s.cgst) }}</td>
                            <td class="py-3 text-right font-mono" :class="s.sgst > 0 ? 'text-emerald' : 'text-ink-3'">{{ fmt(s.sgst) }}</td>
                            <td class="py-3 text-right font-mono" :class="s.igst > 0 ? 'text-brand-300' : 'text-ink-3'">{{ fmt(s.igst) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-[13px] text-ink-3">No state-wise data for selected period.</p>
        </div>
    </div>

    <!-- Orders with GST -->
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3 border-b border-frost-1 flex items-center justify-between">
            <h3 class="text-[15px] font-bold text-white">Orders · GST Breakdown</h3>
            <span class="text-[11px] font-mono text-ink-3">{{ orders.length }} orders</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                    <tr>
                        <th class="text-left px-5 py-3">Order</th>
                        <th class="text-left px-5 py-3">Customer</th>
                        <th class="text-right px-5 py-3">Total</th>
                        <th class="text-right px-5 py-3">Taxable</th>
                        <th class="text-right px-5 py-3">CGST</th>
                        <th class="text-right px-5 py-3">SGST</th>
                        <th class="text-right px-5 py-3">IGST</th>
                        <th class="text-left px-5 py-3">Place</th>
                        <th class="text-left px-5 py-3">Type</th>
                        <th class="text-left px-5 py-3">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-frost-1">
                    <tr v-for="o in orders" :key="o.order_number" class="hover:bg-brand-600/5 transition cursor-pointer"
                        @click="router.visit(route('tenant.orders.show', { tenant: slug, order: o.id }))">
                        <td class="px-5 py-3 font-mono font-semibold text-brand-300">{{ o.order_number }}</td>
                        <td class="px-5 py-3 text-white">{{ o.customer_name || '—' }}</td>
                        <td class="px-5 py-3 text-right font-mono text-white">{{ fmt(o.total_amount) }}</td>
                        <td class="px-5 py-3 text-right font-mono text-ink-2">{{ fmt(o.taxable_amount) }}</td>
                        <td class="px-5 py-3 text-right font-mono text-emerald">{{ fmt(o.cgst_amount) }}</td>
                        <td class="px-5 py-3 text-right font-mono text-emerald">{{ fmt(o.sgst_amount) }}</td>
                        <td class="px-5 py-3 text-right font-mono text-brand-300">{{ fmt(o.igst_amount) }}</td>
                        <td class="px-5 py-3 text-ink-2 text-[11px]">{{ o.place_of_supply || '—' }}</td>
                        <td class="px-5 py-3">
                            <span v-if="o.is_intra_state !== null" class="pill" :class="o.is_intra_state ? 'pill-good' : 'pill-info'">
                                {{ o.is_intra_state ? 'Intra' : 'Inter' }}
                            </span>
                            <span v-else class="text-ink-3">—</span>
                        </td>
                        <td class="px-5 py-3 text-ink-3 text-[11px]">{{ o.placed_at?.substring(0, 10) }}</td>
                    </tr>
                    <tr v-if="!orders.length">
                        <td colspan="10" class="px-5 py-8 text-center text-ink-3 text-[13px]">
                            No orders for selected period.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</TenantLayout>
</template>
