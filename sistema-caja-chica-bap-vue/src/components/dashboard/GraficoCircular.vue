<template>
    <div class="w-full h-full">
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center h-64">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
        </div>

        <!-- Error State -->
        <div v-else-if="error" class="flex items-center justify-center h-64 text-red-500">
            <div class="text-center">
                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm">Error al cargar el gráfico</p>
            </div>
        </div>

        <!-- No Data State -->
        <div v-else-if="!hasData" class="flex items-center justify-center h-64 text-gray-400">
            <div class="text-center">
                <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                    </path>
                </svg>
                <p class="text-sm">No hay datos para mostrar</p>
            </div>
        </div>

        <!-- Chart -->
        <div v-else class="relative">
            <div class="chart-container" :style="{ height: chartHeight + 'px' }">
                <Doughnut :data="processedChartData" :options="chartOptions" :plugins="chartPlugins" />
            </div>

            <!-- Legend personalizada si está habilitada -->
            <div v-if="showCustomLegend" class="mt-4">
                <div class="flex flex-wrap justify-center gap-3">
                    <div v-for="(item, index) in processedChartData.datasets[0].data" :key="index"
                        class="flex items-center space-x-2 px-3 py-1 rounded-full bg-gray-50 hover:bg-gray-100 transition-colors cursor-pointer"
                        @click="toggleDataset(index)">
                        <div class="w-3 h-3 rounded-full flex-shrink-0"
                            :style="{ backgroundColor: processedChartData.datasets[0].backgroundColor[index] }"></div>
                        <span class="text-xs font-medium text-gray-700">
                            {{ processedChartData.labels[index] }}
                        </span>
                        <span class="text-xs text-gray-500">
                            {{ formatValue(item) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Estadísticas adicionales -->
            <div v-if="showStats" class="mt-4 pt-4 border-t border-gray-200">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="bg-blue-50 rounded-lg p-2">
                        <p class="text-xs text-blue-600 font-medium">Total</p>
                        <p class="text-sm font-bold text-blue-800">{{ formatValue(total) }}</p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-2">
                        <p class="text-xs text-green-600 font-medium">Mayor</p>
                        <p class="text-sm font-bold text-green-800">{{ formatValue(maxValue) }}</p>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-2">
                        <p class="text-xs text-orange-600 font-medium">Promedio</p>
                        <p class="text-sm font-bold text-orange-800">{{ formatValue(averageValue) }}</p>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-2">
                        <p class="text-xs text-purple-600 font-medium">Elementos</p>
                        <p class="text-sm font-bold text-purple-800">{{ itemCount }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Doughnut } from 'vue-chartjs';
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
    Title
} from 'chart.js';

// Registrar componentes de Chart.js
ChartJS.register(ArcElement, Tooltip, Legend, Title);

const props = defineProps({
    chartData: {
        type: [Object, Array],
        required: true
    },
    title: {
        type: String,
        default: ''
    },
    height: {
        type: Number,
        default: 300
    },
    showLegend: {
        type: Boolean,
        default: true
    },
    showCustomLegend: {
        type: Boolean,
        default: false
    },
    showStats: {
        type: Boolean,
        default: false
    },
    chartType: {
        type: String,
        default: 'doughnut', // 'doughnut' o 'pie'
        validator: (value) => ['doughnut', 'pie'].includes(value)
    },
    colorScheme: {
        type: String,
        default: 'default', // 'default', 'blue', 'green', 'purple', 'warm', 'cool'
        validator: (value) => ['default', 'blue', 'green', 'purple', 'warm', 'cool'].includes(value)
    },
    formatType: {
        type: String,
        default: 'number', // 'number', 'currency', 'percentage'
        validator: (value) => ['number', 'currency', 'percentage'].includes(value)
    },
    animate: {
        type: Boolean,
        default: true
    }
});

const loading = ref(false);
const error = ref(false);

// Esquemas de colores predefinidos
const colorSchemes = {
    default: [
        '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
        '#06B6D4', '#F97316', '#84CC16', '#EC4899', '#6366F1'
    ],
    blue: [
        '#1E40AF', '#3B82F6', '#60A5FA', '#93C5FD', '#BFDBFE',
        '#DBEAFE', '#EFF6FF', '#1E3A8A', '#1D4ED8', '#2563EB'
    ],
    green: [
        '#059669', '#10B981', '#34D399', '#6EE7B7', '#A7F3D0',
        '#D1FAE5', '#ECFDF5', '#047857', '#065F46', '#064E3B'
    ],
    purple: [
        '#7C3AED', '#8B5CF6', '#A78BFA', '#C4B5FD', '#DDD6FE',
        '#EDE9FE', '#F5F3FF', '#6D28D9', '#5B21B6', '#4C1D95'
    ],
    warm: [
        '#DC2626', '#EA580C', '#D97706', '#CA8A04', '#65A30D',
        '#16A34A', '#059669', '#0891B2', '#0284C7', '#2563EB'
    ],
    cool: [
        '#0F766E', '#0891B2', '#0284C7', '#2563EB', '#4F46E5',
        '#7C3AED', '#C026D3', '#DB2777', '#E11D48', '#DC2626'
    ]
};

// Formatters
const currencyFormatter = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN'
});

const numberFormatter = new Intl.NumberFormat('es-PE');

const percentageFormatter = new Intl.NumberFormat('es-PE', {
    style: 'percent',
    maximumFractionDigits: 1
});

// Computed properties
const chartHeight = computed(() => props.height);

const hasData = computed(() => {
    if (!props.chartData) return false;

    if (Array.isArray(props.chartData)) {
        return props.chartData.length > 0;
    }

    if (props.chartData.datasets && props.chartData.datasets[0]) {
        return props.chartData.datasets[0].data && props.chartData.datasets[0].data.length > 0;
    }

    return false;
});

const processedChartData = computed(() => {
    if (!hasData.value) return null;

    let data, labels;

    // Si los datos vienen como array de objetos
    if (Array.isArray(props.chartData)) {
        labels = props.chartData.map(item => item.name || item.label || item.categoria || Object.keys(item)[0]);
        data = props.chartData.map(item => item.value || item.total || item.monto || Object.values(item)[1] || Object.values(item)[0]);
    } else {
        // Si ya vienen en formato Chart.js
        labels = props.chartData.labels || [];
        data = props.chartData.datasets?.[0]?.data || [];
    }

    // Aplicar esquema de colores
    const colors = colorSchemes[props.colorScheme] || colorSchemes.default;
    const backgroundColor = labels.map((_, index) => colors[index % colors.length]);
    const borderColor = backgroundColor.map(color => color);

    return {
        labels,
        datasets: [{
            data,
            backgroundColor,
            borderColor,
            borderWidth: 2,
            hoverOffset: 8,
            hoverBorderWidth: 3
        }]
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: props.chartType === 'doughnut' ? '60%' : '0%',
    animation: props.animate ? {
        animateRotate: true,
        animateScale: true,
        duration: 1000,
        easing: 'easeOutQuart'
    } : false,
    plugins: {
        legend: {
            display: props.showLegend && !props.showCustomLegend,
            position: 'bottom',
            labels: {
                padding: 20,
                usePointStyle: true,
                font: {
                    size: 12,
                    family: 'Inter, system-ui, sans-serif'
                },
                generateLabels: (chart) => {
                    const data = chart.data;
                    if (data.labels.length && data.datasets.length) {
                        return data.labels.map((label, i) => {
                            const value = data.datasets[0].data[i];
                            return {
                                text: `${label}: ${formatValue(value)}`,
                                fillStyle: data.datasets[0].backgroundColor[i],
                                strokeStyle: data.datasets[0].borderColor[i],
                                lineWidth: data.datasets[0].borderWidth,
                                hidden: false,
                                index: i
                            };
                        });
                    }
                    return [];
                }
            }
        },
        tooltip: {
            backgroundColor: 'rgba(0, 0, 0, 0.8)',
            titleColor: '#fff',
            bodyColor: '#fff',
            borderColor: 'rgba(255, 255, 255, 0.1)',
            borderWidth: 1,
            cornerRadius: 8,
            displayColors: true,
            callbacks: {
                label: (context) => {
                    const label = context.label || '';
                    const value = context.parsed;
                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                    const percentage = ((value / total) * 100).toFixed(1);

                    return [
                        `${label}: ${formatValue(value)}`,
                        `Porcentaje: ${percentage}%`
                    ];
                }
            }
        },
        title: {
            display: !!props.title,
            text: props.title,
            font: {
                size: 16,
                weight: 'bold',
                family: 'Inter, system-ui, sans-serif'
            },
            color: '#374151',
            padding: {
                bottom: 20
            }
        }
    },
    interaction: {
        intersect: false,
        mode: 'point'
    }
}));

const chartPlugins = computed(() => [
    {
        id: 'centerText',
        beforeDraw: (chart) => {
            if (props.chartType === 'doughnut') {
                const { ctx, width, height } = chart;
                const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);

                ctx.restore();
                const fontSize = Math.min(width, height) * 0.08;
                ctx.font = `bold ${fontSize}px Inter, system-ui, sans-serif`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#374151';

                const centerX = width / 2;
                const centerY = height / 2;

                // Texto principal (total)
                ctx.fillText(formatValue(total), centerX, centerY - fontSize * 0.2);

                // Texto secundario
                ctx.font = `${fontSize * 0.5}px Inter, system-ui, sans-serif`;
                ctx.fillStyle = '#6B7280';
                ctx.fillText('Total', centerX, centerY + fontSize * 0.5);

                ctx.save();
            }
        }
    }
]);

// Estadísticas computadas
const total = computed(() => {
    if (!processedChartData.value) return 0;
    return processedChartData.value.datasets[0].data.reduce((a, b) => a + b, 0);
});

const maxValue = computed(() => {
    if (!processedChartData.value) return 0;
    return Math.max(...processedChartData.value.datasets[0].data);
});

const averageValue = computed(() => {
    if (!processedChartData.value) return 0;
    const data = processedChartData.value.datasets[0].data;
    return data.reduce((a, b) => a + b, 0) / data.length;
});

const itemCount = computed(() => {
    if (!processedChartData.value) return 0;
    return processedChartData.value.labels.length;
});

// Métodos
const formatValue = (value) => {
    if (typeof value !== 'number') return value;

    switch (props.formatType) {
        case 'currency':
            return currencyFormatter.format(value);
        case 'percentage':
            return percentageFormatter.format(value / 100);
        default:
            return numberFormatter.format(value);
    }
};

const toggleDataset = (index) => {
    // Funcionalidad para toggle de elementos en la leyenda personalizada
    // Se podría implementar para ocultar/mostrar elementos específicos
    console.log(`Toggle dataset ${index}`);
};

// Watchers
watch(() => props.chartData, () => {
    error.value = false;
}, { deep: true });

onMounted(() => {
    if (!hasData.value) {
        console.warn('GraficoCircular: No hay datos para mostrar');
    }
});
</script>

<style scoped>
.chart-container {
    position: relative;
    width: 100%;
}

/* Animaciones personalizadas */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Estilos para la leyenda personalizada */
.legend-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}
</style>