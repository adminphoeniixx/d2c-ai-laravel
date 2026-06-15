<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { AlertTriangle, TrendingUp, ArrowRight } from 'lucide-vue-next';

const props = defineProps({
    insight: { type: Object, required: true },
    compact: { type: Boolean, default: false },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const ACTION_ROUTES = {
    pnl: 'tenant.pnl',
    expenses: 'tenant.expenses',
    orders: 'tenant.orders',
    inventory: 'tenant.inventory',
    ads: 'tenant.ads',
    banking: 'tenant.banking.index',
    logistics: 'tenant.logistics.index',
    payroll: 'tenant.payroll.index',
    ai: 'tenant.ai',
};

const severityColor = computed(() => ({
    high:   'border-rose-500/30 bg-rose-500/5',
    medium: 'border-amber-500/30 bg-amber-500/5',
    low:    'border-frost-1 bg-surface-2',
}[props.insight.severity] || 'border-frost-1 bg-surface-2'));

const badgeColor = computed(() => ({
    high:   'bg-rose-500/15 text-rose-400',
    medium: 'bg-amber-500/15 text-amber-400',
    low:    'bg-frost-1 text-ink-3',
}[props.insight.severity] || 'bg-frost-1 text-ink-3'));

function goToAction() {
    const routeName = ACTION_ROUTES[props.insight.action_page];
    if (!routeName) return;
    try {
        router.visit(route(routeName, { tenant: slug }));
    } catch (e) {
        // route not resolvable, ignore
    }
}
</script>

<template>
<div class="rounded-xl border p-4 transition" :class="severityColor">
    <div class="flex items-start gap-3">
        <div class="h-7 w-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
             :class="insight.type === 'opportunity' ? 'bg-emerald-500/15' : 'bg-rose-500/15'">
            <TrendingUp v-if="insight.type === 'opportunity'" :size="14" class="text-emerald-400" />
            <AlertTriangle v-else :size="14" class="text-rose-400" />
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                <span class="text-[13px] font-semibold text-white">{{ insight.title }}</span>
                <span class="text-[9px] font-mono uppercase px-1.5 py-0.5 rounded-full" :class="badgeColor">
                    {{ insight.severity }}
                </span>
            </div>
            <p class="text-[12px] text-ink-2 leading-relaxed" :class="compact ? 'line-clamp-2' : ''">
                {{ insight.description }}
            </p>
            <button v-if="insight.action_label && insight.action_page && ACTION_ROUTES[insight.action_page]"
                @click="goToAction"
                class="inline-flex items-center gap-1 text-[11px] font-medium text-brand-300 hover:text-brand-200 mt-2 cursor-pointer transition">
                {{ insight.action_label }}
                <ArrowRight :size="11" />
            </button>
        </div>
    </div>
</div>
</template>
