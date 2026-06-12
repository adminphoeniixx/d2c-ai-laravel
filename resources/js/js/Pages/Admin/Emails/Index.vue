<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Mail, Send, Settings, Eye, Check, X, Loader } from 'lucide-vue-next'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ templates: Array, settings: Object })

const editing      = ref(null)
const settingsOpen = ref(false)
const testEmail    = ref('')
const testLoading  = ref(null)
const testResult   = ref(null)
const connTesting  = ref(false)
const connResult   = ref(null)
const preview      = ref(null)

const settingsForm = ref({ ...props.settings })

function edit(tpl) {
    editing.value = { ...tpl }
    preview.value = null
}

function save() {
    router.put(route('admin.emails.update', editing.value.id), {
        subject:   editing.value.subject,
        html_body: editing.value.html_body,
        is_active: editing.value.is_active,
    }, { onSuccess: () => {} })
}

function saveSettings() {
    router.post(route('admin.emails.settings'), settingsForm.value)
}

async function testConn() {
    connTesting.value = true
    connResult.value  = null
    try {
        const res = await fetch(route('admin.emails.test-connection'), {
            headers: { 'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''), 'Accept': 'application/json' }
        })
        connResult.value = await res.json()
    } finally { connTesting.value = false }
}

async function sendTest(tpl) {
    if (!testEmail.value) return
    testLoading.value = tpl.id
    testResult.value  = null
    try {
        const res = await fetch(route('admin.emails.send-test', tpl.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
                'Accept': 'application/json',
            },
            body: JSON.stringify({ email: testEmail.value }),
        })
        testResult.value = await res.json()
    } finally { testLoading.value = null }
}

const slugLabel = {
    register:                 'Welcome / Registration',
    subscription_activated:   'Subscription Activated',
    subscription_expiring:    'Subscription Expiring Soon',
    subscription_expired:     'Subscription Expired',
}

const wrapVar = (v) => '{{' + v + '}}'

const slugColor = {
    register:               'text-brand-300',
    subscription_activated: 'text-emerald-400',
    subscription_expiring:  'text-amber-400',
    subscription_expired:   'text-rose-400',
}
</script>

<template>
<Head title="Email Templates" />
<AdminLayout>
    <div class="space-y-5">
        <div class="flex items-center justify-between">
            <h1 class="text-[20px] font-bold text-white">Email Templates</h1>
            <button @click="settingsOpen = !settingsOpen"
                class="btn btn-ghost btn-sm flex items-center gap-1.5 cursor-pointer">
                <Settings :size="13" /> Email Settings
            </button>
        </div>

        <!-- Settings panel -->
        <div v-if="settingsOpen" class="card space-y-4">
            <h3 class="text-[14px] font-semibold text-white">Brevo (Sendinblue) Settings</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                    <label class="heyd2c-label">Brevo API Key</label>
                    <div class="flex gap-2">
                        <input v-model="settingsForm.brevo_api_key" type="password"
                            class="heyd2c-input flex-1 font-mono" placeholder="xkeysib-…" />
                        <button @click="testConn" :disabled="connTesting"
                            class="btn btn-ghost btn-sm cursor-pointer shrink-0">
                            <Loader v-if="connTesting" :size="12" class="animate-spin" />
                            <span v-else>Test</span>
                        </button>
                    </div>
                    <div v-if="connResult" class="mt-1 text-[11px]"
                        :class="connResult.success ? 'text-emerald-400' : 'text-rose-400'">
                        {{ connResult.success ? '✓ Connected — ' + connResult.account : '✗ ' + connResult.error }}
                    </div>
                </div>
                <div>
                    <label class="heyd2c-label">Sender Email</label>
                    <input v-model="settingsForm.brevo_sender_email" type="email" class="heyd2c-input" />
                </div>
                <div>
                    <label class="heyd2c-label">Sender Name</label>
                    <input v-model="settingsForm.brevo_sender_name" class="heyd2c-input" />
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" v-model="settingsForm.emails_enabled" class="rounded" />
                    <label class="text-[12px] text-ink-2 cursor-pointer">Emails Enabled</label>
                </div>
            </div>
            <div class="flex justify-end">
                <button @click="saveSettings" class="btn btn-primary btn-sm cursor-pointer">Save Settings</button>
            </div>
        </div>

        <!-- Test email input -->
        <div class="flex items-center gap-2">
            <input v-model="testEmail" type="email" placeholder="Test email address"
                class="heyd2c-input text-[12px] w-56" />
            <span v-if="testResult" class="text-[11px]"
                :class="testResult.success ? 'text-emerald-400' : 'text-rose-400'">
                {{ testResult.message }}
            </span>
        </div>

        <!-- Template cards -->
        <div class="space-y-3">
            <div v-for="tpl in templates" :key="tpl.id" class="card">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <Mail :size="16" :class="slugColor[tpl.slug] || 'text-brand-300'" />
                        <div>
                            <h3 class="text-[14px] font-semibold text-white">{{ slugLabel[tpl.slug] || tpl.name }}</h3>
                            <p class="text-[11px] text-ink-3 font-mono">{{ tpl.slug }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="tpl.is_active ? 'bg-emerald-500/15 text-emerald-400' : 'bg-slate-500/15 text-slate-400'"
                            class="text-[10px] px-2 py-0.5 rounded-full">{{ tpl.is_active ? 'Active' : 'Disabled' }}</span>
                        <button @click="sendTest(tpl)" :disabled="!testEmail || testLoading === tpl.id"
                            class="btn btn-ghost btn-sm flex items-center gap-1 cursor-pointer disabled:opacity-40">
                            <Loader v-if="testLoading === tpl.id" :size="11" class="animate-spin" />
                            <Send v-else :size="11" />
                            Test
                        </button>
                        <button @click="edit(tpl)" class="btn btn-ghost btn-sm cursor-pointer">Edit</button>
                    </div>
                </div>

                <div class="text-[12px] text-ink-3">
                    <span class="text-ink-2">Subject: </span>{{ tpl.subject }}
                </div>
                <div v-if="tpl.variables?.length" class="flex flex-wrap gap-1 mt-2">
                    <span v-for="v in tpl.variables" :key="v"
                        class="bg-frost-1 text-ink-3 text-[10px] px-1.5 py-0.5 rounded font-mono">
                        {{ wrapVar(v) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Edit modal -->
        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="editing = null">
            <div class="bg-bg-2 border border-frost-1 rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between px-5 py-4 border-b border-frost-1">
                    <h3 class="text-[15px] font-bold text-white">Edit — {{ slugLabel[editing.slug] || editing.name }}</h3>
                    <button @click="editing = null" class="text-ink-3 hover:text-white cursor-pointer"><X :size="18" /></button>
                </div>
                <div class="flex-1 overflow-y-auto p-5 space-y-3">
                    <!-- Active toggle -->
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" v-model="editing.is_active" />
                        <span class="text-[12px] text-ink-2">Active (send this email)</span>
                    </label>

                    <!-- Subject -->
                    <div>
                        <label class="heyd2c-label">Subject Line</label>
                        <input v-model="editing.subject" class="heyd2c-input" />
                    </div>

                    <!-- HTML editor -->
                    <div class="flex gap-2 mb-1">
                        <label class="heyd2c-label flex-1">HTML Body</label>
                        <button @click="preview = editing.html_body"
                            class="text-[11px] text-brand-300 hover:underline flex items-center gap-1 cursor-pointer">
                            <Eye :size="11" /> Preview
                        </button>
                    </div>
                    <textarea v-model="editing.html_body" rows="20"
                        class="heyd2c-input w-full font-mono text-[11px] leading-relaxed resize-y"></textarea>

                    <!-- Variables reference -->
                    <div>
                        <p class="text-[10px] text-ink-3 mb-1">Available variables:</p>
                        <div class="flex flex-wrap gap-1">
                            <span v-for="v in editing.variables" :key="v"
                                class="bg-frost-1 text-ink-3 text-[10px] px-1.5 py-0.5 rounded font-mono cursor-pointer hover:text-white"
                                @click="editing.html_body += wrapVar(v)">
                                {{ wrapVar(v) }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-5 py-4 border-t border-frost-1">
                    <button @click="editing = null" class="btn btn-ghost cursor-pointer">Cancel</button>
                    <button @click="save" class="btn btn-primary cursor-pointer flex items-center gap-1.5">
                        <Check :size="13" /> Save Template
                    </button>
                </div>
            </div>
        </div>

        <!-- HTML Preview modal -->
        <div v-if="preview" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-4" @click.self="preview = null">
            <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-auto">
                <div class="flex justify-between items-center p-3 border-b border-gray-200">
                    <span class="text-[13px] font-medium text-gray-700">Email Preview</span>
                    <button @click="preview = null" class="text-gray-400 hover:text-gray-600 cursor-pointer"><X :size="16" /></button>
                </div>
                <iframe :srcdoc="preview" class="w-full" style="height:600px; border:none;"></iframe>
            </div>
        </div>
    </div>
</AdminLayout>
</template>
