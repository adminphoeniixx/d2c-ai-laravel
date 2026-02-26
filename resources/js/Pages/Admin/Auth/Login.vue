<script setup>
import { useForm } from '@inertiajs/vue3'

const form = useForm({
    email: '',
    password: ''
})

const submit = () => {
    form.post(route('admin.login.store'))
}
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-900">
        <form @submit.prevent="submit" class="bg-white p-8 rounded shadow w-96">
            <h2 class="text-xl font-bold mb-6">Admin Login</h2>

            <input v-model="form.email" type="email" placeholder="Email"
                class="w-full mb-3 p-2 border rounded" />

            <input v-model="form.password" type="password" placeholder="Password"
                class="w-full mb-3 p-2 border rounded" />

            <button class="w-full bg-black text-white p-2 rounded">
                Login
            </button>
        </form>
    </div>
</template>