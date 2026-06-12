<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Plus, UserPlus, Trash2, Shield, Mail, X } from 'lucide-vue-next';
import TenantLayout from '@/Layouts/TenantLayout.vue';

const props = defineProps({
    members: { type: Array, default: () => [] },
    pendingInvites: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    roleDescriptions: { type: Object, default: () => ({}) },
});

const slug = window.location.pathname.match(/\/app\/([^/]+)/)?.[1] || '';
const showInvite = ref(false);
const inviteForm = useForm({ email: '', role: 'staff' });

function submitInvite() {
    inviteForm.post(route('tenant.team.invite', { tenant: slug }), {
        onSuccess: () => { showInvite.value = false; inviteForm.reset(); inviteForm.role = 'staff'; },
    });
}
function cancelInvite(id) {
    router.delete(route('tenant.team.cancel-invite', { tenant: slug, invitation: id }));
}
function changeRole(userId, role) {
    router.put(route('tenant.team.update-role', { tenant: slug, userId }), { role });
}
function removeMember(userId, name) {
    if (confirm(`Remove ${name} from the team?`)) {
        router.delete(route('tenant.team.remove', { tenant: slug, userId }));
    }
}

const roleColors = {
    owner: 'bg-purple-500/20 text-purple-400', manager: 'bg-blue-500/20 text-blue-400',
    accountant: 'bg-emerald-500/20 text-emerald-400', hr: 'bg-pink-500/20 text-pink-400',
    sales: 'bg-orange-500/20 text-orange-400', staff: 'bg-ink-3/20 text-ink-3', viewer: 'bg-bg-3 text-ink-3',
};
</script>

<template>
<Head title="Team" />
<TenantLayout>
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-[20px] font-bold text-white">Team Management</h2>
                <p class="text-[12px] text-ink-3 mt-1">{{ members.length }} member{{ members.length !== 1 ? 's' : '' }}</p>
            </div>
            <button @click="showInvite = true" class="btn btn-primary btn-sm flex items-center gap-1 cursor-pointer">
                <UserPlus :size="14" /> Invite Member
            </button>
        </div>

        <!-- Role Overview -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-5">
            <div v-for="r in roles" :key="r.id" class="rounded-xl bg-bg-2 border border-frost-1 px-3 py-2 text-center">
                <div class="text-[14px] font-bold text-white">{{ members.filter(m => m.role === r.name).length }}</div>
                <div class="text-[10px] capitalize" :class="roleColors[r.name]?.split(' ')[1] || 'text-ink-3'">{{ r.name }}</div>
            </div>
        </div>

        <!-- Members -->
        <div class="card overflow-hidden mb-5">
            <div class="px-4 py-2.5 border-b border-frost-1">
                <h3 class="text-[13px] font-semibold text-white">Active Members</h3>
            </div>
            <div v-for="m in members" :key="m.id"
                class="px-4 py-3 border-b border-frost-1 last:border-0 hover:bg-brand-600/5 transition flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-9 w-9 rounded-full bg-brand-600/15 flex items-center justify-center text-[13px] font-bold text-brand-300">
                        {{ m.name?.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <div class="text-[13px] font-medium text-white">
                            {{ m.name }}
                            <span v-if="m.is_current" class="text-[9px] text-brand-300 ml-1">(you)</span>
                        </div>
                        <div class="text-[11px] text-ink-3">{{ m.email }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <select v-if="!m.is_current" :value="m.role" @change="changeRole(m.id, $event.target.value)"
                        class="heyd2c-input !py-1 text-[11px] w-28 cursor-pointer">
                        <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                    </select>
                    <span v-else class="px-2 py-0.5 rounded-full text-[10px] font-bold" :class="roleColors[m.role]">{{ m.role }}</span>
                    <button v-if="!m.is_current" @click="removeMember(m.id, m.name)" class="text-ink-3 hover:text-rose cursor-pointer"><Trash2 :size="14" /></button>
                </div>
            </div>
        </div>

        <!-- Pending Invitations -->
        <div v-if="pendingInvites.length" class="card overflow-hidden mb-5">
            <div class="px-4 py-2.5 border-b border-frost-1">
                <h3 class="text-[13px] font-semibold text-white flex items-center gap-1"><Mail :size="13" /> Pending Invitations</h3>
            </div>
            <div v-for="inv in pendingInvites" :key="inv.id"
                class="px-4 py-3 border-b border-frost-1 last:border-0 flex items-center justify-between">
                <div>
                    <div class="text-[13px] text-ink-2">{{ inv.email }}</div>
                    <div class="text-[10px] text-ink-3">
                        Role: <span class="capitalize">{{ inv.role }}</span> · Expires: {{ new Date(inv.expires_at).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) }}
                    </div>
                </div>
                <button @click="cancelInvite(inv.id)" class="text-ink-3 hover:text-rose cursor-pointer"><X :size="14" /></button>
            </div>
        </div>

        <!-- Role Descriptions -->
        <div class="card">
            <h3 class="text-[13px] font-semibold text-white mb-3 flex items-center gap-1"><Shield :size="13" /> Role Permissions</h3>
            <div class="space-y-2">
                <div v-for="r in roles" :key="r.id" class="flex items-start gap-3 text-[12px]">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold capitalize min-w-[80px] text-center" :class="roleColors[r.name]">{{ r.name }}</span>
                    <span class="text-ink-3">{{ roleDescriptions[r.name] || 'No description' }}</span>
                </div>
            </div>
        </div>

        <!-- Invite Modal -->
        <div v-if="showInvite" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm" @click.self="showInvite = false">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-sm mx-4 p-5">
                <h3 class="text-[16px] font-bold text-white mb-4">Invite Team Member</h3>
                <form @submit.prevent="submitInvite" class="space-y-3">
                    <div>
                        <label class="heyd2c-label">Email Address</label>
                        <input v-model="inviteForm.email" type="email" class="heyd2c-input" placeholder="colleague@company.com" required />
                        <div v-if="inviteForm.errors.email" class="mt-1 text-[11px] text-rose">{{ inviteForm.errors.email }}</div>
                    </div>
                    <div>
                        <label class="heyd2c-label">Role</label>
                        <select v-model="inviteForm.role" class="heyd2c-input">
                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                        </select>
                        <p class="mt-1 text-[10px] text-ink-3">{{ roleDescriptions[inviteForm.role] }}</p>
                    </div>
                    <div class="flex gap-2 pt-2">
                        <button type="submit" class="btn btn-primary flex-1 cursor-pointer" :disabled="inviteForm.processing">Send Invite</button>
                        <button type="button" class="btn btn-ghost flex-1 cursor-pointer" @click="showInvite = false">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</TenantLayout>
</template>
