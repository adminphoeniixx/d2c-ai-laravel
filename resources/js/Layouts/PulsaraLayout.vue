<script setup>
import Sidebar from '@/Components/Pulsara/Sidebar.vue'
import AICopilot from '@/Components/Pulsara/AICopilot.vue'
</script>

<template>
<div class="relative min-h-screen bg-[#0B0F1A] text-white flex overflow-hidden">

<!-- Animated background blobs -->
<div
class="pointer-events-none absolute -top-40 -left-40 w-[500px] h-[500px] bg-purple-600/20 rounded-full blur-[150px] animate-blob">
</div>

<div
class="pointer-events-none absolute -bottom-40 -right-40 w-[500px] h-[500px] bg-pink-600/20 rounded-full blur-[150px] animate-blob animation-delay-4000">
</div>

<!-- Sidebar -->
<div class="relative z-20">
<Sidebar />
</div>

<!-- Page Content -->
<div class="relative flex-1 z-10 bg-gradient-to-br from-[#0B0F1A] via-[#0F1424] to-[#0B0F1A] overflow-y-auto">

<slot />

</div>

<!-- AI COPILOT (FLOATING PANEL) -->

<AICopilot />

</div>
</template>

<style>

@keyframes blob {
0%,100% { transform: translate(0,0) scale(1); }
50% { transform: translate(30px,-50px) scale(1.1); }
}

.animate-blob {
animation: blob 15s infinite ease-in-out;
}

.animation-delay-4000 {
animation-delay: 4s;
}

</style>