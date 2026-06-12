<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, X, AlertTriangle, Package, Search } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    items: { type: Object, default: () => ({ data: [] }) },
    totals: { type: Object, default: () => ({ total_items: 0, low_stock: 0, total_value: 0 }) },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const showForm = ref(false);
const searchQ = ref(props.filters.q || '');

const form = useForm({
    name: '', sku: '', category: '', unit: 'pcs', quantity: 0,
    min_stock_level: 10, cost_price: '', selling_price: '', location: '', notes: '',
});

function submit() {
    form.post(route('tenant.inventory-mgmt.store', { tenant: slug }), {
        onSuccess: () => { showForm.value = false; form.reset(); },
    });
}

function doSearch() {
    router.get(route('tenant.inventory-mgmt.index', { tenant: slug }), { q: searchQ.value }, { preserveState: true });
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
        <button class="btn btn-primary" @click="showForm = true"><Plus :size="14" /> Add Item</button>
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
                </tr>
                <tr v-if="!items.data?.length">
                    <td colspan="8" class="px-5 py-8 text-center text-ink-3">No inventory items yet.</td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
