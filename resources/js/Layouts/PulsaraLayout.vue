<script setup>
import Sidebar from '@/Components/Pulsara/Sidebar.vue'
</script>

<template>
  <div class="relative min-h-screen bg-[#0B0F1A] text-white flex overflow-hidden">

    <!-- Animated background blobs -->
    <div class="absolute -top-40 -left-40 w-[500px] h-[500px] bg-purple-600/20 rounded-full blur-[150px] animate-blob"></div>
    <div class="absolute -bottom-40 -right-40 w-[500px] h-[500px] bg-pink-600/20 rounded-full blur-[150px] animate-blob animation-delay-4000"></div>

    <Sidebar />

    <div class="relative flex-1 bg-gradient-to-br from-[#0B0F1A] via-[#0F1424] to-[#0B0F1A]">
      <slot />
    </div>
  </div>
</template>

<style>
@keyframes blob {
  0%,100% { transform: translate(0,0) scale(1); }
  50% { transform: translate(30px,-50px) scale(1.1); }
}
.animate-blob { animation: blob 15s infinite ease-in-out; }
.animation-delay-4000 { animation-delay: 4s; }
</style>