<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Shield, Plus } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ roles: { type: Array, default: () => [
  { id: 1, name: 'super-admin', permissions: Array(25).fill(0), users_count: 1 },
  { id: 2, name: 'support-admin', permissions: Array(7).fill(0), users_count: 0 },
  { id: 3, name: 'owner', permissions: Array(10).fill(0), users_count: 3 },
  { id: 4, name: 'staff', permissions: Array(5).fill(0), users_count: 2 },
] } });
</script>
<template>
<Head title="Roles" />
<AdminLayout>
  <div class="flex items-center justify-between mb-5">
    <h2 class="text-[18px] font-bold text-white">Roles</h2>
    <button class="btn btn-primary"><Plus :size="15" /> New Role</button>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div v-for="r in roles" :key="r.id" class="card hover:border-frost-3 cursor-pointer">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-2"><Shield :size="16" class="text-brand-400" /><span class="text-[15px] font-bold text-white">{{ r.name }}</span></div>
        <span class="pill pill-info">{{ r.users_count }} users</span>
      </div>
      <div class="text-[12px] text-ink-3">{{ r.permissions?.length || 0 }} permissions assigned</div>
      <div class="mt-3 h-1.5 bg-bg-3 rounded-full overflow-hidden"><div class="h-full bg-brand-gradient" :style="{ width: Math.min(100, (r.permissions?.length || 0) * 4) + '%' }"></div></div>
    </div>
  </div>
</AdminLayout>
</template>
