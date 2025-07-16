<script setup>
import { ref, watch, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

//El modal ahora recibe el objeto 'fondo' completo como una prop.
const props = defineProps({
    mostrar: Boolean,
    fondoProp: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close', 'fondoRepuesto']);

// --- ESTADO ---
const isLoading = ref(false);
const cargandoResumen = ref(false);
const reposicionSummary = ref(null);
const comprobanteFile = ref(null);
const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

// --- MÉTODOS ---
const fetchReposicionSummary = async (fondoId) => {
    if (!fondoId) {
        reposicionSummary.value = null;
        return;
    }
    cargandoResumen.value = true;
    reposicionSummary.value = null;
    try {
        const response = await api.get(`/v1/fondos-efectivo/${fondoId}/reposicion-summary`);
        reposicionSummary.value = response.data;
    } catch (error) {
        console.error("Error al cargar el resumen de reposición:", error);
        Swal.fire('Error', 'No se pudo cargar el detalle para la reposición.', 'error');
        emit('close');
    } finally {
        cargandoResumen.value = false;
    }
};

const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        comprobanteFile.value = file;
    }
};

const confirmarReposicion = async () => {
    if (!props.fondoProp || !reposicionSummary.value || !(reposicionSummary.value.monto_a_reponer > 0)) {
        Swal.fire('Acción no permitida', 'Este fondo no tiene un monto pendiente de reposición.', 'warning');
        return;
    }
    if (!comprobanteFile.value) {
        Swal.fire('Atención', 'Es obligatorio adjuntar el comprobante de la reposición.', 'warning');
        return;
    }

    const montoAReponer = parseFloat(reposicionSummary.value.monto_a_reponer);
    const saldoActual = parseFloat(reposicionSummary.value.saldo_disponible_actual);
    const nuevoSaldo = parseFloat(reposicionSummary.value.monto_asignado);

    const result = await Swal.fire({
        title: `¿Reponer Fondo ${props.fondoProp.codigo_fondo}?`,
        html: `
            <div class="text-left text-sm space-y-3 p-4 my-3 bg-gray-50 border rounded-lg shadow-inner">
                <div class="flex justify-between">
                    <span>Saldo Actual del Fondo:</span>
                    <span class="font-medium ${saldoActual < 0 ? 'text-rojo-bap' : 'text-gray-700'}">${currencyFormatter.format(saldoActual)}</span>
                </div>
                <div class="flex justify-between">
                    <span>(+) Total de gastos a reponer:</span>
                    <strong class="text-verde-bap-dark">${currencyFormatter.format(montoAReponer)}</strong>
                </div>
                <hr class="my-2">
                <div class="flex justify-between font-bold text-base">
                    <span>(=) Nuevo Saldo del Fondo:</span>
                    <strong class="text-gray-800">${currencyFormatter.format(nuevoSaldo)}</strong>
                </div>
            </div>
            <p class="mt-4 text-gray-600 text-sm">Se repondrá el total de los gastos para restaurar el fondo a su monto original. ¿Desea continuar?</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Sí, reponer ahora',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        isLoading.value = true;
        const formData = new FormData();
        formData.append('comprobante_reposicion', comprobanteFile.value);

        try {
            const response = await api.post(`/v1/fondos-efectivo/${props.fondoProp.id_fondo}/reponer`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            await Swal.fire('¡Éxito!', response.data.message, 'success');
            emit('fondoRepuesto');
            emit('close');
        } catch (error) {
            Swal.fire('Error', error.response?.data?.message || 'No se pudo reponer el fondo.', 'error');
        } finally {
            isLoading.value = false;
        }
    }
};

// --- WATCHERS ---
//  El watcher ahora se activa con la prop 'fondoProp' cuando el modal se muestra.
watch(() => props.mostrar, (newVal) => {
    if (newVal && props.fondoProp) {
        fetchReposicionSummary(props.fondoProp.id_fondo);
    } else {
        // Limpieza completa al cerrar el modal
        reposicionSummary.value = null;
        comprobanteFile.value = null;
        isLoading.value = false;
    }
});
</script>

<template>
    <Transition name="modal-fade">
        <div v-if="mostrar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm">
            <div
                class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-auto overflow-hidden transform transition-all">
                <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-xl font-semibold text-gray-800">Estado y Reposición de Fondo</h3>
                    <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
                    <!-- [NUEVO] Panel de información del fondo seleccionado -->
                    <div v-if="props.fondoProp" class="p-4 bg-blue-50 border-l-4 border-blue-400 rounded-r-lg">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="text-sm text-gray-600">Fondo Seleccionado</p>
                                <p class="font-bold text-gray-800">{{ props.fondoProp.codigo_fondo }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 text-right">Responsable</p>
                                <p class="font-bold text-gray-800 text-right">{{ props.fondoProp.responsable.name }} {{
                                    props.fondoProp.responsable.last_name }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="cargandoResumen" class="text-center text-gray-500 py-4">
                        <p>Cargando resumen del fondo...</p>
                    </div>

                    <!-- Contenedor principal del contenido del modal -->
                    <div v-else-if="reposicionSummary" class="space-y-4 animate-fade-in">
                        <!-- Caso 1: El fondo SÍ está listo para reponer -->
                        <div v-if="reposicionSummary.monto_a_reponer > 0">
                            <!-- Resumen de Totales -->
                            <div class="p-4 bg-gray-100 border-l-4 border-verde-bap rounded-r-lg">
                                <h4 class="font-bold text-gray-700 mb-2">Resumen de Reposición</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <span class="text-gray-600">Monto Asignado:</span>
                                    <span class="font-semibold text-right">{{
                                        currencyFormatter.format(reposicionSummary.monto_asignado) }}</span>
                                    <span class="text-gray-600">Saldo Disponible Actual:</span>
                                    <span :class="reposicionSummary.saldo_disponible_actual < 0 ? 'text-rojo-bap' : ''"
                                        class="font-semibold text-right">{{
                                            currencyFormatter.format(reposicionSummary.saldo_disponible_actual) }}</span>
                                    <hr class="col-span-2 my-1 border-gray-300">
                                    <span class="text-gray-800 font-bold">Monto a Reponer:</span>
                                    <span class="font-bold text-verde-bap text-right text-lg">{{
                                        currencyFormatter.format(reposicionSummary.monto_a_reponer) }}</span>
                                </div>
                            </div>

                            <!-- Detalle de Gastos a Reponer -->
                            <div
                                v-if="reposicionSummary.gastos_a_reponer && reposicionSummary.gastos_a_reponer.length > 0">
                                <h4 class="font-bold text-gray-700 mb-2">Gastos a Reponer ({{
                                    reposicionSummary.gastos_a_reponer.length }})</h4>
                                <div class="border rounded-lg max-h-40 overflow-y-auto text-sm">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50 sticky top-0">
                                            <tr>
                                                <!-- [MODIFICADO] El encabezado ahora es más genérico -->
                                                <th class="px-4 py-2 text-left font-medium text-gray-500">Detalle del
                                                    Gasto</th>
                                                <th class="px-4 py-2 text-right font-medium text-gray-500">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="gasto in reposicionSummary.gastos_a_reponer" :key="gasto.id">
                                                <!-- [MODIFICADO] La celda ahora muestra ambos datos -->
                                                <td class="px-4 py-2">
                                                    <p class="font-semibold text-gray-800">{{
                                                        gasto.gasto_proyectado?.descripcion || 'Categoría no especificada' }}</p>
                                                    <p class="text-xs text-gray-500 italic mt-1">{{ gasto.glosa }}</p>
                                                </td>
                                                <td class="px-4 py-2 text-right text-gray-600 align-top">{{
                                                    currencyFormatter.format(gasto.monto_total) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Campo para adjuntar comprobante -->
                            <div>
                                <label for="comprobante-reposicion"
                                    class="block text-sm font-medium text-gray-700 mb-1">Adjuntar Comprobante de
                                    Reposición <span class="text-rojo-bap">*</span></label>
                                <input type="file" id="comprobante-reposicion" @change="handleFileChange"
                                    accept=".pdf,.jpg,.jpeg,.png"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-verde-bap-extralight file:text-verde-bap-dark hover:file:bg-verde-bap-light" />
                                <p v-if="comprobanteFile" class="text-xs text-gray-600 mt-1">Archivo seleccionado: {{
                                    comprobanteFile.name }}</p>
                            </div>
                        </div>

                        <!-- Caso 2: El fondo NO está listo para reponer -->
                        <div v-else
                            class="p-4 bg-estado-alerta-bg border-l-4 border-amarillo-bap-dark text-estado-alerta-text rounded-lg">
                            <p class="font-bold">Este fondo no requiere reposición.</p>
                            <p class="text-sm">{{ reposicionSummary.mensaje_estado || 'No hay gastos contabilizados para reponer.' }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-200 flex justify-end bg-gray-50">
                    <button @click="$emit('close')"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition-colors mr-3">Cancelar</button>
                    <!--  El botón se deshabilita si no hay monto a reponer -->
                    <button @click="confirmarReposicion"
                        :disabled="isLoading || !reposicionSummary || !(reposicionSummary.monto_a_reponer > 0)"
                        class="px-4 py-2 bg-verde-bap text-white rounded-md hover:bg-verde-bap-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        <span v-if="isLoading">Procesando...</span>
                        <span v-else>Confirmar Reposición</span>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
