<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Edit, FileText, Plus, Mail, Phone, MapPin, Building2, Calendar, CreditCard, IndianRupee, Upload, Trash2, File, X, Eye } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    employee: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const statusMap = {
    active: { class: 'pill-good', label: 'Active' },
    on_notice: { class: 'pill-info', label: 'On Notice' },
    terminated: { class: 'pill-bad', label: 'Terminated' },
    resigned: { class: 'pill-bad', label: 'Resigned' },
};

const letterTypeMap = {
    appointment: 'Appointment', warning: 'Warning', full_and_final: 'Full & Final', custom: 'Custom',
    worker_appointment: 'Worker Appointment',
};

const docTypes = ['aadhaar', 'pan', 'resume', 'offer_letter', 'id_proof', 'payslip', 'other'];
const showDocUpload = ref(false);
const previewDoc = ref(null);
const docForm = useForm({
    employee_id: props.employee.id,
    name: '',
    type: 'other',
    file: null,
    notes: '',
});

function isImage(doc) { return doc.mime_type?.startsWith('image/') || /\.(jpg|jpeg|png|gif|webp)$/i.test(doc.file_url || ''); }
function isPdf(doc) { return doc.mime_type === 'application/pdf' || /\.pdf$/i.test(doc.file_url || ''); }

function onFileSelect(e) { docForm.file = e.target.files[0]; }

function uploadDoc() {
    docForm.post(route('tenant.hr.documents.store', { tenant: slug }), {
        forceFormData: true,
        onSuccess: () => { showDocUpload.value = false; docForm.reset(); docForm.employee_id = props.employee.id; },
    });
}

function deleteDoc(doc) {
    if (confirm(`Delete document "${doc.name}"?`)) {
        router.delete(route('tenant.hr.documents.destroy', { tenant: slug, id: doc.id }));
    }
}
</script>

<template>
<Head :title="employee.first_name + ' ' + (employee.last_name || '')" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <Link :href="route('tenant.hr.employees', { tenant: slug })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> Back</Link>
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-[22px] font-bold text-white">{{ employee.first_name }} {{ employee.last_name }}</h2>
                    <span class="pill" :class="statusMap[employee.status]?.class">{{ statusMap[employee.status]?.label }}</span>
                </div>
                <p class="text-[12px] text-ink-3 font-mono">{{ employee.employee_id }} · {{ employee.designation || 'No designation' }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <Link :href="route('tenant.hr.letters.create', { tenant: slug }) + '?employee_id=' + employee.id" class="btn btn-ghost"><FileText :size="14" /> Create Letter</Link>
            <Link :href="route('tenant.hr.employees.edit', { tenant: slug, id: employee.id })" class="btn btn-primary"><Edit :size="14" /> Edit</Link>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <div class="lg:col-span-2 space-y-5">
            <!-- Employment -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4 flex items-center gap-2"><Building2 :size="15" class="text-brand-400" /> Employment</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-[13px]">
                    <div><span class="heyd2c-label">Designation</span><div class="text-ink">{{ employee.designation || '—' }}</div></div>
                    <div><span class="heyd2c-label">Department</span><div class="text-ink">{{ employee.department || '—' }}</div></div>
                    <div><span class="heyd2c-label">Joined</span><div class="text-ink">{{ dateFmt(employee.date_of_joining) }}</div></div>
                    <div><span class="heyd2c-label">Type</span><div class="text-ink capitalize">{{ employee.employment_type?.replace('_', ' ') }}</div></div>
                </div>
            </div>

            <!-- Compensation -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4 flex items-center gap-2"><IndianRupee :size="15" class="text-brand-400" /> Compensation</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-[13px]">
                    <div><span class="heyd2c-label">Annual CTC</span><div class="text-white font-mono font-semibold text-[15px]">{{ fmt(employee.ctc_annual) }}</div></div>
                    <div><span class="heyd2c-label">Basic Salary</span><div class="text-ink font-mono">{{ fmt(employee.basic_salary) }}</div></div>
                    <div><span class="heyd2c-label">HRA</span><div class="text-ink font-mono">{{ fmt(employee.hra) }}</div></div>
                    <div><span class="heyd2c-label">Special Allowance</span><div class="text-ink font-mono">{{ fmt(employee.special_allowance) }}</div></div>
                    <div><span class="heyd2c-label">Other Allowance</span><div class="text-ink font-mono">{{ fmt(employee.other_allowance) }}</div></div>
                    <div><span class="heyd2c-label">Monthly Total</span><div class="text-emerald font-mono font-semibold">{{ fmt(parseFloat(employee.basic_salary || 0) + parseFloat(employee.hra || 0) + parseFloat(employee.special_allowance || 0) + parseFloat(employee.other_allowance || 0)) }}</div></div>
                </div>
            </div>

            <!-- Bank & Statutory -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4 flex items-center gap-2"><CreditCard :size="15" class="text-brand-400" /> Bank & Statutory</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4 text-[13px]">
                    <div><span class="heyd2c-label">Bank</span><div class="text-ink">{{ employee.bank_name || '—' }}</div></div>
                    <div><span class="heyd2c-label">Account No.</span><div class="text-ink font-mono">{{ employee.bank_account_number || '—' }}</div></div>
                    <div><span class="heyd2c-label">IFSC</span><div class="text-ink font-mono">{{ employee.bank_ifsc || '—' }}</div></div>
                    <div><span class="heyd2c-label">PAN</span><div class="text-ink font-mono">{{ employee.pan_number || '—' }}</div></div>
                    <div><span class="heyd2c-label">Aadhaar</span><div class="text-ink font-mono">{{ employee.aadhaar_number || '—' }}</div></div>
                    <div><span class="heyd2c-label">UAN (PF)</span><div class="text-ink font-mono">{{ employee.uan_number || '—' }}</div></div>
                </div>
            </div>

            <!-- Letters -->
            <div class="card overflow-hidden p-0">
                <div class="px-5 py-3 border-b border-frost-1 flex items-center justify-between">
                    <h3 class="text-[15px] font-bold text-white flex items-center gap-2"><FileText :size="15" class="text-brand-400" /> Letters</h3>
                    <Link :href="route('tenant.hr.letters.create', { tenant: slug }) + '?employee_id=' + employee.id" class="btn btn-ghost btn-sm"><Plus :size="12" /> New</Link>
                </div>
                <div v-if="employee.letters?.length">
                    <div v-for="l in employee.letters" :key="l.id"
                        class="px-5 py-3 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition cursor-pointer flex items-center justify-between"
                        @click="router.visit(route('tenant.hr.letters.show', { tenant: slug, id: l.id }))">
                        <div>
                            <div class="text-[13px] font-medium text-white">{{ l.title }}</div>
                            <div class="text-[11px] text-ink-3">{{ letterTypeMap[l.type] || l.type }} · {{ dateFmt(l.created_at) }}</div>
                        </div>
                        <span class="pill" :class="l.status === 'issued' ? 'pill-good' : 'pill-info'">{{ l.status }}</span>
                    </div>
                </div>
                <div v-else class="px-5 py-6 text-center text-[13px] text-ink-3">No letters issued yet.</div>
            </div>

            <!-- Documents (Bunny CDN) -->
            <div class="card overflow-hidden p-0">
                <div class="px-5 py-3 border-b border-frost-1 flex items-center justify-between">
                    <h3 class="text-[15px] font-bold text-white flex items-center gap-2"><File :size="15" class="text-brand-400" /> Documents</h3>
                    <button class="btn btn-ghost btn-sm" @click="showDocUpload = true"><Upload :size="12" /> Upload</button>
                </div>
                <div v-if="employee.documents?.length">
                    <div v-for="doc in employee.documents" :key="doc.id"
                        class="px-5 py-3 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition flex items-center justify-between">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="h-9 w-9 rounded-lg bg-brand-600/15 flex items-center justify-center flex-shrink-0">
                                <File :size="16" class="text-brand-400" />
                            </div>
                            <div class="min-w-0">
                                <div class="text-[13px] font-medium text-white truncate">{{ doc.name }}</div>
                                <div class="text-[10px] text-ink-3">{{ doc.type?.replace('_', ' ') }} · {{ doc.file_size || '—' }} · {{ doc.file_name }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button class="text-ink-3 hover:text-brand-300 cursor-pointer" title="Preview" @click="previewDoc = doc"><Eye :size="14" /></button>
                            <a :href="doc.file_url" target="_blank" class="text-ink-3 hover:text-white" title="Open in new tab"><Upload :size="14" /></a>
                            <button class="text-ink-3 hover:text-rose cursor-pointer" @click="deleteDoc(doc)"><Trash2 :size="14" /></button>
                        </div>
                    </div>
                </div>
                <div v-else class="px-5 py-6 text-center text-[13px] text-ink-3">No documents uploaded yet.</div>
            </div>

            <!-- Document Upload Modal -->
            <div v-if="showDocUpload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showDocUpload = false">
                <div class="card w-full max-w-md mx-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-[16px] font-bold text-white">Upload Document</h3>
                        <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showDocUpload = false"><X :size="18" /></button>
                    </div>
                    <form @submit.prevent="uploadDoc" class="space-y-3">
                        <div><label class="heyd2c-label">Document Name *</label><input v-model="docForm.name" class="heyd2c-input" placeholder="Aadhaar Card" /><div v-if="docForm.errors.name" class="mt-1 text-[11px] text-rose">{{ docForm.errors.name }}</div></div>
                        <div><label class="heyd2c-label">Type</label>
                            <select v-model="docForm.type" class="heyd2c-input">
                                <option v-for="t in docTypes" :key="t" :value="t">{{ t.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="heyd2c-label">File *</label>
                            <input type="file" @change="onFileSelect" class="heyd2c-input text-[12px]" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" />
                            <div v-if="docForm.errors.file" class="mt-1 text-[11px] text-rose">{{ docForm.errors.file }}</div>
                            <div class="mt-1 text-[10px] text-ink-3">PDF, JPG, PNG, DOC — max 10MB</div>
                        </div>
                        <div><label class="heyd2c-label">Notes</label><input v-model="docForm.notes" class="heyd2c-input" placeholder="Optional notes…" /></div>
                        <button type="submit" class="btn btn-primary w-full py-2.5" :disabled="docForm.processing">{{ docForm.processing ? 'Uploading…' : 'Upload Document' }}</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right column -->
        <div class="space-y-5">
            <!-- Contact -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-3">Contact</h3>
                <div class="space-y-3 text-[13px]">
                    <div v-if="employee.email" class="flex items-center gap-2"><Mail :size="13" class="text-ink-3" /> <span class="text-ink font-mono text-[12px]">{{ employee.email }}</span></div>
                    <div v-if="employee.phone" class="flex items-center gap-2"><Phone :size="13" class="text-ink-3" /> <span class="text-ink">{{ employee.phone }}</span></div>
                    <div v-if="employee.date_of_birth" class="flex items-center gap-2"><Calendar :size="13" class="text-ink-3" /> <span class="text-ink">DOB: {{ dateFmt(employee.date_of_birth) }}</span></div>
                </div>
            </div>

            <!-- Address -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-3 flex items-center gap-2"><MapPin :size="14" class="text-brand-400" /> Address</h3>
                <div class="text-[13px] text-ink-2 leading-relaxed">
                    <div v-if="employee.address">{{ employee.address }}</div>
                    <div>{{ [employee.city, employee.state, employee.pincode].filter(Boolean).join(', ') || '—' }}</div>
                </div>
            </div>

            <!-- Emergency -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-3">Emergency Contact</h3>
                <div class="text-[13px] text-ink-2 space-y-1">
                    <div>{{ employee.emergency_contact_name || '—' }}</div>
                    <div v-if="employee.emergency_contact_phone" class="font-mono text-[12px]">{{ employee.emergency_contact_phone }}</div>
                    <div v-if="employee.emergency_contact_relation" class="text-ink-3">{{ employee.emergency_contact_relation }}</div>
                </div>
            </div>

            <div v-if="employee.notes" class="card">
                <h3 class="text-[15px] font-bold text-white mb-3">Notes</h3>
                <p class="text-[13px] text-ink-2">{{ employee.notes }}</p>
            </div>
        </div>
    </div>

    <!-- Document Preview Modal -->
    <div v-if="previewDoc" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="previewDoc = null">
        <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-4xl max-h-[90vh] mx-4 flex flex-col overflow-hidden">
            <div class="px-5 py-3 border-b border-frost-1 flex items-center justify-between flex-shrink-0">
                <div>
                    <h3 class="text-[15px] font-bold text-white">{{ previewDoc.name }}</h3>
                    <p class="text-[11px] text-ink-3">{{ previewDoc.file_name }} · {{ previewDoc.file_size }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="previewDoc.file_url" target="_blank" class="btn btn-ghost btn-sm">Open in Tab</a>
                    <a :href="previewDoc.file_url" download class="btn btn-ghost btn-sm">Download</a>
                    <button class="text-ink-3 hover:text-ink cursor-pointer" @click="previewDoc = null"><X :size="18" /></button>
                </div>
            </div>
            <div class="flex-1 overflow-auto p-4 flex items-center justify-center min-h-[400px]">
                <!-- Image preview -->
                <img v-if="isImage(previewDoc)" :src="previewDoc.file_url" :alt="previewDoc.name" class="max-w-full max-h-[75vh] object-contain rounded-lg" />
                <!-- PDF preview -->
                <iframe v-else-if="isPdf(previewDoc)" :src="previewDoc.file_url" class="w-full h-[75vh] rounded-lg border-0"></iframe>
                <!-- Other files -->
                <div v-else class="text-center">
                    <File :size="48" class="text-ink-3 mx-auto mb-4" />
                    <p class="text-[14px] text-ink-2 mb-2">Preview not available for this file type</p>
                    <p class="text-[12px] text-ink-3 mb-4">{{ previewDoc.mime_type || 'Unknown type' }}</p>
                    <a :href="previewDoc.file_url" target="_blank" class="btn btn-primary">Open File</a>
                </div>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
