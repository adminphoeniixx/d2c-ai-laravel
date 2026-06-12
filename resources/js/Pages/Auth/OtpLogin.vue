<script setup>
import { ref, computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

const step        = ref('phone')
const resendTimer = ref(0)
let   timerInterval = null

const sendForm   = useForm({ phone: '' })
const verifyForm = useForm({ phone: '', otp: '' })
const otp        = ref(['', '', '', ''])
const otpString  = computed(() => otp.value.join(''))

function sendOtp() {
    if (!sendForm.phone || sendForm.phone.length < 10) return
    sendForm.post(route('otp.send'), {
        onSuccess: () => {
            verifyForm.phone = sendForm.phone
            step.value = 'otp'
            startResendTimer()
            setTimeout(() => document.getElementById('otp-0')?.focus(), 100)
        },
    })
}

function verifyOtp() {
    if (otpString.value.length < 4) return
    verifyForm.otp = otpString.value
    verifyForm.post(route('otp.verify'), {
        onError: () => { otp.value = ['', '', '', '']; setTimeout(() => document.getElementById('otp-0')?.focus(), 50) },
    })
}

function handleOtpInput(index, e) {
    const val = e.target.value.replace(/\D/g, '')
    otp.value[index] = val.slice(-1)
    if (val && index < 3) document.getElementById(`otp-${index + 1}`)?.focus()
    if (otpString.value.length === 4) verifyOtp()
}

function handleOtpKeydown(index, e) {
    if (e.key === 'Backspace' && !otp.value[index] && index > 0)
        document.getElementById(`otp-${index - 1}`)?.focus()
}

function handleOtpPaste(e) {
    const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 4)
    if (text.length === 4) { otp.value = text.split(''); setTimeout(verifyOtp, 100) }
    e.preventDefault()
}

function startResendTimer() {
    resendTimer.value = 30
    clearInterval(timerInterval)
    timerInterval = setInterval(() => { if (resendTimer.value > 0) resendTimer.value--; else clearInterval(timerInterval) }, 1000)
}

function resend() {
    if (resendTimer.value > 0) return
    sendForm.post(route('otp.resend'), {
        onSuccess: () => { otp.value = ['','','','']; startResendTimer() }
    })
}
</script>

<template>
<Head title="Login with OTP" />
<GuestLayout>
    <div class="w-full max-w-sm mx-auto">
        <div class="text-center mb-8">
            <div class="w-12 h-12 bg-brand-600 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <span class="text-white text-[20px] font-bold">h</span>
            </div>
            <h1 class="text-[22px] font-bold text-white">heyd2c</h1>
            <p class="text-[13px] text-ink-3 mt-1">Sign in with your mobile number</p>
        </div>

        <!-- Step: Phone -->
        <div v-if="step === 'phone'">
            <div class="mb-4">
                <label class="heyd2c-label">Mobile Number</label>
                <div class="flex gap-2">
                    <div class="flex items-center justify-center bg-frost-1 border border-frost-2 rounded-lg px-3 text-[13px] text-ink-2 shrink-0">
                        🇮🇳 +91
                    </div>
                    <input v-model="sendForm.phone" type="tel" maxlength="10"
                        placeholder="10-digit mobile number"
                        class="heyd2c-input flex-1 font-mono text-[16px]"
                        @keyup.enter="sendOtp" autofocus />
                </div>
                <p v-if="sendForm.errors.phone" class="text-rose-400 text-[12px] mt-1">{{ sendForm.errors.phone }}</p>
            </div>

            <button @click="sendOtp" :disabled="sendForm.processing || sendForm.phone.length < 10"
                class="btn btn-primary w-full cursor-pointer text-[14px] py-3 disabled:opacity-50">
                {{ sendForm.processing ? 'Sending OTP…' : 'Send OTP' }}
            </button>

            <div class="text-center mt-4">
                <p class="text-[11px] text-ink-3">Enter your registered mobile number to receive an OTP</p>
            </div>
        </div>

        <!-- Step: OTP -->
        <div v-else-if="step === 'otp'">
            <p class="text-[13px] text-ink-3 text-center mb-6">
                We sent a 4-digit OTP to <span class="text-white font-medium">+91 {{ sendForm.phone }}</span>
            </p>

            <div class="flex justify-center gap-3 mb-5" @paste="handleOtpPaste">
                <input v-for="i in 4" :key="i" :id="`otp-${i-1}`"
                    v-model="otp[i-1]" type="tel" maxlength="1"
                    :disabled="verifyForm.processing"
                    class="w-14 h-14 text-center text-[22px] font-bold bg-frost-1 border-2 rounded-xl text-white focus:outline-none focus:border-brand-400 transition disabled:opacity-50"
                    :class="otp[i-1] ? 'border-brand-400' : 'border-frost-2'"
                    @input="handleOtpInput(i-1, $event)"
                    @keydown="handleOtpKeydown(i-1, $event)"
                    :autofocus="i === 1" />
            </div>

            <p v-if="verifyForm.errors.otp || verifyForm.errors.phone"
                class="text-rose-400 text-[12px] text-center mb-3">
                {{ verifyForm.errors.otp || verifyForm.errors.phone }}
            </p>

            <button @click="verifyOtp" :disabled="verifyForm.processing || otpString.length < 4"
                class="btn btn-primary w-full cursor-pointer text-[14px] py-3 disabled:opacity-50">
                {{ verifyForm.processing ? 'Verifying…' : 'Verify & Login' }}
            </button>

            <div class="text-center mt-4 space-y-2">
                <button @click="resend" :disabled="resendTimer > 0"
                    class="text-[12px] cursor-pointer"
                    :class="resendTimer > 0 ? 'text-ink-3' : 'text-brand-300 hover:underline'">
                    {{ resendTimer > 0 ? `Resend in ${resendTimer}s` : 'Resend OTP' }}
                </button>
                <br>
                <button @click="step = 'phone'; otp = ['','','','']; verifyForm.clearErrors()"
                    class="text-[12px] text-ink-3 hover:text-white cursor-pointer">
                    ← Change number
                </button>
            </div>
        </div>
    </div>
</GuestLayout>
</template>

