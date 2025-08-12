<template>
    <Transition name="modal-fade">
        <div v-if="mostrar" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4"
            @click.self="closeModal">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col transform transition-transform duration-300"
                :class="{ 'scale-100': mostrar, 'scale-95': !mostrar }">
                <header class="flex items-center justify-between p-5 border-b border-gray-200 bg-verde-bap-dark text-white rounded-t-2xl">
                    <div>
                        <h3 class="text-xl font-bold">Línea de Tiempo del Fondo</h3>
                        <p class="text-sm text-verde-bap-light">{{ fondoCodigo }}</p>
                    </div>
                    <button @click="closeModal" class="p-2 rounded-full text-gray-300 hover:bg-black/20 transition-colors">
                        <X class="w-6 h-6" />
                    </button>
                </header>

                <main class="flex-grow overflow-y-auto p-6 space-y-4">
                    <div v-if="cargando" class="text-center py-10">
                        <div class="inline-flex items-center">
                            <Loader2 class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-500" />
                            Cargando historial...
                        </div>
                    </div>

                    <div v-else-if="error" class="text-center text-estado-error-text bg-estado-error-bg p-4 rounded-lg">
                        {{ error }}
                    </div>
                    <div v-else-if="timeline.length === 0" class="text-center text-gris-bap py-10">
                        Este fondo no tiene historial de modificaciones.
                    </div>

                    <div v-else class="relative pl-6 border-l-2 border-gray-200">
                        <div v-for="item in timeline" :key="item.id" class="mb-8 relative">
                            <!-- Círculo del Timeline con color e icono dinámico -->
                            <span
                                class="absolute -left-[18px] top-0 flex items-center justify-center w-8 h-8 rounded-full ring-4 ring-white"
                                :class="getTimelineClass(item.tipo).bg">
                                <component :is="getTimelineClass(item.tipo).icon" class="w-5 h-5 text-white" />
                            </span>

                            <div class="ml-6">
                                <!-- Tarjeta de información con color dinámico -->
                                <div class="p-4 rounded-lg border" :class="getTimelineClass(item.tipo).card">
                                    <div class="flex justify-between items-center mb-1">
                                        <h4 class="font-bold text-lg" :class="getTimelineClass(item.tipo).text">{{
                                            getTipoLabel(item.tipo) }}</h4>
                                        <p class="text-xs text-gray-500">{{ formatarFecha(item.fecha) }}</p>
                                    </div>
                                    <div class="text-sm space-y-2 mt-2">
                                        
                                        <div v-if="item.saldo_anterior !== null && item.saldo_nuevo !== null"
                                            class="space-y-1 text-xs border-t pt-2 mt-2">
                                            <div class="flex justify-between text-gray-500">
                                                <span>Saldo Anterior:</span>
                                                <span>{{ currencyFormatter.format(item.saldo_anterior) }}</span>
                                            </div>
                                            <div class="flex justify-between font-semibold"
                                                :class="getTimelineClass(item.tipo).text">
                                                <span>Monto del Movimiento:</span>
                                                <span>{{ item.tipo.includes('Devolucion') ? '-' : '+' }} {{
                                                    currencyFormatter.format(item.monto) }}</span>
                                            </div>
                                            <div
                                                class="flex justify-between text-gray-800 font-bold border-t border-dashed mt-1 pt-1">
                                                <span>Saldo Nuevo:</span>
                                                <span>{{ currencyFormatter.format(item.saldo_nuevo) }}</span>
                                            </div>
                                        </div>

                                
                                        <div v-else-if="item.tipo === 'Restauracion Mensual'"
                                            class="space-y-1 text-xs border-t pt-2 mt-2">
                                            <div class="flex justify-between font-semibold"
                                                :class="getTimelineClass(item.tipo).text">
                                                <span>Saldo Restaurado a:</span>
                                                <span>{{ currencyFormatter.format(item.monto) }}</span>
                                            </div>
                                        </div>

                                        <div v-else-if="item.tipo !== 'Cierre'">
                                            <strong>Monto del Fondo:</strong>
                                            <span class="font-semibold" :class="getTimelineClass(item.tipo).text">{{
                                                currencyFormatter.format(item.monto) }}</span>
                                        </div>

                                        <div>
                                            <strong>Realizado por:</strong>
                                            <span>{{ item.usuario }}</span>
                                        </div>
                                        <div>
                                            <strong>Motivo/Justificación:</strong>
                                            <p class="italic text-gray-600 mt-1 pl-2 border-l-2 border-gray-300">{{
                                                item.motivo || 'N/A' }}</p>
                                        </div>

                                        <!-- Enlace al comprobante si existe -->
                                        <div v-if="item.ruta_comprobante" class="pt-2">
                                            <a :href="getComprobanteUrl(item.ruta_comprobante)" target="_blank" rel="noopener noreferrer"
                                                class="inline-flex items-center text-sm font-medium text-azul-bap-dark hover:text-azul-bap-dark bg-azul-bap-light hover:bg-azul-bap/30 px-3 py-1 rounded-full transition-colors">
                                                <ExternalLink class="h-4 w-4 mr-2" />
                                                Ver Comprobante
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, watch } from 'vue';
import {
    Plus,
    TrendingUp,
    TrendingDown,
    X,
    RefreshCw,
    CornerDownLeft,
    ExternalLink,
    Loader2,
    Zap
} from 'lucide-vue-next';
import api from '@/plugins/axios';

const props = defineProps({
    mostrar: Boolean,
    fondoId: {
        type: Number,
        default: null
    },
    fondoCodigo: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['close']);

const timeline = ref([]);
const cargando = ref(false);
const error = ref(null);
const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

const fetchTimeline = async () => {
    if (!props.fondoId) return;

    cargando.value = true;
    error.value = null;
    timeline.value = [];

    try {
        const response = await api.get(`/v1/fondos-efectivo/${props.fondoId}/timeline`);
        timeline.value = response.data.timeline;
    } catch (err) {
        console.error("Error al cargar la línea de tiempo del fondo:", err);
        error.value = err.response?.data?.message || "No se pudo cargar el historial. Intente de nuevo.";
    } finally {
        cargando.value = false;
    }
};

watch(() => props.mostrar, (esVisible) => {
    if (esVisible && props.fondoId) {
        fetchTimeline();
    }
}, { immediate: true });

const closeModal = () => {
    emit('close');
};

const formatarFecha = (fechaString) => {
    if (!fechaString) return 'N/A';
    const opciones = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(fechaString).toLocaleDateString('es-ES', opciones);
};

const getComprobanteUrl = (ruta) => {
    if (!ruta) return '#';
    return `/storage/${ruta}`;
};

// Funciones de ayuda para la UI con Lucide Icons
const getTimelineClass = (tipo) => {
    switch (tipo) {
        case 'Apertura':
            return {
                bg: 'bg-verde-bap',
                text: 'text-verde-bap-dark',
                card: 'bg-verde-bap-light border-verde-bap',
                icon: Plus
            };
        case 'Incremento':
            return {
                bg: 'bg-azul-bap',
                text: 'text-azul-bap-dark',
                card: 'bg-azul-bap-light border-azul-bap',
                icon: TrendingUp
            };
        case 'Decremento':
            return {
                bg: 'bg-amarillo-bap-dark',
                text: 'text-amarillo-bap-dark',
                card: 'bg-amarillo-bap-light border-amarillo-bap',
                icon: TrendingDown
            };
        case 'Reposicion por Excedente':
            return {
                bg: 'bg-naranja-bap',
                text: 'text-naranja-bap-dark',
                card: 'bg-naranja-bap-light border-naranja-bap',
                icon: RefreshCw
            };
        case 'Devolucion por Sobrante':
            return {
                bg: 'bg-naranja-bap',
                text: 'text-naranja-bap-dark',
                card: 'bg-naranja-bap-light border-naranja-bap',
                icon: CornerDownLeft
            };
        case 'Restauracion Mensual':
            return { 
                bg: 'bg-indigo-500', 
                text: 'text-indigo-700', 
                card: 'bg-indigo-100 border-indigo-200', 
                icon: Zap 
            };
        case 'Cierre':
            return {
                bg: 'bg-rojo-bap',
                text: 'text-rojo-bap-dark',
                card: 'bg-rojo-bap-light border-rojo-bap',
                icon: X
            };
        default:
            return {
                bg: 'bg-gris-bap',
                text: 'text-gris-bap-dark',
                card: 'bg-gris-bap-light border-gris-bap',
                icon: Plus
            };
    }
}

const getTipoLabel = (tipo) => {
    return tipo;
}
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
</style>