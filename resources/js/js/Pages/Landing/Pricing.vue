<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
const plans = [
  { name: 'Free', price: 0, features: ['1 store', '1 seat', 'Basic dashboard', 'Manual expense entry'] },
  { name: 'Pro', price: 2499, popular: true, features: ['3 stores', '5 seats', 'AI Copilot', 'Real-time alerts', 'Ad analytics', 'Inventory forecast'] },
  { name: 'Enterprise', price: 9999, features: ['Unlimited stores', 'SSO', 'Dedicated support', 'Custom integrations', 'Priority sync', 'Audit trail'] },
];
</script>
<template>
<Head title="Pricing" />
<GuestLayout>
  <section class="py-24">
    <div class="text-center max-w-2xl mx-auto mb-14 px-6">
      <div class="text-[10px] font-mono uppercase tracking-widest text-brand-400 mb-3">PRICING</div>
      <h1 class="text-[44px] font-extrabold text-white leading-tight">Simple, transparent pricing.</h1>
      <p class="mt-4 text-[16px] text-ink-2">Start free. Upgrade when you need more.</p>
    </div>
    <div class="mx-auto max-w-5xl px-6 grid grid-cols-1 md:grid-cols-3 gap-5">
      <div v-for="p in plans" :key="p.name" class="card relative" :class="p.popular ? 'border-brand-600 shadow-glow' : ''">
        <div v-if="p.popular" class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-0.5 bg-brand-gradient rounded-full text-[10px] font-bold text-white uppercase tracking-wider">Most Popular</div>
        <div class="text-[13px] text-ink-3 font-mono uppercase tracking-wider mb-2">{{ p.name }}</div>
        <div class="text-[36px] font-extrabold text-white leading-none">₹{{ p.price.toLocaleString('en-IN') }}<span class="text-[14px] font-normal text-ink-3">/mo</span></div>
        <ul class="mt-5 space-y-2">
          <li v-for="f in p.features" :key="f" class="flex items-center gap-2 text-[13px] text-ink-2"><Check :size="14" class="text-emerald flex-shrink-0" /> {{ f }}</li>
        </ul>
        <Link :href="route('company.register')" class="btn w-full mt-6 py-2.5" :class="p.popular ? 'btn-primary' : 'btn-ghost'">
          {{ p.price === 0 ? 'Start free' : 'Get started' }}
        </Link>
      </div>
    </div>
  </section>
</GuestLayout>
</template>
