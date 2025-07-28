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
const ejecutarCierre = ref(false);
// Determina el modo del modal ('reponer', 'devolver', o 'ninguno').
const modo = computed(() => {
    if (!reposicionSummary.value) return 'ninguno';
    if (reposicionSummary.value.monto_a_reponer > 0) return 'reponer';
    if (reposicionSummary.value.monto_a_devolver > 0) return 'devolver';
    return 'ninguno';
});

const modalTitle = computed(() => {
    if (modo.value === 'reponer') return 'Reposición de Excedente de Fondo';
    if (modo.value === 'devolver') return 'Registro de Devolución de Fondo';
    return 'Estado del Fondo';
});

const confirmButtonText = computed(() => {
    if (modo.value === 'reponer') return 'Confirmar Reposición';
    if (modo.value === 'devolver') return 'Registrar Devolución';
    return 'Acción no disponible';
});

const isConfirmButtonDisabled = computed(() => {
    if (isLoading.value || modo.value === 'ninguno') return true;
    // La reposición exige un comprobante. La devolución no.
    if (modo.value === 'reponer' && !comprobanteFile.value) return true;
    return false;
});
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

const confirmarAccion = async () => {
    const esCierreDefinitivo = props.fondoProp.tiene_cierre_aprobado || ejecutarCierre.value;
    let configAlerta = {
        icon: 'question',
        showCancelButton: true,
        cancelButtonColor: '#6B7280',
        cancelButtonText: 'Cancelar'
    };

    if (modo.value === 'reponer') {
        const montoAReponer = parseFloat(reposicionSummary.value.monto_a_reponer);
        const saldoActual = parseFloat(reposicionSummary.value.saldo_disponible_actual);
        const saldoPostLiquidacion = saldoActual + montoAReponer;
        const nuevoSaldoProyectado = esCierreDefinitivo ? 0 : parseFloat(reposicionSummary.value.monto_asignado);

        configAlerta.title = `¿Reponer y ${esCierreDefinitivo ? 'Cerrar Fondo' : 'Continuar Fondo'}?`;
        configAlerta.html = `
            <div class="text-left text-sm space-y-3 p-4 my-3 bg-gray-50 border rounded-lg shadow-inner">
                <div class="flex justify-between"><span>Saldo Actual:</span><span class="font-medium text-rojo-bap">${currencyFormatter.format(saldoActual)}</span></div>
                <div class="flex justify-between"><span>(+) Reembolso por Excedente:</span><strong class="text-verde-bap-dark">${currencyFormatter.format(montoAReponer)}</strong></div>
                <hr class="my-2"><div class="flex justify-between"><span>(=) Saldo Tras Liquidación:</span><strong class="text-gray-800">${currencyFormatter.format(saldoPostLiquidacion)}</strong></div>
            </div>
            <div class="mt-4 text-gray-600 text-sm">
                ${esCierreDefinitivo
                ? `<p>El fondo se <strong>cerrará permanentemente</strong> y su saldo final será <strong>${currencyFormatter.format(nuevoSaldoProyectado)}</strong>.</p>`
                : `<p>El fondo se liquidará y su saldo para el siguiente período será restaurado a <strong>${currencyFormatter.format(nuevoSaldoProyectado)}</strong>.</p>`
            }
            </div>`;
        configAlerta.confirmButtonText = esCierreDefinitivo ? 'Sí, liquidar y cerrar' : 'Sí, reponer';
        configAlerta.confirmButtonColor = esCierreDefinitivo ? '#DC2626' : '#10B981';

        const result = await Swal.fire(configAlerta);
        if (result.isConfirmed) ejecutarReposicion();

    } else if (modo.value === 'devolver') {
        const montoADevolver = parseFloat(reposicionSummary.value.monto_a_devolver);
        const saldoActual = parseFloat(reposicionSummary.value.saldo_disponible_actual);
        const saldoPostLiquidacion = saldoActual - montoADevolver;
        const nuevoSaldoProyectado = esCierreDefinitivo ? 0 : parseFloat(reposicionSummary.value.monto_asignado);

        configAlerta.title = `¿Registrar Devolución y ${esCierreDefinitivo ? 'Cerrar Fondo' : 'Continuar Fondo'}?`;
        configAlerta.html = `
            <div class="text-left text-sm space-y-3 p-4 my-3 bg-gray-50 border rounded-lg shadow-inner">
                <div class="flex justify-between"><span>Saldo Actual:</span><span class="font-medium">${currencyFormatter.format(saldoActual)}</span></div>
                <div class="flex justify-between"><span>(-) Devolución de Sobrante:</span><strong class="text-rojo-bap">${currencyFormatter.format(montoADevolver)}</strong></div>
                <hr class="my-2"><div class="flex justify-between"><span>(=) Saldo Tras Liquidación:</span><strong class="text-gray-800">${currencyFormatter.format(saldoPostLiquidacion)}</strong></div>
            </div>
            <div class="mt-4 text-gray-600 text-sm">
                ${esCierreDefinitivo
                ? `<p>El fondo se <strong>cerrará permanentemente</strong> y su saldo final será <strong>${currencyFormatter.format(nuevoSaldoProyectado)}</strong>.</p>`
                : `<p>El fondo se liquidará y su saldo para el siguiente período será restaurado a <strong>${currencyFormatter.format(nuevoSaldoProyectado)}</strong>.</p>`
            }
            </div>`;
        configAlerta.confirmButtonText = esCierreDefinitivo ? 'Sí, liquidar y cerrar' : 'Sí, registrar';
        configAlerta.confirmButtonColor = esCierreDefinitivo ? '#DC2626' : '#10B981';

        const result = await Swal.fire(configAlerta);
        if (result.isConfirmed) ejecutarDevolucion();
    }
};

const ejecutarReposicion = async () => {
    isLoading.value = true;
    const formData = new FormData();
    formData.append('comprobante_reposicion', comprobanteFile.value);
    formData.append('ejecutar_cierre', props.fondoProp.tiene_cierre_aprobado || ejecutarCierre.value ? '1' : '0');

    try {
        const response = await api.post(`/v1/fondos-efectivo/${props.fondoProp.id_fondo}/reponer`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        await Swal.fire('¡Éxito!', response.data.message, 'success');
        emit('fondoRepuesto');
        emit('close');
    } catch (error) {
        Swal.fire('Error', error.response?.data?.message || 'No se pudo reponer el fondo.', 'error');
    } finally {
        isLoading.value = false;
    }
};


const ejecutarDevolucion = async () => {
    isLoading.value = true;
    const formData = new FormData();
    if (comprobanteFile.value) {
        formData.append('comprobante_devolucion', comprobanteFile.value);
    }
    formData.append('ejecutar_cierre', props.fondoProp.tiene_cierre_aprobado || ejecutarCierre.value ? '1' : '0');

    try {
        const response = await api.post(`/v1/fondos-efectivo/${props.fondoProp.id_fondo}/devolver`, formData);
        await Swal.fire('¡Éxito!', response.data.message, 'success');
        emit('fondoRepuesto');
        emit('close');
    } catch (error) {
        Swal.fire('Error', error.response?.data?.message || 'No se pudo registrar la devolución.', 'error');
    } finally {
        isLoading.value = false;
    }
};

// --- WATCHERS ---
//  El watcher ahora se activa con la prop 'fondoProp' cuando el modal se muestra.
watch(() => props.mostrar, (newVal) => {
    if (newVal && props.fondoProp) {
        fetchReposicionSummary(props.fondoProp.id_fondo);
        ejecutarCierre.value = false;
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
                    <h3 class="text-xl font-semibold text-gray-800">{{ modalTitle }}</h3>
                    <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-6 max-h-[70vh] overflow-y-auto">
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

                    <div v-else-if="reposicionSummary" class="space-y-4 animate-fade-in">
                        <div v-if="modo === 'reponer'">
                            <div class="p-4 bg-gray-100 border-l-4 border-verde-bap rounded-r-lg">
                                <h4 class="font-bold text-gray-700 mb-2">Resumen de Reposición por Excedente</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <span class="text-gray-600">Saldo Actual:</span>
                                    <span class="font-semibold text-right text-rojo-bap">{{
                                        currencyFormatter.format(reposicionSummary.saldo_disponible_actual) }}</span>
                                    <hr class="col-span-2 my-1 border-gray-300">
                                    <span class="text-gray-800 font-bold">Monto a Reembolsar (Excedente):</span>
                                    <span class="font-bold text-verde-bap text-right text-lg">{{
                                        currencyFormatter.format(reposicionSummary.monto_a_reponer) }}</span>
                                </div>
                            </div>
                            <div>
                                <label for="comprobante-reposicion"
                                    class="block text-sm font-medium text-gray-700 mb-1">Adjuntar Comprobante de
                                    Reembolso <span class="text-rojo-bap">*</span></label>
                                <input type="file" id="comprobante-reposicion" @change="handleFileChange"
                                    accept=".pdf,.jpg,.jpeg,.png" class="file-input-style" />
                                <p v-if="comprobanteFile" class="text-xs text-gray-600 mt-1">Archivo: {{
                                    comprobanteFile.name }}</p>
                            </div>
                        </div>

                        <div v-else-if="modo === 'devolver'">
                            <div class="p-4 bg-gray-100 border-l-4 border-orange-400 rounded-r-lg">
                                <h4 class="font-bold text-gray-700 mb-2">Resumen de Devolución de Sobrante</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <span class="text-gray-600">Saldo Disponible Actual:</span>
                                    <span class="font-semibold text-right">{{
                                        currencyFormatter.format(reposicionSummary.saldo_disponible_actual) }}</span>
                                    <hr class="col-span-2 my-1 border-gray-300">
                                    <span class="text-gray-800 font-bold">Monto a Devolver (Sobrante):</span>
                                    <span class="font-bold text-orange-600 text-right text-lg">{{
                                        currencyFormatter.format(reposicionSummary.monto_a_devolver) }}</span>
                                </div>
                            </div>
                            <div>
                                <label for="comprobante-devolucion"
                                    class="block text-sm font-medium text-gray-700 mb-1">Adjuntar Comprobante de
                                    Devolución (Opcional)</label>
                                <input type="file" id="comprobante-devolucion" @change="handleFileChange"
                                    accept=".pdf,.jpg,.jpeg,.png" class="file-input-style" />
                                <p v-if="comprobanteFile" class="text-xs text-gray-600 mt-1">Archivo: {{
                                    comprobanteFile.name }}</p>
                            </div>
                        </div>

                        <div v-else
                            class="p-4 bg-estado-alerta-bg border-l-4 border-amarillo-bap-dark text-estado-alerta-text rounded-lg">
                            <p class="font-bold">Este fondo no requiere acción.</p>
                            <p class="text-sm">{{ reposicionSummary.mensaje_estado }}</p>
                        </div>

                        <div v-if="modo !== 'ninguno'">
                            <!-- Caso A: El cierre ya fue aprobado por una solicitud. Se muestra un AVISO. -->
                            <div v-if="props.fondoProp && props.fondoProp.tiene_cierre_aprobado"
                                class="p-4 bg-red-50 border-l-4 border-rojo-bap rounded-r-lg mt-4 flex items-start space-x-3">
                                <div
                                    class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg class="w-4 h-4 text-rojo-bap-dark" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                    </svg>
                                </div>
                                <div class="flex-1">
                                    <h4 class="font-semibold text-rojo-bap-dark text-sm">Acción de Cierre Definitivo
                                    </h4>
                                    <p class="text-xs text-gray-700 mt-1">
                                        Este fondo tiene una solicitud de cierre aprobada. Al liquidar el saldo, el
                                        fondo se cerrará permanentemente.
                                    </p>
                                </div>
                            </div>

                            <!-- Caso B: No hay cierre aprobado. Se da la OPCIÓN de cerrarlo. -->
                            <div v-else class="p-4 bg-yellow-50 border-l-4 border-yellow-500 rounded-r-lg mt-4">
                                <h4 class="font-bold text-yellow-800 mb-2">Acción Opcional de Cierre</h4>
                                <div class="flex items-start space-x-3">
                                    <input id="ejecutar-cierre-check" type="checkbox" v-model="ejecutarCierre"
                                        class="h-5 w-5 mt-1 text-yellow-600 rounded border-gray-300 focus:ring-yellow-500">
                                    <div class="flex-1">
                                        <label for="ejecutar-cierre-check"
                                            class="font-medium text-gray-800 cursor-pointer">Cerrar este fondo después
                                            de la liquidación</label>
                                        <p class="text-xs text-gray-600 mt-1">
                                            Marca esta opción si esta caja chica no se renovará para el siguiente
                                            período.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-200 flex justify-end bg-gray-50">
                    <button @click="$emit('close')"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition-colors mr-3">Cancelar</button>
                    <button @click="confirmarAccion" :disabled="isConfirmButtonDisabled"
                        class="px-4 py-2 text-white rounded-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        :class="modo === 'reponer' ? 'bg-verde-bap hover:bg-verde-bap-dark' : 'bg-orange-500 hover:bg-orange-600'">
                        <span v-if="isLoading">Procesando...</span>
                        <span v-else>{{ confirmButtonText }}</span>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
