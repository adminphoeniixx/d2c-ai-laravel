<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, MessageSquare, AlertCircle, Clock, CheckCircle, XCircle } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    tickets: { type: Object, default: () => ({ data: [] }) },
    counts: { type: Object, default: () => ({}) },
    status: { type: String, default: 'open' },
    categories: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showCreate = ref(false);
const form = useForm({
    subject: '', description: '', priority: 'medium', category_id: '',
    customer_name: '', customer_email: '', customer_phone: '', order_number: '', source: 'portal',
});

function filterStatus(s) { router.get(route('tenant.support.index', { tenant: slug }), { status: s }, { preserveState: true }); }
function submit() {
    form.post(route('tenant.support.store', { tenant: slug }), { onSuccess: () => { showCreate.value = false; form.reset(); } });
}

const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';
const statusIcons = { open: MessageSquare, in_progress: Clock, awaiting_reply: AlertCircle, resolved: CheckCircle, closed: XCircle };
const statusColors = {
    open: 'bg-blue-500/20 text-blue-400', in_progress: 'bg-amber-500/20 text-amber-400',
    awaiting_reply: 'bg-purple-500/20 text-purple-400', resolved: 'bg-emerald-500/20 text-emerald-400', closed: 'bg-ink-3/20 text-ink-3',
};
const priorityColors = { low: 'text-ink-3', medium: 'text-blue-400', high: 'text-amber-400', urgent: 'text-rose-400' };
</script>

<template>
<Head title="Support Tickets" />
<TenantLayout>
    <div class="max-w-4xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white">Support Tickets</h2>
                <p class="text-[12px] text-ink-3 mt-1">Manage customer inquiries and issues</p>
            </div>
            <button @click="showCreate = true" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer"><Plus :size="14" /> New Ticket</button>
        </div>

        <!-- Status tabs -->
        <div class="flex gap-1 mb-4 overflow-x-auto pb-1 flex-wrap">
            <button v-for="s in ['open', 'in_progress', 'awaiting_reply', 'resolved', 'closed', 'all']" :key="s" @click="filterStatus(s)"
                class="px-3 py-1.5 rounded-lg text-[11px] font-medium transition cursor-pointer whitespace-nowrap"
                :class="status === s ? 'bg-brand-600/20 text-brand-300 border border-brand-600/40' : 'text-ink-3 hover:text-ink-2 border border-transparent'">
                {{ s.replace('_', ' ') }}
                <span v-if="s !== 'all' && counts[s]" class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] bg-bg-3">{{ counts[s] }}</span>
            </button>
            <span v-if="counts.sla_breached" class="ml-2 px-2 py-1.5 rounded-lg text-[11px] font-bold bg-rose-500/20 text-rose-400 border border-rose-500/30">
                {{ counts.sla_breached }} SLA Breached
            </span>
        </div>

        <!-- Tickets list -->
        <div class="card overflow-hidden">
            <div v-for="t in tickets.data" :key="t.id"
                class="px-4 py-3 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition cursor-pointer"
                @click="router.visit(route('tenant.support.show', { tenant: slug, ticket: t.id }))">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-[10px] font-mono text-ink-3">{{ t.ticket_number }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold" :class="statusColors[t.status]">{{ t.status.replace('_', ' ') }}</span>
                            <span class="text-[10px] font-bold" :class="priorityColors[t.priority]">{{ t.priority }}</span>
                            <span v-if="t.sla_breached" class="text-[9px] text-rose font-bold">SLA!</span>
                        </div>
                        <div class="text-[13px] font-medium text-white truncate">{{ t.subject }}</div>
                        <div class="text-[11px] text-ink-3 mt-0.5">
                            {{ t.customer_name }} · {{ t.customer_email }}
                            <span v-if="t.category">· {{ t.category.name }}</span>
                        </div>
                    </div>
                    <div class="text-[10px] text-ink-3 text-right flex-shrink-0">
                        {{ dateFmt(t.created_at) }}
                    </div>
                </div>
            </div>
            <div v-if="!tickets.data?.length" class="px-4 py-8 text-center text-[13px] text-ink-3">No tickets</div>
        </div>

        <!-- Create Modal -->
        <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showCreate = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-lg mx-4 p-5 max-h-[90vh] overflow-y-auto">
                <h3 class="text-[16px] font-bold text-white mb-4">New Support Ticket</h3>
                <form @submit.prevent="submit" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="heyd2c-label">Customer Name</label>
                            <input v-model="form.customer_name" class="heyd2c-input" required />
                        </div>
                        <div>
                            <label class="heyd2c-label">Email</label>
                            <input v-model="form.customer_email" type="email" class="heyd2c-input" required />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="heyd2c-label">Phone</label>
                            <input v-model="form.customer_phone" class="heyd2c-input" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Order #</label>
                            <input v-model="form.order_number" class="heyd2c-input" placeholder="Optional" />
                        </div>
                    </div>
                    <div>
                        <label class="heyd2c-label">Subject</label>
                        <input v-model="form.subject" class="heyd2c-input" required />
                    </div>
                    <div>
                        <label class="heyd2c-label">Description</label>
                        <textarea v-model="form.description" class="heyd2c-input" rows="3" required></textarea>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="heyd2c-label">Category</label>
                            <select v-model="form.category_id" class="heyd2c-input">
                                <option value="">Select…</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="heyd2c-label">Priority</label>
                            <select v-model="form.priority" class="heyd2c-input">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label class="heyd2c-label">Source</label>
                            <select v-model="form.source" class="heyd2c-input">
                                <option value="portal">Portal</option>
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                                <option value="whatsapp">WhatsApp</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1" :disabled="form.processing">Create Ticket</button>
                        <button type="button" class="btn btn-ghost flex-1" @click="showCreate = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
