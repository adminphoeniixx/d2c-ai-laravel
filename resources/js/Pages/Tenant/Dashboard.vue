<script setup>
import { Head } from '@inertiajs/vue3';
import { Mic } from 'lucide-vue-next';
import { ref } from 'vue';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';
import RevenueChart from '@/Components/RevenueChart.vue';

const props = defineProps({
    kpis:         { type: Array, default: () => [] },
    revenueLine:  { type: Object, default: () => ({ labels: [], revenue: [], expenses: [] }) },
    orderMetrics: { type: Array, default: () => [] },
});

const revenue = props.kpis.find(k => k.key === 'revenue')?.value ?? 0;
const profit  = props.kpis.find(k => k.key === 'profit')?.value ?? 0;
const margin  = revenue > 0 ? Math.round((profit / revenue) * 100) : 0;

const voiceModalOpen = ref(false);
</script>

<template>
    <Head title="Operations Dashboard" />
    <TenantLayout>
        <div class="card relative overflow-hidden mb-5">
            <div class="pointer-events-none absolute -right-12 -top-12 h-56 w-56 rounded-full bg-brand-600/20 blur-3xl" />
            <div class="flex items-start justify-between gap-6 relative">
                <div>
                    <div class="text-[12px] text-ink-3 mb-1">Add Expense <span class="font-mono text-brand-400">(AI Voice)</span></div>
                    <div class="text-[18px] lg:text-[20px] font-semibold text-white italic">"Spent ₹12,000 on Meta Ads today"</div>
                </div>
                <button
                    class="h-12 w-12 rounded-full bg-gradient-to-br from-brand-500 to-fuchsia flex items-center justify-center shadow-glow flex-shrink-0"
                    @click="voiceModalOpen = true"
                >
                    <Mic :size="20" class="text-white" />
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <KpiCard
                v-for="k in kpis" :key="k.key"
                :label="k.label"
                :value="k.value"
                :format="k.format"
                :delta="k.delta"
                :tone="k.tone"
            />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
            <div class="lg:col-span-2">
                <RevenueChart
                    :labels="revenueLine.labels"
                    :revenue="revenueLine.revenue"
                    :expenses="revenueLine.expenses"
                />
            </div>
            <div class="card flex flex-col items-center justify-center text-center min-h-[420px]">
                <div class="text-[18px] font-semibold text-white mb-4">Profit Margin</div>
                <div class="text-[80px] font-extrabold leading-none bg-gradient-to-br from-white to-brand-300 bg-clip-text text-transparent">
                    {{ margin }}%
                </div>
                <div class="mt-4 text-[12px] font-mono uppercase tracking-widest text-ink-3">Net / Revenue · 30D</div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <KpiCard
                v-for="k in orderMetrics" :key="k.key"
                :label="k.label"
                :value="k.value"
                :format="k.format"
                :delta="k.delta"
                :tone="k.tone"
            />
        </div>
    </TenantLayout>
</template>
