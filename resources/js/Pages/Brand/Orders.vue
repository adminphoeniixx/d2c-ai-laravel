<script setup>
import { ref } from "vue"
import PulsaraLayout from "@/Layouts/PulsaraLayout.vue"
import TopHeader from "@/Components/Pulsara/TopHeader.vue"

const orders = ref([
{
id:10432,
customer:"Priya Sharma",
product:"Premium Bundle",
amount:3499,
date:"Feb 23",
status:"fulfilled"
},
{
id:10431,
customer:"Rahul Verma",
product:"Starter Kit",
amount:1299,
date:"Feb 23",
status:"pending"
},
{
id:10430,
customer:"Anjali Singh",
product:"Pro Bundle",
amount:5999,
date:"Feb 22",
status:"fulfilled"
},
{
id:10429,
customer:"Karan Mehta",
product:"Mini Pack",
amount:899,
date:"Feb 22",
status:"refunded"
},
{
id:10428,
customer:"Neha Gupta",
product:"Essential Kit",
amount:2199,
date:"Feb 21",
status:"fulfilled"
}
])

const kpis = {
ordersToday:28,
pending:7,
revenue:67240,
refunds:3200
}

function exportCSV(){

let csv = "OrderID,Customer,Product,Amount,Date,Status\n"

orders.value.forEach(o=>{
csv += `${o.id},${o.customer},${o.product},${o.amount},${o.date},${o.status}\n`
})

const blob = new Blob([csv],{type:"text/csv"})
const url = URL.createObjectURL(blob)

const a = document.createElement("a")
a.href=url
a.download="shopify-orders.csv"
a.click()

}
</script>

<template>

<PulsaraLayout>

<TopHeader/>

<div class="p-8">

<!-- KPI CARDS -->

<div class="grid grid-cols-4 gap-6 mb-8">

<div class="bg-[#121a30] border border-white/5 rounded-xl p-5">
<div class="text-xs text-gray-400 mb-1">Orders Today</div>
<div class="text-2xl font-semibold text-green-400">
{{kpis.ordersToday}}
</div>
</div>

<div class="bg-[#121a30] border border-white/5 rounded-xl p-5">
<div class="text-xs text-gray-400 mb-1">Pending Fulfilment</div>
<div class="text-2xl font-semibold text-yellow-400">
{{kpis.pending}}
</div>
</div>

<div class="bg-[#121a30] border border-white/5 rounded-xl p-5">
<div class="text-xs text-gray-400 mb-1">Revenue Today</div>
<div class="text-2xl font-semibold text-purple-400">
₹{{kpis.revenue}}
</div>
</div>

<div class="bg-[#121a30] border border-white/5 rounded-xl p-5">
<div class="text-xs text-gray-400 mb-1">Refunds Today</div>
<div class="text-2xl font-semibold text-red-400">
₹{{kpis.refunds}}
</div>
</div>

</div>

<!-- ORDERS TABLE -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">

<div class="flex justify-between items-center mb-5">

<h2 class="text-lg font-semibold">
All Shopify Orders
</h2>

<button
@click="exportCSV"
class="bg-purple-600 px-4 py-2 rounded-lg text-sm"
>
Export CSV
</button>

</div>

<table class="w-full text-sm">

<thead class="border-b border-white/5 text-gray-400">

<tr>
<th class="py-3 text-left">Order ID</th>
<th class="text-left">Customer</th>
<th class="text-left">Product</th>
<th class="text-left">Amount</th>
<th class="text-left">Date</th>
<th class="text-left">Status</th>
</tr>

</thead>

<tbody>

<tr
v-for="order in orders"
:key="order.id"
class="border-b border-white/5"
>

<td class="py-3 text-purple-400 font-medium">
#{{order.id}}
</td>

<td>{{order.customer}}</td>

<td class="text-gray-400">
{{order.product}}
</td>

<td class="font-semibold">
₹{{order.amount}}
</td>

<td class="text-gray-400">
{{order.date}}
</td>

<td>

<span
v-if="order.status=='fulfilled'"
class="bg-green-500/20 text-green-400 px-2 py-1 rounded text-xs"
>
Fulfilled
</span>

<span
v-if="order.status=='pending'"
class="bg-yellow-500/20 text-yellow-400 px-2 py-1 rounded text-xs"
>
Pending
</span>

<span
v-if="order.status=='refunded'"
class="bg-red-500/20 text-red-400 px-2 py-1 rounded text-xs"
>
Refunded
</span>

</td>

</tr>

</tbody>

</table>

</div>

</div>

</PulsaraLayout>

</template>