<script setup>

const rows = [
    { label: "Gross Revenue", value: "₹8,24,340" },
    { label: "COGS", value: "- ₹2,10,200" },
    { label: "Shipping", value: "- ₹62,000" },
    { label: "Payment Fees", value: "- ₹19,300" },
    { label: "Ad Spend", value: "- ₹1,48,700" },
    { label: "Operational Expenses", value: "- ₹99,000" },
]

const netProfit = "₹2,85,140"

</script>

<template>

<div class="bg-[#121a30] rounded-xl border border-white/5 p-6">

<h2 class="text-lg font-semibold mb-6">
Profit & Loss Statement
</h2>

<div class="space-y-3">

<div
v-for="row in rows"
:key="row.label"
class="flex justify-between text-sm border-b border-white/5 pb-2"
>
<span class="text-gray-400">
{{ row.label }}
</span>

<span>
{{ row.value }}
</span>

</div>

</div>

<div class="flex justify-between text-lg font-semibold mt-6">

<span>Net Profit</span>

<span class="text-green-400">
{{ netProfit }}
</span>

</div>

</div>

</template>