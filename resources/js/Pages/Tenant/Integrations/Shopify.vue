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
                            <div class="text-[11px] font-mono text-ink-3">Mode: <span class="text-brand-400">{{ account.mode }}</span></div>
                        </div>
                    </div>
                    <Link :href="route('tenant.integrations.shopify.disconnect', { tenant: slug })" method="delete" as="button" class="btn btn-ghost">Disconnect</Link>
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

                <form v-if="!showManual" class="mt-6 space-y-3" @submit.prevent="connect">
                    <label class="pulsara-label">Shopify Store URL</label>
                    <input v-model="oauthForm.shop_domain" placeholder="yourstore.myshopify.com" class="pulsara-input" />
                    <button type="submit" class="btn btn-primary w-full py-3 text-[14px]" :disabled="oauthForm.processing">
                        <Plug2 :size="15" /> Connect Store →
                    </button>
                    <button type="button" class="text-[11px] font-mono tracking-wider text-ink-3 hover:text-brand-300 block mx-auto mt-2" @click="showManual = true">
                        Advanced: paste API token instead
                    </button>
                </form>

                <form v-else class="mt-6 space-y-3" @submit.prevent="manualSubmit">
                    <div class="flex items-start gap-2 p-3 rounded-[10px] border border-amber/30 bg-amber/5 text-[11.5px] text-amber">
                        <AlertTriangle :size="15" class="flex-shrink-0 mt-0.5" />
                        <span>Manual mode: create an admin API token in your Shopify admin, then paste it below.</span>
                    </div>
                    <div>
                        <label class="pulsara-label">Shopify Store URL</label>
                        <input v-model="manualForm.shop_domain" placeholder="yourstore.myshopify.com" class="pulsara-input" />
                    </div>
                    <div>
                        <label class="pulsara-label">Admin API Access Token</label>
                        <input v-model="manualForm.access_token" placeholder="shpat_..." class="pulsara-input font-mono" />
                    </div>
                    <button type="submit" class="btn btn-primary w-full py-3 text-[14px]" :disabled="manualForm.processing">
                        Connect with token →
                    </button>
                    <button type="button" class="text-[11px] font-mono tracking-wider text-ink-3 hover:text-brand-300 block mx-auto mt-2" @click="showManual = false">
                        ← Back to guided OAuth
                    </button>
                </form>
            </div>
        </div>
    </TenantLayout>
</template>
