<script setup>
import { ref, onMounted } from 'vue'
import Chart from 'chart.js/auto'
import TimeFilter from './TimeFilter.vue'

const canvasRef = ref(null)
const chart = ref(null)

const dataSets = {
  '7D': [200,300,400,500,600,650,700],
  '30D': [400,600,800,750,900,1100,1300],
  'YTD': [300,500,900,1200,1500,1700,2000]
}

const updateChart = (range) => {
  chart.value.data.datasets[0].data = dataSets[range]
  chart.value.update()
}

onMounted(() => {
  chart.value = new Chart(canvasRef.value, {
    type: 'line',
    data: {
      labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul'],
      datasets: [{
        label: 'Revenue',
        data: dataSets['30D'],
        borderColor: '#A855F7',
        tension: 0.4
      }]
    }
  })
})
</script>

<template>
  <div class="bg-[#111827] rounded-2xl border border-white/5 p-8 h-[500px]">
    <div class="flex justify-between mb-6">
      <div class="text-lg font-semibold">Revenue vs Expenses</div>
      <TimeFilter @change="updateChart" />
    </div>
    <canvas ref="canvasRef"></canvas>
  </div>
</template>