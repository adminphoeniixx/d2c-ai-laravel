<script setup>
import { ref } from 'vue'
const emit = defineEmits(['change'])
const active = ref('30D')
const options = ['7D','30D','YTD']

const setActive = (v) => {
  active.value = v
  emit('change', v)
}
</script>

<template>
  <div class="flex gap-3">
    <button v-for="o in options"
      :key="o"
      @click="setActive(o)"
      :class="active === o
        ? 'bg-gradient-to-r from-purple-600 to-pink-500 text-white'
        : 'bg-[#111827] text-gray-400 hover:bg-white/5'"
      class="px-4 py-2 rounded-xl text-sm transition">
      {{ o }}
    </button>
  </div>
</template>