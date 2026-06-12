<script setup>
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
</script>

<template>
<Head title="Terms of Service" />
<GuestLayout>
    <section class="max-w-3xl mx-auto py-16 px-6">
        <h1 class="text-[28px] font-extrabold text-white mb-2">Terms of Service</h1>
        <p class="text-[12px] text-ink-3 mb-8">Last updated: 28 May 2026</p>

        <div class="prose-dark space-y-6 text-[14px] text-ink-2 leading-relaxed">

            <p>These Terms of Service ("Terms") govern your use of the <strong class="text-white">heyd2c</strong> platform operated by <strong class="text-white">SaltyPay Software Private Limited</strong> ("Company", "we", "us"). By accessing or using heyd2c.ai ("Service"), you agree to these Terms.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">1. Account Registration</h2>
            <p>To use the Service, you must create an account and provide accurate, complete information. You are responsible for maintaining the security of your account credentials. You must be at least 18 years old and have the legal authority to enter into these Terms on behalf of your business.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">2. Service Description</h2>
            <p>heyd2c is a multi-tenant SaaS platform for direct-to-consumer (D2C) brands that provides unified operations management including order syncing from Shopify and WooCommerce, expense tracking, HR and attendance management, payroll processing, GST reporting, marketplace integrations, and AI-powered business insights.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">3. Subscription & Payment</h2>
            <p>The Service offers a 14-day free trial. After the trial period, continued use requires a paid subscription. Subscription fees are billed as per the plan selected. All fees are non-refundable unless otherwise stated. We reserve the right to change pricing with 30 days' prior notice.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">4. Your Data</h2>
            <p>You retain ownership of all data you upload to or create within the Service ("Your Data"). You grant us a limited license to process Your Data solely to provide the Service. Each tenant's data is stored in an isolated database schema and is not accessible by other tenants. We do not use Your Data for advertising, profiling, or any purpose other than providing the Service.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">5. Acceptable Use</h2>
            <p>You agree not to:</p>
            <p class="pl-4 text-ink-3">Use the Service for any unlawful purpose or in violation of any applicable laws. Attempt to access other tenants' data or circumvent security measures. Reverse engineer, decompile, or disassemble any part of the Service. Use the Service to store or transmit malicious code. Resell, sublicense, or redistribute the Service without our written consent. Upload content that infringes on intellectual property rights of others.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">6. Third-Party Integrations</h2>
            <p>The Service integrates with third-party platforms including Shopify, WooCommerce, Meta Ads, Google Ads, Amazon, Flipkart, and others. Your use of these integrations is subject to the respective third party's terms and policies. We are not responsible for the availability, accuracy, or actions of third-party services.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">7. Employee & HR Data</h2>
            <p>If you use the HR, attendance, or payroll features, you are responsible for ensuring you have appropriate consent from your employees to process their data through the Service. You are responsible for the accuracy of salary, tax, and statutory compliance data entered into the platform.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">8. Service Availability</h2>
            <p>We strive to maintain 99.9% uptime but do not guarantee uninterrupted service. We may perform scheduled maintenance with advance notice. We are not liable for downtime caused by factors beyond our control.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">9. Limitation of Liability</h2>
            <p>To the maximum extent permitted by law, we shall not be liable for any indirect, incidental, special, consequential, or punitive damages, or any loss of profits, revenue, data, or business opportunities. Our total liability shall not exceed the amount paid by you in the 12 months preceding the claim.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">10. Indemnification</h2>
            <p>You agree to indemnify and hold us harmless from any claims, damages, or expenses arising from your use of the Service, your violation of these Terms, or your violation of any rights of a third party.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">11. Termination</h2>
            <p>Either party may terminate the agreement at any time. Upon termination, your access to the Service will be suspended. Your data will be retained for 30 days after termination, during which you may request an export. After 30 days, your data will be permanently deleted. We may suspend or terminate your account immediately if you violate these Terms.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">12. Governing Law</h2>
            <p>These Terms are governed by the laws of India. Any disputes shall be subject to the exclusive jurisdiction of the courts in Gurugram, Haryana.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">13. Changes to Terms</h2>
            <p>We may modify these Terms at any time. Material changes will be communicated via email or through the Service at least 30 days in advance. Continued use after changes constitutes acceptance.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">14. Contact</h2>
            <div class="rounded-xl bg-bg-2 border border-frost-1 p-5 mt-3">
                <p class="text-white font-semibold">SaltyPay Software Private Limited</p>
                <p class="text-ink-3 text-[13px] mt-2">504, Village Choma, New Gurugram<br/>Gurugram, Haryana 122017, India</p>
                <p class="text-ink-3 text-[13px] mt-2">Phone: +91 8860616130<br/>Email: <a href="mailto:app@heyd2c.ai" class="text-brand-300 hover:underline">app@heyd2c.ai</a></p>
            </div>
        </div>
    </section>
</GuestLayout>
</template>
