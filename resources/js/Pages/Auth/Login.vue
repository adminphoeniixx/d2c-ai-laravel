<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String, default: '' },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.transform(data => ({ ...data, remember: form.remember ? 'on' : '' }))
        .post(route('login'), {
            onFinish: () => form.reset('password'),
        });
}
</script>

<template>
    <Head title="Sign in" />
    <GuestLayout>
        <section class="min-h-[calc(100vh-160px)] flex items-center justify-center py-16 px-6">
            <div class="w-full max-w-md">
                <div class="mb-8 text-center">
                    <h1 class="text-[28px] font-extrabold text-white">Welcome back</h1>
                    <p class="mt-2 text-[13px] text-ink-2">Sign in to your workspace</p>
                </div>

                <div v-if="status" class="card mb-4 border-emerald/30 text-[12.5px] text-emerald">{{ status }}</div>

                <form class="card space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="pulsara-label">Email</label>
                        <input v-model="form.email" type="email" autofocus required class="pulsara-input" />
                        <div v-if="form.errors.email" class="mt-1 text-[11px] text-rose">{{ form.errors.email }}</div>
                    </div>

                    <div>
                        <label class="pulsara-label">Password</label>
                        <input v-model="form.password" type="password" required class="pulsara-input" />
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

                    <p class="text-center text-[12px] text-ink-3">
                        New to Pulsara?
                        <Link :href="route('company.register')" class="text-brand-300 hover:underline">Create a workspace</Link>
                    </p>
                </form>
            </div>
        </section>
    </GuestLayout>
</template>
