<script setup>
import PulsaraLayout from '@/Layouts/PulsaraLayout.vue'
import TopHeader from '@/Components/Pulsara/TopHeader.vue'
import { ref } from 'vue'

const messages = ref([
{
role: "assistant",
text: "Hi 👋 I'm Pulsara AI. Ask me anything about your business performance."
}
])

const input = ref("")

function sendMessage() {

if(!input.value.trim()) return

messages.value.push({
role:"user",
text: input.value
})

messages.value.push({
role:"assistant",
text:"Analyzing your business data..."
})

input.value=""
}
</script>

<template>

<PulsaraLayout>

<TopHeader />

<div class="p-8 w-full max-w-[1200px] mx-auto">

<!-- HEADER -->

<div class="mb-6">

<h1 class="text-2xl font-semibold">
AI Copilot
</h1>

<p class="text-sm text-gray-400">
Ask anything about your D2C performance, inventory, ads, or finance.
</p>

</div>

<!-- CHAT CONTAINER -->

<div class="bg-[#121a30] border border-white/5 rounded-xl h-[600px] flex flex-col">

<!-- MESSAGES -->

<div class="flex-1 overflow-y-auto p-6 space-y-4">

<div
v-for="(msg,index) in messages"
:key="index"
class="flex"
:class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
>

<div
class="max-w-[70%] px-4 py-3 rounded-lg text-sm"
:class="msg.role === 'user'
? 'bg-purple-600 text-white'
: 'bg-[#0f1424] text-gray-200'"
>

{{ msg.text }}

</div>

</div>

</div>

<!-- INPUT -->

<div class="border-t border-white/10 p-4 flex gap-3">

<input
v-model="input"
@keyup.enter="sendMessage"
placeholder="Ask Pulsara AI anything..."
class="flex-1 bg-[#0f1424] border border-white/10 rounded-lg px-4 py-2 text-sm outline-none"
/>

<button
@click="sendMessage"
class="bg-purple-600 hover:bg-purple-700 px-6 py-2 rounded-lg text-sm"
>
Send
</button>

</div>

</div>

</div>

</PulsaraLayout>

</template>