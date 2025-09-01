<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import GastoDetalleModal from './modals/GastoDetalleModal.vue';
import { getClassesForAuditoriaBadge } from '@/utils/statusStyles.js';
import { 
    Receipt, 
    Clock, 
    AlertTriangle, 
    CheckCircle2, 
    Download, 
    X,
    Search,
    User,
    Calendar,
    Building2,
    DollarSign // Añadido para el filtro de Gasto Proyectado
} from 'lucide-vue-next';
import vSelect from 'vue-select';
import 'vue-select/dist/vue-select.css';

// Estilos personalizados para vue-select y tabla
const customStyles = `
<style>
.v-select {
  @apply w-full;
}

.v-select .vs__dropdown-toggle {
  @apply border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap;
  @apply min-h-[38px] bg-white;
  @apply transition-all duration-200;
}

.v-select .vs__dropdown-toggle:hover {
  @apply border-gray-400;
}

.v-select .vs__dropdown-toggle:focus-within {
  @apply border-verde-bap ring-2 ring-verde-bap ring-opacity-20;
}

.v-select .vs__selected-options {
  @apply max-w-full flex-1;
}

.v-select .vs__selected {
  @apply max-w-full truncate;
  @apply text-sm font-medium text-gray-900;
  @apply bg-gray-100 rounded px-2 py-1;
}

.v-select .vs__dropdown-menu {
  @apply border border-gray-300 rounded-md shadow-lg;
  @apply bg-white z-50;
  @apply max-h-60 overflow-y-auto;
  @apply min-w-full;
}

.v-select .vs__dropdown-option {
  @apply text-sm py-2 px-3;
  @apply hover:bg-gray-50 cursor-pointer;
  @apply transition-colors duration-150;
  @apply whitespace-normal;
  @apply break-words;
  @apply leading-relaxed;
}

.v-select .vs__dropdown-option--highlight {
  @apply bg-verde-bap text-white;
}

.v-select .vs__dropdown-option--selected {
  @apply bg-verde-bap bg-opacity-10 text-verde-bap;
}

.v-select .vs__clear {
  @apply text-gray-400 hover:text-gray-600;
  @apply transition-colors duration-150;
  @apply p-1 rounded;
}

.v-select .vs__search {
  @apply text-sm text-gray-900;
  @apply placeholder-gray-500;
}

.v-select .vs__actions {
  @apply pr-2 flex items-center;
}

.v-select .vs__open-indicator {
  @apply text-gray-400 transition-transform duration-200;
}

.v-select.vs--open .vs__open-indicator {
  @apply transform rotate-180;
}

/* Manejo de texto largo - permitir que se vea todo */
.v-select .vs__selected {
  white-space: normal;
  overflow: visible;
  word-wrap: break-word;
  max-width: calc(100% - 80px); /* Dejar espacio para el botón clear y flecha */
  line-height: 1.4;
  min-height: 24px;
  padding: 4px 8px;
}

/* Ajuste de tamaño de texto según longitud */
.v-select.text-sm .vs__selected {
  @apply text-sm;
}

.v-select.text-xs .vs__selected {
  @apply text-xs;
}

/* Responsive para móviles */
@media (max-width: 640px) {
  .v-select .vs__selected {
    max-width: calc(100% - 70px);
  }
}

/* Estilos para la tabla */
.report-table {
  @apply border-collapse;
}

.report-table th {
  @apply font-semibold text-gray-700 bg-gray-100 border-b border-gray-200;
  @apply sticky top-0 z-10;
}

.report-table td {
  @apply border-b border-gray-100;
}

.report-table tr:hover {
  @apply bg-gray-50;
}

.report-table tr:hover td {
  @apply bg-gray-50;
}

/* Estilos para grupos DJ */
.dj-group-row {
  @apply bg-gradient-to-r from-blue-50 to-indigo-50;
  @apply border-l-4 border-blue-500;
}

.dj-group-row:hover {
  @apply from-blue-100 to-indigo-100;
}

/* Estilos para gastos individuales del grupo */
.dj-item-row {
  @apply bg-gray-50;
  @apply border-l-4 border-blue-400;
}

.dj-item-row:hover {
  @apply bg-gray-100;
}

/* Estilos para gastos standalone */
.standalone-row {
  @apply hover:bg-gray-50;
}

/* Scroll horizontal suave */
.overflow-x-auto {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e0 #f7fafc;
}

.overflow-x-auto::-webkit-scrollbar {
  height: 8px;
}

.overflow-x-auto::-webkit-scrollbar-track {
  background: #f7fafc;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 4px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
  background: #a0aec0;
}
</style>
`;

// --- ESTADO DEL COMPONENTE ---
const props = defineProps({
    usuarioActual: {
        type: Object,
        required: true
    }
});

// --- ESTADO DE DATOS ---
const items = ref([]); // Almacena la data cruda de la API (gastos individuales y grupos DJ)
const areas = ref([]);
const gastosProyectados = ref([]); // Lista de gastos proyectados
const cargando = ref(true);
const buscando = ref(false);
const exportando = ref(false);

// --- ESTADO DE FILTROS ---
const filtros = ref({
    texto: '', // Para buscar por código o glosa
    registrador_name: '',
    fecha_inicio: '',
    fecha_fin: '',
    gastoProyectado: 'Todos', // 👈 nuevo filtro
    estado: 'Todos', // Permite filtrar por todos los estados posibles
    area_id: '',
});

// --- CONSTANTES PARA FILTROS ---
let debounceTimeout = null;
const DEBOUNCE_DELAY = 500;
const MIN_SEARCH_LENGTH = 1; // Reducido para permitir búsquedas más flexibles en reportes

// --- ESTADO DE PAGINACIÓN ---
const paginaActual = ref(1);
const registrosPorPagina = ref(10);

// --- ESTADO DE MODALES Y EXPANSIONES ---
const gastoSeleccionado = ref(null);
const mostrarDetalleModal = ref(false);
const expandedGroups = ref(new Set()); // Para controlar qué grupos DJ están expandidos

// --- UTILIDADES ---
const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date)) return 'N/A';
    return date.toLocaleDateString('es-PE');
};

const isPdf = (url) => {
    console.log('isPdf called with:', url);
    const result = url && typeof url === 'string' && url.toLowerCase().endsWith('.pdf');
    console.log('isPdf result:', result);
    return result;
};

// --- CONSTS Y HELPERS PARA ALINEAR CON EXCEL ---
const SAP_SERIE = '323';
const SAP_TIPO_DOCUMENTO = 'dDocument_Service';
const MONEDA_DOCUMENTO = 'SOL';

const mapTipoDocumentoSunat = {
    'Factura': '01',
    'Boleta de Venta': '02',
    'Declaración Jurada': '03',
};

const getTipoDocumentoSunat = (tipo) => mapTipoDocumentoSunat[tipo] || 'N/A';

const getSerieDocumento = (g) => {
    if (!g) return 'N/A';
    // Para Declaraciones Juradas, la serie por defecto es "DJ"
    if (g.tipo_documento === 'Declaración Jurada') {
        return 'DJ';
    }
    // Para otros documentos, usar la serie ingresada
    return g.serie_documento || 'N/A';
};

const getCorrelativoDocumento = (g) => {
    if (!g) return 'N/A';
    // Para Declaraciones Juradas, usar la fecha del documento o fecha de ejecución
    if (g.tipo_documento === 'Declaración Jurada') {
        return g.fecha_documento ? formatDate(g.fecha_documento) : 
               g.fecha_gasto ? formatDate(g.fecha_gasto) : 'N/A';
    }
    // Para otros documentos, usar el correlativo ingresado
    return g.correlativo_documento || 'N/A';
};

const getReferenciaDocumento = (g) => {
    if (!g) return 'N/A';
    const tipo = g.tipo_documento || 'N/A';
    const serie = getSerieDocumento(g);
    const correlativo = getCorrelativoDocumento(g);
    return `${tipo}-${serie}-${correlativo}`;
};

const getFechaContabilizacion = (g) => {
    const historial = g?.historial_aprobaciones || [];
    const record = historial.find(h => String(h.estado_nuevo || '').toLowerCase() === 'contabilizado');
    return record ? formatDate(record.created_at) : 'N/A';
};

// --- PROPIEDADES COMPUTADAS ---
const estadisticas = computed(() => {
    const contadores = {
        todos: { count: 0, amount: 0 },
        pendientes: { count: 0, amount: 0 },
        observados: { count: 0, amount: 0 },
        contabilizados: { count: 0, amount: 0 },
    };

    itemsFiltrados.value.forEach(item => {
        if (item.es_grupo) {
            contadores.todos.count += item.gastos?.length || 0;
            contadores.todos.amount += item.monto_total_grupo || 0;
            if (item.estado_grupo === 'Observado') {
                contadores.observados.count += item.gastos?.length || 0;
                contadores.observados.amount += item.monto_total_grupo || 0;
            } else if (item.estado_grupo === 'Contabilizado') {
                contadores.contabilizados.count += item.gastos?.length || 0;
                contadores.contabilizados.amount += item.monto_total_grupo || 0;
            } else {
                contadores.pendientes.count += item.gastos?.length || 0;
                contadores.pendientes.amount += item.monto_total_grupo || 0;
            }
        } else if (item.gasto) {
            contadores.todos.count++;
            contadores.todos.amount += parseFloat(item.gasto.monto_total || 0);
            if (item.gasto.estado === 'Observado' || item.gasto.estado === 'Rechazado') {
                contadores.observados.count++;
                contadores.observados.amount += parseFloat(item.gasto.monto_total || 0);
            } else if (item.gasto.estado === 'Contabilizado') {
                contadores.contabilizados.count++;
                contadores.contabilizados.amount += parseFloat(item.gasto.monto_total || 0);
            } else { // Pendientes de cualquier tipo
                contadores.pendientes.count++;
                contadores.pendientes.amount += parseFloat(item.gasto.monto_total || 0);
            }
        }
    });

    return contadores;
});

const hayFiltrosActivos = computed(() => {
    return Object.values(filtros.value).some(value => value && String(value).trim() !== '' && value !== 'Todos');
});

// Lógica de filtrado unificada para items (individuales o grupos DJ)
const itemsFiltrados = computed(() => {
    let data = [...items.value]; // Copia de los ítems originales
    const textoBusqueda = filtros.value.texto.toLowerCase().trim();
    const registradorBusqueda = filtros.value.registrador_name.toLowerCase().trim();

    return data.filter(item => {
        const esGrupo = item.es_grupo;
        let fechaItem = null;
        let registradorItem = null;
        let codigoGastoItem = '';
        let glosaItem = '';
        let estadoItem = '';

        // Determinar propiedades para el filtrado según si es grupo o gasto individual
        if (esGrupo) {
            if (!item.gastos || item.gastos.length === 0) {
                return false; // Si es un grupo pero no tiene gastos, lo descartamos
            }
            fechaItem = item.fecha_registro; // Propiedad de resumen del grupo
            registradorItem = item.registrador; // Propiedad de resumen del grupo
            estadoItem = item.estado_grupo; // Propiedad de resumen del grupo
            // Para búsqueda de texto en grupos, se busca en los gastos internos
        } else if (item.gasto) {
            fechaItem = item.gasto.created_at;
            registradorItem = item.gasto.registrador;
            codigoGastoItem = item.gasto.codigo_gasto?.toLowerCase() || '';
            glosaItem = item.gasto.glosa?.toLowerCase() || '';
            estadoItem = item.gasto.estado;
        } else {
            return false; // Item con estructura inesperada
        }

        const registradorFullName = registradorItem ? `${registradorItem.name || ''} ${registradorItem.last_name || ''}`.toLowerCase() : '';

        // Aplicar filtros
        const pasaFecha = (!filtros.value.fecha_inicio || (fechaItem && new Date(fechaItem) >= new Date(filtros.value.fecha_inicio))) &&
            (!filtros.value.fecha_fin || (fechaItem && new Date(fechaItem) <= new Date(filtros.value.fecha_fin)));

        const pasaRegistrador = !registradorBusqueda || registradorFullName.includes(registradorBusqueda);

        const pasaEstado = filtros.value.estado === 'Todos' || estadoItem === filtros.value.estado;

        const pasaArea = !filtros.value.area_id || (registradorItem && registradorItem.area?.id == filtros.value.area_id);

        // Filtro de gasto proyectado
        let pasaGastoProyectado = true;
        if (filtros.value.gastoProyectado && filtros.value.gastoProyectado !== 'Todos') {
            if (esGrupo && item.gastos) {
                pasaGastoProyectado = item.gastos.some(g => g.gasto_proyectado?.descripcion === filtros.value.gastoProyectado);
            } else if (!esGrupo && item.gasto) {
                pasaGastoProyectado = item.gasto.gasto_proyectado?.descripcion === filtros.value.gastoProyectado;
            }
        }

        if (!pasaFecha || !pasaRegistrador || !pasaEstado || !pasaArea || !pasaGastoProyectado) {
            return false;
        }

        // Aplicar filtro por código/glosa (texto)
        if (textoBusqueda.length > 0) {
            if (esGrupo && item.gastos) {
                return item.gastos.some(g =>
                    (g.codigo_gasto?.toLowerCase() || '').includes(textoBusqueda) ||
                    (g.glosa?.toLowerCase() || '').includes(textoBusqueda)
                );
            } else if (!esGrupo && item.gasto) {
                return codigoGastoItem.includes(textoBusqueda) || glosaItem.includes(textoBusqueda);
            }
            return false;
        }
        return true;
    });
});

const totalItems = computed(() => itemsFiltrados.value.length);

const totalPaginas = computed(() => {
    return Math.ceil(totalItems.value / registrosPorPagina.value);
});

const itemsPaginados = computed(() => {
    const inicio = (paginaActual.value - 1) * registrosPorPagina.value;
    return itemsFiltrados.value.slice(inicio, inicio + registrosPorPagina.value);
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


// --- MÉTODOS DE DATOS Y ACCIONES ---
const fetchGastos = async () => {
    cargando.value = true;
    try {
        const params = { ...filtros.value };
        
        // Limpiar parámetros vacíos para evitar filtros innecesarios
        Object.keys(params).forEach(key => {
            if (params[key] === '' || params[key] === null || params[key] === undefined) {
                delete params[key];
            }
        });

        // Llamada al endpoint de reportes
        const response = await api.get('/v1/gastos/reportes', { params });
        items.value = response.data; // La data ya viene lista y agrupada/formateada del backend

        // Ajusta la página actual si es necesario después de cargar los datos
        if (paginaActual.value > totalPaginas.value && totalPaginas.value > 0) {
            paginaActual.value = totalPaginas.value;
        } else if (totalPaginas.value === 0) {
            paginaActual.value = 1; // Si no hay páginas, siempre en la primera
        }

    } catch (error) {
        console.error("Error al cargar gastos para reportes:", error);
        Swal.fire('Error', error.response?.data?.message || 'Ocurrió un error al cargar los gastos.', 'error');
    } finally {
        cargando.value = false;
        buscando.value = false;
    }
};

const triggerSearchWithDebounce = () => {
    buscando.value = true;
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        paginaActual.value = 1; // Siempre reiniciar paginación al buscar
        fetchGastos();
    }, DEBOUNCE_DELAY);
};

const limpiarFiltros = () => {
    filtros.value = {
        texto: '',
        registrador_name: '',
        fecha_inicio: '',
        fecha_fin: '',
        gastoProyectado: 'Todos',
        estado: 'Todos',
        area_id: '',
    };
    // El watcher principal se encargará de llamar a fetchGastos y resetear la paginación
};

const verDetalles = (gasto) => {
    // Siempre pasamos el 'gasto' individual al modal de detalles,
    // ya que este modal no está diseñado para mostrar "grupos de DJ" directamente.
    gastoSeleccionado.value = gasto;
    mostrarDetalleModal.value = true;
};

const toggleGroup = (groupId) => {
    if (expandedGroups.value.has(groupId)) {
        expandedGroups.value.delete(groupId);
    } else {
        expandedGroups.value.add(groupId);
    }
};

const exportarGastos = async () => {
    exportando.value = true;
    try {
        const params = { ...filtros.value }; // Enviar todos los filtros actuales al backend para la exportación
        
        // Limpiar parámetros vacíos para evitar filtros innecesarios
        Object.keys(params).forEach(key => {
            if (params[key] === '' || params[key] === null || params[key] === undefined) {
                delete params[key];
            }
        });

        // Llamada al endpoint de exportación
        const response = await api.post('/v1/gastos/exportar-reporte', params, {
            responseType: 'blob', // Importante para manejar la descarga de archivos
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const filename = 'reporte_gastos_' + new Date().toISOString().slice(0, 10) + '.xlsx';
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();

        // Limpieza de recursos
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        Swal.fire({
            icon: 'success', title: '¡Exportación Exitosa!',
            text: 'El archivo Excel ha sido generado y descargado.',
            timer: 3000, showConfirmButton: false,
        });

    } catch (error) {
        if (error.response && error.response.status === 404) {
            Swal.fire('Información', 'No se encontraron gastos que coincidan con los filtros actuales para exportar.', 'info');
        } else {
            Swal.fire('Error', error.response?.data?.message || 'Ocurrió un error al generar el archivo de exportación.', 'error');
        }
        console.error("Error al exportar gastos:", error);
    } finally {
        exportando.value = false;
    }
};

const fetchAreas = async () => {
    try {
        const response = await api.get('/v1/areas');
        areas.value = response.data.data;
    } catch (error) {
        console.error("Error al cargar las áreas:", error);
        Swal.fire('Error', 'No se pudieron cargar las áreas para el filtro.', 'error');
    }
};

const fetchGastosProyectados = async () => {
    try {
        const response = await api.get('/v1/gastos-proyectados');
        gastosProyectados.value = response.data.gastos_proyectados;
    } catch (error) {
        console.error("Error al cargar los gastos proyectados:", error);
        Swal.fire('Error', 'No se pudieron cargar los gastos proyectados para el filtro.', 'error');
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

// --- WATCHERS Y LIFECYCLE ---
// Watcher principal para filtros que reinicia la paginación y dispara la búsqueda
watch(filtros, () => {
    triggerSearchWithDebounce();
}, { deep: true }); // 'deep: true' para observar cambios en propiedades anidadas de 'filtros'

// Los watchers individuales para campos de texto con debounce y longitud mínima
// ya no son estrictamente necesarios si el watcher 'filtros' con deep:true y debounce
// ya maneja todos los cambios y reinicia la paginación.
// Se mantienen los MIN_SEARCH_LENGTH para la validación visual, pero la lógica de filtrado
// en itemsFiltrados ya no los usa para descartar la búsqueda (solo para mostrar el mensaje).

onMounted(() => {
    fetchGastos();
    fetchAreas();
    fetchGastosProyectados();
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md animate-fade-in-up">
        <!-- Estilos personalizados para vue-select y tabla -->
        <div v-html="customStyles"></div>
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Reporte de Gastos</h2>
            <p class="text-gray-500 max-w-2xl mx-auto">
                Genera reportes detallados de todos los gastos registrados en el sistema, aplicando diversos filtros.
            </p>
        </div>

        <!-- Panel de Filtros -->
        <div class="mb-8 p-4 bg-gray-50 rounded-lg shadow-inner">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <div class="relative">
                    <label for="filtro_codigo_gasto_reporte"
                        class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <Receipt class="w-4 h-4 mr-1" />
                        Código/Glosa
                    </label>
                    <div class="relative">
                        <input type="text" id="filtro_codigo_gasto_reporte" v-model="filtros.texto"
                            placeholder="Buscar por código o glosa"
                            class="mt-1 block w-full p-2 pr-8 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                        <button v-if="filtros.texto" @click="filtros.texto = ''" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <div v-if="filtros.texto.length > 0 && filtros.texto.length < MIN_SEARCH_LENGTH"
                        class="text-xs text-amber-600 mt-1">
                        Mínimo {{ MIN_SEARCH_LENGTH }} caracteres
                    </div>
                </div>
                <div class="relative">
                    <label for="filtro_registrador_reporte"
                        class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <User class="w-4 h-4 mr-1" />
                        Registrador
                    </label>
                    <div class="relative">
                        <input type="text" id="filtro_registrador_reporte" v-model="filtros.registrador_name"
                            placeholder="Nombre o Apellido"
                            class="mt-1 block w-full p-2 pr-8 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                        <button v-if="filtros.registrador_name" @click="filtros.registrador_name = ''" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                    <div v-if="filtros.registrador_name.length > 0 && filtros.registrador_name.length < MIN_SEARCH_LENGTH"
                        class="text-xs text-amber-600 mt-1">
                        Mínimo {{ MIN_SEARCH_LENGTH }} caracteres
                    </div>
                </div>

                <div class="relative">
                    <label for="filtro_area_reporte" class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <Building2 class="w-4 h-4 mr-1" />
                        Área
                    </label>
                    <select id="filtro_area_reporte" v-model="filtros.area_id"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                        <option value="">Todas</option>
                        <option v-for="area in areas" :key="area.id" :value="area.id">
                            {{ area.name }}
                        </option>
                    </select>
                </div>


                <div>
                    <label for="filtro_estado_reporte"
                        class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <AlertTriangle class="w-4 h-4 mr-1" />
                        Estado
                    </label>
                    <select id="filtro_estado_reporte" v-model="filtros.estado"
                        class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                        <option value="Todos">Todos</option>
                        <option value="Pendiente de Aprobación">Pendiente Aprobación Jefatura</option>
                        <option value="Pendiente de Validación DJ">Pendiente Validación DJ</option>
                        <option value="Pendiente de Validación Contable">Pendiente Validación Contable</option>
                        <option value="Observado">Observado</option>
                        <option value="Rechazado">Rechazado</option>
                        <option value="Contabilizado">Contabilizado</option>
                    </select>
                </div>
                <div>
                    <label for="filtro_fecha_inicio_reporte" class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <Calendar class="w-4 h-4 mr-1" />
                        Fecha Desde
                    </label>
                    <div class="relative">
                        <input type="date" id="filtro_fecha_inicio_reporte" v-model="filtros.fecha_inicio"
                            class="mt-1 block w-full p-2 pr-8 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                        <button v-if="filtros.fecha_inicio" @click="filtros.fecha_inicio = ''" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
                <div>
                    <label for="filtro_fecha_fin_reporte" class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                        <Calendar class="w-4 h-4 mr-1" />
                        Fecha Hasta
                    </label>
                    <div class="relative">
                        <input type="date" id="filtro_fecha_fin_reporte" v-model="filtros.fecha_fin"
                            class="mt-1 block w-full p-2 pr-8 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                        <button v-if="filtros.fecha_fin" @click="filtros.fecha_fin = ''" 
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                            <X class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filtro por Gasto Proyectado - Fila separada -->
            <div class="mt-4">
                <label for="gastoProyectado" class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                    <DollarSign class="w-4 h-4 mr-1" />
                    Gasto Proyectado
                </label>
                <v-select
                  v-model="filtros.gastoProyectado"
                  :options="['Todos', ...gastosProyectados.map(g => g.descripcion)]"
                  :reduce="val => val"
                  placeholder="Seleccione un gasto proyectado"
                  clearable
                  class="mt-1 w-full"
                />
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-end items-center h-8">
                <div class="flex items-center space-x-3">
                    <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-5 rounded-full transition-colors shadow-lg flex items-center text-sm">
                        <X class="w-4 h-4 mr-2" />
                        Limpiar Filtros
                    </button>
                    <button @click="exportarGastos" :disabled="exportando"
                        class="bg-verde-bap hover:bg-verde-bap-dark text-white font-bold py-2 px-5 rounded-full transition-colors shadow-lg flex items-center text-sm">
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
                            <Download class="w-4 h-4 mr-2" />
                            Exportar a Excel
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Contadores -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Gastos -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-xl shadow-lg border border-blue-200 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center shadow-sm">
                        <Receipt class="w-6 h-6 text-blue-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-blue-600/80">Total Gastos</p>
                        <p class="text-2xl font-bold text-blue-700">{{ estadisticas.todos.count }}</p>
                        <p class="text-sm text-blue-600/70">{{ currencyFormatter.format(estadisticas.todos.amount) }}</p>
                    </div>
                </div>
            </div>

            <!-- Pendientes -->
            <div class="bg-gradient-to-br from-amber-50 to-amber-100 p-6 rounded-xl shadow-lg border border-amber-200 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center shadow-sm">
                        <Clock class="w-6 h-6 text-amber-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-amber-600/80">Pendientes</p>
                        <p class="text-2xl font-bold text-amber-700">{{ estadisticas.pendientes.count }}</p>
                        <p class="text-sm text-amber-600/70">{{ currencyFormatter.format(estadisticas.pendientes.amount) }}</p>
                    </div>
                </div>
            </div>

            <!-- Observados/Rechazados -->
            <div class="bg-gradient-to-br from-red-50 to-red-100 p-6 rounded-xl shadow-lg border border-red-200 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center shadow-sm">
                        <AlertTriangle class="w-6 h-6 text-red-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-red-600/80">Observados/Rechazados</p>
                        <p class="text-2xl font-bold text-red-700">{{ estadisticas.observados.count }}</p>
                        <p class="text-sm text-red-600/70">{{ currencyFormatter.format(estadisticas.observados.amount) }}</p>
                    </div>
                </div>
            </div>

            <!-- Contabilizados -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-xl shadow-lg border border-green-200 hover:shadow-xl transition-all duration-300">
                <div class="flex items-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center shadow-sm">
                        <CheckCircle2 class="w-6 h-6 text-green-600" />
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-green-600/80">Contabilizados</p>
                        <p class="text-2xl font-bold text-green-700">{{ estadisticas.contabilizados.count }}</p>
                        <p class="text-sm text-green-600/70">{{ currencyFormatter.format(estadisticas.contabilizados.amount) }}</p>
                    </div>
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
        <div v-else-if="!itemsPaginados.length" class="text-center py-16">
            <Search class="mx-auto h-12 w-12 text-gray-400" />
            <h3 class="mt-2 text-xl font-medium text-gray-900">Sin Resultados</h3>
            <p class="mt-1 text-md">No se encontraron gastos que coincidan con los filtros aplicados.</p>
        </div>
        <div v-else>
            <div class="mb-4 text-sm text-gray-600 text-center">
                Mostrando <strong>{{ (paginaActual - 1) * registrosPorPagina + 1 }} - {{ Math.min(paginaActual *
                    registrosPorPagina, totalItems) }}</strong> de <strong>{{ totalItems }}</strong> registros
            </div>
            <div class="overflow-x-auto shadow-lg rounded-lg">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg report-table">
                    <thead class="bg-gray-100">
                        <tr class="bg-gray-100 text-gray-700 uppercase text-xs leading-normal">
                            

                            <th class="py-3 px-3 text-center font-semibold w-16"></th> <!-- Expander -->
                            <th class="py-3 px-3 text-center font-semibold w-32">Número Correlativo</th>
                            <th class="py-3 px-3 text-center font-semibold w-40">Tipo</th>
                            <th class="py-3 px-3 text-center font-semibold w-32">Código</th>
                            <th class="py-3 px-3 text-center font-semibold w-48">Glosa / Descripción</th>
                            <th class="py-3 px-3 text-center font-semibold w-32">Cód. Cuenta</th>
                            <th class="py-3 px-3 text-center font-semibold w-40">Desc. Cuenta</th>
                            <th class="py-3 px-3 text-center font-semibold w-40">Proyección Gasto</th>
                            <th class="py-3 px-3 text-center font-semibold w-32">Fecha Doc.</th>
                            <th class="py-3 px-3 text-center font-semibold w-32">Monto</th>
                            <th class="py-3 px-3 text-center font-semibold w-40">Estado</th>
                            <th class="py-3 px-3 text-center font-semibold w-40">Registrador</th>
                            <th class="py-3 px-3 text-center font-semibold w-32">Área</th>
                            <th class="py-3 px-3 text-center font-semibold w-24">Evidencia</th>
                            <th class="py-3 px-3 text-center font-semibold w-24">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="text-gray-600 text-sm divide-y divide-gray-200">
                        <template v-for="item in itemsPaginados"
                            :key="item.es_grupo ? `grupo-${item.id_dj_consolidada || ''}` : `gasto-${item.gasto?.id || ''}`">
                            <!-- Fila de Grupo DJ -->
                            <tr v-if="item.es_grupo"
                                class="dj-group-row hover:from-blue-100 hover:to-indigo-100 transition-all duration-200">
                                <td class="py-3 px-3 text-center">
                                    <button @click="toggleGroup(item.id_dj_consolidada)"
                                        class="p-2 rounded-full hover:bg-blue-200 transition-colors">
                                        <svg class="w-5 h-5 text-blue-600 transition-transform duration-200"
                                            :class="{ 'rotate-90': expandedGroups.has(item.id_dj_consolidada) }"
                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </button>
                                </td>
                                <td class="py-3 px-3 text-center">—</td> <!-- Número Correlativo -->
                                <td class="py-3 px-3">
                                    <div class="flex items-center space-x-2">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                                                    </path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-bold text-blue-800 text-sm">DJ Grupal</div>
                                            <div class="text-xs text-blue-600 font-medium">{{ item.gastos?.length || 0
                                                }} gastos</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-mono text-sm font-medium text-blue-800">DJ-{{
                                        item.id_dj_consolidada }}</div>
                                    <div class="text-xs text-blue-600">Consolidada</div>
                                </td>
                                <td class="py-3 px-3 text-sm text-gray-700">Gastos consolidados</td>
                                <td class="py-3 px-3 text-left text-xs">—</td>
                                <td class="py-3 px-3 text-left text-xs">—</td>
                                <td class="py-3 px-3 text-left text-xs">—</td>
                                <td class="py-3 px-3 text-center text-gray-500">{{ formatDate(item.fecha_registro) }}</td>
                                <td class="py-3 px-3 text-center">
                                    <div class="font-bold text-lg text-blue-800">{{
                                        currencyFormatter.format(item.monto_total_grupo || 0)
                                        }}</div>
                                    <div class="text-xs text-gray-500">Total consolidado</div>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span :class="getClassesForAuditoriaBadge(item.estado_grupo)">{{ item.estado_grupo
                                        }}</span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <div class="text-sm font-medium text-gray-900">{{ item.registrador?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ item.registrador?.last_name }}</div>
                                </td>
                                <td class="py-3 px-3 text-left text-xs">{{ item.registrador?.area?.name }}</td>
                                <td class="py-3 px-3 text-center text-xs">
                                    <a v-if="item.dj_consolidada && item.dj_consolidada.documento_firmado_url" :href="item.dj_consolidada.documento_firmado_url" target="_blank" class="inline-block p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-600 transition-colors" title="Ver DJ Consolidada">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                    </a>
                                    <span v-else class="text-xs text-gray-400">N/A</span>
                                </td>
                                <td class="py-3 px-3 text-center">     
                                </td>
                            </tr>

                            <!-- Filas de gastos individuales del grupo (solo si está expandido) -->
                            <template v-if="item.es_grupo && expandedGroups.has(item.id_dj_consolidada)">
                                <tr v-for="(gasto, index) in item.gastos" 
                                :key="`${item.id_dj_consolidada}-${gasto.id}`"
                                    class="dj-item-row transition-colors text-xs"
                                    :class="{ 'border-b-2 border-blue-200': index === item.gastos.length - 1 }">
                                    <td class="py-3 px-3 text-center border-l-4 border-blue-400">
                                        <div 
                                            class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-600 text-xs font-bold">{{ index + 1 }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-3 text-center text-gray-600">{{ gasto.id }}</td> <!-- Número Correlativo -->
                                    <td class="py-3 px-3 text-center text-gray-600">
                                        <span class="text-xs bg-gray-200 px-2 py-1 rounded">Parte del grupo</span>
                                    </td>
                                    <td class="py-3 px-3 text-center text-gray-600 font-mono">{{ gasto.codigo_gasto }}</td>
                                    <td class="py-3 px-3 text-center text-gray-700">{{ gasto.glosa }}</td>
                                    <td class="py-3 px-3 text-left text-xs font-mono text-gray-600" :title="gasto.cuenta_contable?.codigo_cuenta">
                                        {{ gasto.cuenta_contable?.codigo_cuenta }}
                                    </td>
                                    <td class="py-3 px-3 text-left text-xs" :title="gasto.cuenta_contable?.descripcion">
                                        {{ gasto.cuenta_contable?.descripcion }}
                                    </td>
                                    <td class="py-3 px-3 text-left text-xs max-w-[150px] truncate" :title="gasto.gasto_proyectado?.descripcion">
                                        {{ gasto.gasto_proyectado?.descripcion || 'N/A' }}
                                    </td>
                                    
                                    <td class="py-3 px-3 text-center">{{ formatDate(gasto.fecha_documento) }}</td>
                                    <td class="py-3 px-3 text-center text-gray-800 font-semibold">
                                        {{ currencyFormatter.format(parseFloat(gasto.monto_total || 0)) }}
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="px-2 py-1 font-semibold leading-tight rounded-full text-xs"
                                            :class="getClassesForAuditoriaBadge(gasto.estado)">
                                            {{ gasto.estado }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center text-gray-500">
                                        <div class="text-sm font-medium text-gray-900">{{ gasto.registrador?.name }}
                                            </div>
                                        <div class="text-xs text-gray-500">{{ gasto.registrador?.last_name }}</div>
                                    </td>
                                    <td class="py-3 px-3 text-left text-xs">{{ gasto.registrador?.area?.name }}</td>
                                    <td class="py-3 px-3 text-center text-gray-500">
                                        <a v-if="item.dj_consolidada && item.dj_consolidada.documento_firmado_url" :href="item.dj_consolidada.documento_firmado_url" target="_blank" class="inline-block p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-600 transition-colors" title="Ver DJ Consolidada">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        </a>
                                        <a v-else-if="gasto.evidencia_url" :href="gasto.evidencia_url" target="_blank" class="inline-block p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-600 transition-colors" title="Ver Evidencia Individual">
                                            <svg v-if="isPdf(gasto.evidencia_url)" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                        </a>
                                        <span v-else class="text-xs text-gray-400">N/A</span>
                                    </td>
                                    <td class="py-3 px-3 text-center">
                                        <div class="flex space-x-1 justify-center">
                                            <button @click="verDetalles(gasto)"
                                                class="p-2 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-600 transition-colors"
                                                title="Ver Detalle Individual">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" 
                                                viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" 
                                                    stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Fila de Gasto Individual (Standalone) -->
                            <tr v-if="!item.es_grupo" class="standalone-row transition-colors">
                                <td class="py-3 px-3">
                                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                            </path>
                                        </svg>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-center">{{ item.gasto?.id }}</td> <!-- Número Correlativo -->
                                <td class="py-3 px-3">
                                    <div class="flex items-center space-x-2">
                                        <div class="flex-shrink-0">
                                            <div
                                                class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                                    </path>
                                                </svg>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="font-bold text-verde-bap-dark text-sm">Individual</div>
                                            <div class="text-xs text-verde-bap">Gasto único</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3">
                                    <div class="font-mono text-sm font-medium text-verde-bap-dark">{{
                                        item.gasto?.codigo_gasto }}</div>
                                    <div class="text-xs text-verde-bap">Código único</div>
                                </td>
                                <td class="py-3 px-3 text-center text-gray-700">{{ item.gasto?.glosa }}</td>
                                
                                <td class="py-3 px-3 text-left text-xs font-mono text-gray-600" :title="item.gasto.cuenta_contable?.codigo_cuenta">
                                    {{ item.gasto.cuenta_contable?.codigo_cuenta }}
                                </td>
                                <td class="py-3 px-3 text-left text-xs" :title="item.gasto.cuenta_contable?.descripcion">
                                    {{ item.gasto.cuenta_contable?.descripcion }}
                                </td>
                                <td class="py-3 px-3 text-left text-xs max-w-[150px] truncate" :title="item.gasto.gasto_proyectado?.descripcion">
                                    {{ item.gasto.gasto_proyectado?.descripcion || 'N/A' }}
                                </td>

                                <td class="py-3 px-3 text-center text-gray-500">{{ formatDate(item.gasto?.fecha_documento)
                                    }}</td>
                                <td class="py-3 px-3 text-center font-semibold text-lg text-verde-bap-dark">
                                    {{ currencyFormatter.format(parseFloat(item.gasto?.monto_total || 0)) }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span :class="getClassesForAuditoriaBadge(item.gasto?.estado)">{{ item.gasto?.estado
                                        }}</span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <div class="text-sm font-medium text-gray-900">{{ item.gasto?.registrador?.name }}
                                    </div>
                                    <div class="text-xs text-gray-500">{{ item.gasto?.registrador?.last_name }}</div>
                                </td>
                                <td class="py-3 px-3 text-left text-xs">{{ item.gasto.registrador?.area?.name }}</td>
                                <td class="py-3 px-3 text-center">
                                    <a v-if="item.gasto.evidencia_url" :href="item.gasto.evidencia_url" target="_blank" class="inline-block p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-600 transition-colors" title="Ver Evidencia">
                                        <svg v-if="isPdf(item.gasto.evidencia_url)" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 01-2 2v12a2 2 0 002 2z"></path></svg>
                                    </a>
                                    <span v-else class="text-xs text-gray-400">N/A</span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <div class="flex space-x-1 justify-center">
                                        <button @click="verDetalles(item.gasto)"
                                            class="p-2 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 transition-colors"
                                            title="Ver Detalle Individual">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" 
                                            viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" 
                                                stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
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

        <GastoDetalleModal :mostrar="mostrarDetalleModal" :gasto="gastoSeleccionado" :usuarioActual="usuarioActual"
            @close="mostrarDetalleModal = false" />

    </div>
</template>