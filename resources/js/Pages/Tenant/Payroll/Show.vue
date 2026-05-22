<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle, Download } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    run: { type: Object, default: () => ({}) },
    payslips: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const monthName = (m) => { const [y, mo] = m.split('-'); return new Date(y, mo - 1).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' }); };
const statusMap = { draft: 'pill-info', processed: 'pill-good', paid: 'pill-good' };

function markPaid() {
    if (confirm('Mark this payroll run as paid?')) {
        router.post(route('tenant.payroll.paid', { tenant: slug, id: props.run.id }));
    }
}
</script>

<template>
<Head :title="'Payroll — ' + monthName(run.month)" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <Link :href="route('tenant.payroll.index', { tenant: slug })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> Back</Link>
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-[20px] font-bold text-white">{{ monthName(run.month) }}</h2>
                    <span class="pill" :class="statusMap[run.status]">{{ run.status }}</span>
                </div>
                <p class="text-[12px] text-ink-3">{{ run.employee_count }} employees · Processed {{ run.processed_at?.substring(0, 10) || '—' }}</p>
            </div>
        </div>
        <button v-if="run.status === 'processed'" class="btn btn-primary" @click="markPaid">
            <CheckCircle :size="14" /> Mark as Paid
        </button>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-3 gap-4 mb-5">
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Gross</div><div class="text-[18px] font-bold text-white font-mono">{{ fmt(run.total_gross) }}</div></div>
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Deductions</div><div class="text-[18px] font-bold text-rose font-mono">{{ fmt(run.total_deductions) }}</div></div>
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Net Pay</div><div class="text-[18px] font-bold text-emerald font-mono">{{ fmt(run.total_net) }}</div></div>
    </div>

    <!-- Payslips table -->
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3 border-b border-frost-1">
            <h3 class="text-[14px] font-bold text-white">Payslips</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-[12px]">
                <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                    <tr>
                        <th class="text-left px-4 py-2.5 sticky left-0 bg-bg-3">Employee</th>
                        <th class="text-right px-4 py-2.5">Basic</th>
                        <th class="text-right px-4 py-2.5">HRA</th>
                        <th class="text-right px-4 py-2.5">Spl Allow</th>
                        <th class="text-right px-4 py-2.5">OT Pay</th>
                        <th class="text-right px-4 py-2.5">Gross</th>
                        <th class="text-right px-4 py-2.5">PF</th>
                        <th class="text-right px-4 py-2.5">ESI</th>
                        <th class="text-right px-4 py-2.5">PT</th>
                        <th class="text-right px-4 py-2.5">Deductions</th>
                        <th class="text-right px-4 py-2.5">Net Pay</th>
                        <th class="text-center px-4 py-2.5">Days</th>
                        <th class="text-right px-4 py-2.5">OT Hrs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-frost-1">
                    <tr v-for="p in payslips" :key="p.id" class="hover:bg-brand-600/5">
                        <td class="px-4 py-2.5 sticky left-0 bg-bg-2">
                            <div class="font-medium text-white">{{ p.employee?.first_name }} {{ p.employee?.last_name }}</div>
                            <div class="text-[10px] text-ink-3">{{ p.employee?.employee_id }} · {{ p.employee?.designation }}</div>
                        </td>
                        <td class="px-4 py-2.5 text-right font-mono text-ink-2">{{ fmt(p.basic_salary) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-ink-2">{{ fmt(p.hra) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-ink-2">{{ fmt(p.special_allowance) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-brand-300">{{ fmt(p.overtime_pay) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-white font-medium">{{ fmt(p.gross_salary) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-rose text-[11px]">{{ fmt(p.pf_employee) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-rose text-[11px]">{{ fmt(p.esi_employee) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-rose text-[11px]">{{ fmt(p.professional_tax) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-rose">{{ fmt(p.total_deductions) }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-emerald font-semibold">{{ fmt(p.net_salary) }}</td>
                        <td class="px-4 py-2.5 text-center text-ink-3">{{ p.days_present }}/{{ p.working_days }}</td>
                        <td class="px-4 py-2.5 text-right font-mono text-ink-3">{{ p.total_overtime_hours || 0 }}h</td>
                    </tr>
                </tbody>
                <tfoot class="bg-bg-3 border-t border-frost-2">
                    <tr class="text-[12px] font-semibold">
                        <td class="px-4 py-3 text-white sticky left-0 bg-bg-3">Total ({{ payslips.length }} employees)</td>
                        <td colspan="4"></td>
                        <td class="px-4 py-3 text-right font-mono text-white">{{ fmt(run.total_gross) }}</td>
                        <td colspan="3"></td>
                        <td class="px-4 py-3 text-right font-mono text-rose">{{ fmt(run.total_deductions) }}</td>
                        <td class="px-4 py-3 text-right font-mono text-emerald">{{ fmt(run.total_net) }}</td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</TenantLayout>
</template>
