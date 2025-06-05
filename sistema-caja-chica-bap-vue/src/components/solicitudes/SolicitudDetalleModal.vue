<script setup>
import { defineProps, defineEmits, computed } from 'vue';

const props = defineProps({
    mostrar: {
        type: Boolean,
        default: false
    },
    solicitud: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close']);

// Propiedad computada para los detalles de gastos, asegurando que sea un array
const detallesDeGastos = computed(() => {
    // Accede a la relación por el nombre que Laravel envía en el JSON (snake_case)
    // Si la relación existe y es un array, úsala; de lo contrario, devuelve un array vacío.
    if (props.solicitud && Array.isArray(props.solicitud.detalles_gastos_proyectados)) {
        return props.solicitud.detalles_gastos_proyectados;
    }
    return []; // Devuelve un array vacío si no hay detalles_gastos_proyectados o no es un array
});

// Función para formatear la fecha
const formatearFecha = (fechaString) => {
    if (!fechaString) return 'N/A';
    const opciones = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(fechaString).toLocaleDateString('es-ES', opciones);
};

const cerrarModal = () => {
    emit('close');
};
</script>

<template>
    <Transition name="modal-backdrop">
        <div v-if="mostrar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-backdrop-dark backdrop-blur-sm">
            <Transition name="modal-content" appear>
                <div
                    class="glass-modal rounded-3xl shadow-modal w-full max-w-md mx-auto overflow-hidden transform animate-modal-scale border border-white/20 sm:max-w-lg md:max-w-xl">
                    <div class="relative bg-gradient-to-r from-verde-bap to-verde-bap-dark p-6 text-white">
                        <div class="absolute inset-0 bg-gradient-to-r from-verde-bap/90 to-verde-bap-dark/90"></div>
                        <div class="relative flex justify-between items-center">
                            <h3 class="text-2xl font-bold text-white drop-shadow-lg">
                                Detalles de Solicitud: {{ solicitud?.codigo_solicitud || 'N/A' }}
                            </h3>
                            <button @click="cerrarModal"
                                class="text-white/80 hover:text-white hover:bg-white/20 p-2 rounded-xl transition-all duration-300 hover:scale-110">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div
                        class="p-6 max-h-[70vh] overflow-y-auto scroll-modal bg-gradient-to-br from-white/95 to-verde-bap-extralight/50">
                        <div class="mb-6 p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2">Información General</h4>
                            <p class="text-sm text-gray-600"><strong>Tipo de Solicitud:</strong> {{ solicitud?.tipo_solicitud || 'N/A'
                                }}</p>
                            <p class="text-sm text-gray-600"><strong>Monto Solicitado:</strong> S/. {{ solicitud?.monto_solicitado ?
                                parseFloat(solicitud.monto_solicitado).toFixed(2) : '0.00' }}</p>
                            <p class="text-sm text-gray-600"><strong>Prioridad:</strong> {{ solicitud?.prioridad || 'N/A' }}</p>
                            <p class="text-sm text-gray-600"><strong>Estado Actual:</strong> {{ solicitud?.estado || 'N/A' }}</p>
                            <!-- NUEVA SECCIÓN: Código de Fondo Asignado para solicitudes de Apertura Aprobadas -->
                            <p v-if="solicitud?.tipo_solicitud === 'Apertura' && solicitud?.estado === 'Aprobada' && solicitud?.fondo_efectivo?.codigo_fondo"
                                class="text-sm text-gray-600" >
                                <strong>Código de Fondo Asignado:</strong> <span class="text-lg text-sm">{{ solicitud.fondo_efectivo.codigo_fondo }}</span>
                            </p>
                            <p class="text-sm text-gray-600"><strong>Fecha de Creación:</strong> {{
                                formatearFecha(solicitud?.created_at) }}</p>
                            <p class="text-sm text-gray-600">
                                <strong>Solicitante:</strong>
                                {{ solicitud?.solicitante?.name || 'N/A' }} {{ solicitud?.solicitante?.last_name || '' }}
                            </p>
                            <p class="text-sm text-gray-600"><strong>Rol del Solicitante:</strong> {{
                                solicitud?.solicitante?.role?.display_name || 'N/A' }}</p>
                            <p class="text-sm text-gray-600"><strong>Área del Solicitante:</strong> {{
                                solicitud?.solicitante?.area?.name || 'N/A' }}</p>
                        </div>

                        <div v-if="solicitud?.tipo_solicitud !== 'Apertura' && solicitud?.solicitud_original?.fondo_efectivo"
                            class="mb-6 p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2">Información del Fondo Original</h4>
                            <p class="text-sm text-gray-600"><strong>Código de Fondo:</strong> {{
                                solicitud.solicitud_original.fondo_efectivo.codigo_fondo || 'N/A' }}</p>
                            <p class="text-sm text-gray-600"><strong>Monto Aprobado del Fondo:</strong> S/. {{
                                solicitud.solicitud_original.fondo_efectivo.monto_aprobado ?
                                    parseFloat(solicitud.solicitud_original.fondo_efectivo.monto_aprobado).toFixed(2) : '0.00' }}</p>
                            <p class="text-sm text-gray-600"><strong>Estado del Fondo:</strong> {{
                                solicitud.solicitud_original.fondo_efectivo.estado || 'N/A' }}</p>
                            <p class="text-sm text-gray-600"><strong>Responsable del Fondo:</strong> {{
                                solicitud.solicitud_original.fondo_efectivo.responsable?.name || 'N/A' }} {{
                                    solicitud.solicitud_original.fondo_efectivo.responsable?.last_name || '' }}</p>
                            <p class="text-sm text-gray-600"><strong>Cargo del Responsable:</strong> {{
                                solicitud.solicitud_original.fondo_efectivo.responsable?.cargo || 'N/A' }}</p>
                            <p class="text-sm text-gray-600"><strong>Área del Fondo:</strong> {{
                                solicitud.solicitud_original.fondo_efectivo.area?.name || 'N/A' }}</p>
                        </div>

                        <div class="mb-6 p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2">Notas de Proceso</h4>
                            <p v-if="solicitud?.motivo_detalle" class="text-sm text-gray-600"><strong>Motivo Detalle:</strong> {{
                                solicitud.motivo_detalle }}</p>
                            <p v-if="solicitud?.motivo_observacion" class="text-sm text-gray-600"><strong>Motivo Observación:</strong>
                                {{ solicitud.motivo_observacion }}</p>
                            <p v-if="solicitud?.motivo_descargo" class="text-sm text-gray-600"><strong>Motivo Descargo:</strong> {{
                                solicitud.motivo_descargo }}</p>
                            <p v-if="solicitud?.motivo_rechazo_final" class="text-sm text-gray-600"><strong>Motivo Rechazo
                                    Final:</strong> {{ solicitud.motivo_rechazo_final }}</p>
                            <p v-if="!solicitud?.motivo_detalle && !solicitud?.motivo_observacion && !solicitud?.motivo_descargo && !solicitud?.motivo_rechazo_final"
                                class="text-sm text-gray-500">No hay notas de proceso adicionales.</p>
                        </div>

                        <div class="mb-6 p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2">Aprobadores</h4>
                            <p class="text-sm text-gray-600"><strong>Revisor ADM:</strong> {{ solicitud?.revisor_adm?.name || 'N/A' }}
                                {{ solicitud?.revisor_adm?.last_name || '' }}</p>
                            <p class="text-sm text-gray-600"><strong>Aprobador Gerente:</strong> {{ solicitud?.aprobador_gerente?.name
                                || 'N/A' }} {{ solicitud?.aprobador_gerente?.last_name || '' }}</p>
                        </div>

                        <div class="p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2">Detalles de Gastos Proyectados</h4>
                            <div v-if="detallesDeGastos.length > 0" class="space-y-2">
                                <div v-for="(detalle, idx) in detallesDeGastos" :key="idx"
                                    class="border-b border-gray-200 pb-2 last:border-b-0">
                                    <p class="text-sm text-gray-600"><strong>Descripción:</strong> {{ detalle.descripcion_gasto || 'N/A'
                                        }}</p>
                                    <p class="text-sm text-gray-600"><strong>Monto Estimado:</strong> S/. {{ detalle.monto_estimado ?
                                        parseFloat(detalle.monto_estimado).toFixed(2) : '0.00' }}</p>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-500">
                                No hay detalles de gastos proyectados.
                            </div>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-gray-50 to-verde-bap-extralight/30 px-6 py-4 border-t border-gray-200/50">
                        <div class="flex justify-end">
                            <button @click="cerrarModal"
                                class="group relative overflow-hidden px-6 py-3 bg-gradient-to-r from-verde-bap to-verde-bap-dark text-white font-semibold rounded-xl shadow-soft hover:shadow-glow-verde transition-all duration-300 hover:scale-105 focus:outline-none focus:ring-4 focus:ring-verde-bap/30">
                                <span class="relative z-10 flex items-center space-x-2">
                                    <span>Cerrar</span>
                                    <svg class="h-4 w-4 group-hover:translate-x-1 transition-transform duration-300" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
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
/* Transiciones del modal */
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

/* Animación de entrada secuencial para las tarjetas (no usada directamente aquí, pero útil si se aplica a elementos dentro) */
@keyframes fadeInUp {
    0% {
        opacity: 0;
        transform: translateY(30px);
    }

    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out forwards;
    opacity: 0;
}

/* Scroll personalizado mejorado */
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
