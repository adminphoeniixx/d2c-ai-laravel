<script setup>

import PulsaraLayout from '@/Layouts/PulsaraLayout.vue'
import TopHeader from '@/Components/Pulsara/TopHeader.vue'
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    orders: {
        type: Array,
        default: () => []
    }
})

</script>


<template>

<PulsaraLayout>

<TopHeader />

<div class="p-8">

<div class="flex justify-between items-center mb-6">

<h1 class="text-2xl font-semibold">
Shopify Orders
</h1>

<Link
href="/brand/shopify/fetch-orders"
class="px-5 py-2 bg-purple-600 rounded-lg text-white hover:bg-purple-700 transition"
>
Fetch Orders
</Link>

</div>


<div class="bg-[#0f172a] rounded-xl border border-white/5 overflow-hidden">

<table class="w-full text-sm">

<thead class="bg-white/5">

<tr>

<th class="py-4 px-6 text-left">Order</th>
<th class="px-6 text-left">Customer</th>
<th class="px-6 text-left">Products</th>
<th class="px-6 text-left">Amount</th>
<th class="px-6 text-left">Status</th>
<th class="px-6 text-left">Date</th>

</tr>

</thead>


<tbody>

<tr
v-for="order in props.orders"
:key="order.shopify_id"
class="border-t border-white/5 hover:bg-white/5 transition"
>

<td class="py-4 px-6 font-medium">
#{{ order.order_number }}
</td>

<td class="px-6">
{{ order.customer?.name ?? 'Guest' }}
</td>

<td class="px-6">
{{ order.products?.length ?? 0 }} items
</td>

<td class="px-6">
{{ order.total_price }} {{ order.currency }}
</td>

<td class="px-6">

<span
class="px-2 py-1 rounded text-xs"
:class="order.financial_status === 'paid'
? 'bg-green-500/20 text-green-400'
: 'bg-yellow-500/20 text-yellow-400'"
>
{{ order.financial_status }}
</span>

</td>

<td class="px-6 text-gray-400">
{{ new Date(order.created_at_shopify).toLocaleDateString() }}
</td>

</tr>


<tr v-if="!props.orders.length">

<td colspan="6" class="text-center py-12 text-gray-400">
No Shopify orders found
</td>

</tr>

</tbody>

</table>

</div>

</div>

</PulsaraLayout>

</template>