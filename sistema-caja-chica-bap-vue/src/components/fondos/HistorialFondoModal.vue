<template>
    <Transition name="modal-fade">
        <div v-if="mostrar" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4"
            @click.self="closeModal">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col transform transition-transform duration-300"
                :class="{ 'scale-100': mostrar, 'scale-95': !mostrar }">
                <header
                    class="flex items-center justify-between p-5 border-b border-gray-200 bg-gray-800 text-white rounded-t-2xl">
                    <div>
                        <h3 class="text-xl font-bold">Línea de Tiempo del Fondo</h3>
                        <p class="text-sm text-gray-300">{{ fondo?.codigo_fondo }}</p>
                    </div>
                    <button @click="closeModal"
                        class="p-2 rounded-full text-gray-300 hover:bg-black/20 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </header>

                <main class="flex-grow overflow-y-auto p-6 space-y-4">
                    <div v-if="cargando" class="text-center py-10">
                        <div class="inline-flex items-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-500"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Cargando historial...
                        </div>
                    </div>

                    <div v-else-if="error" class="text-center text-red-500 bg-red-100 p-4 rounded-lg">
                        {{ error }}
                    </div>

                    <div v-else-if="timeline.length === 0" class="text-center text-gray-500 py-10">
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
                                        <!-- Lógica de visualización de montos -->
                                        <div v-if="item.tipo === 'Reposición'">
                                            <strong>Monto Repuesto:</strong>
                                            <span class="font-semibold" :class="getTimelineClass(item.tipo).text">S/. {{
                                                parseFloat(item.monto).toFixed(2) }}</span>
                                        </div>
                                        <div v-else-if="item.tipo !== 'Cierre'">
                                            <strong>Monto del Fondo:</strong>
                                            <span class="font-semibold" :class="getTimelineClass(item.tipo).text">S/. {{
                                                parseFloat(item.monto).toFixed(2) }}</span>
                                        </div>

                                        <div>
                                            <strong>Realizado por:</strong>
                                            <span>{{ item.usuario }}</span>
                                        </div>
                                        <div>
                                            <strong>Motivo/Justificación:</strong>
                                            <p class="italic text-gray-600 mt-1 pl-2 border-l-2 border-gray-300">{{
                                                item.motivo }}</p>
                                        </div>
                                        <div v-if="item.tipo === 'Reposición' && item.ruta_comprobante" class="pt-2">
                                            <a :href="getComprobanteUrl(item.ruta_comprobante)" target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center text-sm font-medium text-purple-600 hover:text-purple-800 bg-purple-100 hover:bg-purple-200 px-3 py-1 rounded-full transition-colors">
                                                <component :is="IconEnlace" />
                                                Ver Comprobante
                                            </a>
                                        </div>
                                        <div v-if="item.codigo_solicitud" class="text-xs text-gray-400 pt-2 text-right">
                                            Ref. Solicitud: {{ item.codigo_solicitud }}
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
import { ref, watch, markRaw } from 'vue';
import api from '@/plugins/axios';

// --- Iconos para cada tipo de historial ---
const IconApertura = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>` };
const IconIncremento = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" /></svg>` };
const IconDecremento = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" /></svg>` };
const IconCierre = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>` };
const IconReposicion = { template: `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M5 5a7 7 0 0012.544 5.372M19 20v-5h-5m0 0l-3.544-3.544M14 15a7 7 0 00-12.544-5.372" /></svg>` };
const IconEnlace = { template: `<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>` };

const props = defineProps({
    mostrar: Boolean,
    fondo: Object,
});

const emit = defineEmits(['close']);

const timeline = ref([]);
const cargando = ref(false);
const error = ref(null);

const fetchTimeline = async () => {
    if (!props.fondo) return;
    cargando.value = true;
    error.value = null;
    timeline.value = [];

    try {
        // CAMBIO: Se llama al nuevo endpoint unificado
        const response = await api.get(`/v1/fondos-efectivo/${props.fondo.id_fondo}/timeline`);
        timeline.value = response.data.timeline;
    } catch (err) {
        console.error("Error al cargar la línea de tiempo del fondo:", err);
        error.value = "No se pudo cargar el historial. Por favor, intente de nuevo.";
    } finally {
        cargando.value = false;
    }
};

watch(() => props.mostrar, (newVal) => {
    if (newVal) {
        fetchTimeline();
    }
});

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
    // Asume que la carpeta 'public' está enlazada como 'storage' en el directorio público del proyecto.
    return `/storage/${ruta}`;
};
// --- Funciones de ayuda para la UI ---
const getTimelineClass = (tipo) => {
    switch (tipo) {
        case 'Apertura': return { bg: 'bg-green-500', text: 'text-green-700', card: 'bg-green-50 border-green-200', icon: markRaw(IconApertura) };
        case 'Incremento': return { bg: 'bg-blue-500', text: 'text-blue-700', card: 'bg-blue-50 border-blue-200', icon: markRaw(IconIncremento) };
        case 'Decremento': return { bg: 'bg-yellow-500', text: 'text-yellow-700', card: 'bg-yellow-50 border-yellow-200', icon: markRaw(IconDecremento) };
        // NUEVO: Estilo para el evento de reposición
        case 'Reposición': return { bg: 'bg-purple-500', text: 'text-purple-700', card: 'bg-purple-50 border-purple-200', icon: markRaw(IconReposicion) };
        case 'Cierre': return { bg: 'bg-red-500', text: 'text-red-700', card: 'bg-red-50 border-red-200', icon: markRaw(IconCierre) };
        default: return { bg: 'bg-gray-500', text: 'text-gray-700', card: 'bg-gray-50 border-gray-200', icon: null };
    }
}

const getTipoLabel = (tipo) => {
    // No se necesita cambiar, el backend ya envía "Reposición" como tipo.
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
