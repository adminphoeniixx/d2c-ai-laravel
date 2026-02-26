<script setup>
import { usePage } from '@inertiajs/vue3'
</script>

<template>
    <header class="bg-white shadow-sm px-8 py-4 flex justify-between items-center">

        <div class="text-lg font-semibold">
            Dashboard
        </div>

        <div class="text-sm text-gray-600">
            {{ $page.props.auth.user.name }}
        </div>

    </header>
</template>