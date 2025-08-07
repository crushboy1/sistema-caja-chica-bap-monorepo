<template>
    <div class="relative h-72">
        <div v-if="!chartData || !chartData.datasets || chartData.datasets.length === 0 || chartData.datasets[0].data.length === 0"
            class="flex items-center justify-center h-full text-gray-400">
            <div class="text-center">
                <BarChart3 class="w-12 h-12 mx-auto mb-2" />
                <p class="text-sm">No hay datos disponibles para mostrar</p>
            </div>
        </div>
        <Bar v-else :data="processedChartData" :options="chartOptions" />
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
    // Nueva prop para activar el modo de barras apiladas
    stacked: {
        type: Boolean,
        default: false
    }
});

// Paleta de colores para los gráficos apilados
const colorPalette = [
    '#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6',
    '#06B6D4', '#F97316', '#84CC16', '#EC4899', '#6366F1'
];

// Procesamos los datos para añadir colores dinámicamente si es un gráfico apilado
const processedChartData = computed(() => {
    if (!props.chartData || !props.chartData.datasets) return { labels: [], datasets: [] };

    if (props.stacked) {
        return {
            ...props.chartData,
            datasets: props.chartData.datasets.map((dataset, index) => ({
                ...dataset,
                backgroundColor: colorPalette[index % colorPalette.length],
            }))
        };
    }
    return props.chartData;
});

const chartOptions = computed(() => {
    const options = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                // La leyenda solo se muestra si el gráfico es apilado, ya que tiene múltiples datasets
                display: props.stacked,
                position: 'top',
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                // Modo 'index' es mejor para barras apiladas, muestra todos los valores de la barra
                mode: props.stacked ? 'index' : 'nearest',
                intersect: false,
                callbacks: {
                    label: function (context) {
                        const label = context.dataset.label || '';
                        const value = context.parsed.y;
                        return `${label}: ${currencyFormatter.format(value)}`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                // Se activa el apilado en el eje Y si la prop 'stacked' es true
                stacked: props.stacked,
                ticks: {
                    callback: function (value) {
                        if (value >= 1000) return `S/ ${(value / 1000).toFixed(0)}K`;
                        return `S/ ${value}`;
                    }
                }
            },
            x: {
                // Se activa el apilado en el eje X si la prop 'stacked' es true
                stacked: props.stacked,
                ticks: {
                    maxRotation: 45,
                }
            }
        }
    };

    return options;
});

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
