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
                <table class="min-w-full divide-y  divide-gray-200" style="table-layout: fixed; min-width: 900px;">
                    <thead class="bg-gray-50 ">
                        <tr>
                            <th scope="col" class="p-4 w-12 ">
                                <input type="checkbox" @change="seleccionarTodos" :checked="todosSeleccionados"
                                    class="form-checkbox h-5 w-5 text-verde-bap rounded focus:ring-verde-bap-light">
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
                        <tr v-for="gasto in gastosFiltrados" :key="gasto.id"
                            class="hover:bg-verde-bap-extralight transition-colors duration-200">
                            <td class="p-4 align-middle">
                                <input v-if="esConsolidable(gasto)" type="checkbox" v-model="gastosSeleccionados"
                                    :value="gasto.id"
                                    class="form-checkbox h-5 w-5 text-verde-bap rounded focus:ring-verde-bap-light">
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
                </table>
                <!-- Mensaje de "Sin resultados" si los filtros no devuelven nada -->
                <div v-if="gastosFiltrados.length === 0 && !cargando" class="p-10 text-center text-gray-500">
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
import { ref, onMounted, computed, watch, onUpdated } from 'vue';
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
// Modales
const gastoSeleccionado = ref(null); // Gasto actual para ver detalle/corrección
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

// Determina si todos los gastos consolidables visibles están seleccionados
const todosSeleccionados = computed({
    get() {
        // Si no hay fondo activo, no puede estar marcado
        if (!fondoActivoParaConsolidacion.value) return false;
        // Filtra solo los gastos que son consolidables, pertenecen al fondo activo y están visibles
        const consolidablesDelFondoActivo = gastosFiltrados.value.filter(g =>
            esConsolidable(g) &&
            g.id_fondo_efectivo === fondoActivoParaConsolidacion.value &&
            !esCheckboxDeshabilitado(g)
        );
        // Si no hay gastos consolidables del fondo activo, no puede estar marcado
        if (consolidablesDelFondoActivo.length === 0) return false;
        // Solo se marca si TODOS los gastos consolidables del fondo activo están seleccionados
        return consolidablesDelFondoActivo.every(g => gastosSeleccionados.value.includes(g.id));
    },
    set(value) {
        if (value) {
            // Al marcar, primero determina cuál es el fondo activo
            let fondoTarget = fondoActivoParaConsolidacion.value;
            if (!fondoTarget) {
                const primerGasto = gastosFiltrados.value.find(esConsolidable);
                if (primerGasto) fondoTarget = primerGasto.id_fondo_efectivo;
            }
            // Luego, selecciona TODOS y ÚNICAMENTE los gastos de ESE fondo
            gastosSeleccionados.value = gastosFiltrados.value
                .filter(g => esConsolidable(g) && g.id_fondo_efectivo === fondoTarget)
                .map(g => g.id);
        } else {
            // Al desmarcar, limpia la selección
            gastosSeleccionados.value = [];
        }
    }
});
// Hook para evitar el bug del auto-check al seleccionar el único item.
onUpdated(() => {
    // Solo proceder si hay un fondo activo
    if (!fondoActivoParaConsolidacion.value) return;

    // Encontrar los gastos consolidables del fondo activo que están visibles
    const consolidablesDelFondoActivo = gastosFiltrados.value.filter(g =>
        esConsolidable(g) &&
        g.id_fondo_efectivo === fondoActivoParaConsolidacion.value &&
        !esCheckboxDeshabilitado(g)
    );
    // Verificar si todos están seleccionados
    const todosEstanSeleccionados = consolidablesDelFondoActivo.length > 0 &&
        consolidablesDelFondoActivo.every(g => gastosSeleccionados.value.includes(g.id));
    // Actualizar el checkbox principal si es necesario
    const masterCheckbox = document.querySelector('th input[type="checkbox"]');
    if (masterCheckbox && masterCheckbox.checked !== todosEstanSeleccionados) {
        masterCheckbox.checked = todosEstanSeleccionados;
    }
});
// Prepara la lista de gastos a pasar al modal de re-consolidación
const gastosParaReconsolidar = computed(() => {
    return gastos.value.filter(g => gastosSeleccionados.value.includes(g.id));
});

// Habilita el botón "Crear DJ Consolidada"
const puedeReconsolidarDJ = computed(() => {
    // Solo se habilita si hay al menos dos gastos seleccionados Y todos son consolidables.
    // La validación más estricta de que sean "tipo DJ" se hace en esConsolidable.
    return gastosSeleccionados.value.length >= 1 &&
        gastosParaReconsolidar.value.every(g => esConsolidable(g));
});

// --- MÉTODOS ---
const resetearFiltros = () => {
    filtros.value = { busqueda: '', estado: '' };
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
    if (gastosSeleccionados.value.length === 0) return false;
    return gasto.id_fondo_efectivo !== fondoActivoParaConsolidacion.value;
};
// Selecciona/deselecciona todos los gastos consolidables visibles que no estan deshabilitados
const seleccionarTodos = (event) => {
    if (event.target.checked) {
        gastosSeleccionados.value = gastosFiltrados.value
            .filter(g => esConsolidable(g) && !esCheckboxDeshabilitado(g))
            .map(g => g.id);
    } else {
        gastosSeleccionados.value = [];
    }
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
}, { deep: true }); // Observación profunda para detectar cambios en las propiedades del objeto filtros

watch(gastosSeleccionados, (nuevosSeleccionados) => {
    if (nuevosSeleccionados.length > 0) {
        // Si hay gastos seleccionados, encuentra el primero y establece su fondo como el "activo".
        const primerGastoSeleccionado = gastos.value.find(g => g.id === nuevosSeleccionados[0]);
        if (primerGastoSeleccionado) {
            fondoActivoParaConsolidacion.value = primerGastoSeleccionado.id_fondo_efectivo;
        }
    } else {
        // Si no hay nada seleccionado, se libera el bloqueo.
        fondoActivoParaConsolidacion.value = null;
    }
}, { deep: true });
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
