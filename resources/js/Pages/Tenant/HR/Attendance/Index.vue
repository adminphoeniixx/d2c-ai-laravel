<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { Plus, X, Clock, Users, AlertCircle } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    records: { type: Object, default: () => ({ data: [] }) },
    employees: { type: Array, default: () => [] },
    summary: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ month: '' }) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showBulk = ref(false);
const showSingle = ref(false);
const activeTab = ref('records'); // records | summary

const singleForm = useForm({
    employee_id: '', date: new Date().toISOString().split('T')[0],
    check_in: '09:00', check_out: '18:00', status: 'present', notes: '',
});

const bulkDate = ref(new Date().toISOString().split('T')[0]);
const bulkEntries = ref([]);

function initBulk() {
    bulkEntries.value = props.employees.map(e => ({
        employee_id: e.id,
        name: e.first_name + ' ' + (e.last_name || ''),
        emp_id: e.employee_id,
        check_in: '09:00',
        check_out: '18:00',
        status: 'present',
    }));
    showBulk.value = true;
}

function submitSingle() {
    singleForm.post(route('tenant.hr.attendance.store', { tenant: slug }), {
        onSuccess: () => { showSingle.value = false; singleForm.reset(); },
    });
}

const bulkForm = useForm({ date: '', entries: [] });
function submitBulk() {
    bulkForm.date = bulkDate.value;
    bulkForm.entries = bulkEntries.value.map(e => ({
        employee_id: e.employee_id,
        check_in: e.status === 'present' ? e.check_in : null,
        check_out: e.status === 'present' ? e.check_out : null,
        status: e.status,
    }));
    bulkForm.post(route('tenant.hr.attendance.bulk', { tenant: slug }), {
        onSuccess: () => { showBulk.value = false; },
    });
}

function changeMonth(m) {
    router.get(route('tenant.hr.attendance', { tenant: slug }), { month: m }, { preserveState: true });
}

const fmtHrs = (h) => parseFloat(h || 0).toFixed(1) + 'h';
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) : '—';
const statusColors = { present: 'pill-good', absent: 'pill-bad', half_day: 'pill-info', leave: 'pill-info', holiday: 'pill-good' };
</script>

<template>
<Head title="Attendance" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Attendance & Working Hours</h2>
            <p class="text-[12px] text-ink-3 mt-1">Track daily attendance, working hours, and overtime</p>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-ghost" @click="showSingle = true"><Plus :size="14" /> Single Entry</button>
            <button class="btn btn-primary" @click="initBulk"><Users :size="14" /> Bulk Entry</button>
        </div>
    </div>

    <!-- Month filter -->
    <div class="flex items-center gap-3 mb-5">
        <label class="pulsara-label mb-0">Month</label>
        <input type="month" :value="filters.month" @change="changeMonth($event.target.value)" class="pulsara-input w-48" />
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4">
        <button class="px-4 py-2 text-[12px] font-medium rounded-lg transition cursor-pointer"
            :class="activeTab === 'records' ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'"
            @click="activeTab = 'records'">Daily Records</button>
        <button class="px-4 py-2 text-[12px] font-medium rounded-lg transition cursor-pointer"
            :class="activeTab === 'summary' ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'"
            @click="activeTab = 'summary'">Monthly Summary</button>
    </div>

    <!-- Daily Records -->
    <div v-if="activeTab === 'records'" class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">Employee</th>
                    <th class="text-left px-5 py-3">Date</th>
                    <th class="text-center px-5 py-3">Check In</th>
                    <th class="text-center px-5 py-3">Check Out</th>
                    <th class="text-right px-5 py-3">Worked</th>
                    <th class="text-right px-5 py-3">Overtime</th>
                    <th class="text-left px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="r in records.data" :key="r.id" class="hover:bg-brand-600/5">
                    <td class="px-5 py-3">
                        <div class="font-medium text-white">{{ r.employee?.first_name }} {{ r.employee?.last_name }}</div>
                        <div class="text-[10px] text-ink-3 font-mono">{{ r.employee?.employee_id }}</div>
                    </td>
                    <td class="px-5 py-3 text-ink-2 text-[12px]">{{ dateFmt(r.date) }}</td>
                    <td class="px-5 py-3 text-center font-mono text-ink-2">{{ r.check_in || '—' }}</td>
                    <td class="px-5 py-3 text-center font-mono text-ink-2">{{ r.check_out || '—' }}</td>
                    <td class="px-5 py-3 text-right font-mono text-white">{{ fmtHrs(r.worked_hours) }}</td>
                    <td class="px-5 py-3 text-right font-mono" :class="parseFloat(r.overtime_hours) > 0 ? 'text-brand-300 font-semibold' : 'text-ink-3'">{{ fmtHrs(r.overtime_hours) }}</td>
                    <td class="px-5 py-3"><span class="pill" :class="statusColors[r.status]">{{ r.status?.replace('_', ' ') }}</span></td>
                </tr>
                <tr v-if="!records.data?.length">
                    <td colspan="7" class="px-5 py-8 text-center text-ink-3">No attendance records for this month.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Monthly Summary -->
    <div v-if="activeTab === 'summary'" class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">Employee</th>
                    <th class="text-center px-5 py-3">Present</th>
                    <th class="text-center px-5 py-3">Absent</th>
                    <th class="text-center px-5 py-3">Half Day</th>
                    <th class="text-center px-5 py-3">Leave</th>
                    <th class="text-right px-5 py-3">Total Hours</th>
                    <th class="text-right px-5 py-3">Overtime</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="s in summary" :key="s.employee_id" class="hover:bg-brand-600/5">
                    <td class="px-5 py-3">
                        <div class="font-medium text-white">{{ s.employee?.first_name }} {{ s.employee?.last_name }}</div>
                        <div class="text-[10px] text-ink-3">{{ s.employee?.designation }}</div>
                    </td>
                    <td class="px-5 py-3 text-center text-emerald font-mono font-medium">{{ s.present }}</td>
                    <td class="px-5 py-3 text-center text-rose font-mono">{{ s.absent }}</td>
                    <td class="px-5 py-3 text-center text-ink-3 font-mono">{{ s.half_days }}</td>
                    <td class="px-5 py-3 text-center text-ink-3 font-mono">{{ s.leaves }}</td>
                    <td class="px-5 py-3 text-right font-mono text-white">{{ fmtHrs(s.total_worked_hours) }}</td>
                    <td class="px-5 py-3 text-right font-mono" :class="parseFloat(s.total_overtime_hours) > 0 ? 'text-brand-300 font-semibold' : 'text-ink-3'">{{ fmtHrs(s.total_overtime_hours) }}</td>
                </tr>
                <tr v-if="!summary.length">
                    <td colspan="7" class="px-5 py-8 text-center text-ink-3">No data for this month.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Single Entry Modal -->
    <div v-if="showSingle" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showSingle = false">
        <div class="card w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">Add Attendance</h3>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showSingle = false"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submitSingle" class="space-y-3">
                <div>
                    <label class="pulsara-label">Employee</label>
                    <select v-model="singleForm.employee_id" class="pulsara-input">
                        <option value="">Select…</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.first_name }} {{ e.last_name }} ({{ e.employee_id }})</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="pulsara-label">Date</label><input v-model="singleForm.date" type="date" class="pulsara-input" /></div>
                    <div><label class="pulsara-label">Status</label>
                        <select v-model="singleForm.status" class="pulsara-input">
                            <option v-for="s in ['present','absent','half_day','leave','holiday']" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
                        </select>
                    </div>
                </div>
                <div v-if="singleForm.status === 'present' || singleForm.status === 'half_day'" class="grid grid-cols-2 gap-3">
                    <div><label class="pulsara-label">Check In</label><input v-model="singleForm.check_in" type="time" class="pulsara-input font-mono" /></div>
                    <div><label class="pulsara-label">Check Out</label><input v-model="singleForm.check_out" type="time" class="pulsara-input font-mono" /></div>
                </div>
                <div><label class="pulsara-label">Notes</label><input v-model="singleForm.notes" class="pulsara-input" placeholder="Optional…" /></div>
                <button type="submit" class="btn btn-primary w-full py-2.5" :disabled="singleForm.processing">{{ singleForm.processing ? 'Saving…' : 'Save' }}</button>
            </form>
        </div>
    </div>

    <!-- Bulk Entry Modal -->
    <div v-if="showBulk" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showBulk = false">
        <div class="card w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">Bulk Attendance</h3>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showBulk = false"><X :size="18" /></button>
            </div>
            <div class="mb-4">
                <label class="pulsara-label">Date</label>
                <input v-model="bulkDate" type="date" class="pulsara-input w-48" />
            </div>
            <table class="w-full text-[12px] mb-4">
                <thead class="text-[10px] font-mono uppercase text-ink-3">
                    <tr>
                        <th class="text-left px-3 py-2">Employee</th>
                        <th class="text-center px-3 py-2 w-28">Status</th>
                        <th class="text-center px-3 py-2 w-24">Check In</th>
                        <th class="text-center px-3 py-2 w-24">Check Out</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-frost-1">
                    <tr v-for="e in bulkEntries" :key="e.employee_id">
                        <td class="px-3 py-2">
                            <div class="font-medium text-white">{{ e.name }}</div>
                            <div class="text-[10px] text-ink-3 font-mono">{{ e.emp_id }}</div>
                        </td>
                        <td class="px-3 py-2">
                            <select v-model="e.status" class="pulsara-input text-[11px] py-1">
                                <option v-for="s in ['present','absent','half_day','leave']" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
                            </select>
                        </td>
                        <td class="px-3 py-2"><input v-model="e.check_in" type="time" class="pulsara-input text-[11px] py-1 font-mono" :disabled="e.status === 'absent' || e.status === 'leave'" /></td>
                        <td class="px-3 py-2"><input v-model="e.check_out" type="time" class="pulsara-input text-[11px] py-1 font-mono" :disabled="e.status === 'absent' || e.status === 'leave'" /></td>
                    </tr>
                </tbody>
            </table>
            <button class="btn btn-primary w-full py-2.5" @click="submitBulk" :disabled="bulkForm.processing">{{ bulkForm.processing ? 'Saving…' : 'Save All' }}</button>
        </div>
    </div>
</TenantLayout>
</template>
