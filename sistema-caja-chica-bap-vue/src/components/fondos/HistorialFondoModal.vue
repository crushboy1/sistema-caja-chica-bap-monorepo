<template>
    <Transition name="modal-fade">
        <div v-if="mostrar" class="fixed inset-0 bg-black bg-opacity-60 z-40 flex items-center justify-center p-4"
            @click.self="closeModal">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col transform transition-transform duration-300"
                :class="{ 'scale-100': mostrar, 'scale-95': !mostrar }">
                <header
                    class="flex items-center justify-between p-5 border-b border-gray-200 bg-indigo-600 text-white rounded-t-2xl">
                    <div>
                        <h3 class="text-xl font-bold">Historial del Fondo</h3>
                        <p class="text-sm text-indigo-200">{{ fondo?.codigo_fondo }}</p>
                    </div>
                    <button @click="closeModal"
                        class="p-2 rounded-full text-indigo-200 hover:bg-black/20 transition-colors">
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

                    <div v-else-if="historial.length === 0" class="text-center text-gray-500 py-10">
                        Este fondo no tiene historial de modificaciones.
                    </div>
                    <div v-else class="relative pl-6 border-l-2 border-gray-200">
                        <div v-for="item in historial" :key="item.id" class="mb-8 relative">
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
                                        <p class="text-xs text-gray-500">{{ item.fecha_aprobacion }}</p>
                                    </div>
                                    <div class="text-sm space-y-2 mt-2">
                                        <div v-if="item.tipo !== 'Cierre'">
                                            <strong>Monto Final del Fondo:</strong>
                                            <span class="font-semibold" :class="getTimelineClass(item.tipo).text">S/. {{
                                                parseFloat(item.monto_final).toFixed(2) }}</span>
                                        </div>
                                        <div>
                                            <strong>Solicitado por:</strong>
                                            <span>{{ item.solicitado_por }}</span>
                                        </div>
                                        <div>
                                            <strong>Aprobado por:</strong>
                                            <span>{{ item.aprobado_por }}</span>
                                        </div>
                                        <div>
                                            <strong>Motivo/Justificación:</strong>
                                            <p class="italic text-gray-600 mt-1 pl-2 border-l-2 border-gray-300">{{
                                                item.motivo }}</p>
                                        </div>
                                        <div class="text-xs text-gray-400 pt-2 text-right">
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

const props = defineProps({
    mostrar: Boolean,
    fondo: Object,
});

const emit = defineEmits(['close']);

const historial = ref([]);
const cargando = ref(false);
const error = ref(null);

const fetchHistorialFondo = async () => {
    if (!props.fondo) return;
    cargando.value = true;
    error.value = null;
    historial.value = [];

    try {
        const response = await api.get(`/fondos-efectivo/${props.fondo.id_fondo}/historial`);
        historial.value = response.data.historial;
    } catch (err) {
        console.error("Error al cargar el historial del fondo:", err);
        error.value = "No se pudo cargar el historial. Por favor, intente de nuevo.";
    } finally {
        cargando.value = false;
    }
};

watch(() => props.mostrar, (newVal) => {
    if (newVal) {
        fetchHistorialFondo();
    }
});

const closeModal = () => {
    emit('close');
};

// --- Funciones de ayuda para la UI ---
const getTimelineClass = (tipo) => {
    switch (tipo) {
        case 'Apertura': return { bg: 'bg-verde-bap', text: 'text-verde-bap-dark', card: 'bg-green-50 border-green-200', icon: markRaw(IconApertura) };
        case 'Incremento': return { bg: 'bg-blue-500', text: 'text-blue-700', card: 'bg-blue-50 border-blue-200', icon: markRaw(IconIncremento) };
        case 'Decremento': return { bg: 'bg-amarillo-bap', text: 'text-yellow-700', card: 'bg-yellow-50 border-yellow-200', icon: markRaw(IconDecremento) };
        case 'Cierre': return { bg: 'bg-rojo-bap', text: 'text-rojo-bap-dark', card: 'bg-red-50 border-red-200', icon: markRaw(IconCierre) };
        default: return { bg: 'bg-gray-500', text: 'text-gray-700', card: 'bg-gray-50 border-gray-200', icon: null };
    }
}

const getTipoLabel = (tipo) => {
    switch (tipo) {
        case 'Apertura': return 'Apertura de Fondo';
        case 'Incremento': return 'Incremento de Monto';
        case 'Decremento': return 'Decremento de Monto';
        case 'Cierre': return 'Cierre de Fondo';
        default: return 'Evento Desconocido';
    }
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
