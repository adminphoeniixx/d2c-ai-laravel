<script setup>
import { Head } from '@inertiajs/vue3';
import TenantLayout from '@/Layouts/TenantLayout.vue';
</script>
<template>
<Head title="PF — Provident Fund" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Provident Fund (PF)</h2>
            <p class="text-[12px] text-ink-3 mt-1">EPF calculations, challan generation, and ECR filing</p>
        </div>
    </div>
    <div class="rounded-2xl border border-frost-1 bg-surface-2 p-12 text-center">
        <div class="text-[40px] mb-3">🏦</div>
        <h3 class="text-[16px] font-bold text-white mb-2">PF Module Coming Soon</h3>
        <p class="text-[13px] text-ink-3 max-w-md mx-auto">Automated EPF contribution calculations, challan generation, and ECR file export will be available here. Connect your payroll data to get started.</p>
    </div>
</TenantLayout>
</template>
