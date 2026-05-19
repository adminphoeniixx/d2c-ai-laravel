<script setup>
import { Head } from '@inertiajs/vue3';
import { Wallet, Users, TrendingUp } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';
import KpiCard from '@/Components/KpiCard.vue';

defineProps({ thisMonth: { type: Number, default: 185000 } });

const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v));

const departments = [
    { name: 'Operations', headcount: 4, monthly: 72000, pctRevenue: 8.7 },
    { name: 'Marketing', headcount: 2, monthly: 48000, pctRevenue: 5.8 },
    { name: 'Customer Support', headcount: 3, monthly: 36000, pctRevenue: 4.4 },
    { name: 'Tech / Dev', headcount: 1, monthly: 29000, pctRevenue: 3.5 },
];

const months = [
    { label: 'Nov', value: 178000 }, { label: 'Dec', value: 180000 },
    { label: 'Jan', value: 182000 }, { label: 'Feb', value: 183000 },
    { label: 'Mar', value: 184000 }, { label: 'Apr', value: 185000 },
];
</script>

<template>
<Head title="Payroll Intelligence" />
<TenantLayout>
    <div class="mb-5">
        <h2 class="text-[20px] font-bold text-white">Payroll Intelligence</h2>
        <p class="text-[12px] text-ink-3 mt-1">Headcount costs and margin impact analysis</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <KpiCard label="Monthly Payroll" :value="185000" format="currency" :delta="1" tone="bad" />
        <KpiCard label="Headcount" :value="10" format="number" />
        <KpiCard label="Cost per Head" :value="18500" format="currency" />
        <KpiCard label="Payroll / Revenue" value="22.4%" />
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <div class="card">
            <h3 class="text-[16px] font-bold text-white mb-4">By Department</h3>
            <table class="w-full text-[13px]">
                <thead class="text-[10px] font-mono uppercase tracking-wider text-ink-3">
                    <tr>
                        <th class="text-left pb-3">Department</th>
                        <th class="text-right pb-3">People</th>
                        <th class="text-right pb-3">Monthly</th>
                        <th class="text-right pb-3">% Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-frost-1">
                    <tr v-for="d in departments" :key="d.name" class="hover:bg-brand-600/5 transition">
                        <td class="py-3 font-medium text-white">{{ d.name }}</td>
                        <td class="py-3 text-right font-mono text-ink-2">{{ d.headcount }}</td>
                        <td class="py-3 text-right font-mono">{{ fmt(d.monthly) }}</td>
                        <td class="py-3 text-right font-mono text-ink-3">{{ d.pctRevenue }}%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3 class="text-[16px] font-bold text-white mb-4">6-Month Trend</h3>
            <div class="space-y-3">
                <div v-for="m in months" :key="m.label" class="flex items-center gap-3">
                    <span class="w-8 text-[11px] font-mono text-ink-3">{{ m.label }}</span>
                    <div class="flex-1 h-6 bg-bg-3 rounded-lg overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-brand-600 to-fuchsia rounded-lg transition-all"
                             :style="{ width: (m.value / 200000 * 100) + '%' }"></div>
                    </div>
                    <span class="w-16 text-right text-[12px] font-mono text-ink-2">{{ fmt(m.value) }}</span>
                </div>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
