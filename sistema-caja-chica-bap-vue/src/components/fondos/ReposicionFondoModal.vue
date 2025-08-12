<script setup>
import { ref, watch, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { X, Loader2 } from 'lucide-vue-next';
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
const summary = ref(null);
const comprobanteFile = ref(null);
const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const ejecutarCierre = ref(false);
// Determina el modo del modal ('reponer', 'devolver', o 'ninguno').
const modo = computed(() => summary.value?.accion_requerida || 'ninguna');

const modalTitle = computed(() => {
    if (modo.value === 'reponer') return 'Reposición de Excedente de Fondo';
    if (modo.value === 'devolver') return 'Registro de Devolución de Fondo';
    return 'Estado y Cierre de Fondo';
});

const confirmButtonText = computed(() => {
    if (modo.value === 'reponer') return 'Confirmar Reposición';
    if (modo.value === 'devolver') return 'Registrar Devolución';
    if (modo.value === 'ninguna' && ejecutarCierre.value) return 'Confirmar Cierre Definitivo';
    return 'Acción no disponible';
});

const isConfirmButtonDisabled = computed(() => {
    if (isLoading.value || modo.value === 'bloqueado') return true;
    if (modo.value === 'reponer' && !comprobanteFile.value) return true;
    if (modo.value === 'ninguna' && !ejecutarCierre.value) return true;
    return false;
});
// --- MÉTODOS ---
const fetchReposicionSummary = async (fondoId) => {
    if (!fondoId) {
        summary.value = null;
        return;
    }
    cargandoResumen.value = true;
    summary.value = null;
    try {
        const response = await api.get(`/v1/fondos-efectivo/${fondoId}/reposicion-summary`);
        summary.value = response.data;
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
        const montoAReponer = parseFloat(summary.value.monto_a_reponer);
        const saldoActual = parseFloat(summary.value.saldo_disponible_actual);
        const saldoPostLiquidacion = saldoActual + montoAReponer;
        const nuevoSaldoProyectado = esCierreDefinitivo ? 0 : parseFloat(summary.value.monto_asignado);

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
        const montoADevolver = parseFloat(summary.value.monto_a_devolver);
        const saldoActual = parseFloat(summary.value.saldo_disponible_actual);
        const saldoPostLiquidacion = saldoActual - montoADevolver;
        const nuevoSaldoProyectado = esCierreDefinitivo ? 0 : parseFloat(summary.value.monto_asignado);

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

        const response = await api.post(`/v1/fondos-efectivo/${props.fondoProp.id_fondo}/devolver`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
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
    } else {
        summary.value = null;
        comprobanteFile.value = null;
        ejecutarCierre.value = false;
    }
}, { immediate: true });
</script>

<template>
    <Transition name="modal-fade">
        <div v-if="mostrar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm">
            <div
                class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-auto overflow-hidden transform transition-all">
                <div
                    class="flex justify-between items-center p-4 border-b border-gray-200 bg-verde-bap-dark text-white">
                    <h3 class="text-xl font-semibold">{{ modalTitle }}</h3>
                    <button @click="$emit('close')"
                        class="p-2 rounded-full text-white/70 hover:bg-white/20 transition-colors">
                        <X class="h-6 w-6" />
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

                    <div v-else-if="summary" class="space-y-4 animate-fade-in">
                        <div v-if="modo === 'reponer'">
                            <div class="p-4 bg-gris-bap-light border-l-4 border-verde-bap rounded-r-lg">
                                <h4 class="font-bold text-gris-bap-dark mb-2">Resumen de Reposición por Excedente</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <span class="text-gris-bap">Monto Aprobado Original:</span>
                                    <span class="font-semibold text-right">{{
                                        currencyFormatter.format(summary.monto_asignado) }}</span>
                                    <span class="text-gris-bap">Total Gastado (Contabilizado):</span>
                                    <span class="font-semibold text-right">{{
                                        currencyFormatter.format(summary.total_gastado_contabilizado)
                                    }}</span>
                                    <span class="text-gris-bap">Saldo Actual:</span>
                                    <span class="font-semibold text-right text-rojo-bap">{{
                                        currencyFormatter.format(summary.saldo_disponible_actual) }}</span>
                                    <hr class="col-span-2 my-1 border-gray-300">
                                    <span class="text-gris-bap-dark font-bold">Monto a Reembolsar (Excedente):</span>
                                    <span class="font-bold text-verde-bap text-right text-lg">{{
                                        currencyFormatter.format(summary.monto_a_reponer) }}</span>
                                </div>
                            </div>
                            <div>
                                <label for="comprobante-reposicion"
                                    class="block text-sm font-medium text-gris-bap-dark mb-1">Adjuntar Comprobante de
                                    Reembolso <span class="text-rojo-bap">*</span></label>
                                <input type="file" id="comprobante-reposicion" @change="handleFileChange"
                                    accept=".pdf,.jpg,.jpeg,.png" class="file-input-style" />
                                <p v-if="comprobanteFile" class="text-xs text-gris-bap mt-1">Archivo: {{
                                    comprobanteFile.name }}</p>
                            </div>
                        </div>

                        <div v-else-if="modo === 'devolver'">
                            <div class="p-4 bg-gris-bap-light border-l-4 border-naranja-bap rounded-r-lg">
                                <h4 class="font-bold text-gris-bap-dark mb-2">Resumen de Devolución de Sobrante</h4>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <span class="text-gris-bap">Monto Aprobado Original:</span>
                                    <span class="font-semibold text-right">{{
                                        currencyFormatter.format(summary.monto_asignado) }}</span>
                                    <span class="text-gris-bap">Total Gastado (Contabilizado):</span>
                                    <span class="font-semibold text-right">{{
                                        currencyFormatter.format(summary.total_gastado_contabilizado)
                                        }}</span>
                                    <span class="text-gris-bap">Saldo Disponible Actual:</span>
                                    <span class="font-semibold text-right">{{
                                        currencyFormatter.format(summary.saldo_disponible_actual) }}</span>
                                    <hr class="col-span-2 my-1 border-gray-300">
                                    <span class="text-gray-800 font-bold">Monto a Devolver (Sobrante):</span>
                                    <span class="font-bold text-naranja-bap-dark text-right text-lg">{{
                                        currencyFormatter.format(summary.monto_a_devolver) }}</span>
                                </div>
                            </div>
                            <div>
                                <label for="comprobante-devolucion"
                                    class="block text-sm font-medium text-gris-bap-dark mb-1">Adjuntar Comprobante de
                                    Devolución (Opcional)</label>
                                <input type="file" id="comprobante-devolucion" @change="handleFileChange"
                                    accept=".pdf,.jpg,.jpeg,.png" class="file-input-style" />
                                <p v-if="comprobanteFile" class="text-xs text-gris-bap mt-1">Archivo: {{
                                    comprobanteFile.name }}</p>
                            </div>
                        </div>
                        <div v-if="summary.gastos_contabilizados && summary.gastos_contabilizados.length > 0"
                            class="border-t pt-4">
                            <details class="group">
                                <summary class="flex justify-between items-center font-medium cursor-pointer list-none">
                                    <span class="text-gray-800">Ver Desglose de Gastos Contabilizados ({{
                                        summary.gastos_contabilizados.length }})</span>
                                    <span class="transition group-open:rotate-180">
                                        <svg fill="none" height="24" shape-rendering="geometricPrecision"
                                            stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                            stroke-width="1.5" viewBox="0 0 24 24" width="24">
                                            <path d="M6 9l6 6 6-6"></path>
                                        </svg>
                                    </span>
                                </summary>
                                <div class="mt-4 max-h-48 overflow-y-auto custom-scrollbar pr-2">
                                    <table class="min-w-full text-sm">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th class="px-3 py-2 text-left font-semibold">Código</th>
                                                <th class="px-3 py-2 text-left font-semibold">Glosa/Descripcion del
                                                    Gasto</th>
                                                <th class="px-3 py-2 text-right font-semibold">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y">
                                            <tr v-for="gasto in summary.gastos_contabilizados" :key="gasto.id">
                                                <td class="px-3 py-2 whitespace-nowrap">{{ gasto.codigo_gasto }}</td>
                                                <td class="px-3 py-2">{{ gasto.glosa }}</td>
                                                <td class="px-3 py-2 text-right font-mono">{{
                                                    currencyFormatter.format(gasto.monto_total) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        </div>
                        <div class="p-4 rounded-lg" :class="{
                            'bg-estado-error-bg border-l-4 border-rojo-bap text-estado-error-text': summary.accion_requerida === 'bloqueado',
                            'bg-estado-info-bg border-l-4 border-azul-bap text-estado-info-text': summary.accion_requerida === 'ninguna',
                            'bg-estado-alerta-bg border-l-4 border-amarillo-bap text-estado-alerta-text': summary.accion_requerida === 'devolver' || summary.accion_requerida === 'reponer'
                        }">
                            <p class="font-bold">
                                <span v-if="summary.accion_requerida === 'bloqueado'">Acción Bloqueada</span>
                                <span v-else-if="summary.accion_requerida === 'ninguna'">Estado del Fondo</span>
                                <span v-else-if="summary.accion_requerida === 'devolver'">Devolución Requerida</span>
                                <span v-else-if="summary.accion_requerida === 'reponer'">Reposición Requerida</span>
                            </p>
                            <p class="text-sm">{{ summary.mensaje_estado }}</p>
                        </div>

                        <div v-if="modo !== 'bloqueado'">
                            <div v-if="props.fondoProp && props.fondoProp.tiene_cierre_aprobado"
                                class="p-4 bg-estado-error-bg border-l-4 border-rojo-bap rounded-r-lg mt-4">
                                <h4 class="font-semibold text-rojo-bap-dark text-sm">Acción de Cierre Definitivo</h4>
                                <p class="text-xs text-gray-700 mt-1">Este fondo tiene una solicitud de cierre aprobada.
                                    Al liquidar el saldo, se cerrará permanentemente.</p>
                            </div>
                            <div v-else
                                class="p-4 bg-estado-alerta-bg border-l-4 border-amarillo-bap-dark rounded-r-lg mt-4">
                                <h4 class="font-bold text-estado-alerta-text mb-2">Acción Opcional de Cierre</h4>
                                <div class="flex items-start space-x-3">
                                    <input id="ejecutar-cierre-check" type="checkbox" v-model="ejecutarCierre"
                                        class="h-5 w-5 mt-1 text-amarillo-bap-dark rounded border-gray-300 focus:ring-amarillo-bap-dark">
                                    <div class="flex-1">
                                        <label for="ejecutar-cierre-check"
                                            class="font-medium text-gris-bap-dark cursor-pointer">Cerrar este fondo
                                            después de la liquidación</label>
                                        <p class="text-xs text-gris-bap mt-1">Marca esta opción si esta caja chica no se
                                            renovará para el siguiente período.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <footer class="p-4 border-t flex justify-end bg-gray-50">
                    <button @click="$emit('close')" class="btn-secondary mr-3">Cancelar</button>
                    <button @click="confirmarAccion" :disabled="isConfirmButtonDisabled" class="btn-primary" :class="{
                        'bg-verde-bap hover:bg-verde-bap-dark': modo === 'reponer' || (modo === 'ninguna' && ejecutarCierre),
                        'bg-naranja-bap hover:bg-naranja-bap-dark': modo === 'devolver'
                    }">
                        <span v-if="isLoading">Procesando...</span>
                        <span v-else>{{ confirmButtonText }}</span>
                    </button>
                </footer>
            </div>
        </div>
    </Transition>
</template>
<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: #f1f5f9;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 2px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.file-input-style {
    @apply block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-verde-bap-light file:text-verde-bap-dark hover:file:bg-verde-bap/30;
}

.form-label {
    @apply block text-sm font-medium text-gris-bap-dark mb-1;
}

.btn-primary {
    @apply bg-verde-bap hover:bg-verde-bap-dark text-white font-bold py-2 px-4 rounded-lg transition-colors shadow-md flex items-center;
}

.btn-secondary {
    @apply bg-gris-bap-light hover:bg-gray-300 text-gris-bap-dark font-bold py-2 px-4 rounded-lg transition-colors shadow-md flex items-center;
}
</style>