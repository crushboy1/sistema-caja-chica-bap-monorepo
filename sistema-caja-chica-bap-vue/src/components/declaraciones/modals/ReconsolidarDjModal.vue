<template>
    <div
        class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm flex justify-center items-center z-50 p-4 animate-fade-in">
        <div class="bg-white rounded-2xl shadow-strong w-full max-w-2xl transform animate-fade-in-up" @click.stop>
            <div class="p-6 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Re-Consolidar Declaración Jurada</h3>
                <button @click="emit('close')" class="text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
                <!-- Resumen de Gastos Seleccionados -->
                <div>
                    <h4 class="font-semibold text-gray-700 mb-2">Gastos a Consolidar ({{ gastosAConsolidar.length }})
                    </h4>
                    <ul class="space-y-2 border rounded-lg p-3 bg-gray-50">
                        <li v-for="gasto in gastosAConsolidar" :key="gasto.id"
                            class="text-sm flex justify-between items-center">
                            <span class="text-gray-600">{{ gasto.codigo_gasto }} - {{ gasto.glosa }}</span>
                            <span class="font-medium text-gray-800">{{ currencyFormatter.format(gasto.monto_total)
                                }}</span>
                        </li>
                        <li class="border-t pt-2 mt-2 font-bold flex justify-between items-center text-base">
                            <span class="text-gray-900">Total:</span>
                            <span class="text-verde-bap-dark">{{ currencyFormatter.format(totalConsolidado) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Pasos para la DJ -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                        <p class="text-sm text-blue-800 font-medium">Paso 1: Generar Plantilla</p>
                        <p class="text-xs text-blue-600 mt-1 mb-3">
                            Descarga el documento que agrupa todos los gastos seleccionados.
                        </p>
                        <button type="button" @click="generarDJ" :disabled="generandoPDF"
                            class="mt-3 bg-verde-bap hover:bg-verde-bap-dark text-white font-semibold py-2 px-4 rounded-lg transition-colors shadow-sm flex items-center disabled:opacity-50 disabled:cursor-not-allowed mx-auto">
                            <svg v-if="generandoPDF" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            <svg v-else class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            <span>{{ generandoPDF ? 'Generando...' : 'Generar PDF' }}</span>
                        </button>
                    </div>

                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                        <p class="text-sm text-green-800 font-medium">Paso 2: Subir Documento Firmado</p>
                        <p class="text-xs text-green-600 mt-1 mb-3">
                            Adjunta aquí la plantilla después de haberla firmado.
                        </p>
                        <input type="file" id="dj_reconsolidar_input" @change="handleFileChange" class="hidden"
                            accept=".pdf">
                        <label for="dj_reconsolidar_input"
                            class="mt-3 cursor-pointer bg-white hover:bg-gray-100 text-gray-700 font-semibold py-2 px-4 rounded-lg transition-colors shadow-sm flex items-center border border-gray-300 mx-auto justify-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                            <span>{{ djFile ? 'Cambiar' : 'Seleccionar' }} Archivo</span>
                        </label>
                        <p v-if="djFile" class="text-xs text-green-700 mt-2 truncate" :title="djFile.name">
                            Archivo: {{ djFile.name }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="p-6 bg-gray-50 rounded-b-2xl flex justify-end space-x-4">
                <button @click="emit('close')"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-6 rounded-lg transition-colors">
                    Cancelar
                </button>
                <button @click="enviarReconsolidacion" :disabled="!djFile || enviando"
                    class="bg-rojo-bap hover:bg-rojo-bap-dark text-white font-bold py-2 px-6 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                    <svg v-if="enviando" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span>{{ enviando ? 'Enviando...' : 'Consolidar y Enviar' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { cloneDeep } from 'lodash-es'; // Asegúrate de que lodash-es esté instalado: npm install lodash-es

// --- PROPS Y EMITS ---
const props = defineProps({
    gastosAConsolidar: { // Array de objetos de gasto seleccionados desde SeguimientoGastos.vue
        type: Array,
        required: true
    },
    usuarioActual: { // Objeto del usuario actual para enviar id_registrador
        type: Object,
        required: true
    },
    mostrar: { // Bandera para controlar la visibilidad del modal
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'dj-creada']);

// --- ESTADO REACTIVO ---
const djFile = ref(null); // Archivo PDF de la DJ consolidada a subir
const enviando = ref(false); // Estado de carga para el envío de la consolidación
const generandoPDF = ref(false); // Estado de carga para la generación del PDF

const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

// --- PROPIEDADES COMPUTADAS ---
const totalConsolidado = computed(() => {
    return props.gastosAConsolidar.reduce((sum, gasto) => sum + parseFloat(gasto.monto_total || 0), 0);
});

// --- MÉTODOS DE MANEJO DE ARCHIVOS Y GENERACIÓN DE DJ ---

// Maneja la selección de un nuevo archivo de DJ consolidada
const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        // Validar tamaño
        if (file.size > 10 * 1024 * 1024) { // 10MB
            Swal.fire('Archivo Demasiado Grande', 'El archivo PDF de la DJ consolidada no debe superar los 10MB.', 'error');
            djFile.value = null;
            event.target.value = ''; // Limpiar input file
            return;
        }
        // Validar tipo de archivo (solo PDF)
        if (file.type !== 'application/pdf') {
            Swal.fire('Tipo de Archivo Inválido', 'El archivo de la DJ consolidada debe ser en formato PDF.', 'error');
            djFile.value = null;
            event.target.value = ''; // Limpiar input file
            return;
        }
        djFile.value = file;
    } else {
        djFile.value = null;
    }
};

// Genera la plantilla PDF de la DJ consolidada
const generarDJ = async () => {
    if (props.gastosAConsolidar.length === 0) {
        Swal.fire('Sin Gastos', 'No hay gastos seleccionados.', 'warning');
        return;
    }
    generandoPDF.value = true;
    Swal.fire({
        title: 'Generando Plantilla...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        // 1. Extraer solo los IDs de los gastos.
        const gastosIds = props.gastosAConsolidar.map(gasto => gasto.id);

        // 2. Enviar el payload simple con los IDs.
        const response = await api.post('/v1/documentos/generar-dj-consolidada', {
            gastos_ids: gastosIds
        }, {
            responseType: 'blob', // Para manejar la descarga del archivo
        });

        // 3. Descargar el archivo.
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const link = document.createElement('a');
        link.href = url;
        const filename = `DJ-Reconsolidacion-Plantilla-${Date.now()}.pdf`;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        Swal.fire('¡Plantilla Generada!', 'La plantilla ha sido descargada. Fírmala y súbela en el Paso 2.', 'success');

    } catch (error) {
        console.error("Error al generar la DJ Consolidada:", error);
        Swal.fire({
            icon: 'error',
            title: 'Error al Generar DJ',
            text: error.response?.data?.message || 'No se pudo generar el documento PDF.'
        });
    } finally {
        generandoPDF.value = false;
    }
};

/**
 * COMENTARIO BAP: Se han eliminado los campos redundantes del FormData en el envío final.
 */
const enviarReconsolidacion = async () => {
    if (!djFile.value) {
        Swal.fire('Falta Archivo', 'Por favor, suba el documento de la DJ firmado.', 'warning');
        return;
    }

    enviando.value = true;
    const formData = new FormData();

    // 1. Adjuntar los IDs de los gastos a consolidar.
    props.gastosAConsolidar.forEach(gasto => {
        formData.append('gastos_ids[]', gasto.id);
    });

    // 2. Adjuntar el archivo de la DJ consolidada.
    formData.append('dj_consolidada_file', djFile.value);

    try {
        const response = await api.post('/v1/gastos/consolidate-dj', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        Swal.fire({
            icon: 'success',
            title: '¡DJ Re-Consolidada!',
            text: response.data.message || 'La DJ ha sido re-consolidada exitosamente.',
        });
        emit('dj-creada');

    } catch (error) {
        console.error("Error al re-consolidar la DJ:", error);
        const errorMessage = error.response?.data?.message || 'No se pudo completar la operación.';
        const errors = error.response?.data?.errors;
        let htmlError = `<p>${errorMessage}</p>`;
        if (errors) {
            htmlError += '<ul class="text-left mt-2 list-disc list-inside">';
            for (const key in errors) {
                htmlError += `<li>${errors[key][0]}</li>`;
            }
            htmlError += '</ul>';
        }
        Swal.fire({
            icon: 'error',
            title: 'Error de Re-Consolidación',
            html: htmlError
        });
    } finally {
        enviando.value = false;
    }
};
</script>

<style scoped>
/* Estilos del modal */
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
