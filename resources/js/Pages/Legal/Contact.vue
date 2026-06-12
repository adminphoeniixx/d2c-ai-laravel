<script setup>
import { Head } from '@inertiajs/vue3';
import { Mail, Phone, MapPin } from 'lucide-vue-next';
import GuestLayout from '@/Layouts/GuestLayout.vue';
</script>

<template>
<Head title="Contact Us" />
<GuestLayout>
    <section class="max-w-3xl mx-auto py-16 px-6">
        <h1 class="text-[28px] font-extrabold text-white mb-2">Contact Us</h1>
        <p class="text-[12px] text-ink-3 mb-8">We'd love to hear from you</p>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="card text-center">
                <div class="h-11 w-11 rounded-xl bg-brand-600/15 flex items-center justify-center mx-auto mb-3">
                    <Mail :size="20" class="text-brand-300" />
                </div>
                <div class="text-[13px] font-semibold text-white">Email</div>
                <a href="mailto:app@heyd2c.ai" class="text-[13px] text-brand-300 hover:underline mt-1 block">app@heyd2c.ai</a>
            </div>
            <div class="card text-center">
                <div class="h-11 w-11 rounded-xl bg-brand-600/15 flex items-center justify-center mx-auto mb-3">
                    <Phone :size="20" class="text-brand-300" />
                </div>
                <div class="text-[13px] font-semibold text-white">Phone</div>
                <a href="tel:+918860616130" class="text-[13px] text-brand-300 hover:underline mt-1 block">+91 8860616130</a>
            </div>
            <div class="card text-center">
                <div class="h-11 w-11 rounded-xl bg-brand-600/15 flex items-center justify-center mx-auto mb-3">
                    <MapPin :size="20" class="text-brand-300" />
                </div>
                <div class="text-[13px] font-semibold text-white">Office</div>
                <p class="text-[13px] text-ink-3 mt-1">Gurugram, Haryana</p>
            </div>
        </div>

        <div class="card">
            <h2 class="text-[18px] font-bold text-white mb-4">Company Details</h2>
            <div class="space-y-3 text-[14px] text-ink-2">
                <div class="flex justify-between border-b border-frost-1 pb-2">
                    <span class="text-ink-3">Legal Name</span>
                    <span class="text-white font-medium">SaltyPay Software Private Limited</span>
                </div>
                <div class="flex justify-between border-b border-frost-1 pb-2">
                    <span class="text-ink-3">Brand</span>
                    <span class="text-white font-medium">heyd2c</span>
                </div>
                <div class="flex justify-between border-b border-frost-1 pb-2">
                    <span class="text-ink-3">Address</span>
                    <span class="text-white font-medium text-right">504, Village Choma, New Gurugram<br/>Gurugram, Haryana 122017</span>
                </div>
                <div class="flex justify-between border-b border-frost-1 pb-2">
                    <span class="text-ink-3">Email</span>
                    <a href="mailto:app@heyd2c.ai" class="text-brand-300 hover:underline">app@heyd2c.ai</a>
                </div>
                <div class="flex justify-between">
                    <span class="text-ink-3">Phone</span>
                    <a href="tel:+918860616130" class="text-brand-300 hover:underline">+91 8860616130</a>
                </div>
            </div>
        </div>
    </section>
</GuestLayout>
</template>
