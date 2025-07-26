<template>
    <Transition name="modal-backdrop">
        <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-60">
            <Transition name="modal-content" appear>
                <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl transform transition-all">
                    <div class="bg-gray-100 p-4 rounded-t-lg flex justify-between items-center">
                        <h3 class="text-xl font-semibold text-gray-800">
                            Corregir Gasto Observado: {{ gasto?.codigo_gasto }}
                        </h3>
                        <button @click="emit('close')" class="text-gray-500 hover:text-gray-700 transition-colors">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="enviarCorreccion" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">

                        <div class="p-3 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 rounded-r-lg">
                            <p class="font-bold text-sm">Motivo de la Observación:</p>
                            <p class="text-sm">{{ gasto.motivo_observacion_adm || 'No se especificó un motivo.'
                            }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Gasto Proyectado</label>
                            <input type="text" :value="gasto?.gasto_proyectado?.descripcion || 'N/A'"
                                class="mt-1 block w-full p-2 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                                disabled />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Monto del Documento (S/.) <span
                                        class="text-red-500">*</span></label>
                                <input type="number" v-model.number="gastoEditable.monto_total"
                                    :class="getMontoInputClasses" step="0.01" min="0.01"
                                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required />
                                <transition name="shake">
                                    <p v-if="montoExcedeSaldo" class="text-xs text-red-500 mt-1">
                                        {{ montoExcedeSaldo }}
                                    </p>
                                </transition>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Fecha del Documento <span
                                        class="text-red-500">*</span></label>
                                <input type="date" v-model="gastoEditable.fecha_documento"
                                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required />
                                <transition name="shake">
                                    <p v-if="fechaDocumentoInvalida" class="text-xs text-red-500 mt-1">
                                        {{ fechaDocumentoInvalida }}
                                    </p>
                                </transition>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Glosa / Descripción del Gasto <span
                                    class="text-red-500">*</span></label>
                            <input type="text" v-model="gastoEditable.glosa"
                                class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tipo de Documento <span
                                        class="text-red-500">*</span></label>
                                <select v-model="gastoEditable.tipo_documento" @change="onTipoDocumentoChange"
                                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md" required>
                                    <option value="">Seleccione un tipo</option>
                                    <option>Boleta de Venta</option>
                                    <option>Factura</option>
                                    <option>Declaración Jurada</option>
                                </select>
                            </div>
                            <template v-if="!gastoEditable.es_declaracion_jurada">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Serie Documento <span
                                            class="text-red-500">*</span></label>
                                    <input type="text" v-model="gastoEditable.serie_documento"
                                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md"
                                        :required="!gastoEditable.es_declaracion_jurada" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Correlativo Documento <span
                                            class="text-red-500">*</span></label>
                                    <input type="text" v-model="gastoEditable.correlativo_documento"
                                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md"
                                        :required="!gastoEditable.es_declaracion_jurada" />
                                </div>
                            </template>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Archivo de Evidencia <span
                                    class="text-red-500">*</span></label>
                            <p class="text-xs text-gray-500 mb-1">Adjunta el comprobante o sustento del gasto. Si la
                                observación fue por el
                                archivo, sube uno nuevo.</p>

                            <div v-if="gastoEditable.ruta_evidencia && !nuevaEvidenciaFile"
                                class="mb-2 p-2 border rounded-md bg-gray-50 flex items-center justify-between">
                                <span class="text-sm text-gray-700 truncate">Archivo actual: <a
                                        :href="`/storage/${gastoEditable.ruta_evidencia}`" target="_blank"
                                        class="text-blue-600 hover:underline">Ver Evidencia</a></span>
                                <button type="button"
                                    @click="gastoEditable.ruta_evidencia = null; nuevaEvidenciaFile = null;"
                                    class="text-red-500 hover:text-red-700 ml-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>

                            <input type="file" @change="handleFileChange"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-verde-bap-extralight file:text-verde-bap-dark hover:file:bg-verde-bap/20" />
                            <p v-if="nuevaEvidenciaFile" class="text-xs text-green-600 mt-1">Nuevo archivo seleccionado:
                                {{
                                    nuevaEvidenciaFile.name }}</p>
                            <p class="text-xs text-gray-500 mt-1">Formatos permitidos: PDF, JPG, PNG, DOC, DOCX (máx.
                                10MB)</p>
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

<script setup>
import { ref, watch, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { cloneDeep } from 'lodash-es';

// --- PROPS Y EMITS ---
const props = defineProps({
    gasto: {
        type: Object,
        required: true
    },
    mostrar: {
        type: Boolean,
        default: false
    },
    usuarioActual: { 
        type: Object,
        required: true
    }
});

const emit = defineEmits(['close', 'gastoActualizado']); // Cambiado a 'gastoActualizado' para mayor claridad

// --- ESTADO REACTIVO ---
const gastoEditable = ref({});
const nuevaEvidenciaFile = ref(null);
const enviando = ref(false);

// --- PROPIEDADES COMPUTADAS PARA VALIDACIÓN Y ESTADO ---
const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }); // Para formatear montos

// Valida si el monto excede el proyectado (si aplica)
const montoExcedeSaldo = computed(() => {
    if (!gastoEditable.value.monto_total || !gastoEditable.value.monto_proyectado_original) return '';
    const monto = parseFloat(gastoEditable.value.monto_total);
    const proyectado = parseFloat(gastoEditable.value.monto_proyectado_original);
    if (monto > proyectado) {
        return `Excede el monto proyectado en S/. ${currencyFormatter.format(monto - proyectado)}`;
    }
    return '';
});

// Clases dinámicas para el input de monto_total (borde y texto rojo si excede)
const getMontoInputClasses = computed(() => {
    return {
        'border-red-500 focus:border-red-500 ring-red-500 text-red-800': !!montoExcedeSaldo.value,
        'border-gray-300 focus:border-verde-bap focus:ring-verde-bap': !montoExcedeSaldo.value
    };
});

// Valida la fecha del documento
const fechaDocumentoInvalida = computed(() => {
    if (!gastoEditable.value.fecha_documento) return 'La fecha del documento es obligatoria.';
    const fechaDoc = new Date(gastoEditable.value.fecha_documento);
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0); // Comparar solo la fecha

    if (fechaDoc > hoy) {
        return 'La fecha del documento no puede ser futura.';
    }
    return '';
});

// Determina si el botón de guardar debe estar deshabilitado
const isGuardarDisabled = computed(() => {
    return enviando.value ||
        !gastoEditable.value.monto_total ||
        !gastoEditable.value.glosa ||
        !gastoEditable.value.fecha_documento ||
        !!fechaDocumentoInvalida.value || // Deshabilitar si la fecha es inválida
        !gastoEditable.value.tipo_documento ||
        // Si no es DJ, serie y correlativo son obligatorios
        (!gastoEditable.value.es_declaracion_jurada && (!gastoEditable.value.serie_documento || !gastoEditable.value.correlativo_documento)) ||
        // Si no es DJ y no hay evidencia existente ni nueva
        (!gastoEditable.value.es_declaracion_jurada && !gastoEditable.value.ruta_evidencia && !nuevaEvidenciaFile.value);
});
//helperformat
const formatDateForInput = (isoDateString) => {
    if (!isoDateString) return '';
    return isoDateString.split('T')[0]; // Convierte "2025-05-05T05:00:00.000000Z" a "2025-05-05"
};

// --- LÓGICA DEL FORMULARIO ---
// Esta función se activa cada vez que la prop 'gasto' cambia.
// Clona el objeto para evitar mutaciones directas y pre-llena el formulario.
watch(() => props.gasto, (newGasto) => {
    if (newGasto) {
        gastoEditable.value = cloneDeep(newGasto);
        // Asegurarse de que los campos de tipo_documento, serie, correlativo estén bien inicializados
        if (!gastoEditable.value.tipo_documento) {
            gastoEditable.value.tipo_documento = gastoEditable.value.es_declaracion_jurada ? 'Declaración Jurada' : '';
        }
        if (gastoEditable.value.es_declaracion_jurada) {
            gastoEditable.value.serie_documento = ''; // Las DJ no tienen serie/correlativo
            gastoEditable.value.correlativo_documento = '';
        }
        if (gastoEditable.value.fecha_documento) {
            gastoEditable.value.fecha_documento = formatDateForInput(gastoEditable.value.fecha_documento);
        }

        gastoEditable.value.comentario_subsanacion = ''; // Campo para el comentario de corrección
        nuevaEvidenciaFile.value = null; // Reseteamos el archivo de evidencia si se abre el modal para un nuevo gasto
    }
}, { immediate: true, deep: true }); // 'immediate' para que se ejecute al montar, 'deep' para objetos anidados

// Maneja la selección de un nuevo archivo de evidencia.
const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 10 * 1024 * 1024) { // 10MB
            Swal.fire('Archivo Demasiado Grande', 'El archivo no debe superar los 10MB.', 'error');
            nuevaEvidenciaFile.value = null;
            event.target.value = '';
            return;
        }
        const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!allowedTypes.includes(file.type)) {
            Swal.fire('Tipo de Archivo Inválido', 'Solo se permiten PDF, JPG, JPEG, PNG, GIF y DOC/DOCX.', 'error');
            nuevaEvidenciaFile.value = null;
            event.target.value = '';
            return;
        }
        nuevaEvidenciaFile.value = file;
    } else {
        nuevaEvidenciaFile.value = null;
    }
};

// Sincroniza 'es_declaracion_jurada' y 'tipo_documento'
const onTipoDocumentoChange = () => {
    gastoEditable.value.es_declaracion_jurada = (gastoEditable.value.tipo_documento === 'Declaración Jurada');
    if (gastoEditable.value.es_declaracion_jurada) {
        gastoEditable.value.serie_documento = '';
        gastoEditable.value.correlativo_documento = '';
    }
    // Si se cambia de DJ a otro tipo, los campos de serie/correlativo se habilitan automáticamente
};

// --- LÓGICA DE ENVÍO ---
const enviarCorreccion = async () => {
    // Validación final antes de enviar
    if (isGuardarDisabled.value && !enviando.value) { // Solo si no está enviando y hay errores de validación
        Swal.fire('Formulario Incompleto', 'Por favor, complete todos los campos obligatorios y corrija los errores.', 'warning');
        return;
    }

    enviando.value = true;
    const formDataPayload = new FormData();

    // Adjuntamos todos los campos editables del gasto.
    for (const key in gastoEditable.value) {
        // Excluir IDs temporales y propiedades que no deben ir al backend o ya se manejan
        if (['id', 'gasto_proyectado', 'registrador', 'fondo_efectivo', 'cuenta_contable', 'historial_aprobaciones', 'observador_adm', 'dj_consolidada', 'evidencia_url'].includes(key)) {
            continue;
        }

        const value = gastoEditable.value[key];

        // Manejo de archivos de evidencia:
        // Si es la evidencia original (ruta_evidencia), solo la enviamos si no hay una nueva evidencia.
        // Si es el File object (nuevaEvidenciaFile), lo adjuntamos.
        if (key === 'ruta_evidencia') {
            if (!nuevaEvidenciaFile.value && value) { // Si no hay nuevo archivo y existe la ruta original
                formDataPayload.append(key, value); // Mantener la ruta existente
            }
            continue; // Ya se manejó o no es el campo de archivo principal
        }

        // Si es el campo 'evidencia' y es un File object (el nuevo archivo)
        if (key === 'evidencia' && value instanceof File) {
            formDataPayload.append('evidencia', value); // Adjuntar el nuevo archivo
            continue;
        }

        // Convertir booleanos a 0 o 1
        if (value !== null && value !== '') {
            const finalValue = typeof value === 'boolean' ? (value ? 1 : 0) : value;
            formDataPayload.append(key, finalValue);
        }
    }

    // Asegurarse de enviar el ID del registrador y la lógica de auto-aprobación
    formDataPayload.append('id_registrador', props.gasto.id_registrador); // El registrador original del gasto
    if (props.usuarioActual?.id) { // Asegurarse de que el usuario actual esté disponible
        if (['jefe_area', 'gerente_general', 'jefe_administracion', 'super_admin'].includes(props.usuarioActual.role?.name)) {
            formDataPayload.append('id_jefe_aprobador', props.usuarioActual.id);
            if (['jefe_administracion', 'super_admin'].includes(props.usuarioActual.role?.name)) {
                formDataPayload.append('id_validador_adm', props.usuarioActual.id);
            }
        }
    }

    // Laravel espera _method PUT para actualizar, pero FormData solo soporta POST.
    // Se usa el truco de _method POST.
    formDataPayload.append('_method', 'POST');

    try {
        const response = await api.post(`/v1/gastos/${props.gasto.id}/actualizar-observado`, formDataPayload, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        Swal.fire({
            icon: 'success',
            title: '¡Gasto Corregido!',
            text: response.data.message || 'El gasto ha sido actualizado y reenviado para su aprobación.',
        });

        emit('gastoActualizado', response.data.gasto); // Emitir el gasto actualizado
        emit('close');

    } catch (error) {
        console.error("Error al corregir el gasto:", error);
        const errorMessage = error.response?.data?.message || 'Ocurrió un error inesperado.';
        const errors = error.response?.data?.errors;
        let htmlError = `<p>${errorMessage}</p>`;
        if (errors) {
            htmlError += '<ul class="text-left mt-2 list-disc list-inside">';
            for (const key in errors) {
                const fieldName = key.replace(/gastos\.(\d+)\.(\w+)/, (match, gastoIndex, field) => `Gasto #${parseInt(gastoIndex) + 1} (${field.replace(/_/g, ' ')})`);
                htmlError += `<li>${fieldName}: ${errors[key][0]}</li>`;
            }
            htmlError += '</ul>';
        }
        Swal.fire({
            icon: 'error',
            title: 'Error al Corregir',
            html: htmlError
        });
    } finally {
        enviando.value = false;
    }
};
</script>

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

/* Estilos para mensajes de error de validación */
.shake-enter-active {
    animation: shake 0.5s;
}

@keyframes shake {

    10%,
    90% {
        transform: translate3d(-1px, 0, 0);
    }

    20%,
    80% {
        transform: translate3d(2px, 0, 0);
    }

    30%,
    50%,
    70% {
        transform: translate3d(-4px, 0, 0);
    }

    40%,
    60% {
        transform: translate3d(4px, 0, 0);
    }
}
</style>
