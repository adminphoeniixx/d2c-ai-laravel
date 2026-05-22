<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    vendors: { type: Array, default: () => [] },
    nextPO: { type: String, default: 'PO-001' },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));

const form = useForm({
    po_number: props.nextPO,
    vendor_id: '',
    order_date: new Date().toISOString().split('T')[0],
    expected_date: '',
    notes: '',
    items: [{ product_name: '', sku: '', quantity: 1, unit_price: '', tax_rate: 18 }],
});

function addItem() {
    form.items.push({ product_name: '', sku: '', quantity: 1, unit_price: '', tax_rate: 18 });
}
function removeItem(i) {
    if (form.items.length > 1) form.items.splice(i, 1);
}

const lineTotal = (item) => {
    const base = (item.quantity || 0) * (parseFloat(item.unit_price) || 0);
    const tax = base * (item.tax_rate || 0) / 100;
    return { base, tax, total: base + tax };
};

const grandTotal = computed(() => {
    let subtotal = 0, taxTotal = 0;
    form.items.forEach(item => {
        const lt = lineTotal(item);
        subtotal += lt.base;
        taxTotal += lt.tax;
    });
    return { subtotal, taxTotal, total: subtotal + taxTotal };
});

function submit() {
    form.post(route('tenant.purchase-orders.store', { tenant: slug }));
}
</script>

<template>
<Head title="Create Purchase Order" />
<TenantLayout>
    <div class="flex items-center gap-3 mb-5">
        <Link :href="route('tenant.purchase-orders.index', { tenant: slug })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> Back</Link>
        <div>
            <h2 class="text-[20px] font-bold text-white">New Purchase Order</h2>
        </div>
    </div>

    <form @submit.prevent="submit" class="space-y-5">
        <!-- Header -->
        <div class="card">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="pulsara-label">PO Number</label>
                    <input v-model="form.po_number" class="pulsara-input font-mono" />
                    <div v-if="form.errors.po_number" class="mt-1 text-[11px] text-rose">{{ form.errors.po_number }}</div>
                </div>
                <div>
                    <label class="pulsara-label">Vendor</label>
                    <select v-model="form.vendor_id" class="pulsara-input">
                        <option value="">Select vendor…</option>
                        <option v-for="v in vendors" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                    <div v-if="form.errors.vendor_id" class="mt-1 text-[11px] text-rose">{{ form.errors.vendor_id }}</div>
                </div>
                <div>
                    <label class="pulsara-label">Order Date</label>
                    <input v-model="form.order_date" type="date" class="pulsara-input" />
                </div>
                <div>
                    <label class="pulsara-label">Expected Date</label>
                    <input v-model="form.expected_date" type="date" class="pulsara-input" />
                </div>
            </div>
        </div>

        <!-- Line Items -->
        <div class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-frost-1 flex items-center justify-between">
                <h3 class="text-[14px] font-bold text-white">Line Items</h3>
                <button type="button" class="btn btn-ghost btn-sm" @click="addItem"><Plus :size="12" /> Add Item</button>
            </div>
            <table class="w-full text-[12px]">
                <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                    <tr>
                        <th class="text-left px-4 py-2">Product</th>
                        <th class="text-left px-4 py-2 w-24">SKU</th>
                        <th class="text-center px-4 py-2 w-20">Qty</th>
                        <th class="text-right px-4 py-2 w-28">Unit Price</th>
                        <th class="text-center px-4 py-2 w-20">Tax %</th>
                        <th class="text-right px-4 py-2 w-28">Total</th>
                        <th class="w-10"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-frost-1">
                    <tr v-for="(item, i) in form.items" :key="i" class="hover:bg-brand-600/5">
                        <td class="px-4 py-2"><input v-model="item.product_name" class="pulsara-input text-[12px]" placeholder="Product name" /></td>
                        <td class="px-4 py-2"><input v-model="item.sku" class="pulsara-input text-[12px] font-mono" placeholder="SKU" /></td>
                        <td class="px-4 py-2"><input v-model.number="item.quantity" type="number" min="1" class="pulsara-input text-[12px] text-center" /></td>
                        <td class="px-4 py-2"><input v-model.number="item.unit_price" type="number" step="0.01" class="pulsara-input text-[12px] text-right font-mono" placeholder="0.00" /></td>
                        <td class="px-4 py-2"><input v-model.number="item.tax_rate" type="number" step="0.5" class="pulsara-input text-[12px] text-center font-mono" /></td>
                        <td class="px-4 py-2 text-right font-mono text-white">{{ fmt(lineTotal(item).total) }}</td>
                        <td class="px-2"><button type="button" class="text-ink-3 hover:text-rose cursor-pointer" @click="removeItem(i)"><Trash2 :size="14" /></button></td>
                    </tr>
                </tbody>
                <tfoot class="bg-bg-3 border-t border-frost-2">
                    <tr>
                        <td colspan="5" class="px-4 py-2 text-right text-[11px] text-ink-3 font-mono">Subtotal</td>
                        <td class="px-4 py-2 text-right font-mono text-ink">{{ fmt(grandTotal.subtotal) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="5" class="px-4 py-1 text-right text-[11px] text-ink-3 font-mono">Tax</td>
                        <td class="px-4 py-1 text-right font-mono text-ink-3">{{ fmt(grandTotal.taxTotal) }}</td>
                        <td></td>
                    </tr>
                    <tr class="text-[14px] font-semibold">
                        <td colspan="5" class="px-4 py-2 text-right text-white font-mono">Total</td>
                        <td class="px-4 py-2 text-right font-mono text-emerald">{{ fmt(grandTotal.total) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="card">
            <label class="pulsara-label">Notes</label>
            <textarea v-model="form.notes" class="pulsara-input" rows="2" placeholder="Optional notes…"></textarea>
        </div>

        <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
            {{ form.processing ? 'Creating…' : 'Create Purchase Order' }}
        </button>
    </form>
</TenantLayout>
</template>
