<script setup>
defineProps({
  label: String,
  value: String,
  change: String,
  positive: Boolean
})
</script>

<template>
  <div class="bg-[#111827] border border-white/5 rounded-xl p-5 hover:-translate-y-1 transition">
    <div class="text-xs text-gray-400">{{ label }}</div>
    <div class="text-lg font-semibold mt-2">{{ value }}</div>
    <div class="text-xs mt-2" :class="positive ? 'text-green-400' : 'text-red-400'">
      {{ change }}
    </div>
  </div>
</template>