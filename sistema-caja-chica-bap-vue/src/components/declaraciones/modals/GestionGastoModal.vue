<script setup>
import { ref, watch, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { getClassesForActionButton , getTextClassForState } from '@/utils/statusStyles.js';
// --- PROPS Y EMITS ---
const props = defineProps({
    mostrar: Boolean,
    gasto: Object,
    usuarioActual: Object,
});
const emit = defineEmits(['close', 'accionRealizada']);

// --- ESTADO INTERNO ---
const isLoading = ref(false);
const accionActual = ref(null);
const motivoRechazo = ref('');
// NUEVO: Estado para el tipo de cambio
const tipoCambio = ref(null);

// --- COMPUTED PROPS ---
const esGastoPropio = computed(() => {
    if (!props.gasto || !props.usuarioActual) return false;
    return props.gasto.id_registrador === props.usuarioActual.id;
});

// NUEVO: Calcula el monto convertido para mostrarlo en la UI
const montoConvertido = computed(() => {
    if (props.gasto?.moneda === 'USD' && tipoCambio.value > 0) {
        return (props.gasto.monto_total * tipoCambio.value).toFixed(2);
    }
    return '0.00';
});

// NUEVO: Deshabilita el botón de aprobar si es USD y no hay tipo de cambio
const isAprobarDisabled = computed(() => {
    if (isLoading.value) return true;
    if (props.gasto?.moneda === 'USD' && (!tipoCambio.value || tipoCambio.value <= 0)) {
        return true;
    }
    return false;
});


// --- MÉTODOS DE ACCIÓN ---

/**
 * Llama al endpoint específico para APROBAR el gasto.
 */
const aprobarGasto = async () => {
    let confirmText = `El gasto de ${props.gasto.moneda === 'PEN' ? 'S/.' : 'USD'} ${parseFloat(props.gasto.monto_total).toFixed(2)} será aprobado.`;
    if (props.gasto.moneda === 'USD') {
        confirmText += ` Con un tipo de cambio de ${tipoCambio.value}, se descontarán S/. ${montoConvertido.value} del fondo.`
    }

    const result = await Swal.fire({
        title: '¿Aprobar Gasto?',
        text: confirmText,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, Aprobar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#76C49D',
    });

    if (result.isConfirmed) {
        isLoading.value = true;
        try {
            // Construir el payload que se enviará a la API
            const payload = {
                comentario: 'Gasto aprobado por Jefe de Área.'
            };
            if (props.gasto.moneda === 'USD') {
                payload.tipo_cambio = tipoCambio.value;
            }

            // Llamada al endpoint con el payload
            await api.post(`/v1/gastos/${props.gasto.id}/approve`, payload);

            await Swal.fire('¡Éxito!', 'Gasto aprobado correctamente.', 'success');
            emit('accionRealizada');
        } catch (error) {
            console.error("Error al aprobar el gasto:", error);
            const errorMessage = error.response?.data?.errors?.monto_total || error.response?.data?.message || 'No se pudo completar la acción.';
            Swal.fire('Error', errorMessage, 'error');
        } finally {
            isLoading.value = false;
        }
    }
};

/**
 * Llama al endpoint específico para RECHAZAR el gasto.
 */
const confirmarRechazo = async () => {
    if (!motivoRechazo.value.trim()) {
        Swal.fire('Atención', 'Debes ingresar un motivo para el rechazo.', 'warning');
        return;
    }

    const result = await Swal.fire({
        title: '¿Rechazar este Gasto?',
        text: "Esta acción marcará el gasto como 'Rechazado' y no podrá ser procesado.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, Rechazar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#DB3D47',
    });

    if (result.isConfirmed) {
        isLoading.value = true;
        try {
            await api.post(`/v1/gastos/${props.gasto.id}/reject-by-jefe`, {
                comentario: motivoRechazo.value
            });

            await Swal.fire('Rechazado', 'El gasto ha sido rechazado.', 'success');
            emit('accionRealizada');
        } catch (error) {
            console.error("Error al rechazar el gasto:", error);
            Swal.fire('Error', error.response?.data?.message || 'No se pudo completar la acción.', 'error');
        } finally {
            isLoading.value = false;
        }
    }
};


// --- MÉTODOS DEL MODAL ---
const iniciarAccionRechazo = () => {
    accionActual.value = 'rechazar';
};

const cancelarAccion = () => {
    accionActual.value = null;
    motivoRechazo.value = '';
};

const cerrarModal = () => {
    emit('close');
};

// Limpia el estado del modal cuando se cierra.
watch(() => props.mostrar, (newVal) => {
    if (!newVal) {
        cancelarAccion();
        tipoCambio.value = null; // Limpiar tipo de cambio al cerrar
    }
});
</script>

<template>
    <Transition name="modal-fade">
        <div v-if="mostrar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm">
            <div v-if="gasto"
                class="bg-white rounded-xl shadow-xl w-full max-w-lg mx-auto overflow-hidden transform transition-all">
                <!-- Encabezado -->
                <div class="flex justify-between items-center p-4 border-b border-gray-200">
                    <h3 class="text-xl font-semibold text-gray-800">Gestionar Gasto: {{ gasto.codigo_gasto }}</h3>
                    <button @click="cerrarModal"
                        class="text-gray-400 hover:text-gray-600 p-2 rounded-full transition-colors">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Cuerpo del Modal -->
                <div class="p-6 space-y-6">
                    <!-- Sección de Resumen del Gasto -->
                    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <h4 class="font-bold text-gray-700 mb-3">Resumen del Gasto</h4>
                        <div class="grid grid-cols-3 gap-x-4 gap-y-2 text-sm">
                            <span class="text-gray-500 font-medium col-span-1">Registrado por:</span>
                            <span class="font-medium text-gray-800 col-span-2">{{ gasto.registrador?.name }} {{
                                gasto.registrador?.last_name }}</span>

                            <span class="text-gray-500 font-medium col-span-1">Monto:</span>
                            <span class="font-bold text-verde-bap col-span-2">
                                {{ gasto.moneda === 'PEN' ? 'S/.' : 'USD' }} {{ parseFloat(gasto.monto_total).toFixed(2)
                                }}
                            </span>
                            
                            <span class="text-gray-500 font-medium col-span-1">Estado Actual:</span>
                            <span class="font-semibold col-span-2" :class="getTextClassForState(gasto.estado)">
                                {{ gasto.estado }}
                            </span>

                            <span class="text-gray-500 font-medium col-span-1">Fondo Afectado:</span>
                            <span class="font-medium text-gray-800 col-span-2">{{ gasto.fondo_efectivo?.codigo_fondo ||
                                'N/A' }}</span>

                            <span class="text-gray-500 font-medium col-span-1">Tipo Documento:</span>
                            <span class="font-medium text-gray-800 col-span-2">{{ gasto.tipo_documento }}</span>

                            <span class="text-gray-500 font-medium col-span-1">Glosa:</span>
                            <p class="font-medium text-gray-800 col-span-2">{{ gasto.glosa }}</p>
                        </div>
                    </div>

                    <!-- NUEVO: Sección de Tipo de Cambio (solo para USD) -->
                    <div v-if="gasto.moneda === 'USD'"
                        class="p-4 bg-blue-50 border border-blue-200 rounded-lg animate-fade-in">
                        <h4 class="text-lg font-bold text-gray-700 mb-4">Conversión de Moneda</h4>
                        <div class="space-y-4">
                            <div>
                                <label for="tipo_cambio" class="block text-sm font-medium text-gray-700 mb-1">
                                    Tipo de Cambio <span class="text-rojo-bap">*</span>
                                </label>
                                <input type="number" id="tipo_cambio" v-model.number="tipoCambio" step="0.0001"
                                    min="0.0001"
                                    class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                                    placeholder="Ej: 3.75" />
                                <p class="text-xs text-gray-500 mt-1">Ingresa el tipo de cambio del día para la
                                    aprobación.</p>
                            </div>
                            <div>
                                <label for="monto_convertido" class="block text-sm font-medium text-gray-700 mb-1">Monto
                                    Convertido (S/.)</label>
                                <input type="text" id="monto_convertido" :value="montoConvertido"
                                    class="w-full p-2 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                                    disabled />
                            </div>
                        </div>
                    </div>

                    <!-- Sección de Acciones de Jefatura -->
                    <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                        <h4 class="text-lg font-bold text-gray-700 mb-4">Acciones de Jefatura</h4>

                        <div v-if="esGastoPropio"
                            class="text-center p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 rounded-r-md">
                            <p class="font-semibold">Acción no permitida</p>
                            <p class="text-sm">No puedes aprobar o rechazar tus propios gastos.</p>
                        </div>

                        <div v-else>
                            <div v-if="!accionActual" class="flex flex-wrap gap-3">
                                <button @click="aprobarGasto" :disabled="isAprobarDisabled"
                                    :class="getClassesForActionButton('exito')">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Aprobar Gasto</span>
                                </button>
                                <button @click="iniciarAccionRechazo" :disabled="isLoading"
                                    :class="getClassesForActionButton('error')">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Rechazar</span>
                                </button>
                            </div>
                            <div v-if="accionActual === 'rechazar'" class="animate-fade-in">
                                <label for="motivoRechazo" class="block text-sm font-medium text-gray-700 mb-2">Motivo
                                    del Rechazo (requerido):</label>
                                <textarea id="motivoRechazo" v-model="motivoRechazo" rows="3"
                                    class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-rojo-bap focus:ring-rojo-bap resize-none"></textarea>
                                <div class="mt-4 flex justify-end space-x-3">
                                    <button @click="cancelarAccion"
                                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Cancelar</button>
                                    <button @click="confirmarRechazo" :disabled="isLoading || !motivoRechazo"
                                        :class="getClassesForActionButton('error')">
                                        <span v-if="isLoading">Enviando...</span>
                                        <span v-else>Confirmar Rechazo</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}

.animate-fade-in {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
