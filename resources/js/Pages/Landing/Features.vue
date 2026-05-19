<script setup>
import { Head } from '@inertiajs/vue3';
import { LineChart, Bot, Sparkles, ShieldCheck, Zap, TrendingUp, Receipt, ShoppingBag, Megaphone, PackageSearch } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
const features = [
  { icon: LineChart, title: 'Unified P&L', desc: 'Revenue, COGS, ad spend, payroll — one report across all stores.' },
  { icon: Bot, title: 'AI Copilot', desc: 'Ask questions about your data in natural language.' },
  { icon: Sparkles, title: 'Voice Expenses', desc: 'Say "Spent 12K on Meta Ads" and it\'s logged + categorized.' },
  { icon: ShieldCheck, title: 'Schema Isolation', desc: 'Every company\'s data in its own Postgres schema.' },
  { icon: Zap, title: 'Real-time Alerts', desc: 'Low stock, payout delays, spend spikes — via WebSockets.' },
  { icon: TrendingUp, title: 'Cash Flow Forecast', desc: '90-day runway projection from real data.' },
  { icon: Receipt, title: 'Expense Tracking', desc: 'Categorize, trend, and benchmark your opex.' },
  { icon: ShoppingBag, title: 'Order Sync', desc: 'Pull Shopify + Woo orders via OAuth. Backfill 1 year.' },
  { icon: Megaphone, title: 'Ad Analytics', desc: 'Meta + Google Ads blended ROAS, CPC, CTR.' },
  { icon: PackageSearch, title: 'Inventory Intelligence', desc: 'Days-of-cover forecast per SKU.' },
];
</script>
<template>
<Head title="Features" />
<GuestLayout>
  <section class="py-24">
    <div class="text-center max-w-2xl mx-auto mb-14 px-6">
      <div class="text-[10px] font-mono uppercase tracking-widest text-brand-400 mb-3">FEATURES</div>
      <h1 class="text-[44px] font-extrabold text-white leading-tight">Built for D2C operators.</h1>
    </div>
    <div class="mx-auto max-w-5xl px-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="f in features" :key="f.title" class="card group">
        <div class="h-10 w-10 rounded-[10px] bg-brand-600/15 border border-frost-2 flex items-center justify-center mb-4 group-hover:bg-brand-600/25 transition">
          <component :is="f.icon" :size="18" class="text-brand-300" />
        </div>
        <h3 class="text-[15px] font-bold text-white mb-2">{{ f.title }}</h3>
        <p class="text-[13px] text-ink-2 leading-relaxed">{{ f.desc }}</p>
      </div>
    </div>
  </section>
</GuestLayout>
</template>
