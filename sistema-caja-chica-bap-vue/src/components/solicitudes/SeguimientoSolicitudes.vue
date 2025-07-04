<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import SolicitudDetalleModal from './SolicitudDetalleModal.vue';
import HistorialEstadosModal from './HistorialEstadosModal.vue';
import EditarSolicitudModal from './EditarSolicitudModal.vue';
import GestionSolicitudModal from './GestionSolicitudModal.vue';
import { getClassesForBadge } from '@/utils/statusStyles.js';

const props = defineProps({
    usuarioActual: { type: Object, required: true },
    proyectos: { type: Array, required: true },
    gastosProyectadosCatalogo: { type: Array, required: true },
    areasCatalogo: { type: Array, required: true }
});
// --- Variables de Estado ---


const solicitudes = ref([]);
const cargandoSolicitudes = ref(true);
const buscandoSolicitudes = ref(false);
// Variables para el modal de detalles
const mostrarDetalleModal = ref(false);
const solicitudSeleccionada = ref(null);

// Variables para el modal de historial de estados
const mostrarHistorialModal = ref(false);
const solicitudHistorialSeleccionada = ref(null);

// --- ¡NUEVO! Estado para el modal de edición ---
const mostrarEditarModal = ref(false);
const solicitudParaEditar = ref(null);
const modoEdicion = ref('pendiente'); // 'pendiente' o 'observada'

// Variables para el modal de gestión de solicitudes
const mostrarGestionModal = ref(false);
const solicitudGestionSeleccionada = ref(null);

// --- Variables para Filtros y Búsqueda ---
const filtroEstado = ref('Todas');
const filtroTipoSolicitud = ref('Todos');
const busquedaNumeroSolicitud = ref('');
const busquedaSolicitante = ref('');
const filtroFechaInicio = ref('');
const filtroFechaFin = ref('');

// Variables para el debounce de los campos de texto y fecha
let debounceTimeout = null;
const DEBOUNCE_DELAY = 800; // Aumentado para mejor UX
const MIN_SEARCH_LENGTH = 3;

// --- Variables para Paginación ---
const paginaActual = ref(1);
const registrosPorPagina = ref(10);

// --- Estados de Solicitud Definidos ---
// Esta lista es para la UI (como los selectores) y el historial completo.
const estadosSolicitud = [
    'Creada',
    'Pendiente Aprobación ADM',
    'Observada ADM',
    'Descargo Enviado ADM',
    'Pendiente Re-evaluacion',
    'Aprobada ADM',
    'Pendiente Aprobación GRTE',
    'Observada GRTE',
    'Descargo Enviado GRTE',
    'Pendiente Re-evaluacion GRTE',
    'Aprobada',
    'Rechazada Final'
];

// --- Roles de Usuario ---
const ROLES = {
    JEFE_AREA: 'jefe_area',
    JEFE_ADM: 'jefe_administracion',
    GERENTE_GENERAL: 'gerente_general',
    SUPER_ADMIN: 'super_admin',
    COLABORADOR: 'colaborador'
};

// --- Propiedades Computadas ---
const rolUsuario = computed(() => props.usuarioActual?.role?.name || null);

// Estados visibles en el filtro de la tabla principal
// No incluir aquí estados que son solo para el historial o transitorios en la tabla principal
const estadosVisiblesEnTabla = computed(() => {
    return [
        'Todas',
        'Pendiente Aprobación ADM',
        'Observada ADM',
        'Descargo Enviado ADM',
        'Pendiente Re-evaluacion',
        'Pendiente Aprobación GRTE',
        'Observada GRTE',
        'Descargo Enviado GRTE',
        'Pendiente Re-evaluacion GRTE',
        'Aprobada',
        'Rechazada Final'
    ];
});

// Computed para verificar si hay filtros activos
const hayFiltrosActivos = computed(() => {
    return filtroEstado.value !== 'Todas' ||
        filtroTipoSolicitud.value !== 'Todos' ||
        busquedaNumeroSolicitud.value.length > 0 ||
        busquedaSolicitante.value.length > 0 ||
        filtroFechaInicio.value ||
        filtroFechaFin.value;
});

// Computed para mostrar indicador de búsqueda (ahora más preciso con debounce)
const mostrarIndicadorBusqueda = computed(() => {
    // Muestra el indicador si hay una búsqueda pendiente por debounce
    return buscandoSolicitudes.value && (
        (busquedaNumeroSolicitud.value.length > 0 && busquedaNumeroSolicitud.value.length < MIN_SEARCH_LENGTH) ||
        (busquedaSolicitante.value.length > 0 && busquedaSolicitante.value.length < MIN_SEARCH_LENGTH) ||
        filtroFechaInicio.value || filtroFechaFin.value // También para fechas mientras se debouncen
    );
});

const solicitudesFiltradas = computed(() => {
    return solicitudes.value;
});

// Paginación
const totalPaginas = computed(() => {
    return Math.ceil(solicitudesFiltradas.value.length / registrosPorPagina.value);
});

const solicitudesMostradas = computed(() => {
    const inicio = (paginaActual.value - 1) * registrosPorPagina.value;
    const fin = inicio + registrosPorPagina.value;
    return solicitudesFiltradas.value.slice(inicio, fin);
});

// --- Función de Ayuda para Verificar Permisos (simplificada) ---
// La implementación de hasPermission se ha simplificado a la esperada por el backend de Laravel Spatie



const puedeGestionarSolicitud = (solicitud) => {
    const rol = rolUsuario.value;
    const estado = solicitud.estado;
    const usuarioEsSolicitante = props.usuarioActual?.id === solicitud.id_solicitante;
    const esDecrementoCierre = ['Decremento', 'Cierre'].includes(solicitud.tipo_solicitud);

    // REGLA 0: Super Admin siempre puede gestionar.
    if (rol === ROLES.SUPER_ADMIN) {
        return true;
    }
    // REGLA 1: LÓGICA PARA APROBADORES (El usuario actual NO es quien pidió la solicitud)
    if (!usuarioEsSolicitante) {
        // El Jefe de ADM puede gestionar lo que está en su bandeja de entrada.
        if (rol === ROLES.JEFE_ADM) {
            return ['Pendiente Aprobación ADM', 'Pendiente Re-evaluacion'].includes(estado);
        }
        // El Gerente General puede gestionar lo que está en su bandeja de entrada.
        if (rol === ROLES.GERENTE_GENERAL) {
            // Se incluye el nuevo estado 'Pendiente Re-evaluacion GRTE'.
            return ['Pendiente Aprobación GRTE', 'Pendiente Re-evaluacion GRTE'].includes(estado);
        }
    }
    // REGLA 2: LÓGICA PARA SOLICITANTES (El usuario actual SÍ es quien pidió la solicitud)
    if (usuarioEsSolicitante) {
        // REGLA 2.1: Lógica de negocio específica para Decremento/Cierre solicitados por ADM/GRTE.
        // Se preserva la regla de que no pueden auto-gestionar, solo enviar descargos.
        if ((rol === ROLES.JEFE_ADM || rol === ROLES.GERENTE_GENERAL) && esDecrementoCierre) {
            return estado === 'Observada GRTE'; // Solo pueden actuar si GRTE observó.
        }
        // REGLA 2.2: Para CUALQUIER OTRA solicitud, el solicitante solo puede "gestionar"
        // para enviar un descargo si su solicitud fue previamente observada.
        return ['Observada ADM', 'Observada GRTE'].includes(estado);
    }
    // Si ninguna regla se cumple, no se muestra el botón.
    return false;
};

// --- Función de permiso para edición proactiva ---
const puedeEditarProactivamente = (solicitud) => {
    // REGLA 0: Solo el solicitante puede editar y debe existir un usuario actual.
    if (!props.usuarioActual || props.usuarioActual.id !== solicitud.id_solicitante) {
        return false;
    }
    const estado = solicitud.estado;
    const solicitanteRol = solicitud.solicitante?.role?.name;
    // REGLA 1: Solicitantes "regulares" (Jefe de Área, Colaborador) solo pueden editar
    // ANTES de la primera revisión por parte de Administración.
    if ([ROLES.JEFE_AREA, ROLES.COLABORADOR].includes(solicitanteRol)) {
        return estado === 'Pendiente Aprobación ADM';
    }
    // REGLA 2: Solicitantes de "alto nivel" (Jefe ADM) solo pueden editar ANTES de la
    // primera revisión por parte de Gerencia (su solicitud salta a ADM).
    // Esta lógica es segura porque el backend usará 'Pendiente Re-evaluacion GRTE'
    // para los descargos, impidiendo que la solicitud vuelva a este estado inicial.
    if ([ROLES.JEFE_ADM, ROLES.SUPER_ADMIN].includes(solicitanteRol)) {
        return estado === 'Pendiente Aprobación GRTE';
    }
    // REGLA 3: El Gerente General no edita proactivamente, sus solicitudes se auto-aprueban.
    // Si ninguna regla aplica, no se puede editar.
    return false;
};


// --- Función para obtener solicitudes (ahora llamada por los watchers) ---
const obtenerSolicitudes = async () => {
    if (!buscandoSolicitudes.value) cargandoSolicitudes.value = true;
    try {
        const params = {
            estado: filtroEstado.value !== 'Todas' ? filtroEstado.value : undefined,
            tipo_solicitud: filtroTipoSolicitud.value !== 'Todos' ? filtroTipoSolicitud.value : undefined,
            codigo_solicitud: busquedaNumeroSolicitud.value.length >= MIN_SEARCH_LENGTH ? busquedaNumeroSolicitud.value : undefined,
            solicitante_name: busquedaSolicitante.value.length >= MIN_SEARCH_LENGTH ? busquedaSolicitante.value : undefined,
            fecha_inicio: filtroFechaInicio.value || undefined,
            fecha_fin: filtroFechaFin.value || undefined,
        };
        const response = await api.get('/v1/solicitudes', { params });
        solicitudes.value = response.data.solicitudes;
        paginaActual.value = 1;
    } catch (error) {
        console.error('❌ Error al obtener solicitudes:', error);
        Swal.fire('Error', 'No se pudieron cargar las solicitudes.', 'error');
    } finally {
        cargandoSolicitudes.value = false;
        buscandoSolicitudes.value = false;
    }
};

// --- Función para manejar búsquedas con debounce (general) ---
const triggerSearchWithDebounce = () => {
    buscandoSolicitudes.value = true; // Indicar que hay una búsqueda pendiente
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        obtenerSolicitudes();
    }, DEBOUNCE_DELAY);
};

// --- Función para limpiar todos los filtros ---
const limpiarFiltros = () => {
    filtroEstado.value = 'Todas';
    filtroTipoSolicitud.value = 'Todos';
    busquedaNumeroSolicitud.value = '';
    busquedaSolicitante.value = '';
    filtroFechaInicio.value = '';
    filtroFechaFin.value = '';

    // Limpiar timeout pendiente y resetear indicador de búsqueda
    clearTimeout(debounceTimeout);
    buscandoSolicitudes.value = false;

    // Recargar datos inmediatamente después de limpiar todos los filtros
    obtenerSolicitudes();
};

// --- Funciones de Paginación ---
const irAPagina = (pagina) => {
    if (pagina >= 1 && pagina <= totalPaginas.value) {
        paginaActual.value = pagina;
    }
};

const paginaAnterior = () => {
    if (paginaActual.value > 1) {
        paginaActual.value--;
    }
};

const paginaSiguiente = () => {
    if (paginaActual.value < totalPaginas.value) {
        paginaActual.value++;
    }
};

// --- Funciones de Acción ---
const verDetalles = (solicitud) => {
    solicitudSeleccionada.value = solicitud;
    mostrarDetalleModal.value = true;
};

const cerrarDetalleModal = () => {
    mostrarDetalleModal.value = false;
    solicitudSeleccionada.value = null;
};

const verHistorial = (solicitud) => {
    solicitudHistorialSeleccionada.value = solicitud;
    mostrarHistorialModal.value = true;
};

const cerrarHistorialModal = () => {
    mostrarHistorialModal.value = false;
    solicitudHistorialSeleccionada.value = null;
};

const abrirGestionModal = (solicitud) => {
    console.log('Abriendo modal de gestión para solicitud:', solicitud.id);
    solicitudGestionSeleccionada.value = solicitud;
    mostrarGestionModal.value = true;
};

const cerrarGestionModal = (refresh = false, openEditModal = false, solicitudToEdit = null) => {
    mostrarGestionModal.value = false;
    solicitudGestionSeleccionada.value = null;
    if (refresh) {
        obtenerSolicitudes();
    }
    if (openEditModal && solicitudToEdit) {
        abrirModalEdicion(solicitudToEdit, 'observada');
    }
};
// --- ¡NUEVAS! Funciones para manejar el modal de edición ---
const abrirModalEdicion = (solicitud, modo) => {
    solicitudParaEditar.value = solicitud;
    modoEdicion.value = modo;
    mostrarEditarModal.value = true;
};

const cerrarEditarModal = () => {
    mostrarEditarModal.value = false;
    solicitudParaEditar.value = null;
};
/**
 * ¡NUEVO! Esta función actúa como el manejador de eventos.
 * Cuando GestionSolicitudModal emite el evento, esta función se ejecuta.
 * @param {object} solicitud - La solicitud que se va a editar.
 */
const handleOpenEditModal = (solicitud) => {
    // 1. Cierra el modal de gestión actual.
    cerrarGestionModal();
    // 2. Abre el modal de edición, pasándole la solicitud y el modo 'observada'.
    abrirModalEdicion(solicitud, 'observada');
};
const onSolicitudActualizada = () => {
    cerrarEditarModal();
    cerrarGestionModal();
    obtenerSolicitudes(); // Refrescar la tabla
};
// --- Watchers mejorados ---

// Watchers para filtros de selección (disparan búsqueda inmediata)
watch([filtroEstado, filtroTipoSolicitud], () => {
    console.log('🔄 Filtros de selección cambiados');
    clearTimeout(debounceTimeout); // Limpiar cualquier debounce pendiente
    buscandoSolicitudes.value = false; // Resetear indicador de búsqueda pendiente
    obtenerSolicitudes(); // Disparar búsqueda inmediatamente
});

// Watchers para campos de texto (debounced, con lógica de longitud mínima)
watch(busquedaNumeroSolicitud, (newValue) => {
    console.log('🔍 Búsqueda número solicitud:', newValue);
    if (newValue.length >= MIN_SEARCH_LENGTH || newValue.length === 0) {
        // Si cumple el mínimo de caracteres o si se ha vaciado el campo, dispara el debounce
        triggerSearchWithDebounce();
    } else {
        // Si no cumple el mínimo, solo marca como buscando (sin disparar el debounce aún)
        buscandoSolicitudes.value = true;
    }
});

watch(busquedaSolicitante, (newValue) => {
    console.log('🔍 Búsqueda solicitante:', newValue);
    if (newValue.length >= MIN_SEARCH_LENGTH || newValue.length === 0) {
        // Si cumple el mínimo de caracteres o si se ha vaciado el campo, dispara el debounce
        triggerSearchWithDebounce();
    } else {
        // Si no cumple el mínimo, solo marca como buscando (sin disparar el debounce aún)
        buscandoSolicitudes.value = true;
    }
});

// Watchers para campos de fecha (debounced)
watch([filtroFechaInicio, filtroFechaFin], () => {
    console.log('🗓️ Filtros de fecha cambiados (debounced)');
    triggerSearchWithDebounce();
});


// --- Ciclo de Vida ---
onMounted(() => {
    obtenerSolicitudes();
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Seguimiento de Solicitudes de Fondos</h2>

        <div v-if="cargandoSolicitudes" class="text-center text-gray-500 py-8">
            <div class="inline-flex items-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg"
                    fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                Cargando datos...
            </div>
        </div>

        <div v-else>
            <!-- Panel de filtros mejorado -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-4 mb-4">
                    <!-- Filtro por Estado -->
                    <div>
                        <label for="filtroEstado" class="block text-sm font-medium text-gray-700 mb-1">
                            Filtrar por Estado:
                        </label>
                        <select id="filtroEstado" v-model="filtroEstado"
                            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                            <option v-for="estado in estadosVisiblesEnTabla" :key="estado" :value="estado">
                                {{ estado }}
                            </option>
                        </select>
                    </div>

                    <!-- Filtro por Tipo -->
                    <div>
                        <label for="filtroTipoSolicitud" class="block text-sm font-medium text-gray-700 mb-1">
                            Filtrar por Tipo:
                        </label>
                        <select id="filtroTipoSolicitud" v-model="filtroTipoSolicitud"
                            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                            <option value="Todos">Todos</option>
                            <option value="Apertura">Apertura</option>
                            <option value="Incremento">Incremento</option>
                            <option value="Decremento">Decremento</option>
                            <option value="Cierre">Cierre</option>
                        </select>
                    </div>

                    <!-- Botón limpiar filtros -->
                    <div class="flex items-end">
                        <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
                            class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors duration-200 text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Limpiar Filtros
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Búsqueda por Número de Solicitud -->
                    <div class="relative">
                        <label for="busquedaNumeroSolicitud" class="block text-sm font-medium text-gray-700 mb-1">
                            Buscar por Nro de Solicitud:
                        </label>
                        <input type="text" id="busquedaNumeroSolicitud" v-model="busquedaNumeroSolicitud"
                            placeholder="Ej. SOL-00001"
                            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap" />
                        <!-- Indicador de búsqueda -->
                        <div v-if="buscandoSolicitudes && busquedaNumeroSolicitud.length > 0 && busquedaNumeroSolicitud.length < MIN_SEARCH_LENGTH"
                            class="absolute right-3 top-8 text-gray-400">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </div>
                        <!-- Indicador de caracteres mínimos -->
                        <div v-if="busquedaNumeroSolicitud.length > 0 && busquedaNumeroSolicitud.length < MIN_SEARCH_LENGTH"
                            class="text-xs text-amber-600 mt-1">
                            Ingrese al menos {{ MIN_SEARCH_LENGTH }} caracteres para buscar
                        </div>
                    </div>

                    <!-- Búsqueda por Solicitante -->
                    <div class="relative">
                        <label for="busquedaSolicitante" class="block text-sm font-medium text-gray-700 mb-1">
                            Buscar por Solicitante:
                        </label>
                        <input type="text" id="busquedaSolicitante" v-model="busquedaSolicitante"
                            placeholder="Nombre o Apellido"
                            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap" />
                        <!-- Indicador de búsqueda -->
                        <div v-if="buscandoSolicitudes && busquedaSolicitante.length > 0 && busquedaSolicitante.length < MIN_SEARCH_LENGTH"
                            class="absolute right-3 top-8 text-gray-400">
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                </path>
                            </svg>
                        </div>
                        <!-- Indicador de caracteres mínimos -->
                        <div v-if="busquedaSolicitante.length > 0 && busquedaSolicitante.length < MIN_SEARCH_LENGTH"
                            class="text-xs text-amber-600 mt-1">
                            Ingrese al menos {{ MIN_SEARCH_LENGTH }} caracteres para buscar
                        </div>
                    </div>

                    <!-- Fecha Inicio -->
                    <div>
                        <label for="filtroFechaInicio" class="block text-sm font-medium text-gray-700 mb-1">
                            Fecha Inicio:
                        </label>
                        <input type="date" id="filtroFechaInicio" v-model="filtroFechaInicio"
                            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap" />
                    </div>

                    <!-- Fecha Fin -->
                    <div>
                        <label for="filtroFechaFin" class="block text-sm font-medium text-gray-700 mb-1">
                            Fecha Fin:
                        </label>
                        <input type="date" id="filtroFechaFin" v-model="filtroFechaFin"
                            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap" />
                    </div>
                </div>

                <!-- Indicador de estado de búsqueda -->
                <div v-if="buscandoSolicitudes && (busquedaNumeroSolicitud.length >= MIN_SEARCH_LENGTH || busquedaSolicitante.length >= MIN_SEARCH_LENGTH || filtroFechaInicio || filtroFechaFin)"
                    class="mt-3 text-sm text-verde-bap flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-verde-bap" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Buscando solicitudes...
                </div>
            </div>

            <!-- Mensaje cuando no hay resultados -->
            <div v-if="solicitudesFiltradas.length === 0 && !cargandoSolicitudes"
                class="text-center text-gray-500 py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-lg font-medium">No se encontraron solicitudes</p>
                <p class="text-sm text-gray-400 mt-1">
                    {{ hayFiltrosActivos ? 'Intenta ajustar los filtros de búsqueda' : 'No hay solicitudes registradas'
                    }}
                </p>
                <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
                    class="mt-3 px-4 py-2 bg-verde-bap text-white rounded-md hover:bg-verde-bap-dark transition-colors duration-200 text-sm">
                    Limpiar filtros
                </button>
            </div>
            <div v-else>
                <div class="mb-4 text-sm text-gray-600 text-center">
                    Mostrando {{ (paginaActual - 1) * registrosPorPagina + 1 }} -
                    {{ Math.min(paginaActual * registrosPorPagina, solicitudesFiltradas.length) }}
                    de {{ solicitudesFiltradas.length }} registros
                </div>

                <div class="overflow-x-auto shadow-lg rounded-lg">
                    <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                        <thead>
                            <tr class="bg-gray-100 text-gray-700 uppercase text-xs leading-normal">
                                <th class="py-3 px-2 text-center font-semibold">COD SOLICITUD</th>
                                <th class="py-3 px-2 text-center font-semibold">Tipo</th>
                                <th class="py-3 px-2 text-center font-semibold">Tipo Fondo</th>
                                <th class="py-3 px-2 text-center font-semibold">Monto</th>
                                <th class="py-3 px-2 text-center font-semibold">Prioridad</th>
                                <th class="py-3 px-2 text-center font-semibold w-48">Estado</th>
                                <th class="py-3 px-2 text-center font-semibold">Solicitante</th>
                                <th class="py-3 px-2 text-center font-semibold">Fecha Creación</th>
                                <th class="py-3 px-2 text-center font-semibold w-32">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm">
                            <tr v-for="solicitud in solicitudesMostradas" :key="solicitud.id"
                                class="border-b border-gray-200 hover:bg-gray-50 transition-colors duration-200">
                                <td class="py-4 px-4 text-center text-sm whitespace-nowrap">{{
                                    solicitud.codigo_solicitud ||
                                    solicitud.id }}</td>
                                <td class="py-4 px-4 text-center text-sm">{{ solicitud.tipo_solicitud || 'N/A' }}</td>
                                <td class="py-4 px-4 text-center text-sm">
                                    {{ solicitud.tipo_fondo_solicitado || 'N/A' }}
                                </td>
                                <td class="py-4 px-4 text-center text-sm whitespace-nowrap">S/. {{
                                    solicitud.monto_solicitado ?
                                        parseFloat(solicitud.monto_solicitado).toFixed(2) : '0.00' }}</td>
                                <td class="py-4 px-4 text-center text-sm">{{ solicitud.prioridad || 'N/A' }}</td>

                                <td class="py-4 px-4 flex justify-center items-center text-xs">
                                    <span :class="getClassesForBadge(solicitud.estado)">
                                        {{ solicitud.estado }}
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-center text-sm">{{ solicitud.solicitante?.name || 'N/A' }} {{
                                    solicitud.solicitante?.last_name || '' }}</td>
                                <td class="py-4 px-4 text-center text-sm">
                                    {{ new Date(solicitud.created_at).toLocaleDateString('es-ES') }}
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button @click="verDetalles(solicitud)"
                                            class="w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center text-gray-700 transition-colors duration-200"
                                            title="Ver Detalles">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <button @click="verHistorial(solicitud)"
                                            class="w-8 h-8 rounded-full bg-blue-200 hover:bg-blue-300 flex items-center justify-center text-blue-700 transition-colors duration-200"
                                            title="Ver Historial de Estados">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>
                                        <button v-if="puedeEditarProactivamente(solicitud)"
                                            @click="abrirModalEdicion(solicitud, 'pendiente')"
                                            title="Editar Solicitud Pendiente"
                                            class="w-8 h-8 rounded-full bg-gray-500 hover:bg-gray-600 flex items-center justify-center text-white transition-colors duration-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L14.732 3.732z" />
                                            </svg>
                                        </button>
                                        <button v-if="puedeGestionarSolicitud(solicitud)"
                                            @click="abrirGestionModal(solicitud)"
                                            class="w-8 h-8 rounded-full bg-verde-bap-dark hover:bg-verde-bap flex items-center justify-center text-white transition-colors duration-200"
                                            title="Gestionar Solicitud">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 flex justify-center items-center space-x-2">
                    <button @click="paginaAnterior" :disabled="paginaActual === 1" :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200',
                        paginaActual === 1
                            ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                            : 'bg-verde-bap text-white hover:bg-verde-bap-dark'
                    ]">
                        Anterior
                    </button>

                    <div class="flex space-x-1">
                        <button v-for="pagina in Math.min(totalPaginas, 5)" :key="pagina" @click="irAPagina(pagina)"
                            :class="[
                                'w-10 h-10 rounded-lg text-sm font-medium transition-colors duration-200',
                                paginaActual === pagina
                                    ? 'bg-verde-bap text-white'
                                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                            ]">
                            {{ pagina }}
                        </button>

                        <span v-if="totalPaginas > 5" class="flex items-center px-2 text-gray-500">...</span>

                        <button v-if="totalPaginas > 5" @click="irAPagina(totalPaginas)" :class="[
                            'w-10 h-10 rounded-lg text-sm font-medium transition-colors duration-200',
                            paginaActual === totalPaginas
                                ? 'bg-verde-bap text-white'
                                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                        ]">
                            {{ totalPaginas }}
                        </button>
                    </div>

                    <button @click="paginaSiguiente" :disabled="paginaActual === totalPaginas" :class="[
                        'px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200',
                        paginaActual === totalPaginas
                            ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                            : 'bg-verde-bap text-white hover:bg-verde-bap-dark'
                    ]">
                        Siguiente
                    </button>
                </div>

                <div class="mt-4 text-center text-sm text-gray-600">
                    Página {{ paginaActual }} de {{ totalPaginas }}
                </div>
            </div>
        </div>

        <SolicitudDetalleModal :mostrar="mostrarDetalleModal" :solicitud="solicitudSeleccionada"
            :gastos-catalogo="props.gastosProyectadosCatalogo" @close="cerrarDetalleModal" />

        <HistorialEstadosModal :mostrar="mostrarHistorialModal" :solicitud="solicitudHistorialSeleccionada"
            @close="cerrarHistorialModal" />
        <EditarSolicitudModal v-if="mostrarEditarModal && solicitudParaEditar" :mostrar="mostrarEditarModal"
            :solicitud-a-editar="solicitudParaEditar" :modo="modoEdicion" :usuario-actual="props.usuarioActual"
            :proyectos="props.proyectos" :gastos-proyectados-catalogo="props.gastosProyectadosCatalogo"
            :areas-catalogo="props.areasCatalogo" @solicitud-actualizada="onSolicitudActualizada"
            @cancelar="cerrarEditarModal" />

        <!-- Se pasa el objeto usuarioActual al componente GestionSolicitudModal -->
        <GestionSolicitudModal :mostrar="mostrarGestionModal" :solicitud="solicitudGestionSeleccionada"
            :usuario-actual="props.usuarioActual" @close="cerrarGestionModal" @open-edit-modal="handleOpenEditModal" />
    </div>
</template>

<style scoped>
/* Las transiciones del modal y los estilos del scrollbar personalizados se mantienen aquí
   porque son estilos específicos del componente y no utilidades de Tailwind. */

.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

/* Estilos adicionales para mejorar la tabla */
.table-container {
    max-width: 100%;
    overflow-x: auto;
}

/* Mejoras en el diseño responsivo */
@media (max-width: 768px) {
    .table-container {
        font-size: 0.875rem;
    }

    .table-container th,
    .table-container td {
        padding: 0.5rem 0.25rem;
    }
}
</style>
