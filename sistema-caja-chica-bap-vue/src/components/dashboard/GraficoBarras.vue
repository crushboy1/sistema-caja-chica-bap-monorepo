<template>
    <div class="relative w-full h-full">
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

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const props = defineProps({
    chartData: {
        type: Object,
        required: true,
        default: () => ({ labels: [], datasets: [] })
    },
    stacked: {
        type: Boolean,
        default: false
    }
});

const colorPalette = [
    '#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6',
    '#06B6D4', '#F97316', '#84CC16', '#EC4899', '#6366F1'
];

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
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: props.stacked,
                position: 'top',
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
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
                stacked: props.stacked,
                ticks: {
                    callback: function (value) {
                        if (value >= 1000) return `S/ ${(value / 1000).toFixed(0)}K`;
                        return `S/ ${value}`;
                    }
                }
            },
            x: {
                stacked: props.stacked,
                ticks: {
                    // CAMBIO: Se añade una función para acortar etiquetas largas
                    callback: function (value) {
                        // 'this.getLabelForValue(value)' obtiene el texto completo de la etiqueta
                        const label = this.getLabelForValue(value);
                        if (label.length > 25) { // Si la etiqueta es muy larga
                            return label.substring(0, 22) + '...'; // La acorta y añade puntos suspensivos
                        }
                        return label; // De lo contrario, la devuelve completa
                    }
                }
            }
        }
    };
});

const currencyFormatter = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
});

</script>

<style scoped>
/* El canvas debe ocupar todo el espacio que le da su contenedor padre */
canvas {
    width: 100% !important;
    height: 100% !important;
}
</style>
