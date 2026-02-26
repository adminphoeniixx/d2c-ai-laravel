<script setup>
defineProps({
  title: String,
  value: Number,
  prefix: String,
  change: String,
  positive: Boolean
})
</script>

<template>
  <div class="bg-[#111827] rounded-2xl p-6 border border-white/5 hover:-translate-y-1 transition">
    <div class="text-xs text-gray-400">{{ title }}</div>
    <div class="text-2xl font-bold mt-3">{{ prefix }}{{ value.toLocaleString('en-IN') }}</div>
    <div class="text-sm mt-3" :class="positive ? 'text-green-400' : 'text-red-400'">
      {{ change }}
    </div>
  </div>
</template>