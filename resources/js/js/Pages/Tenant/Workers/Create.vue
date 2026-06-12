<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    nextWorkerId: { type: String, default: 'WRK-001' },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const form = useForm({
    worker_id: props.nextWorkerId,
    // व्यक्तिगत विवरण (Personal Details)
    name: '',
    father_husband_name: '',
    date_of_birth: '',
    age: '',
    permanent_address: '',
    local_address: '',
    education: '',
    technical_qualification: '',
    languages: '',
    // पहचान दस्तावेज़ (Identity)
    mobile: '',
    pan_number: '',
    aadhaar_number: '',
    pf_uan: '',
    // नियुक्ति (Appointment)
    post_applied: '',
    post_held: '',
    appointment_from: '',
    appointment_to: '',
    appointment_type: 'temporary',
    // वेतन (Compensation)
    daily_wage: '',
    monthly_wage: '',
    payment_mode: 'daily',
    // PF/ESI
    pf_applicable: false,
    esi_applicable: false,
    pf_number: '',
    esi_number: '',
    // अनुभव (Experience)
    experience: [],
    // सन्दर्भ (References)
    references: [{ name: '', address: '', designation: '' }, { name: '', address: '', designation: '' }],
    // बैंक (Bank)
    bank_name: '',
    bank_account_number: '',
    bank_ifsc: '',
    notes: '',
});

function addExperience() {
    form.experience.push({ employer: '', post: '', from: '', to: '', salary: '', reason_leaving: '' });
}
function removeExperience(i) {
    form.experience.splice(i, 1);
}

function submit() {
    form.post(route('tenant.hr.workers.store', { tenant: slug }));
}
</script>

<template>
<Head title="नया श्रमिक — Bio-Data Form" />
<TenantLayout>
    <div class="flex items-center gap-3 mb-5">
        <Link :href="route('tenant.hr.workers', { tenant: slug })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> वापस</Link>
        <div>
            <h2 class="text-[20px] font-bold text-white">व्यक्तिगत विवरण फार्म</h2>
            <p class="text-[12px] text-ink-3">BIO-DATA FORM · नया श्रमिक जोड़ें</p>
        </div>
    </div>

    <form @submit.prevent="submit" class="max-w-3xl space-y-5">

        <!-- पहचान दस्तावेज़ (Identity Documents) — top box like the PDF -->
        <div class="card">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Worker ID</label>
                    <input v-model="form.worker_id" class="heyd2c-input font-mono" />
                    <div v-if="form.errors.worker_id" class="mt-1 text-[11px] text-rose">{{ form.errors.worker_id }}</div>
                </div>
                <div>
                    <label class="heyd2c-label">Mobile No.</label>
                    <input v-model="form.mobile" class="heyd2c-input font-mono" placeholder="9876543210" />
                </div>
                <div>
                    <label class="heyd2c-label">PAN No.</label>
                    <input v-model="form.pan_number" class="heyd2c-input font-mono" maxlength="10" placeholder="ABCDE1234F" />
                </div>
                <div>
                    <label class="heyd2c-label">Aadhar Card No. (आधार)</label>
                    <input v-model="form.aadhaar_number" class="heyd2c-input font-mono" maxlength="12" placeholder="123456789012" />
                </div>
                <div>
                    <label class="heyd2c-label">PF No. / UAN</label>
                    <input v-model="form.pf_uan" class="heyd2c-input font-mono" />
                </div>
                <div>
                    <label class="heyd2c-label">अभ्यर्थिक पद (Post Applied for)</label>
                    <input v-model="form.post_applied" class="heyd2c-input" placeholder="Helper / Operator / Labour" />
                </div>
            </div>
        </div>

        <!-- 1-8 व्यक्तिगत विवरण (Personal Details) -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">व्यक्तिगत विवरण (Personal Details)</h3>
            <div class="space-y-3">
                <div>
                    <label class="heyd2c-label">1. नाम साफ अक्षरो मे (Name in Capital Letters) *</label>
                    <input v-model="form.name" class="heyd2c-input uppercase" placeholder="FULL NAME" />
                    <div v-if="form.errors.name" class="mt-1 text-[11px] text-rose">{{ form.errors.name }}</div>
                </div>
                <div>
                    <label class="heyd2c-label">2. पिता/पति का नाम (Father's/Husband's Name)</label>
                    <input v-model="form.father_husband_name" class="heyd2c-input" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="heyd2c-label">3. जन्म तिथि (Date of Birth)</label>
                        <input v-model="form.date_of_birth" type="date" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">3(a) आयु (Age)</label>
                        <input v-model="form.age" type="number" min="14" max="100" class="heyd2c-input" />
                    </div>
                </div>
                <div>
                    <label class="heyd2c-label">4. स्थाई पता (Permanent Address)</label>
                    <textarea v-model="form.permanent_address" class="heyd2c-input" rows="2"></textarea>
                </div>
                <div>
                    <label class="heyd2c-label">5. स्थानीय पता (Local Address)</label>
                    <textarea v-model="form.local_address" class="heyd2c-input" rows="2"></textarea>
                </div>
                <div>
                    <label class="heyd2c-label">6. शैक्षणिक अहर्ताएं (Educational Qualifications)</label>
                    <input v-model="form.education" class="heyd2c-input" placeholder="8th Pass / 10th Pass / ITI" />
                </div>
                <div>
                    <label class="heyd2c-label">7. तकनीकी अहर्ताएं (Technical Qualifications)</label>
                    <input v-model="form.technical_qualification" class="heyd2c-input" placeholder="Welding / Electrical / None" />
                </div>
                <div>
                    <label class="heyd2c-label">8. भाषाएं — लिखना एवं पढ़ना (Languages Read & Write)</label>
                    <input v-model="form.languages" class="heyd2c-input" placeholder="हिन्दी, English, मराठी" />
                </div>
            </div>
        </div>

        <!-- 9. अनुभव (Experience) -->
        <div class="card">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[15px] font-bold text-white">9. अनुभव (Experience)</h3>
                <button type="button" class="btn btn-ghost btn-sm" @click="addExperience"><Plus :size="14" /> जोड़ें</button>
            </div>
            <div v-if="!form.experience.length" class="text-[13px] text-ink-3 text-center py-4">कोई अनुभव नहीं (No experience added)</div>
            <div v-for="(exp, i) in form.experience" :key="i" class="mb-4 p-3 rounded-lg bg-bg-3 border border-frost-1">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-[11px] font-mono text-ink-3">क्रमांक {{ i + 1 }}</span>
                    <button type="button" class="text-rose hover:text-rose/80 cursor-pointer" @click="removeExperience(i)"><Trash2 :size="14" /></button>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="heyd2c-label">नियोक्ता का नाम व पता (Name of Employer & Address)</label>
                        <input v-model="exp.employer" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">भारित पद (Post held)</label>
                        <input v-model="exp.post" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">प्राप्त वेतन (Salary Drawn)</label>
                        <input v-model="exp.salary" class="heyd2c-input" placeholder="₹" />
                    </div>
                    <div>
                        <label class="heyd2c-label">अवधि से (From)</label>
                        <input v-model="exp.from" type="date" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">अवधि तक (To)</label>
                        <input v-model="exp.to" type="date" class="heyd2c-input" />
                    </div>
                    <div class="col-span-2">
                        <label class="heyd2c-label">छोड़ने का कारण (Reason for Leaving)</label>
                        <input v-model="exp.reason_leaving" class="heyd2c-input" />
                    </div>
                </div>
            </div>
        </div>

        <!-- 10. सन्दर्भ (References) -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">10. दो परिचित व्यक्तियों का हवाला (References of Two known persons)</h3>
            <div v-for="(ref2, i) in form.references" :key="i" class="mb-3">
                <div class="text-[11px] font-mono text-ink-3 mb-2">{{ i + 1 }}.</div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="heyd2c-label">नाम (Name)</label>
                        <input v-model="ref2.name" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">पता (Address)</label>
                        <input v-model="ref2.address" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">पद (Designation)</label>
                        <input v-model="ref2.designation" class="heyd2c-input" />
                    </div>
                </div>
            </div>
        </div>

        <!-- नियुक्ति विवरण (Appointment Details) -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">नियुक्ति विवरण (Appointment Details)</h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">नियुक्ति प्रकार (Type)</label>
                    <select v-model="form.appointment_type" class="heyd2c-input">
                        <option value="temporary">अस्थाई (Temporary)</option>
                        <option value="permanent">स्थाई (Permanent)</option>
                        <option value="contract">ठेका (Contract)</option>
                    </select>
                </div>
                <div>
                    <label class="heyd2c-label">भारित पद (Post held / assigned)</label>
                    <input v-model="form.post_held" class="heyd2c-input" placeholder="Helper / Operator" />
                </div>
                <div>
                    <label class="heyd2c-label">नियुक्ति दिनांक से (From)</label>
                    <input v-model="form.appointment_from" type="date" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">नियुक्ति दिनांक तक (To)</label>
                    <input v-model="form.appointment_to" type="date" class="heyd2c-input" />
                </div>
            </div>
        </div>

        <!-- वेतन (Compensation) -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">वेतन विवरण (Compensation)</h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">भुगतान प्रकार (Payment Mode)</label>
                    <select v-model="form.payment_mode" class="heyd2c-input">
                        <option value="daily">दैनिक (Daily)</option>
                        <option value="monthly">मासिक (Monthly)</option>
                        <option value="piece_rate">उत्तरी दर (Piece Rate)</option>
                    </select>
                </div>
                <div></div>
                <div>
                    <label class="heyd2c-label">दैनिक वेतन (Daily Wage) ₹</label>
                    <input v-model="form.daily_wage" type="number" step="0.01" class="heyd2c-input" placeholder="500" />
                </div>
                <div>
                    <label class="heyd2c-label">मासिक वेतन (Monthly Wage) ₹</label>
                    <input v-model="form.monthly_wage" type="number" step="0.01" class="heyd2c-input" placeholder="15000" />
                </div>
            </div>
        </div>

        <!-- PF / ESI -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">PF / ESI</h3>
            <div class="flex items-center gap-6 mb-3">
                <label class="flex items-center gap-2 text-[13px] text-ink-2 cursor-pointer select-none">
                    <input v-model="form.pf_applicable" type="checkbox" class="accent-brand-600" />
                    PF लागू (PF Applicable)
                </label>
                <label class="flex items-center gap-2 text-[13px] text-ink-2 cursor-pointer select-none">
                    <input v-model="form.esi_applicable" type="checkbox" class="accent-brand-600" />
                    ESI लागू (ESI Applicable)
                </label>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div v-if="form.pf_applicable">
                    <label class="heyd2c-label">PF Number</label>
                    <input v-model="form.pf_number" class="heyd2c-input font-mono" />
                </div>
                <div v-if="form.esi_applicable">
                    <label class="heyd2c-label">ESI Number</label>
                    <input v-model="form.esi_number" class="heyd2c-input font-mono" />
                </div>
            </div>
        </div>

        <!-- बैंक विवरण (Bank Details) -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">बैंक विवरण (Bank Details)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="heyd2c-label">बैंक का नाम (Bank Name)</label>
                    <input v-model="form.bank_name" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">खाता संख्या (Account No.)</label>
                    <input v-model="form.bank_account_number" class="heyd2c-input font-mono" />
                </div>
                <div>
                    <label class="heyd2c-label">IFSC Code</label>
                    <input v-model="form.bank_ifsc" class="heyd2c-input font-mono" maxlength="11" />
                </div>
            </div>
        </div>

        <!-- टिप्पणी (Notes) -->
        <div>
            <label class="heyd2c-label">टिप्पणी (Notes)</label>
            <textarea v-model="form.notes" class="heyd2c-input" rows="2"></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
            {{ form.processing ? 'सहेजा जा रहा है…' : 'श्रमिक जोड़ें (Add Worker)' }}
        </button>
    </form>
</TenantLayout>
</template>
