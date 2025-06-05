<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import SolicitudDetalleModal from './SolicitudDetalleModal.vue';
import HistorialEstadosModal from './HistorialEstadosModal.vue';
import GestionSolicitudModal from './GestionSolicitudModal.vue';

// --- Variables de Estado ---
const usuarioActual = ref(null);
const cargandoUsuario = ref(true);
const solicitudes = ref([]);
const cargandoSolicitudes = ref(true);
const buscandoSolicitudes = ref(false); // Indica si hay una búsqueda pendiente por debounce
// Variables para el modal de detalles
const mostrarDetalleModal = ref(false);
const solicitudSeleccionada = ref(null);

// Variables para el modal de historial de estados
const mostrarHistorialModal = ref(false);
const solicitudHistorialSeleccionada = ref(null);

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
    'Aprobada ADM',
    'Pendiente Aprobación GRTE',
    'Observada GRTE',
    'Descargo Enviado GRTE',
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
const rolUsuario = computed(() => {
    return usuarioActual.value?.role?.name || null;
});

// Estados visibles en el filtro de la tabla principal
// No incluir aquí estados que son solo para el historial o transitorios en la tabla principal
const estadosVisiblesEnTabla = computed(() => {
    return [
        'Todas',
        'Pendiente Aprobación ADM',
        'Observada ADM', // La solicitud puede estar en este estado principal
        'Descargo Enviado ADM', // También visible en la tabla principal
        'Pendiente Aprobación GRTE',
        'Observada GRTE', // La solicitud puede estar en este estado principal
        'Descargo Enviado GRTE', // También visible en la tabla principal
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
const hasPermission = (permissionName) => {
    if (!usuarioActual.value?.role?.permissions) {
        // console.log('No hay permisos o usuario cargado');
        return false;
    }
    // Asumimos que los permisos vienen como un array de objetos con una propiedad 'name'
    return usuarioActual.value.role.permissions.some(permission => permission.name === permissionName);
};


// --- Función para verificar si puede gestionar solicitud ---
const puedeGestionarSolicitud = (solicitud) => {
    const rol = rolUsuario.value;
    const estado = solicitud.estado;
    const usuarioEsSolicitante = usuarioActual.value?.id === solicitud.id_solicitante;
    
    // Es crucial que solicitud.solicitante.role.name esté cargado.
    // El backend ya lo carga en el index y show, así que debería estar disponible.
    const solicitanteRol = solicitud.solicitante?.role?.name;
    const solicitanteEsAdminOSuperAdmin = [ROLES.JEFE_ADM, ROLES.SUPER_ADMIN].includes(solicitanteRol);
    const esDecrementoCierre = ['Decremento', 'Cierre'].includes(solicitud.tipo_solicitud);

    console.log(`[puedeGestionarSolicitud] Rol: ${rol}, Estado: ${estado}, SolicitanteID: ${solicitud.id_solicitante}, UsuarioActualID: ${usuarioActual.value?.id}, EsSolicitante: ${usuarioEsSolicitante}, SolicitanteRol: ${solicitanteRol}, EsDecrementoCierre: ${esDecrementoCierre}`);

    // Reglas para Super Admin: Siempre puede gestionar el botón, las restricciones finas están en el modal.
    if (rol === ROLES.SUPER_ADMIN) {
        return true;
    }

    // Reglas para Jefe de Administración
    if (rol === ROLES.JEFE_ADM) {
        // Un Jefe ADM no puede "aprobar", "observar" o "rechazar" sus propias solicitudes de Decremento/Cierre
        // si él mismo es el solicitante. Solo puede enviar un descargo si fue observada por GRTE.
        if (usuarioEsSolicitante && solicitanteEsAdminOSuperAdmin && esDecrementoCierre) {
            return estado === 'Observada GRTE'; // Solo si es observada por GRTE, puede enviar descargo
        }
        // Para otras solicitudes (que no sean sus propios Decremento/Cierre)
        // o si es un Decremento/Cierre de OTRA persona,
        // el Jefe ADM puede gestionar si está en Pendiente ADM o si hay un descargo enviado (vuelve a Pendiente ADM).
        return ['Pendiente Aprobación ADM', 'Descargo Enviado ADM'].includes(estado);
    }

    // Reglas para Gerente General
    if (rol === ROLES.GERENTE_GENERAL) {
        // Gerente General puede gestionar solicitudes pendientes para él o con descargo enviado a él.
        // Similar a Jefe ADM, si el propio Gerente es el solicitante de un Decremento/Cierre, solo puede
        // enviar descargo si fue Observada GRTE.
        if (usuarioEsSolicitante && solicitanteRol === ROLES.GERENTE_GENERAL && esDecrementoCierre) {
             return estado === 'Observada GRTE'; // Solo si es observada por GRTE, puede enviar descargo
        }
        return ['Pendiente Aprobación GRTE', 'Descargo Enviado GRTE'].includes(estado);
    }

    // Reglas para Jefe de Área / Colaborador (solo pueden presentar descargo de sus propias solicitudes observadas)
    if (rol === ROLES.JEFE_AREA || rol === ROLES.COLABORADOR) {
        // Solicitantes (Jefe de Área o Colaborador) solo pueden gestionar para enviar un descargo
        // si su solicitud ha sido observada por ADM o GRTE.
        return usuarioEsSolicitante && (estado === 'Observada ADM' || estado === 'Observada GRTE');
    }

    return false; // Por defecto, no se puede gestionar
};

// --- Funciones de Carga de Datos ---
const obtenerUsuarioAutenticado = async () => {
    cargandoUsuario.value = true;
    try {
        console.log('🔍 Haciendo petición a /user...');
        const response = await api.get('/user');

        usuarioActual.value = response.data.user;

        console.log('✅ Usuario y Rol asignados correctamente:', usuarioActual.value?.name, rolUsuario.value);
        if (!usuarioActual.value?.role?.permissions) {
            console.warn('⚠️ El usuario autenticado no tiene permisos cargados.');
        }

    } catch (error) {
        console.error('❌ Error al obtener datos del usuario autenticado:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error de Autenticación',
            text: 'No se pudieron cargar los datos del usuario. Por favor, inicia sesión de nuevo.',
            confirmButtonText: 'Ok'
        });
    } finally {
        cargandoUsuario.value = false;
    }
};

// --- Función para obtener solicitudes (ahora llamada por los watchers) ---
const obtenerSolicitudes = async () => {
    // Solo mostrar loading si no es una búsqueda en tiempo real (debounce ya maneja el indicador)
    if (!buscandoSolicitudes.value) {
        cargandoSolicitudes.value = true;
    }

    try {
        const params = {};

        // Filtros de selección (siempre se aplican)
        if (filtroEstado.value !== 'Todas') {
            params.estado = filtroEstado.value;
        }

        if (filtroTipoSolicitud.value !== 'Todos') {
            params.tipo_solicitud = filtroTipoSolicitud.value;
        }

        // Filtros de texto (solo si cumplen el mínimo de caracteres)
        if (busquedaNumeroSolicitud.value.length >= MIN_SEARCH_LENGTH) {
            params.codigo_solicitud = busquedaNumeroSolicitud.value;
        }

        if (busquedaSolicitante.value.length >= MIN_SEARCH_LENGTH) {
            params.solicitante_name = busquedaSolicitante.value;
        }

        // Filtros de fecha (se aplican si tienen valor)
        if (filtroFechaInicio.value) {
            params.fecha_inicio = filtroFechaInicio.value;
        }

        if (filtroFechaFin.value) {
            params.fecha_fin = filtroFechaFin.value;
        }

        console.log('📤 Parámetros de búsqueda:', params);

        const response = await api.get('/solicitudes-fondo', { params });
        solicitudes.value = response.data.solicitudes;

        console.log(`📥 Solicitudes cargadas: ${solicitudes.value.length}`);

        // Resetear a la primera página cuando se aplican filtros
        paginaActual.value = 1;

    } catch (error) {
        console.error('❌ Error al obtener solicitudes:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error al cargar solicitudes',
            text: 'No se pudieron cargar las solicitudes. Por favor, inténtalo de nuevo.',
            confirmButtonText: 'Ok'
        });
    } finally {
        cargandoSolicitudes.value = false;
        buscandoSolicitudes.value = false; // Resetear indicador de búsqueda pendiente
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

const cerrarGestionModal = (refresh = false) => {
    mostrarGestionModal.value = false;
    solicitudGestionSeleccionada.value = null;
    if (refresh) {
        obtenerSolicitudes(); // Refrescar la tabla si se hizo una gestión
    }
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
    obtenerUsuarioAutenticado();
    obtenerSolicitudes();
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Seguimiento de Solicitudes de Fondos</h2>

        <div v-if="cargandoUsuario || cargandoSolicitudes" class="text-center text-gray-500 py-8">
            <div class="inline-flex items-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
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
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
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
                            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
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
                <div v-if="buscandoSolicitudes && (busquedaNumeroSolicitud.length >= MIN_SEARCH_LENGTH || busquedaSolicitante.length >= MIN_SEARCH_LENGTH || filtroFechaInicio || filtroFechaFin)" class="mt-3 text-sm text-verde-bap flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-verde-bap" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Buscando solicitudes...
                </div>
            </div>

            <!-- Mensaje cuando no hay resultados -->
            <div v-if="solicitudesFiltradas.length === 0 && !cargandoSolicitudes" class="text-center text-gray-500 py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-lg font-medium">No se encontraron solicitudes</p>
                <p class="text-sm text-gray-400 mt-1">
                    {{ hayFiltrosActivos ? 'Intenta ajustar los filtros de búsqueda' : 'No hay solicitudes registradas' }}
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
                            <tr class="bg-gray-100 text-gray-700 uppercase text-sm leading-normal">
                                <th class="py-4 px-4 text-center font-semibold">NRO SOLICITUD</th>
                                <th class="py-4 px-4 text-center font-semibold">Tipo</th>
                                <th class="py-4 px-4 text-center font-semibold">Monto</th>
                                <th class="py-4 px-4 text-center font-semibold">Prioridad</th>
                                <th class="py-4 px-4 text-center font-semibold">Estado</th>
                                <th class="py-4 px-4 text-center font-semibold">Solicitante</th>
                                <th class="py-4 px-4 text-center font-semibold">Fecha Creación</th>
                                <th class="py-4 px-4 text-center font-semibold w-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 text-sm">
                            <tr v-for="solicitud in solicitudesMostradas" :key="solicitud.id"
                                class="border-b border-gray-200 hover:bg-gray-50 transition-colors duration-200">
                                <td class="py-4 px-4 text-center font-medium whitespace-nowrap">{{ solicitud.codigo_solicitud ||
                                    solicitud.id }}</td>
                                <td class="py-4 px-4 text-center">{{ solicitud.tipo_solicitud || 'N/A' }}</td>
                                <td class="py-4 px-4 text-center font-medium whitespace-nowrap">S/. {{ solicitud.monto_solicitado ?
                                    parseFloat(solicitud.monto_solicitado).toFixed(2) : '0.00' }}</td>
                                <td class="py-4 px-4 text-center">{{ solicitud.prioridad || 'N/A' }}</td>
                                <td class="py-4 px-4 text-center">
                                    <span :class="{
                                        'bg-estado-creada text-estado-creada-text': solicitud.estado === 'Creada',
                                        'bg-estado-pendiente text-estado-pendiente-text': solicitud.estado === 'Pendiente Aprobación ADM' || solicitud.estado === 'Pendiente Aprobación GRTE',
                                        'bg-estado-observada text-estado-observada-text': solicitud.estado.includes('Observada'),
                                        'bg-estado-descargo text-estado-descargo-text': solicitud.estado.includes('Descargo Enviado'),
                                        'bg-estado-aprobada-adm text-estado-aprobada-adm-text': solicitud.estado === 'Aprobada ADM',
                                        'bg-estado-aprobada-final text-estado-aprobada-final-text': solicitud.estado === 'Aprobada',
                                        'bg-estado-rechazada text-estado-rechazada-text': solicitud.estado === 'Rechazada Final',
                                    }" class="py-2 px-3 rounded-full text-xs font-semibold inline-block text-center w-40">
                                        {{ solicitud.estado }}
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-center">{{ solicitud.solicitante?.name || 'N/A' }} {{
                                    solicitud.solicitante?.last_name || '' }}</td>
                                <td class="py-4 px-4 text-center">
                                    {{ new Date(solicitud.created_at).toLocaleDateString('es-ES') }}
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button @click="verDetalles(solicitud)"
                                            class="w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center text-gray-700 transition-colors duration-200"
                                            title="Ver Detalles">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>

                                        <button @click="verHistorial(solicitud)"
                                            class="w-8 h-8 rounded-full bg-blue-200 hover:bg-blue-300 flex items-center justify-center text-blue-700 transition-colors duration-200"
                                            title="Ver Historial de Estados">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </button>

                                        <button v-if="puedeGestionarSolicitud(solicitud)" @click="abrirGestionModal(solicitud)"
                                            class="w-8 h-8 rounded-full bg-verde-bap-dark hover:bg-verde-bap flex items-center justify-center text-white transition-colors duration-200"
                                            title="Gestionar Solicitud">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                class="w-4 h-4">
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
                        <button v-for="pagina in Math.min(totalPaginas, 5)" :key="pagina" @click="irAPagina(pagina)" :class="[
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
            @close="cerrarDetalleModal" />

        <HistorialEstadosModal :mostrar="mostrarHistorialModal" :solicitud="solicitudHistorialSeleccionada"
            @close="cerrarHistorialModal" />

        <!-- Se pasa el objeto usuarioActual al componente GestionSolicitudModal -->
        <GestionSolicitudModal :mostrar="mostrarGestionModal" :solicitud="solicitudGestionSeleccionada"
            :usuarioActual="usuarioActual" @close="cerrarGestionModal" />
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
