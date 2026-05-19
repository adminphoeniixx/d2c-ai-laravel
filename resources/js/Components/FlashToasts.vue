<script setup>
import { usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { CheckCircle2, XCircle, Info, X } from 'lucide-vue-next';

const toasts = ref([]);
let nextId = 1;

const page = usePage();

function push(type, message) {
    if (!message) return;
    const id = nextId++;
    toasts.value.push({ id, type, message });
    setTimeout(() => { toasts.value = toasts.value.filter(t => t.id !== id); }, 4500);
}

watch(
    () => [page.props.flash?.success, page.props.flash?.error, page.props.flash?.info],
    ([s, e, i]) => {
        push('success', s);
        push('error',   e);
        push('info',    i);
    },
    { immediate: true },
);

function dismiss(id) {
    toasts.value = toasts.value.filter(t => t.id !== id);
}
</script>

<template>
    <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 pointer-events-none">
        <TransitionGroup
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0 translate-x-6"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0 translate-x-6"
        >
            <div
                v-for="t in toasts" :key="t.id"
                class="pointer-events-auto min-w-[280px] max-w-sm rounded-[12px] border bg-surface-2/95 backdrop-blur-sm px-4 py-3 shadow-glow-sm flex items-start gap-3"
                :class="{
                    'border-emerald/40':   t.type === 'success',
                    'border-rose/40':      t.type === 'error',
                    'border-brand-600/40': t.type === 'info',
                }"
            >
                <CheckCircle2 v-if="t.type === 'success'" :size="18" class="text-emerald mt-0.5 flex-shrink-0" />
                <XCircle v-else-if="t.type === 'error'" :size="18" class="text-rose mt-0.5 flex-shrink-0" />
                <Info v-else :size="18" class="text-brand-400 mt-0.5 flex-shrink-0" />
                <p class="flex-1 text-[13px] text-ink">{{ t.message }}</p>
                <button class="text-ink-3 hover:text-ink" @click="dismiss(t.id)"><X :size="15" /></button>
            </div>
        </TransitionGroup>
    </div>
</template>
