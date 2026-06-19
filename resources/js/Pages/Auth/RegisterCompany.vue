<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const hasGst = ref(false);
const step = ref(1); // 1 = phone, 2 = verify OTP, 3 = registration form
const otpSent = ref(false);
const otpVerified = ref(false);
const otpError = ref('');
const otpLoading = ref(false);
const phoneNumber = ref('');
const otpCode = ref('');
const resendTimer = ref(0);
let resendInterval = null;

const form = useForm({
    company_name: '',
    slug: '',
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    gstin: '',
    business_category: 'apparel',
    country: 'IN',
    currency: 'INR',
    timezone: 'Asia/Kolkata',
    terms: false,
    phone_verified: false,
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

async function sendOtp() {
    if (!phoneNumber.value || phoneNumber.value.length < 10) {
        otpError.value = 'Enter a valid 10-digit phone number';
        return;
    }
    otpLoading.value = true;
    otpError.value = '';
    try {
        // This is the registration page — always use the registration OTP
        // endpoint directly. The previous logic tried the employee-login
        // endpoint first and only fell back on an exact 404, which meant
        // any other failure (422 validation, 500, etc.) from that unrelated
        // endpoint surfaced as "Failed to send OTP" without ever attempting
        // the correct registration endpoint.
        const res = await fetch('/api/v1/register/send-otp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ phone: phoneNumber.value }),
        });
        const data = await res.json();
        if (res.ok && data.success) {
            otpSent.value = true;
            step.value = 2;
            startResendTimer();
        } else {
            otpError.value = data.error || 'Failed to send OTP';
        }
    } catch (e) {
        otpError.value = 'Network error. Try again.';
    }
    otpLoading.value = false;
}

async function verifyOtp() {
    if (otpCode.value.length !== 6) {
        otpError.value = 'Enter 6-digit OTP';
        return;
    }
    otpLoading.value = true;
    otpError.value = '';
    try {
        const res = await fetch('/api/v1/register/verify-otp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ phone: phoneNumber.value, otp: otpCode.value }),
        });
        const data = await res.json();
        if (res.ok && data.success) {
            otpVerified.value = true;
            form.phone = phoneNumber.value;
            form.phone_verified = true;
            step.value = 3;
        } else {
            otpError.value = data.error || 'Invalid OTP';
        }
    } catch (e) {
        otpError.value = 'Network error. Try again.';
    }
    otpLoading.value = false;
}

function startResendTimer() {
    resendTimer.value = 30;
    if (resendInterval) clearInterval(resendInterval);
    resendInterval = setInterval(() => {
        resendTimer.value--;
        if (resendTimer.value <= 0) clearInterval(resendInterval);
    }, 1000);
}

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

                <!-- STEP 1: Phone Number -->
                <div v-if="step === 1" class="card space-y-4">
                    <div class="text-center mb-2">
                        <div class="text-[14px] font-semibold text-white">Verify your phone number</div>
                        <p class="text-[11px] text-ink-3 mt-1">We'll send a 6-digit OTP to verify</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">Mobile Number</label>
                        <div class="flex items-center gap-2">
                            <span class="text-[13px] text-ink-3 font-mono bg-bg-3 border border-frost-2 rounded-[10px] px-3 py-2.5">+91</span>
                            <input v-model="phoneNumber" class="heyd2c-input flex-1" placeholder="9876543210"
                                maxlength="10" type="tel" @keyup.enter="sendOtp" autofocus />
                        </div>
                        <div v-if="otpError" class="mt-1 text-[11px] text-rose">{{ otpError }}</div>
                    </div>
                    <button @click="sendOtp" class="btn btn-primary w-full py-3 text-[14px] cursor-pointer" :disabled="otpLoading">
                        {{ otpLoading ? 'Sending…' : 'Send OTP →' }}
                    </button>
                    <p class="text-center text-[12px] text-ink-3">
                        Already have an account?
                        <Link :href="route('otp.login')" class="text-brand-300 hover:underline">Sign in</Link>
                    </p>
                </div>

                <!-- STEP 2: Enter OTP -->
                <div v-if="step === 2" class="card space-y-4">
                    <div class="text-center mb-2">
                        <div class="text-[14px] font-semibold text-white">Enter OTP</div>
                        <p class="text-[11px] text-ink-3 mt-1">Sent to +91 {{ phoneNumber }}</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">6-digit OTP</label>
                        <input v-model="otpCode" class="heyd2c-input text-center text-[20px] font-mono tracking-[8px]"
                            maxlength="6" type="tel" placeholder="------" @keyup.enter="verifyOtp" autofocus />
                        <div v-if="otpError" class="mt-1 text-[11px] text-rose">{{ otpError }}</div>
                    </div>
                    <button @click="verifyOtp" class="btn btn-primary w-full py-3 text-[14px] cursor-pointer" :disabled="otpLoading">
                        {{ otpLoading ? 'Verifying…' : 'Verify →' }}
                    </button>
                    <div class="flex items-center justify-between text-[11px]">
                        <button @click="step = 1; otpError = ''" class="text-ink-3 hover:text-white cursor-pointer">← Change number</button>
                        <button v-if="resendTimer <= 0" @click="sendOtp" class="text-brand-300 hover:underline cursor-pointer">Resend OTP</button>
                        <span v-else class="text-ink-3">Resend in {{ resendTimer }}s</span>
                    </div>
                </div>

                <!-- STEP 3: Registration Form -->
                <form v-if="step === 3" class="card space-y-4" @submit.prevent="submit">
                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-500/10 border border-emerald-500/20 mb-2">
                        <span class="text-emerald-400 text-[13px]">✓</span>
                        <span class="text-[12px] text-emerald-400">+91 {{ phoneNumber }} verified</span>
                    </div>

                    <div>
                        <label class="heyd2c-label">Company name</label>
                        <input v-model="form.company_name" class="heyd2c-input" placeholder="Acme Apparel" autofocus />
                        <div v-if="form.errors.company_name" class="mt-1 text-[11px] text-rose">{{ form.errors.company_name }}</div>
                    </div>

                    <div>
                        <label class="heyd2c-label">Workspace URL</label>
                        <div class="flex items-center gap-2 rounded-[10px] border border-frost-2 bg-bg-3 px-3.5 py-2.5">
                            <span class="text-[12px] text-ink-3 font-mono">heyd2c.app/app/</span>
                            <input v-model="form.slug" class="flex-1 bg-transparent border-0 p-0 text-[13px] outline-none text-ink" placeholder="acme" />
                        </div>
                        <div v-if="form.errors.slug" class="mt-1 text-[11px] text-rose">{{ form.errors.slug }}</div>
                    </div>

                    <div class="pt-2 border-t border-frost-1" />

                    <div>
                        <label class="heyd2c-label">Business Category</label>
                        <select v-model="form.business_category" class="heyd2c-input">
                            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>

                    <label class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                        <input v-model="hasGst" type="checkbox" class="accent-brand-600" />
                        I have a GSTIN (enables GST breakup on orders)
                    </label>

                    <div v-if="hasGst">
                        <label class="heyd2c-label">GSTIN</label>
                        <input v-model="form.gstin" class="heyd2c-input font-mono" placeholder="27AABCU9603R1ZM" maxlength="15" />
                        <div v-if="form.errors.gstin" class="mt-1 text-[11px] text-rose">{{ form.errors.gstin }}</div>
                        <p class="mt-1 text-[10px] text-ink-3">State auto-detected from first 2 digits · You can add this later in Settings</p>
                    </div>

                    <div class="pt-2 border-t border-frost-1" />

                    <div>
                        <label class="heyd2c-label">Your name</label>
                        <input v-model="form.name" class="heyd2c-input" placeholder="Priya Singh" />
                        <div v-if="form.errors.name" class="mt-1 text-[11px] text-rose">{{ form.errors.name }}</div>
                    </div>

                    <div>
                        <label class="heyd2c-label">Email</label>
                        <input v-model="form.email" type="email" class="heyd2c-input" placeholder="you@company.com" />
                        <div v-if="form.errors.email" class="mt-1 text-[11px] text-rose">{{ form.errors.email }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="heyd2c-label">Password</label>
                            <input v-model="form.password" type="password" class="heyd2c-input" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Confirm</label>
                            <input v-model="form.password_confirmation" type="password" class="heyd2c-input" />
                        </div>
                    </div>
                    <div v-if="form.errors.password" class="text-[11px] text-rose">{{ form.errors.password }}</div>

                    <label class="flex items-start gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                        <input v-model="form.terms" type="checkbox" class="mt-0.5 accent-brand-600" />
                        <span>I agree to the <Link :href="route('terms')" class="text-brand-300 hover:underline">Terms</Link> and <Link :href="route('privacy')" class="text-brand-300 hover:underline">Privacy Policy</Link>.</span>
                    </label>
                    <div v-if="form.errors.terms" class="-mt-2 text-[11px] text-rose">{{ form.errors.terms }}</div>

                    <button type="submit" class="btn btn-primary w-full py-3 text-[14px]" :disabled="form.processing">
                        {{ form.processing ? 'Creating…' : 'Create workspace →' }}
                    </button>

                    <p class="text-center text-[12px] text-ink-3">
                        Already have an account?
                        <Link :href="route('otp.login')" class="text-brand-300 hover:underline">Sign in</Link>
                    </p>
                </form>
            </div>
        </section>
    </GuestLayout>
</template>
