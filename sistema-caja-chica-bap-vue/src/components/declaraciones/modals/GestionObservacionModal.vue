<script setup>
import { ref, watch, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { getTextClassForState, getClassesForActionButton } from '@/utils/statusStyles.js';

// --- PROPS Y EMITS ---
const props = defineProps({
    mostrar: Boolean,
    gasto: Object,
    usuarioActual: Object
});
const emit = defineEmits(['close', 'accionRealizada']);

// --- ESTADO INTERNO ---
const isLoading = ref(false);
const comentario = ref('');

// --- PROPIEDADES COMPUTADAS ---
const esJefeDeArea = computed(() => props.usuarioActual?.role?.name === 'jefe_area');
const esColaborador = computed(() => props.usuarioActual?.role?.name === 'colaborador');

const modalTitle = computed(() => {
    if (esJefeDeArea.value) return "Enviar Directriz al Colaborador";
    if (esColaborador.value) return "Presentar Corrección / Descargo";
    return "Gestionar Observación";
});

const labelText = computed(() => {
    if (esJefeDeArea.value) return "Directriz para la corrección:";
    if (esColaborador.value) return "Detalle de la corrección o descargo:";
    return "Comentario:";
});

const buttonText = computed(() => {
    if (esJefeDeArea.value) return "Enviar Directriz";
    if (esColaborador.value) return "Reenviar para Aprobación";
    return "Confirmar";
});


// --- MÉTODOS DE ACCIÓN ---
const handleConfirmarAccion = async () => {
    if (!comentario.value.trim()) {
        Swal.fire('Atención', 'El campo de comentario no puede estar vacío.', 'warning');
        return;
    }

    let endpoint = '';
    let method = 'post';
    let payload = { comentario: comentario.value };
    let confirmationTitle = '';
    let successMessage = '';
    const endpointPrefix = '/v1';
    if (esJefeDeArea.value) {
        endpoint = `${endpointPrefix}/gastos/${props.gasto.id}/return-to-collaborator`;
        confirmationTitle = '¿Enviar Directriz?';
        successMessage = 'La directriz ha sido enviada al colaborador.';
        method = 'post';
    } else if (esColaborador.value) {
        endpoint = `${endpointPrefix}/gastos/${props.gasto.id}/resubmit`;
        confirmationTitle = '¿Reenviar Gasto?';
        successMessage = 'El gasto ha sido corregido y reenviado para su aprobación.';
        method = 'put';
    } else {
        return; // No hacer nada si el rol no es el esperado
    }

    const result = await Swal.fire({
        title: confirmationTitle,
        html: `Se enviará la siguiente nota:<br><strong>"${comentario.value}"</strong>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, enviar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        isLoading.value = true;
        try {
            await api[method](endpoint, payload);
            await Swal.fire('¡Éxito!', successMessage, 'success');
            emit('accionRealizada');
        } catch (error) {
            console.error("Error al gestionar la observación:", error);
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
        isLoading.value = false;
        comentario.value = '';
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
                    <h3 class="text-xl font-semibold text-gray-800">{{ modalTitle }}: {{ gasto.codigo_gasto }}</h3>
                    <button @click="cerrarModal" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Cuerpo del Modal -->
                <div class="p-6 max-h-[70vh] overflow-y-auto">
                    <!-- Resumen del Gasto Observado -->
                    <div class="mb-6 p-4 border border-orange-300 rounded-md bg-orange-50">
                        <h4 class="text-lg font-bold text-gray-700 mb-2">Gasto Observado</h4>
                        <div class="space-y-2 text-sm">
                            <div>
                                <span class="text-gray-500 font-medium">Motivo de Observación (ADM):</span>
                                <p class="font-medium text-gray-800 italic">"{{ gasto.motivo_observacion_adm }}"</p>
                            </div>
                            <div class="pt-2">
                                <span class="text-gray-500 font-medium">Estado Actual:</span>
                                <span class="font-semibold ml-2" :class="getTextClassForState(gasto.estado)">{{
                                    gasto.estado }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Campo para la acción del usuario -->
                    <div class="p-4 border border-gray-200 rounded-md bg-white">
                        <label for="comentario_accion" class="block text-sm font-medium text-gray-700 mb-2">{{ labelText
                            }}</label>
                        <textarea id="comentario_accion" v-model="comentario"
                            :placeholder="'Escribe tu ' + (esJefeDeArea ? 'directriz' : 'descargo') + ' aquí...'"
                            rows="4"
                            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap resize-none"></textarea>
                    </div>
                </div>

                <!-- Pie de página -->
                <div class="p-4 border-t border-gray-200 flex justify-end bg-gray-50 space-x-3">
                    <button @click="cerrarModal"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition-colors">Cancelar</button>
                    <button @click="handleConfirmarAccion" :disabled="isLoading || !comentario"
                        :class="getClassesForActionButton('info')">
                        <span v-if="isLoading">Enviando...</span>
                        <span v-else>{{ buttonText }}</span>
                    </button>
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
</style>
