<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const hasGst = ref(false);

const form = useForm({
    company_name: '',
    slug: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    gstin: '',
    business_category: 'apparel',
    country: 'IN',
    currency: 'INR',
    timezone: 'Asia/Kolkata',
    terms: false,
});

const categories = [
    { value: 'apparel', label: 'Apparel & Fashion' },
    { value: 'footwear', label: 'Footwear' },
    { value: 'electronics', label: 'Electronics' },
    { value: 'beauty', label: 'Beauty & Personal Care' },
    { value: 'food', label: 'Food & Beverages' },
    { value: 'luxury', label: 'Luxury Goods' },
    { value: 'other', label: 'Other' },
];

watch(() => form.company_name, (v) => {
    form.slug = v.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60);
});

function submit() {
    form.post(route('company.register.store'));
}
</script>

<template>
    <Head title="Create your workspace" />
    <GuestLayout>
        <section class="min-h-[calc(100vh-160px)] flex items-center justify-center py-16 px-6">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <h1 class="text-[28px] font-extrabold text-white">Create your workspace</h1>
                    <p class="mt-2 text-[13px] text-ink-2">14 days free · no credit card · full features</p>
                </div>

                <form class="card space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="pulsara-label">Company name</label>
                        <input v-model="form.company_name" class="pulsara-input" placeholder="Acme Apparel" autofocus />
                        <div v-if="form.errors.company_name" class="mt-1 text-[11px] text-rose">{{ form.errors.company_name }}</div>
                    </div>

                    <div>
                        <label class="pulsara-label">Workspace URL</label>
                        <div class="flex items-center gap-2 rounded-[10px] border border-frost-2 bg-bg-3 px-3.5 py-2.5">
                            <span class="text-[12px] text-ink-3 font-mono">pulsara.app/app/</span>
                            <input v-model="form.slug" class="flex-1 bg-transparent border-0 p-0 text-[13px] outline-none text-ink" placeholder="acme" />
                        </div>
                        <div v-if="form.errors.slug" class="mt-1 text-[11px] text-rose">{{ form.errors.slug }}</div>
                    </div>

                    <div class="pt-2 border-t border-frost-1" />

                    <div>
                        <label class="pulsara-label">Business Category</label>
                        <select v-model="form.business_category" class="pulsara-input">
                            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                        <input v-model="hasGst" type="checkbox" class="accent-brand-600" />
                        I have a GSTIN (enables GST breakup on orders)
                    </label>

                    <div v-if="hasGst">
                        <label class="pulsara-label">GSTIN</label>
                        <input v-model="form.gstin" class="pulsara-input font-mono" placeholder="27AABCU9603R1ZM" maxlength="15" />
                        <div v-if="form.errors.gstin" class="mt-1 text-[11px] text-rose">{{ form.errors.gstin }}</div>
                        <p class="mt-1 text-[10px] text-ink-3">State auto-detected from first 2 digits · You can add this later in Settings</p>
                    </div>

                    <div class="pt-2 border-t border-frost-1" />

                    <div>
                        <label class="pulsara-label">Your name</label>
                        <input v-model="form.name" class="pulsara-input" placeholder="Priya Singh" />
                        <div v-if="form.errors.name" class="mt-1 text-[11px] text-rose">{{ form.errors.name }}</div>
                    </div>

                    <div>
                        <label class="pulsara-label">Email</label>
                        <input v-model="form.email" type="email" class="pulsara-input" placeholder="you@company.com" />
                        <div v-if="form.errors.email" class="mt-1 text-[11px] text-rose">{{ form.errors.email }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="pulsara-label">Password</label>
                            <input v-model="form.password" type="password" class="pulsara-input" />
                        </div>
                        <div>
                            <label class="pulsara-label">Confirm</label>
                            <input v-model="form.password_confirmation" type="password" class="pulsara-input" />
                        </div>
                    </div>
                    <div v-if="form.errors.password" class="text-[11px] text-rose">{{ form.errors.password }}</div>

                    <label class="flex items-start gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                        <input v-model="form.terms" type="checkbox" class="mt-0.5 accent-brand-600" />
                        <span>I agree to the <Link href="#" class="text-brand-300 hover:underline">Terms</Link> and <Link href="#" class="text-brand-300 hover:underline">Privacy Policy</Link>.</span>
                    </label>
                    <div v-if="form.errors.terms" class="-mt-2 text-[11px] text-rose">{{ form.errors.terms }}</div>

                    <button type="submit" class="btn btn-primary w-full py-3 text-[14px]" :disabled="form.processing">
                        {{ form.processing ? 'Creating…' : 'Create workspace →' }}
                    </button>

                    <p class="text-center text-[12px] text-ink-3">
                        Already have an account?
                        <Link :href="route('login')" class="text-brand-300 hover:underline">Sign in</Link>
                    </p>
                </form>
            </div>
        </section>
    </GuestLayout>
</template>
