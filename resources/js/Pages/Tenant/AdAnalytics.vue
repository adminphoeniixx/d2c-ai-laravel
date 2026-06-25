<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Megaphone, Eye, Trash2, Upload, Plus, FileText, RefreshCw } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    days: { type: Number, default: 30 },
    kpis: { type: Object, default: () => ({}) },
    platforms: { type: Object, default: () => ({}) },
    campaigns: { type: Array, default: () => [] },
    manualCampaigns: { type: Array, default: () => [] },
    dailySpend: { type: Object, default: () => ({}) },
    invoices: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const showUpload = ref(false);
const showManual = ref(false);
const activeDays = ref(props.days);
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

function changeDays(d) {
    activeDays.value = d;
    router.get(route('tenant.ads', { tenant: slug }), { days: d }, { preserveState: true });
}

// Upload
const uploadForm = useForm({
    platform: '', invoice_number: '', invoice_date: '', period_from: '', period_to: '', tax_amount: '',
    invoice_pdf: null, spend_csv: null,
});

// Multiple PDFs are uploaded one at a time in sequence (the backend
// contract stays single-file — each PDF gets its own AI extraction pass
// and its own AdInvoice row, which is also what dedup-by-transaction_id
// expects). uploadQueue holds the files staged in the modal before submit;
// uploadProgress tracks how many have completed so the button can show
// real progress instead of a single spinner.
const uploadQueue = ref([]);
const uploadProgress = ref({ done: 0, total: 0 });
const uploadErrors = ref([]);

function resetUpload() {
    uploadForm.reset();
    uploadQueue.value = [];
    uploadProgress.value = { done: 0, total: 0 };
    uploadErrors.value = [];
}

function addFilesToQueue(fileList) {
    uploadQueue.value = [...uploadQueue.value, ...Array.from(fileList)];
}

function removeFromQueue(index) {
    uploadQueue.value = uploadQueue.value.filter((_, i) => i !== index);
}

async function submitUpload() {
    if (uploadQueue.value.length === 0 && !uploadForm.spend_csv) return;

    uploadErrors.value = [];

    // No PDFs, just a CSV — single submit, same as before.
    if (uploadQueue.value.length === 0) {
        uploadForm.invoice_pdf = null;
        uploadForm.post(route('tenant.ads.upload-invoice', { tenant: slug }), {
            forceFormData: true,
            onSuccess: () => { showUpload.value = false; resetUpload(); },
        });
        return;
    }

    // One or more PDFs: upload sequentially so each gets its own AI
    // extraction + dedup pass, and so we don't fire 10 parallel AI calls
    // at once. uploadForm.processing drives the modal's disabled state.
    uploadForm.processing = true;
    uploadProgress.value = { done: 0, total: uploadQueue.value.length };

    for (const file of uploadQueue.value) {
        try {
            await new Promise((resolve, reject) => {
                uploadForm.transform((data) => ({ ...data, invoice_pdf: file, spend_csv: null }))
                    .post(route('tenant.ads.upload-invoice', { tenant: slug }), {
                        forceFormData: true,
                        preserveScroll: true,
                        onSuccess: () => resolve(),
                        onError: (errors) => reject(errors),
                    });
            });
            uploadProgress.value.done += 1;
        } catch (errors) {
            uploadErrors.value.push(`${file.name}: upload failed`);
            uploadProgress.value.done += 1;
        }
    }

    uploadForm.transform((data) => data); // clear the transform override
    uploadForm.processing = false;

    if (uploadErrors.value.length === 0) {
        showUpload.value = false;
        resetUpload();
        router.reload({ only: ['invoices', 'kpis', 'platforms', 'campaigns', 'dailySpend'] });
    }
    // If there were errors, leave the modal open with the error list shown
    // so the user can see which files failed rather than silently losing them.
}

const manualForm = useForm({
    platform: 'meta', date: '', campaign_name: '', spend: '', impressions: '', clicks: '', conversions: '',
});
function submitManual() {
    manualForm.post(route('tenant.ads.manual-spend', { tenant: slug }), {
        onSuccess: () => { showManual.value = false; manualForm.reset(); },
    });
}
function deleteInvoice(inv) {
    if (confirm(`Delete invoice ${inv.invoice_number || inv.id}?`)) {
        router.delete(route('tenant.ads.delete-invoice', { tenant: slug, invoiceId: inv.id }), {
            preserveScroll: true,
            onSuccess: () => {
                // router.delete() alone doesn't reliably refresh the
                // invoices/kpis props in every case — explicitly request
                // a partial reload of just the data this view depends on,
                // so the list updates immediately instead of only after
                // a manual page refresh.
                router.reload({ only: ['invoices', 'kpis', 'platforms', 'campaigns', 'dailySpend'] });
            },
        });
    }
}

const allCampaigns = [...(props.campaigns || []).map(c => ({
    name: c.name, platform: c.platform, spend: c.total_spend || 0,
    clicks: c.total_clicks || 0, impressions: c.total_impressions || 0, conversions: c.total_conversions || 0,
})), ...(props.manualCampaigns || []).map(c => ({
    name: c.campaign_name, platform: c.platform, spend: parseFloat(c.total_spend || 0),
    clicks: parseInt(c.total_clicks || 0), impressions: parseInt(c.total_impressions || 0), conversions: parseInt(c.total_conversions || 0),
}))].sort((a, b) => b.spend - a.spend);
</script>

<template>
<Head title="Ad Expenses" />
<TenantLayout>
    <div class="max-w-5xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white flex items-center gap-2"><Megaphone :size="20" /> Ad Expenses</h2>
                <p class="text-[12px] text-ink-3 mt-1">Meta Ads · Google Ads · Manual Uploads</p>
            </div>
            <div class="flex items-center gap-2">
                <button @click="showManual = true" class="btn btn-ghost btn-sm flex items-center gap-1 cursor-pointer"><Plus :size="12" /> Add Spend</button>
                <button @click="showUpload = true; resetUpload()" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer"><Upload :size="12" /> Upload Invoice</button>
            </div>
        </div>

        <!-- Period -->
        <div class="flex items-center gap-1.5 mb-4">
            <button v-for="d in [7, 14, 30, 60, 90]" :key="d" @click="changeDays(d)"
                class="px-3 py-1.5 text-[11px] font-mono rounded-full cursor-pointer transition"
                :class="activeDays === d ? 'bg-brand-600/20 text-brand-300 border border-brand-600/40' : 'text-ink-3 hover:text-ink border border-transparent'">{{ d }}D</button>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase tracking-wider font-mono">Total Ad Spend</div>
                <div class="text-[22px] font-bold text-rose-400 mt-1">{{ fmt(kpis.ad_spend) }}</div>
                <div class="text-[10px] text-ink-3 mt-0.5">
                    <span v-if="kpis.meta_spend" class="text-blue-400">Meta {{ fmt(kpis.meta_spend) }}</span>
                    <span v-if="kpis.meta_spend && kpis.google_spend" class="mx-1">·</span>
                    <span v-if="kpis.google_spend" class="text-amber-400">Google {{ fmt(kpis.google_spend) }}</span>
                </div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase tracking-wider font-mono">Revenue</div>
                <div class="text-[22px] font-bold text-emerald-400 mt-1">{{ fmt(kpis.revenue) }}</div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase tracking-wider font-mono">Blended ROAS</div>
                <div class="text-[22px] font-bold mt-1" :class="kpis.roas >= 3 ? 'text-emerald-400' : kpis.roas >= 1 ? 'text-amber-400' : 'text-rose-400'">{{ kpis.roas }}x</div>
            </div>
            <div class="card text-center">
                <div class="text-[10px] text-ink-3 uppercase tracking-wider font-mono">Ad Cost %</div>
                <div class="text-[22px] font-bold text-white mt-1">{{ kpis.revenue > 0 ? (kpis.ad_spend / kpis.revenue * 100).toFixed(1) + '%' : '—' }}</div>
            </div>
        </div>

        <!-- Platform cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
            <div v-for="(p, key) in platforms" :key="key" class="card">
                <div class="flex items-center gap-2 mb-3">
                    <div class="h-8 w-8 rounded-lg flex items-center justify-center" :class="key === 'meta' ? 'bg-blue-500/15' : 'bg-amber-500/15'">
                        <Megaphone :size="14" :class="key === 'meta' ? 'text-blue-400' : 'text-amber-400'" />
                    </div>
                    <div class="text-[14px] font-bold text-white">{{ key === 'meta' ? 'Meta Ads' : 'Google Ads' }}</div>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div><div class="text-[16px] font-bold text-white">{{ fmt(p.spend) }}</div><div class="text-[9px] text-ink-3 uppercase">Spend</div></div>
                    <div><div class="text-[16px] font-bold text-white">{{ p.clicks?.toLocaleString() || 0 }}</div><div class="text-[9px] text-ink-3 uppercase">Clicks</div></div>
                    <div><div class="text-[16px] font-bold" :class="p.roas >= 3 ? 'text-emerald-400' : p.roas >= 1 ? 'text-amber-400' : 'text-rose-400'">{{ p.roas }}x</div><div class="text-[9px] text-ink-3 uppercase">ROAS</div></div>
                </div>
                <div class="flex justify-between mt-3 pt-3 border-t border-frost-1 text-[11px] text-ink-3">
                    <span>{{ p.impressions?.toLocaleString() || 0 }} impr</span>
                    <span>{{ p.ctr }}% CTR</span>
                    <span>{{ p.conversions?.toLocaleString() || 0 }} conv</span>
                </div>
            </div>
        </div>

        <!-- Campaign table -->
        <div v-if="allCampaigns.length" class="card overflow-hidden p-0 mb-5">
            <div class="px-5 py-3 border-b border-frost-1"><h3 class="text-[14px] font-semibold text-white">Campaign Performance</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12px]">
                    <thead class="bg-bg-3 text-[9px] font-mono uppercase tracking-wider text-ink-3">
                        <tr><th class="text-left px-4 py-2.5">Campaign</th><th class="text-left px-3 py-2.5">Platform</th><th class="text-right px-3 py-2.5">Spend</th><th class="text-right px-3 py-2.5">Impr</th><th class="text-right px-3 py-2.5">Clicks</th><th class="text-right px-3 py-2.5">CTR</th><th class="text-right px-4 py-2.5">Conv</th></tr>
                    </thead>
                    <tbody class="divide-y divide-frost-1">
                        <tr v-for="c in allCampaigns.slice(0, 20)" :key="c.name + c.platform" class="hover:bg-brand-600/5">
                            <td class="px-4 py-2.5 text-white font-medium max-w-[250px] truncate">{{ c.name }}</td>
                            <td class="px-3 py-2.5"><span class="px-1.5 py-0.5 rounded text-[9px] font-bold" :class="c.platform === 'meta' ? 'bg-blue-500/15 text-blue-400' : c.platform === 'google' ? 'bg-amber-500/15 text-amber-400' : 'bg-ink-3/15 text-ink-3'">{{ c.platform }}</span></td>
                            <td class="px-3 py-2.5 text-right font-mono text-rose-400">{{ fmt(c.spend) }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-ink-2">{{ c.impressions?.toLocaleString() }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-ink-2">{{ c.clicks?.toLocaleString() }}</td>
                            <td class="px-3 py-2.5 text-right font-mono text-ink-3">{{ c.impressions > 0 ? (c.clicks / c.impressions * 100).toFixed(2) + '%' : '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-emerald-400">{{ c.conversions }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Invoices -->
        <div class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-frost-1 flex items-center justify-between">
                <h3 class="text-[14px] font-semibold text-white">Invoices & Uploads</h3>
                <button @click="showUpload = true; resetUpload()" class="text-[11px] text-brand-300 hover:underline cursor-pointer">+ Upload</button>
            </div>
            <div v-for="inv in invoices" :key="inv.id"
                class="px-5 py-3 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition flex items-center justify-between">
                <div class="flex items-center gap-3 cursor-pointer flex-1"
                    @click="router.visit(route('tenant.ads.invoice-detail', { tenant: slug, invoiceId: inv.id }))">
                    <div class="h-9 w-9 rounded-lg flex items-center justify-center"
                        :class="inv.platform === 'meta' ? 'bg-blue-500/15' : inv.platform === 'google' ? 'bg-amber-500/15' : 'bg-ink-3/15'">
                        <FileText :size="14" :class="inv.platform === 'meta' ? 'text-blue-400' : inv.platform === 'google' ? 'text-amber-400' : 'text-ink-3'" />
                    </div>
                    <div>
                        <div class="text-[13px] font-medium text-white">{{ inv.invoice_number || inv.platform + ' invoice' }}</div>
                        <div class="text-[11px] text-ink-3">{{ dateFmt(inv.invoice_date) }} · {{ inv.entry_count || 0 }} entries · <span class="capitalize">{{ inv.platform }}</span></div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <div class="text-[14px] font-bold text-white">{{ fmt(inv.total_amount) }}</div>
                        <div v-if="parseFloat(inv.tax) > 0" class="text-[10px] text-ink-3">incl. {{ fmt(inv.tax) }} tax</div>
                    </div>
                    <a v-if="inv.file_url" :href="inv.file_url" target="_blank" class="text-ink-3 hover:text-white"><Eye :size="14" /></a>
                    <button @click="deleteInvoice(inv)" class="text-ink-3 hover:text-rose-400 cursor-pointer"><Trash2 :size="14" /></button>
                </div>
            </div>
            <div v-if="!invoices?.length" class="px-5 py-8 text-center text-[13px] text-ink-3">No invoices yet. Upload your Meta/Google Ads invoices.</div>
        </div>

        <!-- UPLOAD MODAL: Just drop files -->
        <div v-if="showUpload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showUpload = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-lg mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-1">Upload Invoices</h3>
                <p class="text-[12px] text-ink-3 mb-4">Drop one or more invoice PDFs and/or a spend CSV. Everything is auto-detected.</p>
                <form @submit.prevent="submitUpload" class="space-y-3">
                    <div class="border-2 border-dashed border-frost-1 rounded-xl p-5 text-center hover:border-brand-600/40 transition">
                        <Upload :size="22" class="text-ink-3 mx-auto mb-1" />
                        <div class="text-[11px] text-ink-3 mb-2">Invoice PDFs (Meta / Google / Delhivery) — select multiple</div>
                        <input type="file" accept=".pdf" multiple class="heyd2c-input"
                               @change="addFilesToQueue($event.target.files); $event.target.value = ''" />
                    </div>

                    <!-- Queued files list -->
                    <div v-if="uploadQueue.length" class="space-y-1.5">
                        <div v-for="(file, i) in uploadQueue" :key="i"
                             class="flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-surface-2 border border-frost-1 text-[12px]">
                            <span class="flex items-center gap-2 text-ink truncate">
                                <FileText :size="13" class="text-ink-3 flex-shrink-0" />
                                <span class="truncate">{{ file.name }}</span>
                            </span>
                            <button v-if="!uploadForm.processing" type="button" @click="removeFromQueue(i)"
                                    class="text-ink-3 hover:text-rose-400 cursor-pointer flex-shrink-0">
                                <Trash2 :size="13" />
                            </button>
                            <span v-else-if="i < uploadProgress.done" class="text-emerald-400 flex-shrink-0 text-[11px]">✓ done</span>
                        </div>
                    </div>

                    <div class="border-2 border-dashed border-frost-1 rounded-xl p-5 text-center hover:border-brand-600/40 transition">
                        <FileText :size="22" class="text-ink-3 mx-auto mb-1" />
                        <div class="text-[11px] text-ink-3 mb-2">Spend Report CSV (optional)</div>
                        <input type="file" accept=".csv,.txt" class="heyd2c-input" @change="uploadForm.spend_csv = $event.target.files[0]" />
                    </div>

                    <!-- Manual tax/GST override — only makes sense for a single
                         invoice at a time, since a batch of files each need
                         their own (different) tax amount, which this simple
                         form doesn't attempt to support per-file. -->
                    <div v-if="uploadQueue.length <= 1">
                        <label class="heyd2c-label">Tax / GST amount (₹) — optional, overrides what was auto-detected</label>
                        <input v-model="uploadForm.tax_amount" type="number" step="0.01" min="0" class="heyd2c-input" placeholder="Leave blank to use auto-detected tax" />
                    </div>

                    <!-- Errors from a partially-failed batch -->
                    <div v-if="uploadErrors.length" class="rounded-lg bg-rose-500/10 border border-rose-500/30 px-3 py-2 text-[11.5px] text-rose-300 space-y-0.5">
                        <div v-for="(err, i) in uploadErrors" :key="i">{{ err }}</div>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1 cursor-pointer" :disabled="uploadForm.processing || (uploadQueue.length === 0 && !uploadForm.spend_csv)">
                            {{ uploadForm.processing
                                ? `Uploading ${uploadProgress.done}/${uploadProgress.total}…`
                                : (uploadQueue.length > 1 ? `Upload ${uploadQueue.length} Invoices` : 'Upload & Auto-Detect') }}
                        </button>
                        <button type="button" class="btn btn-ghost flex-1 cursor-pointer" @click="showUpload = false; resetUpload()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MANUAL SPEND MODAL -->
        <div v-if="showManual" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showManual = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-4">Add Ad Spend</h3>
                <form @submit.prevent="submitManual" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="heyd2c-label">Platform</label>
                            <select v-model="manualForm.platform" class="heyd2c-input">
                                <option value="meta">Meta Ads</option><option value="google">Google Ads</option><option value="other">Other</option>
                            </select>
                        </div>
                        <div><label class="heyd2c-label">Date</label><input v-model="manualForm.date" type="date" class="heyd2c-input" required /></div>
                    </div>
                    <div><label class="heyd2c-label">Campaign Name</label><input v-model="manualForm.campaign_name" class="heyd2c-input" placeholder="Optional" /></div>
                    <div><label class="heyd2c-label">Spend (₹)</label><input v-model.number="manualForm.spend" type="number" step="0.01" class="heyd2c-input" required /></div>
                    <div class="grid grid-cols-3 gap-3">
                        <div><label class="heyd2c-label">Impressions</label><input v-model.number="manualForm.impressions" type="number" class="heyd2c-input" /></div>
                        <div><label class="heyd2c-label">Clicks</label><input v-model.number="manualForm.clicks" type="number" class="heyd2c-input" /></div>
                        <div><label class="heyd2c-label">Conversions</label><input v-model.number="manualForm.conversions" type="number" class="heyd2c-input" /></div>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1 cursor-pointer" :disabled="manualForm.processing">Add</button>
                        <button type="button" class="btn btn-ghost flex-1 cursor-pointer" @click="showManual = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
