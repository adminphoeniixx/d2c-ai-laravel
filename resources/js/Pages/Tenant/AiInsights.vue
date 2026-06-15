<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Sparkles, RefreshCw, AlertTriangle, TrendingUp, Clock } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import AiInsightCard from '@/Components/AiInsightCard.vue';

const props = defineProps({
    insights:    { type: Array, default: () => [] },
    generatedAt: { type: String, default: null },
    hasError:    { type: Boolean, default: false },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const insights = ref([...props.insights]);
const generatedAt = ref(props.generatedAt);
const refreshing = ref(false);
const error = ref(null);

function csrfToken() {
    const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
    return token ? decodeURIComponent(token) : '';
}

async function refresh() {
    refreshing.value = true;
    error.value = null;
    try {
        const res = await fetch(`/app/${slug}/ai-insights/refresh`, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
        });
        const data = await res.json();
        if (data.success) {
            insights.value = data.insights;
            generatedAt.value = data.generatedAt;
            if (!data.insights.length) {
                error.value = "No insights generated. Check that the AI provider key is configured.";
            }
        } else {
            error.value = data.error || 'Could not generate insights right now.';
        }
    } catch (e) {
        error.value = "Couldn't reach the server. Please try again.";
    } finally {
        refreshing.value = false;
    }
}

const alerts = computed(() => insights.value.filter(i => i.type === 'alert'));
const opportunities = computed(() => insights.value.filter(i => i.type === 'opportunity'));

function timeAgo(dateStr) {
    if (!dateStr) return null;
    const diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
    if (diff < 60) return 'just now';
    if (diff < 3600) return Math.floor(diff / 60) + ' min ago';
    if (diff < 86400) return Math.floor(diff / 3600) + ' hr ago';
    return Math.floor(diff / 86400) + ' day(s) ago';
}
</script>

<template>
<Head title="AI Insights" />
<TenantLayout>
    <div class="flex items-center justify-between mb-2 flex-wrap gap-3">
        <div>
            <div class="flex items-center gap-2">
                <Sparkles :size="18" class="text-brand-400" />
                <h2 class="text-[20px] font-bold text-white">AI Insights</h2>
            </div>
            <p class="text-[12px] text-ink-3 mt-1">What's happening in your business and what to do about it — refreshed daily</p>
        </div>
        <div class="flex items-center gap-3">
            <span v-if="generatedAt" class="inline-flex items-center gap-1.5 text-[11px] text-ink-3">
                <Clock :size="12" />
                Updated {{ timeAgo(generatedAt) }}
            </span>
            <button @click="refresh" :disabled="refreshing" class="btn btn-primary cursor-pointer disabled:opacity-50 flex items-center gap-1.5">
                <RefreshCw :size="14" :class="refreshing ? 'animate-spin' : ''" />
                {{ refreshing ? 'Refreshing…' : 'Refresh Now' }}
            </button>
        </div>
    </div>

    <div v-if="error" class="card border border-rose-500/30 bg-rose-500/5 mb-5">
        <p class="text-[12px] text-rose-300">{{ error }}</p>
    </div>

    <div v-if="!insights.length && !error" class="card text-center py-12">
        <Sparkles :size="28" class="text-ink-3 mx-auto mb-3" />
        <p class="text-[13px] text-white font-medium mb-1">No insights yet</p>
        <p class="text-[12px] text-ink-3 mb-4">Click "Refresh Now" to generate insights from your business data.</p>
        <button @click="refresh" :disabled="refreshing" class="btn btn-primary cursor-pointer disabled:opacity-50">
            {{ refreshing ? 'Generating…' : 'Generate Insights' }}
        </button>
    </div>

    <div v-else class="space-y-5">
        <div v-if="alerts.length" class="card">
            <div class="flex items-center gap-2 mb-3">
                <AlertTriangle :size="15" class="text-rose-400" />
                <h3 class="text-[14px] font-semibold text-white">Needs Attention</h3>
                <span class="text-[11px] text-ink-3">({{ alerts.length }})</span>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <AiInsightCard v-for="insight in alerts" :key="insight.id" :insight="insight" />
            </div>
        </div>

        <div v-if="opportunities.length" class="card">
            <div class="flex items-center gap-2 mb-3">
                <TrendingUp :size="15" class="text-emerald-400" />
                <h3 class="text-[14px] font-semibold text-white">Opportunities</h3>
                <span class="text-[11px] text-ink-3">({{ opportunities.length }})</span>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <AiInsightCard v-for="insight in opportunities" :key="insight.id" :insight="insight" />
            </div>
        </div>
    </div>
</TenantLayout>
</template>
