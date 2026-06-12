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
    platform: '', invoice_number: '', invoice_date: '', period_from: '', period_to: '',
    invoice_pdf: null, spend_csv: null,
});

function resetUpload() { uploadForm.reset(); }

function submitUpload() {
    uploadForm.post(route('tenant.ads.upload-invoice', { tenant: slug }), {
        forceFormData: true,
        onSuccess: () => { showUpload.value = false; resetUpload(); },
    });
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
        router.delete(route('tenant.ads.delete-invoice', { tenant: slug, invoiceId: inv.id }));
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
<Head title="Ad Analytics" />
<TenantLayout>
    <div class="max-w-5xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white flex items-center gap-2"><Megaphone :size="20" /> Ad Analytics</h2>
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
                    <span class="text-[14px] font-bold text-white">{{ fmt(inv.total_amount) }}</span>
                    <a v-if="inv.file_url" :href="inv.file_url" target="_blank" class="text-ink-3 hover:text-white"><Eye :size="14" /></a>
                    <button @click="deleteInvoice(inv)" class="text-ink-3 hover:text-rose-400 cursor-pointer"><Trash2 :size="14" /></button>
                </div>
            </div>
            <div v-if="!invoices?.length" class="px-5 py-8 text-center text-[13px] text-ink-3">No invoices yet. Upload your Meta/Google Ads invoices.</div>
        </div>

        <!-- UPLOAD MODAL: Just drop files -->
        <div v-if="showUpload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showUpload = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-lg mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-1">Upload Invoice</h3>
                <p class="text-[12px] text-ink-3 mb-4">Drop your invoice PDF and/or spend CSV. Everything is auto-detected.</p>
                <form @submit.prevent="submitUpload" class="space-y-3">
                    <div class="border-2 border-dashed border-frost-1 rounded-xl p-5 text-center hover:border-brand-600/40 transition">
                        <Upload :size="22" class="text-ink-3 mx-auto mb-1" />
                        <div class="text-[11px] text-ink-3 mb-2">Invoice PDF (Meta / Google / Delhivery)</div>
                        <input type="file" accept=".pdf" class="heyd2c-input" @change="uploadForm.invoice_pdf = $event.target.files[0]" />
                    </div>
                    <div class="border-2 border-dashed border-frost-1 rounded-xl p-5 text-center hover:border-brand-600/40 transition">
                        <FileText :size="22" class="text-ink-3 mx-auto mb-1" />
                        <div class="text-[11px] text-ink-3 mb-2">Spend Report CSV (optional)</div>
                        <input type="file" accept=".csv,.txt" class="heyd2c-input" @change="uploadForm.spend_csv = $event.target.files[0]" />
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1 cursor-pointer" :disabled="uploadForm.processing">
                            {{ uploadForm.processing ? 'Reading & Uploading…' : 'Upload & Auto-Detect' }}
                        </button>
                        <button type="button" class="btn btn-ghost flex-1 cursor-pointer" @click="showUpload = false">Cancel</button>
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
