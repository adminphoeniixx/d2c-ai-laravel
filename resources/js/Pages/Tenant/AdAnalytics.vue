<script setup>
import { Head } from '@inertiajs/vue3';
import { Megaphone, TrendingUp, Eye, MousePointer } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

defineProps({
    kpis: { type: Array, default: () => [] },
    note: { type: String, default: '' },
});

const channels = [
    { name: 'Meta Ads', spend: 65000, revenue: 312000, roas: 4.8, cpc: 12, impressions: 840000, clicks: 5420, ctr: 0.65 },
    { name: 'Google Ads', spend: 42000, revenue: 198000, roas: 4.7, cpc: 18, impressions: 520000, clicks: 2340, ctr: 0.45 },
    { name: 'Google Shopping', spend: 15000, revenue: 89000, roas: 5.9, cpc: 8, impressions: 310000, clicks: 1870, ctr: 0.60 },
];

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v));
const fmtK = (v) => v >= 1000 ? (v / 1000).toFixed(1) + 'K' : v;
</script>

<template>
<Head title="Ad Analytics" />
<TenantLayout>
    <div class="mb-5">
        <h2 class="text-[20px] font-bold text-white">Ad Analytics</h2>
        <p class="text-[12px] text-ink-3 mt-1">Blended performance across all ad channels · Last 30 days</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <KpiCard label="Total Ad Spend" :value="122000" format="currency" :delta="8" tone="bad" />
        <KpiCard label="Revenue from Ads" :value="599000" format="currency" :delta="15" tone="good" />
        <KpiCard label="Blended ROAS" :value="4.9" format="number" :delta="5" tone="good" />
        <KpiCard label="Avg CPC" :value="13" format="currency" :delta="-3" tone="good" />
    </div>

    <div class="card mb-5">
        <h3 class="text-[16px] font-bold text-white mb-4">Channel Breakdown</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
                <thead class="text-[10px] font-mono uppercase tracking-wider text-ink-3">
                    <tr>
                        <th class="text-left pb-3">Channel</th>
                        <th class="text-right pb-3">Spend</th>
                        <th class="text-right pb-3">Revenue</th>
                        <th class="text-right pb-3">ROAS</th>
                        <th class="text-right pb-3">CPC</th>
                        <th class="text-right pb-3">Impressions</th>
                        <th class="text-right pb-3">Clicks</th>
                        <th class="text-right pb-3">CTR</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-frost-1">
                    <tr v-for="ch in channels" :key="ch.name" class="hover:bg-brand-600/5 transition">
                        <td class="py-3 font-semibold text-white flex items-center gap-2">
                            <Megaphone :size="14" class="text-brand-400" /> {{ ch.name }}
                        </td>
                        <td class="py-3 text-right font-mono text-rose">{{ fmt(ch.spend) }}</td>
                        <td class="py-3 text-right font-mono text-emerald">{{ fmt(ch.revenue) }}</td>
                        <td class="py-3 text-right font-mono font-semibold" :class="ch.roas >= 4 ? 'text-emerald' : 'text-amber'">{{ ch.roas }}x</td>
                        <td class="py-3 text-right font-mono text-ink-2">{{ fmt(ch.cpc) }}</td>
                        <td class="py-3 text-right font-mono text-ink-2">{{ fmtK(ch.impressions) }}</td>
                        <td class="py-3 text-right font-mono text-ink-2">{{ fmtK(ch.clicks) }}</td>
                        <td class="py-3 text-right font-mono text-ink-2">{{ ch.ctr }}%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-amber/20 bg-amber/5">
        <p class="text-[13px] text-amber">Connect Meta Ads and Google Ads to see real-time data. Currently showing demo numbers.</p>
    </div>
</TenantLayout>
</template>
