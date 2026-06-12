<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit2, Trash2, HelpCircle, ChevronDown, ChevronRight } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    faqs: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showAdd = ref(false);
const editId = ref(null);
const expandedId = ref(null);
const form = useForm({ question: '', answer: '', category_id: '' });
const editForm = useForm({ question: '', answer: '', category_id: '', is_active: true });

function submit() {
    form.post(route('tenant.support.faqs.store', { tenant: slug }), { onSuccess: () => { showAdd.value = false; form.reset(); } });
}
function startEdit(faq) {
    editId.value = faq.id;
    editForm.question = faq.question;
    editForm.answer = faq.answer;
    editForm.category_id = faq.category_id || '';
    editForm.is_active = faq.is_active;
}
function saveEdit(faq) {
    editForm.put(route('tenant.support.faqs.update', { tenant: slug, faq: faq.id }), { onSuccess: () => { editId.value = null; } });
}
function deleteFaq(faq) {
    if (confirm('Delete this FAQ?')) router.delete(route('tenant.support.faqs.destroy', { tenant: slug, faq: faq.id }));
}
</script>

<template>
<Head title="FAQ" />
<TenantLayout>
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white flex items-center gap-2"><HelpCircle :size="20" /> FAQ Management</h2>
                <p class="text-[12px] text-ink-3 mt-1">{{ faqs.length }} question{{ faqs.length !== 1 ? 's' : '' }}</p>
            </div>
            <button @click="showAdd = true" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer"><Plus :size="14" /> Add FAQ</button>
        </div>

        <!-- FAQ List -->
        <div class="space-y-2">
            <div v-for="faq in faqs" :key="faq.id" class="card overflow-hidden">
                <template v-if="editId === faq.id">
                    <div class="space-y-2 p-1">
                        <input v-model="editForm.question" class="heyd2c-input text-[13px]" placeholder="Question" />
                        <textarea v-model="editForm.answer" class="heyd2c-input text-[12px]" rows="3" placeholder="Answer"></textarea>
                        <div class="flex gap-2">
                            <select v-model="editForm.category_id" class="heyd2c-input flex-1">
                                <option value="">No category</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <button @click="saveEdit(faq)" class="btn btn-primary btn-sm cursor-pointer" :disabled="editForm.processing">Save</button>
                            <button @click="editId = null" class="btn btn-ghost btn-sm cursor-pointer">Cancel</button>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <div class="flex items-start justify-between cursor-pointer" @click="expandedId = expandedId === faq.id ? null : faq.id">
                        <div class="flex items-start gap-2 flex-1 min-w-0">
                            <component :is="expandedId === faq.id ? ChevronDown : ChevronRight" :size="14" class="text-ink-3 mt-0.5 flex-shrink-0" />
                            <div>
                                <div class="text-[13px] font-medium text-white">{{ faq.question }}</div>
                                <div class="text-[10px] text-ink-3 mt-0.5">
                                    <span v-if="faq.category">{{ faq.category.name }}</span>
                                    <span v-if="!faq.is_active" class="ml-1 text-rose">· Hidden</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-1 flex-shrink-0" @click.stop>
                            <button @click="startEdit(faq)" class="text-ink-3 hover:text-white cursor-pointer"><Edit2 :size="12" /></button>
                            <button @click="deleteFaq(faq)" class="text-ink-3 hover:text-rose cursor-pointer"><Trash2 :size="12" /></button>
                        </div>
                    </div>
                    <div v-if="expandedId === faq.id" class="mt-2 pl-6 text-[12px] text-ink-2 whitespace-pre-wrap border-t border-frost-1 pt-2">{{ faq.answer }}</div>
                </template>
            </div>
            <div v-if="!faqs.length" class="card text-center py-8 text-[13px] text-ink-3">No FAQs yet</div>
        </div>

        <!-- Add Modal -->
        <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showAdd = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-4">Add FAQ</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div>
                        <label class="heyd2c-label">Category</label>
                        <select v-model="form.category_id" class="heyd2c-input">
                            <option value="">None</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="heyd2c-label">Question</label>
                        <input v-model="form.question" class="heyd2c-input" required />
                    </div>
                    <div>
                        <label class="heyd2c-label">Answer</label>
                        <textarea v-model="form.answer" class="heyd2c-input" rows="4" required></textarea>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" class="btn btn-primary flex-1 cursor-pointer" :disabled="form.processing">Add</button>
                        <button type="button" class="btn btn-ghost flex-1 cursor-pointer" @click="showAdd = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
