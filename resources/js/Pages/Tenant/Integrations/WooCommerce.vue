<script setup>
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Check, ShoppingBag, Plug2, AlertTriangle } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

defineProps({
    account: { type: Object, default: null },
});

const slug = usePage().props.company?.slug;
const showManual = ref(false);
const oauthForm  = useForm({ shop_url: '' });
const manualForm = useForm({ shop_url: '', consumer_key: '', consumer_secret: '' });
</script>

<template>
    <Head title="WooCommerce" />
    <TenantLayout>
        <div class="max-w-3xl">
            <div class="flex items-center gap-3 mb-6">
                <div class="h-11 w-11 rounded-[12px] bg-surface-2 border border-frost-2 flex items-center justify-center">
                    <ShoppingBag :size="20" class="text-brand-300" />
                </div>
                <div>
                    <h2 class="text-[18px] font-bold text-white">WooCommerce</h2>
                    <p class="text-[12px] text-ink-3">Connect your WordPress + Woo store</p>
                </div>
            </div>

            <div v-if="account && account.status === 'connected'" class="card mb-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-full bg-emerald/15 flex items-center justify-center">
                        <Check :size="16" class="text-emerald" />
                    </div>
                    <div>
                        <div class="text-[14px] font-semibold text-white">Connected to {{ account.shop_domain }}</div>
                        <div class="text-[11px] font-mono text-ink-3">Mode: {{ account.mode }}</div>
                    </div>
                </div>
                <Link :href="route('tenant.integrations.woo.disconnect', { tenant: slug })" method="delete" as="button" class="btn btn-ghost">Disconnect</Link>
            </div>

            <div v-else class="card mb-4">
                <p class="text-[12.5px] text-ink-2 mb-4 leading-relaxed">
                    We'll redirect you to your WordPress admin to approve an API key.
                </p>

                <form v-if="!showManual" class="space-y-3" @submit.prevent="oauthForm.post(route('tenant.integrations.woo.connect', { tenant: slug }))">
                    <label class="pulsara-label">Store URL</label>
                    <input v-model="oauthForm.shop_url" placeholder="https://yourstore.com" class="pulsara-input" />
                    <button type="submit" class="btn btn-primary w-full py-3 text-[14px]" :disabled="oauthForm.processing">
                        <Plug2 :size="15" /> Authorize via WordPress →
                    </button>
                    <button type="button" class="text-[11px] font-mono tracking-wider text-ink-3 hover:text-brand-300 block mx-auto mt-2" @click="showManual = true">
                        Advanced: paste consumer key/secret
                    </button>
                </form>

                <form v-else class="space-y-3" @submit.prevent="manualForm.post(route('tenant.integrations.woo.manual', { tenant: slug }))">
                    <div class="flex items-start gap-2 p-3 rounded-[10px] border border-amber/30 bg-amber/5 text-[11.5px] text-amber">
                        <AlertTriangle :size="15" class="flex-shrink-0 mt-0.5" />
                        <span>In WP: WooCommerce → Settings → Advanced → REST API → Add key (Read/Write).</span>
                    </div>
                    <div>
                        <label class="pulsara-label">Store URL</label>
                        <input v-model="manualForm.shop_url" placeholder="https://yourstore.com" class="pulsara-input" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="pulsara-label">Consumer key</label>
                            <input v-model="manualForm.consumer_key" placeholder="ck_…" class="pulsara-input font-mono" />
                        </div>
                        <div>
                            <label class="pulsara-label">Consumer secret</label>
                            <input v-model="manualForm.consumer_secret" placeholder="cs_…" class="pulsara-input font-mono" />
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-full py-3 text-[14px]" :disabled="manualForm.processing">
                        Connect with keys →
                    </button>
                    <button type="button" class="text-[11px] font-mono tracking-wider text-ink-3 hover:text-brand-300 block mx-auto mt-2" @click="showManual = false">
                        ← Back
                    </button>
                </form>
            </div>
        </div>
    </TenantLayout>
</template>
