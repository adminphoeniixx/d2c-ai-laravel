<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>
<div class="min-h-screen bg-[#0B0F1A] text-white overflow-hidden">

    <!-- Background glow -->
    <div class="absolute -top-40 -left-40 w-[500px] h-[500px] bg-purple-600/20 rounded-full blur-[150px]"></div>
    <div class="absolute -bottom-40 -right-40 w-[500px] h-[500px] bg-pink-600/20 rounded-full blur-[150px]"></div>

    <!-- NAV -->
    <header class="relative flex justify-between items-center px-12 py-6 border-b border-white/5">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl flex items-center justify-center">
                ⚡
            </div>
            <div>
                <div class="font-semibold text-lg">Pulsara</div>
                <div class="text-xs text-gray-400">D2C Ops AI</div>
            </div>
        </div>

        <div class="flex gap-6 items-center">
            <Link href="/login" class="text-gray-300 hover:text-white">Login</Link>
            <Link href="/register"
                  class="bg-gradient-to-r from-purple-600 to-pink-500 px-5 py-2 rounded-xl">
                Get Started
            </Link>
        </div>
    </header>

    <!-- HERO -->
    <section class="relative text-center py-28 px-6">

        <h1 class="text-5xl font-bold leading-tight max-w-4xl mx-auto">
            Stop Tracking Revenue. <br>
            <span class="bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
                Start Tracking Real Profit.
            </span>
        </h1>

        <p class="text-gray-400 mt-6 max-w-2xl mx-auto text-lg">
            Pulsara gives D2C founders real-time clarity across revenue,
            ad spend, expenses and net profitability — in one intelligent dashboard.
        </p>

        <div class="mt-10 flex justify-center gap-6">
            <Link href="/register"
                class="bg-gradient-to-r from-purple-600 to-pink-500 px-8 py-4 rounded-xl text-lg">
                Start Free Trial
            </Link>

            <a href="#features"
               class="border border-white/20 px-8 py-4 rounded-xl text-lg">
                See Features
            </a>
        </div>

    </section>

    <!-- PROBLEM SECTION -->
    <section class="py-24 px-12 text-center border-t border-white/5">
        <h2 class="text-3xl font-semibold mb-6">
            D2C Brands Don't Fail From Lack of Sales.
        </h2>

        <p class="text-gray-400 max-w-3xl mx-auto">
            They fail because they don't know their real margins.
            Revenue looks great.
            ROAS looks good.
            But hidden expenses, agency fees and operational costs
            quietly destroy cash flow.
        </p>
    </section>

    <!-- FEATURES -->
    <section id="features" class="py-24 px-12">

        <h2 class="text-3xl font-semibold text-center mb-16">
            Everything You Need To Run A Profitable Brand
        </h2>

        <div class="grid grid-cols-3 gap-10 max-w-6xl mx-auto">

            <div class="bg-[#111827] border border-white/5 p-8 rounded-2xl">
                <h3 class="text-xl font-semibold mb-4">Unified P&L Dashboard</h3>
                <p class="text-gray-400">
                    Revenue, ad spend, COGS and operational costs in one place.
                </p>
            </div>

            <div class="bg-[#111827] border border-white/5 p-8 rounded-2xl">
                <h3 class="text-xl font-semibold mb-4">Shopify Auto Sync</h3>
                <p class="text-gray-400">
                    Pull real-time order data directly from your store.
                </p>
            </div>

            <div class="bg-[#111827] border border-white/5 p-8 rounded-2xl">
                <h3 class="text-xl font-semibold mb-4">Meta & Google Ad Tracking</h3>
                <p class="text-gray-400">
                    Track real ROAS across platforms instantly.
                </p>
            </div>

            <div class="bg-[#111827] border border-white/5 p-8 rounded-2xl">
                <h3 class="text-xl font-semibold mb-4">AI Expense Logging</h3>
                <p class="text-gray-400">
                    Add expenses via voice and let AI categorize them.
                </p>
            </div>

            <div class="bg-[#111827] border border-white/5 p-8 rounded-2xl">
                <h3 class="text-xl font-semibold mb-4">Profit Margin Intelligence</h3>
                <p class="text-gray-400">
                    See real net margins daily — not just vanity revenue.
                </p>
            </div>

            <div class="bg-[#111827] border border-white/5 p-8 rounded-2xl">
                <h3 class="text-xl font-semibold mb-4">Cash Flow Visibility</h3>
                <p class="text-gray-400">
                    Know exactly when your runway becomes risky.
                </p>
            </div>

        </div>
    </section>

    <!-- CTA -->
    <section class="py-28 text-center border-t border-white/5">

        <h2 class="text-4xl font-bold mb-6">
            Built For Revenue-Driven Founders.
        </h2>

        <p class="text-gray-400 mb-10">
            If you care about margins more than vanity metrics —
            Pulsara is built for you.
        </p>

        <Link href="/register"
              class="bg-gradient-to-r from-purple-600 to-pink-500 px-10 py-5 rounded-2xl text-lg">
            Create Your Brand Workspace
        </Link>

    </section>

    <!-- FOOTER -->
    <footer class="py-10 text-center text-gray-500 border-t border-white/5">
        © 2026 Pulsara — D2C Ops AI
    </footer>

</div>
</template>