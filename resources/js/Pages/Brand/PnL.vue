<script setup>

import PulsaraLayout from '@/Layouts/PulsaraLayout.vue'
import TopHeader from '@/Components/Pulsara/TopHeader.vue'
import ProfitLossStatement from '@/Components/Pulsara/ProfitLossStatement.vue'

</script>

<template>

<PulsaraLayout>

<TopHeader />

<div class="p-8 grid grid-cols-2 gap-6">

<ProfitLossStatement />

<div class="bg-[#121a30] rounded-xl border border-white/5 p-6">

<h2 class="text-lg font-semibold mb-4">
Monthly Profit Trend
</h2>

<div class="h-64 flex items-center justify-center text-gray-400">
Chart will come here
</div>

</div>

</div>

</PulsaraLayout>

</template>