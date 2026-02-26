<script setup>
</script>

<template>
    <div class="min-h-screen bg-white text-black">

        <!-- Header -->
        <header class="flex justify-between items-center px-8 py-4 border-b">
            <div class="text-xl font-bold">
                Pulsara
            </div>

            <div class="space-x-6">
                <a href="/" class="hover:underline">Home</a>
                <a href="/login" class="hover:underline">Login</a>
                <a href="/register" class="bg-black text-white px-4 py-2 rounded">
                    Get Started
                </a>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex items-center justify-center py-20">
            <slot />
        </main>

    </div>
</template>