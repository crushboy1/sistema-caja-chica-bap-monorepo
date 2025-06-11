<script setup>
import { ref, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

// --- PROPS Y EMITS ---
const props = defineProps({
    mostrar: Boolean,
    gasto: Object,
    usuarioActual: Object
});
const emit = defineEmits(['close', 'accionRealizada']);

// --- ESTADO INTERNO ---
const accionEnCurso = ref(false);
const motivo = ref('');

// --- MÉTODOS ---
const cerrarModal = () => {
    if (accionEnCurso.value) return;
    emit('close');
};

// Limpia el estado del modal cada vez que se abre
watch(() => props.mostrar, (newVal) => {
    if (newVal) {
        accionEnCurso.value = false;
        motivo.value = '';
    }
});

/**
 * Función genérica para ejecutar una acción en el backend.
 * @param {string} accion - El nombre de la acción para la API (ej: 'observar_adm').
 * @param {object} swalOptions - La configuración para el SweetAlert de confirmación.
 */
const ejecutarAccion = async (accion, swalOptions) => {
    // Para observar o rechazar, el motivo es obligatorio.
    if ((accion === 'observar_adm' || accion === 'rechazar_final') && !motivo.value.trim()) {
        Swal.fire('Atención', 'Es necesario ingresar un motivo para observar o rechazar el gasto.', 'warning');
        return;
    }

    const result = await Swal.fire(swalOptions);

    if (result.isConfirmed) {
        accionEnCurso.value = true;
        try {
            const payload = {
                accion: accion,
                comentario: motivo.value,
            };
            // Llamada a la API para actualizar el gasto
            await api.put(`/gastos/${props.gasto.id}/actualizar-estado`, payload);

            Swal.fire({
                icon: 'success',
                title: '¡Acción Realizada!',
                text: 'El estado del gasto ha sido actualizado correctamente.',
                timer: 2000,
                showConfirmButton: false,
            });

            emit('accionRealizada');
            cerrarModal();
        } catch (error) {
            console.error(`Error al ejecutar la acción '${accion}':`, error);
            Swal.fire({
                icon: 'error',
                title: 'Error Inesperado',
                text: error.response?.data?.message || 'No se pudo completar la acción.',
            });
        } finally {
            accionEnCurso.value = false;
        }
    }
};

const handleObservar = () => {
    ejecutarAccion('observar_adm', {
        title: '¿Observar Gasto?',
        text: "El gasto será devuelto a la jefatura con tus observaciones. ¿Continuar?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f8bb86',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, observar',
        cancelButtonText: 'Cancelar',
    });
};

const handleRechazar = () => {
    ejecutarAccion('rechazar_final', {
        title: '¿Rechazar Gasto Definitivamente?',
        text: "Esta acción no se puede deshacer y marcará el gasto como rechazado.",
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Sí, rechazar',
        cancelButtonText: 'Cancelar',
    });
};

const handleContabilizar = () => {
    ejecutarAccion('contabilizar', {
        title: '¿Validar y Contabilizar Gasto?',
        text: "El gasto se marcará como 'Contabilizado' y se cerrará el ciclo para este item.",
        icon: 'success',
        showCancelButton: true,
        confirmButtonColor: '#76C49D',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Sí, validar',
        cancelButtonText: 'Cancelar',
    });
};

</script>

<template>
    <Transition name="modal-backdrop">
        <div v-if="mostrar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-backdrop-dark backdrop-blur-sm">
            <Transition name="modal-content" appear>
                <div v-if="gasto"
                    class="glass-modal rounded-3xl shadow-modal w-full max-w-lg mx-auto overflow-hidden transform animate-modal-scale border border-white/20">

                    <!-- Encabezado -->
                    <div class="relative bg-gradient-to-r from-rojo-bap to-rojo-bap-dark p-6 text-white">
                        <div class="absolute inset-0 bg-gradient-to-r from-rojo-bap/90 to-rojo-bap-dark/90"></div>
                        <div class="relative flex justify-between items-center">
                            <h3 class="text-2xl font-bold text-white drop-shadow-lg">
                                Auditoría de Gasto: {{ gasto.codigo_gasto }}
                            </h3>
                            <button @click="cerrarModal" :disabled="accionEnCurso"
                                class="text-white/80 hover:text-white hover:bg-white/20 p-2 rounded-xl transition-all duration-300 hover:scale-110">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Cuerpo del Modal -->
                    <div
                        class="p-6 max-h-[70vh] overflow-y-auto scroll-modal bg-gradient-to-br from-white/95 to-red-50/50">
                        <!-- Resumen del Gasto -->
                        <div
                            class="mb-6 p-4 border border-gray-200 rounded-md bg-white/80 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2">Resumen del Gasto</h4>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                <span class="font-medium text-gray-500">Registrador:</span>
                                <span class="text-gray-800">{{ gasto.registrador?.name }} {{
                                    gasto.registrador?.last_name }}</span>

                                <span class="font-medium text-gray-500">Monto:</span>
                                <span class="font-bold text-verde-bap">S/. {{ parseFloat(gasto.monto_total).toFixed(2)
                                    }}</span>

                                <span class="font-medium text-gray-500">Estado Actual:</span>
                                <span class="font-semibold" :class="getEstadoClass(gasto.estado)">{{ gasto.estado
                                    }}</span>

                                <span class="font-medium text-gray-500 col-span-2">Glosa:</span>
                                <p class="text-gray-800 col-span-2">{{ gasto.glosa }}</p>
                            </div>
                        </div>

                        <!-- Acciones de Administración -->
                        <div class="p-4 border border-gray-200 rounded-md bg-white/80 backdrop-blur-sm shadow-inner">
                            <h4 class="text-lg font-bold text-gray-700 mb-2">Acciones de Administración</h4>
                            <div>
                                <label for="motivoObservacion" class="block text-sm font-medium text-gray-700 mb-1">
                                    Motivo (Requerido para Observar o Rechazar):
                                </label>
                                <textarea id="motivoObservacion" v-model="motivo" rows="3"
                                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap resize-y"
                                    placeholder="Ej: El comprobante no es legible..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Pie de página con botones de acción -->
                    <div class="bg-gradient-to-r from-gray-50 to-red-50/30 px-6 py-4 border-t border-gray-200/50">
                        <div class="flex justify-end items-center space-x-3">
                            <button @click="handleObservar" :disabled="accionEnCurso"
                                class="px-4 py-2 bg-amarillo-bap text-amarillo-bap-dark font-semibold rounded-lg shadow-md hover:bg-amarillo-bap-dark hover:text-white transition-colors flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                                    </path>
                                </svg>
                                Observar
                            </button>
                            <button @click="handleRechazar" :disabled="accionEnCurso"
                                class="px-4 py-2 bg-rojo-bap text-white font-semibold rounded-lg shadow-md hover:bg-rojo-bap-dark transition-colors flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Rechazar
                            </button>
                            <button @click="handleContabilizar" :disabled="accionEnCurso"
                                class="px-4 py-2 bg-verde-bap text-white font-semibold rounded-lg shadow-md hover:bg-verde-bap-dark transition-colors flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Validar y Contabilizar
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
/* Transiciones y estilos de scroll replicados para consistencia */
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
}
</style>
