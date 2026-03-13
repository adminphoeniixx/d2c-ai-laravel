<script setup>
import PulsaraLayout from '@/Layouts/PulsaraLayout.vue'
import TopHeader from '@/Components/Pulsara/TopHeader.vue'
import { onMounted } from 'vue'
import Chart from 'chart.js/auto'

onMounted(() => {

const ctx = document.getElementById('inventoryChart')

if(!ctx) return

new Chart(ctx,{
type:'line',
data:{
labels:['Jan','Feb','Mar','Apr','May','Jun'],
datasets:[
{
label:'Inventory Level',
data:[1200,1000,820,640,520,360],
borderColor:'#6c63ff',
backgroundColor:'rgba(108,99,255,0.2)',
tension:0.4,
fill:true
}
]
},
options:{
plugins:{
legend:{labels:{color:'#ccc'}}
},
scales:{
x:{ticks:{color:'#888'}},
y:{ticks:{color:'#888'}}
}
}
})

})
</script>

<template>

<PulsaraLayout>

<TopHeader />

<div class="p-8 w-full max-w-[1400px] mx-auto space-y-10">

<!-- HEADER -->

<div class="flex items-center justify-between mb-5">

<h1 class="text-2xl font-semibold">
Inventory Forecast
</h1>

<div class="flex items-center gap-3">

<select class="bg-[#121a30] border border-white/10 rounded-lg px-4 py-2 text-sm">
<option>Last 7 days</option>
<option>Last 30 days</option>
<option>Last 90 days</option>
</select>

<button class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg text-sm">
Export
</button>

</div>

</div>

<!-- KPI CARDS -->

<div class="grid grid-cols-4 gap-6 mb-8">

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">
<div class="text-sm text-gray-400">Total SKUs Tracked</div>
<div class="text-3xl font-bold mt-2">48</div>
<div class="text-xs text-green-400 mt-2">↑ 6 new this month</div>
</div>

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">
<div class="text-sm text-gray-400">Low Stock Alerts</div>
<div class="text-3xl font-bold text-yellow-400 mt-2">5</div>
<div class="text-xs text-gray-400 mt-2">Reorder needed now</div>
</div>

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">
<div class="text-sm text-gray-400">Forecast Accuracy</div>
<div class="text-3xl font-bold text-green-400 mt-2">91.4%</div>
<div class="text-xs text-green-400 mt-2">↑ 3.2% vs last model</div>
</div>

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">
<div class="text-sm text-gray-400">Predicted Stockout</div>
<div class="text-3xl font-bold text-red-400 mt-2">3 days</div>
<div class="text-xs text-gray-400 mt-2">Pro Bundle critical</div>
</div>

</div>

<!-- MAIN GRID -->

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

<!-- AI REORDER SUGGESTIONS -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6 space-y-4">

<h2 class="text-lg font-semibold">
🤖 AI Reorder Suggestions
</h2>

<div class="flex items-center justify-between bg-[#0f1424] p-4 rounded-lg">

<div>
<div class="font-semibold">Pro Bundle</div>
<div class="text-xs text-gray-400">Current: 42 units · Reorder: 200 units</div>
</div>

<span class="text-red-400 text-sm">🔴 Critical</span>

</div>

<div class="flex items-center justify-between bg-[#0f1424] p-4 rounded-lg">

<div>
<div class="font-semibold">Essential Kit</div>
<div class="text-xs text-gray-400">Current: 88 units · Reorder: 150 units</div>
</div>

<span class="text-yellow-400 text-sm">🟡 Low Stock</span>

</div>

<div class="flex items-center justify-between bg-[#0f1424] p-4 rounded-lg">

<div>
<div class="font-semibold">Travel Pack</div>
<div class="text-xs text-gray-400">Current: 65 units · Reorder: 120 units</div>
</div>

<span class="text-yellow-400 text-sm">🟡 Watch</span>

</div>

<div class="flex items-center justify-between bg-[#0f1424] p-4 rounded-lg">

<div>
<div class="font-semibold">Starter Pack</div>
<div class="text-xs text-gray-400">Current: 215 units · Sufficient</div>
</div>

<span class="text-green-400 text-sm">🟢 Healthy</span>

</div>

</div>

<!-- INVENTORY TREND -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">

<h2 class="text-lg font-semibold mb-4">
Inventory Trend
</h2>

<canvas id="inventoryChart" height="160"></canvas>

</div>

</div>

<!-- INVENTORY TABLE -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6 overflow-x-auto">

<h2 class="text-lg font-semibold mb-4">
Inventory Forecast
</h2>

<table class="min-w-full text-sm">

<thead class="text-gray-400 border-b border-white/10">

<tr>

<th class="text-left py-3 pr-6">Product</th>
<th class="text-left pr-6">SKU</th>
<th class="text-left pr-6">Stock</th>
<th class="text-left pr-6">Daily Sales</th>
<th class="text-left pr-6">Days Left</th>
<th class="text-left">Recommendation</th>

</tr>

</thead>

<tbody class="divide-y divide-white/5">

<tr>
<td class="py-4 pr-6">Protein Powder</td>
<td class="pr-6">PRO-112</td>
<td class="text-yellow-400 pr-6">45</td>
<td class="pr-6">12/day</td>
<td class="text-red-400 pr-6">3 days</td>
<td class="text-green-400">Reorder 200 units</td>
</tr>

<tr>
<td class="py-4 pr-6">Face Serum</td>
<td class="pr-6">FS-220</td>
<td class="text-yellow-400 pr-6">30</td>
<td class="pr-6">8/day</td>
<td class="text-red-400 pr-6">4 days</td>
<td class="text-green-400">Reorder 150 units</td>
</tr>

<tr>
<td class="py-4 pr-6">Hair Oil</td>
<td class="pr-6">HO-440</td>
<td class="text-green-400 pr-6">120</td>
<td class="pr-6">6/day</td>
<td class="text-green-400 pr-6">20 days</td>
<td class="text-gray-400">Safe</td>
</tr>

</tbody>

</table>

</div>

</div>

</PulsaraLayout>

</template>