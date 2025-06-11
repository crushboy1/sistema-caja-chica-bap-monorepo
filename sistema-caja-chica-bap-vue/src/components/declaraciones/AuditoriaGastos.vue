<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import GastoDetalleModal from './modals/GastoDetalleModal.vue';
import GestionAuditoriaModal from './modals/GestionAuditoriaModal.vue'; 


// --- ESTADO DEL COMPONENTE ---
const props = defineProps({
    usuarioActual: {
        type: Object,
        required: true
    }
});

const gastos = ref([]);
const cargando = ref(true);
const filtros = ref({
    codigo_gasto: '',
    registrador_name: '',
    fecha_inicio: '',
    fecha_fin: '',
    estado: 'Todos', // El admin ve todos los estados por defecto
    // Podríamos añadir filtros por área si fuera necesario
});

// --- ESTADO DE PAGINACIÓN ---
const paginaActual = ref(1);
const registrosPorPagina = ref(10);

// --- ESTADO DE MODALES ---
const gastoSeleccionado = ref(null);
const mostrarDetalleModal = ref(false);

// --- PROPIEDADES COMPUTADAS ---
const hayFiltrosActivos = computed(() => {
    return filtros.value.codigo_gasto || filtros.value.registrador_name || filtros.value.fecha_inicio || filtros.value.fecha_fin || filtros.value.estado !== 'Todos';
});

const totalPaginas = computed(() => {
    return Math.ceil(gastos.value.length / registrosPorPagina.value);
});

const gastosPaginados = computed(() => {
    const inicio = (paginaActual.value - 1) * registrosPorPagina.value;
    return gastos.value.slice(inicio, inicio + registrosPorPagina.value);
});

const paginasVisibles = computed(() => {
    if (totalPaginas.value <= 7) {
        return Array.from({ length: totalPaginas.value }, (_, i) => i + 1);
    }
    if (paginaActual.value < 5) {
        return [1, 2, 3, 4, 5, '...', totalPaginas.value];
    }
    if (paginaActual.value > totalPaginas.value - 4) {
        return [1, '...', totalPaginas.value - 4, totalPaginas.value - 3, totalPaginas.value - 2, totalPaginas.value - 1, totalPaginas.value];
    }
    return [1, '...', paginaActual.value - 1, paginaActual.value, paginaActual.value + 1, '...', totalPaginas.value];
});


// --- MÉTODOS ---
let debounceTimeout = null;
const fetchGastos = async () => {
    cargando.value = true;
    try {
        const params = { ...filtros.value };
        // El rol de admin/super_admin ya le da acceso a todo en el backend
        const response = await api.get('/gastos', { params });
        gastos.value = response.data;
    } catch (error) {
        console.error("Error al cargar gastos para auditoría:", error);
        Swal.fire('Error', 'No se pudieron cargar los gastos.', 'error');
    } finally {
        cargando.value = false;
    }
};

const limpiarFiltros = () => {
    filtros.value = {
        codigo_gasto: '',
        registrador_name: '',
        fecha_inicio: '',
        fecha_fin: '',
        estado: 'Todos',
    };
    // El watcher se encargará de llamar a fetchGastos
};

const verDetalles = (gasto) => {
    gastoSeleccionado.value = gasto;
    mostrarDetalleModal.value = true;
};

// Paginación
const irAPagina = (pagina) => {
    if (typeof pagina === 'number' && pagina >= 1 && pagina <= totalPaginas.value) {
        paginaActual.value = pagina;
    }
};
const paginaAnterior = () => { if (paginaActual.value > 1) paginaActual.value--; };
const paginaSiguiente = () => { if (paginaActual.value < totalPaginas.value) paginaActual.value++; };

// Estilos dinámicos para el estado del gasto
const getEstadoClass = (estado) => {
    const clases = {
        'Pendiente de Aprobación Jefatura': 'bg-estado-pendiente text-estado-pendiente-text',
        'Aprobado por Jefatura': 'bg-estado-aprobada-adm text-estado-aprobada-adm-text',
        'Observado por Administración': 'bg-estado-observada text-estado-observada-text',
        'Devuelto para Corrección': 'bg-yellow-200 text-yellow-800', // Un estado intermedio
        'Contabilizado': 'bg-blue-200 text-blue-800', // Estado final
        'Rechazado': 'bg-estado-rechazada text-estado-rechazada-text',
    };
    return clases[estado] || 'bg-gray-200 text-gray-800';
};

// --- WATCHERS Y LIFECYCLE ---
watch(filtros, () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        paginaActual.value = 1;
        fetchGastos();
    }, 500);
}, { deep: true });

onMounted(() => {
    fetchGastos();
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md animate-fade-in-up">
        <!-- Encabezado del Módulo -->
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Auditoría y Reportes de Gastos</h2>
            <p class="text-gray-500 max-w-2xl mx-auto">
                Supervisa, valida y exporta todos los gastos registrados en el sistema.
            </p>
        </div>

        <!-- Panel de Filtros Avanzado -->
        <div class="mb-8 p-4 bg-gray-50 rounded-lg shadow-inner">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                <div>
                    <label for="filtro_codigo_gasto_audit"
                        class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                    <input type="text" id="filtro_codigo_gasto_audit" v-model="filtros.codigo_gasto"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                        placeholder="GTO-...">
                </div>
                <div>
                    <label for="filtro_registrador_audit"
                        class="block text-sm font-medium text-gray-700 mb-1">Registrador</label>
                    <input type="text" id="filtro_registrador_audit" v-model="filtros.registrador_name"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                        placeholder="Nombre...">
                </div>
                <div>
                    <label for="filtro_estado_audit" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select id="filtro_estado_audit" v-model="filtros.estado"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                        <option value="Todos">Todos</option>
                        <option value="Pendiente de Aprobación Jefatura">Pendiente Jefatura</option>
                        <option value="Aprobado por Jefatura">Aprobado Jefatura</option>
                        <option value="Observado por Administración">Observado ADM</option>
                        <option value="Contabilizado">Contabilizado</option>
                        <option value="Rechazado">Rechazado</option>
                    </select>
                </div>
                <div>
                    <label for="filtro_fecha_inicio_audit"
                        class="block text-sm font-medium text-gray-700 mb-1">Desde</label>
                    <input type="date" id="filtro_fecha_inicio_audit" v-model="filtros.fecha_inicio"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                </div>
                <div>
                    <label for="filtro_fecha_fin_audit"
                        class="block text-sm font-medium text-gray-700 mb-1">Hasta</label>
                    <input type="date" id="filtro_fecha_fin_audit" v-model="filtros.fecha_fin"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                </div>
            </div>
            <div class="mt-4 flex justify-between items-center">
                <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-5 rounded-full transition-colors shadow-lg flex items-center text-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    Limpiar
                </button>
                <div class="flex-grow"></div>
                <button
                    class="bg-rojo-bap hover:bg-rojo-bap-dark text-white font-bold py-2 px-5 rounded-full transition-colors shadow-lg flex items-center text-sm">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    Exportar a Excel
                </button>
            </div>
        </div>

        <!-- Contenido Principal -->
        <div v-if="cargando" class="text-center text-gray-500 py-16">
            <div class="inline-flex items-center text-lg">
                <svg class="animate-spin -ml-1 mr-3 h-6 w-6 text-verde-bap" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                Cargando datos de auditoría...
            </div>
        </div>

        <div v-else-if="gastosPaginados.length === 0"
            class="text-center text-gray-500 py-16 px-6 bg-gray-50 rounded-lg shadow-inner">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <h3 class="mt-2 text-xl font-medium text-gray-900">Sin Resultados</h3>
            <p class="mt-1 text-md">No se encontraron gastos que coincidan con los filtros aplicados.</p>
        </div>

        <div v-else>
            <div class="mb-4 text-sm text-gray-600 text-center">
                Mostrando <strong>{{ (paginaActual - 1) * registrosPorPagina + 1 }} - {{ Math.min(paginaActual *
                    registrosPorPagina, gastos.length) }}</strong> de <strong>{{ gastos.length }}</strong> registros
            </div>
            <div class="overflow-x-auto shadow-strong rounded-lg">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr class="text-gray-700 uppercase text-xs leading-normal">
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Cód. Gasto</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Monto</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Estado</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Registrador</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Área</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Fecha Registro</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm">
                        <tr v-for="gasto in gastosPaginados" :key="gasto.id"
                            class="hover:bg-gray-50 transition-colors text-center">
                            <td class="py-4 px-6 font-medium whitespace-nowrap">{{ gasto.codigo_gasto }}</td>
                            <td class="py-4 px-6 font-semibold whitespace-nowrap">S/. {{
                                parseFloat(gasto.monto_total).toFixed(2) }}</td>
                            <td class="py-4 px-6 whitespace-normal">
                                <span class="py-2 px-3 rounded-full text-xs font-semibold inline-block"
                                    :class="getEstadoClass(gasto.estado)">
                                    {{ gasto.estado }}
                                </span>
                            </td>
                            <td class="py-4 px-4">{{ gasto.registrador.name }} {{ gasto.registrador.last_name }}</td>
                            <td class="py-4 px-4">{{ gasto.registrador.area?.name || 'N/A' }}</td>
                            <td class="py-4 px-4 text-gray-500">{{ new
                                Date(gasto.created_at).toLocaleDateString('es-ES') }}</td>
                            <td class="py-4 px-4">
                                <div class="flex items-center justify-center space-x-2">
                                    <button @click="verDetalles(gasto)"
                                        class="w-9 h-9 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 flex items-center justify-center transition-all duration-300 hover:scale-110"
                                        title="Ver Detalles y Evidencia">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>
                                    <!-- Botón para gestionar el gasto (observar, contabilizar) -->
                                    <button v-if="gasto.estado === 'Aprobado por Jefatura'"
                                        class="w-9 h-9 rounded-full bg-amarillo-bap hover:bg-amarillo-bap-dark text-amarillo-bap-dark hover:text-white flex items-center justify-center transition-all duration-300 hover:scale-110"
                                        title="Validar/Observar Gasto">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10 20l4-16m4 4l-4 4-4-4M6 16l-4-4 4-4"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="mt-6">
                <div class="flex justify-center items-center space-x-1">
                    <button @click="paginaAnterior" :disabled="paginaActual === 1"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center"
                        :class="paginaActual === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                            </path>
                        </svg>
                        Anterior
                    </button>
                    <button v-for="pagina in paginasVisibles" :key="pagina" @click="irAPagina(pagina)"
                        class="w-10 h-10 rounded-lg text-sm font-medium transition-colors duration-200 border" :class="[
                            paginaActual === pagina ? 'bg-verde-bap text-white border-verde-bap-dark shadow-md' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-200',
                            pagina === '...' ? 'cursor-default' : ''
                        ]">
                        {{ pagina }}
                    </button>
                    <button @click="paginaSiguiente" :disabled="paginaActual === totalPaginas"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center"
                        :class="paginaActual === totalPaginas ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'">
                        Siguiente
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                            </path>
                        </svg>
                    </button>
                </div>
                <div v-if="totalPaginas > 0" class="text-center text-sm text-gray-500 mt-2">
                    Página {{ paginaActual }} de {{ totalPaginas }}
                </div>
            </div>
        </div>

        <!-- Modales -->
        <GastoDetalleModal :mostrar="mostrarDetalleModal" :gasto="gastoSeleccionado"
            @close="mostrarDetalleModal = false" />
        <GestionAuditoriaModal :mostrar="mostrarAuditoriaModal" :gasto="gastoParaAuditar"
            :usuarioActual="props.usuarioActual" @close="cerrarAuditoriaModal"
            @accionRealizada="handleAccionRealizada" />

    </div>
</template>

<style scoped>
/* Estilos adicionales se pueden añadir aquí si son muy específicos del componente */
</style>
