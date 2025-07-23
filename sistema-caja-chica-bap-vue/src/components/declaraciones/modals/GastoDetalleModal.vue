<script setup>
import { defineProps, defineEmits, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
const props = defineProps({
    mostrar: {
        type: Boolean,
        default: false
    },
    gasto: {
        type: Object,
        default: () => null
    },
    usuarioActual: { 
        type: Object, 
        default: () => null 
    }
});

const emit = defineEmits(['close','grupoValidado']);

// Propiedad computada para obtener la URL de la evidencia de forma segura
// --- PROPIEDADES COMPUTADAS PARA EVIDENCIAS ---
const esAdmin = computed(() => {
    const rolesAdmin = ['jefe_administracion', 'super_admin'];
    return rolesAdmin.includes(props.usuarioActual?.role?.name);
});
const puedeValidarGrupoDJ = computed(() => {
    return esAdmin.value && props.gasto?.estado === 'Pendiente de Validación DJ';
});
// Devuelve la URL de la evidencia INDIVIDUAL.
const evidenciaIndividualUrl = computed(() => {
    if (!props.gasto?.ruta_evidencia) return null;
    return `/storage/${props.gasto.ruta_evidencia}`;
});

// Devuelve el objeto de la evidencia CONSOLIDADA.
const djConsolidadaEvidencia = computed(() => {
    if (!props.gasto?.dj_consolidada) return null;
    return {
        url: `/storage/${props.gasto.dj_consolidada.ruta_documento}`,
        // Podríamos añadir más datos si fueran necesarios, como el uploader.
    };
});

// Determina si la evidencia a mostrar (la individual) es una imagen.
const esImagen = computed(() => {
    if (!evidenciaIndividualUrl.value) return false;
    const extension = evidenciaIndividualUrl.value.split('.').pop().toLowerCase();
    return ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension);
});

// Determina si la evidencia a mostrar (la individual) es un PDF.
const esPdf = computed(() => {
    return evidenciaIndividualUrl.value && evidenciaIndividualUrl.value.toLowerCase().endsWith('.pdf');
});
//Propiedades computadas para acceder a datos anidados de forma segura y limpia.
const registradorNombre = computed(() => `${props.gasto?.registrador?.name || ''} ${props.gasto?.registrador?.last_name || ''}`.trim() || 'N/A');
const registradorRol = computed(() => props.gasto?.registrador?.role?.display_name || 'N/A');
const registradorArea = computed(() => props.gasto?.registrador?.area?.name || 'N/A');
const fondoCodigo = computed(() => props.gasto?.fondo_efectivo?.codigo_fondo || 'N/A');
const fondoMontoOriginal = computed(() => parseFloat(props.gasto?.fondo_efectivo?.monto_aprobado || 0).toFixed(2));
const proyeccionMontoOriginal = computed(() => parseFloat(props.gasto?.monto_proyectado_original || 0).toFixed(2));
const proyeccionDescripcion = computed(() => props.gasto?.gasto_proyectado?.descripcion || 'No especificada');
const cuentaContableInfo = computed(() => {
    if (!props.gasto?.cuenta_contable) return 'N/A';
    return `${props.gasto.cuenta_contable.codigo_cuenta} - ${props.gasto.cuenta_contable.descripcion}`;
});
const montoTotal = computed(() => parseFloat(props.gasto?.monto_total || 0));
const alertaMontoMayor = computed(() => montoTotal.value > parseFloat(props.gasto?.monto_proyectado_original || 0));
//METODOS

const validarGrupoDJ = async () => {
    if (!props.gasto?.id_dj_consolidada) {
        Swal.fire('Error', 'No se pudo encontrar el ID de la DJ consolidada.', 'error');
        return;
    }

    const { isConfirmed } = await Swal.fire({
        title: '¿Validar Grupo de Gastos?',
        text: "Esta acción validará todos los gastos asociados a esta Declaración Jurada. Pasarán a 'Pendiente de Validación Contable'.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, validar grupo',
        cancelButtonText: 'Cancelar'
    });

    if (isConfirmed) {
        try {
            const response = await api.put(`/v1/djs-consolidadas/${props.gasto.id_dj_consolidada}/validar`);
            Swal.fire('¡Grupo Validado!', response.data.message, 'success');
            emit('grupoValidado'); // Notificar al padre para que refresque la lista
            emit('close');
        } catch (error) {
            console.error("Error al validar el grupo de DJ:", error);
            Swal.fire('Error', error.response?.data?.message || 'No se pudo validar el grupo.', 'error');
        }
    }
};
const formatCampo= (campo)=> {
        // Asegurar que campo sea una cadena antes de usar replace
        if (typeof campo === 'string') {
            return campo.replace(/_/g, ' ');
        }
        // Si no es una cadena, convertirla a cadena primero
        return String(campo).replace(/_/g, ' ');
    };
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
                            <!-- ALERTA si el monto total es mayor al proyectado -->
                            <div v-if="alertaMontoMayor"
                                class="mb-3 p-3 border-l-4 border-rojo-bap bg-red-50 text-rojo-bap flex items-center">
                                <svg class="w-5 h-5 mr-2 text-rojo-bap" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 5.636l-1.414-1.414A9 9 0 105.636 18.364l1.414 1.414A9 9 0 1018.364 5.636z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4m0 4h.01" />
                                </svg>
                                <span><strong>¡Atención!</strong> El <b>MONTO TOTAL</b> es mayor al <b>MONTO
                                        PROYECTADO</b>.</span>
                            </div>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <span class="text-gray-500 font-medium">MONTO TOTAL:</span>
                                <span class="font-bold text-verde-bap">S/. {{ gasto?.monto_total ?
                                    parseFloat(gasto.monto_total).toFixed(2) : '0.00' }}</span>
                                <span class="text-gray-500 font-medium">Estado Actual:</span>
                                <span class="font-medium text-gray-800">{{ gasto?.estado || 'N/A' }}</span>
                                <span class="text-gray-500 font-medium">Fecha de Registro:</span>
                                <span class="font-medium text-gray-800">{{ formatearFecha(gasto?.created_at) }}</span>
                                <span class="text-gray-500 font-medium">Glosa/descripción del gasto:</span>
                                <span class="font-medium text-gray-800">{{ gasto?.glosa || 'No especificada' }}</span>
                                <span v-if="gasto?.comentario" class="text-gray-500 font-medium">Comentario
                                    Adicional:</span>
                                <span v-if="gasto?.comentario" class="font-medium text-gray-800">{{ gasto.comentario
                                    }}</span>
                                <span class="text-gray-500 font-medium">Tipo de Documento:</span>
                                <span class="font-medium text-gray-800">{{ gasto?.tipo_documento || 'N/A' }}</span>
                                <template v-if="gasto?.tipo_documento !== 'Declaración Jurada'">
                                    <span v-if="!gasto?.es_declaracion_jurada"
                                        class="text-gray-500 font-medium">Comprobante:</span>
                                    <span v-if="!gasto?.es_declaracion_jurada" class="font-medium text-gray-800">{{
                                        gasto?.serie_documento || 'S/S' }} - {{ gasto?.correlativo_documento || 'S/C'
                                        }}</span>
                                </template>
                                <template v-else>
                                    <span class="text-gray-500 font-medium">Serie:</span>
                                    <span class="font-medium text-gray-800">N/A</span>
                                    <span class="text-gray-500 font-medium">Correlativo:</span>
                                    <span class="font-medium text-gray-800">N/A</span>
                                </template>
                            </div>
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
                            <p class="text-sm text-gray-600"><strong>Nombre:</strong> {{ registradorNombre }}</p>
                            <p class="text-sm text-gray-600"><strong>Rol:</strong> {{ registradorRol }}</p>
                            <p class="text-sm text-gray-600"><strong>Área:</strong> {{ registradorArea }}</p>
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
                            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                <span class="text-gray-500 font-medium">Fondo Afectado:</span>
                                <span class="font-medium text-gray-800">{{ fondoCodigo }}</span>
                                <span class="text-gray-500 font-medium">Proyección Original:</span>
                                <span class="font-medium text-blue-800">{{ proyeccionDescripcion }}</span>
                                <span class="text-gray-500 font-medium">MONTO PROYECTADO:</span>
                                <span class="font-bold text-blue-800">S/. {{ proyeccionMontoOriginal }}</span>
                                <span class="text-gray-500 font-medium">MONTO ORIGINAL DEL FONDO:</span>
                                <span class="font-bold text-orange-700">S/. {{ fondoMontoOriginal }}</span>
                                <span class="text-gray-500 font-medium">Cuenta Contable:</span>
                                <span class="font-medium text-gray-800">{{ cuentaContableInfo }}</span>
                            </div>
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

                            <!-- CASO 1: El gasto tiene una evidencia individual (imagen) -->
                            <div v-if="esImagen" class="mt-2">
                                <a :href="evidenciaIndividualUrl" target="_blank" rel="noopener noreferrer"
                                    class="block group">
                                    <img :src="evidenciaIndividualUrl" alt="Evidencia del gasto"
                                        class="w-full h-auto max-h-60 object-contain rounded-lg border border-gray-300 shadow-soft group-hover:shadow-glow-verde transition-all">
                                </a>
                                <div class="flex justify-center mt-4">
                                    <a :href="evidenciaIndividualUrl" download
                                        class="inline-flex items-center space-x-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all transform hover:scale-105">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                            </path>
                                        </svg>
                                        <span>Descargar Evidencia</span>
                                    </a>
                                </div>
                            </div>

                            <!-- CASO 2: El gasto tiene evidencia en formato PDF (individual o consolidada) -->
                            <div v-else-if="esPdf || djConsolidadaEvidencia" class="mt-2">
                                <div
                                    class="flex flex-col items-center justify-center p-4 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300">
                                    <svg class="w-12 h-12 text-rojo-bap" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M10 21h7a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v11m0 5l4.879-4.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242z">
                                        </path>
                                    </svg>
                                    <a :href="esPdf ? evidenciaIndividualUrl : djConsolidadaEvidencia.url"
                                        target="_blank" rel="noopener noreferrer"
                                        class="mt-2 text-sm text-verde-bap-dark hover:underline font-semibold">
                                        Ver PDF en nueva pestaña
                                    </a>
                                </div>
                                <div class="flex justify-center mt-4">
                                    <a :href="esPdf ? evidenciaIndividualUrl : djConsolidadaEvidencia.url" download
                                        class="inline-flex items-center space-x-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all transform hover:scale-105">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                            </path>
                                        </svg>
                                        <span>Descargar {{ esPdf ? 'Evidencia' : 'DJ Consolidada' }}</span>
                                    </a>
                                </div>
                            </div>

                            <!-- CASO 3: No hay ninguna evidencia (salvaguarda) -->
                            <p v-else class="text-sm text-gray-500 mt-2">No se adjuntó evidencia para este gasto.</p>
                        </div>
                        <div v-if="gasto?.historial_aprobaciones && gasto.historial_aprobaciones.length > 0" class="mt-6 p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-verde-bap-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Historial de Cambios
                            </h4>
                            <ul class="space-y-4">
                                <li v-for="historial in gasto.historial_aprobaciones" :key="historial.id" class="text-xs border-t border-gray-200 pt-3">
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                        <span class="text-gray-500 font-medium">Acción:</span>
                                        <span class="font-semibold text-gray-800">{{ historial.estado_nuevo }}</span>
                                        <span class="text-gray-500 font-medium">Realizada por:</span>
                                        <span class="font-medium text-gray-800">{{ historial.usuario_accion.name }} {{ historial.usuario_accion.last_name }}</span>
                                        <span class="text-gray-500 font-medium">Fecha:</span>
                                        <span class="font-medium text-gray-800">{{ formatearFecha(historial.fecha_cambio) }}</span>
                                    </div>
                                    <p v-if="historial.comentario" class="mt-2"><strong class="text-gray-600">Comentario:</strong> <em class="text-gray-700">"{{ historial.comentario }}"</em></p>
                                    
                                    <div v-if="historial.cambios_realizados" class="mt-2 p-2 bg-gray-100 rounded-md border border-gray-200">
                                        <p class="font-semibold text-gray-800 text-xs mb-1">Detalle de la corrección:</p>
                                        <ul class="list-disc list-inside text-gray-700 space-y-1">
                                            <li v-for="(cambio, campo) in JSON.parse(historial.cambios_realizados)" :key="campo">
                                                <strong class="capitalize">{{ formatCampo(campo) }}:</strong> cambió de '{{ cambio.anterior }}' a '{{ cambio.nuevo }}'
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Pie de página del modal con botón de cierre estilizado -->
                    <div
                        class="bg-gradient-to-r from-gray-50 to-verde-bap-extralight/30 px-6 py-4 border-t border-gray-200/50">
                        <div class="flex justify-end">
                            <button v-if="puedeValidarGrupoDJ" @click="validarGrupoDJ"
                                class="group relative overflow-hidden px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white font-semibold rounded-xl ...">
                                <span class="relative z-10 flex items-center space-x-3">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Validar Grupo de DJ</span>
                                </span>
                            </button>
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
