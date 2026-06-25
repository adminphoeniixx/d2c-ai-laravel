<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Search, UserPlus, Users, UserCheck, Clock, FileSignature } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    employees: { type: Object, default: () => ({ data: [] }) },
    departments: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ total: 0, active: 0, on_notice: 0 }) },
    filters: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const q = ref(props.filters.q || '');
const statusFilter = ref(props.filters.status || '');

function search() {
    router.get(route('tenant.hr.employees', { tenant: slug }), { q: q.value, status: statusFilter.value }, { preserveState: true });
}

function filterStatus(s) {
    statusFilter.value = s;
    router.get(route('tenant.hr.employees', { tenant: slug }), { q: q.value, status: s }, { preserveState: true });
}

const statusMap = {
    active: { class: 'pill-good', label: 'Active' },
    on_notice: { class: 'pill-info', label: 'On Notice' },
    terminated: { class: 'pill-bad', label: 'Terminated' },
    resigned: { class: 'pill-bad', label: 'Resigned' },
};

const typeMap = {
    full_time: 'Full Time', part_time: 'Part Time', contract: 'Contract', intern: 'Intern',
};
</script>

<template>
<Head title="Employees" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Employees</h2>
            <p class="text-[12px] text-ink-3 mt-1">Manage your team</p>
        </div>
        <div class="flex items-center gap-2">
            <Link :href="route('tenant.hr.templates', { tenant: slug })" class="btn btn-ghost flex items-center gap-1.5">
                <FileSignature :size="14" /> Letter Templates
            </Link>
            <Link :href="route('tenant.hr.employees.create', { tenant: slug })" class="btn btn-primary">
                <UserPlus :size="14" /> Add Employee
            </Link>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <KpiCard label="Total Employees" :value="totals.total" format="number" />
        <KpiCard label="Active" :value="totals.active" format="number" />
        <KpiCard label="On Notice" :value="totals.on_notice" format="number" />
    </div>

    <!-- Search + filters -->
    <div class="card mb-5">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
                <input v-model="q" @keyup.enter="search" class="heyd2c-input pl-9 w-full" placeholder="Search by name, ID, email, designation..." />
            </div>
            <div class="flex gap-1.5">
                <button v-for="s in ['', 'active', 'on_notice', 'terminated', 'resigned']" :key="s"
                    class="px-3 py-1.5 text-[11px] font-mono rounded-full transition cursor-pointer"
                    :class="statusFilter === s ? 'bg-brand-600/20 text-brand-300 border border-brand-600/40' : 'bg-bg-3 text-ink-3 border border-frost-1 hover:border-frost-3'"
                    @click="filterStatus(s)">
                    {{ s || 'all' }}
                </button>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">Employee</th>
                    <th class="text-left px-5 py-3">ID</th>
                    <th class="text-left px-5 py-3">Designation</th>
                    <th class="text-left px-5 py-3">Department</th>
                    <th class="text-left px-5 py-3">Type</th>
                    <th class="text-left px-5 py-3">Status</th>
                    <th class="text-left px-5 py-3">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="emp in employees.data" :key="emp.id"
                    class="hover:bg-brand-600/5 transition cursor-pointer"
                    @click="router.visit(route('tenant.hr.employees.show', { tenant: slug, id: emp.id }))">
                    <td class="px-5 py-3">
                        <div class="font-medium text-white">{{ emp.first_name }} {{ emp.last_name }}</div>
                        <div v-if="emp.email" class="text-[11px] text-ink-3">{{ emp.email }}</div>
                    </td>
                    <td class="px-5 py-3 font-mono text-brand-300">{{ emp.employee_id }}</td>
                    <td class="px-5 py-3 text-ink-2">{{ emp.designation || '—' }}</td>
                    <td class="px-5 py-3 text-ink-2">{{ emp.department || '—' }}</td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ typeMap[emp.employment_type] || emp.employment_type }}</td>
                    <td class="px-5 py-3">
                        <span class="pill" :class="statusMap[emp.status]?.class || 'pill-info'">{{ statusMap[emp.status]?.label || emp.status }}</span>
                    </td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ emp.date_of_joining?.substring(0, 10) || '—' }}</td>
                </tr>
                <tr v-if="!employees.data?.length">
                    <td colspan="7" class="px-5 py-8 text-center text-ink-3">No employees yet. Click "Add Employee" to get started.</td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
