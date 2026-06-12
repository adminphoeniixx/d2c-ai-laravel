<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { ShieldCheck, CheckCircle, XCircle, Eye, FileText, Download } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    kycs: { type: Object, default: () => ({ data: [] }) },
})

const selected        = ref(null)
const rejectionReason = ref('')

function approve(kyc) {
    router.post(route('admin.kyc.approve', kyc.id), {}, { preserveScroll: true, onSuccess: () => selected.value = null })
}

function reject(kyc) {
    if (!rejectionReason.value.trim()) { alert('Enter a rejection reason.'); return }
    router.post(route('admin.kyc.reject', kyc.id), { reason: rejectionReason.value }, {
        preserveScroll: true,
        onSuccess: () => { selected.value = null; rejectionReason.value = '' }
    })
}

const statusColor = (s) => ({
    pending:   'bg-slate-500/15 text-slate-400',
    submitted: 'bg-amber-500/15 text-amber-400',
    approved:  'bg-emerald-500/15 text-emerald-400',
    rejected:  'bg-rose-500/15 text-rose-400',
}[s] || 'bg-slate-500/15 text-slate-400')

function isPdf(url) { return url?.toLowerCase().endsWith('.pdf') }
function isImage(url) { return /\.(jpg|jpeg|png|gif|webp)$/i.test(url || '') }
</script>

<template>
<Head title="KYC Management" />
<AdminLayout>
    <div class="space-y-4">
        <h1 class="text-[20px] font-bold text-white">KYC Management</h1>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-[12.5px]">
                <thead><tr class="border-b border-frost-1">
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 uppercase">Company</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 uppercase">Legal Name</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 uppercase">GSTIN</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 uppercase">Docs</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 uppercase">Submitted</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 uppercase">Status</th>
                    <th class="px-5 py-3 w-32"></th>
                </tr></thead>
                <tbody class="divide-y divide-frost-1">
                    <tr v-for="kyc in kycs.data" :key="kyc.id" class="hover:bg-brand-600/5">
                        <td class="px-5 py-3 font-medium text-white">{{ kyc.company?.name }}</td>
                        <td class="px-5 py-3 text-ink-2">{{ kyc.legal_name || '—' }}</td>
                        <td class="px-5 py-3 font-mono text-ink-3 text-[11px]">{{ kyc.gstin || '—' }}</td>
                        <td class="px-5 py-3">
                            <span class="text-[11px] text-ink-3">{{ kyc.documents?.length || 0 }} file{{ kyc.documents?.length !== 1 ? 's' : '' }}</span>
                        </td>
                        <td class="px-5 py-3 text-ink-3 text-[11px]">
                            {{ kyc.submitted_at ? new Date(kyc.submitted_at).toLocaleDateString('en-IN') : '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-[10px] px-2 py-0.5 rounded-full capitalize font-medium" :class="statusColor(kyc.status)">
                                {{ kyc.status }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2 justify-end">
                                <button @click="selected = kyc; rejectionReason = ''"
                                    class="text-ink-3 hover:text-white cursor-pointer transition" title="Review">
                                    <Eye :size="13" />
                                </button>
                                <button v-if="kyc.status === 'submitted'" @click="approve(kyc)"
                                    class="text-emerald-400 hover:text-emerald-300 cursor-pointer text-[11px] font-medium">
                                    Approve
                                </button>
                                <button v-if="kyc.status === 'submitted'" @click="selected = kyc; rejectionReason = ''"
                                    class="text-rose-400 hover:text-rose-300 cursor-pointer text-[11px] font-medium">
                                    Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!kycs.data?.length">
                        <td colspan="7" class="px-5 py-8 text-center text-ink-3">No KYC submissions yet</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="kycs.last_page > 1" class="flex items-center justify-between text-[12px]">
            <span class="text-ink-3">Page {{ kycs.current_page }} of {{ kycs.last_page }}</span>
            <div class="flex gap-2">
                <a v-if="kycs.prev_page_url" :href="kycs.prev_page_url" class="btn btn-ghost btn-sm">← Prev</a>
                <a v-if="kycs.next_page_url" :href="kycs.next_page_url" class="btn btn-ghost btn-sm">Next →</a>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4 overflow-y-auto" @click.self="selected = null">
        <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-xl p-6 space-y-5 my-4">
            <div class="flex items-center justify-between">
                <h3 class="text-[15px] font-bold text-white">KYC Review — {{ selected.company?.name }}</h3>
                <span class="text-[10px] px-2 py-0.5 rounded-full capitalize font-medium" :class="statusColor(selected.status)">
                    {{ selected.status }}
                </span>
            </div>

            <!-- Details grid -->
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-[12px]">
                <template v-for="[label, val] in [
                    ['Legal Name', selected.legal_name],
                    ['Business Type', selected.business_type],
                    ['GSTIN', selected.gstin],
                    ['PAN', selected.pan],
                    ['Address', [selected.address_line1, selected.address_line2, selected.city, selected.state, selected.pincode].filter(Boolean).join(', ')],
                    ['Bank', selected.bank_name],
                    ['Account No.', selected.bank_account_number],
                    ['IFSC', selected.bank_ifsc],
                    ['Account Holder', selected.bank_account_name],
                ]" :key="label">
                    <div class="text-ink-3">{{ label }}</div>
                    <div class="text-white break-all">{{ val || '—' }}</div>
                </template>
            </div>

            <!-- Documents -->
            <div v-if="selected.documents?.length" class="space-y-2">
                <p class="text-[11px] text-ink-3 font-medium uppercase tracking-wide">Documents ({{ selected.documents.length }})</p>
                <div class="space-y-2">
                    <div v-for="doc in selected.documents" :key="doc.url"
                        class="bg-frost-1 rounded-xl overflow-hidden">
                        <!-- Image preview -->
                        <img v-if="isImage(doc.url)" :src="doc.url" :alt="doc.name"
                            class="w-full max-h-48 object-contain bg-black/20" />
                        <!-- PDF icon -->
                        <div v-else class="flex items-center gap-3 px-4 py-3">
                            <FileText :size="20" class="text-rose-400 shrink-0" />
                            <span class="text-[12px] text-white flex-1 truncate">{{ doc.name }}</span>
                        </div>
                        <!-- Doc info + actions -->
                        <div class="flex items-center justify-between px-3 py-2 border-t border-frost-1">
                            <div>
                                <p class="text-[11px] text-white truncate max-w-[200px]">{{ doc.name }}</p>
                                <p class="text-[10px] text-ink-3">{{ new Date(doc.uploaded_at).toLocaleDateString('en-IN') }}</p>
                            </div>
                            <a :href="doc.url" target="_blank" download
                                class="btn btn-ghost btn-sm flex items-center gap-1 cursor-pointer text-brand-300">
                                <Download :size="11" /> View
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-[12px] text-ink-3 italic">No documents uploaded.</div>

            <!-- Rejection reason shown if already rejected -->
            <div v-if="selected.status === 'rejected' && selected.rejection_reason"
                class="bg-rose-900/20 border border-rose-800/30 rounded-xl p-3">
                <p class="text-[11px] text-rose-300"><strong>Rejection reason:</strong> {{ selected.rejection_reason }}</p>
            </div>

            <!-- Actions for submitted -->
            <div v-if="selected.status === 'submitted'" class="space-y-3 border-t border-frost-1 pt-3">
                <div>
                    <label class="heyd2c-label">Rejection Reason (required if rejecting)</label>
                    <input v-model="rejectionReason" class="heyd2c-input" placeholder="e.g. GSTIN mismatch, blurry documents" />
                </div>
                <div class="flex gap-2">
                    <button @click="approve(selected)" class="btn btn-primary flex-1 cursor-pointer">
                        <CheckCircle :size="13" class="inline mr-1" /> Approve KYC
                    </button>
                    <button @click="reject(selected)" class="btn btn-ghost text-rose-400 flex-1 cursor-pointer">
                        <XCircle :size="13" class="inline mr-1" /> Reject KYC
                    </button>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
