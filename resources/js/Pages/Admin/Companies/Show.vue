<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Building2, Users, LogIn, Pause, Play } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ company: { type: Object, default: () => ({ name: 'Acme', slug: 'acme', plan: 'pro', status: 'active', email: 'hello@acme.test', country: 'IN', currency: 'INR', timezone: 'Asia/Kolkata', created_at: '2026-04-16' }) }, users: { type: Array, default: () => [] } });
</script>
<template>
<Head title="Company Details" />
<AdminLayout>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 card">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-[18px] font-bold text-white">{{ company.name }}</h2>
        <div class="flex gap-2">
          <button class="btn btn-ghost btn-sm"><LogIn :size="13" /> Impersonate</button>
          <button v-if="company.status === 'active'" class="btn btn-ghost btn-sm text-rose"><Pause :size="13" /> Suspend</button>
          <button v-else class="btn btn-ghost btn-sm text-emerald"><Play :size="13" /> Activate</button>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4 text-[13px]">
        <div><span class="pulsara-label">Slug</span><div class="font-mono text-ink">{{ company.slug }}</div></div>
        <div><span class="pulsara-label">Email</span><div class="text-ink">{{ company.email }}</div></div>
        <div><span class="pulsara-label">Plan</span><div><span class="pill pill-info">{{ company.plan }}</span></div></div>
        <div><span class="pulsara-label">Status</span><div><span class="pill" :class="company.status === 'active' ? 'pill-good' : 'pill-bad'">{{ company.status }}</span></div></div>
        <div><span class="pulsara-label">Country</span><div class="text-ink">{{ company.country }}</div></div>
        <div><span class="pulsara-label">Currency</span><div class="text-ink">{{ company.currency }}</div></div>
        <div><span class="pulsara-label">Timezone</span><div class="text-ink">{{ company.timezone }}</div></div>
        <div><span class="pulsara-label">Created</span><div class="text-ink">{{ company.created_at }}</div></div>
      </div>
    </div>
    <div class="card">
      <h3 class="text-[15px] font-bold text-white mb-4"><Users :size="15" class="inline" /> Users</h3>
      <div v-if="users.length" class="space-y-2">
        <div v-for="u in users" :key="u.id" class="flex items-center gap-2 text-[13px]">
          <div class="h-7 w-7 rounded-full bg-brand-600/15 flex items-center justify-center text-[10px] font-bold text-brand-300">{{ u.name?.charAt(0) }}</div>
          <div><div class="text-ink">{{ u.name }}</div><div class="text-[11px] text-ink-3">{{ u.email }}</div></div>
        </div>
      </div>
      <p v-else class="text-[13px] text-ink-3">No users yet.</p>
    </div>
  </div>
</AdminLayout>
</template>
