<script setup>
import Sidebar from '@/Components/Brand/Sidebar.vue'
</script>

<template>
    <div class="min-h-screen bg-[#0b0f1a] text-white flex">

        <Sidebar />

        <div class="flex-1 p-8 bg-gradient-to-br from-[#0b0f1a] via-[#0f1424] to-[#0b0f1a]">

            <slot />

        </div>

    </div>
</template>