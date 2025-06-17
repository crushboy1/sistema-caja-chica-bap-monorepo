<script setup>
import { ref, watch, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { getTextClassForState , getClassesForActionButton } from '@/utils/statusStyles.js';

// --- PROPS Y EMITS ---
const props = defineProps({
    mostrar: Boolean,
    gasto: Object,
    usuarioActual: Object
});
const emit = defineEmits(['close', 'accionRealizada']);


// --- ESTADO INTERNO ---
const isLoading = ref(false);
const motivo = ref('');
// Nuevo estado para controlar qué acción se está realizando ('observar' o 'rechazar')
const accionActual = ref(null);
const motivoLabel = computed(() => accionActual.value === 'observar' ? 'Motivo de la Observación' : 'Motivo del Rechazo');
const motivoPlaceholder = computed(() => `Escribe el motivo de${accionActual.value === 'observar' ? ' la observación' : 'l rechazo'} aquí...`);


// --- MÉTODOS DE ACCIÓN (REFACTORIZADOS) ---

const iniciarAccionConMotivo = (accion) => {
    accionActual.value = accion;
};

const cancelarAccion = () => {
    accionActual.value = null;
    motivo.value = '';
};

/**
 * Ejecuta la acción final (Observar o Rechazar) después de que se ha ingresado un motivo.
 */
const confirmarAccionConMotivo = async () => {
    if (!motivo.value.trim()) {
        Swal.fire('Atención', 'Es necesario ingresar un motivo para esta acción.', 'warning');
        return;
    }

    let endpoint = '';
    let swalOptions = {};
    let successText = '';

    if (accionActual.value === 'observar') {
        endpoint = `/gastos/${props.gasto.id}/observe`;
        successText = 'El gasto ha sido observado.';
        swalOptions = {
            title: '¿Confirmar Observación?',
            text: "El gasto será devuelto a la jefatura con tus observaciones.",
            icon: 'warning',
            confirmButtonColor: '#f8bb86',
        };
    } else if (accionActual.value === 'rechazar') {
        endpoint = `/gastos/${props.gasto.id}/reject-final`;
        successText = 'El gasto ha sido rechazado definitivamente.';
        swalOptions = {
            title: '¿Confirmar Rechazo Definitivo?',
            text: "Esta acción es irreversible y devolverá el monto al fondo.",
            icon: 'error',
            confirmButtonColor: '#d33',
        };
    } else {
        return;
    }

    const result = await Swal.fire({
        ...swalOptions,
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar',
        cancelButtonText: 'Cancelar',
    });

    if (result.isConfirmed) {
        isLoading.value = true;
        try {
            await api.post(endpoint, { comentario: motivo.value });
            await Swal.fire('¡Éxito!', successText, 'success');
            emit('accionRealizada');
        } catch (error) {
            console.error(`Error al ${accionActual.value} el gasto:`, error);
            Swal.fire('Error', error.response?.data?.message || 'No se pudo completar la acción.', 'error');
        } finally {
            isLoading.value = false;
        }
    }
};

/**
 * Llama al endpoint para VALIDAR y CONTABILIZAR un gasto.
 */
const handleContabilizar = async () => {
    const result = await Swal.fire({
        title: '¿Validar y Contabilizar Gasto?',
        text: "El gasto se marcará como 'Contabilizado' y se cerrará su ciclo.",
        icon: 'success',
        showCancelButton: true,
        confirmButtonColor: '#76C49D',
        confirmButtonText: 'Sí, validar',
        cancelButtonText: 'Cancelar',
    });

    if (result.isConfirmed) {
        isLoading.value = true;
        try {
            await api.post(`/gastos/${props.gasto.id}/finalize`);
            await Swal.fire('¡Éxito!', 'El gasto ha sido validado y contabilizado.', 'success');
            emit('accionRealizada');
        } catch (error) {
            console.error("Error al contabilizar el gasto:", error);
            Swal.fire('Error', error.response?.data?.message || 'No se pudo completar la acción.', 'error');
        } finally {
            isLoading.value = false;
        }
    }
};


// --- MÉTODOS DEL MODAL ---
const cerrarModal = () => {
    if (isLoading.value) return;
    emit('close');
};

watch(() => props.mostrar, (newVal) => {
    if (!newVal) {
        // Resetear estado al cerrar
        isLoading.value = false;
        motivo.value = '';
        accionActual.value = null;
    }
});
</script>

<template>
    <Transition name="modal-fade">
        <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50">
            <div v-if="gasto"
                class="bg-white rounded-lg shadow-xl w-full max-w-md mx-auto overflow-hidden transform transition-all sm:max-w-lg md:max-w-xl">

                <!-- Encabezado -->
                <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-xl font-semibold text-gray-800">Gestionar Auditoría: {{ gasto.codigo_gasto }}</h3>
                    <button @click="cerrarModal" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Cuerpo del Modal -->
                <div class="p-6 max-h-[70vh] overflow-y-auto">
                    <!-- Sección de Resumen del Gasto -->
                    <div class="mb-6 p-4 border border-gray-200 rounded-md bg-gray-100">
                        <h4 class="text-lg font-bold text-gray-700 mb-2">Resumen del Gasto</h4>
                        <div class="grid grid-cols-3 gap-x-4 gap-y-2 text-sm">
                            <span class="text-gray-500 font-medium col-span-1">Registrador:</span>
                            <span class="font-medium text-gray-800 col-span-2">{{ gasto.registrador?.name }} {{
                                gasto.registrador?.last_name }}</span>

                            <span class="text-gray-500 font-medium col-span-1">Monto:</span>
                            <span class="font-bold text-verde-bap col-span-2">S/. {{
                                parseFloat(gasto.monto_total).toFixed(2) }}</span>

                            <span class="text-gray-500 font-medium col-span-1">Estado Actual:</span>
                            <span class="font-semibold col-span-2" :class="getTextClassForState(gasto.estado)">
                                {{ gasto.estado }}
                            </span>

                            <span class="text-gray-500 font-medium col-span-1">Glosa:</span>
                            <p class="font-medium text-gray-800 col-span-2">{{ gasto.glosa }}</p>
                        </div>
                    </div>

                    <!-- Sección de Acciones de Administración -->
                    <div class="mb-6 p-4 border border-gray-200 rounded-md bg-gray-50">
                        <h4 class="text-lg font-bold text-gray-700 mb-4">Acciones de Administración</h4>
                        <div class="flex flex-wrap gap-3">
                            <button @click="handleContabilizar" :disabled="isLoading || accionActual"
                                :class="getClassesForActionButton('exito')">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                <span>Validar</span>
                            </button>
                            <button @click="iniciarAccionConMotivo('observar')" :disabled="isLoading || accionActual"
                                :class="getClassesForActionButton('advertencia')">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.306 17c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>Observar</span>
                            </button>
                            <button @click="iniciarAccionConMotivo('rechazar')" :disabled="isLoading || accionActual"
                                :class="getClassesForActionButton('error')">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <span>Rechazar</span>
                            </button>
                        </div>
                    </div>

                    <!-- Campo de Motivo Condicional -->
                    <div v-if="accionActual" class="mt-6 p-4 border border-gray-200 rounded-md bg-white shadow-inner">
                        <label for="motivo" class="block text-sm font-medium text-gray-700 mb-2">{{ motivoLabel
                        }}:</label>
                        <textarea id="motivo" v-model="motivo" :placeholder="motivoPlaceholder" rows="4"
                            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap resize-none"></textarea>
                        <div class="mt-4 flex justify-end space-x-3">
                            <button @click="cancelarAccion"
                                class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition-colors">Cancelar</button>
                            <button @click="confirmarAccionConMotivo" :disabled="isLoading || !motivo"
                                :class="getClassesForActionButton(accionActual === 'observar' ? 'advertencia' : 'error')">
                                <span v-if="isLoading">Enviando...</span>
                                <span v-else>Confirmar</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pie de página -->
                <div class="p-4 border-t border-gray-200 flex justify-end bg-gray-50">
                    <button @click="cerrarModal"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition-colors">Cerrar</button>
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
