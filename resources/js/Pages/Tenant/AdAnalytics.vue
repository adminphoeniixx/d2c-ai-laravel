<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Megaphone, Target } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

const props = defineProps({
    days: { type: Number, default: 30 },
    kpis: { type: Array, default: () => [] },
    platforms: { type: Object, default: () => ({ meta: {}, google: {} }) },
    campaigns: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const fmtK = (v) => v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : v >= 1000 ? (v / 1000).toFixed(1) + 'K' : v;

const hasData = (props.platforms.meta?.spend > 0 || props.platforms.google?.spend > 0 || props.campaigns.length > 0);

function changeDays(d) {
    router.get(route('tenant.ads', { tenant: slug }), { days: d }, { preserveState: true });
}

const platformRows = [
    { name: 'Meta Ads', icon: Megaphone, color: 'text-[#1877F2]', ...props.platforms.meta },
    { name: 'Google Ads', icon: Target, color: 'text-[#4285F4]', ...props.platforms.google },
].filter(p => p.spend > 0);
</script>

<template>
<Head title="Ad Analytics" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Ad Analytics</h2>
            <p class="text-[12px] text-ink-3 mt-1">Blended performance across all ad channels</p>
        </div>
        <div class="flex gap-1">
            <button v-for="d in [7, 14, 30, 60, 90]" :key="d"
                class="px-3 py-1.5 text-[11px] font-mono rounded-lg transition cursor-pointer"
                :class="days === d ? 'bg-brand-600/20 text-brand-300' : 'text-ink-3 hover:text-ink'"
                @click="changeDays(d)">{{ d }}D</button>
        </div>
    </div>

    <!-- KPI Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-5">
        <KpiCard v-for="k in kpis" :key="k.label" :label="k.label" :value="k.value" :format="k.format" />
    </div>

    <!-- Platform Breakdown -->
    <div v-if="platformRows.length" class="card mb-5">
        <h3 class="text-[15px] font-bold text-white mb-4">Platform Breakdown</h3>
        <table class="w-full text-[13px]">
            <thead class="text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left pb-3">Platform</th>
                    <th class="text-right pb-3">Spend</th>
                    <th class="text-right pb-3">Impressions</th>
                    <th class="text-right pb-3">Clicks</th>
                    <th class="text-right pb-3">CTR</th>
                    <th class="text-right pb-3">Conversions</th>
                    <th class="text-right pb-3">Conv. Value</th>
                    <th class="text-right pb-3">ROAS</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="p in platformRows" :key="p.name" class="hover:bg-brand-600/5">
                    <td class="py-3 font-semibold text-white flex items-center gap-2">
                        <component :is="p.icon" :size="14" :class="p.color" /> {{ p.name }}
                    </td>
                    <td class="py-3 text-right font-mono text-rose">{{ fmt(p.spend) }}</td>
                    <td class="py-3 text-right font-mono text-ink-2">{{ fmtK(p.impressions) }}</td>
                    <td class="py-3 text-right font-mono text-ink-2">{{ fmtK(p.clicks) }}</td>
                    <td class="py-3 text-right font-mono text-ink-2">{{ p.ctr }}%</td>
                    <td class="py-3 text-right font-mono text-ink-2">{{ p.conversions }}</td>
                    <td class="py-3 text-right font-mono text-emerald">{{ fmt(p.conv_value) }}</td>
                    <td class="py-3 text-right font-mono font-semibold" :class="p.roas >= 3 ? 'text-emerald' : p.roas >= 1 ? 'text-amber' : 'text-rose'">{{ p.roas }}x</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Campaign Breakdown -->
    <div v-if="campaigns.length" class="card overflow-hidden p-0 mb-5">
        <div class="px-5 py-3 border-b border-frost-1">
            <h3 class="text-[15px] font-bold text-white">Top Campaigns</h3>
        </div>
        <table class="w-full text-[12px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-2.5">Campaign</th>
                    <th class="text-left px-5 py-2.5">Platform</th>
                    <th class="text-right px-5 py-2.5">Spend</th>
                    <th class="text-right px-5 py-2.5">Impressions</th>
                    <th class="text-right px-5 py-2.5">Clicks</th>
                    <th class="text-right px-5 py-2.5">Conversions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="c in campaigns" :key="c.id" class="hover:bg-brand-600/5">
                    <td class="px-5 py-2.5 font-medium text-white">{{ c.name }}</td>
                    <td class="px-5 py-2.5"><span class="pill" :class="c.platform === 'meta' ? 'pill-info' : 'pill-good'">{{ c.platform }}</span></td>
                    <td class="px-5 py-2.5 text-right font-mono text-rose">{{ fmt(c.total_spend) }}</td>
                    <td class="px-5 py-2.5 text-right font-mono text-ink-2">{{ fmtK(c.total_impressions || 0) }}</td>
                    <td class="px-5 py-2.5 text-right font-mono text-ink-2">{{ fmtK(c.total_clicks || 0) }}</td>
                    <td class="px-5 py-2.5 text-right font-mono text-ink-2">{{ c.total_conversions || 0 }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Empty state -->
    <div v-if="!hasData" class="card border-amber/20 bg-amber/5">
        <p class="text-[13px] text-amber">Connect Meta Ads or Google Ads from the Integrations sidebar to see real campaign data here. Ad spend will also be auto-logged to your Expenses.</p>
    </div>
</TenantLayout>
</template>
