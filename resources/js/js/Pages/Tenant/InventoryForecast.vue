<script setup>
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, PackageSearch, TrendingDown } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

defineProps({
    lowStock: { type: Array, default: () => [] },
    note: String,
});

const products = [
    { sku: 'TEE-BLK-M', name: 'Classic Tee - Black', qty: 240, velocity: 12, daysOfCover: 20, price: 899, cost: 280, status: 'ok' },
    { sku: 'TEE-WHT-M', name: 'Classic Tee - White', qty: 180, velocity: 10, daysOfCover: 18, price: 899, cost: 280, status: 'ok' },
    { sku: 'HOD-OV-L', name: 'Hoodie Oversized', qty: 12, velocity: 4, daysOfCover: 3, price: 1999, cost: 620, status: 'critical' },
    { sku: 'TOTE-01', name: 'Canvas Tote', qty: 320, velocity: 8, daysOfCover: 40, price: 599, cost: 150, status: 'ok' },
    { sku: 'JOG-FL-M', name: 'Joggers Fleece', qty: 28, velocity: 5, daysOfCover: 6, price: 1499, cost: 470, status: 'low' },
    { sku: 'CAP-BLK', name: 'Snapback Cap', qty: 5, velocity: 3, daysOfCover: 2, price: 499, cost: 120, status: 'critical' },
    { sku: 'SCK-WHT-3', name: 'Crew Socks 3-Pack', qty: 450, velocity: 15, daysOfCover: 30, price: 399, cost: 95, status: 'ok' },
];

const statusMap = {
    ok:       { class: 'pill-good', label: 'In Stock' },
    low:      { class: 'pill-info', label: 'Low Stock' },
    critical: { class: 'pill-bad',  label: 'Critical' },
};

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(v);
</script>

<template>
<Head title="Inventory Forecast" />
<TenantLayout>
    <div class="mb-5">
        <h2 class="text-[20px] font-bold text-white">Inventory Forecast</h2>
        <p class="text-[12px] text-ink-3 mt-1">Days-of-cover based on 30-day sales velocity</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <KpiCard label="Total SKUs" :value="products.length" format="number" />
        <KpiCard label="Low / Critical" :value="products.filter(p => p.status !== 'ok').length" format="number" tone="bad" />
        <KpiCard label="Avg Days of Cover" :value="Math.round(products.reduce((a,p) => a + p.daysOfCover, 0) / products.length)" format="number" />
        <KpiCard label="Inventory Value" :value="products.reduce((a,p) => a + p.qty * p.cost, 0)" format="currency" />
    </div>

    <!-- Alerts -->
    <div v-if="products.some(p => p.status === 'critical')" class="card border-rose/30 bg-rose/5 mb-5 flex items-start gap-3">
        <AlertTriangle :size="18" class="text-rose mt-0.5 flex-shrink-0" />
        <div>
            <div class="font-semibold text-white text-[14px]">Stockout Risk</div>
            <p class="text-[12px] text-ink-2 mt-1">
                {{ products.filter(p => p.status === 'critical').map(p => p.name).join(', ') }} —
                less than 3 days of stock remaining at current velocity.
            </p>
        </div>
    </div>

    <div class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">SKU</th>
                    <th class="text-left px-5 py-3">Product</th>
                    <th class="text-right px-5 py-3">Qty</th>
                    <th class="text-right px-5 py-3">Velocity/day</th>
                    <th class="text-right px-5 py-3">Days of Cover</th>
                    <th class="text-right px-5 py-3">Unit Cost</th>
                    <th class="text-left px-5 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="p in products" :key="p.sku" class="hover:bg-brand-600/5 transition" :class="{ 'bg-rose/5': p.status === 'critical' }">
                    <td class="px-5 py-3 font-mono text-brand-300">{{ p.sku }}</td>
                    <td class="px-5 py-3 font-medium text-white">{{ p.name }}</td>
                    <td class="px-5 py-3 text-right font-mono" :class="p.qty < 30 ? 'text-rose' : 'text-ink'">{{ p.qty }}</td>
                    <td class="px-5 py-3 text-right font-mono text-ink-2">{{ p.velocity }}</td>
                    <td class="px-5 py-3 text-right font-mono font-semibold" :class="p.daysOfCover < 7 ? 'text-rose' : p.daysOfCover < 14 ? 'text-amber' : 'text-emerald'">
                        {{ p.daysOfCover }}d
                    </td>
                    <td class="px-5 py-3 text-right font-mono text-ink-2">{{ fmt(p.cost) }}</td>
                    <td class="px-5 py-3"><span class="pill" :class="statusMap[p.status]?.class">{{ statusMap[p.status]?.label }}</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
