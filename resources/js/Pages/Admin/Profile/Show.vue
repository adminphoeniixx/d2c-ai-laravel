<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import axios from 'axios'
import { User, Lock, Smartphone, Eye, EyeOff, CheckCircle, Copy, ShieldCheck } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const page = usePage()
const user = computed(() => page.props.auth.user)

// Profile form
const profileForm = useForm({
    name:  user.value?.name  || '',
    email: user.value?.email || '',
})

function saveProfile() {
    profileForm.put(route('user-profile-information.update'), { preserveScroll: true })
}

// Password form
const showCurrentPw = ref(false)
const showNewPw     = ref(false)
const passwordForm  = useForm({ current_password: '', password: '', password_confirmation: '' })

function savePassword() {
    passwordForm.put(route('user-password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    })
}

// 2FA
const twoFactorEnabled     = computed(() => !!user.value?.two_factor_confirmed_at)
const showingQrCode        = ref(false)
const showingRecoveryCodes = ref(false)
const qrCode                = ref(null)
const recoveryCodes         = ref([])
const confirmationCode      = ref('')
const twoFaForm             = useForm({ code: '' })

// Inline password confirmation modal (avoids full-page redirects entirely)
const showPwConfirm = ref(false)
const pwConfirmValue = ref('')
const pwConfirmError = ref('')
const pwConfirmBusy  = ref(false)
const pendingAfterConfirm = ref(null) // function to run after password confirmed

function requirePasswordThen(action) {
    pendingAfterConfirm.value = action
    pwConfirmValue.value = ''
    pwConfirmError.value = ''
    showPwConfirm.value = true
}

async function submitPasswordConfirm() {
    pwConfirmBusy.value = true
    pwConfirmError.value = ''
    try {
        await axios.post(route('password.confirm'), { password: pwConfirmValue.value })
        showPwConfirm.value = false
        pwConfirmValue.value = ''
        const action = pendingAfterConfirm.value
        pendingAfterConfirm.value = null
        if (action) await action()
    } catch (e) {
        pwConfirmError.value = e.response?.data?.errors?.password?.[0] || 'Incorrect password.'
    } finally {
        pwConfirmBusy.value = false
    }
}

async function doEnableTwoFactor() {
    await router.post(route('two-factor.enable'), {}, {
        preserveScroll: true,
        onSuccess: async () => {
            const res = await fetch(route('two-factor.qr-code'))
            const data = await res.json()
            qrCode.value = data.svg
            showingQrCode.value = true
        }
    })
}

function enableTwoFactor() {
    requirePasswordThen(doEnableTwoFactor)
}

async function confirmTwoFactor() {
    twoFaForm.code = confirmationCode.value
    twoFaForm.post(route('two-factor.confirm'), {
        preserveScroll: true,
        onSuccess: () => { showingQrCode.value = false; confirmationCode.value = '' }
    })
}

async function showRecoveryCodes() {
    const res = await fetch(route('two-factor.recovery-codes'))
    recoveryCodes.value = await res.json()
    showingRecoveryCodes.value = true
}

function disableTwoFactor() {
    if (!confirm('Disable two-factor authentication? This will make your admin account less secure.')) return
    requirePasswordThen(() => router.delete(route('two-factor.disable'), { preserveScroll: true }))
}

function copyCode(code) {
    navigator.clipboard.writeText(code)
}
</script>


<template>
<Head title="Admin Profile" />
<AdminLayout>
    <div class="max-w-2xl space-y-5">
        <h1 class="text-[20px] font-bold text-white">Admin Profile & Security</h1>

        <!-- Profile Info -->
        <div class="card space-y-4">
            <div class="flex items-center gap-2 mb-1">
                <User :size="15" class="text-brand-400" />
                <h3 class="text-[14px] font-semibold text-white">Personal Information</h3>
            </div>
            <div>
                <label class="heyd2c-label">Full Name</label>
                <input v-model="profileForm.name" class="heyd2c-input" />
                <p v-if="profileForm.errors.name" class="text-rose-400 text-[11px] mt-1">{{ profileForm.errors.name }}</p>
            </div>
            <div>
                <label class="heyd2c-label">Email Address</label>
                <input v-model="profileForm.email" type="email" class="heyd2c-input" />
                <p v-if="profileForm.errors.email" class="text-rose-400 text-[11px] mt-1">{{ profileForm.errors.email }}</p>
            </div>
            <div class="flex justify-end">
                <button @click="saveProfile" :disabled="profileForm.processing"
                    class="btn btn-primary btn-sm cursor-pointer disabled:opacity-50">
                    {{ profileForm.processing ? 'Saving…' : 'Save Profile' }}
                </button>
            </div>
        </div>

        <!-- Password -->
        <div class="card space-y-4">
            <div class="flex items-center gap-2 mb-1">
                <Lock :size="15" class="text-brand-400" />
                <h3 class="text-[14px] font-semibold text-white">Change Password</h3>
            </div>
            <div>
                <label class="heyd2c-label">Current Password</label>
                <div class="relative">
                    <input v-model="passwordForm.current_password" :type="showCurrentPw ? 'text' : 'password'" class="heyd2c-input pr-10" />
                    <button type="button" @click="showCurrentPw = !showCurrentPw" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-3 cursor-pointer">
                        <EyeOff v-if="showCurrentPw" :size="14" /><Eye v-else :size="14" />
                    </button>
                </div>
                <p v-if="passwordForm.errors.current_password" class="text-rose-400 text-[11px] mt-1">{{ passwordForm.errors.current_password }}</p>
            </div>
            <div>
                <label class="heyd2c-label">New Password</label>
                <div class="relative">
                    <input v-model="passwordForm.password" :type="showNewPw ? 'text' : 'password'" class="heyd2c-input pr-10" />
                    <button type="button" @click="showNewPw = !showNewPw" class="absolute right-3 top-1/2 -translate-y-1/2 text-ink-3 cursor-pointer">
                        <EyeOff v-if="showNewPw" :size="14" /><Eye v-else :size="14" />
                    </button>
                </div>
                <p v-if="passwordForm.errors.password" class="text-rose-400 text-[11px] mt-1">{{ passwordForm.errors.password }}</p>
            </div>
            <div>
                <label class="heyd2c-label">Confirm New Password</label>
                <input v-model="passwordForm.password_confirmation" type="password" class="heyd2c-input" />
            </div>
            <div class="flex justify-end">
                <button @click="savePassword" :disabled="passwordForm.processing"
                    class="btn btn-primary btn-sm cursor-pointer disabled:opacity-50">
                    {{ passwordForm.processing ? 'Updating…' : 'Update Password' }}
                </button>
            </div>
        </div>

        <!-- 2FA -->
        <div class="card space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Smartphone :size="15" class="text-brand-400" />
                    <h3 class="text-[14px] font-semibold text-white">Two-Factor Authentication</h3>
                </div>
                <span :class="twoFactorEnabled ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-500/15 text-slate-400'"
                    class="text-[10px] px-2 py-0.5 rounded-full font-medium">
                    {{ twoFactorEnabled ? 'Enabled' : 'Disabled' }}
                </span>
            </div>

            <p class="text-[12px] text-ink-3">
                Protect your admin account with Google Authenticator or any TOTP app.
                When enabled, you'll need a 6-digit code on every admin login.
            </p>

            <!-- Not enabled -->
            <div v-if="!twoFactorEnabled && !showingQrCode">
                <button @click="enableTwoFactor" class="btn btn-primary btn-sm cursor-pointer">
                    Enable Two-Factor Auth
                </button>
            </div>

            <!-- QR Code setup -->
            <div v-if="showingQrCode" class="space-y-4">
                <div class="bg-white rounded-xl p-4 inline-block" v-html="qrCode"></div>
                <p class="text-[12px] text-ink-3">
                    Scan this QR code with <strong class="text-white">Google Authenticator</strong> (or any TOTP app), then enter the 6-digit code below.
                </p>
                <div>
                    <label class="heyd2c-label">Confirmation Code</label>
                    <input v-model="confirmationCode" type="text" inputmode="numeric" maxlength="6"
                        class="heyd2c-input font-mono text-[18px] tracking-widest w-40" placeholder="000000" />
                    <p v-if="twoFaForm.errors.code" class="text-rose-400 text-[11px] mt-1">{{ twoFaForm.errors.code }}</p>
                </div>
                <div class="flex gap-2">
                    <button @click="confirmTwoFactor" class="btn btn-primary btn-sm cursor-pointer">Confirm & Enable</button>
                    <button @click="showingQrCode = false" class="btn btn-ghost btn-sm cursor-pointer">Cancel</button>
                </div>
            </div>

            <!-- Enabled state -->
            <div v-if="twoFactorEnabled" class="space-y-3">
                <div class="flex items-center gap-2 text-[12px] text-emerald-400">
                    <CheckCircle :size="14" />
                    Two-factor authentication is active on your account.
                </div>
                <div class="flex gap-2 flex-wrap">
                    <button @click="showRecoveryCodes" class="btn btn-ghost btn-sm cursor-pointer">
                        View Recovery Codes
                    </button>
                    <button @click="disableTwoFactor" class="btn btn-ghost btn-sm text-rose-400 cursor-pointer">
                        Disable 2FA
                    </button>
                </div>

                <!-- Recovery codes -->
                <div v-if="showingRecoveryCodes" class="bg-frost-1 rounded-xl p-4">
                    <p class="text-[11px] text-ink-3 mb-3">Save these recovery codes in a safe place. Each can be used once if you lose access to your authenticator app.</p>
                    <div class="grid grid-cols-2 gap-1">
                        <div v-for="code in recoveryCodes" :key="code"
                            class="flex items-center justify-between bg-bg-2 rounded-lg px-3 py-1.5">
                            <span class="font-mono text-[12px] text-white">{{ code }}</span>
                            <button @click="copyCode(code)" class="text-ink-3 hover:text-white cursor-pointer ml-2">
                                <Copy :size="11" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inline password confirmation modal -->
    <div v-if="showPwConfirm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="showPwConfirm = false">
        <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-sm p-6 space-y-4">
            <div class="flex items-center gap-2.5">
                <ShieldCheck :size="18" class="text-brand-400" />
                <h3 class="text-[15px] font-bold text-white">Confirm Password</h3>
            </div>
            <p class="text-[12px] text-ink-3">For your security, please confirm your password to continue.</p>
            <div>
                <label class="heyd2c-label">Password</label>
                <input v-model="pwConfirmValue" type="password" autofocus
                    class="heyd2c-input" @keyup.enter="submitPasswordConfirm" />
                <p v-if="pwConfirmError" class="text-rose-400 text-[11px] mt-1">{{ pwConfirmError }}</p>
            </div>
            <div class="flex gap-2">
                <button @click="submitPasswordConfirm" :disabled="pwConfirmBusy"
                    class="btn btn-primary flex-1 cursor-pointer disabled:opacity-50">
                    {{ pwConfirmBusy ? 'Confirming…' : 'Confirm' }}
                </button>
                <button @click="showPwConfirm = false" class="btn btn-ghost flex-1 cursor-pointer">Cancel</button>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
