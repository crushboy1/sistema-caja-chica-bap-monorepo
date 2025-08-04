<template>
    <div class="w-full h-full">
        <!-- Controles del gráfico -->
        <div v-if="showControls" class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-4">

                <!-- Selector de período -->
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-600">Período:</label>
                    <select v-model="periodoSeleccionado" @change="aplicarFiltro"
                        class="text-sm border border-gray-300 rounded-md px-3 py-1 bg-white focus:outline-none focus:ring-2 focus:ring-verde-bap focus:border-transparent transition-all">
                        <option value="6">Últimos 6 meses</option>
                        <option value="12">Último año</option>
                        <option value="24">Últimos 2 años</option>
                        <option value="all">Todo el período</option>
                    </select>
                </div>

                <!-- Selector de tipo de datos -->
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-600">Mostrar:</label>
                    <select v-model="tipoVisualizacion"
                        class="text-sm border border-gray-300 rounded-md px-3 py-1 bg-white focus:outline-none focus:ring-2 focus:ring-verde-bap focus:border-transparent transition-all">
                        <option value="ambos">Gastos y Presupuesto</option>
                        <option value="gastos">Solo Gastos</option>
                        <option value="diferencia">Diferencia</option>
                    </select>
                </div>
            </div>

            <!-- Leyenda manual (opcional, Chart.js ya incluye su propia leyenda) -->
            <div class="flex items-center gap-4 text-sm">
                <div v-if="tipoVisualizacion === 'ambos' || tipoVisualizacion === 'gastos'"
                    class="flex items-center gap-2">
                    <div class="w-3 h-3 bg-verde-bap rounded-full"></div>
                    <span class="text-gray-600">Gastos Ejecutados</span>
                </div>
                <div v-if="tipoVisualizacion === 'ambos'" class="flex items-center gap-2">
                    <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                    <span class="text-gray-600">Presupuesto Asignado</span>
                </div>
                <div v-if="tipoVisualizacion === 'diferencia'" class="flex items-center gap-2">
                    <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                    <span class="text-gray-600">Diferencia</span>
                </div>
            </div>
        </div>

        <!-- Gráfico -->
        <div class="relative w-full h-full">
            <Line v-if="datosGrafico.datasets[0].data.length > 0" :data="datosGrafico" :options="chartOptions" />
            <div v-else class="flex items-center justify-center h-full text-gray-400">
                <div class="text-center">
                    <BarChart3 class="w-12 h-12 mx-auto mb-2" />
                    <p class="text-sm">No hay datos suficientes para mostrar el gráfico.</p>
                </div>
            </div>
        </div>

        <!-- Estadísticas resumidas -->
        <div v-if="showSummary" class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-4 border-t">
            <div class="text-center">
                <div class="text-sm text-gray-500">Promedio Mensual</div>
                <div class="text-lg font-semibold text-gray-800">{{ formatearMoneda(estadisticas.promedioMensual) }}
                </div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500">Mes Máximo</div>
                <div class="text-lg font-semibold text-verde-bap">{{ formatearMoneda(estadisticas.maximoMes) }}</div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500">Tendencia</div>
                <div class="text-lg font-semibold"
                    :class="estadisticas.tendencia >= 0 ? 'text-green-600' : 'text-red-600'">
                    {{ estadisticas.tendencia >= 0 ? '↗' : '↘' }} {{ Math.abs(estadisticas.tendencia).toFixed(1) }}%
                </div>
            </div>
            <div class="text-center">
                <div class="text-sm text-gray-500">Total Período</div>
                <div class="text-lg font-semibold text-gray-800">{{ formatearMoneda(estadisticas.totalPeriodo) }}</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { BarChart3 } from 'lucide-vue-next';
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
} from 'chart.js'
import { Line } from 'vue-chartjs'

// Registrar componentes de Chart.js
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    Filler
)

// --- PROPS Y EMITS ---
const props = defineProps({
    chartData: { type: Array, required: true, default: () => [] },
    showControls: { type: Boolean, default: true },
    showSummary: { type: Boolean, default: true },
    defaultPeriod: { type: [String, Number], default: 12 }
})

const emit = defineEmits(['period-changed'])

// --- ESTADO REACTIVO ---
const periodoSeleccionado = ref(props.defaultPeriod)
const tipoVisualizacion = ref('ambos')
const chartRef = ref(null)

const colores = {
    verdeBAP: '#10b981',
    azul: '#3b82f6',
    naranja: '#f59e0b'
}

// --- LÓGICA DE FORMATEO ---
const formatearMoneda = (value) => {
    if (value === null || value === undefined) return 'S/ 0'
    return new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(value)
}

const formatearMes = (fecha) => {
    if (!fecha) return '';
    // Se añade 'T00:00:00' para asegurar que la fecha se interprete en la zona horaria local y no en UTC.
    const date = new Date(fecha + 'T00:00:00');
    return date.toLocaleString('es-PE', { year: 'numeric', month: 'short' });
};

// --- LÓGICA PRINCIPAL (Computadas) ---
const datosProcesados = computed(() => {
    if (!props.chartData || props.chartData.length === 0) return []

    let datos = [...props.chartData]
    if (periodoSeleccionado.value !== 'all') {
        datos = datos.slice(-parseInt(periodoSeleccionado.value))
    }

    return datos.map(item => ({
        ...item,
        mes: formatearMes(item.mes || item.fecha),
        gastos: item.gastos || item.monto_gastado || 0,
        presupuesto: item.presupuesto || item.monto_asignado || 0,
        diferencia: (item.presupuesto || item.monto_asignado || 0) - (item.gastos || item.monto_gastado || 0)
    }))
})

const estadisticas = computed(() => {
    if (datosProcesados.value.length < 2) {
        return {
            promedioMensual: 0,
            maximoMes: 0,
            tendencia: 0,
            totalPeriodo: 0
        }
    }

    const gastos = datosProcesados.value.map(d => d.gastos)
    const total = gastos.reduce((sum, val) => sum + val, 0)
    const promedio = total / gastos.length
    const maximo = Math.max(...gastos)

    let tendencia = 0
    if (gastos.length >= 2) {
        const ultimo = gastos[gastos.length - 1]
        const penultimo = gastos[gastos.length - 2]
        if (penultimo > 0) {
            tendencia = ((ultimo - penultimo) / penultimo) * 100
        }
    }

    return {
        promedioMensual: promedio,
        maximoMes: maximo,
        tendencia: tendencia,
        totalPeriodo: total
    }
})

// --- CONFIGURACIÓN DEL GRÁFICO ---
const datosGrafico = computed(() => {
    const labels = datosProcesados.value.map(item => item.mes)
    const datasets = []

    if (tipoVisualizacion.value === 'ambos' || tipoVisualizacion.value === 'gastos') {
        datasets.push({
            label: 'Gastos Ejecutados',
            data: datosProcesados.value.map(item => item.gastos),
            borderColor: colores.verdeBAP,
            backgroundColor: colores.verdeBAP + '20',
            borderWidth: 3,
            pointBackgroundColor: colores.verdeBAP,
            pointBorderColor: colores.verdeBAP,
            pointRadius: 5,
            pointHoverRadius: 8,
            tension: 0.4
        })
    }

    if (tipoVisualizacion.value === 'ambos') {
        datasets.push({
            label: 'Presupuesto Asignado',
            data: datosProcesados.value.map(item => item.presupuesto),
            borderColor: colores.azul,
            backgroundColor: colores.azul + '20',
            borderWidth: 2,
            borderDash: [5, 5],
            pointBackgroundColor: colores.azul,
            pointBorderColor: colores.azul,
            pointRadius: 4,
            tension: 0.4
        })
    }

    if (tipoVisualizacion.value === 'diferencia') {
        datasets.push({
            label: 'Diferencia',
            data: datosProcesados.value.map(item => item.diferencia),
            borderColor: colores.naranja,
            backgroundColor: colores.naranja + '20',
            borderWidth: 3,
            pointBackgroundColor: colores.naranja,
            pointBorderColor: colores.naranja,
            pointRadius: 5,
            tension: 0.4
        })
    }

    return { labels, datasets }
})

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            mode: 'index',
            intersect: false,
            callbacks: {
                label: (context) => {
                    return `${context.dataset.label}: ${formatearMoneda(context.parsed.y)}`
                }
            }
        }
    },
    scales: {
        x: {
            display: true,
            title: {
                display: true,
                text: 'Período'
            },
            grid: {
                display: true,
                color: '#e5e7eb'
            }
        },
        y: {
            display: true,
            title: {
                display: true,
                text: 'Monto (S/)'
            },
            grid: {
                display: true,
                color: '#e5e7eb'
            },
            ticks: {
                callback: (value) => formatearMoneda(value)
            }
        }
    },
    interaction: {
        mode: 'nearest',
        axis: 'x',
        intersect: false
    }
}))

// --- MÉTODOS ---
const aplicarFiltro = () => {
    emit('period-changed', periodoSeleccionado.value)
}


</script>

<style scoped>
/* Estilos para el color verde BAP */
.text-verde-bap {
    color: var(--color-verde-bap, #10b981);
}

.bg-verde-bap {
    background-color: var(--color-verde-bap, #10b981);
}

.focus\:ring-verde-bap:focus {
    --tw-ring-color: var(--color-verde-bap, #10b981);
}

/* Transiciones suaves */
.transition-all {
    transition: all 0.3s ease;
}

/* Hover effects para controles */
select:hover {
    border-color: var(--color-verde-bap, #10b981);
}

canvas {
    width: 100% !important;
    height: 100% !important;
    max-width: 100% !important;
    max-height: 100% !important;
    display: block;
}
</style>