<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { ArrowLeft, FileText } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    workers: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    placeholders: { type: Object, default: () => ({}) },
    selectedEmployee: { type: Object, default: null },
    selectedWorker: { type: Object, default: null },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const typeLabels = {
    appointment: 'Appointment Letter', confirmation: 'Confirmation Letter', promotion: 'Promotion Letter',
    transfer: 'Transfer Letter', warning: 'Warning Letter', show_cause: 'Show Cause Notice',
    suspension: 'Suspension Letter', termination: 'Termination Letter', resignation_acceptance: 'Resignation Acceptance',
    relieving: 'Relieving Letter', experience: 'Experience Letter', full_and_final: 'Full & Final Settlement',
    increment: 'Increment / Appraisal', bonus: 'Bonus Letter', noc: 'NOC',
    internship: 'Internship Offer', probation_completion: 'Probation Completion',
    worker_appointment: 'अस्थाई नियुक्ति पत्र (Hindi)',
    custom: 'Custom',
};

const form = useForm({
    employee_id: props.selectedEmployee?.id || '',
    letter_template_id: '',
    type: 'appointment',
    title: '',
    body: '',
});

// When template selected, load its body
watch(() => form.letter_template_id, (id) => {
    if (!id) return;
    const tpl = props.templates.find(t => t.id == id);
    if (tpl) {
        form.body = tpl.body;
        form.type = tpl.type;
        form.title = tpl.name;
    }
});

// Auto-set title from type if not set
watch(() => form.type, (type) => {
    if (!form.title || Object.values(typeLabels).includes(form.title)) {
        form.title = typeLabels[type] || type;
    }
});

function submit() {
    form.post(route('tenant.hr.letters.store', { tenant: slug }));
}

function insertPlaceholder(key) {
    form.body += key;
}
</script>

<template>
<Head title="Create Letter" />
<TenantLayout>
    <div class="flex items-center gap-3 mb-5">
        <Link :href="route('tenant.hr.employees', { tenant: slug })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> Back</Link>
        <h2 class="text-[20px] font-bold text-white">Create Letter</h2>
    </div>

    <form @submit.prevent="submit" class="max-w-3xl space-y-5">
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">Letter Details</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Employee *</label>
                    <select v-model="form.employee_id" class="heyd2c-input">
                        <option value="">Select employee</option>
                        <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                            {{ emp.first_name }} {{ emp.last_name }} ({{ emp.employee_id }})
                        </option>
                    </select>
                    <div v-if="form.errors.employee_id" class="mt-1 text-[11px] text-rose">{{ form.errors.employee_id }}</div>
                </div>
                <div>
                    <label class="heyd2c-label">Type</label>
                    <select v-model="form.type" class="heyd2c-input">
                        <option v-for="(label, type) in typeLabels" :key="type" :value="type">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="heyd2c-label">Use Template</label>
                    <select v-model="form.letter_template_id" class="heyd2c-input">
                        <option value="">— No template (blank) —</option>
                        <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id">{{ tpl.name }} ({{ typeLabels[tpl.type] || tpl.type }})</option>
                    </select>
                </div>
                <div>
                    <label class="heyd2c-label">Title</label>
                    <input v-model="form.title" class="heyd2c-input" />
                    <div v-if="form.errors.title" class="mt-1 text-[11px] text-rose">{{ form.errors.title }}</div>
                </div>
            </div>
        </div>

        <!-- Placeholders -->
        <div class="rounded-[10px] bg-bg-3 border border-frost-1 p-3">
            <div class="text-[10px] font-mono uppercase tracking-widest text-ink-3 mb-2">Click to insert placeholder (auto-filled with employee data on save)</div>
            <div class="flex flex-wrap gap-1.5">
                <button v-for="(desc, key) in placeholders" :key="key" type="button"
                    class="px-2 py-1 text-[10px] font-mono rounded bg-brand-600/10 text-brand-300 border border-brand-600/20 hover:bg-brand-600/20 transition cursor-pointer"
                    @click="insertPlaceholder(key)" :title="desc">
                    {{ key }}
                </button>
            </div>
        </div>

        <div>
            <label class="heyd2c-label">Letter Body (HTML — placeholders will be replaced with employee data)</label>
            <textarea v-model="form.body" class="heyd2c-input font-mono text-[12px]" rows="25" placeholder="Write or paste your letter content here, or select a template above..."></textarea>
            <div v-if="form.errors.body" class="mt-1 text-[11px] text-rose">{{ form.errors.body }}</div>
        </div>

        <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
            {{ form.processing ? 'Creating…' : 'Create Letter' }}
        </button>
    </form>
</TenantLayout>
</template>
