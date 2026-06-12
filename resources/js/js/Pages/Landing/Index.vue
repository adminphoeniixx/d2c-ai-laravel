<script setup>
import { Link, Head } from '@inertiajs/vue3';
import { TrendingUp, Zap, ShieldCheck, Sparkles, LineChart, Bot } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    metrics: { type: Array, default: () => [] },
    integrations: { type: Array, default: () => [] },
});

const features = [
    { icon: LineChart,  title: 'Unified P&L',        copy: 'See revenue, ad spend, COGS, payroll — all in one place, across Shopify & WooCommerce stores.' },
    { icon: Bot,        title: 'AI Copilot',         copy: 'Ask anything about your business. "Why did margin drop last week?" — answered in seconds.' },
    { icon: Sparkles,   title: 'Voice expenses',     copy: '"Spent ₹12,000 on Meta Ads today." heyd2c logs it, categorizes it, updates your P&L.' },
    { icon: ShieldCheck,title: 'Isolated tenancy',   copy: 'Your data lives in its own Postgres schema. No leaks, no queries crossing boundaries, ever.' },
    { icon: Zap,        title: 'Real-time alerts',   copy: 'Low stock, payout delays, spend spikes — pushed over WebSockets the moment they happen.' },
    { icon: TrendingUp, title: 'Forecasts',          copy: 'Inventory days-of-cover, cash runway, payroll impact — projected 90 days out.' },
];
</script>

<template>
    <Head title="The operating system for D2C brands" />
    <GuestLayout>
        <section class="relative pt-24 pb-20 lg:pt-32 lg:pb-28">
            <div class="mx-auto max-w-5xl px-6 text-center">
                <div class="inline-flex items-center gap-2 rounded-full border border-frost-2 bg-brand-600/10 px-3 py-1 mb-6">
                </div>

                <h1 class="text-[44px] lg:text-[72px] font-extrabold leading-[1.05] tracking-tight">
                    <span class="bg-gradient-to-br from-white via-brand-200 to-fuchsia bg-clip-text text-transparent">The operating system</span>
                    <br>
                    <span class="text-ink">for your D2C brand.</span>
                </h1>

                <p class="mt-6 text-[17px] lg:text-[19px] text-ink-2 max-w-2xl mx-auto leading-relaxed">
                    heyd2c plugs into Shopify, WooCommerce, and ad platforms — then turns the noise into a single source of truth.
                    Real-time dashboards, AI answers, and voice-speed expense logging.
                </p>

                <div class="mt-10 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link :href="route('company.register')" class="btn btn-primary px-6 py-3 text-[14px]">Start free — 14 days</Link>
                    <Link :href="route('features')" class="btn btn-ghost px-6 py-3 text-[14px]">See how it works</Link>
                </div>

                <div class="mt-6 text-[12px] font-mono text-ink-3">
                    No credit card required · Cancel anytime
                </div>
            </div>

            <div class="mt-20 mx-auto max-w-5xl px-6">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div v-for="m in metrics" :key="m.label" class="card text-center">
                        <div class="text-[28px] font-extrabold bg-gradient-to-br from-white to-brand-300 bg-clip-text text-transparent">{{ m.value }}</div>
                        <div class="mt-1 text-[11px] font-mono uppercase tracking-widest text-ink-3">{{ m.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-10 border-y border-frost-1">
            <div class="mx-auto max-w-5xl px-6 flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="text-[11px] font-mono uppercase tracking-widest text-ink-3">Plugs into what you already use</div>
                <div class="flex flex-wrap items-center gap-x-8 gap-y-3 justify-center">
                    <span v-for="i in integrations" :key="i" class="text-[14px] font-semibold text-ink-2 hover:text-ink transition">{{ i }}</span>
                </div>
            </div>
        </section>

        <section class="py-24">
            <div class="mx-auto max-w-5xl px-6">
                <div class="text-center max-w-2xl mx-auto mb-14">
                    <div class="text-[10px] font-mono uppercase tracking-widest text-brand-400 mb-3">WHAT YOU GET</div>
                    <h2 class="text-[32px] lg:text-[44px] font-extrabold text-white leading-tight tracking-tight">Every metric. One panel. No spreadsheets.</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="f in features" :key="f.title" class="card group">
                        <div class="h-10 w-10 rounded-[10px] bg-brand-600/15 border border-frost-2 flex items-center justify-center mb-4 group-hover:bg-brand-600/25 transition">
                            <component :is="f.icon" :size="18" class="text-brand-300" />
                        </div>
                        <h3 class="text-[15px] font-bold text-white mb-2">{{ f.title }}</h3>
                        <p class="text-[13px] text-ink-2 leading-relaxed">{{ f.copy }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-20">
            <div class="mx-auto max-w-4xl px-6">
                <div class="relative overflow-hidden rounded-[24px] border border-frost-2 bg-gradient-to-br from-surface to-surface-2 p-10 lg:p-14 glow-surface">
                    <h2 class="text-[28px] lg:text-[40px] font-extrabold tracking-tight text-white max-w-lg leading-tight">
                        Stop jumping between 11 dashboards.
                    </h2>
                    <p class="mt-3 text-[15px] text-ink-2 max-w-md">Connect your stores in 60 seconds. Your first dashboard renders before your coffee goes cold.</p>
                    <div class="mt-7">
                        <Link :href="route('company.register')" class="btn btn-primary px-6 py-3 text-[14px]">Create your workspace →</Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-frost-1 py-10">
            <div class="mx-auto max-w-4xl px-6">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="text-[12px] text-ink-3">© 2026 SaltyPay Software Pvt Ltd · heyd2c</div>
                    <div class="flex items-center gap-5 text-[12px]">
                        <Link :href="route('privacy')" class="text-ink-3 hover:text-white transition">Privacy Policy</Link>
                        <Link :href="route('terms')" class="text-ink-3 hover:text-white transition">Terms of Service</Link>
                        <Link :href="route('refund')" class="text-ink-3 hover:text-white transition">Refund Policy</Link>
                        <Link :href="route('contact')" class="text-ink-3 hover:text-white transition">Contact Us</Link>
                    </div>
                </div>
                <div class="text-center mt-4 text-[11px] text-ink-3">
                    504, Village Choma, New Gurugram, Haryana 122017 · <a href="mailto:app@heyd2c.ai" class="text-brand-300 hover:underline">app@heyd2c.ai</a> · +91 8860616130
                </div>
            </div>
        </footer>
    </GuestLayout>
</template>
