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
                <button v-if="puedeReconsolidarDJ" @click="abrirModalReconsolidacion"
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

        <!-- Panel de Contadores Globales -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Card Monto Total -->
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 ease-in-out p-6 flex flex-col justify-between border border-gray-200">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-lg bg-verde-bap/10 text-verde-bap flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8v.01M12 16v.01M19 12h3m-3 0l1.5-1.5M19 12l1.5 1.5" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wider">Monto Total</p>
                        </div>
                    </div>
                    <p class="text-4xl font-bold text-verde-bap-dark mt-3">
                        {{ currencyFormatter.format(contadores.montos.total) }}
                    </p>
                </div>
                <p class="text-xs text-gray-400 mt-4">Suma de todos los gastos en la vista actual.</p>
            </div>

            <!-- Card # Gastos -->
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow duration-300 ease-in-out p-6 flex flex-col justify-between border border-gray-200">
                <div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-lg bg-azul-bap/10 text-azul-bap flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h4l3 10 4-18 3 8h4" />
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-600 uppercase tracking-wider"># Gastos</p>
                        </div>
                    </div>
                    <p class="text-4xl font-bold text-azul-bap mt-3">
                        {{ contadores.estados.total }}
                    </p>
                </div>
                <p class="text-xs text-gray-400 mt-4">Cantidad total de gastos en la vista actual.</p>
            </div>
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

            <!-- Tabla o Mensaje de Vacío (envuelto en un solo bloque condicional) -->
            <div v-else class="overflow-x-auto">
                <div class="mb-4 text-sm text-gray-600 text-center" v-if="totalPages > 0">
                    Mostrando <strong>{{ (currentPage - 1) * itemsPerPage + 1 }}</strong> -
                    <strong>{{ Math.min(currentPage * itemsPerPage, totalItems) }}</strong>
                    de <strong>{{ totalItems }}</strong> gastos
                </div>
                <table class="min-w-full divide-y  divide-gray-200" style="table-layout: fixed; min-width: 900px;">
                    <thead class="bg-gray-50 ">
                        <tr>
                            <!-- Columna de selección (checkbox maestro) -->
                            <th scope="col"
                                class="px-3 py-2 text-center text-xs font-bold text-gray-500 uppercase tracking-wider w-10">
                                <input
                                    type="checkbox"
                                    v-model="todosSeleccionados"
                                    :disabled="gastosFiltrados.filter(esConsolidable).length === 0"
                                    class="rounded border-gray-300 text-verde-bap focus:ring-verde-bap cursor-pointer"
                                    aria-label="Seleccionar todos los gastos consolidables del fondo visible"
                                />
                            </th>
                            <th scope="col"
                                class="px-3 py-2 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Código</th>
                            <th scope="col"
                                class="px-3 py-2 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Glosa / Descripción del Gasto
                            </th>
                            <th scope="col"
                                class="px-3 py-2 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Monto
                            </th>
                            <th scope="col"
                                class="px-3 py-2 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Fecha
                            </th>
                            <th scope="col"
                                class="px-3 py-2 text-center text-xs font-bold text-gray-500 uppercase tracking-wider w-42">
                                Estado
                            </th>
                            <th scope="col"
                                class="px-3 py-2 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <!-- CAMBIO: tbody es ahora el tag de transition-group -->
                    <transition-group name="gasto-list" tag="tbody" class="bg-white divide-y divide-gray-200">
                        <tr v-for="gasto in gastosPaginados" :key="gasto.id"
                            class="hover:bg-verde-bap-extralight transition-colors duration-200">
                            <!-- Checkbox por fila -->
                            <td class="px-4 py-4 whitespace-nowrap text-center align-middle">
                                <input
                                    type="checkbox"
                                    :disabled="esCheckboxDeshabilitado(gasto)"
                                    :checked="gastosSeleccionados.includes(gasto.id)"
                                    @change="onToggleSeleccion(gasto, $event.target.checked)"
                                    class="rounded border-gray-300 text-verde-bap focus:ring-verde-bap cursor-pointer disabled:opacity-40"
                                    :aria-label="`Seleccionar gasto ${gasto.codigo_gasto}`"
                                />
                            </td>
                            <td
                                class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium text-gray-900 align-middle">
                                {{ gasto.codigo_gasto }}</td>
                            <td class="px-4 py-4  text-center text-sm text-gray-600 truncate align-middle"
                                :title="gasto.glosa">
                                {{ gasto.glosa }}</td>
                            <td
                                class="px-4 py-4 whitespace-nowrap text-center text-sm text-gray-800 font-semibold align-middle">
                                {{ currencyFormatter.format(gasto.monto_total) }}</td>
                            <td class="px-4 py-4 whitespace-nowrap text-center text-sm text-gray-500 align-middle">{{
                                formatDate(gasto.created_at) }}</td>
                            <td
                                class="py-4 px-4 flex justify-center items-center text-center whitespace-nowrap align-middle">
                                <span :class="getClassesForAuditoriaBadge(gasto.estado)">{{ gasto.estado }}</span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium align-middle">
                                <div class="flex items-center justify-center space-x-2">
                                    <button @click="abrirModalDetalle(gasto)"
                                        class="p-2 rounded-full  hover:bg-blue-100 text-blue-600 transition-colors"
                                        title="Ver Detalle" aria-label="Ver detalle del gasto">
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
                                        title="Corregir Gasto" aria-label="Corregir gasto observado">
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
                </table>
                <!-- Mensaje de "Sin resultados" si los filtros no devuelven nada -->
                <div v-if="gastosPaginados.length === 0 && !cargando" class="p-10 text-center text-gray-500">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <h3 class="font-semibold text-lg text-gray-700">No se encontraron gastos</h3>
                    <p class="text-sm mt-1">Ajusta tus filtros o registra nuevos gastos.</p>
                </div>
            </div>
        </div>

        <!-- Controles de Paginación -->
        <div class="mt-6" v-if="totalPages > 0">
            <div class="flex justify-center items-center space-x-1">
                <button @click="paginaAnterior" :disabled="currentPage === 1"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center"
                    :class="currentPage === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Anterior
                </button>
                <button v-for="pagina in paginasVisibles" :key="pagina" @click="irAPagina(pagina)"
                    class="w-10 h-10 rounded-lg text-sm font-medium transition-colors duration-200 border"
                    :class="[
                        currentPage === pagina ? 'bg-verde-bap text-white border-verde-bap-dark shadow-md' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-200',
                        pagina === '...' ? 'cursor-default' : ''
                    ]">
                    {{ pagina }}
                </button>
                <button @click="paginaSiguiente" :disabled="currentPage === totalPages"
                    class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center"
                    :class="currentPage === totalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'">
                    Siguiente
                    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
            <div class="text-center text-sm text-gray-500 mt-2">
                Página {{ currentPage }} de {{ totalPages }}
            </div>
        </div>

        <!-- Modales -->
        <GastoDetalleModal v-if="mostrarModalDetalle" :gasto="gastoSeleccionado" :mostrar="mostrarModalDetalle"
            @close="mostrarModalDetalle = false" />
        <CorregirGastoModal v-if="mostrarModalCorreccion && gastoSeleccionado" :mostrar="mostrarModalCorreccion"
            :gasto="gastoSeleccionado" :usuarioActual="usuarioActual" @close="mostrarModalCorreccion = false"
            @gasto-actualizado="handleGastoActualizado" />
        <ReconsolidarDjModal v-if="mostrarModalReconsolidar" :gastos-a-consolidar="gastosParaReconsolidar"
            :usuarioActual="usuarioActual" @close="mostrarModalReconsolidar = false"
            @dj-creada="handleDjReconsolidada" />
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { getClassesForAuditoriaBadge } from '@/utils/statusStyles.js';
import GastoDetalleModal from './modals/GastoDetalleModal.vue';
import CorregirGastoModal from './modals/CorregirGastoModal.vue';
import ReconsolidarDjModal from './modals/ReconsolidarDjModal.vue';

// --- PROPS ---
const props = defineProps({
    usuarioActual: {
        type: Object,
        required: true
    }
});

// --- ESTADO REACTIVO ---
const gastos = ref([]); // Lista completa de gastos del usuario
const cargando = ref(true);
const filtros = ref({ busqueda: '', estado: '' });
const gastosSeleccionados = ref([]); // IDs de gastos seleccionados para re-consolidar
const fondoActivoParaConsolidacion = ref(null);
const fondoActivoDetalles = ref(null); // Detalles del fondo de caja chica activo
// Modales
const gastoSeleccionado = ref(null); // Gasto actual para ver detalle/corrección
const mostrarModalDetalle = ref(false);
const mostrarModalCorreccion = ref(false);
const mostrarModalReconsolidar = ref(false);

const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

// --- LÓGICA DE DATOS Y COMPUTADAS ADICIONALES ---
const fetchFondoDetalles = async (fondoId) => {
    if (!fondoId) {
        fondoActivoDetalles.value = null;
        return;
    }
    try {
        const response = await api.get(`/v1/fondos/${fondoId}`);
        fondoActivoDetalles.value = response.data.data; // Asumiendo que la API devuelve el objeto del fondo en `data`
    } catch (error) {
        console.error(`Error al obtener los detalles del fondo ${fondoId}:`, error);
        fondoActivoDetalles.value = null;
        Swal.fire('Error', 'No se pudieron cargar los detalles del fondo de caja chica.', 'error');
    }
};

// --- LÓGICA DE DATOS ---
onMounted(() => {
    fetchGastos();
});

const fetchGastos = async () => {
    cargando.value = true;
    try {
        // Endpoint para obtener solo los gastos del usuario autenticado
        const response = await api.get('/v1/mis-gastos');
        gastos.value = response.data; // Asume que el backend devuelve un array plano de objetos Gasto
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

    // Filtro por búsqueda de texto (código o glosa)
    if (filtros.value.busqueda) {
        const busquedaLower = filtros.value.busqueda.toLowerCase();
        gastosFiltrados = gastosFiltrados.filter(g =>
            g.codigo_gasto?.toLowerCase().includes(busquedaLower) ||
            g.glosa?.toLowerCase().includes(busquedaLower)
        );
    }

    // Filtro por estado
    if (filtros.value.estado) {
        gastosFiltrados = gastosFiltrados.filter(g => g.estado === filtros.value.estado);
    }

    return gastosFiltrados;
});

// Paginación al estilo Reporte de Gastos
const currentPage = ref(1);
const itemsPerPage = ref(10);
const totalItems = computed(() => gastosFiltrados.value.length);
const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage.value));
const gastosPaginados = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    return gastosFiltrados.value.slice(start, start + itemsPerPage.value);
});
const paginasVisibles = computed(() => {
    if (totalPages.value <= 7) {
        return Array.from({ length: totalPages.value }, (_, i) => i + 1);
    }
    if (currentPage.value < 5) {
        return [1, 2, 3, 4, 5, '...', totalPages.value];
    }
    if (currentPage.value > totalPages.value - 4) {
        return [1, '...', totalPages.value - 4, totalPages.value - 3, totalPages.value - 2, totalPages.value - 1, totalPages.value];
    }
    return [1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value];
});
const irAPagina = (pagina) => {
    if (typeof pagina === 'number' && pagina >= 1 && pagina <= totalPages.value) {
        currentPage.value = pagina;
    }
};
const paginaAnterior = () => { if (currentPage.value > 1) currentPage.value--; };
const paginaSiguiente = () => { if (currentPage.value < totalPages.value) currentPage.value++; };

const contadores = computed(() => {
    const initial = {
        estados: {
            total: 0,
        },
        montos: {
            total: 0,
            proyectado: 0,
            original: 0,
        }
    };

    return gastosFiltrados.value.reduce((acc, gasto) => {
        const monto = parseFloat(gasto.monto_total) || 0;
        const montoProyectado = parseFloat(gasto.monto_proyectado_original) || monto;
        const montoOriginal = montoProyectado;

        acc.estados.total++;
        acc.montos.total += monto;
        acc.montos.proyectado += montoProyectado;
        acc.montos.original += montoOriginal;
        return acc;
    }, initial);
});


// Determina si todos los gastos consolidables visibles están seleccionados
const todosSeleccionados = computed({
    get() {
        let targetFondoId = fondoActivoParaConsolidacion.value;
        // Si no hay fondo activo, intenta determinarlo desde los filtros
        if (!targetFondoId) {
            const primerConsolidable = gastosFiltrados.value.find(esConsolidable);
            if (primerConsolidable) {
                targetFondoId = primerConsolidable.id_fondo_efectivo;
            }
        }

        const consolidablesDelFondo = gastosFiltrados.value.filter(g =>
            esConsolidable(g) && g.id_fondo_efectivo === targetFondoId
        );

        if (consolidablesDelFondo.length === 0) return false;

        return consolidablesDelFondo.every(g => gastosSeleccionados.value.includes(g.id));
    },
    set(value) {
        let targetFondoId = fondoActivoParaConsolidacion.value;

        if (value) {
            // Si se marca y no hay fondo activo, se determina a partir del primer gasto consolidable
            if (!targetFondoId) {
                const primerGasto = gastosFiltrados.value.find(esConsolidable);
                if (primerGasto) {
                    targetFondoId = primerGasto.id_fondo_efectivo;
                }
            }

            if (targetFondoId) {
                const idsParaSeleccionar = gastosFiltrados.value
                    .filter(g => esConsolidable(g) && g.id_fondo_efectivo === targetFondoId)
                    .map(g => g.id);
                // Reemplaza la selección actual con solo los del fondo target
                gastosSeleccionados.value = idsParaSeleccionar;
            }
        } else {
            // Si se desmarca, limpia toda la selección
            gastosSeleccionados.value = [];
        }
    }
});

// Prepara la lista de gastos a pasar al modal de re-consolidación
const gastosParaReconsolidar = computed(() => {
    return gastos.value.filter(g => gastosSeleccionados.value.includes(g.id));
});

// Habilita el botón "Crear DJ Consolidada"
const puedeReconsolidarDJ = computed(() => {
    return gastosSeleccionados.value.length >= 1 &&
        gastosParaReconsolidar.value.every(g => esConsolidable(g));
});

// --- MÉTODOS ---
const resetearFiltros = () => {
    filtros.value = { busqueda: '', estado: '' };
};

// Maneja el toggle de selección por fila
const onToggleSeleccion = (gasto, checked) => {
    if (checked) {
        if (!esConsolidable(gasto)) return;
        if (gastosSeleccionados.value.includes(gasto.id)) return;
        // Si ya hay un fondo activo, no permitir seleccionar de otro fondo (los inputs ya están deshabilitados)
        if (fondoActivoParaConsolidacion.value && gasto.id_fondo_efectivo !== fondoActivoParaConsolidacion.value) return;
        gastosSeleccionados.value = [...gastosSeleccionados.value, gasto.id];
        if (!fondoActivoParaConsolidacion.value) {
            fondoActivoParaConsolidacion.value = gasto.id_fondo_efectivo;
        }
    } else {
        gastosSeleccionados.value = gastosSeleccionados.value.filter(id => id !== gasto.id);
        // Cuando se quite el último, el watcher limpiará el fondo activo
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    // Formatear la fecha sin añadir un día extra
    const options = { year: 'numeric', month: '2-digit', day: '2-digit' };
    return date.toLocaleDateString('es-PE', options);
};

// Lógica para determinar si un gasto es elegible para ser seleccionado para re-consolidación
const esConsolidable = (gasto) => {
    const estadosValidos = [
        'Pendiente de Aprobación',
        'Pendiente de Validación Contable'
    ];
    const noEstaEnDj = !gasto.id_dj_consolidada;
    const esTipoDj = gasto.es_declaracion_jurada;
    const esDelUsuario = gasto.id_registrador === props.usuarioActual?.id;

    return estadosValidos.includes(gasto.estado) && noEstaEnDj && esTipoDj && esDelUsuario;
};
const esCheckboxDeshabilitado = (gasto) => {
    // Siempre deshabilita si el gasto no es consolidable en primer lugar
    if (!esConsolidable(gasto)) return true;
    // Si no hay nada seleccionado, nada está deshabilitado (excepto los no consolidables)
    if (gastosSeleccionados.value.length === 0) return false;
    // Si hay selección, deshabilita los de otros fondos
    return gasto.id_fondo_efectivo !== fondoActivoParaConsolidacion.value;
};

const abrirModalReconsolidacion = () => {
    if (gastosParaReconsolidar.value.length === 0) {
        Swal.fire('Sin Selección', 'Debes seleccionar al menos un gasto tipo DJ para continuar.', 'warning');
        return;
    }
    const primerGasto = gastosParaReconsolidar.value[0];
    const primerEstado = primerGasto.estado;
    const primerFondoId = primerGasto.id_fondo_efectivo;
    const todosMismoEstado = gastosParaReconsolidar.value.every(g => g.estado === primerEstado);
    if (!todosMismoEstado) {
        Swal.fire('Estados Inconsistentes', 'Todos los gastos seleccionados deben tener el mismo estado.', 'error');
        return;
    }
    const todosMismoFondo = gastosParaReconsolidar.value.every(g => g.id_fondo_efectivo === primerFondoId);
    if (!todosMismoFondo) {
        Swal.fire('Fondos Mixtos', 'No puedes consolidar gastos que pertenecen a diferentes fondos de caja chica.', 'error');
        return;
    }

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

// Manejador después de que un gasto es actualizado en el modal de corrección
const handleGastoActualizado = () => {
    mostrarModalCorreccion.value = false;
    fetchGastos(); // Recargar la lista para reflejar el cambio de estado
    gastosSeleccionados.value = []; // Limpiar selección por si acaso
};

// Manejador después de que una DJ es re-consolidada en el modal
const handleDjReconsolidada = () => {
    mostrarModalReconsolidar.value = false;
    gastosSeleccionados.value = []; // Limpiar selección después de la consolidación
    fetchGastos(); // Recargar la lista para mostrar los nuevos estados de los gastos
};

// --- WATCHERS ---
// Observa cambios en los filtros para recargar los gastos
watch(filtros, () => {
    gastosSeleccionados.value = [];
    currentPage.value = 1; // Resetear paginación al cambiar filtros
}, { deep: true }); // Observación profunda para detectar cambios en las propiedades del objeto filtros

watch(gastosSeleccionados, (nuevosSeleccionados, viejosSeleccionados) => {
    // Si la selección pasa de 0 a 1, se define el fondo activo.
    if (viejosSeleccionados.length === 0 && nuevosSeleccionados.length > 0) {
        const primerGastoSeleccionado = gastos.value.find(g => g.id === nuevosSeleccionados[0]);
        if (primerGastoSeleccionado && esConsolidable(primerGastoSeleccionado)) {
            fondoActivoParaConsolidacion.value = primerGastoSeleccionado.id_fondo_efectivo;
        }
    } else if (nuevosSeleccionados.length === 0) {
        // Si la selección se vacía, se resetea el fondo activo.
        fondoActivoParaConsolidacion.value = null;
    }
}, { deep: true });

// Observa cambios en el fondo activo para buscar sus detalles
watch(fondoActivoParaConsolidacion, (nuevoFondoId) => {
    fetchFondoDetalles(nuevoFondoId);
});
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

/* Asegura que los elementos que salen no afecten el layout antes de desaparecer */
.gasto-list-leave-active {
    position: absolute;
}

.gasto-list-move {
    transition: transform 0.4s ease;
}

/* Estilos mejorados para la tabla responsive */
table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    vertical-align: middle;
}

/* Responsive: en pantallas pequeñas, permitir scroll horizontal */
@media (max-width: 768px) {
    .overflow-x-auto {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    table {
        min-width: 700px;
    }
}

/* Estilos para el truncate en la glosa */
.max-w-xs {
    max-width: 20rem;
}

.truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
