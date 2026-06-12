<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String, default: '' },
});

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.transform(data => ({ ...data, remember: form.remember ? 'on' : '' }))
        .post(route('login'), { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Admin Sign in" />
    <GuestLayout :show_nav_login="false">
        <section class="min-h-[calc(100vh-160px)] flex items-center justify-center py-16 px-6">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <div class="w-10 h-10 bg-slate-700 rounded-xl flex items-center justify-center mx-auto mb-3">
                        <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <h1 class="text-[24px] font-extrabold text-white">Admin Portal</h1>
                    <p class="mt-1 text-[13px] text-ink-3">heyd2c internal access only</p>
                </div>

                <div v-if="status" class="card mb-4 border-emerald/30 text-[12.5px] text-emerald">{{ status }}</div>

                <form class="card space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="heyd2c-label">Email</label>
                        <input v-model="form.email" type="email" autofocus required class="heyd2c-input" />
                        <div v-if="form.errors.email" class="mt-1 text-[11px] text-rose">{{ form.errors.email }}</div>
                    </div>
                    <div>
                        <label class="heyd2c-label">Password</label>
                        <input v-model="form.password" type="password" required class="heyd2c-input" />
                        <div v-if="form.errors.password" class="mt-1 text-[11px] text-rose">{{ form.errors.password }}</div>
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 text-[12.5px] text-ink-2 cursor-pointer select-none">
                            <input v-model="form.remember" type="checkbox" class="accent-brand-600" />
                            Remember me
                        </label>
                        <Link v-if="canResetPassword" :href="route('password.request')" class="text-[12px] text-brand-300 hover:underline">Forgot?</Link>
                    </div>
                    <button type="submit" class="btn btn-primary w-full py-3 text-[14px]" :disabled="form.processing">
                        {{ form.processing ? 'Signing in…' : 'Sign in →' }}
                    </button>
                </form>

                <p class="text-center text-[11px] text-ink-3 mt-4">
                    Not an admin?
                    <Link :href="route('otp.login')" class="text-brand-300 hover:underline">Go to company login →</Link>
                </p>
            </div>
        </section>
    </GuestLayout>
</template>
