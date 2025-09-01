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
});

const emit = defineEmits(['close']);

const currencyFormatter = new Intl.NumberFormat('es-PE', {
    style: 'currency',
    currency: 'PEN',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
// --- PROPIEDADES COMPUTADAS PARA EVIDENCIAS ---

const evidenciaUrl = computed(() => {
    if (props.gasto?.dj_consolidada?.ruta_documento_firmado) {
        return `/storage/${props.gasto.dj_consolidada.ruta_documento_firmado}`;
    }
    if (props.gasto?.ruta_evidencia) {
        return `/storage/${props.gasto.ruta_evidencia}`;
    }
    // Fallback por si la URL viene completa desde la API (aunque no debería ser el caso principal)
    if (props.gasto?.dj_consolidada?.documento_url) {
        return props.gasto.dj_consolidada.documento_url;
    }
    if (props.gasto?.evidencia_url) {
        return props.gasto.evidencia_url;
    }
    return null;
});

const tipoEvidencia = computed(() => {
    if (!evidenciaUrl.value) return 'ninguna';
    const url = evidenciaUrl.value.toLowerCase();
    if (url.includes('.pdf')) return 'pdf';
    const extensionesImagen = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    const extension = url.split('.').pop().split('?')[0];
    if (extensionesImagen.includes(extension)) return 'imagen';
    return 'documento';
});

const nombreArchivo = computed(() => {
    if (!evidenciaUrl.value) return 'evidencia';
    const codigoGasto = props.gasto?.codigo_gasto || 'gasto';
    const extension = evidenciaUrl.value.split('.').pop().split('?')[0] || 'file';
    return `evidencia_${codigoGasto}.${extension}`;
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
const tipoDocumentoNombre = computed(() => props.gasto?.tipo_documento?.nombre || 'N/A');
const montoTotal = computed(() => parseFloat(props.gasto?.monto_total || 0));
const montoExcedidoAlRegistrar = computed(() => parseFloat(props.gasto?.monto_excedido_al_registrar || 0));
const saldoDisponibleAlRegistrar = computed(() => parseFloat(props.gasto?.saldo_disponible_al_registrar || 0));
const alertaMontoExcedido = computed(() => montoExcedidoAlRegistrar.value > 0);

//METODOS
const formatCampo = (campo) => {
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

// Función para manejar la descarga del archivo. Esto es más fiable que el atributo `download`
const descargarEvidencia = async () => {
    if (!evidenciaUrl.value) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin evidencia',
            text: 'No hay evidencia disponible para descargar.'
        });
        return;
    }

    try {
        // Crear enlace de descarga
        const link = document.createElement('a');
        link.href = evidenciaUrl.value;
        link.setAttribute('download', nombreArchivo.value);
        link.setAttribute('target', '_blank');

        // Agregar al DOM temporalmente y hacer click
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

    } catch (error) {
        console.error('Error al descargar:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error de descarga',
            text: 'No se pudo descargar la evidencia. Inténtalo de nuevo.'
        });
    }
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
                            <div v-if="alertaMontoExcedido"
                                class="mb-3 p-3 border-l-4 border-rojo-bap bg-red-50 text-rojo-bap flex items-center">
                                <svg class="w-5 h-5 mr-2 text-rojo-bap" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 5.636l-1.414-1.414A9 9 0 105.636 18.364l1.414 1.414A9 9 0 1018.364 5.636z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4m0 4h.01" />
                                </svg>
                                <span>
                                    <strong>¡Atención!</strong> Este gasto de
                                    <b class="text-rojo-bap">{{ currencyFormatter.format(montoTotal) }}</b>
                                    excedió el saldo disponible de la proyección (que era
                                    <b class="text-rojo-bap">{{ currencyFormatter.format(saldoDisponibleAlRegistrar)
                                        }}</b>)
                                    en
                                    <b class="text-rojo-bap">{{ currencyFormatter.format(montoExcedidoAlRegistrar)
                                        }}</b>.
                                </span>
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
                                <span class="font-medium text-gray-800">{{ tipoDocumentoNombre }}</span>
                                <template v-if="tipoDocumentoNombre !== 'Declaración Jurada'">
                                    <span class="text-gray-500 font-medium">Serie del Documento:</span>
                                    <span class="font-medium text-gray-800">{{ gasto?.serie_documento || 'N/A' }}</span>

                                    <span class="text-gray-500 font-medium">Correlativo del Documento:</span>
                                    <span class="font-medium text-gray-800">{{ gasto?.correlativo_documento || 'N/A'
                                        }}</span>
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
                                <span class="text-gray-500 font-medium">SALDO DISPONIBLE (al registrar):</span>
                                <span
                                    :class="['font-bold', saldoDisponibleAlRegistrar < 0 ? 'text-rojo-bap' : 'text-verde-bap']">
                                    {{ currencyFormatter.format(saldoDisponibleAlRegistrar) }}
                                </span>
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
                                <span v-if="props.gasto?.dj_consolidada"
                                    class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                                    Consolidada
                                </span>
                            </h4>

                            <!-- Sin evidencia -->
                            <div v-if="tipoEvidencia === 'ninguna'" class="text-center py-8">
                                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <p class="text-sm text-gray-500">No se adjuntó evidencia para este gasto</p>
                            </div>

                            <!-- Con evidencia -->
                            <div v-else class="mt-2">
                                <!-- IMAGEN -->
                                <div v-if="tipoEvidencia === 'imagen'" class="text-center">
                                    <a :href="evidenciaUrl" target="_blank" rel="noopener noreferrer"
                                        class="block group">
                                        <img :src="evidenciaUrl" :alt="`Evidencia del gasto ${gasto?.codigo_gasto}`"
                                            class="w-full h-auto max-h-60 object-contain rounded-lg border border-gray-300 shadow-soft group-hover:shadow-glow-verde transition-all mx-auto"
                                            @error="$event.target.src = '/images/image-error.png'">
                                    </a>
                                    <p class="text-xs text-gray-500 mt-2">Click en la imagen para ver en tamaño completo en otra pestaña
                                    </p>
                                </div>

                                <!-- PDF -->
                                <div v-else-if="tipoEvidencia === 'pdf'" class="text-center">
                                    <div
                                        class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-red-50 to-orange-50 rounded-lg border-2 border-dashed border-red-300">
                                        <svg class="w-16 h-16 text-red-500 mb-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <h5 class="text-lg font-semibold text-gray-800 mb-2">Documento PDF</h5>
                                        <p class="text-sm text-gray-600 mb-4">La evidencia está en formato PDF</p>
                                        <a :href="evidenciaUrl" target="_blank" rel="noopener noreferrer"
                                            class="inline-flex items-center space-x-2 px-4 py-2 bg-rojo-bap-dark hover:bg-rojo-bap-hover text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all transform hover:scale-105">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>Ver PDF en nueva pestaña</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- DOCUMENTO GENÉRICO -->
                                <div v-else class="text-center">
                                    <div
                                        class="flex flex-col items-center justify-center p-6 bg-gradient-to-br from-gray-50 to-blue-50 rounded-lg border-2 border-dashed border-gray-300">
                                        <svg class="w-16 h-16 text-gray-500 mb-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <h5 class="text-lg font-semibold text-gray-800 mb-2">Documento</h5>
                                        <p class="text-sm text-gray-600 mb-4">Evidencia en formato de documento</p>
                                        <a :href="evidenciaUrl" target="_blank" rel="noopener noreferrer"
                                            class="inline-flex items-center space-x-2 px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all transform hover:scale-105">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            <span>Ver Documento</span>
                                        </a>
                                    </div>
                                </div>

                                <!-- Botón de descarga universal -->
                                <div class="flex justify-center mt-6">
                                    <button @click="descargarEvidencia"
                                        class="inline-flex items-center space-x-2 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold rounded-lg shadow-md hover:shadow-lg transition-all transform hover:scale-105 focus:outline-none focus:ring-4 focus:ring-blue-300">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        <span>Descargar Evidencia</span>
                                    </button>
                                </div>

                                <!-- Información adicional -->
                                <div class="mt-4 p-3 bg-blue-50 rounded-lg border-l-4 border-blue-400">
                                    <div class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-400 mt-0.5 mr-3 flex-shrink-0" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <div class="text-sm text-blue-800">
                                            <p class="font-medium">Información de la evidencia:</p>
                                            <p>• Tipo: {{ tipoEvidencia.charAt(0).toUpperCase() + tipoEvidencia.slice(1)
                                                }}</p>
                                            <p v-if="props.gasto?.dj_consolidada">• Documento consolidado (DJ firmada)
                                            </p>
                                            <p v-else>• Documento individual adjunto</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="gasto?.historial_aprobaciones && gasto.historial_aprobaciones.length > 0"
                            class="mt-6 p-4 border border-gray-200 rounded-md bg-white/70 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-3 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-verde-bap-dark" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Historial de Cambios
                            </h4>
                            <ul class="space-y-4">
                                <li v-for="historial in gasto.historial_aprobaciones" :key="historial.id"
                                    class="text-xs border-t border-gray-200 pt-3">
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1">
                                        <span class="text-gray-500 font-medium">Acción:</span>
                                        <span class="font-semibold text-gray-800">{{ historial.estado_nuevo }}</span>
                                        <span class="text-gray-500 font-medium">Realizada por:</span>
                                        <span class="font-medium text-gray-800">{{ historial.usuario_accion.name }} {{
                                            historial.usuario_accion.last_name }}</span>
                                        <span class="text-gray-500 font-medium">Fecha:</span>
                                        <span class="font-medium text-gray-800">{{ formatearFecha(historial.created_at)
                                        }}</span>
                                    </div>
                                    <p v-if="historial.comentario" class="mt-2"><strong
                                            class="text-gray-600">Comentario:</strong> <em class="text-gray-700">"{{
                                                historial.comentario }}"</em></p>

                                    <div v-if="historial.cambios_realizados"
                                        class="mt-2 p-2 bg-gray-100 rounded-md border border-gray-200">
                                        <p class="font-semibold text-gray-800 text-xs mb-1">Detalle de la corrección:
                                        </p>
                                        <ul class="list-disc list-inside text-gray-700 space-y-1">
                                            <li v-for="(cambio, campo) in historial.cambios_realizados" :key="campo">
                                                <strong class="capitalize">{{ formatCampo(campo) }}:</strong> cambió de
                                                '{{ cambio.anterior }}'
                                                a '{{ cambio.nuevo }}'
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
