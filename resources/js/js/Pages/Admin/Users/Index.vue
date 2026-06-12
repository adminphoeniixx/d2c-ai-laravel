<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Search, Plus, Pencil, Trash2, Shield, User } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
    users:   { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
})

const q          = ref(props.filters.q || '')
const adminsOnly = ref(!!props.filters.admins_only)

function search() {
    router.get(route('admin.users.index'), { q: q.value, admins_only: adminsOnly.value ? 1 : '' }, { preserveState: true, replace: true })
}

function destroy(user) {
    if (!confirm(`Delete user "${user.name}"? This cannot be undone.`)) return
    router.visit(route('admin.users.destroy', user.id), { method: 'delete', preserveScroll: true })
}

const roleColor = (name) => ({
    'super-admin': 'bg-rose-500/15 text-rose-400',
    'owner':       'bg-brand-500/15 text-brand-300',
    'staff':       'bg-slate-500/15 text-slate-400',
    'manager':     'bg-blue-500/15 text-blue-400',
}[name] || 'bg-slate-500/15 text-slate-400')
</script>

<template>
<Head title="Users" />
<AdminLayout>
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <h1 class="text-[20px] font-bold text-white">Users</h1>
            <Link :href="route('admin.users.create')" class="btn btn-primary btn-sm flex items-center gap-1.5 cursor-pointer">
                <Plus :size="13" /> New User
            </Link>
        </div>

        <!-- Filters -->
        <div class="flex items-center gap-3 flex-wrap">
            <div class="relative flex-1 max-w-sm">
                <Search :size="13" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
                <input v-model="q" @keyup.enter="search" placeholder="Search name or email…"
                    class="heyd2c-input pl-9 text-[12.5px]" />
            </div>
            <label class="flex items-center gap-2 text-[12px] text-ink-2 cursor-pointer select-none">
                <input type="checkbox" v-model="adminsOnly" @change="search" class="rounded" />
                Admins only
            </label>
            <span class="text-[11px] text-ink-3">{{ users.total }} users</span>
        </div>

        <!-- Table -->
        <div class="card overflow-hidden p-0">
            <table class="w-full text-[12.5px]">
                <thead>
                    <tr class="border-b border-frost-1">
                        <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium uppercase">Name</th>
                        <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium uppercase">Email</th>
                        <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium uppercase">Company</th>
                        <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium uppercase">Role</th>
                        <th class="text-left px-5 py-3 text-[10px] text-ink-3 font-medium uppercase">Type</th>
                        <th class="px-5 py-3 w-20"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-frost-1">
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-brand-600/5 transition">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-full bg-brand-600/20 flex items-center justify-center text-[10px] font-bold text-brand-300 shrink-0">
                                    {{ u.name?.charAt(0)?.toUpperCase() }}
                                </div>
                                <span class="font-medium text-white">{{ u.name }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-ink-2">{{ u.email }}</td>
                        <td class="px-5 py-3">
                            <Link v-if="u.company" :href="route('admin.companies.show', u.company.id)"
                                class="text-brand-300 hover:underline text-[12px] font-mono">
                                {{ u.company.name }}
                            </Link>
                            <span v-else class="text-ink-3">—</span>
                        </td>
                        <td class="px-5 py-3">
                            <span v-if="u.roles?.[0]" class="text-[10px] font-medium px-2 py-0.5 rounded-full capitalize"
                                :class="roleColor(u.roles[0].name)">
                                {{ u.roles[0].name }}
                            </span>
                            <span v-else class="text-ink-3">—</span>
                        </td>
                        <td class="px-5 py-3">
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                :class="u.is_admin ? 'bg-rose-500/15 text-rose-400' : 'bg-slate-500/15 text-slate-400'">
                                {{ u.is_admin ? 'ADMIN' : 'USER' }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2 justify-end">
                                <Link :href="route('admin.users.edit', u.id)"
                                    class="text-ink-3 hover:text-white transition cursor-pointer" title="Edit">
                                    <Pencil :size="13" />
                                </Link>
                                <button @click="destroy(u)"
                                    class="text-ink-3 hover:text-rose-400 transition cursor-pointer" title="Delete">
                                    <Trash2 :size="13" />
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!users.data?.length">
                        <td colspan="6" class="px-5 py-8 text-center text-ink-3 text-[12px]">No users found</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="users.last_page > 1" class="flex items-center justify-between text-[12px]">
            <span class="text-ink-3">Page {{ users.current_page }} of {{ users.last_page }}</span>
            <div class="flex gap-2">
                <Link v-if="users.prev_page_url" :href="users.prev_page_url" class="btn btn-ghost btn-sm cursor-pointer">← Prev</Link>
                <Link v-if="users.next_page_url" :href="users.next_page_url" class="btn btn-ghost btn-sm cursor-pointer">Next →</Link>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
