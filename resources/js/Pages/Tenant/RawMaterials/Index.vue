<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, X, AlertTriangle, Search, Pencil, Trash2, ArrowRightLeft } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    items:      { type: Object, default: () => ({ data: [] }) },
    totals:     { type: Object, default: () => ({ total_items: 0, low_stock: 0, total_value: 0 }) },
    categories: { type: Array,  default: () => [] },
    filters:    { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt  = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const fmtQ = (v, u) => (parseFloat(v) || 0).toFixed(3).replace(/\.?0+$/, '') + ' ' + (u || '');

// Add form
const showForm = ref(false);
const form = useForm({
    name: '', sku: '', category: '', unit: 'kg', quantity: 0,
    reorder_level: 0, cost_per_unit: '', supplier: '', location: '', notes: '',
});
function submit() {
    form.post(route('tenant.raw-materials.store', { tenant: slug }), {
        onSuccess: () => { showForm.value = false; form.reset(); router.reload(); },
    });
}

// Edit form
const showEdit = ref(false);
const editItem = ref(null);
const editForm = useForm({
    name: '', sku: '', category: '', unit: 'kg', reorder_level: 0,
    cost_per_unit: '', supplier: '', location: '', status: 'active', notes: '',
});
function openEdit(item, e) {
    e.stopPropagation();
    editItem.value = item;
    Object.assign(editForm, {
        name: item.name, sku: item.sku || '', category: item.category || '',
        unit: item.unit || 'kg', reorder_level: item.reorder_level || 0,
        cost_per_unit: item.cost_per_unit, supplier: item.supplier || '',
        location: item.location || '', status: item.status || 'active', notes: item.notes || '',
    });
    showEdit.value = true;
}
function submitEdit() {
    editForm.put(route('tenant.raw-materials.update', { tenant: slug, id: editItem.value.id }), {
        preserveScroll: true,
        onSuccess: () => { showEdit.value = false; editItem.value = null; router.reload(); },
    });
}

// Transaction form
const showTxn = ref(false);
const txnItem = ref(null);
const txnForm = useForm({
    type: 'in', quantity: '', cost_per_unit: '',
    transaction_date: new Date().toISOString().slice(0, 10),
    reference: '', reason: 'purchase', notes: '',
});
function openTxn(item, e) {
    e.stopPropagation();
    txnItem.value = item;
    txnForm.cost_per_unit = item.cost_per_unit;
    txnForm.type = 'in';
    showTxn.value = true;
}
function submitTxn() {
    txnForm.post(route('tenant.raw-materials.transactions.store', { tenant: slug, id: txnItem.value.id }), {
        preserveScroll: true,
        onSuccess: () => { showTxn.value = false; txnItem.value = null; txnForm.reset(); router.reload(); },
    });
}

function deleteItem(item) {
    if (!confirm(`Delete "${item.name}"?`)) return;
    router.delete(route('tenant.raw-materials.destroy', { tenant: slug, id: item.id }), { preserveScroll: true });
}

const searchQ = ref(props.filters.q || '');
function doSearch() {
    router.get(route('tenant.raw-materials.index', { tenant: slug }), { q: searchQ.value }, { preserveState: true });
}
function goToPage(url) {
    if (!url) return;
    try { const p = new URL(url); router.get(p.pathname + p.search, {}, { preserveState: true, preserveScroll: true }); }
    catch (e) { router.visit(url, { preserveState: true, preserveScroll: true }); }
}

const units = ['kg', 'gram', 'litre', 'ml', 'pcs', 'metre', 'cm', 'box', 'roll', 'sheet', 'bag'];
const reasons = ['purchase', 'usage', 'wastage', 'adjustment', 'return', 'opening_stock'];
</script>

<template>
<Head title="Raw Materials" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Raw Materials</h2>
            <p class="text-[12px] text-ink-3 mt-1">Track raw material inventory and usage</p>
        </div>
        <button class="btn btn-primary flex items-center gap-1.5" @click="showForm = true">
            <Plus :size="14" /> Add Material
        </button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <KpiCard label="Total Materials" :value="totals.total_items" format="number" />
        <KpiCard label="Low Stock" :value="totals.low_stock" format="number" :tone="totals.low_stock > 0 ? 'bad' : 'good'" />
        <KpiCard label="Stock Value" :value="totals.total_value" format="currency" />
    </div>

    <div class="flex gap-3 mb-4">
        <div class="relative flex-1">
            <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
            <input v-model="searchQ" @keyup.enter="doSearch" class="heyd2c-input pl-9" placeholder="Search by name or SKU…" />
        </div>
        <select class="heyd2c-input w-40"
            :value="filters.category || ''"
            @change="router.get(route('tenant.raw-materials.index', { tenant: slug }), { category: $event.target.value }, { preserveState: true })">
            <option value="">All Categories</option>
            <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
    </div>

    <!-- Add Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showForm = false">
        <div class="card w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">Add Raw Material</h3>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showForm = false"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submit" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Name *</label><input v-model="form.name" class="heyd2c-input" placeholder="Cotton Fabric" required /></div>
                    <div><label class="heyd2c-label">SKU</label><input v-model="form.sku" class="heyd2c-input font-mono" placeholder="RM-001" /></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Category</label><input v-model="form.category" class="heyd2c-input" placeholder="Fabric, Dye, Thread…" /></div>
                    <div><label class="heyd2c-label">Unit</label><select v-model="form.unit" class="heyd2c-input"><option v-for="u in units" :key="u">{{ u }}</option></select></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="heyd2c-label">Opening Qty</label><input v-model.number="form.quantity" type="number" step="0.001" min="0" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Reorder Level</label><input v-model.number="form.reorder_level" type="number" step="0.001" min="0" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Cost / Unit (₹)</label><input v-model.number="form.cost_per_unit" type="number" step="0.01" min="0" class="heyd2c-input font-mono" /></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Supplier</label><input v-model="form.supplier" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Location</label><input v-model="form.location" class="heyd2c-input" placeholder="Shelf A2" /></div>
                </div>
                <div><label class="heyd2c-label">Notes</label><textarea v-model="form.notes" class="heyd2c-input" rows="2" /></div>
                <button type="submit" class="btn btn-primary w-full" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Add Raw Material' }}</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showEdit = false">
        <div class="card w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">Edit — {{ editItem?.name }}</h3>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showEdit = false"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submitEdit" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Name</label><input v-model="editForm.name" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">SKU</label><input v-model="editForm.sku" class="heyd2c-input font-mono" /></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Category</label><input v-model="editForm.category" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Unit</label><select v-model="editForm.unit" class="heyd2c-input"><option v-for="u in units" :key="u">{{ u }}</option></select></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="heyd2c-label">Reorder Level</label><input v-model.number="editForm.reorder_level" type="number" step="0.001" min="0" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Cost / Unit (₹)</label><input v-model.number="editForm.cost_per_unit" type="number" step="0.01" min="0" class="heyd2c-input font-mono" /></div>
                    <div><label class="heyd2c-label">Status</label><select v-model="editForm.status" class="heyd2c-input"><option value="active">Active</option><option value="discontinued">Discontinued</option></select></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Supplier</label><input v-model="editForm.supplier" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Location</label><input v-model="editForm.location" class="heyd2c-input" /></div>
                </div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-primary flex-1" :disabled="editForm.processing">Save</button>
                    <button type="button" class="btn btn-ghost flex-1" @click="showEdit = false">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transaction Modal -->
    <div v-if="showTxn" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showTxn = false">
        <div class="card w-full max-w-md mx-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-[16px] font-bold text-white">Record Transaction</h3>
                    <p class="text-[11px] text-ink-3 mt-0.5">{{ txnItem?.name }} · Current: {{ fmtQ(txnItem?.quantity, txnItem?.unit) }}</p>
                </div>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showTxn = false"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submitTxn" class="space-y-3">
                <!-- In / Out toggle -->
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="txnForm.type = 'in'"
                        :class="txnForm.type === 'in' ? 'btn btn-primary' : 'btn btn-ghost'"
                        class="cursor-pointer">
                        ↑ Stock In
                    </button>
                    <button type="button" @click="txnForm.type = 'out'"
                        :class="txnForm.type === 'out' ? 'bg-rose-500/20 border-rose-500/50 text-rose-400 rounded-lg border px-4 py-2' : 'btn btn-ghost'"
                        class="cursor-pointer">
                        ↓ Stock Out
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="heyd2c-label">Quantity ({{ txnItem?.unit }}) *</label>
                        <input v-model.number="txnForm.quantity" type="number" step="0.001" min="0.001" class="heyd2c-input" required />
                    </div>
                    <div>
                        <label class="heyd2c-label">Cost / Unit (₹)</label>
                        <input v-model.number="txnForm.cost_per_unit" type="number" step="0.01" min="0" class="heyd2c-input font-mono" />
                    </div>
                </div>
                <div>
                    <label class="heyd2c-label">Date *</label>
                    <input v-model="txnForm.transaction_date" type="date" class="heyd2c-input" required />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="heyd2c-label">Reason</label>
                        <select v-model="txnForm.reason" class="heyd2c-input">
                            <option v-for="r in reasons" :key="r" :value="r">{{ r.replace('_', ' ') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="heyd2c-label">Reference</label>
                        <input v-model="txnForm.reference" class="heyd2c-input" placeholder="PO#, Invoice#…" />
                    </div>
                </div>
                <div><label class="heyd2c-label">Notes</label><textarea v-model="txnForm.notes" class="heyd2c-input" rows="2" /></div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-primary flex-1" :disabled="txnForm.processing">
                        {{ txnForm.processing ? 'Saving…' : 'Record Transaction' }}
                    </button>
                    <button type="button" class="btn btn-ghost flex-1" @click="showTxn = false">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">Material</th>
                    <th class="text-left px-5 py-3">Category</th>
                    <th class="text-center px-5 py-3">Qty</th>
                    <th class="text-center px-5 py-3">Reorder</th>
                    <th class="text-right px-5 py-3">Cost/Unit</th>
                    <th class="text-right px-5 py-3">Value</th>
                    <th class="text-left px-5 py-3">Supplier</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="item in items.data" :key="item.id"
                    class="hover:bg-brand-600/5 transition cursor-pointer"
                    :class="{ 'bg-rose/5': item.quantity <= item.reorder_level && item.reorder_level > 0 }"
                    @click="router.visit(route('tenant.raw-materials.transactions', { tenant: slug, id: item.id }))">
                    <td class="px-5 py-3">
                        <div class="font-medium text-white flex items-center gap-2">
                            {{ item.name }}
                            <AlertTriangle v-if="item.quantity <= item.reorder_level && item.reorder_level > 0" :size="13" class="text-rose" />
                        </div>
                        <div class="text-[10px] text-ink-3 font-mono mt-0.5">{{ item.sku || '' }}</div>
                    </td>
                    <td class="px-5 py-3 text-ink-2">{{ item.category || '—' }}</td>
                    <td class="px-5 py-3 text-center font-mono"
                        :class="(item.quantity <= item.reorder_level && item.reorder_level > 0) ? 'text-rose font-bold' : 'text-ink'">
                        {{ fmtQ(item.quantity, item.unit) }}
                    </td>
                    <td class="px-5 py-3 text-center text-ink-3 font-mono">{{ fmtQ(item.reorder_level, item.unit) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-ink-2">{{ fmt(item.cost_per_unit) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-white">{{ fmt(item.quantity * item.cost_per_unit) }}</td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ item.supplier || '—' }}</td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-1">
                            <button @click="openTxn(item, $event)"
                                class="p-1.5 rounded-lg text-ink-3 hover:text-emerald-400 hover:bg-emerald-500/10 transition cursor-pointer"
                                title="Add Transaction">
                                <ArrowRightLeft :size="13" />
                            </button>
                            <button @click="openEdit(item, $event)"
                                class="p-1.5 rounded-lg text-ink-3 hover:text-white hover:bg-brand-600/20 transition cursor-pointer">
                                <Pencil :size="13" />
                            </button>
                            <button @click="deleteItem(item)"
                                class="p-1.5 rounded-lg text-ink-3 hover:text-rose-400 hover:bg-rose-500/10 transition cursor-pointer">
                                <Trash2 :size="13" />
                            </button>
                        </div>
                    </td>
                </tr>
                <tr v-if="!items.data?.length">
                    <td colspan="8" class="px-5 py-12 text-center">
                        <div class="text-[36px] mb-3">🧵</div>
                        <div class="text-[14px] font-medium text-white mb-1">No raw materials yet</div>
                        <div class="text-[12px] text-ink-3">Add fabrics, dyes, threads, chemicals or any raw material you use in production.</div>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-if="items.last_page > 1" class="px-5 py-3 border-t border-frost-1 text-[12px] text-ink-3 flex items-center justify-between">
            <span>Page {{ items.current_page }} of {{ items.last_page }} · {{ items.total }} items</span>
            <div class="flex gap-1">
                <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!items.prev_page_url" @click="goToPage(items.prev_page_url)">← Prev</button>
                <button class="btn btn-ghost btn-sm cursor-pointer" :disabled="!items.next_page_url" @click="goToPage(items.next_page_url)">Next →</button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
