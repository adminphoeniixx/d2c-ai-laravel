<script setup>
import PulsaraLayout from '@/Layouts/PulsaraLayout.vue'
import TopHeader from '@/Components/Pulsara/TopHeader.vue'
import { onMounted } from 'vue'
import Chart from 'chart.js/auto'

onMounted(()=>{

const ctx = document.getElementById('cashflowChart')

if(!ctx) return

new Chart(ctx,{
type:'line',
data:{
labels:['Jan','Feb','Mar','Apr','May','Jun'],
datasets:[
{
label:'Revenue',
data:[120000,160000,210000,240000,280000,320000],
borderColor:'#22c55e',
backgroundColor:'rgba(34,197,94,0.15)',
tension:0.4,
fill:true
},
{
label:'Expenses',
data:[90000,110000,140000,180000,200000,230000],
borderColor:'#ef4444',
backgroundColor:'rgba(239,68,68,0.15)',
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

<div class="flex justify-between items-center">

<h1 class="text-2xl font-semibold">
Cash Flow Intelligence
</h1>

<select class="bg-[#121a30] border border-white/10 rounded-lg px-4 py-2 text-sm">
<option>Last 30 days</option>
<option>Last 90 days</option>
<option>This year</option>
</select>

</div>

<!-- KPI CARDS -->

<div class="grid grid-cols-4 gap-6 mb-8">

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">
<div class="text-sm text-gray-400">Total Revenue</div>
<div class="text-3xl font-bold mt-2 text-green-400">₹8.2L</div>
<div class="text-xs text-green-400 mt-2">↑ 12% growth</div>
</div>

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">
<div class="text-sm text-gray-400">Total Expenses</div>
<div class="text-3xl font-bold mt-2 text-red-400">₹5.4L</div>
<div class="text-xs text-gray-400 mt-2">Operational costs</div>
</div>

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">
<div class="text-sm text-gray-400">Net Cash Flow</div>
<div class="text-3xl font-bold mt-2 text-green-400">₹2.8L</div>
<div class="text-xs text-green-400 mt-2">Positive</div>
</div>

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">
<div class="text-sm text-gray-400">Runway</div>
<div class="text-3xl font-bold mt-2 text-yellow-400">7.2 months</div>
<div class="text-xs text-gray-400 mt-2">Based on burn rate</div>
</div>

</div>

<!-- CASHFLOW CHART -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">

<h2 class="text-lg font-semibold mb-4">
Cash Flow Trend
</h2>

<canvas id="cashflowChart" height="140"></canvas>

</div>

<!-- UPCOMING PAYMENTS -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">

<h2 class="text-lg font-semibold mb-4">
Upcoming Payments
</h2>

<table class="min-w-full text-sm">

<thead class="text-gray-400 border-b border-white/10">

<tr>
<th class="text-left py-3 pr-6">Expense</th>
<th class="text-left pr-6">Category</th>
<th class="text-left pr-6">Amount</th>
<th class="text-left">Due Date</th>
</tr>

</thead>

<tbody class="divide-y divide-white/5">

<tr>
<td class="py-4 pr-6">Facebook Ads</td>
<td class="pr-6">Marketing</td>
<td class="text-red-400 pr-6">₹65,000</td>
<td>25 Mar</td>
</tr>

<tr>
<td class="py-4 pr-6">Warehouse Rent</td>
<td class="pr-6">Operations</td>
<td class="text-red-400 pr-6">₹42,000</td>
<td>28 Mar</td>
</tr>

<tr>
<td class="py-4 pr-6">Salary Payments</td>
<td class="pr-6">Payroll</td>
<td class="text-red-400 pr-6">₹1.2L</td>
<td>30 Mar</td>
</tr>

</tbody>

</table>

</div>

</div>

</PulsaraLayout>

</template>