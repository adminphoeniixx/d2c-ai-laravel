<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, DollarSign, Calendar } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    runs: { type: Object, default: () => ({ data: [] }) },
    nextMonth: { type: String, default: '' },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const statusMap = { draft: 'pill-info', processed: 'pill-good', paid: 'pill-good' };
const monthName = (m) => { const [y, mo] = m.split('-'); return new Date(y, mo - 1).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' }); };
</script>

<template>
<Head title="Payroll" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Payroll</h2>
            <p class="text-[12px] text-ink-3 mt-1">Monthly salary processing with PF, ESI, PT deductions</p>
        </div>
        <Link :href="route('tenant.payroll.create', { tenant: slug })" class="btn btn-primary"><Plus :size="14" /> Run Payroll</Link>
    </div>

    <div class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">Month</th>
                    <th class="text-left px-5 py-3">Employees</th>
                    <th class="text-right px-5 py-3">Gross</th>
                    <th class="text-right px-5 py-3">Deductions</th>
                    <th class="text-right px-5 py-3">Net Pay</th>
                    <th class="text-left px-5 py-3">Status</th>
                    <th class="text-left px-5 py-3">Processed</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="run in runs.data" :key="run.id" class="hover:bg-brand-600/5 transition cursor-pointer"
                    @click="router.visit(route('tenant.payroll.show', { tenant: slug, id: run.id }))">
                    <td class="px-5 py-3 font-medium text-white">{{ monthName(run.month) }}</td>
                    <td class="px-5 py-3 text-ink-2">{{ run.payslips_count || run.employee_count }}</td>
                    <td class="px-5 py-3 text-right font-mono text-ink">{{ fmt(run.total_gross) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-rose">{{ fmt(run.total_deductions) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-emerald font-semibold">{{ fmt(run.total_net) }}</td>
                    <td class="px-5 py-3"><span class="pill" :class="statusMap[run.status]">{{ run.status }}</span></td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ run.processed_at?.substring(0, 10) || '—' }}</td>
                </tr>
                <tr v-if="!runs.data?.length">
                    <td colspan="7" class="px-5 py-8 text-center text-ink-3">No payroll runs yet. Click "Run Payroll" to process your first month.</td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
