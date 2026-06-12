<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Plus, Wallet, CheckCircle, Clock } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    runs: { type: Object, default: () => ({ data: [] }) },
    nextMonth: { type: String, default: '' },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + parseFloat(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
</script>

<template>
<Head title="Payroll" />
<TenantLayout>
    <div class="max-w-3xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white flex items-center gap-2"><Wallet :size="20" /> Payroll</h2>
                <p class="text-[12px] text-ink-3 mt-1">Monthly payroll runs with attendance integration</p>
            </div>
            <button @click="router.visit(route('tenant.payroll.create', { tenant: slug }))"
                class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer">
                <Plus :size="14" /> Process Payroll
            </button>
        </div>

        <div class="card overflow-hidden">
            <div v-for="r in runs.data" :key="r.id"
                class="px-4 py-3.5 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition cursor-pointer flex items-center justify-between"
                @click="router.visit(route('tenant.payroll.show', { tenant: slug, id: r.id }))">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl flex items-center justify-center"
                        :class="r.status === 'paid' ? 'bg-emerald-500/15' : 'bg-amber-500/15'">
                        <component :is="r.status === 'paid' ? CheckCircle : Clock" :size="18"
                            :class="r.status === 'paid' ? 'text-emerald-400' : 'text-amber-400'" />
                    </div>
                    <div>
                        <div class="text-[14px] font-medium text-white">{{ r.month }}</div>
                        <div class="text-[11px] text-ink-3">{{ r.payslips_count }} employees</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[14px] font-bold text-white">{{ fmt(r.total_net) }}</div>
                    <span class="text-[10px] px-2 py-0.5 rounded-full font-bold"
                        :class="r.status === 'paid' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'">
                        {{ r.status }}
                    </span>
                </div>
            </div>
            <div v-if="!runs.data?.length" class="px-4 py-8 text-center text-[13px] text-ink-3">No payroll runs yet</div>
        </div>
    </div>
</TenantLayout>
</template>
