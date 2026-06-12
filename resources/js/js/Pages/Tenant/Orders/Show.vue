<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { ArrowLeft, Package, User as UserIcon, MapPin, Receipt, IndianRupee, Tag, Truck } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    order: { type: Object, default: () => ({}) },
    hasGst: { type: Boolean, default: false },
});

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v * 100) / 100);
const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const statusMap = {
    paid:      { class: 'pill-good', label: 'Paid' },
    fulfilled: { class: 'pill-good', label: 'Fulfilled' },
    pending:   { class: 'pill-info', label: 'Pending' },
    refunded:  { class: 'pill-bad',  label: 'Refunded' },
    cancelled: { class: 'pill-bad',  label: 'Cancelled' },
    partially_fulfilled: { class: 'pill-info', label: 'Partially Fulfilled' },
    partially_refunded:  { class: 'pill-info', label: 'Partially Refunded' },
};

const totalGst = computed(() => (props.order.cgst_amount || 0) + (props.order.sgst_amount || 0) + (props.order.igst_amount || 0));
const hasGstData = computed(() => totalGst.value > 0 || props.order.gst_rate);

const addr = (a) => {
    if (!a) return null;
    const parts = [a.address1, a.address2, a.city, a.province, a.zip, a.country].filter(Boolean);
    return parts.join(', ');
};
</script>

<template>
<Head :title="'Order ' + order.order_number" />
<TenantLayout>
    <!-- Back + header -->
    <div class="flex items-center gap-3 mb-5">
        <Link :href="route('tenant.orders', { tenant: slug })" class="btn btn-ghost btn-sm">
            <ArrowLeft :size="14" /> Back
        </Link>
        <div class="flex-1">
            <div class="flex items-center gap-3">
                <h2 class="text-[22px] font-bold text-white">{{ order.order_number }}</h2>
                <span class="pill" :class="statusMap[order.status]?.class || 'pill-info'">{{ statusMap[order.status]?.label || order.status }}</span>
                <span v-if="order.fulfillment_status" class="pill pill-info">{{ order.fulfillment_status }}</span>
                <span class="pill bg-brand-600/10 text-brand-300">{{ order.provider }}</span>
            </div>
            <p class="text-[12px] text-ink-3 mt-1">Placed {{ order.placed_at }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        <!-- Left column: Items + GST -->
        <div class="lg:col-span-2 space-y-5">

            <!-- Line Items -->
            <div class="card overflow-hidden p-0">
                <div class="px-5 py-3 border-b border-frost-1 flex items-center gap-2">
                    <Package :size="15" class="text-brand-400" />
                    <h3 class="text-[15px] font-bold text-white">Line Items</h3>
                    <span class="pill pill-info ml-auto">{{ order.items?.length || 0 }} items</span>
                </div>
                <table class="w-full text-[13px]">
                    <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                        <tr>
                            <th class="text-left px-5 py-3">Product</th>
                            <th class="text-left px-5 py-3">SKU</th>
                            <th class="text-right px-5 py-3">Qty</th>
                            <th class="text-right px-5 py-3">Unit Price</th>
                            <th class="text-right px-5 py-3">Total</th>
                            <th v-if="hasGst && hasGstData" class="text-right px-5 py-3">GST %</th>
                            <th v-if="hasGst && hasGstData" class="text-right px-5 py-3">CGST</th>
                            <th v-if="hasGst && hasGstData" class="text-right px-5 py-3">SGST</th>
                            <th v-if="hasGst && hasGstData" class="text-right px-5 py-3">IGST</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-frost-1">
                        <tr v-for="item in order.items" :key="item.id" class="hover:bg-brand-600/5 transition">
                            <td class="px-5 py-3">
                                <div class="font-medium text-white">{{ item.product_name }}</div>
                                <div v-if="item.variant_name" class="text-[11px] text-ink-3">{{ item.variant_name }}</div>
                            </td>
                            <td class="px-5 py-3 font-mono text-ink-3 text-[11px]">{{ item.sku || '—' }}</td>
                            <td class="px-5 py-3 text-right font-mono text-ink-2">{{ item.quantity }}</td>
                            <td class="px-5 py-3 text-right font-mono text-ink-2">{{ fmt(item.unit_price) }}</td>
                            <td class="px-5 py-3 text-right font-mono font-semibold text-white">{{ fmt(item.total_price) }}</td>
                            <td v-if="hasGst && hasGstData" class="px-5 py-3 text-right font-mono text-ink-3">{{ item.gst_rate ? item.gst_rate + '%' : '—' }}</td>
                            <td v-if="hasGst && hasGstData" class="px-5 py-3 text-right font-mono text-emerald">{{ fmt(item.cgst_amount) }}</td>
                            <td v-if="hasGst && hasGstData" class="px-5 py-3 text-right font-mono text-emerald">{{ fmt(item.sgst_amount) }}</td>
                            <td v-if="hasGst && hasGstData" class="px-5 py-3 text-right font-mono text-brand-300">{{ fmt(item.igst_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Order Summary -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4 flex items-center gap-2">
                    <Receipt :size="15" class="text-brand-400" /> Order Summary
                </h3>
                <div class="space-y-2 text-[13px]">
                    <div class="flex justify-between"><span class="text-ink-2">Subtotal</span><span class="font-mono text-ink">{{ fmt(order.subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-2">Shipping</span><span class="font-mono text-ink">{{ fmt(order.total_shipping) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-2">Discount</span><span class="font-mono text-rose">-{{ fmt(order.total_discount) }}</span></div>
                    <div class="flex justify-between"><span class="text-ink-2">Tax (from Shopify)</span><span class="font-mono text-ink">{{ fmt(order.total_tax) }}</span></div>
                    <div class="border-t border-frost-1 pt-2 flex justify-between">
                        <span class="font-semibold text-white">Total</span>
                        <span class="font-mono font-bold text-[16px] text-white">{{ fmt(order.total_amount) }}</span>
                    </div>
                </div>
            </div>

            <!-- GST Breakdown -->
            <div v-if="hasGst && hasGstData" class="card">
                <h3 class="text-[15px] font-bold text-white mb-4 flex items-center gap-2">
                    <IndianRupee :size="15" class="text-brand-400" /> GST Breakdown
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="rounded-[10px] bg-bg-3 border border-frost-1 p-3">
                        <div class="text-[10px] font-mono uppercase tracking-wider text-ink-3">Taxable Amount</div>
                        <div class="text-[16px] font-bold text-white mt-1">{{ fmt(order.taxable_amount) }}</div>
                    </div>
                    <div class="rounded-[10px] bg-bg-3 border border-frost-1 p-3">
                        <div class="text-[10px] font-mono uppercase tracking-wider text-ink-3">CGST</div>
                        <div class="text-[16px] font-bold text-emerald mt-1">{{ fmt(order.cgst_amount) }}</div>
                        <div class="text-[10px] text-ink-3">{{ order.gst_rate ? (order.gst_rate / 2) + '%' : '' }}</div>
                    </div>
                    <div class="rounded-[10px] bg-bg-3 border border-frost-1 p-3">
                        <div class="text-[10px] font-mono uppercase tracking-wider text-ink-3">SGST</div>
                        <div class="text-[16px] font-bold text-emerald mt-1">{{ fmt(order.sgst_amount) }}</div>
                        <div class="text-[10px] text-ink-3">{{ order.gst_rate ? (order.gst_rate / 2) + '%' : '' }}</div>
                    </div>
                    <div class="rounded-[10px] bg-bg-3 border border-frost-1 p-3">
                        <div class="text-[10px] font-mono uppercase tracking-wider text-ink-3">IGST</div>
                        <div class="text-[16px] font-bold text-brand-300 mt-1">{{ fmt(order.igst_amount) }}</div>
                        <div class="text-[10px] text-ink-3">{{ order.gst_rate ? order.gst_rate + '%' : '' }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-[12px]">
                    <span class="pill" :class="order.is_intra_state ? 'pill-good' : 'pill-info'">
                        {{ order.is_intra_state ? 'Intra-state (CGST+SGST)' : 'Inter-state (IGST)' }}
                    </span>
                    <span v-if="order.place_of_supply" class="text-ink-3">
                        <MapPin :size="12" class="inline" /> Place of supply: {{ order.place_of_supply }}
                    </span>
                </div>
            </div>

            <div v-else-if="hasGst && !hasGstData" class="card border-amber/20 bg-amber/5">
                <p class="text-[13px] text-amber">GST not calculated for this order. Re-sync from Shopify to calculate GST breakup.</p>
            </div>
        </div>

        <!-- Right column: Customer + Address -->
        <div class="space-y-5">
            <!-- Customer -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-3 flex items-center gap-2">
                    <UserIcon :size="15" class="text-brand-400" /> Customer
                </h3>
                <div class="space-y-2 text-[13px]">
                    <div v-if="order.customer_name"><span class="text-ink-3">Name</span><div class="text-white">{{ order.customer_name }}</div></div>
                    <div v-if="order.customer_email"><span class="text-ink-3">Email</span><div class="text-ink font-mono text-[12px]">{{ order.customer_email }}</div></div>
                    <div v-if="order.customer_phone"><span class="text-ink-3">Phone</span><div class="text-ink font-mono text-[12px]">{{ order.customer_phone }}</div></div>
                    <div v-if="!order.customer_name && !order.customer_email" class="text-ink-3">No customer data</div>
                </div>
            </div>

            <!-- Shipping Address -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-3 flex items-center gap-2">
                    <Truck :size="15" class="text-brand-400" /> Shipping Address
                </h3>
                <div v-if="order.shipping_address" class="text-[13px] text-ink-2 leading-relaxed">
                    <div v-if="order.shipping_address.name" class="font-medium text-white">{{ order.shipping_address.name }}</div>
                    <div v-if="order.shipping_address.address1">{{ order.shipping_address.address1 }}</div>
                    <div v-if="order.shipping_address.address2">{{ order.shipping_address.address2 }}</div>
                    <div>
                        {{ [order.shipping_address.city, order.shipping_address.province, order.shipping_address.zip].filter(Boolean).join(', ') }}
                    </div>
                    <div v-if="order.shipping_address.country">{{ order.shipping_address.country }}</div>
                    <div v-if="order.shipping_address.phone" class="mt-2 font-mono text-[12px] text-ink-3">{{ order.shipping_address.phone }}</div>
                </div>
                <p v-else class="text-[13px] text-ink-3">No shipping address</p>
            </div>

            <!-- Billing Address -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-3 flex items-center gap-2">
                    <MapPin :size="15" class="text-brand-400" /> Billing Address
                </h3>
                <div v-if="order.billing_address" class="text-[13px] text-ink-2 leading-relaxed">
                    <div v-if="order.billing_address.name" class="font-medium text-white">{{ order.billing_address.name }}</div>
                    <div v-if="order.billing_address.address1">{{ order.billing_address.address1 }}</div>
                    <div v-if="order.billing_address.address2">{{ order.billing_address.address2 }}</div>
                    <div>
                        {{ [order.billing_address.city, order.billing_address.province, order.billing_address.zip].filter(Boolean).join(', ') }}
                    </div>
                    <div v-if="order.billing_address.country">{{ order.billing_address.country }}</div>
                </div>
                <p v-else class="text-[13px] text-ink-3">Same as shipping</p>
            </div>

            <!-- Tags -->
            <div v-if="order.tags?.length" class="card">
                <h3 class="text-[15px] font-bold text-white mb-3 flex items-center gap-2">
                    <Tag :size="15" class="text-brand-400" /> Tags
                </h3>
                <div class="flex flex-wrap gap-2">
                    <span v-for="tag in order.tags" :key="tag" class="pill pill-info">{{ tag }}</span>
                </div>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
