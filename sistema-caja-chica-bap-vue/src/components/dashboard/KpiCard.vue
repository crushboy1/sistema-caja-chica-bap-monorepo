<template>
    <div class="p-6 rounded-xl shadow-lg transition-all duration-300 transform hover:-translate-y-1 hover:shadow-xl"
        :class="[colorClasses.bg, { 'animate-pulse': loading, 'cursor-pointer': clickable }]" @click="handleClick"
        @mouseenter="isHovered = true" @mouseleave="isHovered = false">
        <!-- Loading State -->
        <div v-if="loading" class="flex items-center space-x-4">
            <div class="p-3 rounded-full bg-gray-200 animate-pulse">
                <div class="w-6 h-6 bg-gray-300 rounded"></div>
            </div>
            <div class="space-y-2">
                <div class="h-4 bg-gray-200 rounded w-20"></div>
                <div class="h-8 bg-gray-300 rounded w-16"></div>
            </div>
        </div>

        <!-- Normal State -->
        <div v-else class="flex items-center justify-between">
            <div class="flex items-center space-x-4 flex-1">
                <div class="p-3 rounded-full" :class="colorClasses.iconBg">
                    <component :is="iconComponent" class="w-6 h-6 transition-transform duration-200"
                        :class="[colorClasses.iconText, { 'scale-110': isHovered }]" />
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium mb-1" :class="colorClasses.text">{{ titulo }}</p>
                    <div class="flex items-baseline space-x-2">
                        <p class="text-3xl font-bold" :class="colorClasses.valueText">{{ formattedValue }}</p>
                        <!-- Indicador de tendencia -->
                        <div v-if="tendencia" class="flex items-center">
                            <component :is="tendenciaIcon" class="w-4 h-4" :class="tendenciaColorClass" />
                            <span class="text-xs font-medium ml-1" :class="tendenciaColorClass">
                                {{ Math.abs(tendencia) }}%
                            </span>
                        </div>
                    </div>
                    <!-- Descripción adicional -->
                    <p v-if="descripcion" class="text-xs mt-1" :class="colorClasses.description">
                        {{ descripcion }}
                    </p>
                </div>
            </div>

            <!-- Valor anterior o meta (opcional) -->
            <div v-if="valorAnterior || meta" class="text-right">
                <p v-if="valorAnterior" class="text-xs" :class="colorClasses.text">
                    Anterior: {{ formatValue(valorAnterior, formato) }}
                </p>
                <p v-if="meta" class="text-xs" :class="colorClasses.text">
                    Meta: {{ formatValue(meta, formato) }}
                </p>
                <!-- Barra de progreso hacia la meta -->
                <div v-if="meta && mostrarProgreso" class="mt-2">
                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                        <div class="h-1.5 rounded-full transition-all duration-500" :class="colorClasses.progressBar"
                            :style="{ width: progressPercentage + '%' }"></div>
                    </div>
                    <p class="text-xs mt-1" :class="colorClasses.text">
                        {{ progressPercentage }}% de la meta
                    </p>
                </div>
            </div>
        </div>

        <!-- Gráfico en miniatura (opcional) -->
        <div v-if="miniChart && miniChart.length > 0" class="mt-4">
            <div class="flex items-end justify-between h-8 space-x-1">
                <div v-for="(value, index) in miniChart" :key="index"
                    class="rounded-t transition-all duration-300 hover:opacity-80" :class="colorClasses.chartBar"
                    :style="{
                        height: (value / Math.max(...miniChart)) * 100 + '%',
                        width: `${100 / miniChart.length - 2}%`
                    }"></div>
            </div>
        </div>

        <!-- Footer con información adicional -->
        <div v-if="footerText || lastUpdated" class="mt-4 pt-3 border-t" :class="colorClasses.border">
            <div class="flex justify-between items-center text-xs" :class="colorClasses.footer">
                <span v-if="footerText">{{ footerText }}</span>
                <span v-if="lastUpdated" class="flex items-center">
                    <Clock class="w-3 h-3 mr-1" />
                    {{ formatDate(lastUpdated) }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, onUnmounted } from 'vue';
import {
    FolderKanban,
    CircleDollarSign,
    TrendingDown,
    PieChart,
    TrendingUp,
    Minus,
    Receipt,
    FileText,
    XCircle,
    Ban,
    AlertTriangle,
    Clock,
    Users,
    Target,
    Activity,
    BarChart3,
    Calendar,
    CheckCircle,
    AlertCircle,
    Info,
    FileClock,
    CheckCircle2
} from 'lucide-vue-next';

// --- DEFINICIÓN DE PROPS ---
const props = defineProps({
    titulo: {
        type: String,
        required: true,
    },
    valor: {
        type: [Number, String],
        required: true,
    },
    formato: {
        type: String,
        default: 'numero', // 'numero', 'moneda', 'porcentaje', 'texto'
        validator: (value) => ['numero', 'moneda', 'porcentaje', 'texto'].includes(value)
    },
    icono: {
        type: String,
        required: true,
    },
    color: {
        type: String,
        default: 'gray',
        validator: (value) => ['blue', 'green', 'orange', 'purple', 'red', 'yellow', 'indigo', 'teal', 'pink', 'gray', 'cyan'].includes(value)
    },
    loading: {
        type: Boolean,
        default: false
    },
    tendencia: {
        type: Number,
        default: null // Porcentaje positivo o negativo
    },
    valorAnterior: {
        type: Number,
        default: null
    },
    meta: {
        type: Number,
        default: null
    },
    mostrarProgreso: {
        type: Boolean,
        default: true
    },
    descripcion: {
        type: String,
        default: ''
    },
    footerText: {
        type: String,
        default: ''
    },
    lastUpdated: {
        type: [Date, String],
        default: null
    },
    miniChart: {
        type: Array,
        default: () => []
    },
    clickable: {
        type: Boolean,
        default: false
    },
    size: {
        type: String,
        default: 'normal', // 'small', 'normal', 'large'
        validator: (value) => ['small', 'normal', 'large'].includes(value)
    }
});

// --- EVENTOS ---
const emit = defineEmits(['click']);

// --- ESTADO REACTIVO ---
const isHovered = ref(false);

// --- LÓGICA DE FORMATEO ---
const formatValue = (value, format) => {
    if (value === null || value === undefined) return 'N/A';

    switch (format) {
        case 'moneda':
            return new Intl.NumberFormat('es-PE', {
                style: 'currency',
                currency: 'PEN',
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(Number(value));
        case 'porcentaje':
            return `${Number(value).toFixed(1)}%`;
        case 'texto':
            return String(value);
        default:
            return Number(value).toLocaleString('es-PE');
    }
};

const formattedValue = computed(() => formatValue(props.valor, props.formato));

// --- LÓGICA DE ICONOS ---
const iconMap = {
    FolderKanban,
    CircleDollarSign,
    TrendingDown,
    PieChart,
    TrendingUp,
    Receipt,
    FileText,
    XCircle,
    Ban,
    AlertTriangle,
    Clock,
    Users,
    Target,
    Activity,
    BarChart3,
    Calendar,
    CheckCircle,
    AlertCircle,
    Info,
    FileClock,
    CheckCircle2
};

const iconComponent = computed(() => iconMap[props.icono] || Info);

// --- LÓGICA DE TENDENCIA ---
const tendenciaIcon = computed(() => {
    if (!props.tendencia) return null;
    if (props.tendencia > 0) return TrendingUp;
    if (props.tendencia < 0) return TrendingDown;
    return Minus;
});

const tendenciaColorClass = computed(() => {
    if (!props.tendencia) return '';
    if (props.tendencia > 0) return 'text-green-600';
    if (props.tendencia < 0) return 'text-red-600';
    return 'text-gray-600';
});

// --- LÓGICA DE PROGRESO ---
const progressPercentage = computed(() => {
    if (!props.meta || !props.valor) return 0;
    const percentage = (Number(props.valor) / Number(props.meta)) * 100;
    return Math.min(Math.round(percentage), 100);
});

// --- ESTILOS DE COLOR ---
const colorClasses = computed(() => {
    const sizeClasses = {
        small: 'p-4',
        normal: 'p-6',
        large: 'p-8'
    };

    const themes = {
        blue: {
            bg: 'bg-blue-50 border border-blue-200',
            iconBg: 'bg-blue-100 hover:bg-blue-200',
            iconText: 'text-blue-600',
            text: 'text-blue-700',
            valueText: 'text-blue-900',
            description: 'text-blue-600',
            border: 'border-blue-200',
            footer: 'text-blue-600',
            progressBar: 'bg-blue-500',
            chartBar: 'bg-blue-400'
        },
        green: {
            bg: 'bg-green-50 border border-green-200',
            iconBg: 'bg-green-100 hover:bg-green-200',
            iconText: 'text-green-600',
            text: 'text-green-700',
            valueText: 'text-green-900',
            description: 'text-green-600',
            border: 'border-green-200',
            footer: 'text-green-600',
            progressBar: 'bg-green-500',
            chartBar: 'bg-green-400'
        },
        orange: {
            bg: 'bg-orange-50 border border-orange-200',
            iconBg: 'bg-orange-100 hover:bg-orange-200',
            iconText: 'text-orange-600',
            text: 'text-orange-700',
            valueText: 'text-orange-900',
            description: 'text-orange-600',
            border: 'border-orange-200',
            footer: 'text-orange-600',
            progressBar: 'bg-orange-500',
            chartBar: 'bg-orange-400'
        },
        purple: {
            bg: 'bg-purple-50 border border-purple-200',
            iconBg: 'bg-purple-100 hover:bg-purple-200',
            iconText: 'text-purple-600',
            text: 'text-purple-700',
            valueText: 'text-purple-900',
            description: 'text-purple-600',
            border: 'border-purple-200',
            footer: 'text-purple-600',
            progressBar: 'bg-purple-500',
            chartBar: 'bg-purple-400'
        },
        red: {
            bg: 'bg-red-50 border border-red-200',
            iconBg: 'bg-red-100 hover:bg-red-200',
            iconText: 'text-red-600',
            text: 'text-red-700',
            valueText: 'text-red-900',
            description: 'text-red-600',
            border: 'border-red-200',
            footer: 'text-red-600',
            progressBar: 'bg-red-500',
            chartBar: 'bg-red-400'
        },
        yellow: {
            bg: 'bg-yellow-50 border border-yellow-200',
            iconBg: 'bg-yellow-100 hover:bg-yellow-200',
            iconText: 'text-yellow-600',
            text: 'text-yellow-700',
            valueText: 'text-yellow-900',
            description: 'text-yellow-600',
            border: 'border-yellow-200',
            footer: 'text-yellow-600',
            progressBar: 'bg-yellow-500',
            chartBar: 'bg-yellow-400'
        },
        indigo: {
            bg: 'bg-indigo-50 border border-indigo-200',
            iconBg: 'bg-indigo-100 hover:bg-indigo-200',
            iconText: 'text-indigo-600',
            text: 'text-indigo-700',
            valueText: 'text-indigo-900',
            description: 'text-indigo-600',
            border: 'border-indigo-200',
            footer: 'text-indigo-600',
            progressBar: 'bg-indigo-500',
            chartBar: 'bg-indigo-400'
        },
        teal: {
            bg: 'bg-teal-50 border border-teal-200',
            iconBg: 'bg-teal-100 hover:bg-teal-200',
            iconText: 'text-teal-600',
            text: 'text-teal-700',
            valueText: 'text-teal-900',
            description: 'text-teal-600',
            border: 'border-teal-200',
            footer: 'text-teal-600',
            progressBar: 'bg-teal-500',
            chartBar: 'bg-teal-400'
        },
        pink: {
            bg: 'bg-pink-50 border border-pink-200',
            iconBg: 'bg-pink-100 hover:bg-pink-200',
            iconText: 'text-pink-600',
            text: 'text-pink-700',
            valueText: 'text-pink-900',
            description: 'text-pink-600',
            border: 'border-pink-200',
            footer: 'text-pink-600',
            progressBar: 'bg-pink-500',
            chartBar: 'bg-pink-400'
        },
        gray: {
            bg: 'bg-gray-50 border border-gray-200',
            iconBg: 'bg-gray-100 hover:bg-gray-200',
            iconText: 'text-gray-600',
            text: 'text-gray-700',
            valueText: 'text-gray-900',
            description: 'text-gray-600',
            border: 'border-gray-200',
            footer: 'text-gray-600',
            progressBar: 'bg-gray-500',
            chartBar: 'bg-gray-400'
        },
        cyan: {
            bg: 'bg-cyan-50 border border-cyan-200',
            iconBg: 'bg-cyan-100',
            iconText: 'text-cyan-600',
            text: 'text-cyan-700',
            valueText: 'text-cyan-900',
            description: 'text-cyan-600',
            border: 'border-cyan-200',
            footer: 'text-cyan-600'
        }

    };

    return {
        ...themes[props.color],
        size: sizeClasses[props.size]
    };
});

// --- MÉTODOS ---
const handleClick = () => {
    if (props.clickable) {
        emit('click', {
            titulo: props.titulo,
            valor: props.valor,
            formato: props.formato
        });
    }
};

const formatDate = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleString('es-PE', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

onUnmounted(() => {
    isHovered.value = false;
});
</script>

<style scoped>
/* Animaciones personalizadas */
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.kpi-card {
    animation: slideIn 0.3s ease-out;
}

/* Efecto de pulso para loading */
@keyframes pulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.7;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Transiciones suaves */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}
</style>