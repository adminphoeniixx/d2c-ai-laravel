<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Printer, Edit, CheckCircle, Trash2, Upload } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    letter: { type: Object, required: true },
    company: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const isEditing = ref(false);
const editBody = ref(props.letter.body);
const statusForm = useForm({});
const letterTypeMap = { appointment: 'Appointment Letter', warning: 'Warning Letter', full_and_final: 'Full & Final Settlement', custom: 'Custom Letter' };

function printLetter() {
    const printWindow = window.open('', '_blank');
    const letterhead = props.company?.letterhead_url
        ? `<img src="${props.company.letterhead_url}" style="width:100%;max-height:120px;object-fit:contain;margin-bottom:20px" />`
        : '';

    printWindow.document.write(`<!DOCTYPE html>
<html>
<head>
    <title>${props.letter.title}</title>
    <style>
        @media print { @page { margin: 15mm 20mm; } }
        body { font-family: 'Georgia', 'Times New Roman', serif; font-size: 14px; line-height: 1.7; color: #111; max-width: 800px; margin: 0 auto; padding: 40px 20px; }
        h2, h3 { font-family: 'Arial', sans-serif; }
        table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        td, th { padding: 8px 12px; border: 1px solid #ccc; font-size: 13px; }
        .letterhead { text-align: center; margin-bottom: 20px; }
        .footer { margin-top: 40px; font-size: 12px; color: #666; text-align: center; }
    </style>
</head>
<body>
    <div class="letterhead">${letterhead}</div>
    ${props.letter.body}
    <div class="footer">${props.company?.name || ''}</div>
</body>
</html>`);
    printWindow.document.close();
    setTimeout(() => printWindow.print(), 300);
}

function saveEdit() {
    router.put(route('tenant.hr.letters.update', { tenant: slug, id: props.letter.id }), { body: editBody.value }, {
        onSuccess: () => { isEditing.value = false; },
    });
}

function markIssued() {
    if (confirm('Mark this letter as issued? This action cannot be undone.')) {
        statusForm.put(route('tenant.hr.letters.update', { tenant: slug, id: props.letter.id }), {
            data: { status: 'issued', body: props.letter.body },
        });
    }
}

function deleteLetter() {
    if (confirm('Delete this letter?')) {
        router.delete(route('tenant.hr.letters.destroy', { tenant: slug, id: props.letter.id }));
    }
}
</script>

<template>
<Head :title="letter.title" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <Link :href="route('tenant.hr.employees.show', { tenant: slug, id: letter.employee_id })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> Back</Link>
            <div>
                <h2 class="text-[20px] font-bold text-white">{{ letter.title }}</h2>
                <p class="text-[12px] text-ink-3">
                    {{ letterTypeMap[letter.type] || letter.type }} · {{ letter.employee?.first_name }} {{ letter.employee?.last_name }}
                    <span class="pill ml-2" :class="letter.status === 'issued' ? 'pill-good' : 'pill-info'">{{ letter.status }}</span>
                </p>
            </div>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-ghost" @click="printLetter"><Printer :size="14" /> Print / PDF</button>
            <button v-if="!isEditing && letter.status === 'draft'" class="btn btn-ghost" @click="isEditing = true"><Edit :size="14" /> Edit</button>
            <button v-if="letter.status === 'draft'" class="btn btn-primary" @click="markIssued"><CheckCircle :size="14" /> Mark Issued</button>
            <button class="btn btn-ghost text-rose" @click="deleteLetter"><Trash2 :size="14" /></button>
        </div>
    </div>

    <!-- Editor mode -->
    <div v-if="isEditing" class="card mb-5">
        <h3 class="text-[14px] font-bold text-white mb-3">Edit Letter Body</h3>
        <textarea v-model="editBody" class="pulsara-input font-mono text-[12px]" rows="25"></textarea>
        <div class="flex gap-2 mt-3">
            <button class="btn btn-primary" @click="saveEdit">Save Changes</button>
            <button class="btn btn-ghost" @click="isEditing = false; editBody = letter.body">Cancel</button>
        </div>
    </div>

    <!-- Preview -->
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[14px] font-bold text-white">Preview</h3>
            <div class="text-[11px] text-ink-3">
                <span v-if="letter.issued_at">Issued: {{ new Date(letter.issued_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) }}</span>
                <span v-else>Draft — not yet issued</span>
            </div>
        </div>

        <!-- Letterhead -->
        <div v-if="company?.letterhead_url" class="mb-4 text-center">
            <img :src="company.letterhead_url" alt="Letterhead" class="max-h-[100px] mx-auto object-contain" />
        </div>

        <!-- Letter content -->
        <div class="bg-white text-gray-900 rounded-lg p-8 shadow-md"
             style="font-family: Georgia, 'Times New Roman', serif; font-size: 14px; line-height: 1.7"
             v-html="letter.body">
        </div>
    </div>
</TenantLayout>
</template>
