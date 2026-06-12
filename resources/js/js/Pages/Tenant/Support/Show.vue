<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Send, Lock, User, Bot, Clock } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    ticket: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    agents: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const replyForm = useForm({ body: '', is_internal_note: false });
const statusForm = useForm({ status: props.ticket.status, assigned_to: props.ticket.assigned_to || '' });

function submitReply() {
    replyForm.post(route('tenant.support.reply', { tenant: slug, ticket: props.ticket.id }), {
        onSuccess: () => replyForm.reset(),
    });
}
function updateStatus() {
    statusForm.put(route('tenant.support.update-status', { tenant: slug, ticket: props.ticket.id }));
}

const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—';
const senderIcons = { customer: User, agent: User, bot: Bot };
const senderColors = { customer: 'bg-blue-500/20 border-blue-500/30', agent: 'bg-brand-600/20 border-brand-600/30', bot: 'bg-amber-500/20 border-amber-500/30' };
const priorityColors = { low: 'text-ink-3', medium: 'text-blue-400', high: 'text-amber-400', urgent: 'text-rose-400' };
</script>

<template>
<Head :title="ticket.ticket_number" />
<TenantLayout>
    <div class="max-w-3xl">
        <!-- Header -->
        <div class="flex items-center gap-3 mb-5">
            <button @click="router.visit(route('tenant.support.index', { tenant: slug }))" class="text-ink-3 hover:text-white cursor-pointer"><ArrowLeft :size="18" /></button>
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <span class="text-[12px] font-mono text-ink-3">{{ ticket.ticket_number }}</span>
                    <span class="text-[10px] font-bold" :class="priorityColors[ticket.priority]">{{ ticket.priority }}</span>
                    <span v-if="ticket.sla_breached" class="text-[9px] text-rose font-bold px-1.5 py-0.5 rounded bg-rose-500/20">SLA BREACHED</span>
                </div>
                <h2 class="text-[18px] font-bold text-white">{{ ticket.subject }}</h2>
                <div class="text-[11px] text-ink-3 mt-0.5">
                    {{ ticket.customer_name }} · {{ ticket.customer_email }}
                    <span v-if="ticket.order_number"> · Order: {{ ticket.order_number }}</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <!-- Reply Thread -->
            <div class="col-span-2 space-y-3">
                <!-- Original description -->
                <div class="card">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="h-7 w-7 rounded-full bg-blue-500/20 flex items-center justify-center"><User :size="12" class="text-blue-400" /></div>
                        <div>
                            <span class="text-[12px] font-medium text-white">{{ ticket.customer_name }}</span>
                            <span class="text-[10px] text-ink-3 ml-2">{{ dateFmt(ticket.created_at) }}</span>
                        </div>
                    </div>
                    <div class="text-[13px] text-ink-2 whitespace-pre-wrap">{{ ticket.description }}</div>
                </div>

                <!-- Replies -->
                <div v-for="r in ticket.replies" :key="r.id"
                    class="card"
                    :class="r.is_internal_note ? '!border-amber-500/30 !bg-amber-500/5' : ''">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="h-7 w-7 rounded-full flex items-center justify-center" :class="senderColors[r.sender_type]">
                            <component :is="senderIcons[r.sender_type]" :size="12" class="text-white/70" />
                        </div>
                        <div>
                            <span class="text-[12px] font-medium text-white">{{ r.sender_name }}</span>
                            <span v-if="r.sender_type === 'bot'" class="text-[9px] text-amber-400 ml-1">BOT</span>
                            <span v-if="r.is_internal_note" class="text-[9px] text-amber-400 ml-1 flex items-center gap-0.5"><Lock :size="8" /> Internal Note</span>
                            <span class="text-[10px] text-ink-3 ml-2">{{ dateFmt(r.created_at) }}</span>
                        </div>
                    </div>
                    <div class="text-[13px] text-ink-2 whitespace-pre-wrap">{{ r.body }}</div>
                </div>

                <!-- Reply Form -->
                <div class="card">
                    <form @submit.prevent="submitReply">
                        <textarea v-model="replyForm.body" class="heyd2c-input mb-3" rows="3" placeholder="Type your reply…" required></textarea>
                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 text-[11px] text-ink-3 cursor-pointer select-none">
                                <input v-model="replyForm.is_internal_note" type="checkbox" class="accent-brand-600" />
                                <Lock :size="11" /> Internal note (customer won't see this)
                            </label>
                            <button type="submit" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer" :disabled="replyForm.processing">
                                <Send :size="12" /> {{ replyForm.is_internal_note ? 'Add Note' : 'Send Reply' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-3">
                <div class="card">
                    <h4 class="text-[12px] font-semibold text-ink-3 uppercase tracking-widest mb-3">Ticket Details</h4>
                    <form @submit.prevent="updateStatus" class="space-y-2">
                        <div>
                            <label class="heyd2c-label">Status</label>
                            <select v-model="statusForm.status" class="heyd2c-input">
                                <option value="open">Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="awaiting_reply">Awaiting Reply</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div>
                            <label class="heyd2c-label">Assign To</label>
                            <select v-model="statusForm.assigned_to" class="heyd2c-input">
                                <option value="">Unassigned</option>
                                <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-full cursor-pointer" :disabled="statusForm.processing">Update</button>
                    </form>
                </div>

                <div class="card">
                    <h4 class="text-[12px] font-semibold text-ink-3 uppercase tracking-widest mb-2">Timeline</h4>
                    <div class="space-y-1.5 text-[11px]">
                        <div class="flex justify-between"><span class="text-ink-3">Created</span><span class="text-ink-2">{{ dateFmt(ticket.created_at) }}</span></div>
                        <div v-if="ticket.assigned_at" class="flex justify-between"><span class="text-ink-3">Assigned</span><span class="text-ink-2">{{ dateFmt(ticket.assigned_at) }}</span></div>
                        <div v-if="ticket.first_responded_at" class="flex justify-between"><span class="text-ink-3">First Reply</span><span class="text-ink-2">{{ dateFmt(ticket.first_responded_at) }}</span></div>
                        <div v-if="ticket.resolved_at" class="flex justify-between"><span class="text-ink-3">Resolved</span><span class="text-emerald-400">{{ dateFmt(ticket.resolved_at) }}</span></div>
                        <div class="flex justify-between"><span class="text-ink-3">SLA</span><span :class="ticket.sla_breached ? 'text-rose' : 'text-emerald-400'">{{ ticket.sla_hours }}h {{ ticket.sla_breached ? '(breached)' : '' }}</span></div>
                    </div>
                </div>

                <div class="card">
                    <h4 class="text-[12px] font-semibold text-ink-3 uppercase tracking-widest mb-2">Category</h4>
                    <p class="text-[13px] text-ink-2">{{ ticket.category?.name || 'Uncategorized' }}</p>
                    <p class="text-[11px] text-ink-3 mt-0.5">Source: {{ ticket.source }}</p>
                </div>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
