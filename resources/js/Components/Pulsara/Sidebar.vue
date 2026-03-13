<script setup>
import { Link } from '@inertiajs/vue3'
</script>

<template>

<aside class="w-72 bg-[#0f1424] border-r border-white/5 p-6 flex flex-col justify-between">

<div>

<!-- LOGO -->

<div class="flex items-center gap-3 mb-12">
<div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center">
⚡
</div>

<div>
<div class="text-lg font-semibold">Pulsara</div>
<div class="text-xs text-gray-400">D2C Ops AI</div>
</div>
</div>

<!-- PHASE 1 -->

<div class="text-xs text-gray-400 mb-2 uppercase">Phase 1</div>

<div class="space-y-2 text-sm mb-8">

<Link href="/brand/dashboard" class="block px-4 py-3 rounded-lg hover:bg-white/5">
Dashboard
</Link>

<Link href="/brand/pnl" class="block px-4 py-3 rounded-lg hover:bg-white/5">
P&L Report
</Link>

<Link href="/brand/expenses" class="block px-4 py-3 rounded-lg hover:bg-white/5">
Expenses
</Link>

<Link href="/brand/orders" class="block px-4 py-3 rounded-lg hover:bg-white/5">
Shopify Orders
</Link>

<Link href="/brand/ads" class="block px-4 py-3 rounded-lg hover:bg-white/5">
Ad Analytics
</Link>

</div>

<!-- PHASE 2 -->

<div class="flex items-center justify-between text-xs text-gray-400 mb-2 uppercase">
<span>Phase 2</span>
<span class="text-purple-400 text-[10px]">NEW</span>
</div>

<div class="space-y-2 text-sm">

<Link href="/brand/inventory" class="block px-4 py-3 rounded-lg hover:bg-white/5">
Inventory Forecast
</Link>

<Link href="/brand/payroll" class="block px-4 py-3 rounded-lg hover:bg-white/5">
Payroll Intelligence
</Link>

<Link href="/brand/cashflow" class="block px-4 py-3 rounded-lg hover:bg-white/5">
Cash Flow
</Link>

</div>

<!-- AI -->

<div class="text-xs text-gray-400 mt-8 mb-2 uppercase">
AI
</div>

<button
@click="$emit('open-ai')"
class="w-full text-left px-4 py-3 rounded-lg hover:bg-white/5 text-sm"
>
AI Copilot
</button>

</div>


<!-- INTEGRATIONS -->

<div class="border-t border-white/10 pt-4 mt-6">

<div class="text-xs text-gray-400 uppercase mb-3">
Integrations
</div>

<div class="space-y-2 text-sm">

<div class="flex justify-between items-center px-3 py-2 bg-[#121a30] rounded-lg">
<span>Shopify</span>
<span class="text-green-400 text-xs">Live</span>
</div>

<div class="flex justify-between items-center px-3 py-2 bg-[#121a30] rounded-lg">
<span>Meta Ads</span>
<span class="text-green-400 text-xs">Live</span>
</div>

<div class="flex justify-between items-center px-3 py-2 bg-[#121a30] rounded-lg">
<span>Google Ads</span>
<span class="text-yellow-400 text-xs">Syncing</span>
</div>

</div>

</div>

</aside>

</template>