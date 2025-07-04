<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import GastoDetalleModal from './modals/GastoDetalleModal.vue';
import { getClassesForAuditoriaBadge } from '@/utils/statusStyles.js';
import ReposicionFondoModal from './modals/ReposicionFondoModal.vue';

// --- ESTADO DEL COMPONENTE ---
const props = defineProps({
    usuarioActual: {
        type: Object,
        required: true
    }
});

const gastos = ref([]);
const areas = ref([]);
const cargando = ref(true);
const buscando = ref(false);
const exportando = ref(false);
const filtros = ref({
    codigo_gasto: '',
    registrador_name: '',
    fecha_inicio: '',
    fecha_fin: '',
    estado: 'Todos',
    area_id: '',
});

// --- CONSTANTES PARA FILTROS ---
let debounceTimeout = null;
const DEBOUNCE_DELAY = 500;
const MIN_SEARCH_LENGTH = 3;

// --- ESTADO DE PAGINACIÓN ---
const paginaActual = ref(1);
const registrosPorPagina = ref(10);

// --- ESTADO DE MODALES ---
const gastoSeleccionado = ref(null);
const mostrarDetalleModal = ref(false);
const mostrarReposicionModal = ref(false);

// --- PROPIEDADES COMPUTADAS ---
const hayFiltrosActivos = computed(() => {
    return filtros.value.codigo_gasto ||
        filtros.value.registrador_name ||
        filtros.value.fecha_inicio ||
        filtros.value.fecha_fin ||
        filtros.value.estado !== 'Todos' ||
        filtros.value.area_id; // <-- AÑADIDO: Detecta si el filtro de área está activo
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
const fetchGastos = async () => {
    try {
        const params = { ...filtros.value };
        if (params.codigo_gasto && params.codigo_gasto.length < MIN_SEARCH_LENGTH) {
            delete params.codigo_gasto;
        }
        if (params.registrador_name && params.registrador_name.length < MIN_SEARCH_LENGTH) {
            delete params.registrador_name;
        }

        const response = await api.get('/v1/gastos', { params });
        gastos.value = response.data;
        if (paginaActual.value > totalPaginas.value) paginaActual.value = totalPaginas.value || 1;

    } catch (error) {
        console.error("Error al cargar gastos para auditoría:", error);
        Swal.fire('Error', 'No se pudieron cargar los gastos.', 'error');
    } finally {
        cargando.value = false;
        buscando.value = false;
    }
};

const triggerSearchWithDebounce = () => {
    buscando.value = true;
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        fetchGastos();
    }, DEBOUNCE_DELAY);
};


const limpiarFiltros = () => {
    filtros.value = {
        codigo_gasto: '',
        registrador_name: '',
        fecha_inicio: '',
        fecha_fin: '',
        estado: 'Todos',
        area_id: '',
    };
    // El watcher se encargará de llamar a fetchGastos
};

const verDetalles = (gasto) => {
    gastoSeleccionado.value = gasto;
    mostrarDetalleModal.value = true;
};

const gestionarAccionAdm = async (gasto, accion) => {
    let config;
    const endpointPrefix = '/v1';
    switch (accion) {
        case 'finalizeAsAccounted':
            config = {
                title: 'Contabilizar Gasto',
                text: `¿Estás seguro de finalizar y contabilizar el gasto ${gasto.codigo_gasto}? Esta acción descontará el monto del fondo y no se puede revertir.`,
                icon: 'success',
                confirmButtonText: 'Sí, Contabilizar',
                endpoint: `${endpointPrefix}/gastos/${gasto.id}/finalize`,
                method: 'post',
                needsComment: false,
            };
            break;
        case 'observe':
            config = {
                title: 'Observar Gasto',
                text: `Vas a devolver el gasto ${gasto.codigo_gasto} para su corrección.`,
                icon: 'warning',
                confirmButtonText: 'Sí, Observar',
                endpoint: `${endpointPrefix}/gastos/${gasto.id}/observe`,
                method: 'post',
                needsComment: true,
                commentLabel: 'Motivo de la observación:'
            };
            break;
        case 'rejectFinal':
            config = {
                title: 'Rechazar Gasto Definitivamente',
                text: `Esta acción es final. ¿Estás seguro de rechazar el gasto ${gasto.codigo_gasto}?`,
                icon: 'error',
                confirmButtonText: 'Sí, Rechazar',
                endpoint: `${endpointPrefix}/gastos/${gasto.id}/reject-final`,
                method: 'post',
                needsComment: true,
                commentLabel: 'Motivo del rechazo:'
            };
            break;
        default: return;
    }

    let comentario = '';
    if (config.needsComment) {
        const { value: text } = await Swal.fire({
            title: config.title,
            input: 'textarea',
            inputLabel: config.commentLabel,
            inputPlaceholder: 'Escribe tu comentario aquí...',
            showCancelButton: true,
            confirmButtonText: config.confirmButtonText
        });
        if (!text) return; // El usuario canceló o no escribió nada.
        comentario = text;
    } else {
        const result = await Swal.fire({
            title: config.title,
            text: config.text,
            icon: config.icon,
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: config.confirmButtonText,
            cancelButtonText: 'Cancelar'
        });
        if (!result.isConfirmed) return;
    }

    // Ejecutar la llamada a la API
    try {
        await api[config.method](config.endpoint, { comentario });
        Swal.fire('¡Acción Completada!', 'La operación se realizó con éxito.', 'success');
        fetchGastos(); // Refrescar la tabla
    } catch (error) {
        console.error(`Error al ejecutar la acción ${accion}:`, error);
        Swal.fire('Error', error.response?.data?.message || 'Ocurrió un error inesperado.', 'error');
    }
};

const exportarGastos = async () => {
    exportando.value = true;
    try {
        const response = await api.post('/v1/gastos/exportar', filtros.value, {
            responseType: 'blob',
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const filename = 'reporte_gastos_sap_' + new Date().toISOString().slice(0, 10) + '.xlsx';
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();

        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        Swal.fire({
            icon: 'success', title: '¡Exportación Exitosa!',
            text: 'Los gastos han sido exportados y marcados como "Contabilizado". La lista se actualizará.',
            timer: 3000, showConfirmButton: false,
        });

        fetchGastos();

    } catch (error) {
        if (error.response && error.response.status === 404) {
            Swal.fire('Información', 'No se encontraron gastos aprobados que coincidan con los filtros actuales para exportar.', 'info');
        } else {
            Swal.fire('Error', 'Ocurrió un error al generar el archivo de exportación.', 'error');
        }
        console.error("Error al exportar gastos:", error);
    } finally {
        exportando.value = false;
    }
};

const fetchAreas = async () => {
    try {
        const response = await api.get('/v1/areas');
        areas.value = response.data.areas;
    } catch (error) {
        console.error("Error al cargar las áreas:", error);
        Swal.fire('Error', 'No se pudieron cargar las áreas para el filtro.', 'error');
    }
};

// Paginación
const irAPagina = (pagina) => {
    if (typeof pagina === 'number' && pagina >= 1 && pagina <= totalPaginas.value) {
        paginaActual.value = pagina;
    }
};
const paginaAnterior = () => { if (paginaActual.value > 1) paginaActual.value--; };
const paginaSiguiente = () => { if (paginaActual.value < totalPaginas.value) paginaActual.value++; };

const handleFondoRepuesto = () => {
    console.log("Evento 'fondoRepuesto' recibido. Refrescando la lista de gastos...");
    fetchGastos(); // Llama al método principal para recargar los datos de la tabla.
};
// --- WATCHERS Y LIFECYCLE ---
watch([() => filtros.value.estado, () => filtros.value.area_id], () => {
    buscando.value = true;
    clearTimeout(debounceTimeout);
    fetchGastos();
});

watch(() => filtros.value.codigo_gasto, (newValue) => {
    if (newValue.length === 0 || newValue.length >= MIN_SEARCH_LENGTH) {
        triggerSearchWithDebounce();
    }
});

watch(() => filtros.value.registrador_name, (newValue) => {
    if (newValue.length === 0 || newValue.length >= MIN_SEARCH_LENGTH) {
        triggerSearchWithDebounce();
    }
});

watch([() => filtros.value.fecha_inicio, () => filtros.value.fecha_fin], () => {
    triggerSearchWithDebounce();
});
watch(filtros, () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        paginaActual.value = 1;
        fetchGastos();
    }, 500);
}, { deep: true });
onMounted(() => {
    fetchGastos();
    fetchAreas();
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md animate-fade-in-up">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Auditoría y Reportes de Gastos</h2>
            <p class="text-gray-500 max-w-2xl mx-auto">
                Supervisa, valida y exporta todos los gastos registrados en el sistema.
            </p>
        </div>

        <div class="mb-8 p-4 bg-gray-50 rounded-lg shadow-inner">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <div class="relative">
                    <label for="filtro_codigo_gasto_audit"
                        class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                    <input type="text" id="filtro_codigo_gasto_audit" v-model="filtros.codigo_gasto"
                        placeholder="GTO-00001"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                    <div v-if="filtros.codigo_gasto.length > 0 && filtros.codigo_gasto.length < MIN_SEARCH_LENGTH"
                        class="text-xs text-amber-600 mt-1">
                        Mínimo {{ MIN_SEARCH_LENGTH }} caracteres
                    </div>
                </div>
                <div class="relative">
                    <label for="filtro_registrador_audit"
                        class="block text-sm font-medium text-gray-700 mb-1">Registrador</label>
                    <input type="text" id="filtro_registrador_audit" v-model="filtros.registrador_name"
                        placeholder="Nombre o Apellido"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                    <div v-if="filtros.registrador_name.length > 0 && filtros.registrador_name.length < MIN_SEARCH_LENGTH"
                        class="text-xs text-amber-600 mt-1">
                        Mínimo {{ MIN_SEARCH_LENGTH }} caracteres
                    </div>
                </div>

                <div class="relative">
                    <label for="filtro_area_audit" class="block text-sm font-medium text-gray-700 mb-1">Área</label>
                    <select id="filtro_area_audit" v-model="filtros.area_id"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                        <option value="">Todas</option>
                        <option v-for="area in areas" :key="area.id" :value="area.id">
                            {{ area.name }}
                        </option>
                    </select>
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

            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end items-center h-8">
                <div class="flex items-center space-x-3">
                    <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-5 rounded-full transition-colors shadow-lg flex items-center text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke-width="2"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                        Limpiar
                    </button>
                    <button @click="mostrarReposicionModal = true"
                        class="bg-verde-bap hover:bg-verde-bap-hover text-white font-bold py-2 px-5 rounded-full transition-colors shadow-lg flex items-center text-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6">
                            </path>
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"></circle>
                        </svg>
                        Reposición de Fondos
                    </button>
                    <button @click="exportarGastos" :disabled="exportando"
                        class="bg-rojo-bap hover:bg-rojo-bap-dark text-white font-bold py-2 px-5 rounded-full transition-colors shadow-lg flex items-center text-sm">
                        <span v-if="exportando" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                            Exportando...
                        </span>
                        <span v-else class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                </path>
                            </svg>
                            Exportar a Excel
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="cargando || buscando" class="text-center py-16">
            <div class="inline-flex items-center text-lg text-gray-600">
                <svg class="animate-spin -ml-1 mr-3 h-6 w-6 text-verde-bap" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                Cargando datos...
            </div>
        </div>
        <div v-else-if="!gastosPaginados.length" class="text-center py-16">
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
                            <th class="py-3 px-4 text-left font-semibold">Proyección Original</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Monto</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Registrador</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Área</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Fondo Afectado</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Estado</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Fecha Registro</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm">
                        <tr v-for="gasto in gastosPaginados" :key="gasto.id"
                            class="hover:bg-gray-50 transition-colors text-center">
                            <td class="py-4 px-6 font-medium whitespace-nowrap">{{ gasto.codigo_gasto }}</td>
                            <td class="py-4 px-4 text-left text-gray-500 whitespace-nowrap">{{
                                gasto.detalle_proyectado?.descripcion_gasto }}</td>
                            <td class="py-4 px-6 font-semibold whitespace-nowrap">S/. {{
                                parseFloat(gasto.monto_total).toFixed(2) }}</td>
                            <td class="py-4 px-4">{{ gasto.registrador.name }} {{ gasto.registrador.last_name }}</td>
                            <td class="py-4 px-4">{{ gasto.registrador.area?.name || 'N/A' }}</td>
                            <td class="py-4 px-4">{{ gasto.fondo_efectivo?.codigo_fondo || 'N/A' }}</td>
                            <td class="py-4 px-6 whitespace-normal">
                                <span :class="getClassesForAuditoriaBadge(gasto.estado)">
                                    {{ gasto.estado }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-gray-500">{{ new
                                Date(gasto.created_at).toLocaleDateString('es-PE') }}</td>
                            <td class="py-4 px-4">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    
                                    <div class="flex items-center space-x-2">
                                        <button @click="verDetalles(gasto)"
                                            class="w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 flex items-center justify-center transition-all duration-300 hover:scale-110"
                                            title="Ver Detalles y Evidencia">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                        <template v-if="gasto.estado === 'Pendiente de Validación Contable'">
                                            <button @click="gestionarAccionAdm(gasto, 'finalizeAsAccounted')"
                                                title="Contabilizar Gasto"
                                                class="w-8 h-8 rounded-full bg-verde-bap-light hover:bg-verde-bap text-verde-bap-dark hover:text-white flex items-center justify-center transition-all duration-300 hover:scale-110">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                        </template>
                                    </div>

                                    <template v-if="gasto.estado === 'Pendiente de Validación Contable'">
                                        <div class="flex items-center space-x-2">
                                            <button @click="gestionarAccionAdm(gasto, 'observe')" title="Observar Gasto"
                                                class="w-8 h-8 rounded-full bg-estado-advertencia-bg hover:bg-orange-500 text-estado-advertencia-text hover:text-white flex items-center justify-center transition-all duration-300 hover:scale-110">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                </svg>
                                            </button>
                                            <button @click="gestionarAccionAdm(gasto, 'rejectFinal')"
                                                title="Rechazar Gasto"
                                                class="w-8 h-8 rounded-full bg-rojo-bap-light hover:bg-rojo-bap text-rojo-bap-dark hover:text-white flex items-center justify-center transition-all duration-300 hover:scale-110">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </td>

                        </tr>
                    </tbody>
                </table>
            </div>

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

        <GastoDetalleModal :mostrar="mostrarDetalleModal" :gasto="gastoSeleccionado"
            @close="mostrarDetalleModal = false" />

        <ReposicionFondoModal :mostrar="mostrarReposicionModal" @close="mostrarReposicionModal = false"
            @fondoRepuesto="handleFondoRepuesto" />
    </div>
</template>

<style scoped>
/* Estilos adicionales se pueden añadir aquí si son muy específicos del componente */
</style>