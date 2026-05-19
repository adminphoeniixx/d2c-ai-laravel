<script setup>
import { Head } from '@inertiajs/vue3';
import { TrendingUp, TrendingDown, Banknote } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

defineProps({
    labels: { type: Array, default: () => ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr'] },
    inflows: { type: Array, default: () => [620000, 710000, 680000, 750000, 790000, 824340] },
    outflows: { type: Array, default: () => [480000, 520000, 510000, 560000, 580000, 663019] },
    runway: { type: String, default: '' },
});

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v));

const months = ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr'];
const inData  = [620000, 710000, 680000, 750000, 790000, 824340];
const outData = [480000, 520000, 510000, 560000, 580000, 663019];
const netData = inData.map((v, i) => v - outData[i]);
const maxVal  = Math.max(...inData, ...outData);

const totalIn  = inData.reduce((a, b) => a + b, 0);
const totalOut = outData.reduce((a, b) => a + b, 0);
const totalNet = totalIn - totalOut;
const avgNet   = Math.round(totalNet / months.length);
const runwayMonths = avgNet > 0 ? '∞ (profitable)' : Math.abs(Math.round(500000 / avgNet)) + ' months';
</script>

<template>
<Head title="Cash Flow" />
<TenantLayout>
    <div class="mb-5">
        <h2 class="text-[20px] font-bold text-white">Cash Flow</h2>
        <p class="text-[12px] text-ink-3 mt-1">Inflows vs outflows · 6-month view</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <KpiCard label="Total Inflows (6M)" :value="totalIn" format="currency" />
        <KpiCard label="Total Outflows (6M)" :value="totalOut" format="currency" />
        <KpiCard label="Net Cash Flow" :value="totalNet" format="currency" :delta="12" tone="good" />
        <KpiCard label="Cash Runway" :value="runwayMonths" />
    </div>

    <div class="card mb-5">
        <h3 class="text-[16px] font-bold text-white mb-6">Monthly Cash Flow</h3>
        <div class="space-y-4">
            <div v-for="(m, i) in months" :key="m" class="grid grid-cols-[40px_1fr_100px] items-center gap-3">
                <span class="text-[12px] font-mono text-ink-3">{{ m }}</span>
                <div class="relative h-10">
                    <!-- Inflow bar -->
                    <div class="absolute top-0 left-0 h-4 rounded-r-lg bg-gradient-to-r from-emerald/60 to-emerald/30 transition-all"
                         :style="{ width: (inData[i] / maxVal * 100) + '%' }">
                    </div>
                    <!-- Outflow bar -->
                    <div class="absolute bottom-0 left-0 h-4 rounded-r-lg bg-gradient-to-r from-rose/60 to-rose/30 transition-all"
                         :style="{ width: (outData[i] / maxVal * 100) + '%' }">
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[11px] font-mono" :class="netData[i] >= 0 ? 'text-emerald' : 'text-rose'">
                        {{ netData[i] >= 0 ? '+' : '' }}{{ fmt(netData[i]) }}
                    </div>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-6 mt-4 pt-4 border-t border-frost-1 text-[11px] font-mono text-ink-3">
            <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-emerald/50"></span> Inflows</span>
            <span class="flex items-center gap-1.5"><span class="w-3 h-2 rounded-sm bg-rose/50"></span> Outflows</span>
        </div>
    </div>

    <div class="card">
        <h3 class="text-[16px] font-bold text-white mb-4">Month-by-Month Detail</h3>
        <table class="w-full text-[13px]">
            <thead class="text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left pb-3">Month</th>
                    <th class="text-right pb-3">Inflows</th>
                    <th class="text-right pb-3">Outflows</th>
                    <th class="text-right pb-3">Net</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="(m, i) in months" :key="m" class="hover:bg-brand-600/5 transition">
                    <td class="py-3 font-medium text-white">{{ m }}</td>
                    <td class="py-3 text-right font-mono text-emerald">{{ fmt(inData[i]) }}</td>
                    <td class="py-3 text-right font-mono text-rose">{{ fmt(outData[i]) }}</td>
                    <td class="py-3 text-right font-mono font-semibold" :class="netData[i] >= 0 ? 'text-emerald' : 'text-rose'">
                        {{ netData[i] >= 0 ? '+' : '' }}{{ fmt(netData[i]) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
