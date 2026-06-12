<script setup>
import { Head } from '@inertiajs/vue3'
import { TrendingUp, Users, CreditCard, BarChart2, Tag, AlertCircle } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    mrr: Number, arr: Number, total_revenue: Number, total_tax: Number,
    month_revenue: Number, plan_dist: Array, category_analysis: Array,
    monthly_revenue: Array, stats: Object,
})

const fmt    = (n) => '₹' + Number(n||0).toLocaleString('en-IN', { maximumFractionDigits: 0 })
const fmtNum = (n) => Number(n||0).toLocaleString('en-IN')

const planColors = { Free: 'text-slate-400', Basic: 'text-blue-400', Growth: 'text-purple-400', Scale: 'text-amber-400' }

const kpis = [
    { label: 'MRR',             value: fmt(props.mrr),           icon: TrendingUp, color: 'text-emerald-400', sub: 'Monthly recurring' },
    { label: 'ARR',             value: fmt(props.arr),           icon: TrendingUp, color: 'text-emerald-400', sub: 'Annual recurring' },
    { label: 'Total Revenue',   value: fmt(props.total_revenue), icon: CreditCard, color: 'text-brand-300',  sub: 'All time' },
    { label: 'Tax Collected',   value: fmt(props.total_tax),     icon: CreditCard, color: 'text-amber-400',  sub: 'GST all time' },
    { label: 'This Month',      value: fmt(props.month_revenue), icon: TrendingUp, color: 'text-blue-400',   sub: 'Revenue MTD' },
    { label: 'Active Subs',     value: fmtNum(props.stats.active_subscriptions), icon: Users, color: 'text-emerald-400', sub: 'Paying customers' },
    { label: 'Free Accounts',   value: fmtNum(props.stats.free_companies),       icon: Users, color: 'text-slate-400',   sub: 'On free plan' },
    { label: 'Churned MTD',     value: fmtNum(props.stats.churn_this_month),     icon: AlertCircle, color: 'text-rose-400', sub: 'Cancelled this month' },
]
</script>

<template>
<Head title="Subscription Dashboard" />
<AdminLayout>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <h1 class="text-[20px] font-bold text-white">Subscription Overview</h1>
            <div class="flex gap-2">
                <a :href="route('admin.subscriptions.list')"    class="btn btn-ghost btn-sm cursor-pointer">Subscriptions</a>
                <a :href="route('admin.subscriptions.plans')"   class="btn btn-ghost btn-sm cursor-pointer">Plans</a>
                <a :href="route('admin.subscriptions.coupons')" class="btn btn-ghost btn-sm cursor-pointer">Coupons</a>
                <a :href="route('admin.subscriptions.settings')"class="btn btn-primary btn-sm cursor-pointer">Settings</a>
            </div>
        </div>

        <!-- KPI grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div v-for="kpi in kpis" :key="kpi.label" class="card">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-[10px] text-ink-3 uppercase font-medium">{{ kpi.label }}</span>
                    <component :is="kpi.icon" :size="14" :class="kpi.color" />
                </div>
                <div :class="kpi.color" class="text-[22px] font-bold">{{ kpi.value }}</div>
                <div class="text-[10px] text-ink-3 mt-0.5">{{ kpi.sub }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Plan distribution -->
            <div class="card">
                <h3 class="text-[13px] font-semibold text-white mb-3">Plan Distribution</h3>
                <div class="space-y-3">
                    <div v-for="p in plan_dist" :key="p.plan" class="flex items-center gap-3">
                        <span :class="planColors[p.plan] || 'text-ink-2'" class="text-[12px] font-medium w-16">{{ p.plan }}</span>
                        <div class="flex-1 bg-frost-1 rounded-full h-2">
                            <div class="h-2 rounded-full bg-brand-400 transition-all"
                                :style="{ width: stats.active_subscriptions ? (p.count / stats.active_subscriptions * 100) + '%' : '0%' }"></div>
                        </div>
                        <span class="text-[12px] text-ink-2 w-6 text-right">{{ p.count }}</span>
                    </div>
                    <p v-if="!plan_dist?.length" class="text-[12px] text-ink-3 text-center py-2">No paid subscriptions yet</p>
                </div>
            </div>

            <!-- Monthly revenue -->
            <div class="card lg:col-span-2">
                <h3 class="text-[13px] font-semibold text-white mb-3">Monthly Revenue</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-[12px]">
                        <thead><tr class="border-b border-frost-1">
                            <th class="text-left pb-2 text-[10px] text-ink-3">Month</th>
                            <th class="text-right pb-2 text-[10px] text-ink-3">Revenue</th>
                            <th class="text-right pb-2 text-[10px] text-ink-3">Tax</th>
                            <th class="text-right pb-2 text-[10px] text-ink-3">Orders</th>
                        </tr></thead>
                        <tbody>
                            <tr v-for="m in [...(monthly_revenue||[])].reverse()" :key="m.month" class="border-b border-frost-1 last:border-0">
                                <td class="py-2 text-ink-2">{{ new Date(m.month).toLocaleDateString('en-IN', {month:'short', year:'numeric'}) }}</td>
                                <td class="py-2 text-right text-emerald-400 font-medium">{{ fmt(m.revenue) }}</td>
                                <td class="py-2 text-right text-amber-400">{{ fmt(m.tax) }}</td>
                                <td class="py-2 text-right text-ink-2">{{ m.count }}</td>
                            </tr>
                            <tr v-if="!monthly_revenue?.length">
                                <td colspan="4" class="py-4 text-center text-ink-3">No revenue data yet</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Category analysis -->
        <div class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-frost-1">
                <h3 class="text-[13px] font-semibold text-white">Companies by Category</h3>
            </div>
            <table class="w-full text-[12px]">
                <thead><tr class="border-b border-frost-1">
                    <th class="text-left px-5 py-2.5 text-[10px] text-ink-3 font-medium">Category</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 font-medium">Total</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 font-medium">Paid</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 font-medium">Free</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 font-medium">Conversion</th>
                    <th class="px-5 py-2.5"></th>
                </tr></thead>
                <tbody>
                    <tr v-for="c in category_analysis" :key="c.business_category" class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition">
                        <td class="px-5 py-3 text-white font-medium capitalize">{{ c.business_category || 'Unknown' }}</td>
                        <td class="px-5 py-3 text-ink-2 text-right">{{ c.total }}</td>
                        <td class="px-5 py-3 text-emerald-400 text-right">{{ c.paid }}</td>
                        <td class="px-5 py-3 text-slate-400 text-right">{{ c.free_count }}</td>
                        <td class="px-5 py-3 text-right">
                            <span :class="c.total > 0 && c.paid/c.total > 0.3 ? 'text-emerald-400' : 'text-amber-400'" class="font-medium">
                                {{ c.total > 0 ? Math.round(c.paid / c.total * 100) : 0 }}%
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="w-20 bg-frost-1 rounded-full h-1.5">
                                <div class="h-1.5 rounded-full bg-brand-400"
                                    :style="{ width: c.total > 0 ? (c.paid / c.total * 100) + '%' : '0%' }"></div>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!category_analysis?.length">
                        <td colspan="6" class="px-5 py-6 text-center text-ink-3">No category data yet</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</AdminLayout>
</template>
