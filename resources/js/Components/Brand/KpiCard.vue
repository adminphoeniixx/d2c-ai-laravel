<script setup>
defineProps({
    title: String,
    value: String,
    change: String,
    positive: Boolean
})
</script>

<template>
    <div class="bg-[#111827] p-6 rounded-2xl border border-white/5 relative overflow-hidden">

        <div class="text-xs text-gray-400 tracking-wide">
            {{ title }}
        </div>

        <div class="text-2xl font-bold mt-3">
            {{ value }}
        </div>

        <div class="mt-3 text-sm"
             :class="positive ? 'text-green-400' : 'text-red-400'">
            {{ change }}
        </div>

        <!-- Glow effect -->
        <div class="absolute -right-10 -top-10 w-32 h-32 bg-purple-600/20 blur-3xl"></div>

    </div>
</template>