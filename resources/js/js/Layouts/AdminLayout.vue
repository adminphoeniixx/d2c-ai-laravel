<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    LayoutDashboard, Building2, Users, Shield, KeyRound,
    Activity, Plug, History, LogOut, Menu, X, CreditCard, Mail, ShieldCheck,
} from 'lucide-vue-next';
import LogoMark from '@/Components/LogoMark.vue';
import FlashToasts from '@/Components/FlashToasts.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const mobileOpen = ref(false);

const nav = [
    { label: 'OVERVIEW', items: [
        { name: 'Dashboard', icon: LayoutDashboard, route: 'admin.dashboard' },
    ]},
    { label: 'TENANTS', items: [
        { name: 'Companies', icon: Building2,   route: 'admin.companies.index' },
        { name: 'Users',     icon: Users,       route: 'admin.users.index' },
        { name: 'KYC',       icon: ShieldCheck, route: 'admin.kyc.index' },
    ]},
    { label: 'BILLING', items: [
        { name: 'Subscriptions', icon: CreditCard, route: 'admin.subscriptions.dashboard' },
        { name: 'Emails',        icon: Mail,        route: 'admin.emails.index' },
    ]},
    { label: 'ACCESS', items: [
        { name: 'Roles',       icon: Shield,   route: 'admin.roles.index' },
        { name: 'Permissions', icon: KeyRound, route: 'admin.permissions.index' },
    ]},
    { label: 'SYSTEM', items: [
        { name: 'Health',       icon: Activity, route: 'admin.system.health' },
        { name: 'Integrations', icon: Plug,     route: 'admin.system.integrations' },
        { name: 'Audit Log',    icon: History,  route: 'admin.system.audit' },
    ]},
];

const pageTitle = computed(() => {
    const current = route().current() || '';
    const map = {
        'admin.dashboard':           'Platform Overview',
        'admin.companies.index':     'Companies',
        'admin.companies.create':    'New Company',
        'admin.companies.show':      'Company Details',
        'admin.companies.edit':      'Edit Company',
        'admin.users.index':         'Users',
        'admin.roles.index':         'Roles',
        'admin.permissions.index':   'Permissions',
        'admin.system.health':       'System Health',
        'admin.system.integrations': 'Integration Logs',
        'admin.system.audit':        'Audit Trail',
    };
    return map[current] || 'heyd2c Admin';
});
</script>

<template>
    <div class="min-h-screen bg-bg text-ink flex">
        <aside
            class="fixed inset-y-0 left-0 z-40 w-[220px] bg-bg-2 border-r border-frost-1 flex flex-col overflow-hidden transition-transform"
            :class="{ '-translate-x-full lg:translate-x-0': !mobileOpen }"
        >
            <div class="pointer-events-none absolute inset-0 opacity-[.35] bg-grid-fade bg-grid-sm" />
            <div class="pointer-events-none absolute -top-14 -right-14 h-[180px] w-[180px] rounded-full bg-brand-600/25 blur-2xl" />

            <div class="relative px-5 pt-5 pb-4 flex items-start justify-between">
                <Link :href="route('admin.dashboard')" class="flex items-center gap-2.5">
                    <LogoMark :size="36" />
                    <div>
                        <div class="text-[15px] font-extrabold leading-none bg-gradient-to-br from-white to-brand-300 bg-clip-text text-transparent">heyd2c</div>
                        <div class="mt-0.5 font-mono text-[9px] tracking-[1.5px] text-brand-500">ADMIN CONSOLE</div>
                    </div>
                </Link>
                <button class="lg:hidden text-ink-2" @click="mobileOpen = false"><X :size="18" /></button>
            </div>

            <nav class="relative flex-1 px-2.5 overflow-y-auto">
                <div v-for="group in nav" :key="group.label">
                    <div class="nav-group-label">{{ group.label }}</div>
                    <Link
                        v-for="item in group.items" :key="item.name"
                        :href="route(item.route)"
                        class="nav-item"
                        :class="{ active: route().current(item.route) }"
                    >
                        <component :is="item.icon" :size="15" class="flex-shrink-0 opacity-70" />
                        <span class="truncate">{{ item.name }}</span>
                    </Link>
                </div>
            </nav>

            <div class="relative border-t border-frost-1 p-3">
                <div class="flex items-center gap-2.5 px-2.5 py-2">
                    <div class="h-8 w-8 rounded-full bg-gradient-to-br from-brand-600 to-fuchsia flex items-center justify-center text-white text-[12px] font-semibold">
                        {{ user?.initials || user?.name?.charAt(0) || '?' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[12.5px] font-medium text-ink truncate">{{ user?.name }}</div>
                        <div class="text-[10.5px] font-mono tracking-wider text-brand-400">ADMIN</div>
                    </div>
                    <Link :href="route('logout')" method="post" as="button" class="text-ink-3 hover:text-rose">
                        <LogOut :size="15" />
                    </Link>
                </div>
            </div>
        </aside>

        <div v-if="mobileOpen" class="fixed inset-0 z-30 bg-black/60 lg:hidden" @click="mobileOpen = false" />

        <div class="flex-1 min-h-screen lg:ml-[220px] flex flex-col">
            <header class="sticky top-0 z-20 border-b border-frost-1 bg-bg/80 backdrop-blur-md">
                <div class="flex items-center justify-between px-5 lg:px-8 py-4">
                    <div class="flex items-center gap-3">
                        <button class="lg:hidden text-ink-2" @click="mobileOpen = true"><Menu :size="20" /></button>
                        <h1 class="text-[22px] font-bold tracking-tight text-white">{{ pageTitle }}</h1>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-5 lg:px-8 py-6"><slot /></main>
        </div>

        <FlashToasts />
    </div>
</template>
