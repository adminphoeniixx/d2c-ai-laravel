<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, X, AlertTriangle, Search, Pencil, Package } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    items:   { type: Object, default: () => ({ data: [] }) },
    totals:  { type: Object, default: () => ({ total_items: 0, low_stock: 0, total_value: 0 }) },
    filters: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt  = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));

// Add form
const showForm = ref(false);
const form = useForm({
    name: '', sku: '', category: 'packaging', unit: 'pcs',
    quantity: 0, min_stock_level: 10, cost_price: '', selling_price: '', location: '', notes: '',
});

function submit() {
    form.post(route('tenant.packaging.inventory.store', { tenant: slug }), {
        onSuccess: () => { showForm.value = false; form.reset(); form.category = 'packaging'; router.reload(); },
    });
}

// Edit form
const showEditModal = ref(false);
const editingItem   = ref(null);
const editForm = useForm({
    name: '', category: 'packaging', unit: 'pcs', quantity: 0,
    min_stock_level: 0, cost_price: '', selling_price: '', location: '', status: 'active', notes: '',
});

function openEdit(item, e) {
    e.stopPropagation();
    editingItem.value = item;
    Object.assign(editForm, {
        name: item.name, category: 'packaging', unit: item.unit || 'pcs',
        quantity: item.quantity, min_stock_level: item.min_stock_level || 0,
        cost_price: item.cost_price, selling_price: item.selling_price || '',
        location: item.location || '', status: item.status || 'active', notes: item.notes || '',
    });
    showEditModal.value = true;
}

function submitEdit() {
    editForm.put(route('tenant.packaging.inventory.update', { tenant: slug, id: editingItem.value.id }), {
        preserveScroll: true,
        onSuccess: () => { showEditModal.value = false; editingItem.value = null; router.reload(); },
    });
}

const searchQ = ref(props.filters.q || '');
function doSearch() {
    router.get(route('tenant.packaging.inventory', { tenant: slug }), { q: searchQ.value }, { preserveState: true });
}
</script>

<template>
<Head title="Packaging Inventory" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-2.5">
            <div class="h-9 w-9 rounded-xl bg-brand-600/20 border border-brand-600/40 flex items-center justify-center">
                <Package :size="18" class="text-brand-300" />
            </div>
            <div>
                <h2 class="text-[20px] font-bold text-white">Packaging Inventory</h2>
                <p class="text-[12px] text-ink-3 mt-0.5">Boxes, tape, bubble wrap, labels, and other packaging materials</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a :href="route('tenant.purchase-orders.create', { tenant: slug })"
               class="btn btn-ghost flex items-center gap-1.5 text-[12.5px] cursor-pointer">
                + Create Purchase Order
            </a>
            <button class="btn btn-primary flex items-center gap-1.5" @click="showForm = true">
                <Plus :size="14" /> Add Item
            </button>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <KpiCard label="Packaging Items" :value="totals.total_items" format="number" />
        <KpiCard label="Low Stock" :value="totals.low_stock" format="number" :tone="totals.low_stock > 0 ? 'bad' : 'good'" />
        <KpiCard label="Stock Value" :value="totals.total_value" format="currency" />
    </div>

    <!-- Search -->
    <div class="flex gap-3 mb-4">
        <div class="relative flex-1">
            <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
            <input v-model="searchQ" @keyup.enter="doSearch" class="heyd2c-input pl-9" placeholder="Search packaging items…" />
        </div>
    </div>

    <!-- Add Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showForm = false">
        <div class="card w-full max-w-lg mx-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">Add Packaging Item</h3>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showForm = false"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submit" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Name *</label><input v-model="form.name" class="heyd2c-input" placeholder="Corrugated Box 12x8x6" required /></div>
                    <div><label class="heyd2c-label">SKU</label><input v-model="form.sku" class="heyd2c-input font-mono" placeholder="PKG-BOX-001" /></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="heyd2c-label">Unit</label><select v-model="form.unit" class="heyd2c-input"><option v-for="u in ['pcs','roll','sheet','kg','box','pack']" :key="u" :value="u">{{ u }}</option></select></div>
                    <div><label class="heyd2c-label">Quantity</label><input v-model.number="form.quantity" type="number" min="0" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Min Level</label><input v-model.number="form.min_stock_level" type="number" min="0" class="heyd2c-input" /></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Cost Price (₹)</label><input v-model.number="form.cost_price" type="number" step="0.01" class="heyd2c-input font-mono" /></div>
                    <div><label class="heyd2c-label">Location</label><input v-model="form.location" class="heyd2c-input" placeholder="Warehouse Shelf B3" /></div>
                </div>
                <div><label class="heyd2c-label">Notes</label><textarea v-model="form.notes" class="heyd2c-input" rows="2" /></div>
                <button type="submit" class="btn btn-primary w-full py-2.5" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Add Packaging Item' }}</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showEditModal = false">
        <div class="card w-full max-w-lg mx-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">Edit — {{ editingItem?.name }}</h3>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showEditModal = false"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submitEdit" class="space-y-3">
                <div><label class="heyd2c-label">Name</label><input v-model="editForm.name" class="heyd2c-input" /></div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="heyd2c-label">Unit</label><select v-model="editForm.unit" class="heyd2c-input"><option v-for="u in ['pcs','roll','sheet','kg','box','pack']" :key="u" :value="u">{{ u }}</option></select></div>
                    <div><label class="heyd2c-label">Quantity</label><input v-model.number="editForm.quantity" type="number" min="0" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Min Level</label><input v-model.number="editForm.min_stock_level" type="number" min="0" class="heyd2c-input" /></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Cost Price (₹)</label><input v-model.number="editForm.cost_price" type="number" step="0.01" class="heyd2c-input font-mono" /></div>
                    <div><label class="heyd2c-label">Location</label><input v-model="editForm.location" class="heyd2c-input" /></div>
                </div>
                <div><label class="heyd2c-label">Status</label><select v-model="editForm.status" class="heyd2c-input"><option value="active">Active</option><option value="discontinued">Discontinued</option></select></div>
                <div class="flex gap-2 pt-1">
                    <button type="submit" class="btn btn-primary flex-1" :disabled="editForm.processing">{{ editForm.processing ? 'Saving…' : 'Save Changes' }}</button>
                    <button type="button" class="btn btn-ghost flex-1" @click="showEditModal = false">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">Item</th>
                    <th class="text-left px-5 py-3">SKU</th>
                    <th class="text-center px-5 py-3">Qty</th>
                    <th class="text-center px-5 py-3">Min</th>
                    <th class="text-right px-5 py-3">Cost</th>
                    <th class="text-right px-5 py-3">Value</th>
                    <th class="text-left px-5 py-3">Location</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="item in items.data" :key="item.id"
                    class="hover:bg-brand-600/5 transition"
                    :class="{ 'bg-rose/5': item.quantity <= item.min_stock_level }">
                    <td class="px-5 py-3">
                        <div class="font-medium text-white flex items-center gap-2">
                            {{ item.name }}
                            <AlertTriangle v-if="item.quantity <= item.min_stock_level" :size="13" class="text-rose" />
                        </div>
                    </td>
                    <td class="px-5 py-3 font-mono text-[11px] text-ink-3">{{ item.sku || '—' }}</td>
                    <td class="px-5 py-3 text-center font-mono"
                        :class="item.quantity <= item.min_stock_level ? 'text-rose font-bold' : 'text-ink'">
                        {{ item.quantity }} {{ item.unit }}
                    </td>
                    <td class="px-5 py-3 text-center text-ink-3 font-mono">{{ item.min_stock_level }}</td>
                    <td class="px-5 py-3 text-right font-mono text-ink-2">{{ fmt(item.cost_price) }}</td>
                    <td class="px-5 py-3 text-right font-mono text-white">{{ fmt(item.quantity * item.cost_price) }}</td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ item.location || '—' }}</td>
                    <td class="px-5 py-3">
                        <button @click="openEdit(item, $event)"
                                class="p-1.5 rounded-lg text-ink-3 hover:text-white hover:bg-brand-600/20 transition cursor-pointer">
                            <Pencil :size="13" />
                        </button>
                    </td>
                </tr>
                <tr v-if="!items.data?.length">
                    <td colspan="8" class="px-5 py-12 text-center">
                        <div class="text-[36px] mb-3">📦</div>
                        <div class="text-[14px] font-medium text-white mb-1">No packaging items yet</div>
                        <div class="text-[12px] text-ink-3">Add boxes, tape, bubble wrap, labels and other packaging materials to track stock levels.</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
