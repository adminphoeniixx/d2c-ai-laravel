<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Lock, ShieldCheck } from 'lucide-vue-next';

const password = ref('');
const error = ref('');
const processing = ref(false);
const passwordInput = ref(null);

const submit = async () => {
    processing.value = true;
    error.value = '';

    try {
        await axios.post(route('password.confirm'), { password: password.value });

        // Where to go back to (set by the page that triggered this confirmation)
        const returnTo = sessionStorage.getItem('2fa_return_to');
        sessionStorage.removeItem('2fa_return_to');

        if (returnTo) {
            sessionStorage.setItem('2fa_auto_enable', '1');
            window.location.href = returnTo;
        } else {
            window.location.href = '/';
        }
    } catch (e) {
        error.value = e.response?.data?.errors?.password?.[0] || 'The password is incorrect.';
        password.value = '';
        passwordInput.value?.focus();
    } finally {
        processing.value = false;
    }
};
</script>

<template>
<Head title="Confirm Password" />

<div class="min-h-screen flex items-center justify-center bg-[#0a0a0f] px-4">
    <div class="w-full max-w-sm">

        <!-- Brand -->
        <div class="text-center mb-8">
            <div class="w-10 h-10 bg-slate-700/80 rounded-xl flex items-center justify-center mx-auto mb-3 border border-slate-600/50">
                <ShieldCheck class="w-5 h-5 text-slate-300" />
            </div>
            <h1 class="text-[20px] font-bold text-white">Confirm Password</h1>
            <p class="text-[12px] text-slate-500 mt-1">This is a secure area. Please confirm your password to continue.</p>
        </div>

        <form @submit.prevent="submit" class="bg-[#12121a] border border-white/[0.06] rounded-2xl p-6 space-y-4">
            <div>
                <label class="block text-[11px] text-slate-400 uppercase font-medium tracking-wide mb-1.5">Password</label>
                <div class="relative">
                    <Lock class="absolute left-3.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500" />
                    <input ref="passwordInput" v-model="password" type="password" required autofocus autocomplete="current-password"
                        class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl pl-9 pr-3.5 py-2.5 text-[13px] text-white placeholder-slate-600 focus:outline-none focus:border-slate-500 transition" />
                </div>
                <p v-if="error" class="text-rose-400 text-[11px] mt-1">{{ error }}</p>
            </div>

            <button type="submit" :disabled="processing"
                class="w-full bg-slate-700 hover:bg-slate-600 text-white font-semibold text-[13px] py-2.5 rounded-xl transition disabled:opacity-50 cursor-pointer">
                {{ processing ? 'Confirming…' : 'Confirm' }}
            </button>
        </form>
    </div>
</div>
</template>
