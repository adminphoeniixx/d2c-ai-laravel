<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Search, UserPlus, HardHat, UserCheck } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    workers: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    totals: { type: Object, default: () => ({ total: 0, active: 0 }) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const q = ref(props.filters.q || '');

function search() {
    router.get(route('tenant.hr.workers', { tenant: slug }), { q: q.value }, { preserveState: true });
}
function filterStatus(s) {
    router.get(route('tenant.hr.workers', { tenant: slug }), { q: q.value, status: s }, { preserveState: true });
}

const statusMap = {
    active: { class: 'pill-good', label: 'कार्यरत' },
    terminated: { class: 'pill-bad', label: 'निष्कासित' },
    absconded: { class: 'pill-bad', label: 'फरार' },
    completed: { class: 'pill-info', label: 'पूर्ण' },
};

const dateFmt = (d) => d ? new Date(d).toLocaleDateString('hi-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
</script>

<template>
<Head title="श्रमिक (Workers)" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">श्रमिक (Workers)</h2>
            <p class="text-[12px] text-ink-3 mt-1">कर्मचारी / श्रमिक प्रबंधन · Worker Management</p>
        </div>
        <Link :href="route('tenant.hr.workers.create', { tenant: slug })" class="btn btn-primary"><UserPlus :size="15" /> नया श्रमिक</Link>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-5">
        <KpiCard label="कुल श्रमिक (Total)" :value="totals.total" format="number" />
        <KpiCard label="कार्यरत (Active)" :value="totals.active" format="number" />
    </div>

    <!-- Search -->
    <div class="card mb-5 flex items-center gap-3">
        <Search :size="16" class="text-ink-3" />
        <input v-model="q" class="bg-transparent flex-1 text-[14px] text-white outline-none placeholder:text-ink-3" placeholder="नाम से खोजें / Search by name…" @keyup.enter="search" />
        <div class="flex gap-1">
            <button v-for="s in ['', 'active', 'terminated', 'completed']" :key="s"
                class="px-2.5 py-1 text-[11px] font-mono rounded-full cursor-pointer"
                :class="(filters.status || '') === s ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'"
                @click="filterStatus(s)">{{ s || 'सभी' }}</button>
        </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">ID</th>
                    <th class="text-left px-5 py-3">नाम (Name)</th>
                    <th class="text-left px-5 py-3">पिता/पति (Father/Husband)</th>
                    <th class="text-left px-5 py-3">पद (Post)</th>
                    <th class="text-left px-5 py-3">प्रकार (Type)</th>
                    <th class="text-right px-5 py-3">वेतन (Wage)</th>
                    <th class="text-left px-5 py-3">स्थिति (Status)</th>
                    <th class="text-left px-5 py-3">नियुक्ति (Joining)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="w in workers.data" :key="w.id" class="hover:bg-brand-600/5 transition cursor-pointer" @click="router.get(route('tenant.hr.workers.show', { tenant: slug, worker: w.id }))">
                    <td class="px-5 py-3 font-mono text-ink-3 text-[11px]">{{ w.worker_id }}</td>
                    <td class="px-5 py-3 font-semibold text-white">{{ w.name }}</td>
                    <td class="px-5 py-3 text-ink-2">{{ w.father_husband_name || '—' }}</td>
                    <td class="px-5 py-3 text-ink-2">{{ w.post_held || w.post_applied || '—' }}</td>
                    <td class="px-5 py-3"><span class="pill pill-info">{{ w.appointment_type === 'temporary' ? 'अस्थाई' : w.appointment_type === 'permanent' ? 'स्थाई' : 'ठेका' }}</span></td>
                    <td class="px-5 py-3 text-right font-mono text-ink">{{ w.payment_mode === 'daily' ? fmt(w.daily_wage) + '/day' : fmt(w.monthly_wage) + '/mo' }}</td>
                    <td class="px-5 py-3"><span class="pill" :class="(statusMap[w.status] || statusMap.active).class">{{ (statusMap[w.status] || statusMap.active).label }}</span></td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ dateFmt(w.appointment_from) }}</td>
                </tr>
                <tr v-if="!workers.data?.length">
                    <td colspan="8" class="px-5 py-8 text-center text-ink-3">कोई श्रमिक नहीं। "नया श्रमिक" पर क्लिक करें।</td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
