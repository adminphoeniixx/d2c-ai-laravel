<script setup>
import { Head } from '@inertiajs/vue3';
import { History } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ logs: { type: Object, default: () => ({ data: [
  { id: 1, description: 'created', subject_type: 'App\\Models\\User', event: 'created', causer_id: 1, properties: {}, created_at: '2026-04-16 14:30' },
  { id: 2, description: 'updated', subject_type: 'App\\Models\\Company', event: 'updated', causer_id: 1, properties: { old: { plan: 'free' }, attributes: { plan: 'pro' } }, created_at: '2026-04-16 13:00' },
  { id: 3, description: 'created', subject_type: 'App\\Models\\User', event: 'created', causer_id: 2, properties: {}, created_at: '2026-04-16 12:30' },
] }) }, filters: Object });
</script>
<template>
<Head title="Audit Trail" />
<AdminLayout>
  <div class="mb-5"><h2 class="text-[18px] font-bold text-white">Audit Trail</h2><p class="text-[12px] text-ink-3 mt-1">All model changes tracked by Spatie Activitylog</p></div>
  <div class="card overflow-hidden p-0">
    <table class="w-full text-[13px]">
      <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3"><tr><th class="text-left px-5 py-3">Event</th><th class="text-left px-5 py-3">Model</th><th class="text-left px-5 py-3">By</th><th class="text-left px-5 py-3">Time</th></tr></thead>
      <tbody class="divide-y divide-frost-1">
        <tr v-for="l in logs.data" :key="l.id" class="hover:bg-brand-600/5 transition">
          <td class="px-5 py-3"><span class="pill" :class="l.event === 'created' ? 'pill-good' : l.event === 'deleted' ? 'pill-bad' : 'pill-info'">{{ l.event }}</span></td>
          <td class="px-5 py-3 font-mono text-ink-2 text-[11px]">{{ l.subject_type?.split('\\').pop() }}</td>
          <td class="px-5 py-3 text-ink-3">User #{{ l.causer_id }}</td>
          <td class="px-5 py-3 text-ink-3 text-[12px]">{{ l.created_at }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</AdminLayout>
</template>
