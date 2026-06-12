<script setup>
import { Head, useForm } from '@inertiajs/vue3'

defineProps({ status: String })

const form = useForm({
    email:    '',
    password: '',
    remember: false,
})

function submit() {
    form.post(route('admin.login.store'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
<Head title="Admin Sign in" />

<div class="min-h-screen flex items-center justify-center bg-[#0a0a0f] px-4">
    <div class="w-full max-w-sm">

        <!-- Brand -->
        <div class="text-center mb-8">
            <div class="w-10 h-10 bg-slate-700/80 rounded-xl flex items-center justify-center mx-auto mb-3 border border-slate-600/50">
                <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <h1 class="text-[20px] font-bold text-white">Admin Portal</h1>
            <p class="text-[12px] text-slate-500 mt-1">heyd2c internal access only</p>
        </div>

        <div v-if="status" class="mb-4 text-[12px] text-emerald-400 text-center bg-emerald-400/10 border border-emerald-400/20 rounded-xl px-4 py-3">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="bg-[#12121a] border border-white/[0.06] rounded-2xl p-6 space-y-4">
            <div>
                <label class="block text-[11px] text-slate-400 uppercase font-medium tracking-wide mb-1.5">Email</label>
                <input v-model="form.email" type="email" required autofocus
                    class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-[13px] text-white placeholder-slate-600 focus:outline-none focus:border-slate-500 transition" />
                <p v-if="form.errors.email" class="text-rose-400 text-[11px] mt-1">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-[11px] text-slate-400 uppercase font-medium tracking-wide mb-1.5">Password</label>
                <input v-model="form.password" type="password" required
                    class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl px-3.5 py-2.5 text-[13px] text-white placeholder-slate-600 focus:outline-none focus:border-slate-500 transition" />
                <p v-if="form.errors.password" class="text-rose-400 text-[11px] mt-1">{{ form.errors.password }}</p>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-[12px] text-slate-400 cursor-pointer select-none">
                    <input v-model="form.remember" type="checkbox" class="rounded accent-slate-500" />
                    Remember me
                </label>
            </div>

            <button type="submit" :disabled="form.processing"
                class="w-full bg-slate-700 hover:bg-slate-600 text-white font-semibold text-[13px] py-2.5 rounded-xl transition disabled:opacity-50 cursor-pointer">
                {{ form.processing ? 'Signing in…' : 'Sign in →' }}
            </button>
        </form>

        <!-- Security notice -->
        <p class="text-center text-[11px] text-slate-600 mt-5">
            This portal is restricted to heyd2c staff.<br>
            <a href="/login" class="text-slate-500 hover:text-slate-400 transition">Go to company login</a>
        </p>
    </div>
</div>
</template>
