<script setup>
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
</script>

<template>
<Head title="Refund Policy" />
<GuestLayout>
    <section class="max-w-3xl mx-auto py-16 px-6">
        <h1 class="text-[28px] font-extrabold text-white mb-2">Refund & Cancellation Policy</h1>
        <p class="text-[12px] text-ink-3 mb-8">Last updated: 28 May 2026</p>

        <div class="prose-dark space-y-6 text-[14px] text-ink-2 leading-relaxed">

            <p>This policy outlines the refund and cancellation terms for <strong class="text-white">heyd2c</strong>, operated by <strong class="text-white">SaltyPay Software Private Limited</strong>.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">1. Free Trial</h2>
            <p>heyd2c offers a 14-day free trial for all new accounts. No payment is required during the trial period. You may cancel at any time during the trial without any charges.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">2. Subscription Cancellation</h2>
            <p>You may cancel your subscription at any time from your account settings. Upon cancellation, your access will continue until the end of the current billing period. No further charges will be made after cancellation. Your data will be retained for 30 days after the subscription ends, after which it will be permanently deleted.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">3. Refund Policy</h2>
            <p>Subscription fees are generally non-refundable. However, we may offer a refund in the following cases:</p>
            <p class="pl-4 text-ink-3"><strong class="text-white">Service disruption:</strong> If the platform is unavailable for more than 72 consecutive hours due to issues on our end. <strong class="text-white">Billing error:</strong> If you were charged incorrectly or for a plan you did not select. <strong class="text-white">Within 7 days:</strong> If you cancel within 7 days of your first paid subscription and have not extensively used the Service.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">4. How to Request a Refund</h2>
            <p>To request a refund, email us at <a href="mailto:app@heyd2c.ai" class="text-brand-300 hover:underline">app@heyd2c.ai</a> with your account email and the reason for the refund. Refund requests are reviewed within 5 business days. Approved refunds are processed within 7-10 business days to the original payment method.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">5. Downgrade</h2>
            <p>You may downgrade your plan at any time. The downgrade takes effect at the start of the next billing cycle. No partial refunds are issued for downgrades mid-cycle.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">6. Contact</h2>
            <div class="rounded-xl bg-bg-2 border border-frost-1 p-5 mt-3">
                <p class="text-white font-semibold">SaltyPay Software Private Limited</p>
                <p class="text-ink-3 text-[13px] mt-2">504, Village Choma, New Gurugram<br/>Gurugram, Haryana 122017, India</p>
                <p class="text-ink-3 text-[13px] mt-2">Phone: +91 8860616130<br/>Email: <a href="mailto:app@heyd2c.ai" class="text-brand-300 hover:underline">app@heyd2c.ai</a></p>
            </div>
        </div>
    </section>
</GuestLayout>
</template>
