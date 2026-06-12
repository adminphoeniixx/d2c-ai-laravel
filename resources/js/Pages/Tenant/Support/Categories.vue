<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit2, FolderKanban, Clock, MessageSquare } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showAdd = ref(false);
const editId = ref(null);
const form = useForm({ name: '', auto_reply: '', sla_hours: 24 });
const editForm = useForm({ name: '', auto_reply: '', sla_hours: 24, is_active: true });

function submit() {
    form.post(route('tenant.support.categories.store', { tenant: slug }), { onSuccess: () => { showAdd.value = false; form.reset(); form.sla_hours = 24; } });
}
function startEdit(cat) {
    editId.value = cat.id;
    editForm.name = cat.name;
    editForm.auto_reply = cat.auto_reply || '';
    editForm.sla_hours = cat.sla_hours;
    editForm.is_active = cat.is_active;
}
function saveEdit(cat) {
    editForm.put(route('tenant.support.categories.update', { tenant: slug, category: cat.id }), { onSuccess: () => { editId.value = null; } });
}
</script>

<template>
<Head title="Support Categories" />
<TenantLayout>
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white flex items-center gap-2"><FolderKanban :size="20" /> Categories</h2>
                <p class="text-[12px] text-ink-3 mt-1">Configure ticket categories, SLA, and auto-replies</p>
            </div>
            <button @click="showAdd = true" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer"><Plus :size="14" /> Add</button>
        </div>

        <div class="space-y-2">
            <div v-for="cat in categories" :key="cat.id" class="card">
                <template v-if="editId === cat.id">
                    <div class="space-y-2">
                        <input v-model="editForm.name" class="heyd2c-input" placeholder="Category name" />
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="heyd2c-label">SLA (hours)</label>
                                <input v-model.number="editForm.sla_hours" type="number" class="heyd2c-input" min="1" />
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 text-[11px] text-ink-3 cursor-pointer">
                                    <input v-model="editForm.is_active" type="checkbox" class="accent-brand-600" /> Active
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="heyd2c-label">Auto Reply (bot sends this when ticket is created)</label>
                            <textarea v-model="editForm.auto_reply" class="heyd2c-input text-[12px]" rows="2"></textarea>
                        </div>
                        <div class="flex gap-2">
                            <button @click="saveEdit(cat)" class="btn btn-primary btn-sm cursor-pointer" :disabled="editForm.processing">Save</button>
                            <button @click="editId = null" class="btn btn-ghost btn-sm cursor-pointer">Cancel</button>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-9 w-9 rounded-xl bg-brand-600/15 flex items-center justify-center">
                                <FolderKanban :size="16" class="text-brand-300" />
                            </div>
                            <div>
                                <div class="text-[13px] font-medium text-white">
                                    {{ cat.name }}
                                    <span v-if="!cat.is_active" class="text-[9px] text-rose ml-1">HIDDEN</span>
                                </div>
                                <div class="flex items-center gap-3 text-[10px] text-ink-3 mt-0.5">
                                    <span class="flex items-center gap-0.5"><Clock :size="9" /> {{ cat.sla_hours }}h SLA</span>
                                    <span class="flex items-center gap-0.5"><MessageSquare :size="9" /> {{ cat.tickets_count }} tickets</span>
                                    <span v-if="cat.auto_reply" class="text-emerald-400">Bot reply ✓</span>
                                </div>
                            </div>
                        </div>
                        <button @click="startEdit(cat)" class="text-ink-3 hover:text-white cursor-pointer"><Edit2 :size="14" /></button>
                    </div>
                </template>
            </div>
        </div>

        <!-- Add Modal -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showAdd = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-4">New Category</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="heyd2c-label">Name</label>
                        <input v-model="form.name" class="heyd2c-input" required />
                    </div>
                    <div>
                        <label class="heyd2c-label">SLA (hours)</label>
                        <input v-model.number="form.sla_hours" type="number" class="heyd2c-input" min="1" />
                    </div>
                    <div>
                        <label class="heyd2c-label">Auto Reply (optional)</label>
                        <textarea v-model="form.auto_reply" class="heyd2c-input text-[12px]" rows="3" placeholder="Bot sends this when a customer creates a ticket in this category"></textarea>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="btn btn-primary flex-1 cursor-pointer" :disabled="form.processing">Create</button>
                        <button type="button" class="btn btn-ghost flex-1 cursor-pointer" @click="showAdd = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
