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
        <div><label class="heyd2c-label">Company Name</label><input v-model="form.name" class="heyd2c-input" placeholder="Acme Apparel" /></div>
        <div><label class="heyd2c-label">Slug</label><input v-model="form.slug" class="heyd2c-input" placeholder="acme" /></div>
        <div><label class="heyd2c-label">Email</label><input v-model="form.email" type="email" class="heyd2c-input" /></div>
        <div><label class="heyd2c-label">Plan</label>
          <select v-model="form.plan" class="heyd2c-input"><option value="free">Free</option><option value="pro">Pro</option><option value="enterprise">Enterprise</option></select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div><label class="heyd2c-label">Country</label><input v-model="form.country" class="heyd2c-input" /></div>
          <div><label class="heyd2c-label">Currency</label><input v-model="form.currency" class="heyd2c-input" /></div>
        </div>
        <button type="submit" class="btn btn-primary w-full py-2.5">Create Company</button>
      </form>
    </div>
  </div>
</AdminLayout>
</template>
