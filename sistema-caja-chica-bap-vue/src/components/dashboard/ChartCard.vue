<template>
    <div class="bg-white rounded-2xl shadow-lg p-6 hover:shadow-xl transition-all duration-300 animate-fade-in-up">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-3">
                <h3 class="text-lg font-semibold text-gray-800">{{ title }}</h3>
                <div v-if="subtitle" class="hidden sm:block">
                    <span class="text-gray-400">•</span>
                    <span class="text-sm text-gray-500 ml-2">{{ subtitle }}</span>
                </div>
            </div>

            <!-- Actions slot -->
            <div v-if="$slots.actions" class="flex items-center gap-2">
                <slot name="actions"></slot>
            </div>

            <!-- Default actions -->
            <div v-else-if="showActions" class="flex items-center gap-2">
                <button v-if="allowFullscreen" @click="toggleFullscreen" class="action-button"
                    title="Pantalla completa">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                    </svg>
                </button>

                <button v-if="allowExport" @click="exportChart" class="action-button" title="Exportar gráfico">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </button>

                <button v-if="allowRefresh" @click="refreshChart" class="action-button"
                    :class="{ 'animate-spin': loading }" title="Actualizar datos">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Chart Content -->
        <div class="bg-white rounded-xl shadow-sm p-4 overflow-hidden relative" :class="height">
            <!-- Loading overlay -->
            <div v-if="loading"
                class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10 rounded-lg">
                <div class="flex flex-col items-center gap-3">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-verde-bap"></div>
                    <span class="text-sm text-gray-500">{{ loadingText }}</span>
                </div>
            </div>

            <!-- Error state -->
            <div v-else-if="error" class="chart-placeholder">
                <div class="text-red-500 mb-2">
                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 18.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                </div>
                <p class="text-gray-500 mb-2">{{ errorMessage || 'Error al cargar el gráfico' }}</p>
                <button @click="refreshChart"
                    class="text-sm text-verde-bap hover:text-green-600 underline transition-colors">
                    Reintentar
                </button>
            </div>

            <!-- Empty state -->
            <div v-else-if="!hasContent" class="chart-placeholder">
                <div class="text-gray-400 mb-2">
                    <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <p class="text-gray-500 mb-2">{{ emptyMessage || 'No hay datos disponibles' }}</p>
                <p class="text-xs text-gray-400">{{ emptySubtext || 'Los datos aparecerán aquí cuando estén disponibles'
                    }}</p>
            </div>

            <!-- Chart content -->
            <div v-else :class="{ 'opacity-50': loading }">
                <slot></slot>
            </div>
        </div>

        <!-- Footer info -->
        <div v-if="showFooter && (lastUpdated || footerText)" class="mt-4 pt-4 border-t border-gray-100">
            <div class="flex items-center justify-between text-xs text-gray-500">
                <span v-if="footerText">{{ footerText }}</span>
                <span v-if="lastUpdated">
                    Actualizado: {{ formatDate(lastUpdated) }}
                </span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, useSlots } from 'vue'

// Props
const props = defineProps({
    title: {
        type: String,
        required: true
    },
    subtitle: {
        type: String,
        default: null
    },
    loading: {
        type: Boolean,
        default: false
    },
    loadingText: {
        type: String,
        default: 'Cargando datos...'
    },
    error: {
        type: Boolean,
        default: false
    },
    errorMessage: {
        type: String,
        default: null
    },
    emptyMessage: {
        type: String,
        default: null
    },
    emptySubtext: {
        type: String,
        default: null
    },
    showActions: {
        type: Boolean,
        default: true
    },
    allowFullscreen: {
        type: Boolean,
        default: false
    },
    allowExport: {
        type: Boolean,
        default: false
    },
    allowRefresh: {
        type: Boolean,
        default: true
    },
    showFooter: {
        type: Boolean,
        default: false
    },
    footerText: {
        type: String,
        default: null
    },
    lastUpdated: {
        type: [String, Date],
        default: null
    },
    height: {
        type: String,
        default: 'h-80'
    }
})

// Emits
const emit = defineEmits(['refresh', 'export', 'fullscreen'])

// Slots
const slots = useSlots()

// Computed properties
const hasContent = computed(() => {
    return slots.default && slots.default().length > 0
})

// Methods
const refreshChart = () => {
    emit('refresh')
}

const exportChart = () => {
    emit('export')
}

const toggleFullscreen = () => {
    emit('fullscreen')
}

const formatDate = (date) => {
    if (!date) return ''

    const dateObj = typeof date === 'string' ? new Date(date) : date
    return dateObj.toLocaleString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}
</script>

<style scoped>
.action-button {
    @apply p-2 text-gray-500 hover:text-verde-bap hover:bg-gray-50 rounded-lg transition-colors duration-200;
}

.chart-placeholder {
    @apply flex flex-col items-center justify-center text-center py-12;
}
</style>