<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Check, ShoppingBag, Plug2, AlertTriangle } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

defineProps({
    account: { type: Object, default: null },
    scopes:  { type: Array, default: () => [] },
});

const slug = usePage().props.company?.slug;
const showManual = ref(false);

const oauthForm  = useForm({ shop_domain: '' });
const manualForm = useForm({ shop_domain: '', access_token: '' });
const ccForm      = useForm({ shop_domain: '', client_id: '', client_secret: '' });
const showClientCredentials = ref(false);

const permissions = [
    { title: 'Orders Data (Last 1 Year)',     desc: 'Order time, amount, product details and status' },
    { title: 'Product Data',                  desc: 'SKU, pricing, categories and sales data' },
    { title: 'Modify Order Data',             desc: 'Edit order data based on shipping updates' },
    { title: 'Customer Personal Information', desc: 'Name, email, phone, address for labels & notifications' },
    { title: 'Costs Data',                    desc: 'Discounts, refunds, taxes and shipping costs' },
];

function connect() {
    oauthForm.post(route('tenant.integrations.shopify.connect', { tenant: slug }));
}
function manualSubmit() {
    manualForm.post(route('tenant.integrations.shopify.manual', { tenant: slug }));
}
function ccSubmit() {
    ccForm.post(route('tenant.integrations.shopify.connect-client-credentials', { tenant: slug }));
}
</script>

<template>
    <Head title="Shopify" />
    <TenantLayout>
        <div class="max-w-3xl">
            <div class="flex items-center gap-3 mb-6">
                <div class="h-11 w-11 rounded-[12px] bg-surface-2 border border-frost-2 flex items-center justify-center">
                    <ShoppingBag :size="20" class="text-brand-300" />
                </div>
                <div>
                    <h2 class="text-[18px] font-bold text-white">Shopify</h2>
                    <p class="text-[12px] text-ink-3">Connect your store</p>
                </div>
            </div>

            <div v-if="account && account.status === 'connected'" class="card mb-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="h-9 w-9 rounded-full bg-emerald/15 flex items-center justify-center">
                            <Check :size="16" class="text-emerald" />
                        </div>
                        <div>
                            <div class="text-[14px] font-semibold text-white">Connected to {{ account.shop_domain }}</div>
                            <div class="text-[11px] font-mono text-ink-3">
                                Mode: <span class="text-brand-400">{{ account.mode }}</span>
                                <span v-if="account.has_refresh_token" class="ml-2 text-emerald">· expiring token (auto-refreshes)</span>
                                <span v-else class="ml-2 text-amber">· non-expiring token</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="!account.has_refresh_token" :href="route('tenant.integrations.shopify.migrate-token', { tenant: slug })" method="post" as="button" class="btn btn-ghost btn-sm" title="If sync fails with a 'non-expiring token' error, this converts your token to the newer expiring format Shopify now requires.">Fix Token</Link>
                        <Link :href="route('tenant.integrations.shopify.sync', { tenant: slug })" method="post" as="button" class="btn btn-primary btn-sm">Sync Orders</Link>
                        <Link :href="route('tenant.integrations.shopify.disconnect', { tenant: slug })" method="delete" as="button" class="btn btn-ghost">Disconnect</Link>
                    </div>
                </div>
            </div>

            <div v-else-if="account && account.status === 'error'" class="card mb-4 border border-rose-500/30 bg-rose-500/5">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <div class="text-[14px] font-semibold text-rose-300">Sync error on {{ account.shop_domain }}</div>
                        <div class="text-[12px] text-ink-2 mt-1">{{ account.error_message || 'Unknown error' }}</div>
                        <div class="text-[11px] font-mono text-ink-3 mt-1">Mode: {{ account.mode }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Link v-if="!account.has_refresh_token" :href="route('tenant.integrations.shopify.migrate-token', { tenant: slug })" method="post" as="button" class="btn btn-primary btn-sm">Fix Token</Link>
                        <Link :href="route('tenant.integrations.shopify.sync', { tenant: slug })" method="post" as="button" class="btn btn-ghost btn-sm">Retry Sync</Link>
                        <Link :href="route('tenant.integrations.shopify.disconnect', { tenant: slug })" method="delete" as="button" class="btn btn-ghost">Disconnect</Link>
                    </div>
                </div>
            </div>

            <div v-else class="card mb-4">
                <div class="text-[12px] text-ink-3 mb-4">We'll request access to the following Shopify data:</div>
                <div class="space-y-3">
                    <div v-for="p in permissions" :key="p.title" class="flex items-start gap-3 pb-3 border-b border-frost-1 last:border-0 last:pb-0">
                        <div class="h-8 w-8 rounded-[10px] bg-brand-600/10 border border-frost-2 flex items-center justify-center flex-shrink-0">
                            <Check :size="14" class="text-brand-300" />
                        </div>
                        <div>
                            <div class="text-[13px] font-semibold text-white">{{ p.title }}</div>
                            <div class="text-[11.5px] text-ink-3 mt-0.5">{{ p.desc }}</div>
                        </div>
                    </div>
                </div>

                <form v-if="!showManual && !showClientCredentials" class="mt-6 space-y-3" @submit.prevent="connect">
                    <label class="heyd2c-label">Shopify Store URL</label>
                    <input v-model="oauthForm.shop_domain" placeholder="yourstore.myshopify.com" class="heyd2c-input" />
                    <button type="submit" class="btn btn-primary w-full py-3 text-[14px]" :disabled="oauthForm.processing">
                        <Plug2 :size="15" /> Connect Store →
                    </button>
                    <div class="flex items-center justify-center gap-4 mt-2">
                        <button type="button" class="text-[11px] font-mono tracking-wider text-ink-3 hover:text-brand-300" @click="showManual = true">
                            Advanced: paste API token
                        </button>
                        <button type="button" class="text-[11px] font-mono tracking-wider text-ink-3 hover:text-brand-300" @click="showClientCredentials = true">
                            Use Client ID / Secret
                        </button>
                    </div>
                </form>

                <form v-else-if="showManual" class="mt-6 space-y-3" @submit.prevent="manualSubmit">
                    <div class="flex items-start gap-2 p-3 rounded-[10px] border border-amber/30 bg-amber/5 text-[11.5px] text-amber">
                        <AlertTriangle :size="15" class="flex-shrink-0 mt-0.5" />
                        <span>Manual mode: create an admin API token in your Shopify admin, then paste it below.</span>
                    </div>
                    <div>
                        <label class="heyd2c-label">Shopify Store URL</label>
                        <input v-model="manualForm.shop_domain" placeholder="yourstore.myshopify.com" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">Admin API Access Token</label>
                        <input v-model="manualForm.access_token" placeholder="shpat_..." class="heyd2c-input font-mono" />
                    </div>
                    <button type="submit" class="btn btn-primary w-full py-3 text-[14px]" :disabled="manualForm.processing">
                        Connect with token →
                    </button>
                    <button type="button" class="text-[11px] font-mono tracking-wider text-ink-3 hover:text-brand-300 block mx-auto mt-2" @click="showManual = false">
                        ← Back to guided OAuth
                    </button>
                </form>

                <form v-else class="mt-6 space-y-3" @submit.prevent="ccSubmit">
                    <div class="flex items-start gap-2 p-3 rounded-[10px] border border-amber/30 bg-amber/5 text-[11.5px] text-amber">
                        <AlertTriangle :size="15" class="flex-shrink-0 mt-0.5" />
                        <span>For Dev Dashboard apps where install/OAuth isn't available (e.g. app under review). Token auto-regenerates roughly every 24h.</span>
                    </div>
                    <div>
                        <label class="heyd2c-label">Shopify Store URL</label>
                        <input v-model="ccForm.shop_domain" placeholder="yourstore.myshopify.com" class="heyd2c-input" />
                    </div>
                    <div>
                        <label class="heyd2c-label">Client ID</label>
                        <input v-model="ccForm.client_id" placeholder="Client ID from Dev Dashboard" class="heyd2c-input font-mono" />
                    </div>
                    <div>
                        <label class="heyd2c-label">Client Secret</label>
                        <input v-model="ccForm.client_secret" placeholder="shpss_..." class="heyd2c-input font-mono" />
                    </div>
                    <button type="submit" class="btn btn-primary w-full py-3 text-[14px]" :disabled="ccForm.processing">
                        Connect with Client ID / Secret →
                    </button>
                    <button type="button" class="text-[11px] font-mono tracking-wider text-ink-3 hover:text-brand-300 block mx-auto mt-2" @click="showClientCredentials = false">
                        ← Back to guided OAuth
                    </button>
                </form>
            </div>
        </div>
    </TenantLayout>
</template>
