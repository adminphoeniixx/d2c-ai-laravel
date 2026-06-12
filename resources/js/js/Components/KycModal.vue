<script setup>
import { computed } from 'vue'
import { ShieldCheck, X } from 'lucide-vue-next'

const props = defineProps({
    kyc_status: { type: Object, default: null },
})

const slug = computed(() => window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '')
const isOnKycPage = computed(() => window.location.pathname.includes('/kyc'))

const showPopup = computed(() =>
    props.kyc_status?.required &&
    !props.kyc_status?.approved &&
    !isOnKycPage.value
)

const canClose  = computed(() => props.kyc_status?.status === 'submitted')

function goToKyc() {
    window.location.href = `/app/${slug.value}/kyc`
}
</script>

<template>
    <div v-if="showPopup"
        class="fixed inset-0 z-[998] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="bg-bg-2 border border-brand-700/50 rounded-2xl w-full max-w-md p-6 text-center relative">

            <!-- Close only if submitted (pending review) -->
            <button v-if="canClose" @click="showPopup = false"
                class="absolute top-4 right-4 text-ink-3 hover:text-white cursor-pointer">
                <X :size="16" />
            </button>

            <div class="w-16 h-16 bg-brand-500/15 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <ShieldCheck :size="28" class="text-brand-400" />
            </div>

            <h2 class="text-[20px] font-bold text-white mb-1">KYC Verification Required</h2>

            <div v-if="kyc_status?.status === 'submitted'" class="bg-amber-900/20 border border-amber-800/30 rounded-xl p-3 mb-4">
                <p class="text-[12px] text-amber-300">Your KYC is under review. You'll be notified once approved.</p>
            </div>
            <div v-else-if="kyc_status?.status === 'rejected'" class="bg-rose-900/20 border border-rose-800/30 rounded-xl p-3 mb-4">
                <p class="text-[12px] text-rose-300">Your KYC was rejected: {{ kyc_status?.rejection_reason || 'Please resubmit.' }}</p>
            </div>
            <div v-else class="mb-4">
                <p class="text-[13px] text-ink-3">Complete your KYC verification to access all features. It only takes 2 minutes.</p>
            </div>

            <button @click="goToKyc" class="btn btn-primary w-full text-[14px] py-3 cursor-pointer mb-2">
                {{ kyc_status?.status === 'submitted' ? 'View KYC Status' : kyc_status?.status === 'rejected' ? 'Resubmit KYC' : '✓ Complete KYC Now' }}
            </button>
            <p v-if="!canClose" class="text-[11px] text-ink-3">Required before accessing the platform</p>
        </div>
    </div>
</template>
