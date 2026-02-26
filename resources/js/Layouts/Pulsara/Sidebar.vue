<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <aside class="w-72 bg-[#0E1322] border-r border-white/5 p-6 flex flex-col justify-between">

    <div>
      <div class="flex items-center gap-3 mb-10">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-xl">
          ⚡
        </div>
        <div>
          <div class="font-semibold text-lg">Pulsara</div>
          <div class="text-xs text-gray-400">D2C OPS AI</div>
        </div>
      </div>

      <div class="text-xs text-gray-500 mb-3">PHASE 1</div>

      <div class="space-y-2">
        <Link href="/brand/dashboard"
          class="block px-4 py-3 rounded-xl bg-purple-600/20 border border-purple-500/30">
          Dashboard
        </Link>

        <div class="px-4 py-3 rounded-xl hover:bg-white/5 cursor-pointer">P&L Report</div>
        <div class="px-4 py-3 rounded-xl hover:bg-white/5 cursor-pointer">Expenses</div>
        <div class="px-4 py-3 rounded-xl hover:bg-white/5 cursor-pointer">Shopify Orders</div>
        <div class="px-4 py-3 rounded-xl hover:bg-white/5 cursor-pointer">Ad Analytics</div>
      </div>
    </div>

  </aside>
</template>