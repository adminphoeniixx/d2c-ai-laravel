<script setup>
defineProps({ percentage: Number })
</script>

<template>
  <div class="bg-[#111827] rounded-2xl border border-white/5 p-8 flex flex-col items-center justify-center">
    <div class="text-lg mb-6">Profit Margin</div>
    <div class="text-4xl font-bold text-purple-400">{{ percentage }}%</div>
  </div>
</template>