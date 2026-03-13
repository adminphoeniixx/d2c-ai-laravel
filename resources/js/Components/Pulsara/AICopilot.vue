<script setup>
import { ref } from 'vue'

const open = ref(false)

const messages = ref([
{role:'assistant', text:'Hi 👋 I am Pulsara AI. Ask anything about your business.'}
])

const input = ref("")

function toggle(){
open.value = !open.value
}

function send(){

if(!input.value.trim()) return

messages.value.push({
role:'user',
text:input.value
})

messages.value.push({
role:'assistant',
text:'Analyzing your business data...'
})

input.value=""
}
</script>

<template>

<!-- FLOATING BUTTON -->

<button
v-if="!open"
@click="toggle"
class="fixed bottom-6 right-6 bg-purple-600 hover:bg-purple-700 text-white px-5 py-3 rounded-full shadow-lg z-50"
>
🤖 AI
</button>

<!-- CHAT PANEL -->

<div
v-if="open"
class="fixed top-0 right-0 h-full w-[420px] bg-[#0f1424] border-l border-white/10 flex flex-col z-40"
>

<!-- HEADER -->

<div class="p-4 border-b border-white/10 flex justify-between">

<div class="font-semibold">
Pulsara AI
</div>

<button @click="toggle">
✕
</button>

</div>

<!-- MESSAGES -->

<div class="flex-1 overflow-y-auto p-4 space-y-3">

<div
v-for="(msg,i) in messages"
:key="i"
class="flex"
:class="msg.role==='user'?'justify-end':'justify-start'"
>

<div
class="max-w-[75%] px-4 py-2 rounded-lg text-sm"
:class="msg.role==='user'
?'bg-purple-600'
:'bg-[#121a30]'"
>

{{ msg.text }}

</div>

</div>

</div>

<!-- INPUT -->

<div class="border-t border-white/10 p-3 flex gap-2">

<input
v-model="input"
@keyup.enter="send"
placeholder="Ask Pulsara AI..."
class="flex-1 bg-[#121a30] border border-white/10 rounded-lg px-3 py-2 text-sm"
/>

<button
@click="send"
class="bg-purple-600 px-4 rounded-lg"
>
Send
</button>

</div>

</div>

</template>