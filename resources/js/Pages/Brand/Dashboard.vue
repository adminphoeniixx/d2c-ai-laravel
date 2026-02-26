<script setup>
import PulsaraLayout from '@/Layouts/PulsaraLayout.vue'
import TopHeader from '@/Components/Pulsara/TopHeader.vue'
import ExpenseVoiceBar from '@/Components/Pulsara/ExpenseVoiceBar.vue'
import KpiCard from '@/Components/Pulsara/KpiCard.vue'
import RevenueChart from '@/Components/Pulsara/RevenueChart.vue'
import ProfitGauge from '@/Components/Pulsara/ProfitGauge.vue'
import MiniStatCard from '@/Components/Pulsara/MiniStatCard.vue'
</script>

<template>
  <PulsaraLayout>

    <TopHeader />

    <div class="p-8">

      <ExpenseVoiceBar />

      <div class="grid grid-cols-4 gap-6 mb-10">
        <KpiCard title="TOTAL REVENUE" :value="824340" prefix="₹" change="+18%" :positive="true"/>
        <KpiCard title="TOTAL EXPENSES" :value="540200" prefix="₹" change="+9%" :positive="false"/>
        <KpiCard title="NET PROFIT" :value="284140" prefix="₹" change="+32%" :positive="true"/>
        <KpiCard title="BLENDED ROAS" :value="39" prefix="" change="+5%" :positive="true"/>
      </div>

      <div class="grid grid-cols-3 gap-6">
        <div class="col-span-2">
          <RevenueChart />
        </div>
        <ProfitGauge :percentage="62" />
      </div>

      <div class="grid grid-cols-4 gap-6 mt-8">
        <MiniStatCard label="Average Order Value" value="₹1,240" change="+4%" :positive="true"/>
        <MiniStatCard label="CAC" value="₹320" change="-2%" :positive="true"/>
        <MiniStatCard label="Returning Customers" value="28%" change="+1%" :positive="true"/>
        <MiniStatCard label="Conversion Rate" value="2.8%" change="-0.4%" :positive="false"/>
      </div>

    </div>

  </PulsaraLayout>
</template>