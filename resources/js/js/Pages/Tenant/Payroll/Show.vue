<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, Download, Wallet } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    run: { type: Object, default: () => ({}) },
    payslips: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

function markPaid() {
    if (confirm('Mark this payroll run as paid?')) {
        router.post(route('tenant.payroll.paid', { tenant: slug, id: props.run.id }));
    }
}
</script>

<template>
<Head :title="`Payroll ${run.month}`" />
<TenantLayout>
    <div class="max-w-4xl">
        <div class="flex items-center gap-3 mb-5">
            <button @click="router.visit(route('tenant.payroll.index', { tenant: slug }))" class="text-ink-3 hover:text-white cursor-pointer"><ArrowLeft :size="18" /></button>
            <div class="flex-1">
                <h2 class="text-[20px] font-bold text-white">Payroll — {{ run.month }}</h2>
                <div class="flex items-center gap-3 mt-0.5">
                    <span class="text-[11px] px-2 py-0.5 rounded-full font-bold"
                        :class="run.status === 'paid' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'">
                        {{ run.status }}
                    </span>
                    <span class="text-[11px] text-ink-3">{{ run.employee_count }} employees</span>
                </div>
            </div>
            <button v-if="run.status !== 'paid'" @click="markPaid" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer">
                <CheckCircle :size="14" /> Mark Paid
            </button>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase">Total Gross</div>
                <div class="text-[18px] font-bold text-emerald-400 mt-1">{{ fmt(run.total_gross) }}</div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase">Total Deductions</div>
                <div class="text-[18px] font-bold text-rose mt-1">{{ fmt(run.total_deductions) }}</div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase">Net Payable</div>
                <div class="text-[18px] font-bold text-brand-300 mt-1">{{ fmt(run.total_net) }}</div>
            </div>
        </div>

        <!-- Payslip Detail -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[11px]">
                    <thead>
                        <tr class="border-b border-frost-1 text-ink-3 uppercase tracking-wider">
                            <th class="text-left px-3 py-2.5">Employee</th>
                            <th class="text-right px-2">Present</th>
                            <th class="text-right px-2">Absent</th>
                            <th class="text-right px-2">Late</th>
                            <th class="text-right px-2">Half Days</th>
                            <th class="text-right px-2">Gross</th>
                            <th class="text-right px-2">PF</th>
                            <th class="text-right px-2">ESI</th>
                            <th class="text-right px-2">PT</th>
                            <th class="text-right px-2">Late Ded.</th>
                            <th class="text-right px-2">Absent Ded.</th>
                            <th class="text-right px-2">LWP Ded.</th>
                            <th class="text-right px-2">OT Pay</th>
                            <th class="text-right px-2 font-bold">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in payslips" :key="p.id" class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5">
                            <td class="px-3 py-2">
                                <div class="text-[12px] font-medium text-white">{{ p.employee?.first_name }} {{ p.employee?.last_name }}</div>
                                <div class="text-[10px] text-ink-3">{{ p.employee?.employee_id }} · {{ p.employee?.designation }}</div>
                            </td>
                            <td class="text-right px-2 text-emerald-400">{{ p.days_present }}</td>
                            <td class="text-right px-2" :class="p.days_absent > 0 ? 'text-rose' : 'text-ink-3'">{{ p.days_absent }}</td>
                            <td class="text-right px-2" :class="p.late_count > 0 ? 'text-amber-400' : 'text-ink-3'">{{ p.late_count }}</td>
                            <td class="text-right px-2 text-ink-3">{{ p.half_days }}</td>
                            <td class="text-right px-2 text-ink-2">{{ fmt(p.gross_salary) }}</td>
                            <td class="text-right px-2 text-ink-3">{{ fmt(p.pf_employee) }}</td>
                            <td class="text-right px-2 text-ink-3">{{ fmt(p.esi_employee) }}</td>
                            <td class="text-right px-2 text-ink-3">{{ fmt(p.professional_tax) }}</td>
                            <td class="text-right px-2" :class="parseFloat(p.late_deductions) > 0 ? 'text-rose' : 'text-ink-3'">{{ fmt(p.late_deductions) }}</td>
                            <td class="text-right px-2" :class="parseFloat(p.absent_deductions) > 0 ? 'text-rose' : 'text-ink-3'">{{ fmt(p.absent_deductions) }}</td>
                            <td class="text-right px-2" :class="parseFloat(p.lwp_deductions) > 0 ? 'text-rose' : 'text-ink-3'">{{ fmt(p.lwp_deductions) }}</td>
                            <td class="text-right px-2 text-emerald-400">{{ fmt(p.overtime_pay) }}</td>
                            <td class="text-right px-2 font-bold text-white">{{ fmt(p.net_salary) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
