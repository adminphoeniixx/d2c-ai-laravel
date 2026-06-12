<script setup>
import { Head } from '@inertiajs/vue3';
import { Plug, Filter } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ logs: { type: Object, default: () => ({ data: [
  { id: 1, provider: 'shopify', event: 'sync.completed', level: 'info', message: 'Synced 45 orders (failed: 0)', company_id: 'acme', created_at: '2026-04-16 14:30' },
  { id: 2, provider: 'woocommerce', event: 'sync.completed', level: 'info', message: 'Synced 12 orders (failed: 1)', company_id: 'nova', created_at: '2026-04-16 12:15' },
  { id: 3, provider: 'shopify', event: 'webhook.received', level: 'info', message: 'Order #1042 created', company_id: 'acme', created_at: '2026-04-16 10:45' },
  { id: 4, provider: 'shopify', event: 'sync.error', level: 'error', message: 'Rate limited — retrying in 60s', company_id: 'acme', created_at: '2026-04-15 22:00' },
] }) }, filters: Object });
const levelMap = { info: 'pill-good', warn: 'pill-info', error: 'pill-bad' };
</script>
<template>
<Head title="Integration Logs" />
<AdminLayout>
  <div class="mb-5"><h2 class="text-[18px] font-bold text-white">Integration Logs</h2><p class="text-[12px] text-ink-3 mt-1">Shopify & WooCommerce sync activity</p></div>
  <div class="card overflow-hidden p-0">
    <table class="w-full text-[13px]">
      <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3"><tr><th class="text-left px-5 py-3">Provider</th><th class="text-left px-5 py-3">Event</th><th class="text-left px-5 py-3">Level</th><th class="text-left px-5 py-3">Message</th><th class="text-left px-5 py-3">Company</th><th class="text-left px-5 py-3">Time</th></tr></thead>
      <tbody class="divide-y divide-frost-1">
        <tr v-for="l in logs.data" :key="l.id" class="hover:bg-brand-600/5 transition">
          <td class="px-5 py-3"><span class="pill pill-info">{{ l.provider }}</span></td>
          <td class="px-5 py-3 font-mono text-ink-2">{{ l.event }}</td>
          <td class="px-5 py-3"><span class="pill" :class="levelMap[l.level]">{{ l.level }}</span></td>
          <td class="px-5 py-3 text-ink">{{ l.message }}</td>
          <td class="px-5 py-3 font-mono text-brand-300">{{ l.company_id }}</td>
          <td class="px-5 py-3 text-ink-3 text-[12px]">{{ l.created_at }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</AdminLayout>
</template>
