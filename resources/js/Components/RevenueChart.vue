<script setup>
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale, LinearScale, PointElement, LineElement,
    Filler, Tooltip, Legend,
} from 'chart.js';
import { computed, ref } from 'vue';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip, Legend);

const props = defineProps({
    labels:   { type: Array, required: true },
    revenue:  { type: Array, required: true },
    expenses: { type: Array, default: () => [] },
});

const activeRange = ref('30D');

const data = computed(() => ({
    labels: props.labels,
    datasets: [
        {
            label: 'Revenue',
            data: props.revenue,
            borderColor: '#8b5cf6',
            backgroundColor: (ctx) => {
                const chart = ctx.chart;
                const { ctx: c, chartArea } = chart;
                if (!chartArea) return 'rgba(139,92,246,.15)';
                const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                g.addColorStop(0, 'rgba(139,92,246,.35)');
                g.addColorStop(1, 'rgba(139,92,246,0)');
                return g;
            },
            borderWidth: 2,
            fill: true,
            tension: 0.45,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: '#0a0812',
            pointBorderColor: '#b98fff',
            pointBorderWidth: 1.5,
        },
    ],
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#201d38',
            borderColor: 'rgba(139,92,246,.35)',
            borderWidth: 1,
            titleColor: '#e8e4f8',
            bodyColor: '#9b93c4',
            padding: 10,
            displayColors: false,
            callbacks: {
                label: (ctx) => '₹' + new Intl.NumberFormat('en-IN').format(ctx.parsed.y),
            },
        },
    },
    scales: {
        x: {
            grid:   { display: false },
            ticks:  { color: '#5c5480', font: { family: 'Outfit', size: 11 } },
            border: { display: false },
        },
        y: {
            grid:   { color: 'rgba(139,92,246,.08)', drawBorder: false },
            ticks:  { color: '#5c5480', font: { family: 'Outfit', size: 11 } },
            border: { display: false },
        },
    },
    animation: { duration: 700, easing: 'easeOutQuart' },
};
</script>

<template>
    <div class="card">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-[18px] font-bold text-white">Revenue vs Expenses</h3>
            <div class="flex items-center gap-1 bg-bg-3 rounded-full p-0.5 border border-frost-1">
                <button
                    v-for="r in ['7D','30D','YTD']" :key="r"
                    class="px-3 py-1 text-[11px] font-mono font-semibold rounded-full transition-all"
                    :class="activeRange === r
                        ? 'bg-gradient-to-br from-brand-600 to-fuchsia text-white shadow-glow-sm'
                        : 'text-ink-2 hover:text-ink'"
                    @click="activeRange = r"
                >{{ r }}</button>
            </div>
        </div>

        <div class="h-[340px]">
            <Line :data="data" :options="options" />
        </div>

        <div class="flex items-center gap-4 mt-3 text-[11px] font-mono text-ink-3">
            <span class="flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-4 rounded-sm border border-brand-500"></span> Revenue
            </span>
        </div>
    </div>
</template>
