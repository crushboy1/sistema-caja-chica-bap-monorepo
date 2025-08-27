<template>
    <Transition name="modal-backdrop">
        <div v-if="mostrar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
            <Transition name="modal-content" appear>
                <div
                    class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[80vh] flex flex-col overflow-hidden">
                    <!-- Header con gradiente y mejor jerarquía visual -->
                    <div class="bg-gradient-to-r from-slate-50 to-gray-100 p-6 border-b border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div
                                    class="w-12 h-12 bg-gradient-to-br from-orange-400 to-orange-600 rounded-xl flex items-center justify-center shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-gray-900">
                                        Corregir Gasto Observado
                                    </h3>
                                    <p class="text-sm text-gray-600 mt-1">Código: {{ gasto?.codigo_gasto }}</p>
                                </div>
                            </div>
                            <button @click="emit('close')"
                                class="w-10 h-10 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition-all duration-200 hover:scale-105">
                                <svg class="h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Contenido del formulario con scroll mejorado -->
                    <div class="flex-1 overflow-y-auto">
                        <form @submit.prevent="enviarCorreccion" class="p-6">

                            <!-- Alerta de observación mejorada -->
                            <div class="mb-8 relative">
                                <div
                                    class="absolute -left-1 top-0 bottom-0 w-1 bg-gradient-to-b from-orange-400 to-orange-600 rounded-full">
                                </div>
                                <div
                                    class="bg-gradient-to-r from-orange-50 to-amber-50 border border-orange-200 rounded-xl p-5 ml-4 shadow-sm">
                                    <div class="flex items-start space-x-3">
                                        <div
                                            class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                            <svg class="w-4 h-4 text-orange-600" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-semibold text-orange-900 text-sm mb-1">Motivo de la
                                                Observación</h4>
                                            <p class="text-sm text-orange-800 leading-relaxed">
                                                {{ gasto.motivo_observacion_adm || 'No se especificó un motivo específico.' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Secciones organizadas con tarjetas -->
                            <div class="space-y-8">

                                <!-- Información General -->
                                <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <div
                                            class="w-6 h-6 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        Información General
                                    </h4>

                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                        <div class="lg:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Gasto
                                                Proyectado</label>
                                            <div class="relative">
                                                <input type="text"
                                                    :value="gasto?.gasto_proyectado?.descripcion || 'N/A'"
                                                    class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 cursor-not-allowed focus:outline-none"
                                                    disabled />
                                                <div class="absolute right-3 top-1/2 transform -translate-y-1/2">
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Monto del Documento (S/.) <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <div
                                                    class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 font-medium">
                                                    S/.</div>
                                                <input type="number" v-model.number="gastoEditable.monto_total" :class="[
                                                    'w-full pl-12 pr-4 py-3 border rounded-lg transition-all duration-200 focus:outline-none focus:ring-2',
                                                    montoExcedeSaldo
                                                        ? 'border-rojo-bap focus:border-rojo-bap-dark ring-rojo-bap text-rojo-bap-dark'
                                                        : 'border-gray-300 focus:border-verde-bap focus:ring-verde-bap'
                                                ]" step="0.01" min="0.01" required placeholder="0.00" />
                                            </div>
                                            <Transition name="error-slide">
                                                <p v-if="montoExcedeSaldo"
                                                    class="text-xs text-red-600 mt-2 flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                                    </svg>
                                                    {{ montoExcedeSaldo }}
                                                </p>
                                            </Transition>
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Fecha del Documento <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <input type="date" v-model="gastoEditable.fecha_documento" :class="[
                                                    'w-full p-3 border rounded-lg transition-all duration-200 focus:outline-none focus:ring-2',
                                                    fechaDocumentoInvalida
                                                        ? 'border-red-300 focus:ring-red-500 focus:border-red-500 bg-red-50'
                                                        : 'border-gray-300 focus:border-verde-bap focus:ring-verde-bap'
                                                ]" required />
                                            </div>
                                            <Transition name="error-slide">
                                                <p v-if="fechaDocumentoInvalida"
                                                    class="text-xs text-red-600 mt-2 flex items-center">
                                                    <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                                    </svg>
                                                    {{ fechaDocumentoInvalida }}
                                                </p>
                                            </Transition>
                                        </div>
                                    </div>

                                    <div class="mt-6">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Glosa / Descripción del Gasto <span class="text-red-500">*</span>
                                        </label>
                                        <textarea v-model="gastoEditable.glosa" rows="3"
                                            class="w-full p-3 border border-gray-300 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-verde-bap focus:border-verde-bap"
                                            placeholder="Describe brevemente el gasto realizado..." required></textarea>
                                    </div>
                                </div>

                                <!-- Información del Documento -->
                                <div class="bg-indigo-50 rounded-xl p-6 border border-indigo-200">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <div
                                            class="w-6 h-6 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        Información del Documento
                                    </h4>

                                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                Tipo de Documento <span class="text-red-500">*</span>
                                            </label>
                                            <select v-model="gastoEditable.id_tipo_documento_comprobante"
                                                @change="onTipoDocumentoChange"
                                                class="w-full p-3 border border-gray-300 rounded-lg focus:border-blue-500"
                                                required>
                                                <option :value="null" disabled>Seleccione un tipo</option>
                                                <option v-for="doc in tiposDocumento" :key="doc.id" :value="doc.id">
                                                    {{ doc.nombre }}
                                                </option>
                                            </select>
                                        </div>

                                        <template v-if="!gastoEditable.es_declaracion_jurada">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    Serie Documento <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" v-model="gastoEditable.serie_documento"
                                                    class="w-full p-3 border border-gray-300 rounded-lg"
                                                    placeholder="Ej: B001"
                                                    :required="!gastoEditable.es_declaracion_jurada" />
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    Correlativo Documento <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" v-model="gastoEditable.correlativo_documento"
                                                    class="w-full p-3 border border-gray-300 rounded-lg"
                                                    placeholder="Ej: 000123"
                                                    :required="!gastoEditable.es_declaracion_jurada" />
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                <!-- Evidencia -->
                                <div v-if="!gastoEditable.es_declaracion_jurada"
                                    class="bg-green-50 rounded-xl p-6 border border-green-200">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <div
                                            class="w-6 h-6 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.586-6.586a2 2 0 000-2.828z M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.586-6.586a2 2 0 000-2.828z" />
                                            </svg>
                                        </div>
                                        Archivo de Evidencia <span class="text-red-500">*</span>
                                    </h4>

                                    <p
                                        class="text-sm text-gray-600 mb-4 bg-green-50 p-3 rounded-lg border border-green-200">
                                        💡 Adjunta el comprobante o sustento del gasto. Si la observación fue por el
                                        archivo, sube uno nuevo.
                                    </p>

                                    <!-- Archivo actual -->
                                    <div v-if="gastoEditable.ruta_evidencia && !nuevaEvidenciaFile" class="mb-4">
                                        <div
                                            class="bg-white border border-gray-200 rounded-lg p-4 flex items-center justify-between shadow-sm">
                                            <div class="flex items-center space-x-3">
                                                <div
                                                    class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">Archivo actual</p>
                                                    <a :href="`/storage/${gastoEditable.ruta_evidencia}`"
                                                        target="_blank"
                                                        class="text-sm text-blue-600 hover:text-blue-800 hover:underline transition-colors">
                                                        Ver evidencia →
                                                    </a>
                                                </div>
                                            </div>
                                            <button type="button"
                                                @click="gastoEditable.ruta_evidencia = null; nuevaEvidenciaFile = null;"
                                                class="w-8 h-8 rounded-full bg-red-100 hover:bg-red-200 flex items-center justify-center transition-colors">
                                                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12">
                                                    </path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Input de archivo mejorado -->
                                    <div class="relative">
                                        <input type="file" @change="handleFileChange" id="file-input"
                                            class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                                        <div
                                            class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-green-400 hover:bg-green-25 transition-all duration-200 bg-white">
                                            <div
                                                class="w-12 h-12 bg-green-100 rounded-xl mx-auto mb-4 flex items-center justify-center">
                                                <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                                </svg>
                                            </div>
                                            <p class="text-gray-600 font-medium mb-1">Arrastra tu archivo aquí o haz
                                                clic para seleccionar</p>
                                            <p class="text-xs text-gray-500">PDF, JPG, PNG, DOC, DOCX (máx. 10MB)</p>
                                        </div>
                                    </div>

                                    <!-- Archivo seleccionado -->
                                    <Transition name="file-appear">
                                        <div v-if="nuevaEvidenciaFile"
                                            class="mt-4 bg-white border border-green-200 rounded-lg p-4 shadow-sm">
                                            <div class="flex items-center space-x-3">
                                                <div
                                                    class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-medium text-green-800">Nuevo archivo
                                                        seleccionado</p>
                                                    <p class="text-sm text-gray-600">{{ nuevaEvidenciaFile.name }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </Transition>
                                </div>

                                <!-- Comentario de Subsanación -->
                                <div class="bg-amber-50 rounded-xl p-6 border border-amber-200">
                                    <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                        <div
                                            class="w-6 h-6 bg-amber-100 rounded-lg flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                            </svg>
                                        </div>
                                        Comentario de Subsanación (Opcional)
                                    </h4>

                                    <p class="text-sm text-gray-600 mb-4">Añade un comentario para explicar los cambios
                                        realizados.</p>
                                    <textarea v-model="gastoEditable.comentario_subsanacion" rows="4"
                                        class="w-full p-3 border border-gray-300 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 hover:border-gray-400 resize-none"
                                        placeholder="Explica brevemente las correcciones realizadas..."></textarea>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Footer con botones mejorados -->
                    <div
                        class="bg-gradient-to-r from-gray-50 to-slate-50 px-6 py-4 flex justify-end space-x-4 border-t border-gray-200">
                        <button type="button" @click="emit('close')"
                            class="px-6 py-3 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 font-medium shadow-sm">
                            Cancelar
                        </button>
                        <button type="button" @click="enviarCorreccion" :disabled="isGuardarDisabled"
                            class="px-6 py-3 bg-gradient-to-r from-verde-bap to-green-600 text-white rounded-lg hover:from-verde-bap-dark hover:to-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 font-medium shadow-lg flex items-center space-x-2">
                            <svg v-if="enviando" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            <span v-if="enviando">Enviando...</span>
                            <span v-else>Guardar y Reenviar</span>
                        </button>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { cloneDeep, isEqual } from 'lodash-es';

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

const emit = defineEmits(['close', 'gastoActualizado']);

// --- ESTADO REACTIVO ---
const gastoEditable = ref({});
const gastoOriginal = ref(null);
const nuevaEvidenciaFile = ref(null);
const enviando = ref(false);
const tiposDocumento = ref([]);
// --- CARGA DE DATOS ---
onMounted(async () => {
    // Carga el catálogo de tipos de documento cuando el componente se monta
    try {
        const response = await api.get('/v1/tipos-documento-comprobante');
        tiposDocumento.value = response.data;
    } catch (error) {
        console.error("Error al cargar tipos de documento:", error);
        Swal.fire('Error', 'No se pudo cargar el catálogo de tipos de documento.', 'error');
    }
});
// --- FUNCIONES AUXILIARES ---
const getTipoDocumento = (gasto) => {
    if (!gasto.id_tipo_documento_comprobante) return null;
    return tiposDocumento.value.find(doc => doc.id === gasto.id_tipo_documento_comprobante);
};
// --- PROPIEDADES COMPUTADAS PARA VALIDACIÓN Y ESTADO ---
const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

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

const hayCambios = computed(() => {
    if (!gastoOriginal.value) return false;
    // Compara el objeto editable actual con la copia original.
    // isEqual de lodash es perfecto para comparaciones profundas de objetos.
    const datosCambiados = !isEqual(gastoOriginal.value, gastoEditable.value);
    // También considera si se ha añadido un nuevo archivo.
    const archivoCambiado = !!nuevaEvidenciaFile.value;
    return datosCambiados || archivoCambiado;
});
// Determina si el botón de guardar debe estar deshabilitado
const isGuardarDisabled = computed(() => {
    const tipoDocSeleccionado = getTipoDocumento(gastoEditable.value);
    const requiereComprobante = tipoDocSeleccionado && !tipoDocSeleccionado.nombre.includes('Declaración Jurada');

    return enviando.value ||
        !gastoEditable.value.monto_total ||
        !gastoEditable.value.glosa ||
        !gastoEditable.value.fecha_documento ||
        !!fechaDocumentoInvalida.value ||
        !gastoEditable.value.id_tipo_documento_comprobante ||
        (requiereComprobante && (!gastoEditable.value.serie_documento || !gastoEditable.value.correlativo_documento)) ||
        (requiereComprobante && !gastoEditable.value.ruta_evidencia && !nuevaEvidenciaFile.value);
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
        const gastoClonado = cloneDeep(newGasto);
        if (gastoClonado.fecha_documento) {
            gastoClonado.fecha_documento = formatDateForInput(gastoClonado.fecha_documento);
        }
        gastoClonado.comentario_subsanacion = '';
        gastoEditable.value = gastoClonado;
        gastoOriginal.value = cloneDeep(gastoClonado);
        nuevaEvidenciaFile.value = null;
    }
}, { immediate: true, deep: true });

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
    const tipoDoc = getTipoDocumento(gastoEditable.value);
    gastoEditable.value.es_declaracion_jurada = tipoDoc && tipoDoc.nombre.includes('Declaración Jurada');
    if (gastoEditable.value.es_declaracion_jurada) {
        gastoEditable.value.serie_documento = '';
        gastoEditable.value.correlativo_documento = '';
    }
};

// --- LÓGICA DE ENVÍO ---
const enviarCorreccion = () => {
    // 1. Se verifica si hay errores de validación (campos requeridos, etc.)
    const hayErrores = // Se copian las condiciones de tu 'isGuardarDisabled' que no dependen de 'hayCambios'
        !gastoEditable.value.monto_total ||
        !gastoEditable.value.glosa ||
        !gastoEditable.value.fecha_documento ||
        !!fechaDocumentoInvalida.value ||
        !gastoEditable.value.tipo_documento ||
        (!gastoEditable.value.es_declaracion_jurada && (!gastoEditable.value.serie_documento || !gastoEditable.value.correlativo_documento)) ||
        (!gastoEditable.value.es_declaracion_jurada && !gastoEditable.value.ruta_evidencia && !nuevaEvidenciaFile.value);

    if (hayErrores) {
        Swal.fire('Formulario Incompleto', 'Por favor, complete todos los campos obligatorios (*).', 'warning');
        return;
    }
    // 2. Se define el contenido de la alerta dinámicamente.
    const configAlerta = {
        title: '¿Confirmar acción?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#76C49D', // Verde BAP
        cancelButtonColor: '#6B7280', // Gris
        cancelButtonText: 'Cancelar'
    };
    if (hayCambios.value) {
        // Alerta si SÍ hubo cambios
        configAlerta.html = `
            <p class="text-gray-600 text-sm">Se guardarán los cambios y el gasto <strong>${props.gasto?.codigo_gasto}</strong> se reenviará para su aprobación.</p>
            <p class="text-gray-600 text-sm mt-2">Su estado cambiará a <span class="font-semibold text-amarillo-bap-dark">Pendiente</span>.</p>`;
        configAlerta.confirmButtonText = 'Sí, Guardar y Enviar';
    } else {
        // Alerta si NO hubo cambios
        configAlerta.html = `
            <p class="text-gray-600 text-sm">No se han detectado cambios en el formulario.</p>
            <p class="text-gray-600 text-sm mt-2">¿Deseas confirmar que el gasto <strong>${props.gasto?.codigo_gasto}</strong> está correcto y reenviarlo igualmente?</p>`;
        configAlerta.confirmButtonText = 'Sí, Reenviar';
        configAlerta.icon = 'info';
    }
    // 3. Se muestra la alerta correspondiente.
    Swal.fire(configAlerta).then((result) => {
        if (result.isConfirmed) {
            enviarCorreccionFinal();
        }
    });
};

const enviarCorreccionFinal = async () => {
    enviando.value = true;
    const formDataPayload = new FormData();

    // Adjuntamos todos los campos editables del gasto.
    for (const key in gastoEditable.value) {
        // Excluir IDs temporales y propiedades que no deben ir al backend o ya se manejan
        if (['id', 'gasto_proyectado', 'registrador', 'fondo_efectivo', 'cuenta_contable', 'historial_aprobaciones', 'observador_adm', 'dj_consolidada', 'evidencia_url', 'tipo_documento'].includes(key)) {
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
        if (error.response && error.response.status === 403) {
            Swal.fire({
                icon: 'error',
                title: 'Acción No Permitida',
                html: `<p>No se puede actualizar el gasto.</p><p class="mt-2 text-sm">${error.response.data.message || 'La fecha seleccionada corresponde a un período contable que ya ha sido cerrado.'}</p>`
            });
        } else {
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
        }
    } finally {
        enviando.value = false;
    }
};
</script>

<style scoped>
.modal-backdrop-enter-active,
.modal-backdrop-leave-active {
    transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
}

.modal-backdrop-enter-from,
.modal-backdrop-leave-to {
    opacity: 0;
}

.modal-content-enter-active,
.modal-content-leave-active {
    transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
}

.modal-content-enter-from,
.modal-content-leave-to {
    opacity: 0;
    transform: translateY(-40px) scale(0.95);
}

/* Transiciones de errores */
.error-slide-enter-active,
.error-slide-leave-active {
    transition: all 0.3s ease;
}

.error-slide-enter-from,
.error-slide-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

/* Transición para archivo seleccionado */
.file-appear-enter-active,
.file-appear-leave-active {
    transition: all 0.3s ease;
}

.file-appear-enter-from,
.file-appear-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
}

/* Efectos de hover mejorados */
.hover\:scale-105:hover {
    transform: scale(1.05);
}

/* Scrollbar personalizado */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Animación de carga */
@keyframes spin {
    from {
        transform: rotate(0deg);
    }

    to {
        transform: rotate(360deg);
    }
}

.animate-spin {
    animation: spin 1s linear infinite;
}
</style>
