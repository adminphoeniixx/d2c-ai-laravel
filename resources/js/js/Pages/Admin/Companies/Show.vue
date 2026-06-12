<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { LogIn, Pause, Play, Trash2, Save, User, Building2, CreditCard, RefreshCw } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    company:      { type: Object, default: () => ({}) },
    users:        { type: Array,  default: () => [] },
    subscription: { type: Object, default: null },
    plans:        { type: Array,  default: () => [] },
})

// ── Company form
const companyForm = useForm({
    name:              props.company.name              || '',
    email:             props.company.email             || '',
    phone:             props.company.phone             || '',
    status:            props.company.status            || 'active',
    plan:              props.company.plan              || 'free',
    country:           props.company.country           || 'IN',
    currency:          props.company.currency          || 'INR',
    timezone:          props.company.timezone          || 'Asia/Kolkata',
    business_category: props.company.business_category || '',
    gstin:             props.company.gstin             || '',
    subscription_status: props.company.subscription_status || 'free',
    order_count:       props.company.order_count       || 0,
})

function saveCompany() {
    companyForm.put(route('admin.companies.update', props.company.id), { preserveScroll: true })
}

// ── Owner form
const ownerForm = useForm({
    name:  props.users[0]?.name  || '',
    email: props.users[0]?.email || '',
    phone: props.users[0]?.phone || '',
})

function saveOwner() {
    ownerForm.post(route('admin.companies.update-owner', props.company.id), { preserveScroll: true })
}

// ── Plan override
const selectedPlan = ref(props.company.active_plan_id || '')
function setPlan() {
    if (!selectedPlan.value) return
    router.post(route('admin.companies.set-plan', props.company.id), { plan_id: selectedPlan.value }, { preserveScroll: true })
}

// ── Actions
function suspend()     { router.post(route('admin.companies.suspend',     props.company.id), {}, { preserveScroll: true }) }
function activate()    { router.post(route('admin.companies.activate',    props.company.id), {}, { preserveScroll: true }) }
function impersonate() { router.post(route('admin.companies.impersonate', props.company.id)) }
function destroy() {
    if (!confirm(`Delete ${props.company.name}? This will permanently drop the tenant database.`)) return
    router.visit(route('admin.companies.destroy', props.company.id), { method: 'delete' })
}

const fmt = (n) => '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 })
const statusColor = (s) => ({
    active: 'bg-emerald-500/15 text-emerald-400',
    suspended: 'bg-rose-500/15 text-rose-400',
    pending: 'bg-amber-500/15 text-amber-400',
}[s] || 'bg-slate-500/15 text-slate-400')

const planColor = (p) => ({
    free: 'text-slate-400', basic: 'text-blue-400',
    growth: 'text-purple-400', scale: 'text-amber-400',
}[p] || 'text-white')

const timezones = [
    'Asia/Kolkata', 'Asia/Dubai', 'Asia/Singapore', 'UTC',
    'America/New_York', 'America/Los_Angeles', 'Europe/London',
]
const categories = ['apparel', 'footwear', 'electronics', 'beauty', 'food', 'luxury', 'other']
</script>

<template>
<Head :title="`${company.name} — Edit`" />
<AdminLayout>
    <div class="space-y-5 max-w-4xl">

        <!-- Header -->
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <a :href="route('admin.companies.index')" class="text-ink-3 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <h1 class="text-[20px] font-bold text-white">{{ company.name }}</h1>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="font-mono text-[11px] text-ink-3">{{ company.slug }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-medium" :class="statusColor(company.status)">{{ company.status }}</span>
                        <span class="text-[11px] font-semibold" :class="planColor(company.plan)">{{ company.plan }}</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button @click="impersonate" class="btn btn-ghost btn-sm flex items-center gap-1 cursor-pointer">
                    <LogIn :size="12" /> Impersonate
                </button>
                <button v-if="company.status === 'active'" @click="suspend"
                    class="btn btn-ghost btn-sm text-amber-400 flex items-center gap-1 cursor-pointer">
                    <Pause :size="12" /> Suspend
                </button>
                <button v-else @click="activate"
                    class="btn btn-ghost btn-sm text-emerald-400 flex items-center gap-1 cursor-pointer">
                    <Play :size="12" /> Activate
                </button>
                <button @click="destroy" class="btn btn-ghost btn-sm text-rose-400 flex items-center gap-1 cursor-pointer">
                    <Trash2 :size="12" /> Delete
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <!-- Left col: company + owner forms -->
            <div class="lg:col-span-2 space-y-4">

                <!-- Company Details -->
                <div class="card">
                    <div class="flex items-center gap-2 mb-4">
                        <Building2 :size="15" class="text-brand-400" />
                        <h3 class="text-[14px] font-semibold text-white">Company Details</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="heyd2c-label">Company Name</label>
                            <input v-model="companyForm.name" class="heyd2c-input" />
                            <p v-if="companyForm.errors.name" class="text-rose-400 text-[11px] mt-1">{{ companyForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="heyd2c-label">Email</label>
                            <input v-model="companyForm.email" type="email" class="heyd2c-input" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Phone</label>
                            <input v-model="companyForm.phone" type="tel" class="heyd2c-input font-mono" placeholder="+91XXXXXXXXXX" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Business Category</label>
                            <select v-model="companyForm.business_category" class="heyd2c-input capitalize">
                                <option value="">— None —</option>
                                <option v-for="c in categories" :key="c" :value="c" class="capitalize">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="heyd2c-label">GSTIN</label>
                            <input v-model="companyForm.gstin" class="heyd2c-input font-mono uppercase" placeholder="22AAAAA0000A1Z5" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Country</label>
                            <input v-model="companyForm.country" maxlength="2" class="heyd2c-input uppercase" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Currency</label>
                            <input v-model="companyForm.currency" maxlength="3" class="heyd2c-input uppercase" />
                        </div>
                        <div class="col-span-2">
                            <label class="heyd2c-label">Timezone</label>
                            <select v-model="companyForm.timezone" class="heyd2c-input">
                                <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="heyd2c-label">Status</label>
                            <select v-model="companyForm.status" class="heyd2c-input">
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        <div>
                            <label class="heyd2c-label">Order Count</label>
                            <input v-model.number="companyForm.order_count" type="number" min="0" class="heyd2c-input" />
                            <p class="text-[10px] text-ink-3 mt-0.5">Used for free plan limit tracking</p>
                        </div>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button @click="saveCompany" :disabled="companyForm.processing"
                            class="btn btn-primary btn-sm flex items-center gap-1.5 cursor-pointer">
                            <Save :size="12" /> {{ companyForm.processing ? 'Saving…' : 'Save Company' }}
                        </button>
                    </div>
                </div>

                <!-- Owner / User Details -->
                <div class="card" v-if="users.length">
                    <div class="flex items-center gap-2 mb-4">
                        <User :size="15" class="text-brand-400" />
                        <h3 class="text-[14px] font-semibold text-white">Owner Details</h3>
                        <span class="text-[10px] text-ink-3 ml-1">{{ users[0]?.email }}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="heyd2c-label">Full Name</label>
                            <input v-model="ownerForm.name" class="heyd2c-input" />
                            <p v-if="ownerForm.errors.name" class="text-rose-400 text-[11px] mt-1">{{ ownerForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="heyd2c-label">Email</label>
                            <input v-model="ownerForm.email" type="email" class="heyd2c-input" />
                            <p v-if="ownerForm.errors.email" class="text-rose-400 text-[11px] mt-1">{{ ownerForm.errors.email }}</p>
                        </div>
                        <div>
                            <label class="heyd2c-label">Mobile Number</label>
                            <input v-model="ownerForm.phone" type="tel" class="heyd2c-input font-mono" placeholder="+91XXXXXXXXXX" />
                        </div>
                    </div>
                    <div class="flex justify-end mt-4">
                        <button @click="saveOwner" :disabled="ownerForm.processing"
                            class="btn btn-primary btn-sm flex items-center gap-1.5 cursor-pointer">
                            <Save :size="12" /> {{ ownerForm.processing ? 'Saving…' : 'Save Owner' }}
                        </button>
                    </div>
                </div>

                <!-- All users -->
                <div class="card" v-if="users.length > 1">
                    <h3 class="text-[13px] font-semibold text-white mb-3">All Users ({{ users.length }})</h3>
                    <div class="space-y-2">
                        <div v-for="u in users" :key="u.id"
                            class="flex items-center gap-3 py-2 border-b border-frost-1 last:border-0 text-[12px]">
                            <div class="w-7 h-7 rounded-full bg-brand-600/20 flex items-center justify-center text-[10px] font-bold text-brand-300 shrink-0">
                                {{ u.name?.charAt(0)?.toUpperCase() }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-white font-medium truncate">{{ u.name }}</div>
                                <div class="text-ink-3 text-[10px]">{{ u.email }} · {{ u.phone || 'No phone' }}</div>
                            </div>
                            <div class="text-[10px] text-ink-3">{{ u.created_at ? new Date(u.created_at).toLocaleDateString('en-IN') : '' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right col: subscription + meta -->
            <div class="space-y-4">

                <!-- Subscription -->
                <div class="card">
                    <div class="flex items-center gap-2 mb-4">
                        <CreditCard :size="15" class="text-brand-400" />
                        <h3 class="text-[14px] font-semibold text-white">Subscription</h3>
                    </div>

                    <div v-if="subscription" class="space-y-2 mb-4">
                        <div class="flex justify-between text-[12px]">
                            <span class="text-ink-3">Plan</span>
                            <span class="font-semibold" :class="planColor(subscription.plan?.slug)">{{ subscription.plan?.name }}</span>
                        </div>
                        <div class="flex justify-between text-[12px]">
                            <span class="text-ink-3">Status</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] bg-emerald-500/15 text-emerald-400">{{ subscription.status }}</span>
                        </div>
                        <div class="flex justify-between text-[12px]">
                            <span class="text-ink-3">Amount</span>
                            <span class="text-white">{{ fmt(subscription.final_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-[12px]">
                            <span class="text-ink-3">Cycle</span>
                            <span class="text-ink-2 capitalize">{{ subscription.billing_cycle }}</span>
                        </div>
                        <div class="flex justify-between text-[12px]">
                            <span class="text-ink-3">Renews</span>
                            <span class="text-ink-2">{{ subscription.ends_at ? new Date(subscription.ends_at).toLocaleDateString('en-IN') : '—' }}</span>
                        </div>
                    </div>
                    <div v-else class="text-[12px] text-ink-3 mb-4">No active subscription — on Free plan.</div>

                    <!-- Override plan -->
                    <div class="border-t border-frost-1 pt-3">
                        <label class="heyd2c-label">Override Plan</label>
                        <div class="flex gap-2 mt-1">
                            <select v-model="selectedPlan" class="heyd2c-input flex-1 text-[12px]">
                                <option value="">Select plan…</option>
                                <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                            <button @click="setPlan" :disabled="!selectedPlan"
                                class="btn btn-ghost btn-sm shrink-0 cursor-pointer disabled:opacity-50">
                                <RefreshCw :size="12" />
                            </button>
                        </div>
                        <p class="text-[10px] text-ink-3 mt-1">Manually set plan — no payment required</p>
                    </div>
                </div>

                <!-- Meta info -->
                <div class="card text-[12px] space-y-2">
                    <h3 class="text-[13px] font-semibold text-white mb-3">Meta</h3>
                    <div class="flex justify-between">
                        <span class="text-ink-3">ID</span>
                        <span class="font-mono text-[10px] text-ink-2">{{ company.id?.slice(0,16) }}…</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-3">Slug</span>
                        <span class="font-mono text-ink-2">{{ company.slug }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-3">Orders</span>
                        <span class="text-white font-semibold">{{ (company.order_count || 0).toLocaleString('en-IN') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-3">Users</span>
                        <span class="text-ink-2">{{ users.length }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-3">Created</span>
                        <span class="text-ink-3">{{ company.created_at ? new Date(company.created_at).toLocaleDateString('en-IN') : '—' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-3">WooCommerce</span>
                        <span :class="company.woo_connected_at ? 'text-emerald-400' : 'text-ink-3'">
                            {{ company.woo_connected_at ? 'Connected' : 'Not connected' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
