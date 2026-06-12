<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Zap, X } from 'lucide-vue-next'

const page = usePage()
const ls   = computed(() => page.props.limit_status)
const slug = computed(() => window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '')

// Persist dismissal for the day — resets tomorrow or on new session
const dismissKey = () => `upgrade_dismissed_${slug.value}_${new Date().toDateString()}`
const dismissed  = ref(sessionStorage.getItem(dismissKey()) === '1')
const isNear      = computed(() => ls.value?.is_near && !ls.value?.is_over && ls.value?.is_free)
const isOver      = computed(() => ls.value?.is_over && ls.value?.is_free)
const inGrace     = computed(() => ls.value?.in_grace)
const hardBlocked = computed(() => ls.value?.hard_blocked)
const daysLeft    = computed(() => ls.value?.grace_days_left ?? 0)
const graceEnds   = computed(() => ls.value?.grace_ends_at)
const count       = computed(() => ls.value?.order_count ?? 0)
const limit       = computed(() => ls.value?.order_limit ?? 3000)
const pct         = computed(() => ls.value?.pct_used ?? 0)

const isOnBillingPage = computed(() => window.location.pathname.includes('/subscription/plans'))

// Show popup when over limit and not dismissed and not already on billing page
const showPopup = computed(() => isOver.value && !dismissed.value && !isOnBillingPage.value)
// Only closable during active grace period
const canClose  = computed(() => inGrace.value && !hardBlocked.value)

function goToUpgrade() {
    window.location.href = `/app/${slug.value}/subscription/plans`
}

function dismiss() {
    if (canClose.value) {
        dismissed.value = true
        sessionStorage.setItem(dismissKey(), '1')
    }
}
</script>

<template>
    <!-- Near limit: soft amber banner -->
    <div v-if="isNear && !isOver"
        class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 flex items-center gap-3 bg-amber-950 border border-amber-700 rounded-xl px-4 py-3 shadow-2xl max-w-lg w-[calc(100%-32px)]">
        <Zap :size="15" class="text-amber-400 shrink-0" />
        <div class="flex-1 min-w-0">
            <p class="text-[12px] text-amber-300 font-medium">
                {{ count.toLocaleString('en-IN') }} / {{ limit.toLocaleString('en-IN') }} free orders used ({{ pct }}%)
            </p>
            <div class="bg-amber-900/50 rounded-full h-1.5 mt-1.5">
                <div class="h-1.5 rounded-full bg-amber-500 transition-all" :style="{ width: pct + '%' }"></div>
            </div>
        </div>
        <button @click="goToUpgrade" class="btn btn-primary btn-sm cursor-pointer shrink-0 text-[11px] whitespace-nowrap">
            Upgrade Now
        </button>
    </div>

    <!-- Over limit popup -->
    <div v-if="showPopup"
        class="fixed inset-0 z-[999] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
        <div class="bg-bg-2 border rounded-2xl w-full max-w-md p-6 text-center relative"
            :class="hardBlocked ? 'border-rose-700/50' : 'border-amber-700/50'">

            <!-- Close only during grace -->
            <button v-if="canClose" @click="dismiss"
                class="absolute top-4 right-4 text-ink-3 hover:text-white cursor-pointer">
                <X :size="16" />
            </button>

            <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4"
                :class="hardBlocked ? 'bg-rose-500/15' : 'bg-amber-500/15'">
                <Zap :size="28" :class="hardBlocked ? 'text-rose-400' : 'text-amber-400'" />
            </div>

            <h2 class="text-[20px] font-bold text-white mb-1">
                {{ hardBlocked ? 'Upgrade required' : 'Free plan limit reached' }}
            </h2>

            <!-- Grace countdown -->
            <div v-if="inGrace" class="mb-3">
                <div class="text-[44px] font-extrabold text-amber-400 leading-none">{{ daysLeft }}</div>
                <div class="text-[11px] text-amber-300 mt-0.5">days left · Grace ends {{ graceEnds }}</div>
            </div>

            <!-- Hard blocked -->
            <div v-else-if="hardBlocked" class="bg-rose-900/20 border border-rose-800/30 rounded-xl p-3 mb-3">
                <p class="text-[12px] text-rose-300">Grace period ended. New orders are paused until you upgrade.</p>
            </div>

            <!-- Immediate (grace = 0) -->
            <div v-else class="mb-3">
                <p class="text-[12px] text-rose-300">Order limit reached. Upgrade to continue syncing.</p>
            </div>

            <!-- Usage bar -->
            <div class="bg-frost-1 rounded-full h-2.5 mb-1.5">
                <div class="h-2.5 rounded-full w-full"
                    :class="hardBlocked || !inGrace ? 'bg-rose-500' : 'bg-amber-500'"></div>
            </div>
            <p class="text-[11px] mb-4" :class="hardBlocked || !inGrace ? 'text-rose-400' : 'text-amber-400'">
                {{ count.toLocaleString('en-IN') }} / {{ limit.toLocaleString('en-IN') }} orders used
            </p>

            <p class="text-[13px] text-ink-3 mb-5">
                You've used all <strong class="text-white">{{ limit.toLocaleString('en-IN') }} free orders</strong>.
                All your existing data is safe. Upgrade to resume syncing.
            </p>

            <button @click="goToUpgrade"
                class="btn btn-primary w-full text-[14px] py-3 cursor-pointer mb-2">
                🚀 Upgrade Now — from ₹999/mo
            </button>

            <p v-if="canClose" class="text-[10px] text-ink-3 hover:text-ink-2 cursor-pointer" @click="dismiss">
                Remind me later ({{ daysLeft }} days left in grace)
            </p>
            <p v-else class="text-[11px] text-ink-3">No lock-in · Cancel anytime</p>
        </div>
    </div>
</template>
