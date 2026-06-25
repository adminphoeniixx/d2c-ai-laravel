<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Landmark, Upload, Plus, Trash2, ArrowUpRight, ArrowDownRight } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    accounts: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    categorySpend: { type: Array, default: () => [] },
    vendorSpend: { type: Array, default: () => [] },
    monthlyTotals: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const showUpload = ref(false);
const needsPassword = ref(false);

const uploadForm = useForm({ statement: null, pdf_password: '' });
function submitUpload() {
    uploadForm.post(route('tenant.banking.smart-upload', { tenant: slug }), {
        forceFormData: true,
        onSuccess: () => { showUpload.value = false; uploadForm.reset(); needsPassword.value = false; },
        onError: (errors) => {
            // If the server tells us a password is needed, show the field
            if (Object.values(errors).some(e => String(e).toLowerCase().includes('password'))) {
                needsPassword.value = true;
            }
        },
    });
}
function deleteAccount(id) {
    if (confirm('Delete this account and all its transactions?')) {
        router.delete(route('tenant.banking.delete-account', { tenant: slug, accountId: id }));
    }
}
</script>

<template>
<Head title="Banking" />
<TenantLayout>
    <div class="max-w-5xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white flex items-center gap-2"><Landmark :size="20" /> Banking</h2>
                <p class="text-[12px] text-ink-3 mt-1">Upload bank statements, track credits & debits</p>
            </div>
            <button @click="showUpload = true" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer"><Upload :size="12" /> Upload Statement</button>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-3 gap-3 mb-5">
            <div class="card text-center">
                <ArrowUpRight :size="16" class="text-emerald-400 mx-auto mb-1" />
                <div class="text-[22px] font-bold text-emerald-400">{{ fmt(stats?.total_credit) }}</div>
                <div class="text-[10px] text-ink-3 uppercase">Total Credits</div>
            </div>
            <div class="card text-center">
                <ArrowDownRight :size="16" class="text-rose-400 mx-auto mb-1" />
                <div class="text-[22px] font-bold text-rose-400">{{ fmt(stats?.total_debit) }}</div>
                <div class="text-[10px] text-ink-3 uppercase">Total Debits</div>
            </div>
            <div class="card text-center">
                <div class="text-[22px] font-bold" :class="(stats?.total_credit - stats?.total_debit) >= 0 ? 'text-emerald-400' : 'text-rose-400'">{{ fmt(Math.abs((stats?.total_credit||0) - (stats?.total_debit||0))) }}</div>
                <div class="text-[10px] text-ink-3 uppercase">Net Flow</div>
            </div>
        </div>

        <!-- Monthly Overview -->
        <div v-if="monthlyTotals?.length" class="card mb-5">
            <h3 class="text-[13px] font-semibold text-white mb-3">Monthly Overview</h3>
            <div class="space-y-2">
                <div v-for="m in monthlyTotals" :key="m.month" class="flex items-center justify-between rounded-lg bg-bg-3 border border-frost-1 px-3 py-2">
                    <div>
                        <span class="text-[13px] text-white font-medium">{{ new Date(m.month + '-01').toLocaleDateString('en-IN', { month: 'long', year: 'numeric' }) }}</span>
                        <span class="text-[10px] text-ink-3 ml-2">{{ m.count }} txns</span>
                    </div>
                    <div class="flex items-center gap-4 text-[13px] font-mono">
                        <span class="text-emerald-400">↑ {{ fmt(m.credit) }}</span>
                        <span class="text-rose-400">↓ {{ fmt(m.debit) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Vendors -->
        <div v-if="vendorSpend?.length" class="card mb-5">
            <h3 class="text-[13px] font-semibold text-white mb-3">Top Vendors (Debits)</h3>
            <div class="space-y-1.5">
                <div v-for="v in vendorSpend" :key="v.vendor" class="flex items-center justify-between text-[12px] px-2 py-1.5 rounded bg-bg-3">
                    <span class="text-white">{{ v.vendor }}</span>
                    <div class="flex items-center gap-3">
                        <span class="text-ink-3">{{ v.count }} txns</span>
                        <span class="font-mono text-rose-400 font-bold">{{ fmt(v.total) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bank Accounts -->
        <div class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-frost-1">
                <h3 class="text-[14px] font-semibold text-white">Bank Accounts</h3>
            </div>
            <div v-for="a in accounts" :key="a.id"
                class="px-5 py-3 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition flex items-center justify-between cursor-pointer"
                @click="router.visit(route('tenant.banking.ledger', { tenant: slug, accountId: a.id }))">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-lg bg-bg-3 flex items-center justify-center"><Landmark :size="16" class="text-ink-3" /></div>
                    <div>
                        <div class="text-[13px] font-medium text-white">{{ a.name }}</div>
                        <div class="text-[11px] text-ink-3">{{ a.bank_name }} · {{ a.transactions_count || 0 }} transactions · {{ a.account_type }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <div class="text-[12px] text-emerald-400 font-mono">↑ {{ fmt(a.credit_total) }}</div>
                        <div class="text-[12px] text-rose-400 font-mono">↓ {{ fmt(a.debit_total) }}</div>
                    </div>
                    <button @click.stop="deleteAccount(a.id)" class="text-ink-3 hover:text-rose-400 cursor-pointer"><Trash2 :size="14" /></button>
                </div>
            </div>
            <div v-if="!accounts?.length" class="px-5 py-8 text-center text-[13px] text-ink-3">No bank accounts. Upload a statement to get started.</div>
        </div>

        <!-- Smart Upload Modal -->
        <div v-if="showUpload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showUpload = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-1">Upload Bank Statement</h3>
                <p class="text-[12px] text-ink-3 mb-4">Supports CSV, PDF, and Excel (XLSX). Bank auto-detected (HDFC, ICICI, SBI, Axis, Kotak). Account created automatically.</p>
                <form @submit.prevent="submitUpload" class="space-y-3">
                    <div class="border-2 border-dashed border-frost-1 rounded-xl p-6 text-center hover:border-brand-600/40 transition">
                        <Upload :size="24" class="text-ink-3 mx-auto mb-2" />
                        <div class="text-[11px] text-ink-3 mb-2">Bank Statement — CSV, PDF, or Excel</div>
                        <input type="file" accept=".csv,.txt,.pdf,.xlsx,.xls" class="heyd2c-input"
                               @change="uploadForm.statement = $event.target.files[0]; needsPassword = false" required />
                    </div>

                    <!-- Password field — shown when the uploaded PDF is password-protected,
                         or proactively when the selected file is a PDF (banks commonly
                         password-protect statements with DOB or account number) -->
                    <div v-if="needsPassword || (uploadForm.statement && uploadForm.statement.name?.toLowerCase().endsWith('.pdf'))">
                        <label class="heyd2c-label">PDF Password <span class="text-ink-3 font-normal">(if the file is password-protected)</span></label>
                        <input v-model="uploadForm.pdf_password" type="password" class="heyd2c-input"
                               placeholder="e.g. date of birth as DDMMYYYY, or last 4 digits of account" autocomplete="off" />
                        <p v-if="needsPassword" class="text-[11px] text-rose-400 mt-1">This PDF requires a password. Enter it above and try again.</p>
                        <p class="text-[10.5px] text-ink-3 mt-1">Common formats: HDFC/ICICI → customer ID, SBI → account number, Axis → date of birth (DDMMYYYY). Leave blank if not protected.</p>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1 cursor-pointer" :disabled="uploadForm.processing">
                            {{ uploadForm.processing ? 'Reading & Importing…' : 'Upload & Auto-Detect' }}
                        </button>
                        <button type="button" class="btn btn-ghost flex-1 cursor-pointer" @click="showUpload = false; needsPassword = false">Cancel</button>
                    </div>

                    <!-- Show any validation errors -->
                    <div v-if="Object.keys(uploadForm.errors).length" class="rounded-lg bg-rose-500/10 border border-rose-500/30 px-3 py-2 text-[12px] text-rose-400">
                        <div v-for="(err, key) in uploadForm.errors" :key="key">{{ err }}</div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
