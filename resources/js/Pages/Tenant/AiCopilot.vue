<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, nextTick, computed } from 'vue';
import { Bot, Send, Sparkles, Loader2, Plus, Trash2, MessageSquare, ChevronDown, ChevronUp, Database, Zap } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    conversations: { type: Array, default: () => [] },
    suggestions:   { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const conversationList = ref([...props.conversations]);
const activeConversationId = ref(null);
const messages = ref([
    { role: 'assistant', content: "Hi! I'm your AI Copilot. I can answer questions about your orders, expenses, P&L, inventory, ads, banking, logistics, and more — using your company's own data. Try one of the suggestions below or ask anything." },
]);
const input = ref('');
const loading = ref(false);
const expandedSql = ref({}); // message id -> bool
const scrollArea = ref(null);

function scrollToBottom() {
    nextTick(() => {
        if (scrollArea.value) scrollArea.value.scrollTop = scrollArea.value.scrollHeight;
    });
}

function csrfToken() {
    const token = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
    return token ? decodeURIComponent(token) : '';
}

function newChat() {
    activeConversationId.value = null;
    messages.value = [
        { role: 'assistant', content: "Hi! I'm your AI Copilot. Ask me anything about your business — orders, expenses, P&L, inventory, ads, banking, logistics, payroll, and more." },
    ];
    input.value = '';
}

async function openConversation(conv) {
    activeConversationId.value = conv.id;
    try {
        const res = await fetch(`/app/${slug}/ai/conversations/${conv.id}`, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await res.json();
        messages.value = (data.messages || []).map(m => ({
            id: m.id,
            role: m.role,
            content: m.content,
            sql: m.sql,
            meta: m.meta,
        }));
        scrollToBottom();
    } catch (e) {
        messages.value = [{ role: 'assistant', content: 'Could not load this conversation. Please try again.' }];
    }
}

async function deleteConversation(conv) {
    if (!confirm('Delete this conversation?')) return;
    try {
        await fetch(`/app/${slug}/ai/conversations/${conv.id}`, {
            method: 'DELETE',
            headers: { 'X-XSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
        });
        conversationList.value = conversationList.value.filter(c => c.id !== conv.id);
        if (activeConversationId.value === conv.id) newChat();
    } catch (e) {
        // ignore
    }
}

async function sendMessage(text) {
    const msg = (text || input.value).trim();
    if (!msg || loading.value) return;

    messages.value.push({ role: 'user', content: msg });
    input.value = '';
    loading.value = true;
    scrollToBottom();

    try {
        const res = await fetch(`/app/${slug}/ai/prompt`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                prompt: msg,
                conversation_id: activeConversationId.value,
            }),
        });

        if (!res.ok) throw new Error('Request failed');

        const data = await res.json();

        messages.value.push({
            id: data.message.id,
            role: 'assistant',
            content: data.message.content,
            sql: data.message.sql,
            meta: data.message.meta,
        });

        // New conversation? add to sidebar list
        if (!activeConversationId.value) {
            activeConversationId.value = data.conversation_id;
            conversationList.value.unshift({
                id: data.conversation_id,
                title: data.title,
                updated_at: new Date().toISOString(),
            });
        } else {
            // Move conversation to top
            const idx = conversationList.value.findIndex(c => c.id === activeConversationId.value);
            if (idx > 0) {
                const [conv] = conversationList.value.splice(idx, 1);
                conversationList.value.unshift(conv);
            }
        }
    } catch (e) {
        messages.value.push({
            role: 'assistant',
            content: "Sorry, I couldn't process that just now. Please try again.",
        });
    } finally {
        loading.value = false;
        scrollToBottom();
    }
}

function toggleSql(id) {
    expandedSql.value = { ...expandedSql.value, [id]: !expandedSql.value[id] };
}

const showSuggestions = computed(() => messages.value.length <= 1 && !loading.value);

function timeAgo(dateStr) {
    const d = new Date(dateStr);
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60) return 'now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
}
</script>

<template>
<Head title="AI Copilot" />
<TenantLayout>
    <div class="flex gap-4 h-[calc(100vh-7rem)]">

        <!-- Conversations sidebar -->
        <div class="hidden lg:flex flex-col w-[220px] flex-shrink-0 card p-0 overflow-hidden">
            <div class="p-3 border-b border-frost-1">
                <button @click="newChat" class="btn btn-primary btn-sm w-full cursor-pointer flex items-center justify-center gap-1.5">
                    <Plus :size="14" /> New Chat
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-2 space-y-1">
                <div v-if="!conversationList.length" class="text-[11px] text-ink-3 text-center py-6 px-2">
                    No conversations yet. Ask something to get started.
                </div>
                <div v-for="conv in conversationList" :key="conv.id"
                    class="group flex items-center gap-2 px-2.5 py-2 rounded-lg cursor-pointer transition text-[12px]"
                    :class="activeConversationId === conv.id ? 'bg-brand-600/15 text-white' : 'text-ink-2 hover:bg-frost-1'"
                    @click="openConversation(conv)">
                    <MessageSquare :size="13" class="flex-shrink-0 opacity-60" />
                    <div class="flex-1 min-w-0">
                        <div class="truncate">{{ conv.title || 'New conversation' }}</div>
                        <div class="text-[10px] text-ink-3">{{ timeAgo(conv.updated_at) }}</div>
                    </div>
                    <button @click.stop="deleteConversation(conv)"
                        class="opacity-0 group-hover:opacity-100 text-ink-3 hover:text-rose-400 cursor-pointer transition flex-shrink-0">
                        <Trash2 :size="12" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Chat area -->
        <div class="flex-1 card flex flex-col p-0 overflow-hidden">
            <!-- Header -->
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-frost-1">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-full bg-gradient-to-br from-brand-600 to-fuchsia flex items-center justify-center">
                        <Bot :size="18" class="text-white" />
                    </div>
                    <div>
                        <div class="text-[14px] font-bold text-white">AI Copilot</div>
                        <div class="text-[10.5px] text-ink-3 font-mono">Powered by your data · Ask anything about your business</div>
                    </div>
                </div>
                <button @click="newChat" class="lg:hidden btn btn-ghost btn-sm cursor-pointer flex items-center gap-1.5">
                    <Plus :size="13" /> New
                </button>
            </div>

            <!-- Messages -->
            <div ref="scrollArea" class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                <div v-for="(msg, i) in messages" :key="msg.id || i"
                     class="flex gap-3"
                     :class="msg.role === 'user' ? 'justify-end' : ''">
                    <div v-if="msg.role === 'assistant'" class="h-7 w-7 rounded-full bg-brand-600/20 flex items-center justify-center flex-shrink-0 mt-1">
                        <Sparkles :size="13" class="text-brand-300" />
                    </div>
                    <div class="max-w-[80%] space-y-1.5">
                        <div class="rounded-[12px] px-4 py-2.5 text-[13px] leading-relaxed whitespace-pre-line"
                             :class="msg.role === 'user'
                                 ? 'bg-brand-600/20 text-white'
                                 : 'bg-surface-2 text-ink border border-frost-1'">
                            {{ msg.content }}
                        </div>

                        <!-- Badges + SQL transparency -->
                        <div v-if="msg.role === 'assistant' && (msg.sql || msg.meta?.escalated || msg.meta?.row_count)"
                             class="flex items-center gap-2 flex-wrap px-1">
                            <span v-if="msg.meta?.escalated" class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-fuchsia/15 text-fuchsia">
                                <Zap :size="10" /> Deeper analysis
                            </span>
                            <span v-if="msg.meta?.row_count !== null && msg.meta?.row_count !== undefined" class="text-[10px] px-2 py-0.5 rounded-full bg-frost-1 text-ink-3">
                                {{ msg.meta.row_count }} row{{ msg.meta.row_count === 1 ? '' : 's' }}
                            </span>
                            <button v-if="msg.sql" @click="toggleSql(msg.id || i)"
                                class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-frost-1 text-ink-3 hover:text-white cursor-pointer transition">
                                <Database :size="10" /> View SQL
                                <ChevronUp v-if="expandedSql[msg.id || i]" :size="10" />
                                <ChevronDown v-else :size="10" />
                            </button>
                        </div>
                        <pre v-if="msg.sql && expandedSql[msg.id || i]"
                            class="text-[10.5px] font-mono bg-bg-2 border border-frost-1 rounded-lg px-3 py-2 overflow-x-auto text-ink-3">{{ msg.sql }}</pre>
                    </div>
                </div>

                <div v-if="loading" class="flex gap-3">
                    <div class="h-7 w-7 rounded-full bg-brand-600/20 flex items-center justify-center flex-shrink-0 mt-1">
                        <Loader2 :size="13" class="text-brand-300 animate-spin" />
                    </div>
                    <div class="bg-surface-2 border border-frost-1 rounded-[12px] px-4 py-2.5 text-[13px] text-ink-3">
                        Thinking…
                    </div>
                </div>
            </div>

            <!-- Suggestions -->
            <div v-if="showSuggestions" class="px-5 pb-2">
                <div class="text-[10px] font-mono uppercase tracking-widest text-ink-3 mb-2">Try asking</div>
                <div class="flex flex-wrap gap-2">
                    <button v-for="s in suggestions" :key="s"
                            class="btn btn-ghost text-[11px] cursor-pointer"
                            @click="sendMessage(s)">
                        {{ s }}
                    </button>
                </div>
            </div>

            <!-- Input -->
            <div class="flex items-center gap-2 px-5 py-4 border-t border-frost-1">
                <input v-model="input"
                       class="heyd2c-input flex-1"
                       placeholder="Ask about your orders, expenses, P&L, inventory, ads..."
                       @keydown.enter="sendMessage()"
                       :disabled="loading" />
                <button class="btn btn-primary px-3 cursor-pointer" @click="sendMessage()" :disabled="loading || !input.trim()">
                    <Send :size="16" />
                </button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
