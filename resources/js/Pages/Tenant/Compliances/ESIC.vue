<script setup>
import { Head } from '@inertiajs/vue3';
import TenantLayout from '@/Layouts/TenantLayout.vue';
</script>
<template>
<Head title="ESIC" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">ESIC</h2>
            <p class="text-[12px] text-ink-3 mt-1">Employee State Insurance contributions and return filing</p>
        </div>
    </div>
    <div class="rounded-2xl border border-frost-1 bg-surface-2 p-12 text-center">
        <div class="text-[40px] mb-3">🏥</div>
        <h3 class="text-[16px] font-bold text-white mb-2">ESIC Module Coming Soon</h3>
        <p class="text-[13px] text-ink-3 max-w-md mx-auto">ESIC contribution calculations, monthly returns, and challan filing will be available here. Connected to your payroll for automatic computation.</p>
    </div>
</TenantLayout>
</template>
