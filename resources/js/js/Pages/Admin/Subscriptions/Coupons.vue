<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Plus, Trash2, Tag } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ coupons: Object, plans: Array })
const showCreate = ref(false)

const form = ref({
    code: '', type: 'percent', value: '', max_discount: '',
    usage_limit: '', per_user_limit: 1, is_active: true,
    first_time_only: false, applicable_plans: [], valid_from: '', valid_until: '',
})

function create() {
    router.post(route('admin.subscriptions.coupons.store'), form.value, {
        onSuccess: () => { showCreate.value = false; form.value = { ...form.value, code: '', value: '' } }
    })
}

function toggleActive(c) {
    router.patch(route('admin.subscriptions.coupons.update', c.id), { is_active: !c.is_active })
}

function deleteCoupon(c) {
    if (!confirm('Delete coupon ' + c.code + '?')) return
    router.delete(route('admin.subscriptions.coupons.destroy', c.id))
}
</script>

<template>
<Head title="Coupons" />
<AdminLayout>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h1 class="text-[20px] font-bold text-white">Coupons</h1>
            <div class="flex gap-2">
                <a :href="route('admin.subscriptions.dashboard')" class="btn btn-ghost btn-sm cursor-pointer">← Dashboard</a>
                <button @click="showCreate = true" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer">
                    <Plus :size="12" /> New Coupon
                </button>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="w-full text-[12px]">
                <thead><tr class="border-b border-frost-1">
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium">Code</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium">Type</th>
                    <th class="text-right px-5 py-3 text-[10px] text-ink-3 font-medium">Value</th>
                    <th class="text-right px-5 py-3 text-[10px] text-ink-3 font-medium">Used</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium">Valid Until</th>
                    <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium">Status</th>
                    <th class="px-5 py-3"></th>
                </tr></thead>
                <tbody>
                    <tr v-for="c in coupons.data" :key="c.id" class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition">
                        <td class="px-5 py-3 font-mono text-brand-300 font-bold">{{ c.code }}</td>
                        <td class="px-5 py-3 text-ink-2 capitalize">{{ c.type }}</td>
                        <td class="px-5 py-3 text-right text-white font-medium">
                            {{ c.type === 'percent' ? c.value + '%' : '₹' + c.value }}
                        </td>
                        <td class="px-5 py-3 text-right text-ink-2">
                            {{ c.usages_count }}<span v-if="c.usage_limit" class="text-ink-3"> / {{ c.usage_limit }}</span>
                        </td>
                        <td class="px-5 py-3 text-ink-3">{{ c.valid_until ? new Date(c.valid_until).toLocaleDateString('en-IN') : '∞' }}</td>
                        <td class="px-5 py-3">
                            <button @click="toggleActive(c)" class="cursor-pointer">
                                <span :class="c.is_active ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-500/15 text-slate-400'"
                                    class="px-2 py-0.5 rounded-full text-[10px]">{{ c.is_active ? 'Active' : 'Inactive' }}</span>
                            </button>
                        </td>
                        <td class="px-5 py-3">
                            <button @click="deleteCoupon(c)" class="text-rose-400 hover:text-rose-300 cursor-pointer"><Trash2 :size="13" /></button>
                        </td>
                    </tr>
                    <tr v-if="!coupons.data?.length">
                        <td colspan="7" class="px-5 py-8 text-center text-ink-3">No coupons yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70" @click.self="showCreate = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md mx-4 p-5">
                <h3 class="text-[15px] font-bold text-white mb-4">Create Coupon</h3>
                <div class="space-y-3">
                    <div><label class="heyd2c-label">Coupon Code</label>
                    <input v-model="form.code" class="heyd2c-input font-mono uppercase" placeholder="e.g. LAUNCH50" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><label class="heyd2c-label">Type</label>
                        <select v-model="form.type" class="heyd2c-input">
                            <option value="percent">Percent (%)</option>
                            <option value="flat">Flat (₹)</option>
                        </select></div>
                        <div><label class="heyd2c-label">Value</label>
                        <input v-model.number="form.value" type="number" class="heyd2c-input" /></div>
                        <div><label class="heyd2c-label">Max Discount (₹)</label>
                        <input v-model="form.max_discount" type="number" class="heyd2c-input" placeholder="optional" /></div>
                        <div><label class="heyd2c-label">Total Usage Limit</label>
                        <input v-model="form.usage_limit" type="number" class="heyd2c-input" placeholder="blank = unlimited" /></div>
                        <div><label class="heyd2c-label">Valid From</label>
                        <input v-model="form.valid_from" type="date" class="heyd2c-input" /></div>
                        <div><label class="heyd2c-label">Valid Until</label>
                        <input v-model="form.valid_until" type="date" class="heyd2c-input" /></div>
                    </div>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="form.first_time_only" />
                            <span class="text-[12px] text-ink-2">First subscription only</span>
                        </label>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button @click="create" class="btn btn-primary flex-1 cursor-pointer">Create</button>
                        <button @click="showCreate = false" class="btn btn-ghost flex-1 cursor-pointer">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
