<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeft, Plus, Minus, RotateCw, X, ArrowUpRight, ArrowDownRight, RefreshCw, AlertTriangle } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    item: { type: Object, default: () => ({}) },
    movements: { type: Array, default: () => [] },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const fmt = (v) => '₹' + new Intl.NumberFormat('en-IN').format(Math.round(v || 0));
const dateFmt = (d) => d ? new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
const isLow = props.item.quantity <= props.item.min_stock_level;

const showAdjust = ref(false);
const form = useForm({ type: 'in', quantity: 1, notes: '' });

function submit() {
    form.post(route('tenant.inventory-mgmt.adjust', { tenant: slug, id: props.item.id }), {
        onSuccess: () => { showAdjust.value = false; form.reset(); },
    });
}

const typeIcons = { in: ArrowDownRight, out: ArrowUpRight, adjustment: RefreshCw };
const typeColors = { in: 'text-emerald', out: 'text-rose', adjustment: 'text-brand-300' };
</script>

<template>
<Head :title="item.name" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-3">
            <Link :href="route('tenant.inventory-mgmt.index', { tenant: slug })" class="btn btn-ghost btn-sm"><ArrowLeft :size="14" /> Back</Link>
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-[20px] font-bold text-white">{{ item.name }}</h2>
                    <AlertTriangle v-if="isLow" :size="16" class="text-rose" />
                </div>
                <p class="text-[12px] text-ink-3 font-mono">{{ item.sku }} · {{ item.category || 'No category' }}</p>
            </div>
        </div>
        <button class="btn btn-primary" @click="showAdjust = true"><RotateCw :size="14" /> Adjust Stock</button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-5">
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Quantity</div><div class="text-[20px] font-bold font-mono" :class="isLow ? 'text-rose' : 'text-white'">{{ item.quantity }} {{ item.unit }}</div></div>
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Min Stock</div><div class="text-[18px] font-bold text-ink-2 font-mono">{{ item.min_stock_level }}</div></div>
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Cost Price</div><div class="text-[18px] font-bold text-ink font-mono">{{ fmt(item.cost_price) }}</div></div>
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Selling Price</div><div class="text-[18px] font-bold text-ink font-mono">{{ fmt(item.selling_price) }}</div></div>
        <div class="card text-center"><div class="text-[11px] text-ink-3 uppercase font-mono mb-1">Stock Value</div><div class="text-[18px] font-bold text-emerald font-mono">{{ fmt(item.quantity * item.cost_price) }}</div></div>
    </div>

    <!-- Adjust Modal -->
    <div v-if="showAdjust" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showAdjust = false">
        <div class="card w-full max-w-sm mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">Adjust Stock</h3>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showAdjust = false"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submit" class="space-y-3">
                <div>
                    <label class="heyd2c-label">Type</label>
                    <div class="flex gap-2">
                        <button type="button" v-for="t in ['in','out','adjustment']" :key="t"
                            class="flex-1 py-2 rounded-lg text-[12px] font-medium transition cursor-pointer border"
                            :class="form.type === t ? 'bg-brand-600/20 border-brand-500 text-brand-300' : 'border-frost-2 text-ink-3 hover:text-ink'"
                            @click="form.type = t">{{ t === 'in' ? 'Stock In' : t === 'out' ? 'Stock Out' : 'Set To' }}</button>
                    </div>
                </div>
                <div><label class="heyd2c-label">Quantity</label><input v-model.number="form.quantity" type="number" min="1" class="heyd2c-input" /></div>
                <div><label class="heyd2c-label">Notes</label><input v-model="form.notes" class="heyd2c-input" placeholder="Reason…" /></div>
                <button type="submit" class="btn btn-primary w-full py-2.5" :disabled="form.processing">{{ form.processing ? 'Saving…' : 'Save' }}</button>
            </form>
        </div>
    </div>

    <!-- Movement History -->
    <div class="card overflow-hidden p-0">
        <div class="px-5 py-3 border-b border-frost-1"><h3 class="text-[14px] font-bold text-white">Movement History</h3></div>
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">Type</th>
                    <th class="text-right px-5 py-3">Qty</th>
                    <th class="text-right px-5 py-3">Balance After</th>
                    <th class="text-left px-5 py-3">Notes</th>
                    <th class="text-left px-5 py-3">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="m in movements" :key="m.id" class="hover:bg-brand-600/5">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <component :is="typeIcons[m.type]" :size="14" :class="typeColors[m.type]" />
                            <span class="capitalize" :class="typeColors[m.type]">{{ m.type }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-right font-mono font-medium" :class="m.quantity > 0 ? 'text-emerald' : 'text-rose'">{{ m.quantity > 0 ? '+' : '' }}{{ m.quantity }}</td>
                    <td class="px-5 py-3 text-right font-mono text-ink-2">{{ m.balance_after }}</td>
                    <td class="px-5 py-3 text-ink-3 text-[12px]">{{ m.notes || '—' }}</td>
                    <td class="px-5 py-3 text-ink-3 text-[11px]">{{ dateFmt(m.created_at) }}</td>
                </tr>
                <tr v-if="!movements.length">
                    <td colspan="5" class="px-5 py-6 text-center text-ink-3">No movements recorded yet.</td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
