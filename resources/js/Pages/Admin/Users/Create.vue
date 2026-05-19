<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ user: Object, roles: Array });
</script>
<template><Head title="Create User" /><AdminLayout><div class="card"><h2 class="text-[18px] font-bold text-white">Create User</h2><p class="text-ink-2 mt-2">Form coming soon.</p></div></AdminLayout></template>
