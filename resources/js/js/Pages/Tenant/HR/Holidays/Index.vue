<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit, Trash2, CalendarDays } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    holidays: { type: Array, default: () => [] },
    year: { type: Number, default: 2026 },
    totals: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showForm = ref(false);
const editingId = ref(null);

const form = useForm({ date: '', name: '', type: 'company', is_paid: true, notes: '' });

function openAdd() {
    form.reset(); editingId.value = null; showForm.value = true;
}
function openEdit(h) {
    form.date = h.date?.split('T')[0]; form.name = h.name; form.type = h.type; form.is_paid = h.is_paid; form.notes = h.notes || '';
    editingId.value = h.id; showForm.value = true;
}
function submit() {
    if (editingId.value) {
        form.put(route('tenant.hr.holidays.update', { tenant: slug, holiday: editingId.value }), { onSuccess: () => { showForm.value = false; } });
    } else {
        form.post(route('tenant.hr.holidays.store', { tenant: slug }), { onSuccess: () => { showForm.value = false; form.reset(); } });
    }
}
function remove(h) {
    if (confirm(`Delete holiday "${h.name}"?`)) {
        router.delete(route('tenant.hr.holidays.destroy', { tenant: slug, holiday: h.id }));
    }
}
function changeYear(dir) {
    router.get(route('tenant.hr.holidays', { tenant: slug }), { year: props.year + dir }, { preserveState: true });
}

const typeColors = {
    national: 'bg-orange-500/20 text-orange-400', company: 'bg-brand-600/20 text-brand-300',
    optional: 'bg-teal-500/20 text-teal-400', restricted: 'bg-slate-500/20 text-slate-400',
};
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short' }) : '—';
</script>

<template>
<Head title="Holidays" />
<TenantLayout>
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white">Holidays</h2>
                <p class="text-[12px] text-ink-3 mt-1">{{ totals.total || 0 }} holidays · {{ totals.paid || 0 }} paid</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="changeYear(-1)" class="btn btn-ghost btn-sm cursor-pointer">← {{ year - 1 }}</button>
                <span class="text-[14px] font-bold text-white">{{ year }}</span>
                <button @click="changeYear(1)" class="btn btn-ghost btn-sm cursor-pointer">{{ year + 1 }} →</button>
                <button @click="openAdd" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer"><Plus :size="14" /> Add Holiday</button>
            </div>
        </div>

        <!-- KPI -->
        <div class="grid grid-cols-4 gap-3 mb-5">
            <div class="card !py-3 text-center">
                <div class="text-[20px] font-bold text-white">{{ totals.total || 0 }}</div>
                <div class="text-[10px] text-ink-3 mt-0.5">Total</div>
            </div>
            <div class="card !py-3 text-center">
                <div class="text-[20px] font-bold text-orange-400">{{ totals.national || 0 }}</div>
                <div class="text-[10px] text-ink-3 mt-0.5">National</div>
            </div>
            <div class="card !py-3 text-center">
                <div class="text-[20px] font-bold text-brand-300">{{ totals.company || 0 }}</div>
                <div class="text-[10px] text-ink-3 mt-0.5">Company</div>
            </div>
            <div class="card !py-3 text-center">
                <div class="text-[20px] font-bold text-emerald-400">{{ totals.paid || 0 }}</div>
                <div class="text-[10px] text-ink-3 mt-0.5">Paid</div>
            </div>
        </div>

        <!-- List -->
        <div class="card overflow-hidden">
            <div v-for="h in holidays" :key="h.id"
                class="px-4 py-3 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-bg-3 flex items-center justify-center flex-shrink-0">
                        <CalendarDays :size="16" class="text-brand-400" />
                    </div>
                    <div>
                        <div class="text-[13px] font-medium text-white">{{ h.name }}</div>
                        <div class="text-[11px] text-ink-3 flex items-center gap-2">
                            <span class="font-mono">{{ dateFmt(h.date) }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold" :class="typeColors[h.type]">{{ h.type }}</span>
                            <span v-if="h.is_paid" class="text-emerald-400">Paid</span>
                            <span v-else class="text-rose-400">Unpaid</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="openEdit(h)" class="text-ink-3 hover:text-brand-300 cursor-pointer"><Edit :size="14" /></button>
                    <button @click="remove(h)" class="text-ink-3 hover:text-rose cursor-pointer"><Trash2 :size="14" /></button>
                </div>
            </div>
            <div v-if="!holidays.length" class="px-4 py-8 text-center text-[13px] text-ink-3">No holidays added for {{ year }}</div>
        </div>

        <!-- Add/Edit Modal -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showForm = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-4">{{ editingId ? 'Edit' : 'Add' }} Holiday</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="heyd2c-label">Date</label>
                        <input v-model="form.date" type="date" class="heyd2c-input" required />
                        <div v-if="form.errors.date" class="mt-1 text-[11px] text-rose">{{ form.errors.date }}</div>
                    </div>
                    <div>
                        <label class="heyd2c-label">Holiday Name</label>
                        <input v-model="form.name" class="heyd2c-input" placeholder="Republic Day" required />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="heyd2c-label">Type</label>
                            <select v-model="form.type" class="heyd2c-input">
                                <option value="national">National</option>
                                <option value="company">Company</option>
                                <option value="optional">Optional</option>
                                <option value="restricted">Restricted</option>
                            </select>
                        </div>
                        <div>
                            <label class="heyd2c-label">Paid?</label>
                            <select v-model="form.is_paid" class="heyd2c-input">
                                <option :value="true">Yes — Paid</option>
                                <option :value="false">No — Unpaid</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="heyd2c-label">Notes</label>
                        <input v-model="form.notes" class="heyd2c-input" placeholder="Optional" />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1" :disabled="form.processing">{{ editingId ? 'Update' : 'Add' }}</button>
                        <button type="button" class="btn btn-ghost flex-1" @click="showForm = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
