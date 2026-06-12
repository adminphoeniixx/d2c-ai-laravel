<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit, ToggleLeft, ToggleRight } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({ types: { type: Array, default: () => [] } });
const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '', code: '', is_paid: true, annual_quota: 12,
    carry_forward: false, max_carry_forward_days: 0, max_consecutive_days: 3,
    requires_approval: true, is_active: true, description: '',
});

function openAdd() {
    form.reset(); form.is_paid = true; form.annual_quota = 12; form.requires_approval = true; form.is_active = true;
    editingId.value = null; showForm.value = true;
}
function openEdit(t) {
    Object.keys(form.data()).forEach(k => { if (k in t) form[k] = t[k]; });
    editingId.value = t.id; showForm.value = true;
}
function submit() {
    if (editingId.value) {
        form.put(route('tenant.hr.leaves.types.update', { tenant: slug, leaveType: editingId.value }), { onSuccess: () => showForm.value = false });
    } else {
        form.post(route('tenant.hr.leaves.types.store', { tenant: slug }), { onSuccess: () => { showForm.value = false; form.reset(); } });
    }
}
</script>

<template>
<Head title="Leave Types" />
<TenantLayout>
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white">Leave Types</h2>
                <p class="text-[12px] text-ink-3 mt-1">Configure leave policies and quotas</p>
            </div>
            <button @click="openAdd" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer"><Plus :size="14" /> Add Type</button>
        </div>

        <div class="card overflow-hidden">
            <div v-for="t in types" :key="t.id"
                class="px-4 py-3 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center flex-shrink-0 text-[14px] font-bold"
                        :class="t.is_active ? 'bg-brand-600/15 text-brand-300' : 'bg-bg-3 text-ink-3'">
                        {{ t.code }}
                    </div>
                    <div>
                        <div class="text-[13px] font-medium" :class="t.is_active ? 'text-white' : 'text-ink-3'">{{ t.name }}</div>
                        <div class="text-[11px] text-ink-3 flex items-center gap-2">
                            <span>{{ t.annual_quota }} days/year</span>
                            <span :class="t.is_paid ? 'text-emerald-400' : 'text-rose-400'">{{ t.is_paid ? 'Paid' : 'Unpaid' }}</span>
                            <span v-if="t.carry_forward" class="text-blue-400">Carry forward (max {{ t.max_carry_forward_days }})</span>
                            <span v-if="t.requires_approval" class="text-amber-400">Needs approval</span>
                        </div>
                    </div>
                </div>
                <button @click="openEdit(t)" class="text-ink-3 hover:text-brand-300 cursor-pointer"><Edit :size="14" /></button>
            </div>
            <div v-if="!types.length" class="px-4 py-8 text-center text-[13px] text-ink-3">No leave types configured</div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showForm = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-lg mx-4 p-5 max-h-[90vh] overflow-y-auto">
                <h3 class="text-[16px] font-bold text-white mb-4">{{ editingId ? 'Edit' : 'Add' }} Leave Type</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="heyd2c-label">Name</label>
                            <input v-model="form.name" class="heyd2c-input" placeholder="Casual Leave" required />
                        </div>
                        <div>
                            <label class="heyd2c-label">Code</label>
                            <input v-model="form.code" class="heyd2c-input font-mono" placeholder="CL" maxlength="10" :disabled="!!editingId" required />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="heyd2c-label">Annual Quota (days)</label>
                            <input v-model="form.annual_quota" type="number" min="0" class="heyd2c-input" />
                            <p class="mt-1 text-[10px] text-ink-3">0 = unlimited (like LWP)</p>
                        </div>
                        <div>
                            <label class="heyd2c-label">Max Consecutive Days</label>
                            <input v-model="form.max_consecutive_days" type="number" min="1" class="heyd2c-input" />
                        </div>
                    </div>
                    <div class="space-y-2 rounded-[10px] bg-bg-3 border border-frost-1 p-3">
                        <label class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                            <input v-model="form.is_paid" type="checkbox" class="accent-brand-600" /> Paid Leave
                        </label>
                        <label class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                            <input v-model="form.requires_approval" type="checkbox" class="accent-brand-600" /> Requires Approval
                        </label>
                        <label class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                            <input v-model="form.carry_forward" type="checkbox" class="accent-brand-600" /> Allow Carry Forward
                        </label>
                        <div v-if="form.carry_forward" class="ml-6">
                            <label class="heyd2c-label">Max Carry Forward Days</label>
                            <input v-model="form.max_carry_forward_days" type="number" min="0" class="heyd2c-input w-32" />
                        </div>
                        <label v-if="editingId" class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                            <input v-model="form.is_active" type="checkbox" class="accent-brand-600" /> Active
                        </label>
                    </div>
                    <div>
                        <label class="heyd2c-label">Description</label>
                        <input v-model="form.description" class="heyd2c-input" placeholder="Optional notes about this leave type" />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1" :disabled="form.processing">{{ editingId ? 'Update' : 'Create' }}</button>
                        <button type="button" class="btn btn-ghost flex-1" @click="showForm = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
