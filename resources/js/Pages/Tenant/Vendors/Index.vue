<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, Edit, Trash2, X, Building2 } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    vendors: { type: Object, default: () => ({ data: [] }) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showForm = ref(false);
const editing = ref(null);

const form = useForm({ name: '', email: '', phone: '', gstin: '', address: '', city: '', state: '', pincode: '', contact_person: '', notes: '' });

function openAdd() { form.reset(); editing.value = null; showForm.value = true; }
function openEdit(v) {
    editing.value = v;
    Object.keys(form.data()).forEach(k => form[k] = v[k] || '');
    showForm.value = true;
}

function submit() {
    if (editing.value) {
        form.put(route('tenant.vendors.update', { tenant: slug, id: editing.value.id }), {
            onSuccess: () => { showForm.value = false; form.reset(); editing.value = null; },
        });
    } else {
        form.post(route('tenant.vendors.store', { tenant: slug }), {
            onSuccess: () => { showForm.value = false; form.reset(); },
        });
    }
}

function deleteVendor(v) {
    if (confirm(`Delete vendor "${v.name}"?`)) {
        router.delete(route('tenant.vendors.destroy', { tenant: slug, id: v.id }));
    }
}
</script>

<template>
<Head title="Vendors" />
<TenantLayout>
    <div class="flex items-center justify-between mb-5">
        <div>
            <h2 class="text-[20px] font-bold text-white">Vendors</h2>
            <p class="text-[12px] text-ink-3 mt-1">Manage your suppliers</p>
        </div>
        <button class="btn btn-primary" @click="openAdd"><Plus :size="14" /> Add Vendor</button>
    </div>

    <!-- Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" @click.self="showForm = false">
        <div class="card w-full max-w-lg mx-4 max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[16px] font-bold text-white">{{ editing ? 'Edit' : 'Add' }} Vendor</h3>
                <button class="text-ink-3 hover:text-ink cursor-pointer" @click="showForm = false"><X :size="18" /></button>
            </div>
            <form @submit.prevent="submit" class="space-y-3">
                <div><label class="pulsara-label">Company Name *</label><input v-model="form.name" class="pulsara-input" /><div v-if="form.errors.name" class="mt-1 text-[11px] text-rose">{{ form.errors.name }}</div></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="pulsara-label">Email</label><input v-model="form.email" type="email" class="pulsara-input" /></div>
                    <div><label class="pulsara-label">Phone</label><input v-model="form.phone" class="pulsara-input" /></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="pulsara-label">GSTIN</label><input v-model="form.gstin" class="pulsara-input font-mono" placeholder="22AAAAA0000A1Z5" /></div>
                    <div><label class="pulsara-label">Contact Person</label><input v-model="form.contact_person" class="pulsara-input" /></div>
                </div>
                <div><label class="pulsara-label">Address</label><textarea v-model="form.address" class="pulsara-input" rows="2"></textarea></div>
                <div class="grid grid-cols-3 gap-3">
                    <div><label class="pulsara-label">City</label><input v-model="form.city" class="pulsara-input" /></div>
                    <div><label class="pulsara-label">State</label><input v-model="form.state" class="pulsara-input" /></div>
                    <div><label class="pulsara-label">Pincode</label><input v-model="form.pincode" class="pulsara-input font-mono" /></div>
                </div>
                <div><label class="pulsara-label">Notes</label><textarea v-model="form.notes" class="pulsara-input" rows="2"></textarea></div>
                <button type="submit" class="btn btn-primary w-full py-2.5" :disabled="form.processing">{{ form.processing ? 'Saving…' : (editing ? 'Update Vendor' : 'Add Vendor') }}</button>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden p-0">
        <table class="w-full text-[13px]">
            <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                <tr>
                    <th class="text-left px-5 py-3">Name</th>
                    <th class="text-left px-5 py-3">Contact</th>
                    <th class="text-left px-5 py-3">GSTIN</th>
                    <th class="text-left px-5 py-3">City</th>
                    <th class="text-center px-5 py-3">POs</th>
                    <th class="text-right px-5 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-frost-1">
                <tr v-for="v in vendors.data" :key="v.id" class="hover:bg-brand-600/5 transition">
                    <td class="px-5 py-3 font-medium text-white">{{ v.name }}<div v-if="v.contact_person" class="text-[11px] text-ink-3">{{ v.contact_person }}</div></td>
                    <td class="px-5 py-3 text-ink-2 text-[12px]"><div v-if="v.email" class="font-mono">{{ v.email }}</div><div v-if="v.phone">{{ v.phone }}</div></td>
                    <td class="px-5 py-3 font-mono text-[11px] text-ink-3">{{ v.gstin || '—' }}</td>
                    <td class="px-5 py-3 text-ink-2">{{ v.city || '—' }}</td>
                    <td class="px-5 py-3 text-center font-mono">{{ v.purchase_orders_count || 0 }}</td>
                    <td class="px-5 py-3 text-right">
                        <button class="text-ink-3 hover:text-brand-300 mr-2 cursor-pointer" @click="openEdit(v)"><Edit :size="14" /></button>
                        <button class="text-ink-3 hover:text-rose cursor-pointer" @click="deleteVendor(v)"><Trash2 :size="14" /></button>
                    </td>
                </tr>
                <tr v-if="!vendors.data?.length">
                    <td colspan="6" class="px-5 py-8 text-center text-ink-3">No vendors yet. Click "Add Vendor" to get started.</td>
                </tr>
            </tbody>
        </table>
    </div>
</TenantLayout>
</template>
