<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { Building2, IndianRupee, Shield, FileImage, ChevronRight, Clock, Plug, ShoppingBag, Megaphone } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
    categories: { type: Array, default: () => [] },
    stateMap: { type: Array, default: () => [] },
});

const categoryLabels = {
    apparel: 'Apparel & Fashion', footwear: 'Footwear', jewelry: 'Jewelry & Accessories',
    electronics: 'Electronics', beauty: 'Beauty & Personal Care', wellness: 'Health & Wellness',
    food: 'Food & Beverages', home: 'Home & Decor', baby_kids: 'Baby & Kids', pet: 'Pet Supplies',
    sports_fitness: 'Sports & Fitness', luxury: 'Luxury Goods', books_stationery: 'Books & Stationery',
    art_craft: 'Art, Craft & Handmade', spiritual: 'Spiritual & Astrology', other: 'Other',
};
// Default GST rate suggestions per category — based on common Indian GST
// slabs for each vertical. These are starting points the user can still
// override per-product; not a legal/compliance guarantee. Jewelry sits at
// 3% (the special lower slab for gold/silver/precious stone items), books
// are largely nil-rated but stationery isn't, so 5% is a reasonable blended
// default for that combined category.
const categoryDefaultRates = {
    apparel: 5, footwear: 5, jewelry: 3, electronics: 18, beauty: 5, wellness: 12,
    food: 5, home: 18, baby_kids: 5, pet: 18, sports_fitness: 18, luxury: 40,
    books_stationery: 5, art_craft: 12, spiritual: 18, other: 18,
};

const form = useForm({
    name: props.settings.name || '', email: props.settings.email || '',
    gstin: props.settings.gstin || '', business_category: props.settings.business_category || 'other',
    default_gst_rate: props.settings.default_gst_rate || 18, currency: props.settings.currency || 'INR',
    timezone: props.settings.timezone || 'Asia/Kolkata',
    pf_enabled: props.settings.pf_enabled ?? true, pf_employee_rate: props.settings.pf_employee_rate ?? 12,
    pf_employer_rate: props.settings.pf_employer_rate ?? 12, pf_basic_cap: props.settings.pf_basic_cap ?? 15000,
    pf_establishment_code: props.settings.pf_establishment_code || '',
    esi_enabled: props.settings.esi_enabled ?? true, esi_employee_rate: props.settings.esi_employee_rate ?? 0.75,
    esi_employer_rate: props.settings.esi_employer_rate ?? 3.25, esi_gross_threshold: props.settings.esi_gross_threshold ?? 21000,
    esi_establishment_code: props.settings.esi_establishment_code || '',
    pt_amount: props.settings.pt_amount ?? 200,
});

const hasGst = ref(!!props.settings.gstin);
const activeSection = ref(null);
const tenantSlug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';

const detectedState = computed(() => {
    if (!form.gstin || form.gstin.length < 2) return null;
    const code = form.gstin.substring(0, 2);
    const state = props.stateMap.find(s => s.code === code);
    return state ? state.name : null;
});

watch(() => form.business_category, (cat) => {
    if (categoryDefaultRates[cat]) form.default_gst_rate = categoryDefaultRates[cat];
});

function submit() {
    if (!hasGst.value) form.gstin = '';
    form.put(route('tenant.settings.update', { tenant: tenantSlug }));
}

const letterheadInput = ref(null);
function uploadLetterhead(e) {
    const file = e.target.files[0];
    if (!file) return;
    router.post(route('tenant.settings.letterhead.upload', { tenant: tenantSlug }), { letterhead: file }, { forceFormData: true });
}
function removeLetterhead() {
    if (confirm('Remove company letterhead?')) {
        router.delete(route('tenant.settings.letterhead.remove', { tenant: tenantSlug }));
    }
}

// Navigate to sub-pages (Attendance Settings, Integrations)
function goTo(routeName) {
    router.visit(route(routeName, { tenant: tenantSlug }));
}

const tiles = [
    { id: 'business', icon: Building2, label: 'Business Details', desc: 'Company name, email, currency, timezone', color: 'text-blue-400' },
    { id: 'gst', icon: IndianRupee, label: 'GST Configuration', desc: 'GSTIN, tax rates, business category', color: 'text-emerald' },
    { id: 'statutory', icon: Shield, label: 'PF / ESI / PT', desc: 'Provident Fund, ESI rates, Professional Tax', color: 'text-amber' },
    { id: 'letterhead', icon: FileImage, label: 'Letterhead', desc: 'Company letterhead for printed letters', color: 'text-purple-400' },
    { id: 'attendance', icon: Clock, label: 'Attendance Settings', desc: 'Shift timing, late policy, overtime, work schedule', color: 'text-cyan-400', route: 'tenant.hr.attendance.settings' },
    { id: 'integrations_shopify', icon: ShoppingBag, label: 'Shopify', desc: 'Connect your Shopify store', color: 'text-green-400', route: 'tenant.integrations.shopify.show' },
    { id: 'integrations_woo', icon: ShoppingBag, label: 'WooCommerce', desc: 'Connect your WooCommerce store', color: 'text-violet-400', route: 'tenant.integrations.woo.show' },
    { id: 'integrations_meta', icon: Megaphone, label: 'Meta Ads', desc: 'Connect Facebook & Instagram ads', color: 'text-blue-500', route: 'tenant.integrations.meta.show' },
    { id: 'integrations_google', icon: Megaphone, label: 'Google Ads', desc: 'Connect Google Ads account', color: 'text-red-400', route: 'tenant.integrations.google-ads.show' },
    { id: 'integrations_marketplaces', icon: ShoppingBag, label: 'Marketplaces', desc: 'Amazon, Flipkart, Myntra, Nykaa', color: 'text-orange-400', route: 'tenant.marketplaces.index' },
];
</script>

<template>
<Head title="Company Settings" />
<TenantLayout>
    <div class="max-w-2xl">
        <div class="mb-5">
            <h2 class="text-[20px] font-bold text-white">Company Settings</h2>
            <p class="text-[12px] text-ink-3 mt-1">Manage your business configuration</p>
        </div>

        <!-- Tiles Grid (shown when no section is active) -->
        <div v-if="!activeSection">
            <!-- Company Settings -->
            <div class="text-[10px] font-mono uppercase tracking-widest text-ink-3 mb-2 mt-4">Company</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                <div v-for="t in tiles.filter(t => !t.route && !t.id.startsWith('integrations'))" :key="t.id"
                    class="card cursor-pointer hover:border-brand-600/40 transition group"
                    @click="activeSection = t.id">
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-xl bg-bg-3 flex items-center justify-center flex-shrink-0">
                            <component :is="t.icon" :size="20" :class="t.color" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[14px] font-semibold text-white group-hover:text-brand-300 transition flex items-center justify-between">
                                {{ t.label }}
                                <ChevronRight :size="14" class="text-ink-3 group-hover:text-brand-300 transition" />
                            </div>
                            <p class="text-[12px] text-ink-3 mt-0.5">{{ t.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Settings -->
            <div class="text-[10px] font-mono uppercase tracking-widest text-ink-3 mb-2 mt-5">Attendance & Shifts</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">
                <div v-for="t in tiles.filter(t => t.id === 'attendance')" :key="t.id"
                    class="card cursor-pointer hover:border-brand-600/40 transition group"
                    @click="goTo(t.route)">
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-xl bg-bg-3 flex items-center justify-center flex-shrink-0">
                            <component :is="t.icon" :size="20" :class="t.color" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[14px] font-semibold text-white group-hover:text-brand-300 transition flex items-center justify-between">
                                {{ t.label }}
                                <ChevronRight :size="14" class="text-ink-3 group-hover:text-brand-300 transition" />
                            </div>
                            <p class="text-[12px] text-ink-3 mt-0.5">{{ t.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Integrations -->
            <div class="text-[10px] font-mono uppercase tracking-widest text-ink-3 mb-2 mt-5">Integrations</div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div v-for="t in tiles.filter(t => t.id.startsWith('integrations'))" :key="t.id"
                    class="card cursor-pointer hover:border-brand-600/40 transition group"
                    @click="goTo(t.route)">
                    <div class="flex items-start gap-3">
                        <div class="h-10 w-10 rounded-xl bg-bg-3 flex items-center justify-center flex-shrink-0">
                            <component :is="t.icon" :size="20" :class="t.color" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-[14px] font-semibold text-white group-hover:text-brand-300 transition flex items-center justify-between">
                                {{ t.label }}
                                <ChevronRight :size="14" class="text-ink-3 group-hover:text-brand-300 transition" />
                            </div>
                            <p class="text-[12px] text-ink-3 mt-0.5">{{ t.desc }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Back button when inside a section -->
        <div v-if="activeSection" class="mb-4">
            <button class="text-[13px] text-brand-300 hover:text-brand-200 transition cursor-pointer flex items-center gap-1" @click="activeSection = null">
                ← Back to Settings
            </button>
        </div>

        <!-- ═══ BUSINESS DETAILS ═══ -->
        <form v-if="activeSection === 'business'" @submit.prevent="submit" class="space-y-5">
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4 flex items-center gap-2">
                    <Building2 :size="16" class="text-blue-400" /> Business Details
                </h3>
                <div class="space-y-3">
                    <div>
                        <label class="heyd2c-label">Company Name</label>
                        <input v-model="form.name" class="heyd2c-input" />
                        <div v-if="form.errors.name" class="mt-1 text-[11px] text-rose">{{ form.errors.name }}</div>
                    </div>
                    <div>
                        <label class="heyd2c-label">Email</label>
                        <input v-model="form.email" type="email" class="heyd2c-input" />
                        <div v-if="form.errors.email" class="mt-1 text-[11px] text-rose">{{ form.errors.email }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="heyd2c-label">Currency</label>
                            <input v-model="form.currency" class="heyd2c-input" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Timezone</label>
                            <input v-model="form.timezone" class="heyd2c-input" />
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save Business Details' }}
            </button>
        </form>

        <!-- ═══ GST CONFIGURATION ═══ -->
        <form v-if="activeSection === 'gst'" @submit.prevent="submit" class="space-y-5">
            <div class="card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-[15px] font-bold text-white flex items-center gap-2">
                        <IndianRupee :size="16" class="text-emerald" /> GST Configuration
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
                        <label class="heyd2c-label">GSTIN</label>
                        <input v-model="form.gstin" class="heyd2c-input font-mono text-[15px] tracking-wider" placeholder="27AABCU9603R1ZM" maxlength="15" />
                        <div v-if="form.errors.gstin" class="mt-1 text-[11px] text-rose">{{ form.errors.gstin }}</div>
                        <div v-if="detectedState" class="mt-1 text-[11px] text-emerald">✓ Registered State: {{ detectedState }}</div>
                    </div>
                    <div>
                        <label class="heyd2c-label">Business Category</label>
                        <select v-model="form.business_category" class="heyd2c-input">
                            <option v-for="(label, key) in categoryLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <p class="mt-1 text-[10px] text-ink-3">Determines default GST rate for your products</p>
                    </div>
                    <div>
                        <label class="heyd2c-label">Default GST Rate (%)</label>
                        <div class="flex items-center gap-3">
                            <input v-model="form.default_gst_rate" type="number" step="0.01" min="0" max="40" class="heyd2c-input w-32" />
                            <div class="flex gap-2">
                                <button type="button" v-for="r in [5, 18, 40]" :key="r"
                                    class="px-3 py-1.5 rounded-lg text-[11px] font-mono transition cursor-pointer"
                                    :class="form.default_gst_rate == r ? 'bg-brand-600/20 text-brand-300 border border-brand-600/40' : 'bg-bg-3 text-ink-3 border border-frost-1'"
                                    @click="form.default_gst_rate = r">{{ r }}%</button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 rounded-[10px] bg-bg-3 border border-frost-1 p-4">
                        <div class="text-[11px] font-mono uppercase tracking-widest text-ink-3 mb-2">GST 2.0 Rate Reference</div>
                        <div class="grid grid-cols-2 gap-2 text-[12px]">
                            <div class="text-ink-2">Apparel ≤ ₹2,500</div><div class="text-ink font-mono">5%</div>
                            <div class="text-ink-2">Apparel > ₹2,500</div><div class="text-ink font-mono">18%</div>
                            <div class="text-ink-2">Electronics</div><div class="text-ink font-mono">18%</div>
                            <div class="text-ink-2">Beauty & Personal Care</div><div class="text-ink font-mono">5%</div>
                            <div class="text-ink-2">Packaged Food</div><div class="text-ink font-mono">5%</div>
                            <div class="text-ink-2">Luxury / Sin Goods</div><div class="text-ink font-mono">40%</div>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save GST Settings' }}
            </button>
        </form>

        <!-- ═══ PF / ESI / PT ═══ -->
        <form v-if="activeSection === 'statutory'" @submit.prevent="submit" class="space-y-5">
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-4 flex items-center gap-2">
                    <Shield :size="16" class="text-amber" /> PF & ESI Configuration
                </h3>

                <!-- PF -->
                <div class="mb-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[13px] font-semibold text-white">Provident Fund (PF)</span>
                        <label class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                            <input v-model="form.pf_enabled" type="checkbox" class="accent-brand-600" /> Enabled
                        </label>
                    </div>
                    <div v-if="form.pf_enabled" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="heyd2c-label">Employee Rate (%)</label>
                            <input v-model="form.pf_employee_rate" type="number" step="0.01" class="heyd2c-input" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Employer Rate (%)</label>
                            <input v-model="form.pf_employer_rate" type="number" step="0.01" class="heyd2c-input" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Basic Cap (₹)</label>
                            <input v-model="form.pf_basic_cap" type="number" class="heyd2c-input" />
                            <p class="mt-1 text-[10px] text-ink-3">PF calculated on basic up to this cap</p>
                        </div>
                        <div>
                            <label class="heyd2c-label">PF Establishment Code</label>
                            <input v-model="form.pf_establishment_code" class="heyd2c-input font-mono" placeholder="MH/MUM/12345" />
                        </div>
                    </div>
                    <div v-else class="rounded-[10px] bg-bg-3 border border-frost-1 px-4 py-4 text-center">
                        <p class="text-[12px] text-ink-3">PF is disabled. Enable to deduct PF from employee salaries.</p>
                    </div>
                </div>

                <!-- ESI -->
                <div class="mb-5">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[13px] font-semibold text-white">ESI (Employee State Insurance)</span>
                        <label class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                            <input v-model="form.esi_enabled" type="checkbox" class="accent-brand-600" /> Enabled
                        </label>
                    </div>
                    <div v-if="form.esi_enabled" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="heyd2c-label">Employee Rate (%)</label>
                            <input v-model="form.esi_employee_rate" type="number" step="0.01" class="heyd2c-input" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Employer Rate (%)</label>
                            <input v-model="form.esi_employer_rate" type="number" step="0.01" class="heyd2c-input" />
                        </div>
                        <div>
                            <label class="heyd2c-label">Gross Threshold (₹)</label>
                            <input v-model="form.esi_gross_threshold" type="number" class="heyd2c-input" />
                            <p class="mt-1 text-[10px] text-ink-3">ESI applies only if gross ≤ this amount</p>
                        </div>
                        <div>
                            <label class="heyd2c-label">ESI Establishment Code</label>
                            <input v-model="form.esi_establishment_code" class="heyd2c-input font-mono" />
                        </div>
                    </div>
                    <div v-else class="rounded-[10px] bg-bg-3 border border-frost-1 px-4 py-4 text-center">
                        <p class="text-[12px] text-ink-3">ESI is disabled. Enable if 10+ employees.</p>
                    </div>
                </div>

                <!-- PT -->
                <div>
                    <label class="heyd2c-label">Professional Tax (₹/month)</label>
                    <div class="flex items-center gap-3">
                        <input v-model="form.pt_amount" type="number" step="1" class="heyd2c-input w-32" />
                        <span class="text-[11px] text-ink-3">Maharashtra default: ₹200/month</span>
                    </div>
                </div>

                <div class="mt-4 rounded-[10px] bg-bg-3 border border-frost-1 p-4">
                    <div class="text-[11px] font-mono uppercase tracking-widest text-ink-3 mb-2">Statutory Defaults</div>
                    <div class="grid grid-cols-2 gap-2 text-[12px]">
                        <div class="text-ink-2">PF Employee/Employer</div><div class="text-ink font-mono">12% / 12%</div>
                        <div class="text-ink-2">PF Basic Cap</div><div class="text-ink font-mono">₹15,000</div>
                        <div class="text-ink-2">ESI Employee/Employer</div><div class="text-ink font-mono">0.75% / 3.25%</div>
                        <div class="text-ink-2">ESI Gross Threshold</div><div class="text-ink font-mono">₹21,000</div>
                        <div class="text-ink-2">PT (Maharashtra)</div><div class="text-ink font-mono">₹200/month</div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-full py-3" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save Statutory Settings' }}
            </button>
        </form>

        <!-- ═══ LETTERHEAD ═══ -->
        <div v-if="activeSection === 'letterhead'" class="card">
            <h3 class="text-[15px] font-bold text-white mb-4 flex items-center gap-2">
                <FileImage :size="16" class="text-purple-400" /> Company Letterhead
            </h3>
            <p class="text-[12px] text-ink-3 mb-4">Upload your company letterhead image (JPG, PNG, WebP — max 2MB). It will appear at the top of printed letters.</p>

            <div v-if="settings.letterhead_url" class="mb-4 p-6 bg-white rounded-lg text-center">
                <img :src="settings.letterhead_url" alt="Letterhead" class="max-h-[120px] mx-auto object-contain" />
            </div>
            <div v-else class="mb-4 rounded-[10px] bg-bg-3 border border-frost-1 px-4 py-8 text-center">
                <FileImage :size="32" class="text-ink-3 mx-auto mb-2" />
                <p class="text-[12px] text-ink-3">No letterhead uploaded yet</p>
            </div>

            <div class="flex items-center gap-3">
                <label class="btn btn-primary cursor-pointer">
                    <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" ref="letterheadInput" @change="uploadLetterhead" />
                    {{ settings.letterhead_url ? 'Replace Letterhead' : 'Upload Letterhead' }}
                </label>
                <button v-if="settings.letterhead_url" type="button" class="btn btn-ghost text-rose" @click="removeLetterhead">Remove</button>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
