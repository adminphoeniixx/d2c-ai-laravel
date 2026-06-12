<script setup>
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
</script>

<template>
<Head title="Screencast" />
<GuestLayout>
    <section class="max-w-4xl mx-auto py-16 px-6">
        <h1 class="text-[28px] font-extrabold text-white mb-6 text-center">heyd2c Demo</h1>
        <div class="rounded-2xl overflow-hidden border border-frost-1 bg-black">
            <video
                controls
                autoplay
                playsinline
                class="w-full"
                src="/videos/screencast.mp4"
            >
                Your browser does not support the video tag.
            </video>
        </div>
    </section>
</GuestLayout>
</template>
