<script setup>
import { ref, watch, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

// --- PROPS Y EMITS ---
// Se define un emit 'accionRealizada' para notificar al componente padre que debe recargar los datos.
const props = defineProps({
    mostrar: Boolean,
    gasto: Object,
    usuarioActual: Object, 
});
const emit = defineEmits(['close', 'accionRealizada']);


// --- ESTADO INTERNO ---
const isLoading = ref(false);
const accionActual = ref(null); // Usado para mostrar/ocultar el textarea de rechazo.
const motivoRechazo = ref('');


// --- COMPUTED PROPS ---
// La lógica para evitar la autoaprobación se mantiene, es una regla de negocio correcta.
const esGastoPropio = computed(() => {
    if (!props.gasto || !props.usuarioActual) return false;
    return props.gasto.id_registrador === props.usuarioActual.id;
});


// --- MÉTODOS DE ACCIÓN (REFACTORIZADOS) ---
// Se elimina la función genérica 'ejecutarAccionAPI' y se crean métodos específicos
// para cada acción, apuntando a los nuevos endpoints RESTful del backend.

/**
 * Llama al endpoint específico para APROBAR el gasto.
 * Corresponde al Paso 2 del flujo.
 */
const aprobarGasto = async () => {
    const result = await Swal.fire({
        title: '¿Aprobar Gasto?',
        text: `El gasto de S/. ${parseFloat(props.gasto.monto_total).toFixed(2)} será aprobado y descontado del fondo.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, Aprobar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#76C49D', // Verde BAP
    });

    if (result.isConfirmed) {
        isLoading.value = true;
        try {
            // Llamada directa al nuevo endpoint POST para aprobar.
            await api.post(`/gastos/${props.gasto.id}/approve`);
            
            await Swal.fire('¡Éxito!', 'Gasto aprobado correctamente.', 'success');
            emit('accionRealizada'); // Notifica al padre para refrescar y cerrar el modal.
        } catch (error) {
            console.error("Error al aprobar el gasto:", error);
            Swal.fire('Error', error.response?.data?.message || 'No se pudo completar la acción.', 'error');
        } finally {
            isLoading.value = false;
        }
    }
};

/**
 * Llama al endpoint específico para RECHAZAR el gasto.
 * Corresponde a la alternativa del Paso 2.
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
        confirmButtonColor: '#DB3D47', // Rojo BAP
    });

    if (result.isConfirmed) {
        isLoading.value = true;
        try {
            // Llamada directa al nuevo endpoint POST para rechazar, enviando el motivo.
            await api.post(`/gastos/${props.gasto.id}/reject-by-jefe`, {
                comentario: motivoRechazo.value
            });
            
            await Swal.fire('Rechazado', 'El gasto ha sido rechazado.', 'success');
            emit('accionRealizada'); // Notifica al padre para refrescar y cerrar.
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

// Se simplifica la función de cierre. El padre se encarga de la lógica de refresco.
const cerrarModal = () => {
    emit('close');
};

// Limpia el estado del modal cuando se cierra.
watch(() => props.mostrar, (newVal) => {
    if (!newVal) {
        cancelarAccion();
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
                            <span class="font-bold text-verde-bap col-span-2">S/. {{
                                parseFloat(gasto.monto_total).toFixed(2) }}</span>

                            <span class="text-gray-500 font-medium col-span-1">Fondo Afectado:</span>
                            <span class="font-medium text-gray-800 col-span-2">{{ gasto.fondo_efectivo?.codigo_fondo ||
                                'N/A' }}</span>

                            <span class="text-gray-500 font-medium col-span-1">Tipo Documento:</span>
                            <span class="font-medium text-gray-800 col-span-2">{{ gasto.tipo_documento }}</span>

                            <span class="text-gray-500 font-medium col-span-1">Glosa:</span>
                            <p class="font-medium text-gray-800 col-span-2">{{ gasto.glosa }}</p>
                        </div>
                    </div>

                    <!-- Sección de Acciones de Jefatura -->
                    <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                        <h4 class="text-lg font-bold text-gray-700 mb-4">Acciones de Jefatura</h4>

                        <!-- Mensaje si el gasto es propio -->
                        <div v-if="esGastoPropio"
                            class="text-center p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 rounded-r-md">
                            <p class="font-semibold">Acción no permitida</p>
                            <p class="text-sm">No puedes aprobar o rechazar tus propios gastos registrados.</p>
                        </div>
                        
                        <!-- Acciones si el gasto NO es propio -->
                        <div v-else>
                            <!-- Botones principales: Aprobar y Rechazar -->
                            <div v-if="!accionActual" class="flex flex-wrap gap-3">
                                <button @click="aprobarGasto" :disabled="isLoading"
                                    class="px-4 py-2 bg-verde-bap text-white rounded-md hover:bg-verde-bap-dark transition-colors flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span>Aprobar Gasto</span>
                                </button>
                                <button @click="iniciarAccionRechazo" :disabled="isLoading"
                                    class="px-4 py-2 bg-rojo-bap text-white rounded-md hover:bg-rojo-bap-dark transition-colors flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>Rechazar</span>
                                </button>
                            </div>

                            <!-- Formulario para ingresar el motivo del rechazo -->
                            <div v-if="accionActual === 'rechazar'" class="animate-fade-in">
                                <label for="motivoRechazo" class="block text-sm font-medium text-gray-700 mb-2">Motivo
                                    del Rechazo (requerido):</label>
                                <textarea id="motivoRechazo" v-model="motivoRechazo" rows="3"
                                    class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-rojo-bap focus:ring-rojo-bap resize-none"></textarea>
                                <div class="mt-4 flex justify-end space-x-3">
                                    <button @click="cancelarAccion"
                                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Cancelar</button>
                                    <button @click="confirmarRechazo"
                                        class="px-4 py-2 bg-rojo-bap text-white rounded-md hover:bg-rojo-bap-dark"
                                        :disabled="isLoading || !motivoRechazo">
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
