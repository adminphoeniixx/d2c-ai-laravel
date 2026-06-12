<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ChevronLeft, ChevronRight, AlertTriangle, Clock } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    report: { type: Array, default: () => [] },
    lateDetails: { type: Array, default: () => [] },
    settings: { type: Object, default: () => ({}) },
    month: { type: String, default: '' },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));

const currentMonth = (() => {
    const [y, m] = props.month.split('-');
    return new Date(y, m - 1).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
})();

function changeMonth(dir) {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m - 1 + dir);
    const newMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    router.get(route('tenant.hr.attendance.late-report', { tenant: slug }), { month: newMonth }, { preserveState: true });
}

const expandedEmployee = ref(null);
</script>

<template>
<Head title="Late & Penalty Report" />
<TenantLayout>
    <div class="max-w-3xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white">Late & Penalty Report</h2>
                <p class="text-[12px] text-ink-3 mt-1">Monthly lateness summary with penalties</p>
            </div>
            <div class="flex items-center gap-1">
                <button @click="changeMonth(-1)" class="p-1.5 rounded-lg bg-bg-3 border border-frost-1 text-ink-3 hover:text-white cursor-pointer"><ChevronLeft :size="16" /></button>
                <span class="text-[14px] font-semibold text-white px-3">{{ currentMonth }}</span>
                <button @click="changeMonth(1)" class="p-1.5 rounded-lg bg-bg-3 border border-frost-1 text-ink-3 hover:text-white cursor-pointer"><ChevronRight :size="16" /></button>
            </div>
        </div>

        <!-- Policy reminder -->
        <div v-if="settings" class="card mb-5 !py-3">
            <div class="flex items-center gap-4 text-[11px] text-ink-3 flex-wrap">
                <span>Late after <strong class="text-ink-2">{{ settings.late_threshold_minutes }}min</strong></span>
                <span>Half-day after <strong class="text-ink-2">{{ settings.half_day_threshold_minutes }}min</strong></span>
                <span>Grace: <strong class="text-ink-2">{{ settings.late_grace_count }}/month</strong></span>
                <span>Penalty: <strong class="text-ink-2">{{ settings.late_penalty_type }}</strong></span>
            </div>
        </div>

        <!-- Summary Table -->
        <div class="card overflow-hidden">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="border-b border-frost-1">
                        <th class="text-left px-4 py-3 text-ink-3 font-medium">Employee</th>
                        <th class="text-center px-3 py-3 text-ink-3 font-medium">Late Count</th>
                        <th class="text-center px-3 py-3 text-ink-3 font-medium">Total Late</th>
                        <th class="text-center px-3 py-3 text-ink-3 font-medium">Avg Late</th>
                        <th class="text-right px-4 py-3 text-ink-3 font-medium">Penalty</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in report" :key="r.employee_id"
                        class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition cursor-pointer"
                        @click="expandedEmployee = expandedEmployee === r.employee_id ? null : r.employee_id">
                        <td class="px-4 py-3">
                            <div class="text-[13px] font-medium text-white">{{ r.employee?.first_name }} {{ r.employee?.last_name }}</div>
                            <div class="text-[10px] text-ink-3 font-mono">{{ r.employee?.employee_id }}</div>
                        </td>
                        <td class="text-center px-3 py-3">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold"
                                :class="r.late_count > (settings?.late_grace_count || 3) ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400'">
                                <AlertTriangle :size="10" /> {{ r.late_count }}
                            </span>
                        </td>
                        <td class="text-center px-3 py-3 font-mono text-ink-2">{{ r.total_late_minutes }}min</td>
                        <td class="text-center px-3 py-3 font-mono text-ink-2">{{ Math.round(r.avg_late_minutes || 0) }}min</td>
                        <td class="text-right px-4 py-3 font-mono font-bold" :class="parseFloat(r.total_penalty) > 0 ? 'text-rose-400' : 'text-ink-3'">
                            {{ fmt(r.total_penalty) }}
                        </td>
                    </tr>
                    <tr v-if="!report.length">
                        <td colspan="5" class="px-4 py-8 text-center text-[13px] text-ink-3">No late entries this month</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Expanded Details -->
        <div v-if="expandedEmployee" class="card mt-4">
            <h3 class="text-[14px] font-bold text-white mb-3">Late Details</h3>
            <div class="space-y-1">
                <div v-for="d in lateDetails.filter(x => x.employee_id === expandedEmployee)" :key="d.id"
                    class="flex items-center justify-between px-3 py-2 rounded-lg bg-bg-3 border border-frost-1 text-[12px]">
                    <span class="text-ink-2 font-mono">{{ new Date(d.date).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) }}</span>
                    <span class="text-ink-2">In: <strong class="text-white">{{ d.check_in?.substring(0, 5) || '—' }}</strong></span>
                    <span class="text-amber-400 font-mono">+{{ d.late_minutes }}min</span>
                    <span class="text-rose-400 font-mono">{{ fmt(d.penalty_amount) }}</span>
                </div>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
