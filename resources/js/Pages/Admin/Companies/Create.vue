<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Building2 } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const form = useForm({ name: '', slug: '', email: '', plan: 'free', country: 'IN', currency: 'INR' });
function submit() { /* form.post(route('admin.companies.store')); */ }
</script>
<template>
<Head title="New Company" />
<AdminLayout>
  <div class="max-w-lg">
    <div class="card">
      <h2 class="text-[18px] font-bold text-white mb-4">Create Company</h2>
      <form @submit.prevent="submit" class="space-y-3">
        <div><label class="pulsara-label">Company Name</label><input v-model="form.name" class="pulsara-input" placeholder="Acme Apparel" /></div>
        <div><label class="pulsara-label">Slug</label><input v-model="form.slug" class="pulsara-input" placeholder="acme" /></div>
        <div><label class="pulsara-label">Email</label><input v-model="form.email" type="email" class="pulsara-input" /></div>
        <div><label class="pulsara-label">Plan</label>
          <select v-model="form.plan" class="pulsara-input"><option value="free">Free</option><option value="pro">Pro</option><option value="enterprise">Enterprise</option></select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="pulsara-label">Country</label><input v-model="form.country" class="pulsara-input" /></div>
          <div><label class="pulsara-label">Currency</label><input v-model="form.currency" class="pulsara-input" /></div>
        </div>
        <button type="submit" class="btn btn-primary w-full py-2.5">Create Company</button>
      </form>
    </div>
  </div>
</AdminLayout>
</template>
