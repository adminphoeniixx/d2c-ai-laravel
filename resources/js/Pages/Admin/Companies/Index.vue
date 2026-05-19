<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search, Pause, Play, LogIn } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    companies: { type: Object, required: true },
    filters:   { type: Object, default: () => ({}) },
});

const q = ref(props.filters.q ?? '');
let t;
watch(q, (v) => {
    clearTimeout(t);
    t = setTimeout(() => {
        router.get(route('admin.companies.index'), { q: v }, { preserveState: true, replace: true });
    }, 300);
});

function suspend(c) {
    if (!confirm(`Suspend "${c.name}"? Users will be locked out until reactivated.`)) return;
    router.post(route('admin.companies.suspend', c.id));
}
function activate(c) {
    router.post(route('admin.companies.activate', c.id));
}
function impersonate(c) {
    if (!confirm(`Impersonate owner of "${c.name}"?`)) return;
    router.post(route('admin.companies.impersonate', c.id));
}
</script>

<template>
    <Head title="Companies" />
    <AdminLayout>
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
            <div class="flex-1 max-w-md relative">
                <Search :size="15" class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-3" />
                <input v-model="q" placeholder="Search companies…" class="pulsara-input pl-9" />
            </div>
            <Link :href="route('admin.companies.create')" class="btn btn-primary">
                <Plus :size="15" /> New company
            </Link>
        </div>

        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-bg-3 text-[10px] font-mono uppercase tracking-wider text-ink-3">
                        <tr>
                            <th class="text-left px-5 py-3">Name</th>
                            <th class="text-left px-5 py-3">Slug</th>
                            <th class="text-left px-5 py-3">Plan</th>
                            <th class="text-left px-5 py-3">Status</th>
                            <th class="text-left px-5 py-3">Users</th>
                            <th class="text-left px-5 py-3">Created</th>
                            <th class="text-right px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-frost-1">
                        <tr v-for="c in companies.data" :key="c.id" class="hover:bg-brand-600/5 transition">
                            <td class="px-5 py-3">
                                <Link :href="route('admin.companies.show', c.id)" class="font-semibold text-white hover:text-brand-300">{{ c.name }}</Link>
                                <div class="text-[11px] text-ink-3">{{ c.email }}</div>
                            </td>
                            <td class="px-5 py-3 font-mono text-ink-2">{{ c.slug }}</td>
                            <td class="px-5 py-3"><span class="pill pill-info">{{ c.plan }}</span></td>
                            <td class="px-5 py-3">
                                <span class="pill" :class="c.status === 'active' ? 'pill-good' : 'pill-bad'">{{ c.status }}</span>
                            </td>
                            <td class="px-5 py-3 font-mono text-ink-2">{{ c.users_count }}</td>
                            <td class="px-5 py-3 text-ink-3">{{ new Date(c.created_at).toLocaleDateString() }}</td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <button class="btn btn-ghost btn-sm" title="Impersonate" @click="impersonate(c)">
                                        <LogIn :size="13" />
                                    </button>
                                    <button v-if="c.status === 'active'" class="btn btn-ghost btn-sm" title="Suspend" @click="suspend(c)">
                                        <Pause :size="13" />
                                    </button>
                                    <button v-else class="btn btn-ghost btn-sm" title="Activate" @click="activate(c)">
                                        <Play :size="13" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="companies.data.length === 0">
                            <td colspan="7" class="px-5 py-10 text-center text-ink-3">No companies found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AdminLayout>
</template>
