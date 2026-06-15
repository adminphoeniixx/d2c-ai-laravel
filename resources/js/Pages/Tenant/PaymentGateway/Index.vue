<script setup>
import { ref, computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Upload, CreditCard, Trash2, CheckCircle, XCircle, Loader, Eye, X, FileText } from 'lucide-vue-next'
import TenantLayout from '@/Layouts/TenantLayout.vue'

const props = defineProps({
    invoices: { type: Array,  default: () => [] },
    summary:  { type: Object, default: () => ({}) },
})

const slug      = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || ''
const uploading = ref(false)
const dragOver  = ref(false)
const toast     = ref(null) // { type: 'success'|'error', message }
const selected  = ref(null)

const totalGross    = computed(() => props.summary.total_gross    ?? 0)
const totalCharges  = computed(() => props.summary.total_charges  ?? 0)
const totalGst      = computed(() => props.summary.total_gst      ?? 0)
const effectiveRate = computed(() => totalGross.value > 0 ? (totalCharges.value / totalGross.value * 100).toFixed(2) : '0.00')

const fmt = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 })

function showToast(type, message) {
    toast.value = { type, message }
    setTimeout(() => toast.value = null, 4000)
}

async function handleFiles(files) {
    if (!files?.length) return
    uploading.value = true

    const fd = new FormData()
    for (const f of files) fd.append('invoices[]', f)

    // Get CSRF token
    const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]
    const csrfToken = token ? decodeURIComponent(token) : ''

    try {
        const res = await fetch(`/app/${slug}/payment-gateway/upload`, {
            method: 'POST',
            headers: { 'X-XSRF-TOKEN': csrfToken },
            body: fd,
        })

        const data = await res.json()

        if (data.success) {
            showToast('success', data.message || 'Invoice(s) processed successfully')
            router.reload({ preserveScroll: true })
        } else {
            showToast('error', data.error || 'Upload failed')
        }
    } catch (e) {
        showToast('error', 'Network error. Please try again.')
    } finally {
        uploading.value = false
    }
}

function onDrop(e)      { dragOver.value = false; handleFiles(e.dataTransfer.files) }
function onFileInput(e) { handleFiles(e.target.files); e.target.value = '' }

function destroy(id) {
    if (!confirm('Delete this invoice?')) return
    router.visit(`/app/${slug}/payment-gateway/${id}`, { method: 'delete', preserveScroll: true })
}

function viewDetails(inv) {
    selected.value = inv
}

function closeDetails() {
    selected.value = null
}

const pgColor = (g) => ({
    razorpay: 'text-blue-400',
    payu:     'text-purple-400',
    cashfree: 'text-emerald-400',
    stripe:   'text-violet-400',
    phonepe:  'text-indigo-400',
}[g?.toLowerCase()] || 'text-ink-2')

const dateFmt = (d) => d ? new Date(d).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '—'
</script>

<template>
<Head title="Payment Gateway" />
<TenantLayout>
    <div class="space-y-5">

        <!-- Toast -->
        <Transition enter-from-class="opacity-0 translate-y-2" enter-active-class="transition duration-200"
            leave-to-class="opacity-0 translate-y-2" leave-active-class="transition duration-200">
            <div v-if="toast" class="fixed top-5 right-5 z-50 flex items-center gap-2.5 px-4 py-3 rounded-xl shadow-lg text-[13px] font-medium"
                :class="toast.type === 'success' ? 'bg-emerald-900/90 border border-emerald-500/30 text-emerald-300' : 'bg-rose-900/90 border border-rose-500/30 text-rose-300'">
                <CheckCircle v-if="toast.type === 'success'" :size="15" />
                <XCircle v-else :size="15" />
                {{ toast.message }}
            </div>
        </Transition>

        <h1 class="text-[20px] font-bold text-white">Payment Gateway</h1>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase mb-1">Total Charges (excl. GST)</div>
                <div class="text-[20px] font-bold text-white">{{ fmt(totalCharges) }}</div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase mb-1">Total GST Paid</div>
                <div class="text-[20px] font-bold text-amber-400">{{ fmt(totalGst) }}</div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase mb-1">Total Invoiced</div>
                <div class="text-[20px] font-bold text-rose-400">{{ fmt(totalGross) }}</div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase mb-1">Invoices</div>
                <div class="text-[20px] font-bold text-brand-300">{{ invoices.length }}</div>
            </div>
        </div>

        <!-- Upload -->
        <div class="card transition"
            :class="dragOver ? 'border-brand-400 bg-brand-600/5' : ''"
            @dragover.prevent="dragOver = true"
            @dragleave="dragOver = false"
            @drop.prevent="onDrop">
            <div class="text-center py-5">
                <div v-if="uploading" class="flex flex-col items-center gap-2">
                    <Loader :size="24" class="text-brand-400 animate-spin" />
                    <p class="text-[13px] text-ink-2">Processing with AI…</p>
                </div>
                <div v-else>
                    <Upload :size="24" class="mx-auto text-ink-3 mb-3" />
                    <p class="text-[13px] text-white font-medium mb-1">Upload PG Invoice</p>
                    <p class="text-[11px] text-ink-3 mb-3">PDF, CSV, or image — AI extracts charges, GST & settlement data</p>
                    <label class="btn btn-primary btn-sm cursor-pointer">
                        Choose Files
                        <input type="file" accept=".pdf,.csv,.jpg,.jpeg,.png" multiple class="hidden" @change="onFileInput" />
                    </label>
                    <p class="text-[10px] text-ink-3 mt-2">Supports Razorpay, PayU, Cashfree, Stripe, PhonePe</p>
                </div>
            </div>
        </div>

        <!-- Invoice List -->
        <div v-if="invoices.length" class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-frost-1 flex items-center justify-between">
                <h3 class="text-[13px] font-semibold text-white">Invoice History</h3>
                <span class="text-[11px] text-ink-3">{{ invoices.length }} invoice{{ invoices.length !== 1 ? 's' : '' }}</span>
            </div>
            <table class="w-full text-[12px]">
                <thead><tr class="border-b border-frost-1">
                    <th class="text-left px-5 py-2.5 text-[10px] text-ink-3 uppercase">Invoice</th>
                    <th class="text-left px-5 py-2.5 text-[10px] text-ink-3 uppercase">Gateway</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 uppercase">Gross</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 uppercase">Charges</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 uppercase">GST</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 uppercase">Settled</th>
                    <th class="text-left px-5 py-2.5 text-[10px] text-ink-3 uppercase">Period</th>
                    <th class="px-5 py-2.5 w-16"></th>
                </tr></thead>
                <tbody>
                    <tr v-for="inv in invoices" :key="inv.id"
                        class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition cursor-pointer"
                        @click="viewDetails(inv)">
                        <td class="px-5 py-3 font-mono text-[11px] text-brand-300">{{ inv.invoice_number || '—' }}</td>
                        <td class="px-5 py-3 font-semibold capitalize" :class="pgColor(inv.gateway)">{{ inv.gateway || '—' }}</td>
                        <td class="px-5 py-3 text-right text-white">{{ fmt(inv.gross_volume) }}</td>
                        <td class="px-5 py-3 text-right text-rose-400">{{ fmt(inv.total_charges) }}</td>
                        <td class="px-5 py-3 text-right text-amber-400">{{ fmt(inv.gst_amount) }}</td>
                        <td class="px-5 py-3 text-right text-emerald-400">{{ fmt(inv.net_settled) }}</td>
                        <td class="px-5 py-3 text-ink-3 text-[11px]">{{ inv.period || '—' }}</td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2.5 justify-end">
                                <button @click.stop="viewDetails(inv)"
                                    class="text-ink-3 hover:text-brand-300 cursor-pointer transition" title="View details">
                                    <Eye :size="13" />
                                </button>
                                <button @click.stop="destroy(inv.id)"
                                    class="text-ink-3 hover:text-rose-400 cursor-pointer transition" title="Delete">
                                    <Trash2 :size="12" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="card text-center py-10">
            <CreditCard :size="28" class="text-ink-3 mx-auto mb-2" />
            <p class="text-[13px] text-ink-3">No PG invoices uploaded yet.</p>
            <p class="text-[11px] text-ink-3 mt-1">Upload your Razorpay, PayU, or Cashfree invoice to see charge breakdown.</p>
        </div>
    </div>

    <!-- Details Modal -->
    <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="closeDetails">
        <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <FileText :size="18" class="text-brand-400" />
                    <h3 class="text-[15px] font-bold text-white">Invoice Details</h3>
                </div>
                <button @click="closeDetails" class="text-ink-3 hover:text-white cursor-pointer transition">
                    <X :size="16" />
                </button>
            </div>

            <div class="space-y-2.5 text-[12.5px]">
                <div class="flex items-center justify-between">
                    <span class="text-ink-3">Gateway</span>
                    <span class="font-semibold capitalize" :class="pgColor(selected.gateway)">{{ selected.gateway || '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-ink-3">Invoice Number</span>
                    <span class="font-mono text-brand-300">{{ selected.invoice_number || '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-ink-3">Billing Period</span>
                    <span class="text-white">{{ selected.period || '—' }}</span>
                </div>
                <div v-if="selected.period_start || selected.period_end" class="flex items-center justify-between">
                    <span class="text-ink-3">Period Dates</span>
                    <span class="text-white text-[11px] font-mono">{{ selected.period_start || '?' }} → {{ selected.period_end || '?' }}</span>
                </div>
                <div class="h-px bg-frost-1 my-2"></div>
                <div class="flex items-center justify-between">
                    <span class="text-ink-3">Gross Volume</span>
                    <span class="text-white font-medium">{{ fmt(selected.gross_volume) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-ink-3">Total Charges (excl. GST)</span>
                    <span class="text-rose-400 font-medium">{{ fmt(selected.total_charges) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-ink-3">GST Amount</span>
                    <span class="text-amber-400 font-medium">{{ fmt(selected.gst_amount) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-ink-3">Net Settled / Grand Total</span>
                    <span class="text-emerald-400 font-semibold">{{ fmt(selected.net_settled) }}</span>
                </div>
                <div class="h-px bg-frost-1 my-2"></div>
                <div class="flex items-center justify-between">
                    <span class="text-ink-3">Source File</span>
                    <span class="text-white truncate max-w-[200px]" :title="selected.original_name">{{ selected.original_name || '—' }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-ink-3">Uploaded</span>
                    <span class="text-ink-2">{{ dateFmt(selected.created_at) }}</span>
                </div>
            </div>

            <button @click="closeDetails" class="btn btn-ghost w-full cursor-pointer">Close</button>
        </div>
    </div>
</TenantLayout>
</template>
