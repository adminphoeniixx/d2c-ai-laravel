<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Plus, X, Filter, Trash2, Upload, FileText, Image, Table2, Loader2, Check, AlertCircle, Paperclip, ExternalLink, ChevronDown, ChevronUp, Settings, Eye, Cpu, Calendar, BarChart3 } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    expenses: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
    totals: { type: Object, default: () => ({}) },
    pnl: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^\/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));

/* ── Categories ─────────────────────────────────────── */
const categories = ['ads','payroll','inventory','shipping','tools','rent','packaging','logistics','platform_fee','payment_gateway','software','marketing','office','travel','utilities','other'];
const catLabels = { ads:'Ads', payroll:'Payroll', inventory:'Inventory', shipping:'Shipping', tools:'Tools', rent:'Rent', packaging:'Packaging', logistics:'Logistics', platform_fee:'Platform Fee', payment_gateway:'Payment Gateway', software:'Software', marketing:'Marketing', office:'Office', travel:'Travel', utilities:'Utilities', other:'Other' };

/* ── KPI Tile Config ────────────────────────────────── */
const allTiles = [
    { key: 'total', label: 'Total' },
    { key: 'gst_paid', label: 'GST Paid' },
    { key: 'net_amount', label: 'Net Amount' },
    { key: 'entries', label: 'Entries' },
    { key: 'ads', label: 'Ad Spend' },
    { key: 'inventory', label: 'Inventory' },
    { key: 'logistics', label: 'Logistics' },
    { key: 'platform_fee', label: 'Platform Fees' },
    { key: 'payroll', label: 'Payroll' },
    { key: 'rent', label: 'Rent' },
    { key: 'tools', label: 'Tools' },
    { key: 'marketing', label: 'Marketing' },
];
const visibleTiles = ref(['total', 'gst_paid', 'net_amount', 'entries']);
const activeTiles = computed(() => allTiles.filter(t => visibleTiles.value.includes(t.key)));

function tileValue(key) {
    const val = props.totals[key] ?? 0;
    return key === 'entries' ? String(val) : fmt(val);
}

/* ── Settings ────────────────────────────────────────── */
const showSettings = ref(false);
const imageMethod = ref('ai');
const settingsSaving = ref(false);
const tempTiles = ref([]);

async function loadSettings() {
    try {
        const resp = await fetch(route('tenant.expenses.settings', { tenant: slug }), { headers: { 'Accept': 'application/json' } });
        if (resp.ok) {
            const json = await resp.json();
            imageMethod.value = json.image_method || 'ai';
            if (json.visible_tiles?.length) visibleTiles.value = json.visible_tiles;
        }
    } catch (e) {}
}

async function saveSettings() {
    settingsSaving.value = true;
    try {
        await fetch(route('tenant.expenses.settings.update', { tenant: slug }), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ image_method: imageMethod.value, visible_tiles: tempTiles.value }),
        });
        visibleTiles.value = [...tempTiles.value];
        showSettings.value = false;
    } catch (e) {}
    settingsSaving.value = false;
}

function openSettings() {
    loadSettings();
    tempTiles.value = [...visibleTiles.value];
    showSettings.value = true;
}

function toggleTile(key) {
    const idx = tempTiles.value.indexOf(key);
    if (idx >= 0) tempTiles.value.splice(idx, 1);
    else tempTiles.value.push(key);
}

/* ── Filters ────────────────────────────────────────── */
const activeCategory = ref(props.filters.category || 'all');
const activeSource = ref(props.filters.source || 'all');
const activeDatePreset = ref(props.filters.date_preset || 'this_month');
const dateFrom = ref(props.filters.from || '');
const dateTo = ref(props.filters.to || '');
const showCustomDates = ref(false);
const datePresets = [{ key:'this_month', label:'This Month' }, { key:'last_month', label:'Last Month' }, { key:'last_3m', label:'Last 3M' }, { key:'all', label:'All Time' }, { key:'custom', label:'Custom' }];

function buildFilterParams() {
    return { category: activeCategory.value === 'all' ? '' : activeCategory.value, source: activeSource.value === 'all' ? '' : activeSource.value, date_preset: activeDatePreset.value, from: dateFrom.value || '', to: dateTo.value || '' };
}
function applyFilters() { router.get(route('tenant.expenses', { tenant: slug }), buildFilterParams(), { preserveState: true }); }
function filterCategory(c) { activeCategory.value = c; applyFilters(); }
function filterSource(s) { activeSource.value = s; applyFilters(); }
function filterDatePreset(p) {
    activeDatePreset.value = p;
    if (p === 'custom') { showCustomDates.value = true; return; }
    showCustomDates.value = false; dateFrom.value = ''; dateTo.value = ''; applyFilters();
}
function applyCustomDates() { if (dateFrom.value && dateTo.value) { activeDatePreset.value = 'custom'; applyFilters(); } }

/* ── Manual Form ────────────────────────────────────── */
const showManual = ref(false);
const form = useForm({ category: 'other', label: '', amount: '', occurred_at: new Date().toISOString().split('T')[0], source: 'manual' });
function submitManual() { form.post(route('tenant.expenses.store', { tenant: slug }), { onSuccess: () => { showManual.value = false; form.reset(); } }); }

/* ── Upload Modal ───────────────────────────────────── */
const showUpload = ref(false);
const uploadStep = ref('select');
const dragOver = ref(false);
const uploadError = ref('');
const selectedFile = ref(null);
const extractedData = ref(null);
const needsTitle = ref(false);
const titleInput = ref('');
const multiRows = ref([]);
const editForm = ref({ label:'', amount:0, category:'other', occurred_at:'', vendor:'', notes:'', line_items:[], attachment_path:'', attachment_type:'' });

function openUpload() { showUpload.value = true; uploadStep.value = 'select'; uploadError.value = ''; selectedFile.value = null; extractedData.value = null; multiRows.value = []; }
function closeUpload() { showUpload.value = false; uploadStep.value = 'select'; }
function onDragOver(e) { e.preventDefault(); dragOver.value = true; }
function onDragLeave() { dragOver.value = false; }
function onDrop(e) { e.preventDefault(); dragOver.value = false; if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]); }
function onFileSelect(e) { if (e.target.files.length) handleFile(e.target.files[0]); }

async function handleFile(file) {
    const ext = file.name.split('.').pop().toLowerCase();
    if (!['csv','pdf','jpg','jpeg','png','gif','webp'].includes(ext)) { uploadError.value = 'Unsupported file type.'; return; }
    if (file.size > 10 * 1024 * 1024) { uploadError.value = 'File too large. Max 10MB.'; return; }
    selectedFile.value = file; uploadError.value = ''; uploadStep.value = 'extracting';

    const fd = new FormData();
    fd.append('file', file); fd.append('image_method', imageMethod.value);
    try {
        const resp = await fetch(route('tenant.expenses.extract', { tenant: slug }), {
            method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json' }, body: fd,
        });
        const json = await resp.json();
        if (!resp.ok || !json.success) { uploadError.value = json.error || 'Extraction failed'; uploadStep.value = 'select'; return; }
        extractedData.value = json;

        if (json.multi) {
            multiRows.value = (json.data || []).map((r, i) => ({ ...r, selected: true, _idx: i, label: r.vendor ? `${r.vendor} - ${r.label}` : r.label || '', occurred_at: r.date || r.occurred_at || new Date().toISOString().split('T')[0], attachment_path: json.attachment_path || '', attachment_type: json.attachment_type || json.type || '' }));
            uploadStep.value = 'multi_preview';
        } else if (json.needs_title) { populateEditForm(json); needsTitle.value = true; titleInput.value = ''; uploadStep.value = 'title_prompt'; }
        else { populateEditForm(json); uploadStep.value = 'preview'; }
    } catch (err) { uploadError.value = 'Upload failed: ' + err.message; uploadStep.value = 'select'; }
}

function populateEditForm(json) {
    const d = json.data || {};
    editForm.value = { label: d.vendor ? `${d.vendor} - ${d.label}` : d.label || '', amount: d.amount || 0, category: d.category || 'other', occurred_at: d.date || new Date().toISOString().split('T')[0], vendor: d.vendor || '', notes: d.notes || '', line_items: d.line_items || [], attachment_path: json.attachment_path || '', attachment_type: json.attachment_type || json.type || '', gst_amount: d.gst_amount || 0, confidence: d.confidence || 'medium' };
}
function confirmTitle() { if (!titleInput.value.trim()) return; editForm.value.label = titleInput.value.trim(); uploadStep.value = 'preview'; }

async function saveExpense() {
    uploadStep.value = 'saving';
    try {
        const resp = await fetch(route('tenant.expenses.upload.store', { tenant: slug }), {
            method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ expenses: [{ label: editForm.value.label, amount: editForm.value.amount, category: editForm.value.category, occurred_at: editForm.value.occurred_at, vendor: editForm.value.vendor, notes: editForm.value.notes, attachment_path: editForm.value.attachment_path, attachment_type: editForm.value.attachment_type, line_items: editForm.value.line_items, extracted_data: { gst_amount: editForm.value.gst_amount || 0, gst_rate: extractedData.value?.data?.gst_rate || 0, has_gst: extractedData.value?.data?.has_gst || false, confidence: editForm.value.confidence || 'medium' } }] }),
        });
        if (resp.ok) { closeUpload(); router.reload(); } else { uploadError.value = (await resp.json()).message || 'Save failed'; uploadStep.value = 'preview'; }
    } catch (err) { uploadError.value = 'Save failed: ' + err.message; uploadStep.value = 'preview'; }
}

async function saveMultiExpenses() {
    const selected = multiRows.value.filter(r => r.selected); if (!selected.length) return;
    uploadStep.value = 'saving';
    try {
        const resp = await fetch(route('tenant.expenses.upload.store', { tenant: slug }), {
            method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ expenses: selected.map(r => ({ label: r.label || r.vendor || 'Imported', amount: r.amount, category: r.category || 'other', occurred_at: r.occurred_at || r.date || new Date().toISOString().split('T')[0], vendor: r.vendor || '', notes: r.notes || '', attachment_path: r.attachment_path || '', attachment_type: r.attachment_type || '', line_items: r.line_items || [], extracted_data: { gst_amount: r.gst_amount || 0, gst_rate: r.gst_rate || 0, has_gst: r.has_gst || false, confidence: r.confidence || 'medium' } })) }),
        });
        if (resp.ok) { closeUpload(); router.reload(); } else { uploadError.value = (await resp.json()).message || 'Save failed'; uploadStep.value = 'multi_preview'; }
    } catch (err) { uploadError.value = 'Save failed: ' + err.message; uploadStep.value = 'multi_preview'; }
}

function toggleAllMulti() { const all = multiRows.value.every(r => r.selected); multiRows.value.forEach(r => r.selected = !all); }
const multiSelectedCount = computed(() => multiRows.value.filter(r => r.selected).length);
const multiSelectedTotal = computed(() => multiRows.value.filter(r => r.selected).reduce((s, r) => s + (r.amount || 0), 0));

/* ── P&L Toggle ──────────────────────────────────────── */
const showPnl = ref(false);
const pnlMaxAmount = computed(() => Math.max(...(props.pnl || []).map(p => p.amount), 1));

/* ── Row Expand / Delete ─────────────────────────────── */
const expandedRow = ref(null);
function toggleExpand(id) { expandedRow.value = expandedRow.value === id ? null : id; }
function deleteExpense(id) { if (!confirm('Delete this expense?')) return; router.delete(route('tenant.expenses.destroy', { tenant: slug, id })); }
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';
const confidenceColor = (c) => c === 'high' ? 'text-green-400' : c === 'medium' ? 'text-yellow-400' : 'text-red-400';

onMounted(() => { loadSettings(); });
</script>

<template>
    <Head title="Daily Expenses" />
    <TenantLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-white">Daily Expenses</h1>
                    <p class="text-sm text-slate-400 mt-1">Track all your operating costs · Upload receipts for AI extraction</p>
                </div>
                <div class="flex gap-3">
                    <button @click="openSettings" class="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-2 rounded-lg text-sm transition" title="Settings">
                        <Settings class="w-4 h-4" />
                    </button>
                    <button @click="openUpload" class="flex items-center gap-2 bg-purple-600 hover:bg-purple-500 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                        <Upload class="w-4 h-4" /> Upload
                    </button>
                    <button @click="showManual = true" class="flex items-center gap-2 bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg text-sm font-medium transition">
                        <Plus class="w-4 h-4" /> Add Expense
                    </button>
                </div>
            </div>

            <!-- KPI Tiles -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <KpiCard v-for="t in activeTiles" :key="t.key" :label="t.label.toUpperCase()" :value="tileValue(t.key)" />
            </div>

            <!-- Date Filters -->
            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <Calendar class="w-4 h-4 text-slate-400" />
                    <button v-for="p in datePresets" :key="p.key" @click="filterDatePreset(p.key)"
                        :class="['px-3 py-1 rounded-full text-xs font-medium transition', activeDatePreset === p.key ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700']">
                        {{ p.label }}
                    </button>
                    <template v-if="showCustomDates">
                        <input v-model="dateFrom" type="date" class="bg-slate-800 border border-slate-700 rounded-lg px-2 py-1 text-white text-xs" />
                        <span class="text-slate-500 text-xs">to</span>
                        <input v-model="dateTo" type="date" class="bg-slate-800 border border-slate-700 rounded-lg px-2 py-1 text-white text-xs" />
                        <button @click="applyCustomDates" class="px-3 py-1 rounded-full text-xs font-medium bg-purple-600 text-white">Apply</button>
                    </template>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Filter class="w-4 h-4 text-slate-400" />
                    <button v-for="c in ['all', ...categories.slice(0, 7)]" :key="c" @click="filterCategory(c)"
                        :class="['px-3 py-1 rounded-full text-xs font-medium transition', activeCategory === c ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700']">
                        {{ catLabels[c] || c }}
                    </button>
                    <span class="text-slate-600 mx-1">|</span>
                    <button v-for="s in ['all','manual','auto']" :key="s" @click="filterSource(s)"
                        :class="['px-3 py-1 rounded-full text-xs font-medium transition', activeSource === s ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700']">
                        {{ s }}
                    </button>
                </div>
            </div>

            <!-- P&L Breakdown -->
            <div class="bg-slate-900/50 border border-slate-800 rounded-xl overflow-hidden">
                <button @click="showPnl = !showPnl" class="w-full flex items-center justify-between px-4 py-3 text-sm font-medium text-white hover:bg-slate-800/30 transition">
                    <div class="flex items-center gap-2"><BarChart3 class="w-4 h-4 text-purple-400" /> Expense Breakdown by Category</div>
                    <component :is="showPnl ? ChevronUp : ChevronDown" class="w-4 h-4 text-slate-400" />
                </button>
                <div v-if="showPnl" class="px-4 pb-4 space-y-2">
                    <div v-for="item in pnl" :key="item.category" class="flex items-center gap-3 text-sm">
                        <span class="w-28 text-slate-300 truncate">{{ catLabels[item.category] || item.category }}</span>
                        <div class="flex-1 bg-slate-800 rounded-full h-5 overflow-hidden">
                            <div class="h-full bg-purple-600/60 rounded-full flex items-center px-2"
                                :style="{ width: Math.max((item.amount / pnlMaxAmount) * 100, 2) + '%' }">
                                <span class="text-xs text-white font-medium whitespace-nowrap">{{ fmt(item.amount) }}</span>
                            </div>
                        </div>
                        <span class="text-xs text-slate-500 w-12 text-right">{{ item.percent }}%</span>
                        <span class="text-xs text-slate-600 w-8 text-right">{{ item.count }}</span>
                    </div>
                    <div v-if="!pnl?.length" class="text-slate-500 text-sm py-2">No expenses in this period.</div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-slate-900/50 border border-slate-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 text-xs uppercase tracking-wider border-b border-slate-800">
                            <th class="px-4 py-3">Label</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Source</th><th class="px-4 py-3">Vendor</th>
                            <th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Date</th><th class="px-4 py-3 w-16"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="exp in expenses.data" :key="exp.id">
                            <tr @click="toggleExpand(exp.id)" :class="['border-b border-slate-800/50 hover:bg-slate-800/30 transition cursor-pointer', expandedRow === exp.id ? 'bg-slate-800/30' : '']">
                                <td class="px-4 py-3 text-white">
                                    <div class="flex items-center gap-2">
                                        <component :is="expandedRow === exp.id ? ChevronUp : ChevronDown" class="w-3.5 h-3.5 text-slate-500 flex-shrink-0" />
                                        <Paperclip v-if="exp.attachment_path" class="w-3.5 h-3.5 text-purple-400 flex-shrink-0" />
                                        {{ exp.label }}
                                    </div>
                                </td>
                                <td class="px-4 py-3"><span class="px-2 py-0.5 rounded-full text-xs bg-slate-800 text-slate-300">{{ catLabels[exp.category] || exp.category }}</span></td>
                                <td class="px-4 py-3 text-slate-400">{{ exp.source }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ exp.vendor || '—' }}</td>
                                <td class="px-4 py-3 text-right text-white font-medium">{{ fmt(exp.amount) }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ dateFmt(exp.occurred_at) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex gap-1" @click.stop>
                                        <a v-if="exp.attachment_path" :href="exp.attachment_path" target="_blank" class="p-1 hover:bg-slate-700 rounded text-slate-400 hover:text-purple-400 transition"><ExternalLink class="w-3.5 h-3.5" /></a>
                                        <button @click="deleteExpense(exp.id)" class="p-1 hover:bg-slate-700 rounded text-slate-400 hover:text-red-400 transition"><Trash2 class="w-3.5 h-3.5" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="expandedRow === exp.id">
                                <td colspan="7" class="px-4 py-4 bg-slate-800/20 border-b border-slate-800">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-w-4xl">
                                        <div v-if="exp.line_items?.length" class="space-y-2">
                                            <p class="text-xs text-slate-400 uppercase tracking-wider font-medium">Line Items</p>
                                            <div class="bg-slate-900/50 rounded-lg p-3 space-y-1.5">
                                                <div v-for="(item, i) in exp.line_items" :key="i" class="flex items-center justify-between text-sm">
                                                    <span class="text-slate-300"><span class="text-slate-500">{{ item.qty }}×</span> {{ item.description }} <span v-if="item.rate" class="text-slate-500 text-xs ml-1">@ {{ fmt(item.rate) }}</span></span>
                                                    <span class="text-white font-medium">{{ fmt(item.amount) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            <p class="text-xs text-slate-400 uppercase tracking-wider font-medium">Details</p>
                                            <div class="bg-slate-900/50 rounded-lg p-3 space-y-2 text-sm">
                                                <div v-if="exp.vendor" class="flex justify-between"><span class="text-slate-400">Vendor</span><span class="text-white">{{ exp.vendor }}</span></div>
                                                <div v-if="exp.extracted_data?.gst_amount" class="flex justify-between"><span class="text-slate-400">GST</span><span class="text-white">{{ fmt(exp.extracted_data.gst_amount) }} <span v-if="exp.extracted_data?.gst_rate" class="text-slate-500 text-xs">({{ exp.extracted_data.gst_rate }}%)</span></span></div>
                                                <div v-if="exp.notes" class="flex justify-between"><span class="text-slate-400">Notes</span><span class="text-slate-300 text-right max-w-xs">{{ exp.notes }}</span></div>
                                                <div v-if="exp.extracted_data?.confidence" class="flex justify-between"><span class="text-slate-400">AI Confidence</span><span :class="confidenceColor(exp.extracted_data.confidence)" class="capitalize">{{ exp.extracted_data.confidence }}</span></div>
                                                <div v-if="exp.attachment_path" class="pt-1"><a :href="exp.attachment_path" target="_blank" class="text-purple-400 hover:text-purple-300 text-xs flex items-center gap-1"><ExternalLink class="w-3 h-3" /> View attachment</a></div>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="!expenses.data?.length"><td colspan="7" class="px-4 py-12 text-center text-slate-500">No expenses yet. Upload a receipt or add manually.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ═══ Manual Modal ═══ -->
        <Teleport to="body">
            <div v-if="showManual" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showManual = false">
                <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-md p-6 space-y-4">
                    <div class="flex items-center justify-between"><h2 class="text-lg font-semibold text-white">Add Expense</h2><button @click="showManual = false" class="p-1 hover:bg-slate-800 rounded"><X class="w-5 h-5 text-slate-400" /></button></div>
                    <div class="space-y-3">
                        <div><label class="text-xs text-slate-400 block mb-1">Label</label><input v-model="form.label" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" placeholder="e.g. Office rent March" /></div>
                        <div class="grid grid-cols-2 gap-3">
                            <div><label class="text-xs text-slate-400 block mb-1">Amount (₹)</label><input v-model="form.amount" type="number" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" /></div>
                            <div><label class="text-xs text-slate-400 block mb-1">Date</label><input v-model="form.occurred_at" type="date" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" /></div>
                        </div>
                        <div><label class="text-xs text-slate-400 block mb-1">Category</label><select v-model="form.category" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm"><option v-for="c in categories" :key="c" :value="c">{{ catLabels[c] }}</option></select></div>
                    </div>
                    <button @click="submitManual" :disabled="form.processing" class="w-full bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white py-2 rounded-lg text-sm font-medium transition">Save Expense</button>
                </div>
            </div>
        </Teleport>

        <!-- ═══ Upload Modal ═══ -->
        <Teleport to="body">
            <div v-if="showUpload" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="closeUpload">
                <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-white">{{ { select:'Upload Receipt / Invoice', extracting:'Extracting Data...', title_prompt:'Add a Title', multi_preview:'Multiple Invoices Found', saving:'Saving...', preview:'Review & Save' }[uploadStep] }}</h2>
                        <button @click="closeUpload" class="p-1 hover:bg-slate-800 rounded"><X class="w-5 h-5 text-slate-400" /></button>
                    </div>
                    <div v-if="uploadError" class="flex items-center gap-2 bg-red-900/30 border border-red-800 text-red-300 px-3 py-2 rounded-lg text-sm"><AlertCircle class="w-4 h-4 flex-shrink-0" /> {{ uploadError }}</div>

                    <!-- Select -->
                    <div v-if="uploadStep === 'select'" class="space-y-3">
                        <div @dragover="onDragOver" @dragleave="onDragLeave" @drop="onDrop" :class="['border-2 border-dashed rounded-xl p-8 text-center transition cursor-pointer', dragOver ? 'border-purple-500 bg-purple-900/20' : 'border-slate-700 hover:border-slate-500']" @click="$refs.fileInput.click()">
                            <Upload class="w-8 h-8 text-slate-400 mx-auto mb-3" /><p class="text-white text-sm font-medium">Drop file here or click to browse</p><p class="text-slate-500 text-xs mt-1">Images, PDF, or CSV · Max 10MB</p>
                        </div>
                        <input ref="fileInput" type="file" class="hidden" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.csv" @change="onFileSelect" />
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="bg-slate-800/50 rounded-lg p-3"><Image class="w-5 h-5 text-purple-400 mx-auto mb-1" /><p class="text-xs text-slate-400">Photo of receipt</p></div>
                            <div class="bg-slate-800/50 rounded-lg p-3"><FileText class="w-5 h-5 text-green-400 mx-auto mb-1" /><p class="text-xs text-slate-400">PDF invoice</p></div>
                            <div class="bg-slate-800/50 rounded-lg p-3"><Table2 class="w-5 h-5 text-orange-400 mx-auto mb-1" /><p class="text-xs text-slate-400">CSV bulk import</p></div>
                        </div>
                    </div>

                    <!-- Extracting -->
                    <div v-if="uploadStep === 'extracting'" class="py-8 text-center"><Loader2 class="w-10 h-10 text-purple-400 mx-auto animate-spin" /><p class="text-white text-sm font-medium mt-4">AI is reading your file...</p><p class="text-slate-500 text-xs mt-1">{{ selectedFile?.name }}</p></div>

                    <!-- Title Prompt -->
                    <div v-if="uploadStep === 'title_prompt'" class="space-y-4">
                        <div class="bg-yellow-900/20 border border-yellow-800/50 rounded-lg p-3"><p class="text-yellow-300 text-sm">Couldn't identify a vendor. Please add a title.</p></div>
                        <div v-if="editForm.amount" class="bg-slate-800/50 rounded-lg p-3 space-y-1">
                            <p class="text-white text-sm">Amount: <span class="font-semibold">{{ fmt(editForm.amount) }}</span></p>
                            <div v-if="editForm.line_items?.length" class="mt-2"><div v-for="(item, i) in editForm.line_items" :key="i" class="text-xs text-slate-300">{{ item.qty }}× {{ item.description }} — {{ fmt(item.amount) }}</div></div>
                        </div>
                        <div><label class="text-xs text-slate-400 block mb-1">Title</label><input v-model="titleInput" @keydown.enter="confirmTitle" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" placeholder="e.g. Fabric from Gandhi Nagar" autofocus /></div>
                        <button @click="confirmTitle" :disabled="!titleInput.trim()" class="w-full bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white py-2 rounded-lg text-sm font-medium">Continue →</button>
                    </div>

                    <!-- Single Preview -->
                    <div v-if="uploadStep === 'preview'" class="space-y-4">
                        <div v-if="editForm.confidence" class="flex items-center gap-2 text-xs"><span class="text-slate-400">AI Confidence:</span><span :class="confidenceColor(editForm.confidence)" class="capitalize">{{ editForm.confidence }}</span></div>
                        <div class="space-y-3">
                            <div><label class="text-xs text-slate-400 block mb-1">Label</label><input v-model="editForm.label" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" /></div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><label class="text-xs text-slate-400 block mb-1">Amount (₹)</label><input v-model.number="editForm.amount" type="number" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" /></div>
                                <div><label class="text-xs text-slate-400 block mb-1">Date</label><input v-model="editForm.occurred_at" type="date" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" /></div>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div><label class="text-xs text-slate-400 block mb-1">Category</label><select v-model="editForm.category" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm"><option v-for="c in categories" :key="c" :value="c">{{ catLabels[c] }}</option></select></div>
                                <div><label class="text-xs text-slate-400 block mb-1">Vendor</label><input v-model="editForm.vendor" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm" /></div>
                            </div>
                            <div><label class="text-xs text-slate-400 block mb-1">Notes</label><textarea v-model="editForm.notes" rows="2" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm"></textarea></div>
                        </div>
                        <div v-if="editForm.line_items?.length" class="bg-slate-800/50 rounded-lg p-3"><p class="text-xs text-slate-400 mb-2 uppercase">Line Items</p><div v-for="(item, i) in editForm.line_items" :key="i" class="flex justify-between text-sm"><span class="text-slate-300">{{ item.qty }}× {{ item.description }}</span><span class="text-white font-medium">{{ fmt(item.amount) }}</span></div></div>
                        <div v-if="editForm.gst_amount" class="bg-slate-800/50 rounded-lg p-3 flex justify-between text-sm"><span class="text-slate-400">GST</span><span class="text-white">{{ fmt(editForm.gst_amount) }}</span></div>
                        <div class="flex gap-3">
                            <button @click="uploadStep = 'select'" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white py-2 rounded-lg text-sm font-medium">← Back</button>
                            <button @click="saveExpense" class="flex-1 bg-purple-600 hover:bg-purple-500 text-white py-2 rounded-lg text-sm font-medium flex items-center justify-center gap-2"><Check class="w-4 h-4" /> Save</button>
                        </div>
                    </div>

                    <!-- Multi Preview -->
                    <div v-if="uploadStep === 'multi_preview'" class="space-y-4">
                        <div class="bg-purple-900/20 border border-purple-800/50 rounded-lg p-3"><p class="text-purple-300 text-sm">Found {{ multiRows.length }} invoices. Select which to import.</p></div>
                        <div class="flex items-center justify-between text-sm"><span class="text-slate-400">{{ multiSelectedCount }} selected</span><span class="text-white font-medium">{{ fmt(multiSelectedTotal) }}</span></div>
                        <div class="space-y-3 max-h-80 overflow-y-auto">
                            <div v-for="(row, i) in multiRows" :key="i" :class="['border rounded-lg p-3 transition', row.selected ? 'border-purple-600 bg-purple-900/10' : 'border-slate-800 opacity-60']">
                                <div class="flex items-start gap-3">
                                    <input type="checkbox" v-model="row.selected" class="mt-1 rounded bg-slate-700 border-slate-600" />
                                    <div class="flex-1 space-y-2">
                                        <div class="flex justify-between"><input v-model="row.label" class="bg-transparent text-white text-sm font-medium border-b border-transparent hover:border-slate-600 focus:border-purple-500 outline-none flex-1 mr-2" /><span class="text-white font-semibold text-sm">{{ fmt(row.amount) }}</span></div>
                                        <div class="flex items-center gap-3 text-xs">
                                            <select v-model="row.category" class="bg-slate-800 border border-slate-700 rounded px-2 py-1 text-slate-300 text-xs"><option v-for="c in categories" :key="c" :value="c">{{ catLabels[c] }}</option></select>
                                            <span class="text-slate-500">{{ row.occurred_at || row.date }}</span>
                                            <span v-if="row.vendor" class="text-slate-400">{{ row.vendor }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button @click="uploadStep = 'select'" class="flex-1 bg-slate-700 hover:bg-slate-600 text-white py-2 rounded-lg text-sm font-medium">← Back</button>
                            <button @click="saveMultiExpenses" :disabled="!multiSelectedCount" class="flex-1 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white py-2 rounded-lg text-sm font-medium flex items-center justify-center gap-2"><Check class="w-4 h-4" /> Import {{ multiSelectedCount }}</button>
                        </div>
                    </div>

                    <!-- Saving -->
                    <div v-if="uploadStep === 'saving'" class="py-8 text-center"><Loader2 class="w-8 h-8 text-purple-400 mx-auto animate-spin" /><p class="text-white text-sm mt-3">Saving...</p></div>
                </div>
            </div>
        </Teleport>

        <!-- ═══ Settings Modal ═══ -->
        <Teleport to="body">
            <div v-if="showSettings" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" @click.self="showSettings = false">
                <div class="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-md p-6 space-y-5">
                    <div class="flex items-center justify-between"><h2 class="text-lg font-semibold text-white">Expense Settings</h2><button @click="showSettings = false" class="p-1 hover:bg-slate-800 rounded"><X class="w-5 h-5 text-slate-400" /></button></div>

                    <!-- Image Method -->
                    <div class="space-y-3">
                        <label class="text-xs text-slate-400 uppercase tracking-wider font-medium">Image Extraction</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button @click="imageMethod = 'ai'" :class="['flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition', imageMethod === 'ai' ? 'border-purple-500 bg-purple-900/20' : 'border-slate-700 hover:border-slate-600 bg-slate-800/30']">
                                <Eye class="w-6 h-6" :class="imageMethod === 'ai' ? 'text-purple-400' : 'text-slate-400'" />
                                <span class="text-sm font-medium" :class="imageMethod === 'ai' ? 'text-white' : 'text-slate-300'">AI Vision</span>
                                <span class="text-xs text-slate-500 text-center">Nemotron VL · Best for handwritten</span>
                            </button>
                            <button @click="imageMethod = 'tesseract'" :class="['flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition', imageMethod === 'tesseract' ? 'border-purple-500 bg-purple-900/20' : 'border-slate-700 hover:border-slate-600 bg-slate-800/30']">
                                <Cpu class="w-6 h-6" :class="imageMethod === 'tesseract' ? 'text-purple-400' : 'text-slate-400'" />
                                <span class="text-sm font-medium" :class="imageMethod === 'tesseract' ? 'text-white' : 'text-slate-300'">Tesseract</span>
                                <span class="text-xs text-slate-500 text-center">On-server · Printed text only</span>
                            </button>
                        </div>
                    </div>

                    <!-- Visible Tiles -->
                    <div class="space-y-3">
                        <label class="text-xs text-slate-400 uppercase tracking-wider font-medium">KPI Tiles to Show</label>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="t in allTiles" :key="t.key" @click="toggleTile(t.key)"
                                :class="['px-3 py-2 rounded-lg text-xs font-medium transition border', tempTiles.includes(t.key) ? 'border-purple-500 bg-purple-900/20 text-white' : 'border-slate-700 bg-slate-800/30 text-slate-400']">
                                {{ t.label }}
                            </button>
                        </div>
                        <p class="text-xs text-slate-500">Select which KPI cards appear at the top of the page.</p>
                    </div>

                    <button @click="saveSettings" :disabled="settingsSaving" class="w-full bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white py-2 rounded-lg text-sm font-medium transition">{{ settingsSaving ? 'Saving...' : 'Save Settings' }}</button>
                </div>
            </div>
        </Teleport>
    </TenantLayout>
</template>
