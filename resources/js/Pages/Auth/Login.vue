<script setup>
import { Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    email: '',
    password: '',
})

const submit = () => {
    form.post('/login')
}
</script>

<template>
<div class="min-h-screen bg-[#0B0F1A] text-white flex items-center justify-center relative overflow-hidden">

    <!-- Background glow -->
    <div class="absolute -top-40 -left-40 w-[500px] h-[500px] bg-purple-600/20 rounded-full blur-[150px]"></div>
    <div class="absolute -bottom-40 -right-40 w-[500px] h-[500px] bg-pink-600/20 rounded-full blur-[150px]"></div>

    <div class="relative w-full max-w-md bg-[#111827] border border-white/5 rounded-2xl p-10">

        <!-- Logo -->
        <div class="flex items-center gap-3 mb-8 justify-center">
            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl flex items-center justify-center">
                ⚡
            </div>
            <div>
                <div class="font-semibold text-lg">Pulsara</div>
                <div class="text-xs text-gray-400">D2C Ops AI</div>
            </div>
        </div>

        <h2 class="text-2xl font-semibold text-center mb-8">
            Welcome Back
        </h2>

        <form @submit.prevent="submit" class="space-y-5">

            <input
                v-model="form.email"
                type="email"
                placeholder="Email"
                class="w-full bg-[#0B0F1A] border border-white/10 p-4 rounded-xl focus:outline-none focus:border-purple-500"
            />

            <input
                v-model="form.password"
                type="password"
                placeholder="Password"
                class="w-full bg-[#0B0F1A] border border-white/10 p-4 rounded-xl focus:outline-none focus:border-purple-500"
            />

            <button
                class="w-full bg-gradient-to-r from-purple-600 to-pink-500 py-4 rounded-xl font-semibold"
            >
                Login
            </button>

        </form>

        <div class="text-center mt-6 text-sm text-gray-400">
            Don’t have an account?
            <Link href="/register" class="text-purple-400 hover:underline">
                Create one
            </Link>
        </div>

    </div>

</div>
</template>