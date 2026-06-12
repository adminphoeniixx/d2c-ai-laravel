<script setup>
import { ref, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { Check, Zap, CreditCard, FileText } from 'lucide-vue-next'
import TenantLayout from '@/Layouts/TenantLayout.vue'

const props = defineProps({
    plans:        Array,
    current_plan: Object,
    active_sub:   Object,
    limit_status: Object,
    invoices:     Array,
    razorpay_key: String,
})

// Extract slug from URL — same pattern used across all tenant pages
const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || ''

const billing       = ref('monthly')
const couponCode    = ref('')
const couponResult  = ref(null)
const checkingCoupon= ref(false)
const processing    = ref(null)

const fmt    = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })
const price  = (plan) => billing.value === 'yearly' ? plan.price_yearly : plan.price
const displayPrice = (plan) => price(plan) === 0 ? 'Free' : fmt(price(plan))

const planColor = (slug) => ({
    free: 'text-slate-400', basic: 'text-blue-400',
    growth: 'text-purple-400', scale: 'text-amber-400',
}[slug] || 'text-white')

const planBorder = (slug) => ({
    free: '', basic: 'border-blue-500/30',
    growth: 'border-brand-400', scale: 'border-amber-500/30',
}[slug] || '')

const featureLabels = {
    expenses: 'Expenses & Receipts', orders: 'Order Management',
    banking: 'Banking & Ledger', logistics: 'Logistics Analytics',
    gst: 'GST Reports', hr: 'HR & Attendance', payroll: 'Payroll',
    ai: 'AI Features', marketplace: 'Marketplace Sync',
    dedicated_support: 'Dedicated Support', custom_integrations: 'Custom Integrations',
}

async function applyCoupon(planId, amount) {
    if (!couponCode.value) return
    checkingCoupon.value = true
    try {
        const res = await fetch(`/app/${slug}/subscription/coupon`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ code: couponCode.value, plan_id: planId, amount }),
        })
        couponResult.value = await res.json()
    } finally { checkingCoupon.value = false }
}

async function subscribe(plan) {
    if (plan.is_free || plan.slug === props.current_plan?.slug) return

    if (!props.razorpay_key) {
        alert('Payment gateway not configured yet. Please contact support.')
        return
    }

    processing.value = plan.id

    // Fetch order from server
    let orderData = null
    try {
        const res = await fetch(`/app/${slug}/subscription/order`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                plan_id:      plan.id,
                billing_cycle: billing.value,
                coupon_code:  couponCode.value || null,
            }),
        })
        orderData = await res.json()
    } catch(e) {
        alert('Network error. Please try again.')
        processing.value = null
        return
    }

    if (!orderData || orderData.error) { alert(orderData?.error || 'Error'); processing.value = null; return }
    if (orderData.free) { window.location.reload(); return }

    // Capture all needed values before opening Razorpay
    const rzpOrderId    = orderData.order_id
    const rzpAmount     = orderData.amount
    const rzpPlanId     = String(orderData.plan_id)
    const rzpCycle      = String(orderData.billing_cycle)
    const rzpSubtotal   = String(orderData.subtotal ?? 0)
    const rzpDiscount   = String(orderData.discount ?? 0)
    const rzpCoupon     = String(orderData.coupon_code ?? '')
    const verifyUrl     = `/app/${slug}/subscription/verify`
    const xsrf          = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '')

    const rzp = new window.Razorpay({
        key:         props.razorpay_key,
        amount:      rzpAmount,
        currency:    'INR',
        name:        'heyd2c',
        description: `${plan.name} — ${billing.value}`,
        order_id:    rzpOrderId,
        theme:       { color: '#7c3aed' },
        handler: (response) => {

            const form = document.createElement('form')
            form.method = 'POST'
            form.action = verifyUrl
            form.style.display = 'none'

            const data = {
                _token:                xsrf,
                razorpay_order_id:     response.razorpay_order_id   || rzpOrderId || '',
                razorpay_payment_id:   response.razorpay_payment_id || '',
                razorpay_signature:    response.razorpay_signature   || '',
                plan_id:               rzpPlanId,
                billing_cycle:         rzpCycle,
                amount:                rzpSubtotal,
                discount:              rzpDiscount,
                coupon_code:           rzpCoupon,
            }

            for (const [key, value] of Object.entries(data)) {
                const input = document.createElement('input')
                input.type  = 'hidden'
                input.name  = key
                input.value = value
                form.appendChild(input)
            }

            document.body.appendChild(form)
            form.submit()
        },
        modal: { ondismiss: () => { processing.value = null } },
    })

    rzp.open()
    processing.value = null
}
</script>

<template>
<Head title="Subscription & Billing" />
<TenantLayout>
    <div class="max-w-4xl space-y-5">
        <h1 class="text-[20px] font-bold text-white">Subscription & Billing</h1>

        <!-- Current plan card -->
        <div class="card border" :class="planBorder(current_plan?.slug)">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <p class="text-[11px] text-ink-3 uppercase font-medium mb-1">Current Plan</p>
                    <div class="flex items-center gap-2">
                        <h2 class="text-[22px] font-bold" :class="planColor(current_plan?.slug)">{{ current_plan?.name }}</h2>
                        <span v-if="active_sub" class="bg-emerald-500/15 text-emerald-400 text-[10px] px-2 py-0.5 rounded-full font-medium">Active</span>
                        <span v-else class="bg-slate-500/15 text-slate-400 text-[10px] px-2 py-0.5 rounded-full font-medium">Free</span>
                    </div>
                    <div v-if="active_sub" class="text-[12px] text-ink-3 mt-1">
                        Renews {{ new Date(active_sub.ends_at).toLocaleDateString('en-IN', {day:'numeric',month:'short',year:'numeric'}) }}
                        · {{ fmt(active_sub.final_amount) }} / {{ active_sub.billing_cycle }}
                    </div>
                </div>

                <!-- Usage meter for free plan -->
                <div v-if="limit_status?.is_free" class="min-w-[180px]">
                    <div class="flex justify-between text-[11px] mb-1.5">
                        <span class="text-ink-3">Orders used</span>
                        <span :class="limit_status.is_over ? 'text-rose-400 font-semibold' : limit_status.is_near ? 'text-amber-400 font-semibold' : 'text-ink-2'">
                            {{ limit_status.order_count?.toLocaleString('en-IN') }} / {{ limit_status.order_limit?.toLocaleString('en-IN') }}
                        </span>
                    </div>
                    <div class="bg-frost-1 rounded-full h-2">
                        <div class="h-2 rounded-full transition-all"
                            :class="limit_status.is_over ? 'bg-rose-500' : limit_status.is_near ? 'bg-amber-500' : 'bg-brand-400'"
                            :style="{ width: limit_status.pct_used + '%' }"></div>
                    </div>
                    <p v-if="limit_status.is_over" class="text-[10px] text-rose-400 mt-1">Limit reached — upgrade to continue</p>
                </div>
            </div>
        </div>

        <!-- Upgrade section -->
        <div>
            <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                <h2 class="text-[15px] font-semibold text-white">
                    {{ current_plan?.is_free ? 'Upgrade your plan' : 'Change plan' }}
                </h2>
                <!-- Billing toggle -->
                <div class="inline-flex items-center gap-1 bg-frost-1 rounded-xl p-1">
                    <button @click="billing = 'monthly'"
                        :class="billing === 'monthly' ? 'bg-bg-2 text-white shadow' : 'text-ink-3'"
                        class="px-3 py-1 rounded-lg text-[11px] font-medium transition cursor-pointer">Monthly</button>
                    <button @click="billing = 'yearly'"
                        :class="billing === 'yearly' ? 'bg-bg-2 text-white shadow' : 'text-ink-3'"
                        class="px-3 py-1 rounded-lg text-[11px] font-medium transition cursor-pointer">
                        Yearly <span class="text-emerald-400 text-[9px] ml-1">Save 17%</span>
                    </button>
                </div>
            </div>

            <!-- Plan cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div v-for="plan in plans" :key="plan.id"
                    :class="[plan.is_featured ? 'border-brand-400 bg-brand-600/5' : 'border-frost-1',
                             plan.slug === current_plan?.slug ? 'ring-2 ring-brand-400' : '']"
                    class="card border-2 relative flex flex-col">

                    <div v-if="plan.is_featured" class="absolute -top-3 left-1/2 -translate-x-1/2 bg-brand-600 text-white text-[9px] font-bold px-2.5 py-0.5 rounded-full whitespace-nowrap">
                        MOST POPULAR
                    </div>
                    <div v-if="plan.slug === current_plan?.slug" class="absolute -top-3 right-2 bg-emerald-600 text-white text-[9px] font-bold px-2.5 py-0.5 rounded-full">
                        CURRENT
                    </div>

                    <h3 class="text-[13px] font-bold text-white mb-1">{{ plan.name }}</h3>
                    <div class="text-[22px] font-bold mb-0.5" :class="planColor(plan.slug)">{{ displayPrice(plan) }}</div>
                    <div v-if="!plan.is_free" class="text-[9px] text-ink-3 mb-3">{{ billing === 'yearly' ? '/year' : '/month' }}</div>
                    <div v-else class="text-[9px] text-ink-3 mb-3">forever</div>

                    <ul class="space-y-1 mb-4 flex-1">
                        <li class="flex items-start gap-1.5 text-[10px] text-ink-2">
                            <Check :size="10" class="text-emerald-400 mt-0.5 shrink-0" />
                            {{ plan.order_limit === -1 ? 'Unlimited orders' : plan.order_limit?.toLocaleString('en-IN') + ' orders' }}
                        </li>
                        <li class="flex items-start gap-1.5 text-[10px] text-ink-2">
                            <Check :size="10" class="text-emerald-400 mt-0.5 shrink-0" />
                            {{ plan.store_limit === -1 ? 'Unlimited stores' : plan.store_limit + (plan.store_limit > 1 ? ' stores' : ' store') }}
                        </li>
                        <li class="flex items-start gap-1.5 text-[10px] text-ink-2">
                            <Check :size="10" class="text-emerald-400 mt-0.5 shrink-0" />
                            {{ plan.team_member_limit === -1 ? 'Unlimited team' : plan.team_member_limit + ' team members' }}
                        </li>
                        <li class="flex items-start gap-1.5 text-[10px] text-ink-2">
                            <Check :size="10" class="text-emerald-400 mt-0.5 shrink-0" />
                            {{ plan.data_history_days === -1 ? 'Unlimited history' : plan.data_history_days + ' days history' }}
                        </li>
                    </ul>

                    <button @click="subscribe(plan)"
                        :disabled="plan.slug === current_plan?.slug || plan.is_free || processing === plan.id"
                        :class="plan.is_featured ? 'btn-primary' : 'btn-ghost'"
                        class="btn w-full text-[11px] cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed py-2">
                        {{ plan.slug === current_plan?.slug ? '✓ Current'
                         : plan.is_free ? 'Free Forever'
                         : processing === plan.id ? '…'
                         : 'Upgrade' }}
                    </button>
                </div>
            </div>

            <!-- Coupon -->
            <div class="mt-4 flex items-center gap-2 flex-wrap">
                <input v-model="couponCode" class="heyd2c-input w-44 font-mono uppercase text-[12px]" placeholder="Coupon code" />
                <button @click="applyCoupon(plans[1]?.id, price(plans[1]))"
                    :disabled="checkingCoupon || !couponCode"
                    class="btn btn-ghost btn-sm cursor-pointer">Apply</button>
                <span v-if="couponResult" class="text-[11px]"
                    :class="couponResult.valid ? 'text-emerald-400' : 'text-rose-400'">
                    {{ couponResult.valid ? '✓ ' + couponResult.description : couponResult.error }}
                </span>
            </div>
        </div>

        <!-- Invoice history -->
        <div v-if="invoices?.length" class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-frost-1 flex items-center gap-2">
                <FileText :size="14" class="text-brand-400" />
                <h3 class="text-[13px] font-semibold text-white">Billing History</h3>
            </div>
            <table class="w-full text-[12px]">
                <thead><tr class="border-b border-frost-1">
                    <th class="text-left px-5 py-2.5 text-[10px] text-ink-3 font-medium">Invoice</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 font-medium">Subtotal</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 font-medium">GST</th>
                    <th class="text-right px-5 py-2.5 text-[10px] text-ink-3 font-medium">Total</th>
                    <th class="text-left px-5 py-2.5 text-[10px] text-ink-3 font-medium">Date</th>
                    <th class="text-left px-5 py-2.5 text-[10px] text-ink-3 font-medium">Status</th>
                    <th class="px-5 py-2.5"></th>
                </tr></thead>
                <tbody>
                    <tr v-for="inv in invoices" :key="inv.id" class="border-b border-frost-1 last:border-0">
                        <td class="px-5 py-3 font-mono text-[11px] text-brand-300">{{ inv.invoice_number }}</td>
                        <td class="px-5 py-3 text-right text-ink-2">{{ fmt(inv.subtotal) }}</td>
                        <td class="px-5 py-3 text-right text-ink-3">{{ fmt((inv.cgst||0) + (inv.sgst||0) + (inv.igst||0)) }}</td>
                        <td class="px-5 py-3 text-right text-white font-semibold">{{ fmt(inv.total) }}</td>
                        <td class="px-5 py-3 text-ink-3">{{ inv.paid_at ? new Date(inv.paid_at).toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric'}) : '—' }}</td>
                        <td class="px-5 py-3">
                            <span class="bg-emerald-500/15 text-emerald-400 text-[10px] px-2 py-0.5 rounded-full capitalize">{{ inv.status }}</span>
                        </td>
                        <td class="px-5 py-3">
                            <a :href="`/app/${slug}/subscription/invoice/${inv.id}/download`" target="_blank"
                                class="text-[11px] text-brand-300 hover:underline flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                PDF
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="!invoices?.length" class="card text-center py-6">
            <CreditCard :size="24" class="text-ink-3 mx-auto mb-2" />
            <p class="text-[13px] text-ink-3">No billing history yet.</p>
            <p class="text-[11px] text-ink-3 mt-1">Invoices will appear here after your first payment.</p>
        </div>
    </div>
</TenantLayout>
</template>
