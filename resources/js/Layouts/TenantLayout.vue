<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import {
    LayoutDashboard, FileText, Receipt, ShoppingBag, Megaphone, IndianRupee,
    PackageSearch, Wallet, TrendingUp, Bot, Sparkles, ChevronDown, LogOut, User as UserIcon,
    Menu, X, RefreshCw, Plug, Settings as SettingsIcon, Users, FileSignature, ClipboardList,
    Clock, Truck, Building2, Boxes, HardHat, CalendarDays, CalendarCheck, AlertTriangle, Timer,
    Headphones, MessageSquare, HelpCircle, FolderKanban, Shield, UserPlus, Landmark,
    CreditCard, Zap, ShieldCheck,
} from 'lucide-vue-next';
import LogoMark from '@/Components/LogoMark.vue';
import FlashToasts from '@/Components/FlashToasts.vue';
import UpgradeModal from '@/Components/UpgradeModal.vue';
import KycModal from '@/Components/KycModal.vue';

const page  = usePage();
const user  = computed(() => page.props.auth.user);
const co    = computed(() => page.props.company);
const slug  = computed(() => co.value?.slug || (window.location.pathname.match(/\/app\/([^/]+)/)?.[1]) || '');
const limitStatus = computed(() => page.props.limit_status);
const kycStatus   = computed(() => page.props.kyc_status);

const menuOpen    = ref(false);
const mobileOpen  = ref(false);
const syncing     = ref(false);
const collapsedGroups = ref({});

// Auto-expand group containing the active route
function isGroupActive(group) {
    return group.items?.some(item => item.active);
}
function isGroupCollapsed(label) {
    if (!(label in collapsedGroups.value)) return !isGroupActive({ items: nav.value.find(g => g.label === label)?.items || [] });
    return collapsedGroups.value[label];
}
function toggleGroup(label) {
    collapsedGroups.value[label] = !isGroupCollapsed(label);
}

const lastSynced = ref('2 min ago');

const nav = computed(() => ([
    {
        label: 'OVERVIEW',
        items: [
            { name: 'Analytics',      icon: LayoutDashboard, href: route('tenant.dashboard',  { tenant: slug.value }), active: route().current('tenant.dashboard') },
            { name: 'P&L Report',     icon: FileText,        href: route('tenant.pnl',        { tenant: slug.value }), active: route().current('tenant.pnl') },
            { name: 'Expenses',       icon: Receipt,         href: route('tenant.expenses',   { tenant: slug.value }), active: route().current('tenant.expenses*') },
            { name: 'Orders',         icon: ShoppingBag,     href: route('tenant.orders',     { tenant: slug.value }), active: route().current('tenant.orders*') },
            { name: 'Ad Analytics',   icon: Megaphone,       href: route('tenant.ads',        { tenant: slug.value }), active: route().current('tenant.ads') },
            { name: 'Payment Gateway',icon: CreditCard,      href: route('tenant.pg.index',   { tenant: slug.value }), active: route().current('tenant.pg*') },
            { name: 'Logistics',      icon: Truck,           href: route('tenant.logistics.index', { tenant: slug.value }), active: route().current('tenant.logistics*') },
            { name: 'Banking',        icon: Landmark,        href: route('tenant.banking.index', { tenant: slug.value }), active: route().current('tenant.banking*') },
            { name: 'GST Reports',    icon: IndianRupee,     href: route('tenant.gst', { tenant: slug.value }), active: route().current('tenant.gst*') },
        ],
    },
    {
        label: 'HR',
        items: [
            { name: 'Employees',        icon: Users,          href: route('tenant.hr.employees',            { tenant: slug.value }), active: route().current('tenant.hr.employees*') },
            { name: 'Attendance',       icon: Clock,          href: route('tenant.hr.attendance',           { tenant: slug.value }), active: route().current('tenant.hr.attendance') },
            { name: 'Late Report',      icon: AlertTriangle,  href: route('tenant.hr.attendance.late-report',{ tenant: slug.value }), active: route().current('tenant.hr.attendance.late-report') },
            { name: 'Holidays',         icon: CalendarDays,   href: route('tenant.hr.holidays',             { tenant: slug.value }), active: route().current('tenant.hr.holidays*') },
            { name: 'Leave Types',      icon: CalendarCheck,  href: route('tenant.hr.leaves.types',         { tenant: slug.value }), active: route().current('tenant.hr.leaves.types*') },
            { name: 'Leave Requests',   icon: Timer,          href: route('tenant.hr.leaves.requests',      { tenant: slug.value }), active: route().current('tenant.hr.leaves.requests*') },
            { name: 'Leave Balances',   icon: ClipboardList,  href: route('tenant.hr.leaves.balances',      { tenant: slug.value }), active: route().current('tenant.hr.leaves.balances*') },
            { name: 'Letter Templates', icon: FileSignature,  href: route('tenant.hr.templates',            { tenant: slug.value }), active: route().current('tenant.hr.templates*') },
            { name: 'Create Letter',    icon: FileText,       href: route('tenant.hr.letters.create',       { tenant: slug.value }), active: route().current('tenant.hr.letters*') },
        ],
    },
    {
        label: 'OPERATIONS',
        badge: 'NEW',
        items: [
            { name: 'Payroll',          icon: Wallet,         href: route('tenant.payroll.index',          { tenant: slug.value }), active: route().current('tenant.payroll*') },
            { name: 'Inventory',        icon: Boxes,          href: route('tenant.inventory-mgmt.index',   { tenant: slug.value }), active: route().current('tenant.inventory-mgmt*') },
            { name: 'Purchase Orders',  icon: Truck,          href: route('tenant.purchase-orders.index',  { tenant: slug.value }), active: route().current('tenant.purchase-orders*') },
            { name: 'Vendors',          icon: Building2,      href: route('tenant.vendors.index',          { tenant: slug.value }), active: route().current('tenant.vendors*') },
            { name: 'Daily Exchange', icon: TrendingUp,     href: route('tenant.cashflow',               { tenant: slug.value }), active: route().current('tenant.cashflow') },
        ],
    },
    {
        label: 'AI',
        items: [
            { name: 'AI Copilot',  icon: Bot,      href: route('tenant.ai', { tenant: slug.value }), active: route().current('tenant.ai') },
            { name: 'AI Insights', icon: Sparkles, href: route('tenant.ai-insights', { tenant: slug.value }), active: route().current('tenant.ai-insights') },
        ],
    },
    {
        label: 'SUPPORT',
        items: [
            { name: 'Tickets',    icon: Headphones,    href: route('tenant.support.index',      { tenant: slug.value }), active: route().current('tenant.support.index') || route().current('tenant.support.show') },
            { name: 'Categories', icon: FolderKanban,  href: route('tenant.support.categories', { tenant: slug.value }), active: route().current('tenant.support.categories') },
            { name: 'FAQ',        icon: HelpCircle,    href: route('tenant.support.faqs',       { tenant: slug.value }), active: route().current('tenant.support.faqs') },
        ],
    },
    {
        label: '',
        items: [
            { name: 'KYC',      icon: ShieldCheck,  href: route('tenant.kyc',        { tenant: slug.value }), active: route().current('tenant.kyc') },
            { name: 'Team',     icon: Shield,       href: route('tenant.team.index', { tenant: slug.value }), active: route().current('tenant.team*') },
            { name: 'Settings', icon: SettingsIcon, href: route('tenant.settings',   { tenant: slug.value }), active: route().current('tenant.settings') },
        ],
    },
]));

const integrations = computed(() => {
    const metaAccount = null; // loaded via props if needed
    const googleAccount = null;
    return [
        { name: 'Shopify',     connected: co.value?.integrations?.shopify, href: route('tenant.integrations.shopify.show', { tenant: slug.value }) },
        { name: 'Meta Ads',    connected: co.value?.integrations?.meta_ads, href: route('tenant.integrations.meta.show', { tenant: slug.value }) },
        { name: 'Google Ads',  connected: co.value?.integrations?.google_ads, href: route('tenant.integrations.google-ads.show', { tenant: slug.value }) },
    ];
});

function syncNow() {
    syncing.value = true;
    // Trigger actual sync from connected integrations
    fetch(route('tenant.sync-all', { tenant: slug.value }), {
        method: 'POST',
        headers: {
            'X-XSRF-TOKEN': document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]?.replace(/%3D/g, '=') || '',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    }).then(() => {
        syncing.value = false;
        lastSynced.value = 'just now';
        router.reload();
    }).catch(() => {
        syncing.value = false;
        router.reload();
    });
}

const pageTitle = computed(() => {
    if (route().current('tenant.dashboard')) return 'Insights';
    if (route().current('tenant.pnl'))       return 'P&L Report';
    if (route().current('tenant.expenses'))  return 'Expenses';
    if (route().current('tenant.orders'))    return 'Orders';
    if (route().current('tenant.ads'))       return 'Ad Analytics';
    if (route().current('tenant.inventory')) return 'Inventory Forecast';
    if (route().current('tenant.payroll'))   return 'Payroll Intelligence';
    if (route().current('tenant.cashflow'))  return 'Cash Flow';
    if (route().current('tenant.ai'))        return 'AI Copilot';
    if (route().current('tenant.ai-insights')) return 'AI Insights';
    return 'heyd2c';
});

onMounted(() => {
    if (co.value?.id && window.Echo) {
        window.Echo.private(`company.${co.value.id}`)
            .listen('.sync.completed', () => {
                lastSynced.value = 'just now';
            });
    }
});
</script>

<template>
    <div class="min-h-screen bg-bg text-ink flex">
        <!-- Sidebar (desktop) -->
        <aside
            class="fixed inset-y-0 left-0 z-40 w-[220px] min-w-[220px] bg-bg-2 border-r border-frost-1 flex flex-col overflow-hidden transition-transform"
            :class="{ '-translate-x-full lg:translate-x-0': !mobileOpen }"
        >
            <div class="pointer-events-none absolute inset-0 opacity-[.35] bg-grid-fade bg-grid-sm" />
            <div class="pointer-events-none absolute -top-14 -right-14 h-[180px] w-[180px] rounded-full bg-brand-600/25 blur-2xl" />

            <div class="relative px-5 pt-5 pb-4 flex items-start justify-between">
                <div class="flex items-center gap-2.5">
                    <LogoMark :size="36" />
                    <div>
                        <div class="text-[15px] font-extrabold leading-none bg-gradient-to-br from-white to-brand-300 bg-clip-text text-transparent">heyd2c</div>
                        <div class="mt-0.5 font-mono text-[9px] tracking-[1.5px] text-brand-500">D2C Ops AI</div>
                    </div>
                </div>
                <button class="lg:hidden text-ink-2" @click="mobileOpen = false"><X :size="18" /></button>
            </div>

            <div class="relative mx-3 mb-3 rounded-[10px] bg-surface border border-frost-2 px-3 py-2 hover:border-frost-3 cursor-pointer transition-colors">
                <div class="font-mono text-[11px] tracking-wider text-ink-3">WORKSPACE</div>
                <div class="flex items-center justify-between mt-0.5">
                    <div class="text-[13px] font-semibold text-ink truncate">{{ co?.name }}</div>
                    <ChevronDown :size="13" class="text-ink-3" />
                </div>
            </div>

            <nav class="relative flex-1 px-2.5 overflow-y-auto">
                <div v-for="group in nav" :key="group.label">
                    <!-- Collapsible group header -->
                    <div v-if="group.label"
                        class="nav-group-label flex items-center gap-2 cursor-pointer select-none hover:text-ink-2 transition"
                        @click="toggleGroup(group.label)">
                        <ChevronDown :size="11" class="transition-transform duration-200" :class="isGroupCollapsed(group.label) ? '-rotate-90' : ''" />
                        <span>{{ group.label }}</span>
                        <span v-if="group.badge" class="font-bold text-brand-400 text-[9px]">{{ group.badge }}</span>
                    </div>
                    <!-- Non-labeled items (Settings) always visible -->
                    <div v-else class="nav-group-label"></div>
                    <!-- Collapsible items -->
                    <div v-show="!group.label || !isGroupCollapsed(group.label)" class="overflow-hidden">
                        <Link
                            v-for="item in group.items" :key="item.name"
                            :href="item.href"
                            class="nav-item"
                            :class="{ active: item.active }"
                        >
                            <component :is="item.icon" :size="15" class="flex-shrink-0 opacity-70" :class="{ 'opacity-100': item.active }" />
                            <span class="truncate">{{ item.name }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="relative border-t border-frost-1 p-3">
                <div class="relative">
                    <button
                        class="flex items-center gap-2.5 w-full px-2.5 py-2 rounded-lg hover:bg-brand-600/10 transition"
                        @click="menuOpen = !menuOpen"
                    >
                        <div class="h-8 w-8 rounded-full bg-gradient-to-br from-brand-600 to-fuchsia flex items-center justify-center text-white text-[12px] font-semibold">
                            {{ user?.initials || user?.name?.charAt(0) || '?' }}
                        </div>
                        <div class="flex-1 text-left min-w-0">
                            <div class="text-[12.5px] font-medium text-ink truncate">{{ user?.name }}</div>
                            <div class="text-[10.5px] text-ink-3 truncate">{{ user?.email }}</div>
                        </div>
                        <ChevronDown :size="13" class="text-ink-3 transition-transform" :class="{ 'rotate-180': menuOpen }" />
                    </button>
                    <div
                        v-if="menuOpen"
                        class="absolute bottom-full left-0 right-0 mb-2 bg-surface-2 border border-frost-2 rounded-[10px] shadow-glow-sm overflow-hidden"
                    >
                        <Link :href="route('profile.show')" class="flex items-center gap-2 px-3 py-2.5 text-[12.5px] text-ink-2 hover:bg-brand-600/10 hover:text-ink">
                            <UserIcon :size="14" /> Profile
                        </Link>
                        <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center gap-2 px-3 py-2.5 text-[12.5px] text-ink-2 hover:bg-rose/10 hover:text-rose">
                            <LogOut :size="14" /> Sign out
                        </Link>
                    </div>
                </div>
            </div>
        </aside>

        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-30 bg-black/60 lg:hidden"
            @click="mobileOpen = false"
        />

        <div class="flex-1 min-h-screen lg:ml-[220px] flex flex-col">
            <header class="sticky top-0 z-20 border-b border-frost-1 bg-bg/80 backdrop-blur-md">
                <div class="flex items-center justify-between px-5 lg:px-8 py-4">
                    <div class="flex items-center gap-3">
                        <button class="lg:hidden text-ink-2" @click="mobileOpen = true"><Menu :size="20" /></button>
                        <div>
                            <h1 class="text-[22px] font-bold tracking-tight text-white">{{ pageTitle }}</h1>
                            <div class="text-[12px] text-ink-3">Last synced {{ lastSynced }}</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button class="btn btn-primary" :disabled="syncing" @click="syncNow">
                            <RefreshCw :size="14" :class="{ 'animate-spin': syncing }" />
                            Sync Now
                        </button>
                        <div class="h-9 w-9 rounded-full bg-brand-600/20 border border-frost-2 flex items-center justify-center text-brand-300 text-[12px] font-semibold">
                            {{ (user?.initials || user?.name || '?').charAt(0) }}
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-5 lg:px-8 py-6">
                <slot />
            </main>

            <Link :href="route('tenant.ai', { tenant: slug })" class="fixed bottom-5 right-5 z-30 flex items-center gap-1.5 rounded-full bg-gradient-to-br from-brand-600 to-fuchsia px-4 py-2.5 shadow-glow animate-glow-pulse">
                <Bot :size="16" class="text-white" />
                <span class="text-[13px] font-semibold text-white">AI</span>
            </Link>
        </div>

        <FlashToasts />
        <UpgradeModal />
        <KycModal :kyc_status="kycStatus" />
    </div>
</template>
