<template>
    <div class="relative h-72">
        <div v-if="!chartData || !chartData.datasets || chartData.datasets[0].data.length === 0"
            class="flex items-center justify-center h-full text-gray-400">
            <div class="text-center">
                <BarChart3 class="w-12 h-12 mx-auto mb-2" />
                <p class="text-sm">No hay datos disponibles para mostrar</p>
            </div>
        </div>
        <Bar v-else :data="chartData" :options="chartOptions" />
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale } from 'chart.js';
import { BarChart3 } from 'lucide-vue-next';

// Se registran los componentes necesarios de Chart.js
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);
const props = defineProps({
    chartData: {
        type: Object,
        required: true,
        default: () => ({ labels: [], datasets: [] })
    },
});
const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false, // Se oculta la leyenda para un look más limpio.
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            callbacks: {
                label: function (context) {
                    const value = context.parsed.y;
                    return currencyFormatter.format(value);
                }
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: function (value) {
                    // Formateo simplificado para el eje Y
                    if (value >= 1000) return `S/ ${(value / 1000).toFixed(0)}K`;
                    return `S/ ${value}`;
                }
            }
        },
        x: {
            ticks: {
                maxRotation: 45, // Rotación para etiquetas largas
            }
        }
    }
}));

// Currency formatter 
const currencyFormatter = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
});

</script>

<style scoped>
canvas {
    max-height: 300px;
}
</style>