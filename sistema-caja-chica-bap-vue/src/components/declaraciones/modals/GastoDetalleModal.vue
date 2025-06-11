<script setup>
import { defineProps, defineEmits, computed } from 'vue';

const props = defineProps({
    mostrar: {
        type: Boolean,
        default: false
    },
    gasto: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close']);

// Propiedad computada para obtener la URL de la evidencia de forma segura
const evidenciaUrl = computed(() => {
    return props.gasto?.evidencia_url || null;
});
// Detecta si la evidencia es un PDF
const esPdf = computed(() => {
    return evidenciaUrl.value && evidenciaUrl.value.toLowerCase().endsWith('.pdf');
});

// Detecta si la evidencia es una imagen
const esImagen = computed(() => {
    if (!evidenciaUrl.value) return false;
    const extension = evidenciaUrl.value.split('.').pop().toLowerCase();
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension);
});
// Función para formatear la fecha con hora y minutos
const formatearFecha = (fechaString) => {
    if (!fechaString) return 'N/A';
    const opciones = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(fechaString).toLocaleDateString('es-ES', opciones);
};

// Función para cerrar el modal
const cerrarModal = () => {
    emit('close');
};
</script>

<template>
    <Transition name="modal-backdrop">
        <div v-if="mostrar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-backdrop-dark backdrop-blur-sm">
            <Transition name="modal-content" appear>
                <!-- Estructura principal del modal, replicando el efecto glass y las animaciones -->
                <div
                    class="glass-modal rounded-3xl shadow-modal w-full max-w-md mx-auto overflow-hidden transform animate-modal-scale border border-white/20 sm:max-w-lg md:max-w-xl">

                    <!-- Encabezado del modal con gradiente y botón de cierre estilizado -->
                    <div class="relative bg-gradient-to-r from-verde-bap to-verde-bap-dark p-6 text-white">
                        <div class="absolute inset-0 bg-gradient-to-r from-verde-bap/90 to-verde-bap-dark/90"></div>
                        <div class="relative flex justify-between items-center">
                            <h3 class="text-2xl font-bold text-white drop-shadow-lg">
                                Detalles del Gasto: {{ gasto?.codigo_gasto || 'N/A' }}
                            </h3>
                            <button @click="cerrarModal"
                                class="text-white/80 hover:text-white hover:bg-white/20 p-2 rounded-xl transition-all duration-300 hover:scale-110">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Cuerpo del modal con scroll personalizado -->
                    <div
                        class="p-6 max-h-[70vh] overflow-y-auto scroll-modal bg-gradient-to-br from-white/95 to-verde-bap-extralight/50">

                        <!-- Tarjeta de Información General -->
                        <div
                            class="mb-6 p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-verde-bap-dark" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Información General
                            </h4>
                            <p class="text-sm text-gray-600"><strong>Monto Total:</strong> S/. {{ gasto?.monto_total ?
                                parseFloat(gasto.monto_total).toFixed(2) : '0.00' }}</p>
                            <p class="text-sm text-gray-600"><strong>Estado Actual:</strong> {{ gasto?.estado || 'N/A'
                            }}</p>
                            <p class="text-sm text-gray-600"><strong>Fecha de Registro:</strong> {{
                                formatearFecha(gasto?.created_at) }}</p>
                            <p class="text-sm text-gray-600"><strong>Glosa:</strong> {{ gasto?.glosa || 'No especificada' }}</p>
                            <p v-if="gasto?.comentario" class="text-sm text-gray-600"><strong>Comentario
                                    Adicional:</strong> {{ gasto.comentario }}</p>
                            <p class="text-sm text-gray-600"><strong>Tipo de Documento:</strong> {{
                                gasto?.tipo_documento || 'N/A' }}</p>
                            <!-- Lógica para mostrar Serie y Correlativo -->
                            <template v-if="gasto?.tipo_documento !== 'Declaración Jurada'">
                                <p class="text-sm text-gray-600"><strong>Serie:</strong> {{ gasto?.serie_documento ||
                                    'N/A' }}</p>
                                <p class="text-sm text-gray-600"><strong>Correlativo:</strong> {{
                                    gasto?.correlativo_documento || 'N/A' }}</p>
                            </template>
                            <template v-else>
                                <p class="text-sm text-gray-600"><strong>Serie:</strong> N/A</p>
                                <p class="text-sm text-gray-600"><strong>Correlativo:</strong> N/A</p>
                            </template>
                        </div>

                        <!-- Tarjeta de Información del Registrador -->
                        <div
                            class="mb-6 p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-verde-bap-dark" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Registrador del Gasto
                            </h4>
                            <p class="text-sm text-gray-600">
                                <strong>Nombre:</strong>
                                {{ gasto?.registrador?.name || 'N/A' }} {{ gasto?.registrador?.last_name || '' }}
                            </p>
                            <p class="text-sm text-gray-600"><strong>Rol:</strong> {{
                                gasto?.registrador?.role?.display_name || 'N/A' }}</p>
                            <p class="text-sm text-gray-600"><strong>Área:</strong> {{ gasto?.registrador?.area?.name ||
                                'N/A' }}</p>
                        </div>
                        <!-- Tarjeta de Detalles Contables y de Fondo -->
                        <div
                            class="mb-6 p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-verde-bap-dark" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                                    </path>
                                </svg>
                                Detalles Contables y de Fondo
                            </h4>
                            <p class="text-sm text-gray-600"><strong>Fondo Afectado:</strong> {{
                                gasto?.fondo_efectivo?.codigo_fondo || 'N/A' }}
                            </p>
                            <p class="text-sm text-gray-600"><strong>Cuenta Contable:</strong>
                                <span v-if="gasto?.cuenta_contable">{{ gasto.cuenta_contable.codigo_cuenta }} - {{
                                    gasto.cuenta_contable.descripcion }}</span>
                                <span v-else>N/A</span>
                            </p>
                        </div>
                        <!-- Tarjeta de Evidencia con botón de descarga -->
                        <div
                            class="mb-6 p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-verde-bap-dark" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Registrador del Gasto
                            </h4>
                            <p class="text-sm text-gray-600">
                                <strong>Nombre:</strong>
                                {{ gasto?.registrador?.name || 'N/A' }} {{ gasto?.registrador?.last_name || '' }}
                            </p>
                            <p class="text-sm text-gray-600"><strong>Rol:</strong> {{
                                gasto?.registrador?.role?.display_name || 'N/A' }}</p>
                            <p class="text-sm text-gray-600"><strong>Área:</strong> {{ gasto?.registrador?.area?.name ||
                                'N/A' }}</p>
                        </div>

                        <!-- Tarjeta de Evidencia -->
                        <div class="p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-verde-bap-dark" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                    </path>
                                </svg>
                                Evidencia Adjunta
                            </h4>
                            <div v-if="evidenciaUrl" class="mt-2">
                                <!-- Muestra la imagen si es una imagen -->
                                <a v-if="esImagen" :href="evidenciaUrl" target="_blank" rel="noopener noreferrer"
                                    class="block group">
                                    <img :src="evidenciaUrl" alt="Evidencia del gasto"
                                        class="w-full h-auto max-h-60 object-contain rounded-lg border border-gray-300 shadow-soft group-hover:shadow-glow-verde transition-all duration-300">
                                </a>
                                <!-- Muestra un ícono y enlace si es un PDF -->
                                <div v-else-if="esPdf"
                                    class="flex flex-col items-center justify-center p-4 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300">
                                    <svg class="w-12 h-12 text-rojo-bap" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z">
                                        </path>
                                    </svg>
                                    <a :href="evidenciaUrl" target="_blank" rel="noopener noreferrer"
                                        class="mt-2 text-sm text-verde-bap-dark hover:underline font-semibold">
                                        Ver PDF en nueva pestaña
                                    </a>
                                </div>

                                <!-- Botón de descarga universal -->
                                <div class="flex justify-center mt-4">
                                    <a :href="evidenciaUrl" download
                                        class="inline-flex items-center space-x-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all duration-300 transform hover:scale-105">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                            </path>
                                        </svg>
                                        <span>Descargar Evidencia</span>
                                    </a>
                                </div>
                            </div>
                            <p v-else class="text-sm text-gray-500 mt-2">No se adjuntó evidencia para este gasto.</p>
                        </div>
                    </div>

                    <!-- Pie de página del modal con botón de cierre estilizado -->
                    <div
                        class="bg-gradient-to-r from-gray-50 to-verde-bap-extralight/30 px-6 py-4 border-t border-gray-200/50">
                        <div class="flex justify-end">
                            <button @click="cerrarModal"
                                class="group relative overflow-hidden px-6 py-3 bg-gradient-to-r from-verde-bap to-verde-bap-dark text-white font-semibold rounded-xl shadow-soft hover:shadow-glow-verde transition-all duration-300 hover:scale-105 focus:outline-none focus:ring-4 focus:ring-verde-bap/30">
                                <span class="relative z-10 flex items-center space-x-2">
                                    <span>Cerrar</span>
                                    <svg class="h-4 w-4 group-hover:translate-x-1 transition-transform duration-300"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </span>
                                <div
                                    class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700">
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
/* Transiciones del modal, replicadas de SolicitudDetalleModal */
.modal-backdrop-enter-active,
.modal-backdrop-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.modal-backdrop-enter-from,
.modal-backdrop-leave-to {
    opacity: 0;
    backdrop-filter: blur(0px);
}

.modal-content-enter-active {
    transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modal-content-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 1, 1);
}

.modal-content-enter-from {
    opacity: 0;
    transform: scale(0.8) translateY(50px);
}

.modal-content-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(-20px);
}

/* Scroll personalizado, replicado de SolicitudDetalleModal */
.scroll-modal::-webkit-scrollbar {
    width: 8px;
}

.scroll-modal::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 10px;
}

.scroll-modal::-webkit-scrollbar-thumb {
    background: linear-gradient(180deg, #76C49D, #5da887);
    border-radius: 10px;
    transition: all 0.3s ease;
}

.scroll-modal::-webkit-scrollbar-thumb:hover {
    background: linear-gradient(180deg, #5da887, #4a9470);
    box-shadow: 0 0 10px rgba(118, 196, 157, 0.5);
}
</style>
