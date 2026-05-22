<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Check, Target, AlertTriangle, RefreshCw, Plug2, Key } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    account: { type: Object, default: null },
    configured: { type: Boolean, default: false },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showManual = ref(false);
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

const manualForm = useForm({ access_token: '', refresh_token: '', customer_id: '' });

function connectOAuth() {
    router.post(route('tenant.integrations.google-ads.connect', { tenant: slug }));
}
function manualSubmit() {
    manualForm.post(route('tenant.integrations.google-ads.manual', { tenant: slug }));
}
function syncNow() {
    router.post(route('tenant.integrations.google-ads.sync', { tenant: slug }));
}
function disconnect() {
    if (confirm('Disconnect Google Ads?')) {
        router.delete(route('tenant.integrations.google-ads.disconnect', { tenant: slug }));
    }
}
</script>

<template>
<Head title="Google Ads Integration" />
<TenantLayout>
    <div class="max-w-2xl mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <div class="h-12 w-12 rounded-xl bg-[#4285F4]/15 flex items-center justify-center">
                <Target :size="24" class="text-[#4285F4]" />
            </div>
            <div>
                <h2 class="text-[20px] font-bold text-white">Google Ads</h2>
                <p class="text-[12px] text-ink-3">Connect your Google Ads account</p>
            </div>
        </div>

        <!-- Connected state -->
        <div v-if="account && account.status === 'connected'" class="card space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Check :size="16" class="text-emerald" />
                    <span class="text-[14px] font-semibold text-emerald">Connected</span>
                </div>
                <button class="btn btn-ghost text-rose text-[12px]" @click="disconnect">Disconnect</button>
            </div>
            <div class="grid grid-cols-2 gap-4 text-[13px]">
                <div><span class="pulsara-label">Customer ID</span><div class="text-ink font-mono">{{ account.customer_id }}</div></div>
                <div><span class="pulsara-label">Connected At</span><div class="text-ink">{{ dateFmt(account.connected_at) }}</div></div>
                <div><span class="pulsara-label">Last Synced</span><div class="text-ink">{{ dateFmt(account.last_synced_at) }}</div></div>
            </div>
            <button class="btn btn-primary w-full" @click="syncNow"><RefreshCw :size="14" /> Sync Now</button>
            <p class="text-[11px] text-ink-3 text-center">Ad spend is automatically synced to your Expenses as "Google Ads · [date]"</p>
        </div>

        <!-- Error state -->
        <div v-else-if="account && account.status === 'error'" class="card space-y-4">
            <div class="flex items-center gap-2 text-rose">
                <AlertTriangle :size="16" />
                <span class="text-[14px] font-semibold">Connection Error</span>
            </div>
            <p class="text-[13px] text-ink-2">{{ account.error_message }}</p>
            <button class="btn btn-primary w-full" @click="connectOAuth"><Plug2 :size="14" /> Reconnect</button>
        </div>

        <!-- Not connected -->
        <div v-else class="space-y-4">
            <div class="card space-y-4">
                <h3 class="text-[15px] font-bold text-white">What you'll get</h3>
                <div class="space-y-2 text-[13px] text-ink-2">
                    <div class="flex items-start gap-2"><Check :size="14" class="text-emerald mt-0.5 flex-shrink-0" /> Campaign performance: spend, impressions, clicks, conversions</div>
                    <div class="flex items-start gap-2"><Check :size="14" class="text-emerald mt-0.5 flex-shrink-0" /> Daily spend tracking with cost-per-click and CTR</div>
                    <div class="flex items-start gap-2"><Check :size="14" class="text-emerald mt-0.5 flex-shrink-0" /> Auto-logged ad spend in Expenses panel</div>
                    <div class="flex items-start gap-2"><Check :size="14" class="text-emerald mt-0.5 flex-shrink-0" /> Blended ROAS across all platforms</div>
                </div>
            </div>

            <div v-if="configured" class="card">
                <button class="btn btn-primary w-full py-3 text-[14px]" @click="connectOAuth">
                    <Plug2 :size="16" /> Connect with Google
                </button>
                <div class="text-center mt-3">
                    <button class="text-[12px] text-ink-3 hover:text-brand-300 cursor-pointer" @click="showManual = !showManual">
                        <Key :size="11" /> Or connect manually with tokens
                    </button>
                </div>
            </div>
            <div v-else class="card">
                <p class="text-[13px] text-ink-2 mb-3">Google Ads is not configured yet. Add these environment variables:</p>
                <div class="bg-bg-3 rounded-lg p-3 font-mono text-[11px] text-ink-3 space-y-1">
                    <div>GOOGLE_ADS_CLIENT_ID=your_client_id</div>
                    <div>GOOGLE_ADS_CLIENT_SECRET=your_client_secret</div>
                    <div>GOOGLE_ADS_DEVELOPER_TOKEN=your_dev_token</div>
                </div>
            </div>

            <!-- Manual connect -->
            <div v-if="showManual" class="card">
                <h3 class="text-[15px] font-bold text-white mb-3">Manual Connection</h3>
                <form @submit.prevent="manualSubmit" class="space-y-3">
                    <div>
                        <label class="pulsara-label">Access Token</label>
                        <input v-model="manualForm.access_token" class="pulsara-input font-mono text-[11px]" placeholder="ya29...." />
                    </div>
                    <div>
                        <label class="pulsara-label">Refresh Token</label>
                        <input v-model="manualForm.refresh_token" class="pulsara-input font-mono text-[11px]" placeholder="1//0..." />
                    </div>
                    <div>
                        <label class="pulsara-label">Customer ID (no dashes)</label>
                        <input v-model="manualForm.customer_id" class="pulsara-input font-mono" placeholder="1234567890" />
                    </div>
                    <button type="submit" class="btn btn-primary w-full" :disabled="manualForm.processing">{{ manualForm.processing ? 'Connecting…' : 'Connect Manually' }}</button>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
