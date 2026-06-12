<script setup>
import { Head, router } from '@inertiajs/vue3';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    balances: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
    year: { type: Number, default: 2026 },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

function changeYear(dir) {
    router.get(route('tenant.hr.leaves.balances', { tenant: slug }), { year: props.year + dir }, { preserveState: true });
}

function getBalance(empId, typeId) {
    const empBalances = props.balances[empId] || [];
    return empBalances.find(b => b.leave_type_id === typeId);
}
</script>

<template>
<Head title="Leave Balances" />
<TenantLayout>
    <div class="max-w-full">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white">Leave Balances</h2>
                <p class="text-[12px] text-ink-3 mt-1">Employee-wise leave balance for {{ year }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="changeYear(-1)" class="btn btn-ghost btn-sm cursor-pointer">← {{ year - 1 }}</button>
                <span class="text-[14px] font-bold text-white">{{ year }}</span>
                <button @click="changeYear(1)" class="btn btn-ghost btn-sm cursor-pointer">{{ year + 1 }} →</button>
            </div>
        </div>

        <div class="card overflow-x-auto">
            <table class="w-full text-[12px]">
                <thead>
                    <tr class="border-b border-frost-1">
                        <th class="text-left px-4 py-3 text-ink-3 font-medium sticky left-0 bg-bg-2 min-w-[160px]">Employee</th>
                        <template v-for="t in types" :key="t.id">
                            <th class="text-center px-2 py-3 text-ink-3 font-medium" colspan="3">
                                <div>{{ t.code }}</div>
                                <div class="text-[9px] font-normal">{{ t.name }}</div>
                            </th>
                        </template>
                    </tr>
                    <tr class="border-b border-frost-1">
                        <th class="sticky left-0 bg-bg-2"></th>
                        <template v-for="t in types" :key="'h-'+t.id">
                            <th class="text-center px-2 py-1 text-[9px] text-ink-3 font-normal">Alloc</th>
                            <th class="text-center px-2 py-1 text-[9px] text-ink-3 font-normal">Used</th>
                            <th class="text-center px-2 py-1 text-[9px] text-ink-3 font-normal border-r border-frost-1">Bal</th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="emp in employees" :key="emp.id" class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition">
                        <td class="px-4 py-2.5 sticky left-0 bg-bg-2">
                            <div class="text-[12px] font-medium text-white">{{ emp.first_name }} {{ emp.last_name }}</div>
                            <div class="text-[9px] text-ink-3 font-mono">{{ emp.employee_id }}</div>
                        </td>
                        <template v-for="t in types" :key="'b-'+t.id">
                            <td class="text-center px-2 py-2.5 font-mono text-ink-3">{{ getBalance(emp.id, t.id)?.allocated ?? '—' }}</td>
                            <td class="text-center px-2 py-2.5 font-mono text-ink-2">{{ getBalance(emp.id, t.id)?.used ?? 0 }}</td>
                            <td class="text-center px-2 py-2.5 font-mono font-bold border-r border-frost-1"
                                :class="(getBalance(emp.id, t.id)?.allocated - getBalance(emp.id, t.id)?.used) <= 0 ? 'text-rose-400' : 'text-emerald-400'">
                                {{ getBalance(emp.id, t.id) ? (parseFloat(getBalance(emp.id, t.id).allocated) + parseFloat(getBalance(emp.id, t.id).carried_forward || 0) - parseFloat(getBalance(emp.id, t.id).used)).toFixed(1) : '—' }}
                            </td>
                        </template>
                    </tr>
                    <tr v-if="!employees.length">
                        <td :colspan="1 + types.length * 3" class="px-4 py-8 text-center text-[13px] text-ink-3">No active employees</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</TenantLayout>
</template>
