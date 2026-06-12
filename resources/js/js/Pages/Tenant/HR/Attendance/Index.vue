<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Calendar, ChevronLeft, ChevronRight, Plus, Clock, AlertTriangle, Users } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    records: { type: [Array, Object], default: () => [] },
    byEmployee: { type: Object, default: () => ({}) },
    employees: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    holidays: { type: Array, default: () => [] },
    schedules: { type: Array, default: () => [] },
    daysInMonth: { type: Number, default: 30 },
    settings: { type: Object, default: () => ({}) },
    month: { type: String, default: '' },
    filters: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const selectedEmployee = ref(props.filters.employee_id || '');
const showAddModal = ref(false);
const addForm = ref({ employee_id: '', date: '', check_in: '', check_out: '', status: 'present', notes: '' });

const currentMonth = computed(() => {
    const [y, m] = props.month.split('-');
    return new Date(y, m - 1).toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
});

function changeMonth(dir) {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m - 1 + dir);
    const newMonth = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    router.get(route('tenant.hr.attendance', { tenant: slug }), { month: newMonth, employee_id: selectedEmployee.value || undefined }, { preserveState: true });
}

function filterByEmployee() {
    router.get(route('tenant.hr.attendance', { tenant: slug }), { month: props.month, employee_id: selectedEmployee.value || undefined }, { preserveState: true });
}

const statusColors = {
    present: 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30',
    absent: 'bg-rose-500/20 text-rose-400 border-rose-500/30',
    half_day: 'bg-amber-500/20 text-amber-400 border-amber-500/30',
    leave: 'bg-blue-500/20 text-blue-400 border-blue-500/30',
    holiday: 'bg-purple-500/20 text-purple-400 border-purple-500/30',
};
const statusLabels = { present: 'P', absent: 'A', half_day: 'HD', leave: 'L', holiday: 'H' };

const holidayMap = computed(() => {
    const m = {};
    (props.holidays || []).forEach(h => { m[h.date?.split('T')[0]] = h; });
    return m;
});

const nonWorkingDays = computed(() => {
    const s = new Set();
    (props.schedules || []).forEach(sc => { if (!sc.is_working_day) s.add(sc.day_of_week); });
    return s;
});

function getAttendanceForDay(empId, day) {
    const dateStr = props.month + '-' + String(day).padStart(2, '0');
    const empRecords = props.byEmployee[empId] || [];
    return empRecords.find(r => r.date?.split('T')[0] === dateStr);
}

function getDayType(day) {
    const dateStr = props.month + '-' + String(day).padStart(2, '0');
    if (holidayMap.value[dateStr]) return 'holiday';
    const d = new Date(dateStr);
    if (nonWorkingDays.value.has(d.getDay())) return 'off';
    return 'working';
}

function getSummaryFor(empId) {
    return props.summary[empId] || {};
}

function submitAdd() {
    router.post(route('tenant.hr.attendance.store', { tenant: slug }), addForm.value, {
        preserveState: true,
        onSuccess: () => { showAddModal.value = false; addForm.value = { employee_id: '', date: '', check_in: '', check_out: '', status: 'present', notes: '' }; },
    });
}

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
</script>

<template>
<Head title="Attendance" />
<TenantLayout>
    <div class="max-w-full">
        <!-- Header -->
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div>
                <h2 class="text-[20px] font-bold text-white">Attendance</h2>
                <p class="text-[12px] text-ink-3 mt-1">Monthly attendance overview</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="showAddModal = true" class="btn btn-primary btn-sm flex items-center gap-1.5 cursor-pointer">
                    <Plus :size="14" /> Mark Attendance
                </button>
            </div>
        </div>

        <!-- Controls -->
        <div class="flex items-center gap-3 mb-5 flex-wrap">
            <div class="flex items-center gap-1">
                <button @click="changeMonth(-1)" class="p-1.5 rounded-lg bg-bg-3 border border-frost-1 text-ink-3 hover:text-white cursor-pointer"><ChevronLeft :size="16" /></button>
                <span class="text-[14px] font-semibold text-white px-3 min-w-[160px] text-center">{{ currentMonth }}</span>
                <button @click="changeMonth(1)" class="p-1.5 rounded-lg bg-bg-3 border border-frost-1 text-ink-3 hover:text-white cursor-pointer"><ChevronRight :size="16" /></button>
            </div>
            <select v-model="selectedEmployee" @change="filterByEmployee" class="heyd2c-input w-48">
                <option value="">All Employees</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.first_name }} {{ e.last_name }}</option>
            </select>
            <div class="flex gap-2 ml-auto">
                <div v-for="(color, st) in statusColors" :key="st" class="flex items-center gap-1">
                    <span class="w-3 h-3 rounded-sm border" :class="color"></span>
                    <span class="text-[10px] text-ink-3">{{ st.replace('_', ' ') }}</span>
                </div>
            </div>
        </div>

        <!-- Calendar Grid -->
        <div class="card overflow-x-auto">
            <table class="w-full text-[11px]">
                <thead>
                    <tr>
                        <th class="text-left px-2 py-2 text-ink-3 font-medium sticky left-0 bg-bg-2 min-w-[140px] border-b border-frost-1">Employee</th>
                        <th v-for="d in daysInMonth" :key="d"
                            class="text-center px-0.5 py-2 font-medium border-b border-frost-1 min-w-[28px]"
                            :class="getDayType(d) === 'off' ? 'text-ink-3/50 bg-bg-3/30' : getDayType(d) === 'holiday' ? 'text-purple-400 bg-purple-500/5' : 'text-ink-3'">
                            {{ d }}
                        </th>
                        <th class="text-center px-2 py-2 text-ink-3 font-medium border-b border-frost-1 min-w-[30px]">P</th>
                        <th class="text-center px-2 py-2 text-ink-3 font-medium border-b border-frost-1 min-w-[30px]">A</th>
                        <th class="text-center px-2 py-2 text-ink-3 font-medium border-b border-frost-1 min-w-[30px]">L</th>
                        <th class="text-center px-2 py-2 text-ink-3 font-medium border-b border-frost-1 min-w-[30px]">Late</th>
                        <th class="text-center px-2 py-2 text-ink-3 font-medium border-b border-frost-1 min-w-[40px]">OT</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="emp in employees" :key="emp.id" class="hover:bg-brand-600/5 transition">
                        <td class="px-2 py-1.5 sticky left-0 bg-bg-2 border-b border-frost-1">
                            <div class="text-[12px] font-medium text-white truncate">{{ emp.first_name }} {{ emp.last_name }}</div>
                            <div class="text-[9px] text-ink-3 font-mono">{{ emp.employee_id }}</div>
                        </td>
                        <td v-for="d in daysInMonth" :key="d" class="text-center px-0.5 py-1.5 border-b border-frost-1"
                            :class="getDayType(d) === 'off' ? 'bg-bg-3/30' : ''">
                            <template v-if="getAttendanceForDay(emp.id, d)">
                                <span class="inline-flex w-5 h-5 items-center justify-center rounded text-[9px] font-bold border"
                                    :class="statusColors[getAttendanceForDay(emp.id, d).status] || 'text-ink-3'"
                                    :title="getAttendanceForDay(emp.id, d).check_in ? getAttendanceForDay(emp.id, d).check_in.substring(0,5) + (getAttendanceForDay(emp.id, d).check_out ? ' → ' + getAttendanceForDay(emp.id, d).check_out.substring(0,5) : '') : getAttendanceForDay(emp.id, d).status">
                                    {{ statusLabels[getAttendanceForDay(emp.id, d).status] || '?' }}
                                </span>
                            </template>
                            <template v-else-if="getDayType(d) === 'off'">
                                <span class="text-[9px] text-ink-3/30">—</span>
                            </template>
                            <template v-else-if="getDayType(d) === 'holiday'">
                                <span class="inline-flex w-5 h-5 items-center justify-center rounded text-[9px] font-bold border bg-purple-500/20 text-purple-400 border-purple-500/30">H</span>
                            </template>
                        </td>
                        <td class="text-center px-2 py-1.5 border-b border-frost-1 font-mono text-emerald-400 font-bold">{{ getSummaryFor(emp.id).present || 0 }}</td>
                        <td class="text-center px-2 py-1.5 border-b border-frost-1 font-mono text-rose-400 font-bold">{{ getSummaryFor(emp.id).absent || 0 }}</td>
                        <td class="text-center px-2 py-1.5 border-b border-frost-1 font-mono text-blue-400">{{ getSummaryFor(emp.id).leaves || 0 }}</td>
                        <td class="text-center px-2 py-1.5 border-b border-frost-1 font-mono text-amber-400">{{ getSummaryFor(emp.id).late_count || 0 }}</td>
                        <td class="text-center px-2 py-1.5 border-b border-frost-1 font-mono text-ink-2">{{ parseFloat(getSummaryFor(emp.id).total_overtime_hours || 0).toFixed(1) }}h</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="!employees.length" class="py-12 text-center text-[13px] text-ink-3">No active employees found.</div>
        </div>

        <!-- Add Attendance Modal -->
        <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showAddModal = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-4">Mark Attendance</h3>
                <form @submit.prevent="submitAdd" class="space-y-3">
                    <div>
                        <label class="heyd2c-label">Employee</label>
                        <select v-model="addForm.employee_id" class="heyd2c-input" required>
                            <option value="">Select…</option>
                            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.first_name }} {{ e.last_name }} ({{ e.employee_id }})</option>
                        </select>
                    </div>
                    <div>
                        <label class="heyd2c-label">Date</label>
                        <input v-model="addForm.date" type="date" class="heyd2c-input" required />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="heyd2c-label">Check In</label>
                            <input v-model="addForm.check_in" type="time" class="heyd2c-input" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Check Out</label>
                            <input v-model="addForm.check_out" type="time" class="heyd2c-input" />
                        </div>
                    </div>
                    <div>
                        <label class="heyd2c-label">Status</label>
                        <select v-model="addForm.status" class="heyd2c-input">
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="half_day">Half Day</option>
                            <option value="leave">Leave</option>
                            <option value="holiday">Holiday</option>
                        </select>
                    </div>
                    <div>
                        <label class="heyd2c-label">Notes</label>
                        <input v-model="addForm.notes" class="heyd2c-input" placeholder="Optional" />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1">Save</button>
                        <button type="button" class="btn btn-ghost flex-1" @click="showAddModal = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
