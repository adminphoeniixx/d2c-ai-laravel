<script setup>

import { ref, onMounted } from 'vue'
import PulsaraLayout from '@/Layouts/PulsaraLayout.vue'
import TopHeader from '@/Components/Pulsara/TopHeader.vue'

const props = defineProps({
    connection: Object
})

const shopDomain = ref('')

onMounted(() => {
    if (props.connection) {
        shopDomain.value = props.connection.shop
    }
})

function connectShopify(){

    if(!shopDomain.value){
        alert('Please enter your Shopify store domain')
        return
    }

    window.location.href = `/brand/shopify/connect?shop=${shopDomain.value}`

}

</script>


<template>

<PulsaraLayout>

<TopHeader />

<div class="p-8 w-full max-w-[900px] mx-auto">

<h1 class="text-2xl font-semibold mb-6">
Shopify Integration
</h1>

<div class="bg-[#121a30] border border-white/5 rounded-xl p-8">

<div class="mb-6">

<h2 class="text-lg font-semibold">
Connect Shopify Store
</h2>

<p class="text-sm text-gray-400 mt-1">
Connect your Shopify store to sync orders, products and inventory automatically.
</p>

</div>

<div class="flex gap-4 items-center">

<input
v-model="shopDomain"
type="text"
placeholder="mystore.myshopify.com"
class="flex-1 bg-[#0f1424] border border-white/10 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-purple-500"
/>

<button
v-if="!props.connection"
@click="connectShopify"
class="bg-purple-600 hover:bg-purple-700 px-6 py-2 rounded-lg text-sm"
>
Connect Shopify
</button>

<div
v-if="props.connection"
class="bg-green-600 px-6 py-2 rounded-lg text-sm"
>
Connected
</div>

</div>

<p class="text-xs text-gray-500 mt-3">
Example: <span class="text-gray-400">yourstore.myshopify.com</span>
</p>

<div
v-if="props.connection"
class="mt-4 text-green-400 text-sm"
>
Shopify connected successfully to <b>{{ props.connection.shop }}</b>
</div>

</div>

</div>

</PulsaraLayout>

</template>