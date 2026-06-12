<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ plans: Array })
const editing = ref(null)

function edit(plan) { editing.value = { ...plan } }
function save() {
    router.put(route('admin.subscriptions.plans.update', editing.value.id), editing.value, {
        onSuccess: () => { editing.value = null }
    })
}

const fmt = (n) => n === -1 ? 'Unlimited' : Number(n).toLocaleString('en-IN')
</script>

<template>
<Head title="Plans" />
<AdminLayout>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-[20px] font-bold text-white">Subscription Plans</h1>
            <a :href="route('admin.subscriptions.dashboard')" class="btn btn-ghost btn-sm cursor-pointer">← Dashboard</a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div v-for="plan in plans" :key="plan.id" class="card">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h3 class="text-[15px] font-bold text-white">{{ plan.name }}</h3>
                        <p class="text-[11px] text-ink-3 mt-0.5">{{ plan.subscriptions_count }} active subscribers</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="plan.is_active ? 'bg-emerald-500/15 text-emerald-400' : 'bg-rose-500/15 text-rose-400'"
                            class="px-2 py-0.5 rounded-full text-[10px]">{{ plan.is_active ? 'Active' : 'Inactive' }}</span>
                        <span v-if="plan.is_featured" class="bg-amber-500/15 text-amber-400 px-2 py-0.5 rounded-full text-[10px]">Featured</span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-[11px] mb-3">
                    <div class="bg-frost-1 rounded-lg p-2">
                        <div class="text-ink-3">Monthly</div>
                        <div class="text-white font-bold text-[14px]">₹{{ plan.price.toLocaleString('en-IN') }}</div>
                    </div>
                    <div class="bg-frost-1 rounded-lg p-2">
                        <div class="text-ink-3">Yearly</div>
                        <div class="text-white font-bold text-[14px]">₹{{ plan.price_yearly.toLocaleString('en-IN') }}</div>
                    </div>
                    <div class="bg-frost-1 rounded-lg p-2">
                        <div class="text-ink-3">Orders</div>
                        <div class="text-ink-2">{{ fmt(plan.order_limit) }}</div>
                    </div>
                    <div class="bg-frost-1 rounded-lg p-2">
                        <div class="text-ink-3">Stores</div>
                        <div class="text-ink-2">{{ fmt(plan.store_limit) }}</div>
                    </div>
                    <div class="bg-frost-1 rounded-lg p-2">
                        <div class="text-ink-3">Team</div>
                        <div class="text-ink-2">{{ fmt(plan.team_member_limit) }}</div>
                    </div>
                    <div class="bg-frost-1 rounded-lg p-2">
                        <div class="text-ink-3">Data History</div>
                        <div class="text-ink-2">{{ plan.data_history_days === -1 ? 'Unlimited' : plan.data_history_days + 'd' }}</div>
                    </div>
                </div>

                <button @click="edit(plan)" class="btn btn-ghost btn-sm w-full cursor-pointer">Edit Plan</button>
            </div>
        </div>

        <!-- Edit modal -->
        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" @click.self="editing = null">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-lg mx-4 p-5 max-h-[90vh] overflow-y-auto">
                <h3 class="text-[15px] font-bold text-white mb-4">Edit {{ editing.name }}</h3>
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="heyd2c-label">Monthly Price (₹)</label>
                        <input v-model.number="editing.price" type="number" class="heyd2c-input" /></div>
                        <div><label class="heyd2c-label">Yearly Price (₹)</label>
                        <input v-model.number="editing.price_yearly" type="number" class="heyd2c-input" /></div>
                        <div><label class="heyd2c-label">Order Limit (-1=∞)</label>
                        <input v-model.number="editing.order_limit" type="number" class="heyd2c-input" /></div>
                        <div><label class="heyd2c-label">Store Limit (-1=∞)</label>
                        <input v-model.number="editing.store_limit" type="number" class="heyd2c-input" /></div>
                        <div><label class="heyd2c-label">Team Limit (-1=∞)</label>
                        <input v-model.number="editing.team_member_limit" type="number" class="heyd2c-input" /></div>
                        <div><label class="heyd2c-label">Data History Days (-1=∞)</label>
                        <input v-model.number="editing.data_history_days" type="number" class="heyd2c-input" /></div>
                        <div><label class="heyd2c-label">Per Order Charge (₹)</label>
                        <input v-model.number="editing.per_order_charge" type="number" step="0.01" class="heyd2c-input" /></div>
                    </div>
                    <div><label class="heyd2c-label">Razorpay Plan ID (Live)</label>
                    <input v-model="editing.razorpay_plan_id" class="heyd2c-input font-mono" /></div>
                    <div><label class="heyd2c-label">Razorpay Plan ID (Test)</label>
                    <input v-model="editing.razorpay_plan_id_test" class="heyd2c-input font-mono" /></div>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="editing.is_active" class="rounded" />
                            <span class="text-[12px] text-ink-2">Active</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="editing.is_featured" class="rounded" />
                            <span class="text-[12px] text-ink-2">Featured (Most Popular)</span>
                        </label>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button @click="save" class="btn btn-primary flex-1 cursor-pointer">Save Changes</button>
                        <button @click="editing = null" class="btn btn-ghost flex-1 cursor-pointer">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
