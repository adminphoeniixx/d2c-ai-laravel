<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Shield, AlertTriangle } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props  = defineProps({ settings: Object })
const form   = ref({ ...props.settings })
const saving = ref(false)
function save() {
    saving.value = true
    router.post(route('admin.subscriptions.settings.update'), form.value, {
        onFinish: () => { saving.value = false }
    })
}
</script>

<template>
<Head title="Payment Settings" />
<AdminLayout>
    <div class="max-w-xl space-y-5">
        <div class="flex items-center justify-between">
            <h1 class="text-[20px] font-bold text-white">Payment Settings</h1>
            <a :href="route('admin.subscriptions.dashboard')" class="btn btn-ghost btn-sm cursor-pointer">← Dashboard</a>
        </div>

        <!-- Mode toggle -->
        <div class="card">
            <h3 class="text-[13px] font-semibold text-white mb-3">Razorpay Mode</h3>
            <div class="flex gap-3">
                <button @click="form.razorpay_mode = 'test'"
                    :class="form.razorpay_mode === 'test' ? 'bg-amber-500/20 border-amber-500 text-amber-400' : 'border-frost-1 text-ink-3 hover:border-frost-2'"
                    class="flex-1 border-2 rounded-xl py-3 text-[13px] font-medium transition cursor-pointer">
                    🧪 Test Mode
                </button>
                <button @click="form.razorpay_mode = 'live'"
                    :class="form.razorpay_mode === 'live' ? 'bg-emerald-500/20 border-emerald-500 text-emerald-400' : 'border-frost-1 text-ink-3 hover:border-frost-2'"
                    class="flex-1 border-2 rounded-xl py-3 text-[13px] font-medium transition cursor-pointer">
                    🚀 Live Mode
                </button>
            </div>
            <div v-if="form.razorpay_mode === 'live'" class="mt-3 flex items-center gap-2 bg-rose-900/20 border border-rose-800/40 rounded-lg p-3">
                <AlertTriangle :size="14" class="text-rose-400 shrink-0" />
                <p class="text-[11px] text-rose-300">Live mode active — real payments will be charged.</p>
            </div>
        </div>

        <!-- Test Keys -->
        <div class="card space-y-3">
            <h3 class="text-[13px] font-semibold text-white">Test Keys</h3>
            <div><label class="heyd2c-label">Key ID (Test)</label>
            <input v-model="form.razorpay_key_id_test" class="heyd2c-input font-mono" placeholder="rzp_test_…" /></div>
            <div><label class="heyd2c-label">Key Secret (Test)</label>
            <input v-model="form.razorpay_key_secret_test" type="password" class="heyd2c-input font-mono" placeholder="••••••••" /></div>
        </div>

        <!-- Live Keys -->
        <div class="card space-y-3">
            <h3 class="text-[13px] font-semibold text-white">Live Keys</h3>
            <div><label class="heyd2c-label">Key ID (Live)</label>
            <input v-model="form.razorpay_key_id_live" class="heyd2c-input font-mono" placeholder="rzp_live_…" /></div>
            <div><label class="heyd2c-label">Key Secret (Live)</label>
            <input v-model="form.razorpay_key_secret_live" type="password" class="heyd2c-input font-mono" placeholder="••••••••" /></div>
        </div>

        <!-- GST -->
        <div class="card">
            <h3 class="text-[13px] font-semibold text-white mb-3">Tax Settings</h3>
            <div><label class="heyd2c-label">GST Rate (%)</label>
            <input v-model.number="form.gst_rate" type="number" min="0" max="28" class="heyd2c-input w-32" /></div>
        </div>

        <!-- KYC Settings -->
        <div class="card space-y-4">
            <h3 class="text-[13px] font-semibold text-white">KYC Settings</h3>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[12px] text-white font-medium">Require KYC Verification</p>
                    <p class="text-[11px] text-ink-3 mt-0.5">If enabled, companies must complete KYC before using the platform</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" v-model="form.kyc_required" :true-value="'1'" :false-value="'0'" class="sr-only peer" />
                    <div class="w-11 h-6 bg-frost-2 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-brand-600"></div>
                </label>
            </div>
        </div>
        <div class="card space-y-4">
            <h3 class="text-[13px] font-semibold text-white">Grace Period Settings</h3>
            <p class="text-[11px] text-ink-3 -mt-2">After a free account exceeds its order limit, how long before access is hard-blocked.</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Grace Period (days)</label>
                    <input v-model.number="form.grace_period_days" type="number" min="0" max="30" class="heyd2c-input w-32" />
                    <p class="text-[10px] text-ink-3 mt-1">0 = no grace period (hard block immediately)</p>
                </div>
                <div>
                    <label class="heyd2c-label">Warning Threshold (%)</label>
                    <input v-model.number="form.limit_warning_pct" type="number" min="50" max="99" class="heyd2c-input w-32" />
                    <p class="text-[10px] text-ink-3 mt-1">Show soft warning when X% of limit is used</p>
                </div>
            </div>
            <div>
                <label class="heyd2c-label mb-2 block">Send Grace Period Reminder Emails</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="form.grace_email_day_1" :true-value="'1'" :false-value="'0'" class="rounded" />
                        <span class="text-[12px] text-ink-2">Day 1 — when limit is first hit</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="form.grace_email_day_3" :true-value="'1'" :false-value="'0'" class="rounded" />
                        <span class="text-[12px] text-ink-2">Day 3 — mid-grace reminder</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="form.grace_email_day_7" :true-value="'1'" :false-value="'0'" class="rounded" />
                        <span class="text-[12px] text-ink-2">Last day — final warning before block</span>
                    </label>
                </div>
            </div>
        </div>

        <button @click="save" :disabled="saving" class="btn btn-primary w-full cursor-pointer">
            {{ saving ? 'Saving…' : 'Save Settings' }}
        </button>
    </div>
</AdminLayout>
</template>
