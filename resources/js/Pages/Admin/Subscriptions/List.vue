<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Search, XCircle } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    subscriptions: Object,
    filters: Object,
    stats: Object,
})

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')

function filter() {
    router.get(route('admin.subscriptions.list'), { search: search.value, status: status.value }, { preserveState: true })
}

function cancelSub(id) {
    if (!confirm('Cancel this subscription? The company will be downgraded to free.')) return
    router.post(route('admin.subscriptions.cancel', id))
}

const fmt = (n) => '₹' + Number(n||0).toLocaleString('en-IN', { maximumFractionDigits: 0 })
const statusColor = (s) => ({
    active: 'bg-emerald-500/15 text-emerald-400',
    trial:  'bg-blue-500/15 text-blue-400',
    cancelled: 'bg-rose-500/15 text-rose-400',
    expired:   'bg-slate-500/15 text-slate-400',
}[s] || 'bg-slate-500/15 text-slate-400')
</script>

<template>
<Head title="Subscriptions" />
<AdminLayout>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-[20px] font-bold text-white">Subscriptions</h1>
            <a :href="route('admin.subscriptions.dashboard')" class="btn btn-ghost btn-sm cursor-pointer">← Dashboard</a>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-4 gap-3">
            <div v-for="(count, label) in stats" :key="label" class="card text-center">
                <div class="text-[20px] font-bold" :class="{
                    active: 'text-emerald-400', trial: 'text-blue-400',
                    cancelled: 'text-rose-400', expired: 'text-slate-400'
                }[label] || 'text-white'">{{ count }}</div>
                <div class="text-[10px] text-ink-3 uppercase capitalize mt-0.5">{{ label }}</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex gap-2">
            <div class="relative flex-1 max-w-64">
                <Search :size="13" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
                <input v-model="search" @keyup.enter="filter" placeholder="Search company…"
                    class="heyd2c-input pl-8 text-[12px] w-full" />
            </div>
            <select v-model="status" @change="filter" class="heyd2c-input text-[12px]">
                <option value="">All Statuses</option>
                <option value="active">Active</option>
                <option value="trial">Trial</option>
                <option value="cancelled">Cancelled</option>
                <option value="expired">Expired</option>
            </select>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden p-0">
            <table class="w-full text-[12px]">
                <thead><tr class="border-b border-frost-1">
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium">Company</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium">Plan</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium">Status</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium">Cycle</th>
                    <th class="text-right px-5 py-3 text-[10px] text-ink-3 font-medium">Amount</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium">Started</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium">Ends</th>
                    <th class="px-5 py-3"></th>
                </tr></thead>
                <tbody>
                    <tr v-for="s in subscriptions.data" :key="s.id" class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition">
                        <td class="px-5 py-3">
                            <div class="text-white font-medium">{{ s.company?.name }}</div>
                            <div class="text-[10px] text-ink-3">{{ s.company?.email }}</div>
                        </td>
                        <td class="px-5 py-3 text-ink-2 font-medium">{{ s.plan?.name }}</td>
                        <td class="px-5 py-3">
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-medium" :class="statusColor(s.status)">{{ s.status }}</span>
                        </td>
                        <td class="px-5 py-3 text-ink-3 capitalize">{{ s.billing_cycle }}</td>
                        <td class="px-5 py-3 text-right text-white font-medium">{{ fmt(s.final_amount) }}</td>
                        <td class="px-5 py-3 text-ink-3">{{ s.starts_at ? new Date(s.starts_at).toLocaleDateString('en-IN') : '—' }}</td>
                        <td class="px-5 py-3 text-ink-3">{{ s.ends_at ? new Date(s.ends_at).toLocaleDateString('en-IN') : '—' }}</td>
                        <td class="px-5 py-3">
                            <button v-if="s.status === 'active'" @click="cancelSub(s.id)"
                                class="text-[10px] text-rose-400 hover:text-rose-300 cursor-pointer">Cancel</button>
                        </td>
                    </tr>
                    <tr v-if="!subscriptions.data?.length">
                        <td colspan="8" class="px-5 py-8 text-center text-ink-3">No subscriptions found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</AdminLayout>
</template>
