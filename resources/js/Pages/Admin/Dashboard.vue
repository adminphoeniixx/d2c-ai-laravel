<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

defineProps({
    stats:            { type: Array, default: () => [] },
    signups:          { type: Array, default: () => [] },
    planDistribution: { type: Object, default: () => ({}) },
    latestCompanies:  { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Platform Overview" />
    <AdminLayout>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
            <KpiCard
                v-for="s in stats" :key="s.label"
                :label="s.label"
                :value="s.value"
                format="number"
                :delta="s.delta"
            />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-[15px] font-bold text-white">Latest companies</h3>
                    <Link :href="route('admin.companies.index')" class="text-[11px] font-mono text-brand-300 hover:underline flex items-center gap-1">
                        View all <ArrowRight :size="12" />
                    </Link>
                </div>
                <div class="divide-y divide-frost-1">
                    <Link
                        v-for="c in latestCompanies" :key="c.id"
                        :href="route('admin.companies.show', c.id)"
                        class="flex items-center justify-between py-3 hover:bg-brand-600/5 -mx-2 px-2 rounded-lg transition"
                    >
                        <div class="flex items-center gap-3">
                            <div class="h-8 w-8 rounded-[10px] bg-brand-600/15 flex items-center justify-center text-[11px] font-bold text-brand-300">
                                {{ c.name.substring(0, 2).toUpperCase() }}
                            </div>
                            <div>
                                <div class="text-[13px] font-semibold text-white">{{ c.name }}</div>
                                <div class="text-[11px] font-mono text-ink-3">/app/{{ c.slug }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="pill pill-info">{{ c.plan }}</span>
                            <span class="pill" :class="c.status === 'active' ? 'pill-good' : 'pill-bad'">{{ c.status }}</span>
                        </div>
                    </Link>
                </div>
            </div>

            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4">Plan distribution</h3>
                <div v-for="(count, plan) in planDistribution" :key="plan" class="mb-3 last:mb-0">
                    <div class="flex justify-between text-[12px] mb-1">
                        <span class="capitalize text-ink">{{ plan }}</span>
                        <span class="font-mono text-ink-3">{{ count }}</span>
                    </div>
                    <div class="h-1.5 bg-bg-3 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-brand-600 to-fuchsia" :style="{ width: Math.min(100, count * 10) + '%' }"></div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
