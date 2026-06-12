<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ArrowLeft, Calculator, AlertTriangle } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    month: String,
    employees: { type: Array, default: () => [] },
    workingDays: { type: Number, default: 26 },
    calculations: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const form = useForm({ month: props.month });
const selectedMonth = ref(props.month);

function changeMonth() {
    router.get(route('tenant.payroll.create', { tenant: slug }), { month: selectedMonth.value }, { preserveState: true });
}
function processPayroll() {
    form.post(route('tenant.payroll.store', { tenant: slug }));
}

const totals = computed(() => {
    let gross = 0, deductions = 0, net = 0, late = 0, absent = 0, lwp = 0;
    props.calculations.forEach(c => {
        gross += parseFloat(c.gross_salary || 0);
        deductions += parseFloat(c.total_deductions || 0);
        net += parseFloat(c.net_salary || 0);
        late += parseFloat(c.late_deductions || 0);
        absent += parseFloat(c.absent_deductions || 0);
        lwp += parseFloat(c.lwp_deductions || 0);
    });
    return { gross, deductions, net, late, absent, lwp };
});

const fmt = (v) => '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
</script>

<template>
<Head title="Process Payroll" />
<TenantLayout>
    <div class="max-w-4xl">
        <div class="flex items-center gap-3 mb-5">
            <button @click="router.visit(route('tenant.payroll.index', { tenant: slug }))" class="text-ink-3 hover:text-white cursor-pointer"><ArrowLeft :size="18" /></button>
            <div class="flex-1">
                <h2 class="text-[20px] font-bold text-white">Process Payroll</h2>
                <p class="text-[12px] text-ink-3 mt-0.5">Auto-calculated from attendance, leaves, and penalties</p>
            </div>
            <input v-model="selectedMonth" type="month" class="heyd2c-input !py-1.5 text-[12px]" @change="changeMonth" />
        </div>

        <!-- Summary KPIs -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase tracking-wider">Employees</div>
                <div class="text-[20px] font-bold text-white mt-1">{{ calculations.length }}</div>
                <div class="text-[10px] text-ink-3">{{ workingDays }} working days</div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase tracking-wider">Total Gross</div>
                <div class="text-[20px] font-bold text-emerald-400 mt-1">{{ fmt(totals.gross) }}</div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase tracking-wider">Deductions</div>
                <div class="text-[20px] font-bold text-rose mt-1">{{ fmt(totals.deductions) }}</div>
                <div class="text-[9px] text-ink-3">Late: {{ fmt(totals.late) }} · Absent: {{ fmt(totals.absent) }}</div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase tracking-wider">Net Payable</div>
                <div class="text-[20px] font-bold text-brand-300 mt-1">{{ fmt(totals.net) }}</div>
            </div>
        </div>

        <!-- Employee Breakdown -->
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-[11px]">
                    <thead>
                        <tr class="border-b border-frost-1 text-ink-3 uppercase tracking-wider">
                            <th class="text-left px-3 py-2.5">Employee</th>
                            <th class="text-right px-2 py-2.5">Present</th>
                            <th class="text-right px-2 py-2.5">Absent</th>
                            <th class="text-right px-2 py-2.5">Late</th>
                            <th class="text-right px-2 py-2.5">Gross</th>
                            <th class="text-right px-2 py-2.5">PF</th>
                            <th class="text-right px-2 py-2.5">ESI</th>
                            <th class="text-right px-2 py-2.5">Late Ded.</th>
                            <th class="text-right px-2 py-2.5">Absent Ded.</th>
                            <th class="text-right px-2 py-2.5 font-bold">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in calculations" :key="c.employee_id"
                            class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition">
                            <td class="px-3 py-2">
                                <div class="text-[12px] font-medium text-white">{{ c.employee_name }}</div>
                                <div class="text-[10px] text-ink-3">{{ c.employee_code }} · {{ c.designation }}</div>
                            </td>
                            <td class="text-right px-2 py-2 text-emerald-400">{{ c.days_present || workingDays }}</td>
                            <td class="text-right px-2 py-2" :class="c.days_absent > 0 ? 'text-rose' : 'text-ink-3'">{{ c.days_absent || 0 }}</td>
                            <td class="text-right px-2 py-2" :class="c.late_count > 0 ? 'text-amber-400' : 'text-ink-3'">
                                {{ c.late_count || 0 }}
                                <AlertTriangle v-if="c.late_count > 3" :size="10" class="inline text-amber-400" />
                            </td>
                            <td class="text-right px-2 py-2 text-ink-2">{{ fmt(c.gross_salary) }}</td>
                            <td class="text-right px-2 py-2 text-ink-3">{{ fmt(c.pf_employee) }}</td>
                            <td class="text-right px-2 py-2 text-ink-3">{{ fmt(c.esi_employee) }}</td>
                            <td class="text-right px-2 py-2" :class="c.late_deductions > 0 ? 'text-rose' : 'text-ink-3'">{{ fmt(c.late_deductions) }}</td>
                            <td class="text-right px-2 py-2" :class="c.absent_deductions > 0 ? 'text-rose' : 'text-ink-3'">{{ fmt(c.absent_deductions) }}</td>
                            <td class="text-right px-2 py-2 font-bold text-white">{{ fmt(c.net_salary) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-5 flex justify-end">
            <button @click="processPayroll" :disabled="form.processing || !calculations.length"
                class="btn btn-primary px-8 flex items-center gap-2 cursor-pointer">
                <Calculator :size="16" />
                {{ form.processing ? 'Processing…' : `Process Payroll for ${calculations.length} Employees` }}
            </button>
        </div>
    </div>
</TenantLayout>
</template>
