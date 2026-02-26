<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>
    <aside class="w-72 bg-[#0f1424] border-r border-white/5 p-6">

        <div class="flex items-center gap-3 mb-12">
            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
                ⚡
            </div>
            <div>
                <div class="text-lg font-semibold">Pulsara</div>
                <div class="text-xs text-gray-400">D2C Ops AI</div>
            </div>
        </div>

        <div class="space-y-3 text-sm">

            <Link href="/brand/dashboard"
                  class="block px-4 py-3 rounded-lg bg-white/5 hover:bg-purple-600/30 transition">
                Dashboard
            </Link>

            <Link href="#" class="block px-4 py-3 rounded-lg hover:bg-white/5">
                P&L Report
            </Link>

            <Link href="#" class="block px-4 py-3 rounded-lg hover:bg-white/5">
                Expenses
            </Link>

            <Link href="#" class="block px-4 py-3 rounded-lg hover:bg-white/5">
                Shopify Orders
            </Link>

            <Link href="#" class="block px-4 py-3 rounded-lg hover:bg-white/5">
                Ad Analytics
            </Link>

        </div>

    </aside>
</template>