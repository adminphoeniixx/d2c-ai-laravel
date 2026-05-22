<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Edit, Trash2, HardHat, FileText, FileSignature } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    worker: { type: Object, required: true },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const w = props.worker;
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('hi-IN', { day: '2-digit', month: 'long', year: 'numeric' }) : '—';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));

const statusMap = {
    active: { class: 'pill-good', label: 'कार्यरत (Active)' },
    terminated: { class: 'pill-bad', label: 'निष्कासित (Terminated)' },
    absconded: { class: 'pill-bad', label: 'फरार (Absconded)' },
    completed: { class: 'pill-info', label: 'पूर्ण (Completed)' },
};

const typeMap = { temporary: 'अस्थाई', permanent: 'स्थाई', contract: 'ठेका' };
const payMap = { daily: 'दैनिक', monthly: 'मासिक', piece_rate: 'उत्तरी दर' };

function deleteWorker() {
    if (confirm('क्या आप इस श्रमिक को हटाना चाहते हैं?')) {
        router.delete(route('tenant.hr.workers.destroy', { tenant: slug, worker: w.id }));
    }
}
</script>

<template>
<Head :title="w.name + ' — श्रमिक'" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <Link :href="route('tenant.hr.workers', { tenant: slug })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> वापस</Link>
            <div>
                <h2 class="text-[20px] font-bold text-white flex items-center gap-2"><HardHat :size="20" class="text-brand-400" /> {{ w.name }}</h2>
                <p class="text-[12px] text-ink-3">{{ w.worker_id }} · {{ typeMap[w.appointment_type] || w.appointment_type }} · <span class="pill" :class="(statusMap[w.status] || statusMap.active).class">{{ (statusMap[w.status] || statusMap.active).label }}</span></p>
            </div>
        </div>
        <div class="flex gap-2">
            <Link :href="route('tenant.hr.letters.create', { tenant: slug }) + '?worker_id=' + w.id" class="btn btn-ghost"><FileText :size="14" /> Bio-Data</Link>
            <Link :href="route('tenant.hr.letters.create', { tenant: slug }) + '?worker_id=' + w.id" class="btn btn-ghost"><FileSignature :size="14" /> नियुक्ति पत्र</Link>
            <Link :href="route('tenant.hr.workers.edit', { tenant: slug, worker: w.id })" class="btn btn-ghost"><Edit :size="14" /> संपादित करें</Link>
            <button class="btn btn-ghost text-rose" @click="deleteWorker"><Trash2 :size="14" /></button>
        </div>
    </div>

    <div class="max-w-3xl space-y-5">
        <!-- व्यक्तिगत विवरण -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">व्यक्तिगत विवरण (Personal Details)</h3>
            <div class="grid grid-cols-2 gap-y-3 gap-x-6 text-[13px]">
                <div><span class="pulsara-label">नाम (Name)</span><div class="text-white font-semibold">{{ w.name }}</div></div>
                <div><span class="pulsara-label">पिता/पति (Father/Husband)</span><div class="text-ink">{{ w.father_husband_name || '—' }}</div></div>
                <div><span class="pulsara-label">जन्म तिथि (DOB)</span><div class="text-ink">{{ dateFmt(w.date_of_birth) }}</div></div>
                <div><span class="pulsara-label">आयु (Age)</span><div class="text-ink">{{ w.age || '—' }}</div></div>
                <div class="col-span-2"><span class="pulsara-label">स्थाई पता (Permanent Address)</span><div class="text-ink">{{ w.permanent_address || '—' }}</div></div>
                <div class="col-span-2"><span class="pulsara-label">स्थानीय पता (Local Address)</span><div class="text-ink">{{ w.local_address || '—' }}</div></div>
                <div><span class="pulsara-label">शैक्षणिक अहर्ता (Education)</span><div class="text-ink">{{ w.education || '—' }}</div></div>
                <div><span class="pulsara-label">तकनीकी अहर्ता (Technical)</span><div class="text-ink">{{ w.technical_qualification || '—' }}</div></div>
                <div><span class="pulsara-label">भाषाएं (Languages)</span><div class="text-ink">{{ w.languages || '—' }}</div></div>
            </div>
        </div>

        <!-- पहचान दस्तावेज़ -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">पहचान दस्तावेज़ (Identity)</h3>
            <div class="grid grid-cols-2 gap-y-3 gap-x-6 text-[13px]">
                <div><span class="pulsara-label">Mobile</span><div class="text-ink font-mono">{{ w.mobile || '—' }}</div></div>
                <div><span class="pulsara-label">PAN</span><div class="text-ink font-mono">{{ w.pan_number || '—' }}</div></div>
                <div><span class="pulsara-label">Aadhaar</span><div class="text-ink font-mono">{{ w.aadhaar_number || '—' }}</div></div>
                <div><span class="pulsara-label">PF / UAN</span><div class="text-ink font-mono">{{ w.pf_uan || '—' }}</div></div>
            </div>
        </div>

        <!-- नियुक्ति और वेतन -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">नियुक्ति एवं वेतन (Appointment & Wages)</h3>
            <div class="grid grid-cols-2 gap-y-3 gap-x-6 text-[13px]">
                <div><span class="pulsara-label">अभ्यर्थिक पद (Post Applied)</span><div class="text-ink">{{ w.post_applied || '—' }}</div></div>
                <div><span class="pulsara-label">भारित पद (Post Held)</span><div class="text-ink">{{ w.post_held || '—' }}</div></div>
                <div><span class="pulsara-label">नियुक्ति प्रकार</span><div class="text-ink">{{ typeMap[w.appointment_type] || w.appointment_type }}</div></div>
                <div><span class="pulsara-label">भुगतान प्रकार</span><div class="text-ink">{{ payMap[w.payment_mode] || w.payment_mode }}</div></div>
                <div><span class="pulsara-label">नियुक्ति से (From)</span><div class="text-ink">{{ dateFmt(w.appointment_from) }}</div></div>
                <div><span class="pulsara-label">नियुक्ति तक (To)</span><div class="text-ink">{{ dateFmt(w.appointment_to) }}</div></div>
                <div><span class="pulsara-label">दैनिक वेतन (Daily)</span><div class="text-ink font-mono">{{ fmt(w.daily_wage) }}</div></div>
                <div><span class="pulsara-label">मासिक वेतन (Monthly)</span><div class="text-ink font-mono">{{ fmt(w.monthly_wage) }}</div></div>
            </div>
        </div>

        <!-- PF / ESI -->
        <div class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">PF / ESI</h3>
            <div class="grid grid-cols-2 gap-y-3 gap-x-6 text-[13px]">
                <div><span class="pulsara-label">PF लागू</span><div class="text-ink">{{ w.pf_applicable ? 'हाँ (Yes)' : 'नहीं (No)' }}</div></div>
                <div><span class="pulsara-label">ESI लागू</span><div class="text-ink">{{ w.esi_applicable ? 'हाँ (Yes)' : 'नहीं (No)' }}</div></div>
                <div v-if="w.pf_number"><span class="pulsara-label">PF Number</span><div class="text-ink font-mono">{{ w.pf_number }}</div></div>
                <div v-if="w.esi_number"><span class="pulsara-label">ESI Number</span><div class="text-ink font-mono">{{ w.esi_number }}</div></div>
            </div>
        </div>

        <!-- अनुभव (Experience) -->
        <div v-if="w.experience?.length" class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">अनुभव (Experience)</h3>
            <div v-for="(exp, i) in w.experience" :key="i" class="p-3 rounded-lg bg-bg-3 border border-frost-1 mb-3">
                <div class="text-[11px] font-mono text-ink-3 mb-1">{{ i + 1 }}.</div>
                <div class="grid grid-cols-2 gap-2 text-[12px]">
                    <div><span class="text-ink-3">नियोक्ता:</span> <span class="text-ink">{{ exp.employer || '—' }}</span></div>
                    <div><span class="text-ink-3">पद:</span> <span class="text-ink">{{ exp.post || '—' }}</span></div>
                    <div><span class="text-ink-3">अवधि:</span> <span class="text-ink">{{ exp.from || '?' }} — {{ exp.to || '?' }}</span></div>
                    <div><span class="text-ink-3">वेतन:</span> <span class="text-ink">{{ exp.salary || '—' }}</span></div>
                    <div class="col-span-2"><span class="text-ink-3">छोड़ने का कारण:</span> <span class="text-ink">{{ exp.reason_leaving || '—' }}</span></div>
                </div>
            </div>
        </div>

        <!-- सन्दर्भ (References) -->
        <div v-if="w.references?.length" class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">सन्दर्भ (References)</h3>
            <div v-for="(ref2, i) in w.references" :key="i" class="grid grid-cols-3 gap-3 text-[13px] mb-2">
                <div><span class="pulsara-label">नाम</span><div class="text-ink">{{ ref2.name || '—' }}</div></div>
                <div><span class="pulsara-label">पता</span><div class="text-ink">{{ ref2.address || '—' }}</div></div>
                <div><span class="pulsara-label">पद</span><div class="text-ink">{{ ref2.designation || '—' }}</div></div>
            </div>
        </div>

        <!-- बैंक -->
        <div v-if="w.bank_name || w.bank_account_number" class="card">
            <h3 class="text-[15px] font-bold text-white mb-4">बैंक विवरण (Bank)</h3>
            <div class="grid grid-cols-3 gap-3 text-[13px]">
                <div><span class="pulsara-label">बैंक</span><div class="text-ink">{{ w.bank_name || '—' }}</div></div>
                <div><span class="pulsara-label">खाता</span><div class="text-ink font-mono">{{ w.bank_account_number || '—' }}</div></div>
                <div><span class="pulsara-label">IFSC</span><div class="text-ink font-mono">{{ w.bank_ifsc || '—' }}</div></div>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
