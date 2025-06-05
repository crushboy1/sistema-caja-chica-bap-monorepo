<template>
    <Transition name="modal-fade">
        <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-auto overflow-hidden transform transition-all sm:max-w-lg md:max-w-xl">
                <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-xl font-semibold text-gray-800">Gestionar Solicitud: {{ solicitud?.codigo_solicitud || 'N/A' }}</h3>
                    <button @click="cerrarModal()" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 max-h-[70vh] overflow-y-auto">
                    <div class="mb-6 p-4 border border-gray-200 rounded-md bg-gray-100">
                        <h4 class="text-lg font-bold text-gray-700 mb-2">Resumen de Solicitud</h4>
                        <p class="text-sm text-gray-600"><strong>Tipo:</strong> {{ solicitud?.tipo_solicitud || 'N/A' }}</p>
                        <p class="text-sm text-gray-600"><strong>Monto:</strong> S/. {{ solicitud?.monto_solicitado ? parseFloat(solicitud.monto_solicitado).toFixed(2) : '0.00' }}</p>
                        <p class="text-sm text-gray-600"><strong>Motivo:</strong> {{ solicitud?.motivo_detalle || 'N/A' }}</p>
                        <p class="text-sm text-gray-600"><strong>Estado Actual:</strong> {{ solicitud?.estado || 'N/A' }}</p>
                        <p class="text-sm text-gray-600"><strong>Solicitante:</strong> {{ solicitud?.solicitante?.name || 'N/A' }} {{ solicitud?.solicitante?.last_name || '' }}</p>
                    </div>

                    <div v-if="showJefeAdmActions" class="mb-6 p-4 border border-gray-200 rounded-md bg-gray-50">
                        <h4 class="text-lg font-bold text-gray-700 mb-4">Acciones de Administración</h4>
                        <div class="flex flex-wrap gap-3">
                            <button @click="ejecutarAccionSinMotivo('aprobarADM')"
                                class="px-4 py-2 bg-verde-bap text-white rounded-md hover:bg-verde-bap-dark transition-colors flex items-center space-x-2"
                                :disabled="isLoadingAction">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span>Aprobar ADM</span>
                            </button>
                            <button @click="iniciarAccionConMotivo('observarADM')"
                                class="px-4 py-2 bg-estado-observada-text text-white rounded-md hover:bg-observar-bap-hover transition-colors flex items-center space-x-2"
                                :disabled="isLoadingAction">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.306 17c-.77 1.333.192 3 1.732 3z" /></svg>
                                <span>Observar ADM</span>
                            </button>
                            <button @click="iniciarAccionConMotivo('rechazarFinal')"
                                class="px-4 py-2 bg-rojo-bap text-white rounded-md hover:bg-rojo-bap-hover transition-colors flex items-center space-x-2"
                                :disabled="isLoadingAction">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                <span>Rechazar</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="showGerenteGeneralActions" class="mb-6 p-4 border border-gray-200 rounded-md bg-gray-50">
                        <h4 class="text-lg font-bold text-gray-700 mb-4">Acciones de Gerencia General</h4>
                        <div class="flex flex-wrap gap-3">
                            <button @click="ejecutarAccionSinMotivo('aprobarGRTE')"
                                class="px-4 py-2 bg-verde-bap text-white rounded-md hover:bg-verde-bap-dark transition-colors flex items-center space-x-2"
                                :disabled="isLoadingAction">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span>Aprobar GRTE</span>
                            </button>
                            <button @click="iniciarAccionConMotivo('observarGRTE')"
                                class="px-4 py-2 bg-estado-observada-text text-white rounded-md hover:bg-observar-bap-hover transition-colors flex items-center space-x-2"
                                :disabled="isLoadingAction">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.306 17c-.77 1.333.192 3 1.732 3z" /></svg>
                                <span>Observar GRTE</span>
                            </button>
                            <button @click="iniciarAccionConMotivo('rechazarFinal')"
                                class="px-4 py-2 bg-rojo-bap text-white rounded-md hover:bg-rojo-bap-hover transition-colors flex items-center space-x-2"
                                :disabled="isLoadingAction">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                <span>Rechazar</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="showSolicitanteDescargoAction" class="mb-6 p-4 border border-gray-200 rounded-md bg-gray-50">
                        <h4 class="text-lg font-bold text-gray-700 mb-4">Acciones de Solicitante</h4>
                        <div class="flex flex-wrap gap-3">
                            <button @click="iniciarAccionConMotivo('presentarDescargo')"
                                class="px-4 py-2 bg-descargo-bap-hover text-white rounded-md hover:bg-estado-pendiente-text transition-colors flex items-center space-x-2"
                                :disabled="isLoadingAction">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                                <span>Presentar Descargo</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="showMotivoInput" class="mt-6 p-4 border border-gray-200 rounded-md bg-white shadow-inner">
                        <label for="motivoAccion" class="block text-sm font-medium text-gray-700 mb-2">{{ motivoLabel }}:</label>
                        <textarea id="motivoAccion" v-model="motivoAccion" :placeholder="motivoPlaceholder" rows="4"
                            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap resize-none"></textarea>
                        <div class="mt-4 flex justify-end space-x-3">
                            <button @click="cancelarAccion" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition-colors">
                                Cancelar
                            </button>
                            <button @click="ejecutarAccion"
                                class="px-4 py-2 bg-verde-bap text-white rounded-md hover:bg-verde-bap-dark transition-colors"
                                :disabled="isLoadingAction || (showMotivoInput && !motivoAccion)">
                                <span v-if="isLoadingAction">Enviando...</span>
                                <span v-else>Confirmar</span>
                            </button>
                        </div>
                    </div>

                    <div v-if="!showJefeAdmActions && !showGerenteGeneralActions && !showSolicitanteDescargoAction && !showMotivoInput" class="text-center text-gray-500 py-4">
                        No hay acciones disponibles para esta solicitud o su rol actual.
                    </div>
                </div>

                <div class="p-4 border-t border-gray-200 flex justify-end bg-gray-50">
                    <button @click="cerrarModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition-colors">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { defineProps, defineEmits, ref, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

const props = defineProps({
    mostrar: {
        type: Boolean,
        default: false
    },
    solicitud: {
        type: Object,
        default: null
    },
    usuarioActual: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close']);

// --- Variables de Estado Internas ---
const motivoAccion = ref('');
const accionActual = ref(null); // 'observarADM', 'observarGRTE', 'rechazarFinal', 'presentarDescargo', 'aprobarADM', 'aprobarGRTE'
const isLoadingAction = ref(false);

// --- Roles de Usuario (para visibilidad de botones dentro del modal) ---
const ROLES = {
    JEFE_AREA: 'jefe_area',
    JEFE_ADM: 'jefe_administracion',
    GERENTE_GENERAL: 'gerente_general',
    SUPER_ADMIN: 'super_admin',
    COLABORADOR: 'colaborador'
};

// --- Propiedades Computadas ---

// Determina si las acciones para Jefe de Administración deben ser visibles.
// Se ocultan si el usuario actual es el solicitante y es ADM/SuperAdmin en una solicitud de Decremento/Cierre.
const showJefeAdmActions = computed(() => {
    if (!props.solicitud || !props.usuarioActual || !props.usuarioActual.role) return false;

    const estado = props.solicitud.estado;
    const rolActual = props.usuarioActual.role.name;
    const isSolicitanteAdminOrSuperAdmin = props.usuarioActual.id === props.solicitud.id_solicitante &&
                                            [ROLES.JEFE_ADM, ROLES.SUPER_ADMIN].includes(rolActual);
    const isDecrementoCierre = ['Decremento', 'Cierre'].includes(props.solicitud.tipo_solicitud);

    // Si el usuario actual es Jefe ADM o Super Admin Y la solicitud está en un estado gestionable por ADM
    const canActAsAdm = (rolActual === ROLES.JEFE_ADM || rolActual === ROLES.SUPER_ADMIN) &&
                               (estado === 'Pendiente Aprobación ADM' || estado === 'Descargo Enviado ADM');

    // Restricción: Si el solicitante es un ADM/SuperAdmin y es una solicitud de Decremento/Cierre,
    // el ADM no puede "gestionar" su propia solicitud de esta forma (aprobar/observar/rechazar).
    // Esta restricción está manejada en el backend también, pero es bueno ocultar la UI.
    if (canActAsAdm && isSolicitanteAdminOrSuperAdmin && isDecrementoCierre) {
        return false;
    }

    return canActAsAdm;
});

// Determina si las acciones para Gerente General deben ser visibles.
// Se ocultan si el usuario actual es el solicitante y es GRTE/SuperAdmin en una solicitud de Decremento/Cierre.
const showGerenteGeneralActions = computed(() => {
    if (!props.solicitud || !props.usuarioActual || !props.usuarioActual.role) return false;

    const estado = props.solicitud.estado;
    const rolActual = props.usuarioActual.role.name;
    const isSolicitanteGrteOrSuperAdmin = props.usuarioActual.id === props.solicitud.id_solicitante &&
                                           [ROLES.GERENTE_GENERAL, ROLES.SUPER_ADMIN].includes(rolActual);
    const isDecrementoCierre = ['Decremento', 'Cierre'].includes(props.solicitud.tipo_solicitud);

    // Si el usuario actual es Gerente General o Super Admin Y la solicitud está en un estado gestionable por GRTE
    const canActAsGrte = (rolActual === ROLES.GERENTE_GENERAL || rolActual === ROLES.SUPER_ADMIN) &&
                               (estado === 'Pendiente Aprobación GRTE' || estado === 'Descargo Enviado GRTE');

    // Restricción: Si el solicitante es un GRTE/SuperAdmin y es una solicitud de Decremento/Cierre,
    // el GRTE no puede "gestionar" su propia solicitud de esta forma (aprobar/observar/rechazar).
    // Esto se alinea con la regla de que "Solo el Gerente General (otro usuario) puede hacerlo".
    if (canActAsGrte && isSolicitanteGrteOrSuperAdmin && isDecrementoCierre) {
        return false;
    }
    
    return canActAsGrte;
});

// Determina si se debe mostrar el botón de "Presentar Descargo" para el solicitante.
// Esta acción está disponible para cualquier solicitante si su solicitud está en estado 'Observada ADM' u 'Observada GRTE'.
const showSolicitanteDescargoAction = computed(() => {
    if (!props.solicitud || !props.usuarioActual) return false;

    const estado = props.solicitud.estado;
    const usuarioEsSolicitante = props.usuarioActual.id === props.solicitud.id_solicitante;

    return usuarioEsSolicitante && (estado === 'Observada ADM' || estado === 'Observada GRTE');
});


// Determina si el campo de texto para el motivo debe ser visible
const showMotivoInput = computed(() => {
    return accionActual.value === 'observarADM' ||
           accionActual.value === 'observarGRTE' ||
           accionActual.value === 'rechazarFinal' ||
           accionActual.value === 'presentarDescargo';
});

// Texto del placeholder para el campo de motivo
const motivoPlaceholder = computed(() => {
    switch (accionActual.value) {
        case 'observarADM':
            return 'Escribe el motivo de la observación de Administración aquí...';
        case 'observarGRTE':
            return 'Escribe el motivo de la observación de Gerencia General aquí...';
        case 'rechazarFinal':
            return 'Escribe el motivo del rechazo definitivo aquí...';
        case 'presentarDescargo':
            return 'Escribe tu descargo aquí...';
        default:
            return '';
    }
});

// Título del campo de motivo
const motivoLabel = computed(() => {
    switch (accionActual.value) {
        case 'observarADM':
        case 'observarGRTE':
            return 'Motivo de la Observación';
        case 'rechazarFinal':
            return 'Motivo del Rechazo';
        case 'presentarDescargo':
            return 'Motivo del Descargo';
        default:
            return '';
    }
});

// --- Funciones de Acción ---

// Función genérica para iniciar una acción que requiere motivo
const iniciarAccionConMotivo = (accion) => {
    accionActual.value = accion;
    motivoAccion.value = ''; // Limpiar el motivo anterior
};

// Función para cancelar la acción actual y cerrar el input de motivo
const cancelarAccion = () => {
    accionActual.value = null;
    motivoAccion.value = '';
};

// Función para cerrar el modal de gestión
const cerrarModal = (refresh = false) => {
    accionActual.value = null; // Resetear la acción al cerrar
    motivoAccion.value = '';
    emit('close', refresh);
};

// Función para ejecutar la acción de la API
const ejecutarAccion = async () => {
    if (!props.solicitud) return;

    let endpoint = `/solicitudes-fondo/${props.solicitud.id}`;
    let payload = {};
    let successMessageFromAPI = ''; // Variable para el mensaje de éxito de la API
    let errorMessage = '';
    let confirmationTitle = '';
    let confirmationText = '';
    let confirmButtonText = '';
    
    isLoadingAction.value = true;

    try {
        switch (accionActual.value) {
            case 'aprobarADM': {
                confirmationTitle = 'Confirmar Aprobación';
                confirmationText = '¿Estás seguro de aprobar esta solicitud por Administración?';
                confirmButtonText = 'Sí, Aprobar';
                payload.estado = 'Aprobada ADM'; // El backend determinará el siguiente estado principal
                errorMessage = 'Error al aprobar por Administración.';
                break;
            }
            case 'observarADM': {
                if (!motivoAccion.value) {
                    Swal.fire('Advertencia', 'El motivo de la observación es obligatorio.', 'warning');
                    isLoadingAction.value = false;
                    return;
                }
                confirmationTitle = 'Confirmar Observación';
                confirmationText = `¿Estás seguro de observar esta solicitud por Administración con el siguiente motivo:<br><strong>"${motivoAccion.value}"</strong>?`;
                confirmButtonText = 'Sí, Observar';
                payload.estado = 'Observada ADM';
                payload.motivo_observacion = motivoAccion.value;
                errorMessage = 'Error al observar por Administración.';
                break;
            }
            case 'aprobarGRTE': {
                confirmationTitle = 'Confirmar Aprobación';
                confirmationText = '¿Estás seguro de aprobar esta solicitud por Gerencia General? Esta acción es final.';
                confirmButtonText = 'Sí, Aprobar';
                payload.estado = 'Aprobada'; // El backend la marcará como Aprobada (final) y gestionará el FondoEfectivo
                errorMessage = 'Error al aprobar por Gerencia General.';
                break;
            }
            case 'observarGRTE': {
                if (!motivoAccion.value) {
                    Swal.fire('Advertencia', 'El motivo de la observación es obligatorio.', 'warning');
                    isLoadingAction.value = false;
                    return;
                }
                confirmationTitle = 'Confirmar Observación';
                confirmationText = `¿Estás seguro de observar esta solicitud por Gerencia General con el siguiente motivo:<br><strong>"${motivoAccion.value}"</strong>?`;
                confirmButtonText = 'Sí, Observar';
                payload.estado = 'Observada GRTE';
                payload.motivo_observacion = motivoAccion.value;
                errorMessage = 'Error al observar por Gerencia General.';
                break;
            }
            case 'rechazarFinal': {
                if (!motivoAccion.value) {
                    Swal.fire('Advertencia', 'El motivo del rechazo es obligatorio.', 'warning');
                    isLoadingAction.value = false;
                    return;
                }
                confirmationTitle = 'Confirmar Rechazo Definitivo';
                confirmationText = `¿Estás seguro de rechazar definitivamente esta solicitud con el siguiente motivo:<br><strong>"${motivoAccion.value}"</strong>? Esta acción no se puede revertir.`;
                confirmButtonText = 'Sí, Rechazar';
                payload.estado = 'Rechazada Final';
                payload.motivo_rechazo_final = motivoAccion.value;
                errorMessage = 'Error al rechazar definitivamente.';
                break;
            }
            case 'presentarDescargo': {
                if (!motivoAccion.value) {
                    Swal.fire('Advertencia', 'El descargo no puede estar vacío.', 'warning');
                    isLoadingAction.value = false;
                    return;
                }
                let newStateDescargo = '';
                if (props.solicitud.estado === 'Observada ADM') {
                    newStateDescargo = 'Descargo Enviado ADM';
                } else if (props.solicitud.estado === 'Observada GRTE') {
                    newStateDescargo = 'Descargo Enviado GRTE';
                } else {
                    Swal.fire('Advertencia', 'No se puede presentar descargo en el estado actual de la solicitud.', 'warning');
                    isLoadingAction.value = false;
                    return;
                }
                confirmationTitle = 'Confirmar Envío de Descargo';
                confirmationText = `¿Estás seguro de enviar el siguiente descargo:<br><strong>"${motivoAccion.value}"</strong>?`;
                confirmButtonText = 'Sí, Enviar Descargo';
                payload.estado = newStateDescargo;
                payload.motivo_descargo = motivoAccion.value;
                errorMessage = 'Error al presentar descargo.';
                break;
            }
            default:
                Swal.fire('Error', 'Acción no reconocida.', 'error');
                isLoadingAction.value = false;
                return;
        }

        // Definir qué acciones requieren una confirmación adicional
        const requiresConfirmation = [
            'aprobarADM', 'aprobarGRTE',
            'observarADM', 'observarGRTE',
            'rechazarFinal', 'presentarDescargo'
        ];

        if (requiresConfirmation.includes(accionActual.value)) {
            const { isConfirmed } = await Swal.fire({
                title: confirmationTitle,
                html: confirmationText, // Usar html para permitir negritas y saltos de línea
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: confirmButtonText,
                cancelButtonText: 'Cancelar'
            });

            if (!isConfirmed) {
                isLoadingAction.value = false;
                return; // El usuario canceló la acción
            }
        }

        console.log('--- Enviando PATCH a la API ---');
        console.log('Endpoint:', endpoint);
        console.log('Payload:', payload);
        console.log('-----------------------------');

        // Realizar la llamada a la API y CAPTURAR LA RESPUESTA
        const response = await api.patch(endpoint, payload);
        
        // El mensaje de éxito ahora se tomará directamente de la respuesta de la API
        // que ya contiene el código del fondo cuando sea relevante.
        successMessageFromAPI = response.data.message;

        // Pequeña pausa antes de mostrar la alerta de éxito para una transición más suave
        await new Promise(resolve => setTimeout(resolve, 300));

        Swal.fire('¡Éxito!', successMessageFromAPI, 'success');
        cerrarModal(true); // Cerrar y refrescar la tabla padre
    } catch (error) {
        console.error(`Error al ejecutar acción '${accionActual.value}':`, error);
        let apiErrorMessage = 'Ha ocurrido un error inesperado.';
        // Mejorar el mensaje de error si la API devuelve detalles de validación
        if (error.response && error.response.data) {
            // Si hay un mensaje principal de la API
            if (error.response.data.message) {
                apiErrorMessage = error.response.data.message;
            }
            // Si hay errores de validación específicos (común en Laravel, por ejemplo)
            if (error.response.data.errors) {
                const validationErrors = Object.values(error.response.data.errors).flat().join('<br>');
                apiErrorMessage += `<br>Detalles: ${validationErrors}`; // Usar <br> para saltos de línea en HTML
            }
        } else if (error.message) {
            apiErrorMessage = error.message; // Captura errores de red, etc.
        }
        Swal.fire('¡Error!', `${errorMessage}<br>${apiErrorMessage}`, 'error'); // Usar <br> para saltos de línea en HTML
    } finally {
        isLoadingAction.value = false;
    }
};

// Función para acciones que no requieren motivo (como Aprobar)
// Esta función solo establece la acción y luego llama a `ejecutarAccion`
const ejecutarAccionSinMotivo = (accion) => {
    accionActual.value = accion; // Establecer la acción para que `ejecutarAccion` sepa qué hacer
    ejecutarAccion(); // Ejecutar directamente
};

// Limpiar estado cuando el modal se cierra
watch(() => props.mostrar, (newVal) => {
    if (!newVal) {
        accionActual.value = null;
        motivoAccion.value = '';
    }
});
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
/* La clase resize-none es de Tailwind CSS, no necesita definición aquí si Tailwind está configurado */
</style>
