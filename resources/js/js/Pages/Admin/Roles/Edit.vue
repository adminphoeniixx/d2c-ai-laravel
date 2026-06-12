<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ role: Object, permissions: Array });
</script>
<template><Head title="Edit Role" /><AdminLayout><div class="card"><h2 class="text-[18px] font-bold text-white">Edit Role</h2><p class="text-ink-2 mt-2">Form coming soon.</p></div></AdminLayout></template>
