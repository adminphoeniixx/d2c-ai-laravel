<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { FileText, Plus, Edit, Trash2, Copy } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    placeholders: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showEditor = ref(false);
const editing = ref(null);

const form = useForm({ name: '', type: 'appointment', body: '' });

const typeLabels = {
    appointment: 'Appointment Letter',
    confirmation: 'Confirmation Letter',
    promotion: 'Promotion Letter',
    transfer: 'Transfer Letter',
    warning: 'Warning Letter',
    show_cause: 'Show Cause Notice',
    suspension: 'Suspension Letter',
    termination: 'Termination Letter',
    resignation_acceptance: 'Resignation Acceptance',
    relieving: 'Relieving Letter',
    experience: 'Experience Letter',
    full_and_final: 'Full & Final Settlement',
    increment: 'Increment / Appraisal',
    bonus: 'Bonus Letter',
    noc: 'NOC',
    internship: 'Internship Offer',
    probation_completion: 'Probation Completion',
    custom: 'Custom',
};

function openNew() {
    editing.value = null;
    form.reset();
    showEditor.value = true;
}

function openEdit(tpl) {
    editing.value = tpl;
    form.name = tpl.name;
    form.type = tpl.type;
    form.body = tpl.body;
    showEditor.value = true;
}

function save() {
    if (editing.value) {
        form.put(route('tenant.hr.templates.update', { tenant: slug, id: editing.value.id }), {
            onSuccess: () => { showEditor.value = false; form.reset(); },
        });
    } else {
        form.post(route('tenant.hr.templates.store', { tenant: slug }), {
            onSuccess: () => { showEditor.value = false; form.reset(); },
        });
    }
}

function deleteTemplate(id) {
    if (confirm('Delete this template?')) {
        router.delete(route('tenant.hr.templates.destroy', { tenant: slug, id }));
    }
}

function insertPlaceholder(key) {
    form.body += key;
}

const defaultTemplates = {
    appointment: `<h2 style="text-align:center">APPOINTMENT LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>{{address}}</p>
<p>Dear {{employee_name}},</p>
<p>With reference to your application and subsequent interview, we are pleased to appoint you as <strong>{{designation}}</strong> in the <strong>{{department}}</strong> department of <strong>{{company_name}}</strong>, effective from <strong>{{date_of_joining}}</strong>.</p>
<h3>Compensation Details</h3>
<table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse">
<tr><td>Annual CTC</td><td>{{ctc_annual}}</td></tr>
<tr><td>Basic Salary (Monthly)</td><td>{{basic_salary}}</td></tr>
<tr><td>HRA (Monthly)</td><td>{{hra}}</td></tr>
<tr><td>Special Allowance (Monthly)</td><td>{{special_allowance}}</td></tr>
<tr><td><strong>Total Monthly</strong></td><td><strong>{{monthly_salary}}</strong></td></tr>
</table>
<p>This appointment is subject to the terms and conditions of the company. You are expected to maintain the highest standards of integrity and professional conduct.</p>
<p>We look forward to your valuable contribution to the organization.</p>
<br>
<p>For {{company_name}}</p>
<br><br>
<p>Authorized Signatory</p>`,

    warning: `<h2 style="text-align:center">WARNING LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}<br>Department: {{department}}</p>
<p>Dear {{employee_name}},</p>
<p>This letter is to formally warn you regarding ___________________________.</p>
<p>Despite verbal warnings on previous occasions, the said behavior/performance has not improved. This is in violation of the company policies and standards expected of employees at {{company_name}}.</p>
<p>You are hereby advised to correct the above-mentioned issue immediately. Failure to do so may result in further disciplinary action including termination of employment.</p>
<p>Please acknowledge receipt of this letter by signing below.</p>
<br>
<p>For {{company_name}}</p>
<br><br>
<p>Authorized Signatory</p>
<br><br>
<p>Employee Acknowledgement: __________________ Date: __________</p>`,

    full_and_final: `<h2 style="text-align:center">FULL AND FINAL SETTLEMENT LETTER</h2>
<p>Date: {{today_date}}</p>
<p>To,<br>{{employee_name}}<br>Employee ID: {{employee_id}}</p>
<p>Dear {{employee_name}},</p>
<p>This is with reference to your separation from {{company_name}} effective {{date_of_leaving}}.</p>
<p>Please find below the details of your Full and Final settlement:</p>
<table border="1" cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse">
<tr><td>Last Working Day</td><td>{{date_of_leaving}}</td></tr>
<tr><td>Salary for the month</td><td>{{monthly_salary}}</td></tr>
<tr><td>Leave Encashment</td><td>___________</td></tr>
<tr><td>Gratuity</td><td>___________</td></tr>
<tr><td>Deductions</td><td>___________</td></tr>
<tr><td><strong>Net Payable</strong></td><td><strong>___________</strong></td></tr>
</table>
<p>The above amount will be credited to your bank account within 30 working days.</p>
<p>We wish you all the best in your future endeavors.</p>
<br>
<p>For {{company_name}}</p>
<br><br>
<p>Authorized Signatory</p>`,
};

function loadDefault(type) {
    if (defaultTemplates[type]) {
        form.body = defaultTemplates[type];
        form.name = typeLabels[type] || type;
        form.type = type;
    }
}
</script>

<template>
<Head title="Letter Templates" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Letter Templates</h2>
            <p class="text-[12px] text-ink-3 mt-1">Create reusable templates with placeholders for Appointment, Warning, and F&F letters</p>
        </div>
        <button class="btn btn-primary" @click="openNew"><Plus :size="14" /> New Template</button>
    </div>

    <!-- Quick-start defaults -->
    <div v-if="!templates.length && !showEditor" class="card mb-5">
        <h3 class="text-[14px] font-bold text-white mb-3">Quick Start — Load a Default Template</h3>
        <div class="flex flex-wrap gap-2">
            <button v-for="(label, type) in typeLabels" :key="type" class="btn btn-ghost" @click="loadDefault(type); showEditor = true">
                <FileText :size="14" /> {{ label }}
            </button>
        </div>
    </div>

    <!-- Editor -->
    <div v-if="showEditor" class="card mb-5">
        <h3 class="text-[15px] font-bold text-white mb-4">{{ editing ? 'Edit Template' : 'New Template' }}</h3>
        <form @submit.prevent="save" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Template Name</label>
                    <input v-model="form.name" class="heyd2c-input" placeholder="e.g. Appointment Letter v2" />
                </div>
                <div>
                    <label class="heyd2c-label">Type</label>
                    <select v-model="form.type" class="heyd2c-input">
                        <option v-for="(label, type) in typeLabels" :key="type" :value="type">{{ label }}</option>
                    </select>
                </div>
            </div>

            <!-- Placeholders reference -->
            <div class="rounded-[10px] bg-bg-3 border border-frost-1 p-3">
                <div class="text-[10px] font-mono uppercase tracking-widest text-ink-3 mb-2">Click to insert placeholder</div>
                <div class="flex flex-wrap gap-1.5">
                    <button v-for="(desc, key) in placeholders" :key="key" type="button"
                        class="px-2 py-1 text-[10px] font-mono rounded bg-brand-600/10 text-brand-300 border border-brand-600/20 hover:bg-brand-600/20 transition cursor-pointer"
                        @click="insertPlaceholder(key)" :title="desc">
                        {{ key }}
                    </button>
                </div>
            </div>

            <div>
                <label class="heyd2c-label">Template Body (HTML with placeholders)</label>
                <textarea v-model="form.body" class="heyd2c-input font-mono text-[12px]" rows="20" placeholder="Write your letter template here using HTML and {{placeholders}}..."></textarea>
                <div v-if="form.errors.body" class="mt-1 text-[11px] text-rose">{{ form.errors.body }}</div>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save Template' }}</button>
                <button type="button" class="btn btn-ghost" @click="showEditor = false; form.reset()">Cancel</button>
                <button v-if="!editing && defaultTemplates[form.type]" type="button" class="btn btn-ghost ml-auto" @click="loadDefault(form.type)">Load Default</button>
            </div>
        </form>
    </div>

    <!-- Existing templates -->
    <div v-if="templates.length" class="space-y-3">
        <div v-for="tpl in templates" :key="tpl.id" class="card flex items-start justify-between">
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <FileText :size="14" class="text-brand-400" />
                    <span class="font-medium text-white text-[14px]">{{ tpl.name }}</span>
                    <span class="pill pill-info text-[10px]">{{ typeLabels[tpl.type] || tpl.type }}</span>
                </div>
                <p class="text-[11px] text-ink-3 mt-1">Last updated {{ new Date(tpl.updated_at).toLocaleDateString() }}</p>
            </div>
            <div class="flex gap-1.5">
                <button class="btn btn-ghost btn-sm" @click="openEdit(tpl)"><Edit :size="12" /> Edit</button>
                <button class="btn btn-ghost btn-sm text-rose" @click="deleteTemplate(tpl.id)"><Trash2 :size="12" /></button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
