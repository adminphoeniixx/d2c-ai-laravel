<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import LogoMark from '@/Components/LogoMark.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
</script>

<template>
    <div class="min-h-screen bg-bg text-ink overflow-x-hidden relative">
        <!-- Ambient glow -->
        <div class="pointer-events-none absolute -top-40 -right-40 h-[600px] w-[600px] rounded-full bg-brand-600/20 blur-[120px]" />
        <div class="pointer-events-none absolute top-1/2 -left-40 h-[500px] w-[500px] rounded-full bg-fuchsia/15 blur-[120px]" />

        <header class="relative z-10 border-b border-frost-1 backdrop-blur-sm">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                <Link :href="route('landing')" class="flex items-center gap-2.5">
                    <LogoMark />
                    <div>
                        <div class="text-[15px] font-extrabold leading-none bg-gradient-to-br from-white to-brand-300 bg-clip-text text-transparent">heyd2c</div>
                        <div class="mt-0.5 font-mono text-[9px] tracking-[1.5px] text-brand-500">D2C OPS AI</div>
                    </div>
                </Link>

                <nav class="hidden items-center gap-1 md:flex">
                    <Link :href="route('features')" class="nav-item" :class="{ active: route().current('features') }">Features</Link>
                    <Link :href="route('pricing')"  class="nav-item" :class="{ active: route().current('pricing') }">Pricing</Link>
                </nav>

                <div class="flex items-center gap-2">
                    <template v-if="!user">
                        <Link :href="route('otp.login')" class="btn btn-ghost">Log in</Link>
                        <Link :href="route('company.register')" class="btn btn-primary">Start free</Link>
                    </template>
                    <template v-else>
                        <Link :href="route('dashboard')" class="btn btn-primary">Dashboard →</Link>
                    </template>
                </div>
            </div>
        </header>

        <main class="relative z-10">
            <slot />
        </main>

        <footer class="relative z-10 border-t border-frost-1 mt-24">
            <div class="mx-auto max-w-7xl px-6 py-10 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="text-[12px] text-ink-3">© 2026 heyd2c · All rights reserved.</div>
                <div class="flex items-center gap-6 text-[12px] text-ink-2">
                    <Link :href="route('privacy')">Privacy</Link>
                    <Link :href="route('terms')">Terms</Link>
                    <Link href="#">Security</Link>
                    <Link href="#">Contact</Link>
                </div>
            </div>
        </footer>
    </div>
</template>
