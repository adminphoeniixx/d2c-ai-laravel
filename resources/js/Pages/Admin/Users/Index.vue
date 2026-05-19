<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Search, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ users: { type: Object, default: () => ({ data: [
  { id: 1, name: 'Platform Admin', email: 'admin@pulsara.test', is_admin: true, company: null, roles: [{ name: 'super-admin' }] },
  { id: 2, name: 'Acme Owner', email: 'owner@acme.test', is_admin: false, company: { slug: 'acme', name: 'Acme Apparel' }, roles: [{ name: 'owner' }] },
  { id: 3, name: 'Nova Owner', email: 'owner@nova.test', is_admin: false, company: { slug: 'nova', name: 'Nova Brews' }, roles: [{ name: 'owner' }] },
] }) }, filters: Object });
const q = ref('');
</script>
<template>
<Head title="Users" />
<AdminLayout>
  <div class="flex items-center justify-between mb-5">
    <div class="relative max-w-sm flex-1"><Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" /><input v-model="q" placeholder="Search users…" class="pulsara-input pl-9" /></div>
    <button class="btn btn-primary"><Plus :size="15" /> New User</button>
  </div>
  <div class="card overflow-hidden p-0">
    <table class="w-full text-[13px]">
      <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3"><tr><th class="text-left px-5 py-3">Name</th><th class="text-left px-5 py-3">Email</th><th class="text-left px-5 py-3">Company</th><th class="text-left px-5 py-3">Role</th><th class="text-left px-5 py-3">Type</th></tr></thead>
      <tbody class="divide-y divide-frost-1">
        <tr v-for="u in users.data" :key="u.id" class="hover:bg-brand-600/5 transition">
          <td class="px-5 py-3 font-semibold text-white">{{ u.name }}</td>
          <td class="px-5 py-3 text-ink-2">{{ u.email }}</td>
          <td class="px-5 py-3"><span v-if="u.company" class="text-brand-300 font-mono">{{ u.company.name }}</span><span v-else class="text-ink-3">—</span></td>
          <td class="px-5 py-3"><span class="pill pill-info">{{ u.roles?.[0]?.name || '—' }}</span></td>
          <td class="px-5 py-3"><span class="pill" :class="u.is_admin ? 'pill-bad' : 'pill-good'">{{ u.is_admin ? 'Admin' : 'User' }}</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</AdminLayout>
</template>
