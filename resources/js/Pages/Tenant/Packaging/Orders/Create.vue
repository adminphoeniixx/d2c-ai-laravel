<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Plus, Trash2, ArrowLeft } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    packagingItems: { type: Array, default: () => [] },
    nextPoNumber:   { type: String, default: 'PKG-0001' },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));

const form = useForm({
    supplier_name: '',
    order_date:    new Date().toISOString().slice(0, 10),
    expected_date: '',
    notes:         '',
    items:         [{ packaging_item_id: null, item_name: '', unit: 'pcs', quantity: 1, unit_price: '' }],
});

function addLine() {
    form.items.push({ packaging_item_id: null, item_name: '', unit: 'pcs', quantity: 1, unit_price: '' });
}
function removeLine(i) {
    if (form.items.length > 1) form.items.splice(i, 1);
}
function onItemSelect(i, event) {
    const itemId = parseInt(event.target.value);
    const pkg = props.packagingItems.find(p => p.id === itemId);
    if (pkg) {
        form.items[i].packaging_item_id = pkg.id;
        form.items[i].item_name = pkg.name;
        form.items[i].unit = pkg.unit || 'pcs';
        form.items[i].unit_price = pkg.cost_price;
    }
}

const subtotal = computed(() =>
    form.items.reduce((sum, item) => sum + (parseFloat(item.quantity || 0) * parseFloat(item.unit_price || 0)), 0)
);

function submit() {
    form.post(route('tenant.packaging.orders.store', { tenant: slug }), {
        onSuccess: () => router.visit(route('tenant.packaging.orders.index', { tenant: slug })),
    });
}
</script>

<template>
<Head title="Create Packaging PO" />
<TenantLayout>
    <div class="flex items-center gap-3 mb-6">
        <button @click="router.visit(route('tenant.packaging.orders.index', { tenant: slug }))"
                class="text-ink-3 hover:text-white transition cursor-pointer">
            <ArrowLeft :size="18" />
        </button>
        <div>
            <h2 class="text-[20px] font-bold text-white">New Packaging Purchase Order</h2>
            <p class="text-[12px] text-ink-3 mt-0.5">PO # will be assigned as <span class="font-mono text-brand-300">{{ nextPoNumber }}</span></p>
        </div>
    </div>

    <form @submit.prevent="submit" class="space-y-5">
        <!-- Header details -->
        <div class="card space-y-4">
            <h3 class="text-[14px] font-semibold text-white">Order Details</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="heyd2c-label">Supplier Name</label>
                    <input v-model="form.supplier_name" class="heyd2c-input" placeholder="Supplier / vendor name" />
                </div>
                <div>
                    <label class="heyd2c-label">Order Date *</label>
                    <input v-model="form.order_date" type="date" class="heyd2c-input" required />
                </div>
                <div>
                    <label class="heyd2c-label">Expected Delivery</label>
                    <input v-model="form.expected_date" type="date" class="heyd2c-input" />
                </div>
            </div>
            <div>
                <label class="heyd2c-label">Notes</label>
                <textarea v-model="form.notes" class="heyd2c-input" rows="2" placeholder="Any special instructions or notes…" />
            </div>
        </div>

        <!-- Line items -->
        <div class="card">
            <h3 class="text-[14px] font-semibold text-white mb-4">Items</h3>

            <div class="space-y-3">
                <div v-for="(item, i) in form.items" :key="i"
                     class="grid grid-cols-12 gap-2 items-start">
                    <!-- Item picker -->
                    <div class="col-span-4">
                        <label v-if="i === 0" class="heyd2c-label">Item</label>
                        <select @change="onItemSelect(i, $event)" class="heyd2c-input">
                            <option value="">— Select packaging item or type below</option>
                            <option v-for="pkg in packagingItems" :key="pkg.id" :value="pkg.id">
                                {{ pkg.name }} {{ pkg.sku ? `(${pkg.sku})` : '' }}
                            </option>
                        </select>
                    </div>
                    <!-- Custom name (auto-filled from select, or type manually) -->
                    <div class="col-span-3">
                        <label v-if="i === 0" class="heyd2c-label">Name *</label>
                        <input v-model="item.item_name" class="heyd2c-input" placeholder="Item name" required />
                    </div>
                    <!-- Unit -->
                    <div class="col-span-1">
                        <label v-if="i === 0" class="heyd2c-label">Unit</label>
                        <select v-model="item.unit" class="heyd2c-input">
                            <option v-for="u in ['pcs','roll','sheet','kg','box','pack','m']" :key="u">{{ u }}</option>
                        </select>
                    </div>
                    <!-- Qty -->
                    <div class="col-span-1">
                        <label v-if="i === 0" class="heyd2c-label">Qty *</label>
                        <input v-model.number="item.quantity" type="number" min="1" class="heyd2c-input" required />
                    </div>
                    <!-- Unit price -->
                    <div class="col-span-2">
                        <label v-if="i === 0" class="heyd2c-label">Unit Price (₹) *</label>
                        <input v-model.number="item.unit_price" type="number" step="0.01" min="0" class="heyd2c-input font-mono" required />
                    </div>
                    <!-- Delete -->
                    <div class="col-span-1" :class="i === 0 ? 'pt-6' : ''">
                        <button type="button" @click="removeLine(i)"
                                :disabled="form.items.length === 1"
                                class="p-2 rounded-lg text-ink-3 hover:text-rose-400 hover:bg-rose-500/10 disabled:opacity-30 transition cursor-pointer">
                            <Trash2 :size="14" />
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" @click="addLine"
                    class="mt-3 btn btn-ghost btn-sm flex items-center gap-1.5 cursor-pointer">
                <Plus :size="13" /> Add Line
            </button>

            <!-- Totals -->
            <div class="mt-4 pt-4 border-t border-frost-1 flex justify-end">
                <div class="text-right space-y-1">
                    <div class="text-[13px] text-ink-3">Subtotal</div>
                    <div class="text-[20px] font-bold text-white">{{ fmt(subtotal) }}</div>
                </div>
            </div>
        </div>

        <!-- Submit -->
        <div class="flex gap-3">
            <button type="submit" class="btn btn-primary px-8" :disabled="form.processing">
                {{ form.processing ? 'Creating…' : 'Create Purchase Order' }}
            </button>
            <button type="button" class="btn btn-ghost" @click="router.visit(route('tenant.packaging.orders.index', { tenant: slug }))">
                Cancel
            </button>
        </div>
    </form>
</TenantLayout>
</template>
