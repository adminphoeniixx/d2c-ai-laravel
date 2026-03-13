<script setup>
import { onMounted } from "vue"
import PulsaraLayout from "@/Layouts/PulsaraLayout.vue"
import TopHeader from "@/Components/Pulsara/TopHeader.vue"
import Chart from "chart.js/auto"

onMounted(()=>{

const ctx = document.getElementById("adsChart")

new Chart(ctx,{
type:"line",
data:{
labels:["Mon","Tue","Wed","Thu","Fri","Sat","Sun"],
datasets:[
{
label:"Meta Ads Revenue",
data:[12000,18000,22000,26000,30000,34000,38000],
borderColor:"#6c63ff",
backgroundColor:"rgba(108,99,255,0.2)",
tension:0.4
},
{
label:"Google Ads Revenue",
data:[9000,12000,15000,18000,20000,23000,25000],
borderColor:"#43e97b",
backgroundColor:"rgba(67,233,123,0.2)",
tension:0.4
}
]
},
options:{
plugins:{
legend:{labels:{color:"#ccc"}}
},
scales:{
x:{ticks:{color:"#888"}},
y:{ticks:{color:"#888"}}
}
}
})

})
</script>

<template>

<PulsaraLayout>

<TopHeader/>

<div class="p-8">

<!-- AD PLATFORM CARDS -->

<div class="grid grid-cols-3 gap-6 mb-8">

<!-- META ADS -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-5">

<div class="flex items-center justify-between mb-4">

<div class="flex items-center gap-2">
<div class="w-8 h-8 rounded bg-blue-600 flex items-center justify-center text-white">
📘
</div>

<div>
<div class="font-semibold">Meta Ads</div>
<div class="text-xs text-gray-400">Facebook + Instagram</div>
</div>

</div>

<span class="text-xs bg-green-500/20 text-green-400 px-2 py-1 rounded">
Live
</span>

</div>

<div class="grid grid-cols-2 gap-4 text-sm">

<div>
<div class="text-gray-400 text-xs">Spend</div>
<div class="text-lg font-semibold">₹1.4L</div>
</div>

<div>
<div class="text-gray-400 text-xs">ROAS</div>
<div class="text-lg font-semibold text-green-400">3.8x</div>
</div>

<div>
<div class="text-gray-400 text-xs">CTR</div>
<div class="text-lg font-semibold">1.2%</div>
</div>

<div>
<div class="text-gray-400 text-xs">CPC</div>
<div class="text-lg font-semibold">₹142</div>
</div>

</div>

</div>

<!-- GOOGLE ADS -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-5">

<div class="flex items-center justify-between mb-4">

<div class="flex items-center gap-2">
<div class="w-8 h-8 rounded bg-green-600 flex items-center justify-center text-white">
G
</div>

<div>
<div class="font-semibold">Google Ads</div>
<div class="text-xs text-gray-400">Search + Shopping</div>
</div>

</div>

<span class="text-xs bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded">
Syncing
</span>

</div>

<div class="grid grid-cols-2 gap-4 text-sm">

<div>
<div class="text-gray-400 text-xs">Spend</div>
<div class="text-lg font-semibold">₹98K</div>
</div>

<div>
<div class="text-gray-400 text-xs">ROAS</div>
<div class="text-lg font-semibold text-green-400">4.2x</div>
</div>

<div>
<div class="text-gray-400 text-xs">CTR</div>
<div class="text-lg font-semibold">2.8%</div>
</div>

<div>
<div class="text-gray-400 text-xs">CPC</div>
<div class="text-lg font-semibold">₹89</div>
</div>

</div>

</div>

<!-- SHOPIFY REVENUE -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-5">

<div class="flex items-center justify-between mb-4">

<div class="flex items-center gap-2">
<div class="w-8 h-8 rounded bg-green-700 flex items-center justify-center text-white">
🛒
</div>

<div>
<div class="font-semibold">Shopify</div>
<div class="text-xs text-gray-400">Orders + Revenue</div>
</div>

</div>

<span class="text-xs bg-green-500/20 text-green-400 px-2 py-1 rounded">
Live
</span>

</div>

<div class="grid grid-cols-2 gap-4 text-sm">

<div>
<div class="text-gray-400 text-xs">Revenue</div>
<div class="text-lg font-semibold">₹8.2L</div>
</div>

<div>
<div class="text-gray-400 text-xs">Orders</div>
<div class="text-lg font-semibold text-green-400">342</div>
</div>

<div>
<div class="text-gray-400 text-xs">AOV</div>
<div class="text-lg font-semibold">₹2398</div>
</div>

<div>
<div class="text-gray-400 text-xs">Refund</div>
<div class="text-lg font-semibold">2.1%</div>
</div>

</div>

</div>

</div>

<!-- CHART -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">

<div class="flex justify-between mb-4">
<h2 class="text-lg font-semibold">
Ad Performance Trend
</h2>
</div>

<canvas id="adsChart" height="120"></canvas>

</div>

</div>

</PulsaraLayout>

</template>