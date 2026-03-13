<script setup>
import { ref } from 'vue'
import PulsaraLayout from '@/Layouts/PulsaraLayout.vue'
import TopHeader from '@/Components/Pulsara/TopHeader.vue'

const voiceText = ref('')

const expenses = ref([
    {
        id:1,
        date:'2026-02-23',
        desc:'Meta Ads Campaign',
        cat:'Advertising',
        amount:140000,
        source:'Manual'
    },
    {
        id:2,
        date:'2026-02-22',
        desc:'Google Ads',
        cat:'Advertising',
        amount:98000,
        source:'Manual'
    },
    {
        id:3,
        date:'2026-02-21',
        desc:'Packaging Material',
        cat:'Operations',
        amount:42000,
        source:'Manual'
    },
    {
        id:4,
        date:'2026-02-21',
        desc:'Shipping Charges',
        cat:'Logistics',
        amount:36000,
        source:'Manual'
    }
])

function addExpense(){
    if(!voiceText.value) return

    expenses.value.unshift({
        id:Date.now(),
        date:new Date().toISOString().slice(0,10),
        desc:voiceText.value,
        cat:'Other',
        amount:0,
        source:'Voice'
    })

    voiceText.value=''
}
</script>

<template>

<PulsaraLayout>

<TopHeader />

<div class="p-8">

<!-- VOICE EXPENSE BAR -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-4 flex items-center gap-4 mb-6">

<button class="w-10 h-10 rounded-full bg-gradient-to-br from-pink-500 to-red-500 flex items-center justify-center">
🎤
</button>

<div class="flex-1">
<input
v-model="voiceText"
placeholder="Say or type expense…"
class="w-full bg-[#0f1424] border border-white/10 rounded-lg px-4 py-2 text-sm"
/>
</div>

<button
@click="addExpense"
class="bg-purple-600 px-5 py-2 rounded-lg text-sm"
>
Add
</button>

</div>


<!-- EXPENSE TABLE -->

<div class="bg-[#121a30] border border-white/5 rounded-xl p-6">

<div class="flex justify-between mb-4">
<h2 class="font-semibold text-lg">
All Expenses
</h2>

<div class="text-sm text-gray-400">
{{ expenses.length }} entries
</div>
</div>

<table class="w-full text-sm">

<thead class="text-gray-400 border-b border-white/5">
<tr>
<th class="py-2 text-left">Date</th>
<th class="py-2 text-left">Description</th>
<th class="py-2 text-left">Category</th>
<th class="py-2 text-left">Amount</th>
<th class="py-2 text-left">Source</th>
</tr>
</thead>

<tbody>

<tr
v-for="e in expenses"
:key="e.id"
class="border-b border-white/5"
>

<td class="py-3 text-gray-400">
{{ e.date }}
</td>

<td class="py-3">
{{ e.desc }}
</td>

<td class="py-3">
<span class="bg-green-500/20 text-green-400 px-2 py-1 rounded text-xs">
{{ e.cat }}
</span>
</td>

<td class="py-3 font-semibold text-pink-400">
₹{{ e.amount }}
</td>

<td class="py-3 text-gray-400 text-xs">
{{ e.source }}
</td>

</tr>

</tbody>

</table>

</div>

</div>

</PulsaraLayout>

</template>