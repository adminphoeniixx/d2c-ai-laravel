<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    worker: { type: Object, required: true },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const w = props.worker;

const form = useForm({
    name: w.name || '',
    father_husband_name: w.father_husband_name || '',
    date_of_birth: w.date_of_birth?.split('T')[0] || '',
    age: w.age || '',
    permanent_address: w.permanent_address || '',
    local_address: w.local_address || '',
    education: w.education || '',
    technical_qualification: w.technical_qualification || '',
    languages: w.languages || '',
    mobile: w.mobile || '',
    pan_number: w.pan_number || '',
    aadhaar_number: w.aadhaar_number || '',
    pf_uan: w.pf_uan || '',
    post_applied: w.post_applied || '',
    post_held: w.post_held || '',
    appointment_from: w.appointment_from?.split('T')[0] || '',
    appointment_to: w.appointment_to?.split('T')[0] || '',
    appointment_type: w.appointment_type || 'temporary',
    daily_wage: w.daily_wage || '',
    monthly_wage: w.monthly_wage || '',
    payment_mode: w.payment_mode || 'daily',
    pf_applicable: w.pf_applicable ?? false,
    esi_applicable: w.esi_applicable ?? false,
    pf_number: w.pf_number || '',
    esi_number: w.esi_number || '',
    experience: w.experience || [],
    references: w.references?.length ? w.references : [{ name: '', address: '', designation: '' }, { name: '', address: '', designation: '' }],
    status: w.status || 'active',
    date_of_leaving: w.date_of_leaving?.split('T')[0] || '',
    reason_leaving: w.reason_leaving || '',
    bank_name: w.bank_name || '',
    bank_account_number: w.bank_account_number || '',
    bank_ifsc: w.bank_ifsc || '',
    notes: w.notes || '',
});

function addExperience() { form.experience.push({ employer: '', post: '', from: '', to: '', salary: '', reason_leaving: '' }); }
function removeExperience(i) { form.experience.splice(i, 1); }

function submit() {
    form.put(route('tenant.hr.workers.update', { tenant: slug, worker: w.id }));
}
</script>

<template>
<Head :title="'संपादित — ' + w.name" />
<TenantLayout>
    <div class="flex items-center gap-3 mb-5">
        <Link :href="route('tenant.hr.workers.show', { tenant: slug, worker: w.id })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> वापस</Link>
        <div>
            <h2 class="text-[20px] font-bold text-white">श्रमिक संपादित करें</h2>
            <p class="text-[12px] text-ink-3">{{ w.worker_id }} · {{ w.name }}</p>
        </div>
    </div>

    <form @submit.prevent="submit" class="max-w-3xl space-y-5">

        <!-- पहचान -->
        <div class="card">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Mobile No.</label>
                    <input v-model="form.mobile" class="heyd2c-input font-mono" />
                </div>
                <div>
                    <label class="heyd2c-label">PAN No.</label>
                    <input v-model="form.pan_number" class="heyd2c-input font-mono" maxlength="10" />
                </div>
                <div>
                    <label class="heyd2c-label">Aadhar Card No.</label>
                    <input v-model="form.aadhaar_number" class="heyd2c-input font-mono" maxlength="12" />
                </div>
                <div>
                    <label class="heyd2c-label">PF No. / UAN</label>
                    <input v-model="form.pf_uan" class="heyd2c-input font-mono" />
                </div>
            </div>
        </div>

        <!-- व्यक्तिगत -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">व्यक्तिगत विवरण</h3>
            <div class="space-y-3">
                <div>
                    <label class="heyd2c-label">नाम (Name) *</label>
                    <input v-model="form.name" class="heyd2c-input uppercase" />
                    <div v-if="form.errors.name" class="mt-1 text-[11px] text-rose">{{ form.errors.name }}</div>
                </div>
                <div>
                    <label class="heyd2c-label">पिता/पति का नाम</label>
                    <input v-model="form.father_husband_name" class="heyd2c-input" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">जन्म तिथि</label><input v-model="form.date_of_birth" type="date" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">आयु</label><input v-model="form.age" type="number" class="heyd2c-input" /></div>
                </div>
                <div><label class="heyd2c-label">स्थाई पता</label><textarea v-model="form.permanent_address" class="heyd2c-input" rows="2"></textarea></div>
                <div><label class="heyd2c-label">स्थानीय पता</label><textarea v-model="form.local_address" class="heyd2c-input" rows="2"></textarea></div>
                <div><label class="heyd2c-label">शैक्षणिक अहर्ता</label><input v-model="form.education" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">तकनीकी अहर्ता</label><input v-model="form.technical_qualification" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">भाषाएं</label><input v-model="form.languages" class="heyd2c-input" /></div>
            </div>
        </div>

        <!-- अनुभव -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[15px] font-bold text-white">अनुभव (Experience)</h3>
                <button type="button" class="btn btn-ghost btn-sm" @click="addExperience"><Plus :size="14" /> जोड़ें</button>
            </div>
            <div v-for="(exp, i) in form.experience" :key="i" class="mb-4 p-3 rounded-lg bg-bg-3 border border-frost-1">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-[11px] font-mono text-ink-3">{{ i + 1 }}.</span>
                    <button type="button" class="text-rose cursor-pointer" @click="removeExperience(i)"><Trash2 :size="14" /></button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2"><label class="heyd2c-label">नियोक्ता</label><input v-model="exp.employer" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">पद</label><input v-model="exp.post" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">वेतन</label><input v-model="exp.salary" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">से</label><input v-model="exp.from" type="date" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">तक</label><input v-model="exp.to" type="date" class="heyd2c-input" /></div>
                    <div class="col-span-2"><label class="heyd2c-label">छोड़ने का कारण</label><input v-model="exp.reason_leaving" class="heyd2c-input" /></div>
                </div>
            </div>
        </div>

        <!-- सन्दर्भ -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">सन्दर्भ (References)</h3>
            <div v-for="(ref2, i) in form.references" :key="i" class="mb-3">
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="heyd2c-label">नाम</label><input v-model="ref2.name" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">पता</label><input v-model="ref2.address" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">पद</label><input v-model="ref2.designation" class="heyd2c-input" /></div>
                </div>
            </div>
        </div>

        <!-- नियुक्ति -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">नियुक्ति विवरण</h3>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="heyd2c-label">प्रकार</label>
                    <select v-model="form.appointment_type" class="heyd2c-input">
                        <option value="temporary">अस्थाई</option><option value="permanent">स्थाई</option><option value="contract">ठेका</option>
                    </select>
                </div>
                <div><label class="heyd2c-label">भारित पद</label><input v-model="form.post_held" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">से</label><input v-model="form.appointment_from" type="date" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">तक</label><input v-model="form.appointment_to" type="date" class="heyd2c-input" /></div>
            </div>
        </div>

        <!-- वेतन -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">वेतन</h3>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="heyd2c-label">भुगतान</label>
                    <select v-model="form.payment_mode" class="heyd2c-input">
                        <option value="daily">दैनिक</option><option value="monthly">मासिक</option><option value="piece_rate">उत्तरी दर</option>
                    </select>
                </div>
                <div></div>
                <div><label class="heyd2c-label">दैनिक ₹</label><input v-model="form.daily_wage" type="number" step="0.01" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">मासिक ₹</label><input v-model="form.monthly_wage" type="number" step="0.01" class="heyd2c-input" /></div>
            </div>
        </div>

        <!-- PF/ESI -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">PF / ESI</h3>
            <div class="flex items-center gap-6 mb-3">
                <label class="flex items-center gap-2 text-[13px] text-ink-2 cursor-pointer select-none">
                    <input v-model="form.pf_applicable" type="checkbox" class="accent-brand-600" /> PF लागू
                </label>
                <label class="flex items-center gap-2 text-[13px] text-ink-2 cursor-pointer select-none">
                    <input v-model="form.esi_applicable" type="checkbox" class="accent-brand-600" /> ESI लागू
                </label>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div v-if="form.pf_applicable"><label class="heyd2c-label">PF No.</label><input v-model="form.pf_number" class="heyd2c-input font-mono" /></div>
                <div v-if="form.esi_applicable"><label class="heyd2c-label">ESI No.</label><input v-model="form.esi_number" class="heyd2c-input font-mono" /></div>
            </div>
        </div>

        <!-- स्थिति -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">स्थिति (Status)</h3>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="heyd2c-label">स्थिति</label>
                    <select v-model="form.status" class="heyd2c-input">
                        <option value="active">कार्यरत</option><option value="terminated">निष्कासित</option><option value="absconded">फरार</option><option value="completed">पूर्ण</option>
                    </select>
                </div>
                <div><label class="heyd2c-label">छोड़ने की तिथि</label><input v-model="form.date_of_leaving" type="date" class="heyd2c-input" /></div>
                <div class="col-span-2"><label class="heyd2c-label">छोड़ने का कारण</label><input v-model="form.reason_leaving" class="heyd2c-input" /></div>
            </div>
        </div>

        <!-- बैंक -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">बैंक</h3>
            <div class="grid grid-cols-3 gap-3">
                <div><label class="heyd2c-label">बैंक</label><input v-model="form.bank_name" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">खाता</label><input v-model="form.bank_account_number" class="heyd2c-input font-mono" /></div>
                <div><label class="heyd2c-label">IFSC</label><input v-model="form.bank_ifsc" class="heyd2c-input font-mono" maxlength="11" /></div>
            </div>
        </div>

        <div><label class="heyd2c-label">टिप्पणी</label><textarea v-model="form.notes" class="heyd2c-input" rows="2"></textarea></div>

        <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
            {{ form.processing ? 'सहेजा जा रहा है…' : 'अपडेट करें (Update Worker)' }}
        </button>
    </form>
</TenantLayout>
</template>
