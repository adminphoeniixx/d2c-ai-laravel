<script setup>
import { computed } from 'vue';
import { TrendingUp, TrendingDown, Minus } from 'lucide-vue-next';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [Number, String], required: true },
    format: { type: String, default: 'number' },     // 'currency' | 'percent' | 'number'
    delta:  { type: Number, default: null },
    tone:   { type: String, default: 'neutral' },     // 'good' | 'bad' | 'neutral'
    currency: { type: String, default: 'INR' },
});

const formatted = computed(() => {
    const v = props.value;
    if (typeof v === 'string') return v;
    if (props.format === 'currency') {
        return '₹' + new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 }).format(v);
    }
    if (props.format === 'percent') return v + '%';
    return new Intl.NumberFormat('en-IN').format(v);
});

const deltaSign = computed(() => (props.delta == null ? 0 : props.delta >= 0 ? 1 : -1));
const deltaIsPositive = computed(() => {
    if (props.delta == null) return null;
    if (props.tone === 'bad') return props.delta < 0;
    return props.delta >= 0;
});
</script>

<template>
    <div class="kpi-card">
        <div class="kpi-label">{{ label }}</div>
        <div class="kpi-value">{{ formatted }}</div>

        <div v-if="delta != null" class="flex items-center gap-1 text-[12px] font-mono">
            <TrendingUp v-if="deltaSign > 0" :size="13" :class="deltaIsPositive ? 'text-emerald' : 'text-rose'" />
            <TrendingDown v-else-if="deltaSign < 0" :size="13" :class="deltaIsPositive ? 'text-emerald' : 'text-rose'" />
            <Minus v-else :size="13" class="text-ink-3" />
            <span :class="deltaIsPositive ? 'text-emerald' : 'text-rose'">
                {{ delta > 0 ? '+' : '' }}{{ delta }}%
            </span>
        </div>
    </div>
</template>
