<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, X, AlertTriangle, Package, Search, RefreshCw, Pencil } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    items: { type: Object, default: () => ({ data: [] }) },
    totals: { type: Object, default: () => ({ total_items: 0, low_stock: 0, total_value: 0 }) },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    shopifyConnected: { type: Boolean, default: false },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const showForm = ref(false);
const searchQ = ref(props.filters.q || '');
const syncing = ref(false);
const syncMessage = ref('');

const form = useForm({
    name: '', sku: '', category: '', unit: 'pcs', quantity: 0,
    min_stock_level: 10, cost_price: '', selling_price: '', location: '', notes: '',
});

const showEditModal = ref(false);
const editingItem = ref(null);
const editForm = useForm({
    name: '', category: '', unit: 'pcs', quantity: 0,
    min_stock_level: 0, cost_price: '', selling_price: '', location: '', status: 'active', notes: '',
});

function openEdit(item, event) {
    event.stopPropagation();
    editingItem.value = item;
    editForm.name = item.name;
    editForm.category = item.category || '';
    editForm.unit = item.unit || 'pcs';
    editForm.quantity = item.quantity;
    editForm.min_stock_level = item.min_stock_level || 0;
    editForm.cost_price = item.cost_price;
    editForm.selling_price = item.selling_price || '';
    editForm.location = item.location || '';
    editForm.status = item.status || 'active';
    editForm.notes = item.notes || '';
    showEditModal.value = true;
}

function submitEdit() {
    editForm.put(route('tenant.inventory-mgmt.update', { tenant: slug, id: editingItem.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingItem.value = null;
            router.reload({ only: ['items', 'totals'] });
        },
    });
}

function submit() {
    form.post(route('tenant.inventory-mgmt.store', { tenant: slug }), {
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
}

function doSearch() {
    router.get(route('tenant.inventory-mgmt.index', { tenant: slug }), { q: searchQ.value }, { preserveState: true });
}

function goToPage(url) {
    if (!url) return;
    try {
        const p = new URL(url);
        router.get(p.pathname + p.search, {}, { preserveState: true, preserveScroll: true });
    } catch (e) {
        router.visit(url, { preserveState: true, preserveScroll: true });
    }
}

async function syncFromShopify() {
    if (syncing.value) return;
    syncing.value = true;
    syncMessage.value = '';
    try {
        const res = await fetch(route('tenant.inventory-mgmt.sync-shopify', { tenant: slug }), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
            },
        });
        const data = await res.json();
        syncMessage.value = data.message || data.error || 'Sync started.';
        if (res.ok) {
            setTimeout(() => router.reload({ only: ['items', 'totals', 'categories'] }), 3000);
        }
    } catch (e) {
        syncMessage.value = 'Could not start sync. Please try again.';
    } finally {
        syncing.value = false;
    }
}
</script>

<template>
<Head title="Inventory" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Inventory</h2>
            <p class="text-[12px] text-ink-3 mt-1">Track stock levels and movements</p>
        </div>
        <div class="flex items-center gap-2">
            <button v-if="shopifyConnected" @click="syncFromShopify"
                    :disabled="syncing"
                    class="btn btn-ghost flex items-center gap-1.5 text-[12.5px] cursor-pointer disabled:opacity-50">
                <RefreshCw :size="13" :class="syncing ? 'animate-spin' : ''" />
                {{ syncing ? 'Syncing…' : 'Sync from Shopify' }}
            </button>
            <button class="btn btn-primary" @click="showForm = true"><Plus :size="14" /> Add Item</button>
        </div>
    </div>

    <div v-if="syncMessage" class="mb-4 rounded-lg px-3 py-2 text-[12px] bg-brand-600/10 border border-brand-600/30 text-brand-300">
        {{ syncMessage }}
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <KpiCard label="Total Items" :value="totals.total_items" format="number" />
        <KpiCard label="Low Stock" :value="totals.low_stock" format="number" :tone="totals.low_stock > 0 ? 'bad' : 'good'" />
        <KpiCard label="Stock Value" :value="totals.total_value" format="currency" />
    </div>

    <!-- Search -->
    <div class="flex gap-3 mb-4">
        <div class="relative flex-1">
            <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
            <input v-model="searchQ" @keyup.enter="doSearch" class="heyd2c-input pl-9" placeholder="Search by name or SKU…" />
        </div>
    </div>

    <!-- Add Item Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showForm = false">
        <div class="card w-full max-w-lg mx-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">Add Inventory Item</h3>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showForm = false"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submit" class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Name *</label><input v-model="form.name" class="heyd2c-input" /><div v-if="form.errors.name" class="mt-1 text-[11px] text-rose">{{ form.errors.name }}</div></div>
                    <div><label class="heyd2c-label">SKU *</label><input v-model="form.sku" class="heyd2c-input font-mono" /><div v-if="form.errors.sku" class="mt-1 text-[11px] text-rose">{{ form.errors.sku }}</div></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="heyd2c-label">Category</label><input v-model="form.category" class="heyd2c-input" placeholder="Raw Material" /></div>
                    <div><label class="heyd2c-label">Unit</label><select v-model="form.unit" class="heyd2c-input"><option v-for="u in ['pcs','kg','litre','box','m','pair']" :key="u" :value="u">{{ u }}</option></select></div>
                    <div><label class="heyd2c-label">Location</label><input v-model="form.location" class="heyd2c-input" placeholder="Rack A1" /></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="heyd2c-label">Quantity</label><input v-model.number="form.quantity" type="number" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Cost Price (₹)</label><input v-model.number="form.cost_price" type="number" step="0.01" class="heyd2c-input font-mono" /></div>
                    <div><label class="heyd2c-label">Selling Price (₹)</label><input v-model.number="form.selling_price" type="number" step="0.01" class="heyd2c-input font-mono" /></div>
                </div>
                <div><label class="heyd2c-label">Min Stock Level</label><input v-model.number="form.min_stock_level" type="number" class="heyd2c-input w-32" /></div>
                <button type="submit" class="btn btn-primary w-full py-2.5" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Add Item' }}</button>
            </form>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showEditModal = false">
        <div class="card w-full max-w-lg mx-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-[16px] font-bold text-white">Edit Item</h3>
                    <p class="text-[11px] text-ink-3 mt-0.5">{{ editingItem?.sku }}</p>
                </div>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showEditModal = false"><X :size="18" /></button>
            </div>
            <div v-if="props.shopifyConnected && editingItem?.sku" class="mb-3 rounded-lg bg-brand-600/10 border border-brand-600/30 px-3 py-2 text-[11.5px] text-brand-300">
                Quantity changes will automatically sync to Shopify.
            </div>
            <form @submit.prevent="submitEdit" class="space-y-3">
                <div><label class="heyd2c-label">Name</label><input v-model="editForm.name" class="heyd2c-input" /></div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="heyd2c-label">Category</label><input v-model="editForm.category" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Unit</label><select v-model="editForm.unit" class="heyd2c-input"><option v-for="u in ['pcs','kg','litre','box','m','pair']" :key="u" :value="u">{{ u }}</option></select></div>
                    <div><label class="heyd2c-label">Location</label><input v-model="editForm.location" class="heyd2c-input" /></div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="heyd2c-label">Quantity</label>
                        <input v-model.number="editForm.quantity" type="number" min="0" class="heyd2c-input" />
                    </div>
                    <div><label class="heyd2c-label">Cost Price (₹)</label><input v-model.number="editForm.cost_price" type="number" step="0.01" class="heyd2c-input font-mono" /></div>
                    <div><label class="heyd2c-label">Selling Price (₹)</label><input v-model.number="editForm.selling_price" type="number" step="0.01" class="heyd2c-input font-mono" /></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="heyd2c-label">Min Stock Level</label><input v-model.number="editForm.min_stock_level" type="number" min="0" class="heyd2c-input" /></div>
                    <div><label class="heyd2c-label">Status</label><select v-model="editForm.status" class="heyd2c-input"><option value="active">Active</option><option value="discontinued">Discontinued</option></select></div>
                </div>
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
                    <th class="text-left px-5 py-3">Category</th>
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
                    class="hover:bg-brand-600/5 transition cursor-pointer"
                    :class="{ 'bg-rose/5': item.quantity <= item.min_stock_level }"
                    @click="router.visit(route('tenant.inventory-mgmt.show', { tenant: slug, id: item.id }))">
                    <td class="px-5 py-3">
                        <div class="font-medium text-white flex items-center gap-2">
                            {{ item.name }}
                            <AlertTriangle v-if="item.quantity <= item.min_stock_level" :size="13" class="text-rose" />
                        </div>
                    </td>
                    <td class="px-5 py-3 font-mono text-[11px] text-ink-3">{{ item.sku }}</td>
                    <td class="px-5 py-3 text-ink-2">{{ item.category || '—' }}</td>
                    <td class="px-5 py-3 text-center font-mono" :class="item.quantity <= item.min_stock_level ? 'text-rose font-bold' : 'text-ink'">{{ item.quantity }} {{ item.unit }}</td>
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
                    <td colspan="9" class="px-5 py-8 text-center text-ink-3">No inventory items yet.</td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="items.last_page > 1"
             class="px-5 py-3 border-t border-frost-1 text-[12px] text-ink-3 flex items-center justify-between">
            <span>Page {{ items.current_page }} of {{ items.last_page }} · {{ items.total }} items</span>
            <div class="flex gap-1">
                <button class="btn btn-ghost btn-sm cursor-pointer"
                        :disabled="!items.prev_page_url"
                        @click="goToPage(items.prev_page_url)">← Prev</button>
                <button class="btn btn-ghost btn-sm cursor-pointer"
                        :disabled="!items.next_page_url"
                        @click="goToPage(items.next_page_url)">Next →</button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
