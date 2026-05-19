<script setup>
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, XCircle } from 'lucide-vue-next';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    checks: { type: Object, required: true },
    php:    { type: Object, required: true },
    octane: { type: Object, required: true },
});
</script>

<template>
    <Head title="System Health" />
    <AdminLayout>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-5">
            <div v-for="(c, name) in checks" :key="name" class="card">
                <div class="flex items-start justify-between mb-2">
                    <div class="text-[11px] font-mono uppercase tracking-widest text-ink-3">{{ name }}</div>
                    <CheckCircle2 v-if="c.ok" :size="18" class="text-emerald" />
                    <XCircle v-else :size="18" class="text-rose" />
                </div>
                <div class="text-[13px] text-ink break-all">{{ c.detail }}</div>
                <div v-if="c.tenant_schemas != null" class="mt-2 text-[11px] font-mono text-brand-400">
                    {{ c.tenant_schemas }} tenant schema{{ c.tenant_schemas === 1 ? '' : 's' }}
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-3">Runtime</h3>
                <dl class="text-[13px] space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-ink-3 font-mono text-[11px] tracking-wider">PHP</dt>
                        <dd class="font-mono">{{ php.version }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-3 font-mono text-[11px] tracking-wider">Swoole</dt>
                        <dd><span class="pill" :class="php.swoole ? 'pill-good' : 'pill-bad'">{{ php.swoole ? 'Loaded' : 'Missing' }}</span></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-3 font-mono text-[11px] tracking-wider">OPcache</dt>
                        <dd><span class="pill" :class="php.opcache ? 'pill-good' : 'pill-info'">{{ php.opcache ? 'Active' : 'Idle' }}</span></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-3 font-mono text-[11px] tracking-wider">Memory</dt>
                        <dd class="font-mono">{{ php.memory_limit }}</dd>
                    </div>
                </dl>
            </div>

            <div class="card">
                <h3 class="text-[15px] font-bold text-white mb-3">Octane</h3>
                <dl class="text-[13px] space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-ink-3 font-mono text-[11px] tracking-wider">Server</dt>
                        <dd class="font-mono">{{ octane.server }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-ink-3 font-mono text-[11px] tracking-wider">Status</dt>
                        <dd>
                            <span class="pill" :class="octane.enabled ? 'pill-good' : 'pill-bad'">
                                {{ octane.enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </AdminLayout>
</template>
