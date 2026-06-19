<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { CreditCard, Plus, Pencil, Trash2, X, Server, MessageSquare, Mail, Smartphone, Wrench, BarChart3, MoreHorizontal } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    subscriptions: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ monthly_total: 0, yearly_total: 0, active_count: 0, by_category: {} }) },
    categories: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));

const CATEGORY_ICONS = {
    hosting: Server,
    messaging: MessageSquare,
    email: Mail,
    sms: Smartphone,
    software: Wrench,
    analytics: BarChart3,
    other: MoreHorizontal,
};

const showModal = ref(false);
const editingId = ref(null);

const emptyForm = () => ({
    name: '',
    provider: '',
    category: 'hosting',
    amount: '',
    billing_cycle: 'monthly',
    next_billing_date: '',
    status: 'active',
    notes: '',
});

const form = useForm(emptyForm());

function openAdd() {
    editingId.value = null;
    form.defaults(emptyForm());
    form.reset();
    showModal.value = true;
}

function openEdit(sub) {
    editingId.value = sub.id;
    form.defaults({
        name: sub.name || '',
        provider: sub.provider || '',
        category: sub.category || 'hosting',
        amount: sub.amount || '',
        billing_cycle: sub.billing_cycle || 'monthly',
        next_billing_date: sub.next_billing_date || '',
        status: sub.status || 'active',
        notes: sub.notes || '',
    });
    form.reset();
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingId.value = null;
}

function submit() {
    if (editingId.value) {
        form.put(`/app/${slug}/saas-subscriptions/${editingId.value}`, {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    } else {
        form.post(`/app/${slug}/saas-subscriptions`, {
            preserveScroll: true,
            onSuccess: closeModal,
        });
    }
}

function destroy(sub) {
    if (!confirm(`Remove "${sub.name}"?`)) return;
    router.delete(`/app/${slug}/saas-subscriptions/${sub.id}`, { preserveScroll: true });
}

function billingLabel(sub) {
    if (sub.billing_cycle === 'monthly') return '/month';
    if (sub.billing_cycle === 'yearly') return '/year';
    return 'one-time';
}

function daysUntil(dateStr) {
    if (!dateStr) return null;
    const diff = Math.ceil((new Date(dateStr) - new Date()) / (1000 * 60 * 60 * 24));
    return diff;
}

function dueBadge(sub) {
    if (sub.status !== 'active' || !sub.next_billing_date) return null;
    const days = daysUntil(sub.next_billing_date);
    if (days === null) return null;
    if (days < 0) return { label: 'Overdue', class: 'bg-rose-500/15 text-rose-400' };
    if (days <= 3) return { label: `Due in ${days}d`, class: 'bg-amber-500/15 text-amber-400' };
    if (days <= 7) return { label: `Due in ${days}d`, class: 'bg-frost-1 text-ink-3' };
    return null;
}

const statusBadge = (status) => ({
    active: 'bg-emerald-500/15 text-emerald-400',
    paused: 'bg-amber-500/15 text-amber-400',
    cancelled: 'bg-frost-1 text-ink-3',
}[status] || 'bg-frost-1 text-ink-3');

const categoryEntries = computed(() => Object.entries(props.totals.by_category || {})
    .sort((a, b) => b[1] - a[1]));
</script>

<template>
<Head title="SaaS Subscriptions" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <h2 class="text-[20px] font-bold text-white">SaaS Subscriptions</h2>
            <p class="text-[12px] text-ink-3 mt-1">Recurring costs — hosting, messaging, email/SMS providers, and tools</p>
        </div>
        <button class="btn btn-primary cursor-pointer flex items-center gap-1.5" @click="openAdd">
            <Plus :size="14" /> Add Subscription
        </button>
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="kpi-card">
            <div class="kpi-label">Monthly Total</div>
            <div class="kpi-value">{{ fmt(totals.monthly_total) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Yearly Total</div>
            <div class="kpi-value">{{ fmt(totals.yearly_total) }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Active Subscriptions</div>
            <div class="kpi-value">{{ totals.active_count }}</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Top Category</div>
            <div class="kpi-value text-[16px]" v-if="categoryEntries.length">
                {{ categories[categoryEntries[0][0]] || categoryEntries[0][0] }}
            </div>
            <div class="kpi-value text-[16px] text-ink-3" v-else>—</div>
        </div>
    </div>

    <!-- By category breakdown -->
    <div v-if="categoryEntries.length" class="card mb-5">
        <h3 class="text-[13px] font-semibold text-white mb-3">Monthly Spend by Category</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            <div v-for="[cat, amount] in categoryEntries" :key="cat" class="rounded-xl border border-frost-1 bg-surface-2 p-3">
                <div class="flex items-center gap-2 mb-1">
                    <component :is="CATEGORY_ICONS[cat] || MoreHorizontal" :size="13" class="text-brand-400" />
                    <span class="text-[11px] text-ink-3">{{ categories[cat] || cat }}</span>
                </div>
                <div class="text-[15px] font-bold text-white">{{ fmt(amount) }}</div>
            </div>
        </div>
    </div>

    <!-- Subscriptions list -->
    <div v-if="subscriptions.length" class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3">
                <tr>
                    <th class="text-left px-5 py-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">Service</th>
                    <th class="text-left px-5 py-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">Category</th>
                    <th class="text-right px-5 py-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">Cost</th>
                    <th class="text-left px-5 py-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">Next Billing</th>
                    <th class="text-left px-5 py-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">Status</th>
                    <th class="px-5 py-3 w-20"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="sub in subscriptions" :key="sub.id" class="border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2.5">
                            <div class="h-7 w-7 rounded-lg bg-brand-600/15 flex items-center justify-center flex-shrink-0">
                                <component :is="CATEGORY_ICONS[sub.category] || MoreHorizontal" :size="13" class="text-brand-300" />
                            </div>
                            <div>
                                <div class="font-medium text-white">{{ sub.name }}</div>
                                <div v-if="sub.provider" class="text-[11px] text-ink-3">{{ sub.provider }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-ink-2">{{ categories[sub.category] || sub.category }}</td>
                    <td class="px-5 py-3 text-right">
                        <div class="text-white font-medium">{{ fmt(sub.amount) }}</div>
                        <div class="text-[10px] text-ink-3">{{ billingLabel(sub) }}</div>
                    </td>
                    <td class="px-5 py-3">
                        <div class="text-ink-2 text-[12px]">{{ sub.next_billing_date || '—' }}</div>
                        <span v-if="dueBadge(sub)" class="inline-block mt-1 text-[9px] px-1.5 py-0.5 rounded-full" :class="dueBadge(sub).class">
                            {{ dueBadge(sub).label }}
                        </span>
                    </td>
                    <td class="px-5 py-3">
                        <span class="text-[10px] px-2 py-0.5 rounded-full capitalize" :class="statusBadge(sub.status)">{{ sub.status }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2.5 justify-end">
                            <button @click="openEdit(sub)" class="text-ink-3 hover:text-brand-300 cursor-pointer transition" title="Edit">
                                <Pencil :size="13" />
                            </button>
                            <button @click="destroy(sub)" class="text-ink-3 hover:text-rose-400 cursor-pointer transition" title="Remove">
                                <Trash2 :size="13" />
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div v-else class="card text-center py-12">
        <CreditCard :size="28" class="text-ink-3 mx-auto mb-3" />
        <p class="text-[13px] text-white font-medium mb-1">No subscriptions yet</p>
        <p class="text-[12px] text-ink-3 mb-4">Track recurring costs like hosting, WhatsApp/Interakt, email & SMS providers, and other SaaS tools.</p>
        <button class="btn btn-primary cursor-pointer" @click="openAdd">Add Subscription</button>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="closeModal">
        <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-md p-6 space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between">
                <h3 class="text-[15px] font-bold text-white">{{ editingId ? 'Edit' : 'Add' }} Subscription</h3>
                <button @click="closeModal" class="text-ink-3 hover:text-white cursor-pointer transition">
                    <X :size="16" />
                </button>
            </div>

            <div>
                <label class="heyd2c-label">Service Name</label>
                <input v-model="form.name" class="heyd2c-input" placeholder="e.g. Easypanel VPS, Interakt" />
                <p v-if="form.errors.name" class="text-rose-400 text-[11px] mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="heyd2c-label">Provider (optional)</label>
                <input v-model="form.provider" class="heyd2c-input" placeholder="e.g. DigitalOcean, Interakt" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Category</label>
                    <select v-model="form.category" class="heyd2c-input">
                        <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
                <div>
                    <label class="heyd2c-label">Status</label>
                    <select v-model="form.status" class="heyd2c-input">
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="heyd2c-label">Amount (₹)</label>
                    <input v-model="form.amount" type="number" step="0.01" min="0" class="heyd2c-input" />
                    <p v-if="form.errors.amount" class="text-rose-400 text-[11px] mt-1">{{ form.errors.amount }}</p>
                </div>
                <div>
                    <label class="heyd2c-label">Billing Cycle</label>
                    <select v-model="form.billing_cycle" class="heyd2c-input">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                        <option value="one_time">One-time</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="heyd2c-label">Next Billing Date (optional)</label>
                <input v-model="form.next_billing_date" type="date" class="heyd2c-input" />
            </div>

            <div>
                <label class="heyd2c-label">Notes (optional)</label>
                <textarea v-model="form.notes" class="heyd2c-input" rows="2"></textarea>
            </div>

            <div class="flex gap-2">
                <button @click="submit" :disabled="form.processing" class="btn btn-primary flex-1 cursor-pointer disabled:opacity-50">
                    {{ form.processing ? 'Saving…' : (editingId ? 'Save Changes' : 'Add Subscription') }}
                </button>
                <button @click="closeModal" class="btn btn-ghost flex-1 cursor-pointer">Cancel</button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
