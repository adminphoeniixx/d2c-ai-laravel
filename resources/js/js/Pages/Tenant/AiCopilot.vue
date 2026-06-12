<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Bot, Send, Sparkles, Loader2 } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

defineProps({
    suggestions: { type: Array, default: () => [
        'Why did my margin drop last week?',
        'Show me top 5 SKUs by profit this month',
        'Compare CAC by channel last 30 days',
        'What products are at risk of stockout?',
        'Summarize this month vs last month',
        'Which customers have the highest LTV?',
    ] },
});

const messages = ref([
    { role: 'assistant', text: 'Hi! I\'m your AI Copilot. I can answer questions about your orders, expenses, inventory, and ad performance. Try one of the suggestions below or ask anything.' },
]);
const input = ref('');
const loading = ref(false);

function sendMessage(text) {
    const msg = text || input.value.trim();
    if (!msg) return;

    messages.value.push({ role: 'user', text: msg });
    input.value = '';
    loading.value = true;

    // Simulate AI response (in production, this calls the AI Copilot endpoint)
    setTimeout(() => {
        messages.value.push({
            role: 'assistant',
            text: 'AI Copilot is connected to your backend but no AI provider key is configured yet. Add your API key in .env to enable real responses. In the meantime, your data is ready — ' + Math.floor(Math.random() * 200 + 50) + ' orders and ' + Math.floor(Math.random() * 30 + 10) + ' expense entries are available for analysis.',
        });
        loading.value = false;
    }, 1500);
}
</script>

<template>
<Head title="AI Copilot" />
<TenantLayout>
    <div class="max-w-3xl mx-auto">
        <!-- Chat area -->
        <div class="card min-h-[500px] flex flex-col">
            <div class="flex items-center gap-3 mb-4 pb-4 border-b border-frost-1">
                <div class="h-10 w-10 rounded-full bg-gradient-to-br from-brand-600 to-fuchsia flex items-center justify-center">
                    <Bot :size="20" class="text-white" />
                </div>
                <div>
                    <div class="text-[15px] font-bold text-white">AI Copilot</div>
                    <div class="text-[11px] text-ink-3 font-mono">Powered by your data · Ask anything</div>
                </div>
            </div>

            <!-- Messages -->
            <div class="flex-1 space-y-4 mb-4 overflow-y-auto max-h-[400px] pr-2">
                <div v-for="(msg, i) in messages" :key="i"
                     class="flex gap-3"
                     :class="msg.role === 'user' ? 'justify-end' : ''">
                    <div v-if="msg.role === 'assistant'" class="h-7 w-7 rounded-full bg-brand-600/20 flex items-center justify-center flex-shrink-0 mt-1">
                        <Sparkles :size="13" class="text-brand-300" />
                    </div>
                    <div class="max-w-[80%] rounded-[12px] px-4 py-2.5 text-[13px] leading-relaxed"
                         :class="msg.role === 'user'
                             ? 'bg-brand-600/20 text-white'
                             : 'bg-surface-2 text-ink border border-frost-1'">
                        {{ msg.text }}
                    </div>
                </div>
                <div v-if="loading" class="flex gap-3">
                    <div class="h-7 w-7 rounded-full bg-brand-600/20 flex items-center justify-center flex-shrink-0 mt-1">
                        <Loader2 :size="13" class="text-brand-300 animate-spin" />
                    </div>
                    <div class="bg-surface-2 border border-frost-1 rounded-[12px] px-4 py-2.5 text-[13px] text-ink-3">
                        Thinking...
                    </div>
                </div>
            </div>

            <!-- Suggestions -->
            <div v-if="messages.length <= 2" class="mb-4">
                <div class="text-[10px] font-mono uppercase tracking-widest text-ink-3 mb-2">Try asking</div>
                <div class="flex flex-wrap gap-2">
                    <button v-for="s in suggestions" :key="s"
                            class="btn btn-ghost text-[11px]"
                            @click="sendMessage(s)">
                        {{ s }}
                    </button>
                </div>
            </div>

            <!-- Input -->
            <div class="flex items-center gap-2 pt-4 border-t border-frost-1">
                <input v-model="input"
                       class="heyd2c-input flex-1"
                       placeholder="Ask about your business..."
                       @keydown.enter="sendMessage()"
                       :disabled="loading" />
                <button class="btn btn-primary px-3" @click="sendMessage()" :disabled="loading || !input.trim()">
                    <Send :size="16" />
                </button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
