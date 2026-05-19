<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
const props = defineProps({ company: { type: Object, default: () => ({}) } });
const form = useForm({ name: props.company.name || '', email: props.company.email || '', plan: props.company.plan || 'free', country: props.company.country || 'IN', currency: props.company.currency || 'INR', timezone: props.company.timezone || 'UTC' });
</script>
<template>
<Head title="Edit Company" />
<AdminLayout>
  <div class="max-w-lg card">
    <h2 class="text-[18px] font-bold text-white mb-4">Edit {{ company.name }}</h2>
    <form class="space-y-3">
      <div><label class="pulsara-label">Name</label><input v-model="form.name" class="pulsara-input" /></div>
      <div><label class="pulsara-label">Email</label><input v-model="form.email" class="pulsara-input" /></div>
      <div><label class="pulsara-label">Plan</label><select v-model="form.plan" class="pulsara-input"><option value="free">Free</option><option value="pro">Pro</option><option value="enterprise">Enterprise</option></select></div>
      <div class="grid grid-cols-3 gap-3">
        <div><label class="pulsara-label">Country</label><input v-model="form.country" class="pulsara-input" /></div>
        <div><label class="pulsara-label">Currency</label><input v-model="form.currency" class="pulsara-input" /></div>
        <div><label class="pulsara-label">Timezone</label><input v-model="form.timezone" class="pulsara-input" /></div>
      </div>
      <button type="submit" class="btn btn-primary w-full py-2.5">Update</button>
    </form>
  </div>
</AdminLayout>
</template>
