<script setup>
import { nextTick, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldCheck, KeyRound } from 'lucide-vue-next';

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const recoveryCodeInput = ref(null);
const codeInput = ref(null);

const toggleRecovery = async () => {
    recovery.value ^= true;

    await nextTick();

    if (recovery.value) {
        recoveryCodeInput.value?.focus();
        form.code = '';
    } else {
        codeInput.value?.focus();
        form.recovery_code = '';
    }
};

const submit = () => {
    form.post(route('two-factor.login'));
};
</script>

<template>
<Head title="Two-Factor Confirmation" />

<div class="min-h-screen flex items-center justify-center bg-[#0a0a0f] px-4">
    <div class="w-full max-w-sm">

        <!-- Brand -->
        <div class="text-center mb-8">
            <div class="w-10 h-10 bg-slate-700/80 rounded-xl flex items-center justify-center mx-auto mb-3 border border-slate-600/50">
                <ShieldCheck class="w-5 h-5 text-slate-300" />
            </div>
            <h1 class="text-[20px] font-bold text-white">Two-Factor Verification</h1>
            <p class="text-[12px] text-slate-500 mt-1 max-w-xs mx-auto">
                <template v-if="!recovery">
                    Enter the 6-digit code from your authenticator app.
                </template>
                <template v-else>
                    Enter one of your emergency recovery codes.
                </template>
            </p>
        </div>

        <form @submit.prevent="submit" class="bg-[#12121a] border border-white/[0.06] rounded-2xl p-6 space-y-4">
            <div v-if="!recovery">
                <label class="block text-[11px] text-slate-400 uppercase font-medium tracking-wide mb-1.5">Authentication Code</label>
                <div class="relative">
                    <KeyRound class="absolute left-3.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500" />
                    <input ref="codeInput" v-model="form.code" type="text" inputmode="numeric" maxlength="6" autofocus autocomplete="one-time-code"
                        class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl pl-9 pr-3.5 py-2.5 text-[16px] font-mono tracking-widest text-white placeholder-slate-600 focus:outline-none focus:border-slate-500 transition"
                        placeholder="000000" />
                </div>
                <p v-if="form.errors.code" class="text-rose-400 text-[11px] mt-1">{{ form.errors.code }}</p>
            </div>

            <div v-else>
                <label class="block text-[11px] text-slate-400 uppercase font-medium tracking-wide mb-1.5">Recovery Code</label>
                <div class="relative">
                    <KeyRound class="absolute left-3.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500" />
                    <input ref="recoveryCodeInput" v-model="form.recovery_code" type="text" autocomplete="one-time-code"
                        class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl pl-9 pr-3.5 py-2.5 text-[13px] font-mono text-white placeholder-slate-600 focus:outline-none focus:border-slate-500 transition" />
                </div>
                <p v-if="form.errors.recovery_code" class="text-rose-400 text-[11px] mt-1">{{ form.errors.recovery_code }}</p>
            </div>

            <button type="submit" :disabled="form.processing"
                class="w-full bg-slate-700 hover:bg-slate-600 text-white font-semibold text-[13px] py-2.5 rounded-xl transition disabled:opacity-50 cursor-pointer">
                {{ form.processing ? 'Verifying…' : 'Log in' }}
            </button>

            <button type="button" @click.prevent="toggleRecovery"
                class="w-full text-center text-[11px] text-slate-500 hover:text-slate-400 transition cursor-pointer">
                <template v-if="!recovery">Use a recovery code instead</template>
                <template v-else>Use an authentication code instead</template>
            </button>
        </form>
    </div>
</div>
</template>
