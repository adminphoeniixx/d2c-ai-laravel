<script setup>
import { Zap } from 'lucide-vue-next';

defineProps({
    size: { type: Number, default: 32 },
});
</script>

<template>
    <div
        class="flex items-center justify-center rounded-[10px] bg-gradient-to-br from-brand-600 to-fuchsia shadow-glow-sm"
        :style="{ width: size + 'px', height: size + 'px' }"
    >
        <Zap :size="size * 0.55" class="text-white" stroke-width="2.5" fill="currentColor" />
    </div>
</template>
