<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ permission: Object });
</script>
<template><Head title="Edit Permission" /><AdminLayout><div class="card"><h2 class="text-[18px] font-bold text-white">Edit Permission</h2></div></AdminLayout></template>
