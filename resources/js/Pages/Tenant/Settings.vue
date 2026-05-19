<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { Settings, Building2, IndianRupee, Check } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    stateMap: { type: Array, default: () => [] },
});

const categoryLabels = {
    apparel: 'Apparel & Fashion',
    footwear: 'Footwear',
    electronics: 'Electronics',
    beauty: 'Beauty & Personal Care',
    food: 'Food & Beverages',
    luxury: 'Luxury Goods',
    other: 'Other',
};

const categoryDefaultRates = {
    apparel: 5, footwear: 5, electronics: 18, beauty: 5, food: 5, luxury: 40, other: 18,
};

const form = useForm({
    name: props.settings.name || '',
    email: props.settings.email || '',
    gstin: props.settings.gstin || '',
    business_category: props.settings.business_category || 'other',
    default_gst_rate: props.settings.default_gst_rate || 18,
    currency: props.settings.currency || 'INR',
    timezone: props.settings.timezone || 'Asia/Kolkata',
});

const hasGst = ref(!!props.settings.gstin);

// Auto-detect state from GSTIN
const detectedState = computed(() => {
    if (!form.gstin || form.gstin.length < 2) return null;
    const code = form.gstin.substring(0, 2);
    const state = props.stateMap.find(s => s.code === code);
    return state ? state.name : null;
});

// Auto-set default rate when category changes
watch(() => form.business_category, (cat) => {
    if (categoryDefaultRates[cat]) {
        form.default_gst_rate = categoryDefaultRates[cat];
    }
});

function submit() {
    if (!hasGst.value) {
        form.gstin = '';
    }
    form.put(route('tenant.settings.update', { tenant: window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '' }));
}
</script>

<template>
<Head title="Company Settings" />
<TenantLayout>
    <div class="max-w-2xl">
        <div class="mb-5">
            <h2 class="text-[20px] font-bold text-white">Company Settings</h2>
            <p class="text-[12px] text-ink-3 mt-1">Business details, GST configuration, and preferences</p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <!-- Basic Info -->
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4 flex items-center gap-2">
                    <Building2 :size="16" class="text-brand-400" /> Business Details
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="pulsara-label">Company Name</label>
                        <input v-model="form.name" class="pulsara-input" />
                        <div v-if="form.errors.name" class="mt-1 text-[11px] text-rose">{{ form.errors.name }}</div>
                    </div>
                    <div>
                        <label class="pulsara-label">Email</label>
                        <input v-model="form.email" type="email" class="pulsara-input" />
                        <div v-if="form.errors.email" class="mt-1 text-[11px] text-rose">{{ form.errors.email }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="pulsara-label">Currency</label>
                            <input v-model="form.currency" class="pulsara-input" />
                        </div>
                        <div>
                            <label class="pulsara-label">Timezone</label>
                            <input v-model="form.timezone" class="pulsara-input" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- GST Configuration -->
            <div class="card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-[15px] font-bold text-white flex items-center gap-2">
                        <IndianRupee :size="16" class="text-brand-400" /> GST Configuration
                    </h3>
                    <label class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                        <input v-model="hasGst" type="checkbox" class="accent-brand-600" />
                        I have a GSTIN
                    </label>
                </div>

                <div v-if="!hasGst" class="rounded-[10px] bg-bg-3 border border-frost-1 px-4 py-6 text-center">
                    <p class="text-[13px] text-ink-3">GST features are disabled. Toggle "I have a GSTIN" to enable CGST/SGST/IGST breakup on your orders.</p>
                </div>

                <div v-else class="space-y-3">
                    <div>
                        <label class="pulsara-label">GSTIN</label>
                        <input v-model="form.gstin" class="pulsara-input font-mono text-[15px] tracking-wider" placeholder="27AABCU9603R1ZM" maxlength="15" />
                        <div v-if="form.errors.gstin" class="mt-1 text-[11px] text-rose">{{ form.errors.gstin }}</div>
                        <div v-if="detectedState" class="mt-1 text-[11px] text-emerald flex items-center gap-1">
                            <Check :size="12" /> Registered state: {{ detectedState }}
                        </div>
                        <p v-else class="mt-1 text-[10px] text-ink-3">15-character GST number — state is auto-detected from first 2 digits</p>
                    </div>

                    <div>
                        <label class="pulsara-label">Business Category</label>
                        <select v-model="form.business_category" class="pulsara-input">
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ categoryLabels[cat] || cat }}</option>
                        </select>
                        <p class="mt-1 text-[10px] text-ink-3">Determines default GST rate for your products</p>
                    </div>

                    <div>
                        <label class="pulsara-label">Default GST Rate (%)</label>
                        <div class="flex items-center gap-3">
                            <input v-model="form.default_gst_rate" type="number" step="0.01" min="0" max="40" class="pulsara-input w-32" />
                            <div class="flex gap-2">
                                <button type="button" v-for="r in [5, 18, 40]" :key="r"
                                    class="px-3 py-1.5 rounded-lg text-[11px] font-mono transition"
                                    :class="form.default_gst_rate == r ? 'bg-brand-600/20 text-brand-300 border border-brand-600/40' : 'bg-bg-3 text-ink-3 border border-frost-1 hover:border-frost-3'"
                                    @click="form.default_gst_rate = r"
                                >{{ r }}%</button>
                            </div>
                        </div>
                        <div v-if="form.errors.default_gst_rate" class="mt-1 text-[11px] text-rose">{{ form.errors.default_gst_rate }}</div>
                    </div>

                    <!-- GST Rate Reference -->
                    <div class="mt-4 rounded-[10px] bg-bg-3 border border-frost-1 p-4">
                        <div class="text-[11px] font-mono uppercase tracking-widest text-ink-3 mb-2">GST 2.0 Rate Reference (Sep 2025+)</div>
                        <div class="grid grid-cols-2 gap-2 text-[12px]">
                            <div class="text-ink-2">Apparel ≤ ₹2,500 / piece</div><div class="text-ink font-mono">5%</div>
                            <div class="text-ink-2">Apparel > ₹2,500 / piece</div><div class="text-ink font-mono">18%</div>
                            <div class="text-ink-2">Electronics</div><div class="text-ink font-mono">18%</div>
                            <div class="text-ink-2">Beauty & Personal Care</div><div class="text-ink font-mono">5%</div>
                            <div class="text-ink-2">Packaged Food</div><div class="text-ink font-mono">5%</div>
                            <div class="text-ink-2">Luxury / Sin Goods</div><div class="text-ink font-mono">40%</div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save Settings' }}
            </button>
        </form>
    </div>
</TenantLayout>
</template>
