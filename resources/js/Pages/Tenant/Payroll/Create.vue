<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft, Play } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    month: { type: String, default: '' },
    employees: { type: Array, default: () => [] },
    workingDays: { type: Number, default: 26 },
    calculations: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const monthName = (m) => { const [y, mo] = m.split('-'); return new Date(y, mo - 1).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' }); };

const form = useForm({ month: props.month });

function process() {
    if (confirm(`Process payroll for ${monthName(props.month)}? This will generate payslips for ${props.calculations.length} employees.`)) {
        form.post(route('tenant.payroll.store', { tenant: slug }));
    }
}

const totalGross = props.calculations.reduce((s, c) => s + parseFloat(c.gross_salary || 0), 0);
const totalNet = props.calculations.reduce((s, c) => s + parseFloat(c.net_salary || 0), 0);
const totalDeductions = props.calculations.reduce((s, c) => s + parseFloat(c.total_deductions || 0), 0);
</script>

<template>
<Head title="Run Payroll" />
<TenantLayout>
    <div class="flex items-center gap-3 mb-5">
        <Link :href="route('tenant.payroll.index', { tenant: slug })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> Back</Link>
        <div>
            <h2 class="text-[20px] font-bold text-white">Run Payroll — {{ monthName(month) }}</h2>
            <p class="text-[12px] text-ink-3">{{ workingDays }} working days · {{ calculations.length }} employees</p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-5">
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Gross</div><div class="text-[18px] font-bold text-white font-mono">{{ fmt(totalGross) }}</div></div>
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Deductions</div><div class="text-[18px] font-bold text-rose font-mono">{{ fmt(totalDeductions) }}</div></div>
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Net Pay</div><div class="text-[18px] font-bold text-emerald font-mono">{{ fmt(totalNet) }}</div></div>
    </div>

    <div class="card overflow-hidden p-0 mb-5">
        <table class="w-full text-[12px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-4 py-2.5">Employee</th>
                    <th class="text-right px-4 py-2.5">Basic</th>
                    <th class="text-right px-4 py-2.5">HRA</th>
                    <th class="text-right px-4 py-2.5">OT Pay</th>
                    <th class="text-right px-4 py-2.5">Gross</th>
                    <th class="text-right px-4 py-2.5">PF</th>
                    <th class="text-right px-4 py-2.5">ESI</th>
                    <th class="text-right px-4 py-2.5">PT</th>
                    <th class="text-right px-4 py-2.5">Net</th>
                    <th class="text-center px-4 py-2.5">Days</th>
                    <th class="text-right px-4 py-2.5">OT Hrs</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="c in calculations" :key="c.employee_id" class="hover:bg-brand-600/5">
                    <td class="px-4 py-2.5">
                        <div class="font-medium text-white">{{ c.employee_name }}</div>
                        <div class="text-[10px] text-ink-3">{{ c.employee_code }} · {{ c.designation }}</div>
                    </td>
                    <td class="px-4 py-2.5 text-right font-mono text-ink-2">{{ fmt(c.basic_salary) }}</td>
                    <td class="px-4 py-2.5 text-right font-mono text-ink-2">{{ fmt(c.hra) }}</td>
                    <td class="px-4 py-2.5 text-right font-mono text-brand-300">{{ fmt(c.overtime_pay) }}</td>
                    <td class="px-4 py-2.5 text-right font-mono text-white">{{ fmt(c.gross_salary) }}</td>
                    <td class="px-4 py-2.5 text-right font-mono text-rose text-[11px]">{{ fmt(c.pf_employee) }}</td>
                    <td class="px-4 py-2.5 text-right font-mono text-rose text-[11px]">{{ fmt(c.esi_employee) }}</td>
                    <td class="px-4 py-2.5 text-right font-mono text-rose text-[11px]">{{ fmt(c.professional_tax) }}</td>
                    <td class="px-4 py-2.5 text-right font-mono text-emerald font-semibold">{{ fmt(c.net_salary) }}</td>
                    <td class="px-4 py-2.5 text-center text-ink-3">{{ c.days_present }}/{{ c.working_days }}</td>
                    <td class="px-4 py-2.5 text-right font-mono text-ink-3">{{ c.total_overtime_hours || 0 }}h</td>
                </tr>
            </tbody>
        </table>
    </div>

    <button class="btn btn-primary w-full py-3 text-[14px]" @click="process" :disabled="form.processing || !calculations.length">
        <Play :size="16" /> {{ form.processing ? 'Processing…' : 'Process Payroll' }}
    </button>
</TenantLayout>
</template>
