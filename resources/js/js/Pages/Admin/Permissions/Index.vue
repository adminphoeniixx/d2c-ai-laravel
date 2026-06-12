<script setup>
import { Head } from '@inertiajs/vue3';
import { KeyRound } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ grouped: { type: Object, default: () => ({
  admin: [{ id:1, name:'admin.companies.view' },{ id:2, name:'admin.companies.create' },{ id:3, name:'admin.users.view' },{ id:4, name:'admin.roles.manage' },{ id:5, name:'admin.system.view' }],
  orders: [{ id:6, name:'orders.view' },{ id:7, name:'orders.export' }],
  expenses: [{ id:8, name:'expenses.view' },{ id:9, name:'expenses.create' },{ id:10, name:'expenses.delete' }],
  integrations: [{ id:11, name:'integrations.connect' },{ id:12, name:'integrations.disconnect' }],
  ai: [{ id:13, name:'ai.use' }],
}) } });
</script>
<template>
<Head title="Permissions" />
<AdminLayout>
  <h2 class="text-[18px] font-bold text-white mb-5">Permissions</h2>
  <div class="space-y-4">
    <div v-for="(perms, group) in grouped" :key="group" class="card">
      <div class="flex items-center gap-2 mb-3"><KeyRound :size="14" class="text-brand-400" /><span class="text-[14px] font-bold text-white capitalize">{{ group }}</span><span class="pill pill-info ml-auto">{{ perms.length }}</span></div>
      <div class="flex flex-wrap gap-2">
        <span v-for="p in perms" :key="p.id" class="px-3 py-1.5 rounded-lg bg-bg-3 border border-frost-1 text-[12px] font-mono text-ink-2 hover:border-frost-3 transition cursor-default">{{ p.name }}</span>
      </div>
    </div>
  </div>
</AdminLayout>
</template>
