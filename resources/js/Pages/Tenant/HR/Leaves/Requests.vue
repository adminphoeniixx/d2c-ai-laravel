<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Check, X, Clock, AlertCircle, CalendarCheck, ClipboardList } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    requests: { type: Object, default: () => ({ data: [] }) },
    counts: { type: Object, default: () => ({}) },
    status: { type: String, default: 'pending' },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const rejectId = ref(null);
const rejectReason = ref('');

function filterStatus(s) {
    router.get(route('tenant.hr.leaves.requests', { tenant: slug }), { status: s }, { preserveState: true });
}
function approve(id) {
    router.post(route('tenant.hr.leaves.approve', { tenant: slug, leaveRequest: id }));
}
function openReject(id) { rejectId.value = id; rejectReason.value = ''; }
function submitReject() {
    router.post(route('tenant.hr.leaves.reject', { tenant: slug, leaveRequest: rejectId.value }), { rejection_reason: rejectReason.value }, { onSuccess: () => { rejectId.value = null; } });
}

const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const statusColors = { pending: 'bg-amber-500/20 text-amber-400', approved: 'bg-emerald-500/20 text-emerald-400', rejected: 'bg-rose-500/20 text-rose-400' };
</script>

<template>
<Head title="Leave Requests" />
<TenantLayout>
    <div class="max-w-3xl">
        <div class="mb-5 flex items-start justify-between gap-3 flex-wrap">
            <div>
                <h2 class="text-[20px] font-bold text-white">Leave Requests</h2>
                <p class="text-[12px] text-ink-3 mt-1">Review and manage employee leave applications</p>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="route('tenant.hr.leaves.types', { tenant: slug })"
                      class="btn btn-ghost btn-sm flex items-center gap-1.5 cursor-pointer">
                    <CalendarCheck :size="13" /> Leave Types
                </Link>
                <Link :href="route('tenant.hr.leaves.balances', { tenant: slug })"
                      class="btn btn-ghost btn-sm flex items-center gap-1.5 cursor-pointer">
                    <ClipboardList :size="13" /> Leave Balances
                </Link>
            </div>
        </div>

        <!-- Status Tabs -->
        <div class="flex gap-2 mb-5">
            <button v-for="s in ['pending', 'approved', 'rejected', 'all']" :key="s" @click="filterStatus(s)"
                class="px-3 py-1.5 rounded-lg text-[12px] font-medium transition cursor-pointer"
                :class="status === s ? 'bg-brand-600/20 text-brand-300 border border-brand-600/40' : 'text-ink-3 hover:text-ink-2 border border-transparent'">
                {{ s.charAt(0).toUpperCase() + s.slice(1) }}
                <span v-if="s !== 'all' && counts[s]" class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] bg-bg-3">{{ counts[s] }}</span>
            </button>
        </div>

        <!-- Requests List -->
        <div class="card overflow-hidden">
            <div v-for="r in requests.data" :key="r.id"
                class="px-4 py-3 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-xl bg-brand-600/15 flex items-center justify-center flex-shrink-0 text-[12px] font-bold text-brand-300">
                            {{ r.leave_type?.code || '?' }}
                        </div>
                        <div>
                            <div class="text-[13px] font-medium text-white">{{ r.employee?.first_name }} {{ r.employee?.last_name }}</div>
                            <div class="text-[11px] text-ink-3">
                                {{ r.leave_type?.name }} · {{ r.days }} day{{ r.days > 1 ? 's' : '' }} ·
                                {{ dateFmt(r.from_date) }} → {{ dateFmt(r.to_date) }}
                            </div>
                            <div v-if="r.reason" class="text-[11px] text-ink-3 mt-0.5 italic">"{{ r.reason }}"</div>
                            <div v-if="r.rejection_reason" class="text-[11px] text-rose mt-0.5">Reason: {{ r.rejection_reason }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="statusColors[r.status]">{{ r.status }}</span>
                        <template v-if="r.status === 'pending'">
                            <button @click="approve(r.id)" class="p-1.5 rounded-lg bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500/30 cursor-pointer" title="Approve">
                                <Check :size="14" />
                            </button>
                            <button @click="openReject(r.id)" class="p-1.5 rounded-lg bg-rose-500/20 text-rose-400 hover:bg-rose-500/30 cursor-pointer" title="Reject">
                                <X :size="14" />
                            </button>
                        </template>
                    </div>
                </div>
            </div>
            <div v-if="!requests.data?.length" class="px-4 py-8 text-center text-[13px] text-ink-3">No {{ status }} leave requests</div>
        </div>

        <!-- Reject Modal -->
        <div v-if="rejectId" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="rejectId = null">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-sm mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-3">Reject Leave</h3>
                <div>
                    <label class="heyd2c-label">Reason (optional)</label>
                    <textarea v-model="rejectReason" class="heyd2c-input" rows="3" placeholder="Why is this leave rejected?"></textarea>
                </div>
                <div class="flex gap-2 mt-4">
                    <button @click="submitReject" class="btn bg-rose-600 hover:bg-rose-700 text-white flex-1 cursor-pointer">Reject</button>
                    <button @click="rejectId = null" class="btn btn-ghost flex-1 cursor-pointer">Cancel</button>
                </div>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
