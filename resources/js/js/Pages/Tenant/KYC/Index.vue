<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ShieldCheck, CheckCircle, Clock, XCircle, Upload, FileText, Trash2, Eye } from 'lucide-vue-next'
import TenantLayout from '@/Layouts/TenantLayout.vue'

const props = defineProps({
    kyc:    { type: Object, default: null },
    status: { type: String, default: 'pending' },
})

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || ''

const form = useForm({
    legal_name:          props.kyc?.legal_name          || '',
    business_type:       props.kyc?.business_type       || '',
    gstin:               props.kyc?.gstin               || '',
    pan:                 props.kyc?.pan                 || '',
    address_line1:       props.kyc?.address_line1       || '',
    address_line2:       props.kyc?.address_line2       || '',
    city:                props.kyc?.city                || '',
    state:               props.kyc?.state               || '',
    pincode:             props.kyc?.pincode             || '',
    bank_account_name:   props.kyc?.bank_account_name   || '',
    bank_account_number: props.kyc?.bank_account_number || '',
    bank_ifsc:           props.kyc?.bank_ifsc           || '',
    bank_name:           props.kyc?.bank_name           || '',
    documents:           [],
})

const isApproved  = computed(() => props.status === 'approved')
const isSubmitted = computed(() => props.status === 'submitted')
const isRejected  = computed(() => props.status === 'rejected')
const canEdit     = computed(() => ['pending', 'rejected'].includes(props.status))

const existingDocs  = ref(props.kyc?.documents ?? [])
const newFileNames  = ref([])
const dragOver      = ref(false)

function onFileChange(e) {
    const files = Array.from(e.target.files)
    form.documents = files
    newFileNames.value = files.map(f => f.name)
}

function onDrop(e) {
    dragOver.value = false
    const files = Array.from(e.dataTransfer.files)
    form.documents = files
    newFileNames.value = files.map(f => f.name)
}

function removeNewFile(i) {
    const arr = [...form.documents]
    arr.splice(i, 1)
    form.documents = arr
    newFileNames.value.splice(i, 1)
}

function deleteExistingDoc(url) {
    if (!confirm('Remove this document?')) return
    router.post(route('tenant.kyc.delete-doc', { tenant: slug }), { url }, {
        preserveScroll: true,
        onSuccess: () => { existingDocs.value = existingDocs.value.filter(d => d.url !== url) }
    })
}

function submit() {
    form.post(route('tenant.kyc.submit', { tenant: slug }), {
        preserveScroll: true,
        forceFormData: true,
    })
}

function isPdf(url) { return url?.toLowerCase().endsWith('.pdf') }

const businessTypes = ['Proprietorship', 'Partnership', 'Private Limited', 'LLP', 'Public Limited', 'Trust', 'NGO', 'Other']
const indianStates  = ['Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chhattisgarh','Goa','Gujarat','Haryana','Himachal Pradesh','Jharkhand','Karnataka','Kerala','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal','Delhi','Jammu & Kashmir','Ladakh','Puducherry','Chandigarh']
</script>

<template>
<Head title="KYC Verification" />
<TenantLayout>
    <div class="max-w-2xl space-y-5">
        <div class="flex items-center gap-3">
            <ShieldCheck :size="22" class="text-brand-400" />
            <h1 class="text-[20px] font-bold text-white">KYC Verification</h1>
        </div>

        <!-- Status banner -->
        <div v-if="isApproved" class="card border border-emerald-500/30 flex items-center gap-3">
            <CheckCircle :size="20" class="text-emerald-400 shrink-0" />
            <div>
                <p class="text-[13px] font-semibold text-emerald-400">KYC Approved</p>
                <p class="text-[11px] text-ink-3">Your business is verified. Full access enabled.</p>
            </div>
        </div>
        <div v-else-if="isSubmitted" class="card border border-amber-500/30 flex items-center gap-3">
            <Clock :size="20" class="text-amber-400 shrink-0" />
            <div>
                <p class="text-[13px] font-semibold text-amber-400">KYC Under Review</p>
                <p class="text-[11px] text-ink-3">We'll notify you within 24-48 hours.</p>
            </div>
        </div>
        <div v-else-if="isRejected" class="card border border-rose-500/30 flex items-center gap-3">
            <XCircle :size="20" class="text-rose-400 shrink-0" />
            <div>
                <p class="text-[13px] font-semibold text-rose-400">KYC Rejected — Please resubmit</p>
                <p class="text-[11px] text-rose-300 mt-0.5">{{ kyc?.rejection_reason || 'Please correct and resubmit.' }}</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">

            <!-- Business Details -->
            <div class="card space-y-3">
                <h3 class="text-[13px] font-semibold text-white">Business Details</h3>
                <div>
                    <label class="heyd2c-label">Legal Business Name *</label>
                    <input v-model="form.legal_name" :disabled="isApproved" class="heyd2c-input" placeholder="As per registration certificate" />
                    <p v-if="form.errors.legal_name" class="text-rose-400 text-[11px] mt-1">{{ form.errors.legal_name }}</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="heyd2c-label">Business Type *</label>
                        <select v-model="form.business_type" :disabled="isApproved" class="heyd2c-input">
                            <option value="">Select…</option>
                            <option v-for="t in businessTypes" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <p v-if="form.errors.business_type" class="text-rose-400 text-[11px] mt-1">{{ form.errors.business_type }}</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">GSTIN</label>
                        <input v-model="form.gstin" :disabled="isApproved" class="heyd2c-input font-mono uppercase" placeholder="22AAAAA0000A1Z5" maxlength="15" />
                    </div>
                </div>
                <div>
                    <label class="heyd2c-label">PAN Number</label>
                    <input v-model="form.pan" :disabled="isApproved" class="heyd2c-input font-mono uppercase" placeholder="AAAAA0000A" maxlength="10" />
                </div>
            </div>

            <!-- Address -->
            <div class="card space-y-3">
                <h3 class="text-[13px] font-semibold text-white">Registered Address</h3>
                <div>
                    <label class="heyd2c-label">Address Line 1 *</label>
                    <input v-model="form.address_line1" :disabled="isApproved" class="heyd2c-input" placeholder="Building, Street" />
                    <p v-if="form.errors.address_line1" class="text-rose-400 text-[11px] mt-1">{{ form.errors.address_line1 }}</p>
                </div>
                <div>
                    <label class="heyd2c-label">Address Line 2</label>
                    <input v-model="form.address_line2" :disabled="isApproved" class="heyd2c-input" placeholder="Area, Landmark" />
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="heyd2c-label">City *</label>
                        <input v-model="form.city" :disabled="isApproved" class="heyd2c-input" />
                        <p v-if="form.errors.city" class="text-rose-400 text-[11px] mt-1">{{ form.errors.city }}</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">State *</label>
                        <select v-model="form.state" :disabled="isApproved" class="heyd2c-input">
                            <option value="">Select…</option>
                            <option v-for="s in indianStates" :key="s" :value="s">{{ s }}</option>
                        </select>
                        <p v-if="form.errors.state" class="text-rose-400 text-[11px] mt-1">{{ form.errors.state }}</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">Pincode *</label>
                        <input v-model="form.pincode" :disabled="isApproved" class="heyd2c-input font-mono" maxlength="6" />
                        <p v-if="form.errors.pincode" class="text-rose-400 text-[11px] mt-1">{{ form.errors.pincode }}</p>
                    </div>
                </div>
            </div>

            <!-- Bank Details -->
            <div class="card space-y-3">
                <h3 class="text-[13px] font-semibold text-white">Bank Account Details</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="heyd2c-label">Account Holder Name *</label>
                        <input v-model="form.bank_account_name" :disabled="isApproved" class="heyd2c-input" />
                        <p v-if="form.errors.bank_account_name" class="text-rose-400 text-[11px] mt-1">{{ form.errors.bank_account_name }}</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">Account Number *</label>
                        <input v-model="form.bank_account_number" :disabled="isApproved" class="heyd2c-input font-mono" />
                        <p v-if="form.errors.bank_account_number" class="text-rose-400 text-[11px] mt-1">{{ form.errors.bank_account_number }}</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">IFSC Code *</label>
                        <input v-model="form.bank_ifsc" :disabled="isApproved" class="heyd2c-input font-mono uppercase" maxlength="11" />
                        <p v-if="form.errors.bank_ifsc" class="text-rose-400 text-[11px] mt-1">{{ form.errors.bank_ifsc }}</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">Bank Name *</label>
                        <input v-model="form.bank_name" :disabled="isApproved" class="heyd2c-input" />
                        <p v-if="form.errors.bank_name" class="text-rose-400 text-[11px] mt-1">{{ form.errors.bank_name }}</p>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="card space-y-3">
                <h3 class="text-[13px] font-semibold text-white">Supporting Documents</h3>
                <p class="text-[11px] text-ink-3">Upload GST certificate, PAN card, cancelled cheque, or any other business documents. JPG, PNG, or PDF up to 5MB each.</p>

                <!-- Existing uploaded docs -->
                <div v-if="existingDocs.length" class="space-y-2">
                    <div v-for="doc in existingDocs" :key="doc.url"
                        class="flex items-center justify-between bg-frost-1 rounded-xl px-3 py-2">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <FileText :size="14" class="text-brand-400 shrink-0" />
                            <span class="text-[12px] text-white truncate">{{ doc.name }}</span>
                            <span class="text-[10px] text-ink-3 shrink-0">{{ new Date(doc.uploaded_at).toLocaleDateString('en-IN') }}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 ml-2">
                            <a :href="doc.url" target="_blank" class="text-ink-3 hover:text-white cursor-pointer transition">
                                <Eye :size="13" />
                            </a>
                            <button v-if="canEdit || isRejected" type="button" @click="deleteExistingDoc(doc.url)"
                                class="text-ink-3 hover:text-rose-400 cursor-pointer transition">
                                <Trash2 :size="13" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Upload new docs -->
                <div v-if="!isApproved"
                    class="border-2 border-dashed rounded-xl p-5 text-center transition cursor-pointer"
                    :class="dragOver ? 'border-brand-400 bg-brand-600/5' : 'border-frost-2'"
                    @dragover.prevent="dragOver = true"
                    @dragleave="dragOver = false"
                    @drop.prevent="onDrop">
                    <Upload :size="20" class="mx-auto text-ink-3 mb-2" />
                    <p class="text-[12px] text-ink-2 mb-2">Drag & drop files or click to browse</p>
                    <label class="btn btn-ghost btn-sm cursor-pointer">
                        Choose Files
                        <input type="file" multiple accept=".jpg,.jpeg,.png,.pdf" class="hidden" @change="onFileChange" />
                    </label>
                </div>

                <!-- New files preview -->
                <div v-if="newFileNames.length" class="space-y-1.5">
                    <div v-for="(name, i) in newFileNames" :key="i"
                        class="flex items-center justify-between bg-brand-600/10 border border-brand-700/30 rounded-xl px-3 py-2">
                        <div class="flex items-center gap-2">
                            <FileText :size="13" class="text-brand-400" />
                            <span class="text-[12px] text-white">{{ name }}</span>
                            <span class="text-[10px] text-emerald-400">New</span>
                        </div>
                        <button type="button" @click="removeNewFile(i)" class="text-ink-3 hover:text-rose-400 cursor-pointer">
                            <Trash2 :size="12" />
                        </button>
                    </div>
                </div>
            </div>

            <button v-if="canEdit || isRejected" type="submit" :disabled="form.processing"
                class="btn btn-primary w-full py-3 text-[14px] cursor-pointer disabled:opacity-50">
                {{ form.processing ? 'Submitting…' : (isRejected ? 'Resubmit KYC' : 'Submit for Verification') }}
            </button>
        </form>
    </div>
</TenantLayout>
</template>
