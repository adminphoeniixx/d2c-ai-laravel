<script setup>
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
</script>

<template>
<Head title="Delete Your Account — heyd2c Attendance" />

<div class="min-h-screen bg-[#0d0d12] text-white font-sans">

    <!-- Header -->
    <header class="border-b border-white/10 px-6 py-4 flex items-center gap-3">
        <div class="h-8 w-8 rounded-lg bg-brand-600 flex items-center justify-center">
            <svg width="16" height="16" viewBox="0 0 20 20" fill="white">
                <path d="M10 2L3 7v11h5v-5h4v5h5V7L10 2z"/>
            </svg>
        </div>
        <span class="font-bold text-[16px]">heyd2c</span>
        <span class="text-ink-3 text-[13px] ml-1">· Attendance App</span>
    </header>

    <main class="max-w-2xl mx-auto px-6 py-14">

        <!-- Hero -->
        <div class="mb-10">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-400 text-[12px] font-medium mb-5">
                <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                Account Deletion
            </div>
            <h1 class="text-[32px] font-extrabold leading-tight mb-3">Delete Your Account</h1>
            <p class="text-[15px] text-[#9ca3af] leading-relaxed">
                You have the right to request deletion of your personal data from the heyd2c Attendance app.
                This page explains exactly what gets deleted, what is retained, and how to submit your request.
            </p>
        </div>

        <!-- What gets deleted -->
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <h2 class="text-[17px] font-bold mb-4 flex items-center gap-2">
                <span class="h-6 w-6 rounded-full bg-rose-500/15 text-rose-400 flex items-center justify-center text-[12px]">✕</span>
                What gets permanently deleted
            </h2>
            <ul class="space-y-3 text-[14px] text-[#d1d5db]">
                <li class="flex items-start gap-3">
                    <span class="text-rose-400 mt-0.5 flex-shrink-0">●</span>
                    <span><strong class="text-white">Face biometric data</strong> — your facial encoding used for face-recognition punch-in is immediately and permanently erased from all systems.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-rose-400 mt-0.5 flex-shrink-0">●</span>
                    <span><strong class="text-white">Attendance records</strong> — all your individual check-in and check-out records are deleted.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-rose-400 mt-0.5 flex-shrink-0">●</span>
                    <span><strong class="text-white">Leave requests</strong> — your pending and historical leave applications are removed.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-rose-400 mt-0.5 flex-shrink-0">●</span>
                    <span><strong class="text-white">Personal profile information</strong> — your name, phone number, email address, and home address are erased from our systems.</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-rose-400 mt-0.5 flex-shrink-0">●</span>
                    <span><strong class="text-white">Login sessions</strong> — all active sessions and authentication tokens are immediately revoked.</span>
                </li>
            </ul>
        </section>

        <!-- What is retained -->
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <h2 class="text-[17px] font-bold mb-4 flex items-center gap-2">
                <span class="h-6 w-6 rounded-full bg-amber-500/15 text-amber-400 flex items-center justify-center text-[12px]">!</span>
                What may be retained
            </h2>
            <p class="text-[13px] text-[#9ca3af] mb-3">The following data may be retained by your employer for legal and financial compliance purposes. heyd2c is a B2B platform — your employer, not heyd2c, is the data controller for your employment records.</p>
            <ul class="space-y-2 text-[14px] text-[#d1d5db]">
                <li class="flex items-start gap-3">
                    <span class="text-amber-400 mt-0.5 flex-shrink-0">●</span>
                    <span><strong class="text-white">Payroll records</strong> — salary history and payslips are subject to statutory retention requirements under Indian labour law (typically 3–5 years).</span>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-amber-400 mt-0.5 flex-shrink-0">●</span>
                    <span><strong class="text-white">Aggregate reports</strong> — anonymised summary data that cannot be linked back to you personally.</span>
                </li>
            </ul>
        </section>

        <!-- How to delete -->
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <h2 class="text-[17px] font-bold mb-5">How to delete your account</h2>

            <div class="space-y-5">
                <div class="flex items-start gap-4">
                    <div class="h-8 w-8 rounded-full bg-brand-600/20 border border-brand-600/40 text-[13px] font-bold text-brand-300 flex items-center justify-center flex-shrink-0">1</div>
                    <div>
                        <p class="text-[14px] font-semibold text-white mb-1">From the app (recommended)</p>
                        <p class="text-[13px] text-[#9ca3af]">Open the heyd2c Attendance app → tap your profile photo → scroll to the bottom → tap <strong class="text-white">Delete Account</strong>. Your data is deleted immediately.</p>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="h-8 w-8 rounded-full bg-brand-600/20 border border-brand-600/40 text-[13px] font-bold text-brand-300 flex items-center justify-center flex-shrink-0">2</div>
                    <div>
                        <p class="text-[14px] font-semibold text-white mb-1">Via API (for developers)</p>
                        <p class="text-[13px] text-[#9ca3af] mb-2">Send an authenticated DELETE request:</p>
                        <code class="block bg-black/40 border border-white/10 rounded-lg px-4 py-3 text-[12px] text-emerald-400 font-mono">
                            DELETE https://heyd2c.ai/api/v1/account<br/>
                            Authorization: Bearer &lt;your-token&gt;
                        </code>
                    </div>
                </div>

                <div class="flex items-start gap-4">
                    <div class="h-8 w-8 rounded-full bg-brand-600/20 border border-brand-600/40 text-[13px] font-bold text-brand-300 flex items-center justify-center flex-shrink-0">3</div>
                    <div>
                        <p class="text-[14px] font-semibold text-white mb-1">Via email request</p>
                        <p class="text-[13px] text-[#9ca3af]">Email us at <a href="mailto:privacy@heyd2c.ai" class="text-brand-300 hover:underline">privacy@heyd2c.ai</a> with the subject line <em>"Account Deletion Request"</em> and include your registered phone number. We will process your request within <strong class="text-white">7 business days</strong> and confirm via reply.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Timeline -->
        <section class="mb-8 rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <h2 class="text-[17px] font-bold mb-4">Deletion timeline</h2>
            <div class="grid grid-cols-2 gap-4 text-[13px]">
                <div class="rounded-xl bg-white/[0.03] border border-white/10 p-4">
                    <div class="text-[22px] font-extrabold text-white mb-1">Immediate</div>
                    <div class="text-[#9ca3af]">Face biometric data, session tokens, and login access are revoked instantly upon deletion.</div>
                </div>
                <div class="rounded-xl bg-white/[0.03] border border-white/10 p-4">
                    <div class="text-[22px] font-extrabold text-white mb-1">7 days</div>
                    <div class="text-[#9ca3af]">All remaining personal data is purged from active databases and backup systems within 7 days.</div>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section class="rounded-2xl border border-white/10 bg-white/[0.03] p-6">
            <h2 class="text-[17px] font-bold mb-2">Questions or concerns?</h2>
            <p class="text-[13px] text-[#9ca3af] mb-3">Our Data Protection team is here to help.</p>
            <a href="mailto:privacy@heyd2c.ai" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-brand-600/20 border border-brand-600/40 text-brand-300 text-[13px] font-medium hover:bg-brand-600/30 transition">
                privacy@heyd2c.ai
            </a>
            <p class="text-[12px] text-[#6b7280] mt-4">
                For the full Privacy Policy, visit <a href="/privacy" class="text-brand-300 hover:underline">heyd2c.ai/privacy</a>.<br/>
                SaltyPay Software Private Limited · heyd2c
            </p>
        </section>

    </main>
</div>
</template>
