<script setup>
import { ref, watch, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { cloneDeep } from 'lodash-es';

// --- PROPS Y EMITS ---
// El componente recibe el gasto a corregir y una bandera para mostrarse.
const props = defineProps({
    gastoACorregir: {
        type: Object,
        required: true
    },
    mostrar: {
        type: Boolean,
        default: false
    }
});

// Emite eventos para notificar al padre.
const emit = defineEmits(['close', 'gastoCorregido']);

// --- ESTADO REACTIVO ---
// 'gastoEditable' es una copia local y reactiva del gasto para poder editarla en el formulario.
const gastoEditable = ref({});
// 'nuevaEvidenciaFile' almacenará el nuevo archivo de evidencia si el usuario selecciona uno.
const nuevaEvidenciaFile = ref(null);
// 'enviando' controla el estado de carga del botón de envío.
const enviando = ref(false);
const confirmacionDJ = ref(false);
// --- LÓGICA DEL FORMULARIO ---

// Esta función se activa cada vez que la prop 'gastoACorregir' cambia.
// Clona el objeto para evitar mutaciones directas y pre-llena el formulario.
watch(() => props.gastoACorregir, (newGasto) => {
    if (newGasto) {
        gastoEditable.value = cloneDeep(newGasto);
        // Añadimos el campo para el comentario de subsanación, igual que en tu AperturaFondos.vue
        gastoEditable.value.comentario_subsanacion = '';
        nuevaEvidenciaFile.value = null; // Reseteamos el archivo
    }
}, { immediate: true, deep: true });

const djConsolidadaUrl = computed(() => {
    if (!props.gastoACorregir?.dj_consolidada?.ruta_documento) return '#';
    return `/storage/${props.gastoACorregir.dj_consolidada.ruta_documento}`;
});

const isGuardarDisabled = computed(() => {
    // Deshabilitado si se está enviando
    if (enviando.value) return true;
    // Si es un gasto de DJ consolidada, también se deshabilita si no se ha marcado la confirmación
    if (props.gastoACorregir?.dj_consolidada) {
        return !confirmacionDJ.value;
    }
    // En otros casos, el botón está habilitado
    return false;
});
// Maneja la selección de un nuevo archivo de evidencia.
const handleFileChange = (event) => {
    nuevaEvidenciaFile.value = event.target.files[0];
};

// --- LÓGICA DE ENVÍO ---
const enviarCorreccion = async () => {
    // Validación simple de frontend
    if (!gastoEditable.value.monto_total || !gastoEditable.value.glosa) {
        Swal.fire('Datos Incompletos', 'El monto y la glosa son obligatorios.', 'warning');
        return;
    }

    enviando.value = true;

    // Se usa FormData porque podríamos estar enviando un archivo.
    const formDataPayload = new FormData();

    // Adjuntamos todos los campos editables del gasto.
    Object.keys(gastoEditable.value).forEach(key => {
        if (gastoEditable.value[key] !== null && gastoEditable.value[key] !== '') {
            const value = typeof gastoEditable.value[key] === 'boolean' ? (gastoEditable.value[key] ? 1 : 0) : gastoEditable.value[key];
            formDataPayload.append(key, value);
        }
    });

    // Si el usuario seleccionó un nuevo archivo, lo adjuntamos.
    if (nuevaEvidenciaFile.value) {
        formDataPayload.append('evidencia', nuevaEvidenciaFile.value);
    }

    try {
        // Hacemos la llamada al endpoint que definimos en las rutas.
        const response = await api.post(`/v1/gastos/${props.gastoACorregir.id}/actualizar-observado`, formDataPayload, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        Swal.fire({
            icon: 'success',
            title: '¡Gasto Corregido!',
            text: response.data.message || 'El gasto ha sido actualizado y reenviado para su aprobación.',
        });

        // Notificamos al componente padre que el gasto fue corregido y cerramos el modal.
        emit('gastoCorregido', response.data.gasto);
        emit('close');

    } catch (error) {
        console.error("Error al corregir el gasto:", error);
        const errorMessage = error.response?.data?.message || 'Ocurrió un error inesperado.';
        Swal.fire({
            icon: 'error',
            title: 'Error al Corregir',
            text: errorMessage,
        });
    } finally {
        enviando.value = false;
    }
};
</script>

<template>
    <Transition name="modal-backdrop">
        <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-60">
            <Transition name="modal-content" appear>
                <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl transform transition-all">
                    <div class="bg-gray-100 p-4 rounded-t-lg">
                        <h3 class="text-xl font-semibold text-gray-800">
                            Corregir Gasto Observado: {{ gastoACorregir?.codigo_gasto }}
                        </h3>
                    </div>

                    <form @submit.prevent="enviarCorreccion" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">

                        <div class="p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded-r-lg">
                            <p class="font-bold text-sm">Motivo de la Observación:</p>
                            <p class="text-sm">{{ gastoACorregir.motivo_observacion_adm || 'No se especificó un motivo.'
                            }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Gasto Proyectado</label>
                            <input type="text" :value="gastoACorregir?.gasto_proyectado?.descripcion || 'N/A'"
                                class="mt-1 block w-full p-2 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                                disabled />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Monto del Documento (S/.)</label>
                                <input type="number" v-model.number="gastoEditable.monto_total"
                                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md" step="0.01"
                                    required />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fecha del Documento</label>
                                <input type="date" v-model="gastoEditable.fecha_documento"
                                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Glosa / Descripción del Gasto</label>
                            <input type="text" v-model="gastoEditable.glosa"
                                class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required />
                        </div>

                        <div>
                            <div v-if="gastoACorregir.dj_consolidada">
                                <label class="block text-sm font-medium text-gray-700">Evidencia</label>
                                <div class="mt-1 p-3 bg-blue-50 border-l-4 border-blue-400 text-blue-800 rounded-r-lg">
                                    <p class="font-bold text-sm">Sustento Consolidado</p>
                                    <p class="text-xs mt-1">Este gasto se sustenta con una Declaración Jurada
                                        Consolidada.</p>
                                    <a :href="djConsolidadaUrl" target="_blank"
                                        class="text-xs font-semibold text-blue-600 hover:underline mt-2 inline-block">Ver
                                        Documento Consolidado Actual</a>
                                </div>

                                <div class="mt-4 p-3 bg-red-50 border-l-4 border-red-400 text-red-800 rounded-r-lg">
                                    <p class="font-bold text-sm">Acción Requerida</p>
                                    <p class="text-xs mt-1">Al modificar este gasto, la DJ consolidada actual quedará
                                        invalidada. Deberás generar y subir una nueva en la pantalla de Declaración de
                                        Gastos.</p>
                                    <div class="mt-2 flex items-center">
                                        <input type="checkbox" id="confirmacionDJ" v-model="confirmacionDJ"
                                            class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                                        <label for="confirmacionDJ" class="ml-2 block text-xs text-gray-900">Entiendo
                                            que deberé regenerar la DJ consolidada.</label>
                                    </div>
                                </div>
                            </div>

                            <div v-else>
                                <label class="block text-sm font-medium text-gray-700">Cambiar Archivo de Evidencia
                                    (Opcional)</label>
                                <input type="file" @change="handleFileChange"
                                    class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-verde-bap-extralight file:text-verde-bap-dark hover:file:bg-verde-bap/20" />
                                <p v-if="nuevaEvidenciaFile" class="text-xs text-green-600 mt-1">Nuevo archivo: {{
                                    nuevaEvidenciaFile.name }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Comentario de Subsanación
                                (Opcional)</label>
                            <p class="text-xs text-gray-500 mb-1">Añade un comentario para explicar los cambios
                                realizados.</p>
                            <textarea v-model="gastoEditable.comentario_subsanacion" rows="3"
                                class="mt-1 block w-full p-2 border border-gray-300 rounded-md"></textarea>
                        </div>
                    </form>

                    <div class="bg-gray-50 px-6 py-3 flex justify-end space-x-3 rounded-b-lg">
                        <button type="button" @click="emit('close')"
                            class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400">Cancelar</button>
                        <button type="button" @click="enviarCorreccion" :disabled="isGuardarDisabled"
                            class="px-4 py-2 bg-verde-bap text-white rounded-md hover:bg-verde-bap-hover disabled:opacity-50 disabled:cursor-not-allowed">
                            <span v-if="enviando">Enviando...</span>
                            <span v-else>Guardar Cambios y Reenviar</span>
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
.modal-backdrop-enter-active,
.modal-backdrop-leave-active {
    transition: opacity 0.3s ease;
}

.modal-backdrop-enter-from,
.modal-backdrop-leave-to {
    opacity: 0;
}

.modal-content-enter-active,
.modal-content-leave-active {
    transition: all 0.3s ease;
}

.modal-content-enter-from,
.modal-content-leave-to {
    opacity: 0;
    transform: translateY(-20px);
}
</style>
