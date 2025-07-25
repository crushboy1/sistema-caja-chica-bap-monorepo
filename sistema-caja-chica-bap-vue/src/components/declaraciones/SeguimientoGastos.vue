<template>
    <div class="animate-fade-in-up space-y-6">
        <!-- Cabecera con Título y Acciones Principales -->
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 pb-4 border-b border-gray-200">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Mis Gastos Declarados</h2>
                <p class="text-sm text-gray-500 mt-1">Aquí puedes ver el estado de todos los gastos que has registrado y
                    tomar acciones.</p>
            </div>
            <!-- Botón para Re-consolidar DJ (se muestra condicionalmente) -->
            <transition name="fade-in-up">
                <button v-if="gastosSeleccionados.length > 0" @click="abrirModalReconsolidacion"
                    class="bg-verde-bap hover:bg-verde-bap-dark text-white font-bold py-2 px-4 rounded-lg shadow-md transition-all duration-300 transform hover:scale-105 flex items-center space-x-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    <span>Crear DJ Consolidada ({{ gastosSeleccionados.length }})</span>
                </button>
            </transition>
        </div>

        <!-- Barra de Filtros y Búsqueda -->
        <div class="bg-white/80 backdrop-blur-sm p-4 rounded-xl border border-gray-200 shadow-soft">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input type="text" v-model="filtros.busqueda" placeholder="Buscar por código o glosa..."
                    class="form-input rounded-lg border-gray-300 shadow-sm focus:border-verde-bap focus:ring-verde-bap transition">
                <select v-model="filtros.estado"
                    class="form-select rounded-lg border-gray-300 shadow-sm focus:border-verde-bap focus:ring-verde-bap transition">
                    <option value="">Todos los estados</option>
                    <option value="Pendiente de Aprobación">Pendiente de Aprobación</option>
                    <option value="Pendiente de Validación Contable">Pendiente de Validación Contable</option>
                    <option value="Pendiente de Validación DJ">Pendiente de Validación DJ</option>
                    <option value="Observado">Observado</option>
                    <option value="Contabilizado">Contabilizado</option>
                    <option value="Rechazado">Rechazado</option>
                </select>
                <button @click="resetearFiltros"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-medium py-2 px-4 rounded-lg transition-colors">
                    Limpiar Filtros
                </button>
            </div>
        </div>

        <!-- Contenedor de la Tabla -->
        <div class="bg-white rounded-xl shadow-strong overflow-hidden border border-gray-200">
            <!-- Estado de Carga -->
            <div v-if="cargando" class="p-10 text-center text-gray-500">
                <svg class="animate-spin h-8 w-8 text-verde-bap mx-auto mb-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 4V2A10 10 0 002 12h2a8 8 0 018-8z"></path>
                </svg>
                <p>Cargando tus gastos...</p>
            </div>

            <!-- Estado Vacío -->
            <div v-else-if="gastos.length === 0" class="p-10 text-center text-gray-500">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                <h3 class="font-semibold text-lg text-gray-700">No tienes gastos registrados</h3>
                <p class="text-sm mt-1">Cuando registres una nueva declaración, aparecerá aquí.</p>
            </div>

            <!-- Tabla de Gastos -->
            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="p-4"><input type="checkbox" @change="seleccionarTodos"
                                    :checked="todosSeleccionados"
                                    class="form-checkbox h-5 w-5 text-verde-bap rounded focus:ring-verde-bap-light">
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Código</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Glosa / Descripción del Gasto</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Monto</th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Fecha</th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Estado</th>
                            <th scope="col"
                                class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Acciones</th>
                            <th scope="col" class="relative px-6 py-3"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <transition-group name="gasto-list">
                            <tr v-for="gasto in gastosFiltrados" :key="gasto.id"
                                class="hover:bg-verde-bap-extralight transition-colors duration-200">
                                <td class="p-4 align-middle">
                                    <input v-if="esConsolidable(gasto)" type="checkbox" v-model="gastosSeleccionados"
                                        :value="gasto.id"
                                        class="form-checkbox h-5 w-5 text-verde-bap rounded focus:ring-verde-bap-light">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 align-middle">
                                    {{ gasto.codigo_gasto }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate align-middle"
                                    :title="gasto.glosa">{{ gasto.glosa }}</td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-semibold align-middle">
                                    {{ currencyFormatter.format(gasto.monto_total) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 align-middle">{{
                                    formatDate(gasto.created_at) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center align-middle">
                                    <span :class="getClassesForAuditoriaBadge(gasto.estado)">{{ gasto.estado }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium align-middle">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button @click="abrirModalDetalle(gasto)"
                                            class="p-2 rounded-full hover:bg-blue-100 text-blue-600 transition-colors"
                                            title="Ver Detalle">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                        </button>
                                        <button v-if="gasto.estado === 'Observado'" @click="abrirModalCorreccion(gasto)"
                                            class="p-2 rounded-full hover:bg-orange-100 text-orange-600 transition-colors"
                                            title="Corregir Gasto">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L16.732 3.732z">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </transition-group>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modales (placeholders) -->
        <GastoDetalleModal v-if="mostrarModalDetalle" :gasto="gastoSeleccionado" :mostrar="mostrarModalDetalle"
            @close="mostrarModalDetalle = false" />
        <CorregirGastoModal v-if="mostrarModalCorreccion" :gasto="gastoSeleccionado"
            @close="mostrarModalCorreccion = false" @gasto-actualizado="handleGastoActualizado" />
        <ReconsolidarDjModal v-if="mostrarModalReconsolidar" :gastos-a-consolidar="gastosParaReconsolidar"
            @close="mostrarModalReconsolidar = false" @dj-creada="handleDjReconsolidada" />
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { getClassesForAuditoriaBadge } from '@/utils/statusStyles.js';
import GastoDetalleModal from './modals/GastoDetalleModal.vue';
import CorregirGastoModal from './modals/CorregirGastoModal.vue';
import ReconsolidarDjModal from './modals/ReconsolidarDjModal.vue';

// --- ESTADO REACTIVO ---
const gastos = ref([]);
const cargando = ref(true);
const filtros = ref({ busqueda: '', estado: '' });
const gastosSeleccionados = ref([]);
const gastoSeleccionado = ref(null);
const mostrarModalDetalle = ref(false);
const mostrarModalCorreccion = ref(false);
const mostrarModalReconsolidar = ref(false);

const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

// --- LÓGICA DE DATOS ---
onMounted(() => {
    fetchGastos();
});

const fetchGastos = async () => {
    cargando.value = true;
    try {
        // Usamos el endpoint que trae solo los gastos del usuario autenticado.
        const response = await api.get('/v1/mis-gastos'); // O '/v1/mis-gastos' si existe
        gastos.value = response.data;
    } catch (error) {
        console.error("Error al obtener los gastos:", error);
        Swal.fire('Error', 'No se pudieron cargar tus gastos.', 'error');
    } finally {
        cargando.value = false;
    }
};

// --- PROPIEDADES COMPUTADAS ---
const gastosFiltrados = computed(() => {
    let gastosFiltrados = [...gastos.value];
    if (filtros.value.busqueda) {
        const busquedaLower = filtros.value.busqueda.toLowerCase();
        gastosFiltrados = gastosFiltrados.filter(g =>
            g.codigo_gasto.toLowerCase().includes(busquedaLower) ||
            g.glosa.toLowerCase().includes(busquedaLower)
        );
    }
    if (filtros.value.estado) {
        gastosFiltrados = gastosFiltrados.filter(g => g.estado === filtros.value.estado);
    }
    return gastosFiltrados;
});

const todosSeleccionados = computed(() => {
    const consolidables = gastosFiltrados.value.filter(esConsolidable);
    return consolidables.length > 0 && gastosSeleccionados.value.length === consolidables.length;
});
const gastosParaReconsolidar = computed(() => {
    return gastos.value.filter(g => gastosSeleccionados.value.includes(g.id));
});
// --- MÉTODOS ---
const resetearFiltros = () => {
    filtros.value = { busqueda: '', estado: '' };
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);

    date.setDate(date.getDate() + 1);
    const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
    return date.toLocaleDateString('es-PE', options);
};

const esConsolidable = (gasto) => {
    const estadosConsolidables = ['Pendiente de Aprobación', 'Pendiente de Validación Contable', 'Observado'];
    return estadosConsolidables.includes(gasto.estado) && !gasto.id_dj_consolidada && gasto.es_declaracion_jurada;
};

const seleccionarTodos = (event) => {
    if (event.target.checked) {
        gastosSeleccionados.value = gastosFiltrados.value
            .filter(esConsolidable)
            .map(g => g.id);
    } else {
        gastosSeleccionados.value = [];
    }
};

const abrirModalReconsolidacion = () => {
    mostrarModalReconsolidar.value = true;
};
const abrirModalDetalle = (gasto) => {
    gastoSeleccionado.value = gasto;
    mostrarModalDetalle.value = true;
};

const abrirModalCorreccion = (gasto) => {
    gastoSeleccionado.value = gasto;
    mostrarModalCorreccion.value = true;
};

const handleGastoActualizado = () => {
    mostrarModalCorreccion.value = false;
    fetchGastos();
};
const handleDjReconsolidada = () => {
    mostrarModalReconsolidar.value = false;
    gastosSeleccionados.value = []; // Limpiar selección
    fetchGastos(); // Recargar la lista para mostrar los nuevos estados
    Swal.fire('¡Éxito!', 'La Declaración Jurada ha sido consolidada y enviada para validación.', 'success');
};
</script>

<style scoped>
/* Transición para la aparición de las filas de la tabla */
.gasto-list-enter-active,
.gasto-list-leave-active {
    transition: all 0.4s ease-out;
}

.gasto-list-enter-from,
.gasto-list-leave-to {
    opacity: 0;
    transform: translateY(20px);
}
</style>
