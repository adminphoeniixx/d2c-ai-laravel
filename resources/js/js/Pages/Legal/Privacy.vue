<script setup>
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
</script>

<template>
<Head title="Privacy Policy" />
<GuestLayout>
    <section class="max-w-3xl mx-auto py-16 px-6">
        <h1 class="text-[28px] font-extrabold text-white mb-2">Privacy Policy</h1>
        <p class="text-[12px] text-ink-3 mb-8">Last updated: 28 May 2026</p>

        <div class="prose-dark space-y-6 text-[14px] text-ink-2 leading-relaxed">

            <p>This Privacy Policy describes how <strong class="text-white">SaltyPay Software Private Limited</strong> ("Company", "we", "us", or "our"), operating under the brand <strong class="text-white">heyd2c</strong>, collects, uses, and shares information when you use our platform at <strong class="text-white">heyd2c.ai</strong> and related services (collectively, the "Service").</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">1. Information We Collect</h2>

            <h3 class="text-[15px] font-semibold text-white mt-4 mb-2">1.1 Information You Provide</h3>
            <p>When you register for an account or use our Service, we may collect:</p>
            <p class="pl-4 text-ink-3">Account information such as your name, email address, phone number, and password. Business information including company name, GSTIN, business category, and address. Employee data that you add to the platform such as names, phone numbers, attendance records, salary details, and documents. Integration credentials for third-party platforms like Shopify, WooCommerce, Meta Ads, and Google Ads. Payment and financial information related to your business operations.</p>

            <h3 class="text-[15px] font-semibold text-white mt-4 mb-2">1.2 Information Collected Automatically</h3>
            <p class="pl-4 text-ink-3">Device and browser information, IP address, usage patterns, cookies, and similar technologies. Log data including access times, pages viewed, and referring URLs. Location data when you use our attendance features (with your consent).</p>

            <h3 class="text-[15px] font-semibold text-white mt-4 mb-2">1.3 Information from Third Parties</h3>
            <p class="pl-4 text-ink-3">Order data, product data, and customer information synced from Shopify and WooCommerce. Ad performance data from Meta Ads and Google Ads. Marketplace data from Amazon, Flipkart, Myntra, and Nykaa integrations.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">2. How We Use Your Information</h2>
            <p>We use the information we collect to:</p>
            <p class="pl-4 text-ink-3">Provide, maintain, and improve the Service. Process and sync your business data across integrated platforms. Generate reports, analytics, and AI-powered insights for your business. Send OTP verification messages and service-related communications. Manage employee attendance, payroll, and HR functions. Comply with legal obligations including GST reporting and tax compliance. Detect and prevent fraud, abuse, and security issues.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">3. Data Storage & Multi-Tenancy</h2>
            <p>Each business on heyd2c operates within its own isolated database schema. Your data is never mixed with or accessible by other businesses on the platform. We use PostgreSQL schema-based multi-tenancy to ensure complete data isolation.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">4. Data Sharing</h2>
            <p>We do not sell your personal information. We may share information with:</p>
            <p class="pl-4 text-ink-3"><strong class="text-white">Service providers</strong> who assist in operating the platform (hosting, SMS delivery via MSG91, cloud storage via Bunny CDN). <strong class="text-white">Third-party integrations</strong> that you explicitly connect (Shopify, WooCommerce, Meta, Google) — only to sync your data as requested. <strong class="text-white">Legal authorities</strong> when required by law, court order, or government regulation.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">5. Data Security</h2>
            <p>We implement industry-standard security measures including encrypted data transmission (HTTPS/TLS), encrypted credential storage, access controls, and regular security reviews. However, no method of transmission over the Internet is 100% secure.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">6. Data Retention</h2>
            <p>We retain your data for as long as your account is active or as needed to provide the Service. Upon account deletion, your data (including the tenant database schema) will be removed within 30 days, except where retention is required by law.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">7. Your Rights</h2>
            <p>You have the right to:</p>
            <p class="pl-4 text-ink-3">Access and download your data at any time through the platform. Correct inaccurate information in your account. Delete your account and associated data. Withdraw consent for optional data processing. Lodge a complaint with the relevant data protection authority.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">8. Cookies</h2>
            <p>We use essential cookies for authentication and session management. We do not use advertising or tracking cookies. Third-party analytics (if any) are anonymized.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">9. Children's Privacy</h2>
            <p>The Service is not intended for individuals under 18 years of age. We do not knowingly collect personal information from children.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">10. Changes to This Policy</h2>
            <p>We may update this Privacy Policy from time to time. We will notify you of material changes via email or through the Service. Continued use of the Service after changes constitutes acceptance of the updated policy.</p>

            <h2 class="text-[18px] font-bold text-white mt-8 mb-3">11. Contact Us</h2>
            <div class="rounded-xl bg-bg-2 border border-frost-1 p-5 mt-3">
                <p class="text-white font-semibold">SaltyPay Software Private Limited</p>
                <p class="text-ink-3 text-[13px] mt-2">504, Village Choma, New Gurugram<br/>Gurugram, Haryana 122017, India</p>
                <p class="text-ink-3 text-[13px] mt-2">Phone: +91 8860616130<br/>Email: <a href="mailto:app@heyd2c.ai" class="text-brand-300 hover:underline">app@heyd2c.ai</a></p>
            </div>
        </div>
    </section>
</GuestLayout>
</template>
