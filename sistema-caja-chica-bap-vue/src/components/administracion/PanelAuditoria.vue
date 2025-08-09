<template>
    <div class="p-4 sm:p-6">
        <!-- Header con estadísticas -->
        <div class="mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Log de Auditoría del Sistema</h2>
                    <p class="text-gray-500 mt-1 md:mt-0">Historial de cambios y acciones administrativas.</p>
                    
                </div>

                <div class="flex flex-col sm:flex-row gap-2 mt-4 md:mt-0">

                    <button @click="limpiarFiltros" :disabled="!hayFiltrosActivos"
                        class="btn-outline flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                        <RotateCcw class="w-4 h-4 mr-2" />
                        Limpiar Filtros
                    </button>
                    <button @click="exportarLogs" :disabled="exportando || cargando"
                        class="px-4 py-2 bg-verde-bap text-white rounded-md hover:bg-verde-bap-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-verde-bap transition-colors duration-150 flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                        <Download v-if="!exportando" class="w-4 h-4 mr-2" />
                        <svg v-else class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        {{ exportando ? 'Exportando...' : 'Exportar a Excel' }}
                    </button>
                    <button @click="$emit('close')"
                        class="p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                        <X class="w-5 h-5 text-gray-500" />
                    </button>
                </div>
            </div>

            <!-- Tarjetas de estadísticas -->
            <div v-if="!cargandoEstadisticas && estadisticas.resumen"
                class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-6">
                <!-- Total Registros -->
                <div
                    class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-xl shadow-lg border border-blue-200 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center shadow-sm">
                                    <FileText class="w-6 h-6 text-blue-600" />
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-blue-600/80">Total Registros</p>
                                <p class="text-2xl font-bold text-blue-700">{{ getStatValue('total_registros') }}</p>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="w-3 h-3 bg-blue-400 rounded-full animate-pulse"></div>
                        </div>
                    </div>
                </div>

                <!-- Actividad Hoy -->
                <div
                    class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-xl shadow-lg border border-green-200 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center shadow-sm">
                                    <Clock class="w-6 h-6 text-green-600" />
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-green-600/80">Actividad Hoy</p>
                                <p class="text-2xl font-bold text-green-700">{{ getStatValue('actividad_hoy') }}</p>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="flex flex-col space-y-1">
                                <div class="w-1 h-3 bg-green-400 rounded-full"></div>
                                <div class="w-1 h-2 bg-green-300 rounded-full"></div>
                                <div class="w-1 h-4 bg-green-500 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Esta Semana -->
                <div
                    class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-xl shadow-lg border border-purple-200 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center shadow-sm">
                                    <Calendar class="w-6 h-6 text-purple-600" />
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-purple-600/80">Esta Semana</p>
                                <p class="text-2xl font-bold text-purple-700">{{ getStatValue('actividad_semana') }}</p>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 relative">
                                <div class="absolute inset-0 bg-purple-200 rounded-full"></div>
                                <div class="absolute inset-1 bg-purple-400 rounded-full animate-ping opacity-75"></div>
                                <div class="absolute inset-2 bg-purple-500 rounded-full"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usuarios Activos -->
                <div
                    class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-6 rounded-xl shadow-lg border border-yellow-200 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center shadow-sm">
                                    <Users class="w-6 h-6 text-yellow-600" />
                                </div>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-yellow-600/80">Usuarios Activos</p>
                                <p class="text-2xl font-bold text-yellow-700">{{ getStatValue('usuarios_activos') }}</p>
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            <div class="flex space-x-1">
                                <div class="w-2 h-2 bg-yellow-400 rounded-full animate-bounce"></div>
                                <div class="w-2 h-2 bg-yellow-500 rounded-full animate-bounce"
                                    style="animation-delay: 0.1s"></div>
                                <div class="w-2 h-2 bg-yellow-600 rounded-full animate-bounce"
                                    style="animation-delay: 0.2s"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Indicador de carga para estadísticas -->
            <div v-else-if="cargandoEstadisticas" class="grid grid-cols-1 md:grid-cols-4 gap-6 mt-6">
                <div v-for="i in 4" :key="i"
                    class="bg-white p-6 rounded-xl shadow-lg border border-gray-200 animate-pulse">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-gray-200 rounded-xl"></div>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="h-4 bg-gray-200 rounded mb-2"></div>
                            <div class="h-6 bg-gray-200 rounded w-2/3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de filtros mejorado -->
        <div class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm border border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="form-group">
                    <label class="form-label" for="audit-fecha-inicio">Desde</label>
                    <input type="date" id="audit-fecha-inicio" v-model="filtros.fecha_inicio" class="form-input"
                        :max="filtros.fecha_fin || new Date().toISOString().split('T')[0]" />
                </div>

                <div class="form-group">
                    <label class="form-label" for="audit-fecha-fin">Hasta</label>
                    <input type="date" id="audit-fecha-fin" v-model="filtros.fecha_fin" class="form-input"
                        :min="filtros.fecha_inicio" :max="new Date().toISOString().split('T')[0]" />
                </div>

                <div class="form-group">
                    <label class="form-label" for="audit-usuario">Usuario</label>
                    <select id="audit-usuario" v-model="filtros.user_id" class="form-input">
                        <option :value="null">Todos los Usuarios</option>
                        <option v-for="usuario in opcionesFiltro.usuarios" :key="usuario.value" :value="usuario.value">
                            {{ usuario.label }}
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="audit-modulo">Módulo</label>
                    <select id="audit-modulo" v-model="filtros.subject_type" class="form-input">
                        <option :value="null">Todos los Módulos</option>
                        <option v-for="tipo in opcionesFiltro.tipos_modelo" :key="tipo.value" :value="tipo.value">
                            {{ tipo.label }}
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="audit-accion">Acción</label>
                    <select id="audit-accion" v-model="filtros.action_type" class="form-input">
                        <option :value="null">Todas las Acciones</option>
                        <option v-for="accion in opcionesFiltro.tipos_accion" :key="accion.value" :value="accion.value">
                            {{ accion.label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Filtros rápidos -->
            <div class="mt-4 flex flex-wrap gap-2" v-if="!cargandoOpciones">
                <span class="text-sm text-gray-600 mr-2">Filtros rápidos:</span>
                <button @click="aplicarFiltroRapido('fecha_inicio', new Date().toISOString().split('T')[0])"
                    class="px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded-full hover:bg-blue-200 transition-colors">
                    Hoy
                </button>
                <button
                    @click="aplicarFiltroRapido('fecha_inicio', new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0])"
                    class="px-3 py-1 text-xs bg-blue-100 text-blue-800 rounded-full hover:bg-blue-200 transition-colors">
                    Última semana
                </button>
                <button @click="aplicarFiltroRapido('action_type', 'CREADO')"
                    class="px-3 py-1 text-xs bg-green-100 text-green-800 rounded-full hover:bg-green-200 transition-colors">
                    Solo creados
                </button>
                <button @click="aplicarFiltroRapido('action_type', 'ACTUALIZADO')"
                    class="px-3 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full hover:bg-yellow-200 transition-colors">
                    Solo modificaciones
                </button>
            </div>
        </div>

        <!-- Estado de carga -->
        <div v-if="cargando" class="text-center py-16">
            <div class="inline-flex items-center text-gray-600">
                <svg class="animate-spin -ml-1 mr-3 h-8 w-8" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="text-lg">Cargando registros de auditoría...</span>
            </div>
        </div>

        <!-- Estado vacío -->
        <div v-else-if="!logs.length" class="text-center py-16 bg-white rounded-lg shadow">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="mt-4 text-lg font-medium text-gray-700">No se encontraron registros</h3>
            <p class="text-sm text-gray-500 mt-2">
                {{ hayFiltrosActivos ? 'Intenta ajustar los filtros de búsqueda.' : 'Aún no hay actividad registrada en el sistema.' }}
            </p>
            <button v-if="hayFiltrosActivos" @click="limpiarFiltros" class="mt-4 btn-outline">
                Limpiar filtros
            </button>
        </div>

        <!-- Tabla de logs mejorada -->
        <div v-else>
            <div class="overflow-x-auto shadow-lg rounded-lg bg-white">
                <table class="min-w-full border border-gray-200">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100 text-gray-700">
                        <tr>
                            <th class="py-4 px-4 text-left font-semibold text-sm uppercase tracking-wider">Fecha y Hora
                            </th>
                            <th class="py-4 px-4 text-left font-semibold text-sm uppercase tracking-wider">Usuario</th>
                            <th class="py-4 px-4 text-center font-semibold text-sm uppercase tracking-wider">Acción</th>
                            <th class="py-4 px-4 text-left font-semibold text-sm uppercase tracking-wider">Módulo</th>
                            <th class="py-4 px-4 text-left font-semibold text-sm uppercase tracking-wider">Descripción
                            </th>
                            <th class="py-4 px-4 text-center font-semibold text-sm uppercase tracking-wider">Detalles
                            </th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm divide-y divide-gray-200">
                        <template v-for="log in logs" :key="log.id">
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ formatDateTime(log.fecha) }}
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div
                                                class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                <User class="h-5 w-5 text-blue-600" />
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ log.usuario }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <span :class="getBadgeClass(log.accion)">
                                        {{ log.accion }}
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ log.modulo }}
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="text-sm text-gray-900 max-w-xs truncate" :title="log.descripcion">
                                        {{ log.descripcion }}
                                    </div>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <button v-if="log.detalles_disponibles" @click="toggleDetails(log.id)"
                                        class="inline-flex items-center p-2 rounded-full text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all duration-150"
                                        :class="{ 'bg-gray-100 text-gray-600': logAbierto === log.id }">
                                        <svg class="w-5 h-5 transform transition-transform duration-200"
                                            :class="{ 'rotate-180': logAbierto === log.id }" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>
                                    <span v-else class="text-xs text-gray-400">Sin detalles</span>
                                </td>
                            </tr>

                            <!-- Fila expandible para detalles -->
                            <tr v-if="logAbierto === log.id" class="bg-gray-50">
                                <td colspan="6" class="p-0">
                                    <div class="px-4 py-4 border-t border-gray-200">
                                        <LogDetailsTable :log="log" />
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Paginación mejorada -->
            <div v-if="pagination.total > 0"
                class="mt-6 flex flex-col sm:flex-row justify-between items-center bg-white px-4 py-3 border border-gray-200 rounded-lg">
                <div class="flex items-center text-sm text-gray-500 mb-2 sm:mb-0">
                    <span class="mr-2">Mostrando</span>
                    <span class="font-medium text-gray-700">{{ pagination.from }}</span>
                    <span class="mx-1">a</span>
                    <span class="font-medium text-gray-700">{{ pagination.to }}</span>
                    <span class="mx-1">de</span>
                    <span class="font-medium text-gray-700">{{ pagination.total.toLocaleString() }}</span>
                    <span class="ml-1">registros</span>
                </div>

                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500">Items por página:</span>
                    <div class="relative">
                        <select v-model="itemsPorPagina"
                            class="appearance-none text-sm border border-gray-300 rounded-md px-3 py-1.5 pr-8 bg-white focus:border-verde-bap focus:ring-1 focus:ring-verde-bap cursor-pointer">
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                        </select>

                    </div>
                </div>

                <div class="flex space-x-1">
                    <button v-for="link in pagination.links" :key="link.label" @click="cambiarPagina(link.url)"
                        :disabled="!link.url" :class="[
                            'px-3 py-2 text-sm rounded-md transition-colors duration-150',
                            {
                                'bg-verde-bap text-white shadow-sm': link.active,
                                'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50': !link.active && link.url,
                                'bg-gray-100 text-gray-400 cursor-not-allowed border border-gray-200': !link.url
                            }
                        ]" v-html="link.label"></button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import LogDetailsTable from './LogDetailsTable.vue';
import { FileText, Clock, Calendar, Users, User, RotateCcw, Download, X } from 'lucide-vue-next';
import { getClassesForAuditoriaBadge } from '@/utils/statusStyles.js';

const emit = defineEmits(['close']);
// --- ESTADO REACTIVO ---
const cargando = ref(true);
const cargandoOpciones = ref(false);
const cargandoEstadisticas = ref(false);
const exportando = ref(false);

// Datos principales
const logs = ref([]);
const pagination = ref({});
const logAbierto = ref(null);
const estadisticas = ref({});

// Opciones para filtros
const opcionesFiltro = ref({
    usuarios: [],
    tipos_modelo: [],
    tipos_accion: []
});

// Filtros aplicados
const filtros = ref({
    fecha_inicio: '',
    fecha_fin: '',
    user_id: null,
    subject_type: null,
    action_type: null
});

// Configuración de paginación
const itemsPorPagina = ref(15);

// --- COMPUTED PROPERTIES ---
const hayFiltrosActivos = computed(() => {
    return Object.values(filtros.value).some(value => value !== null && value !== '');
});

const totalPaginas = computed(() => {
    return pagination.value.last_page || 1;
});

const paginaActual = computed(() => {
    return pagination.value.current_page || 1;
});

// --- MÉTODOS DE OBTENCIÓN DE DATOS ---
const fetchActivityLogs = async (page = 1) => {
    cargando.value = true;
    try {
        const params = {
            ...filtros.value,
            page,
            per_page: itemsPorPagina.value
        };

        const response = await api.get('/v1/activity-logs', { params });

        if (response.data.success) {
            logs.value = response.data.data.data || [];

            // Procesar paginación
            const paginationData = response.data.data;
            pagination.value = {
                from: paginationData.from,
                to: paginationData.to,
                total: paginationData.total,
                current_page: paginationData.current_page,
                last_page: paginationData.last_page,
                per_page: paginationData.per_page,
                links: procesarEnlacesPaginacion(paginationData.links || [])
            };
        } else {
            throw new Error(response.data.message || 'Error al cargar logs');
        }
    } catch (error) {
        console.error("Error al cargar el log de auditoría:", error);
        mostrarError('No se pudieron cargar los registros de auditoría');
        logs.value = [];
        pagination.value = {};
    } finally {
        cargando.value = false;
    }
};

const fetchOpcionesFiltro = async () => {
    if (opcionesFiltro.value.usuarios.length > 0) return; // Ya cargadas

    cargandoOpciones.value = true;
    try {
        const response = await api.get('/v1/activity-logs/filter-options');

        if (response.data.success) {
            opcionesFiltro.value = response.data.data;
        }
    } catch (error) {
        console.error("Error al cargar opciones de filtro:", error);
        mostrarError('Error al cargar opciones de filtro');
    } finally {
        cargandoOpciones.value = false;
    }
};

const fetchEstadisticas = async () => {
    cargandoEstadisticas.value = true;
    try {
        const response = await api.get('/v1/activity-logs/stats', {
            params: filtros.value
        });

        if (response.data.success) {
            estadisticas.value = response.data.data;
        }
    } catch (error) {
        console.error("Error al cargar estadísticas:", error);
        // No mostrar error para estadísticas, es información adicional
    } finally {
        cargandoEstadisticas.value = false;
    }
};

const fetchLogDetalle = async (logId) => {
    try {
        const response = await api.get(`/v1/activity-logs/${logId}`, {
            params: filtros.value
        });

        if (response.data.success) {
            return response.data.data;
        }
        throw new Error('No se pudo cargar el detalle');
    } catch (error) {
        console.error(`Error al cargar detalle del log ${logId}:`, error);
        mostrarError('Error al cargar los detalles del registro');
        return null;
    }
};

// --- MÉTODOS DE EXPORTACIÓN ---
const exportarLogs = async () => {
    if (exportando.value) return;

    exportando.value = true;

    try {
        // Mostrar confirmación si hay muchos registros
        if (pagination.value.total > 1000) {
            const result = await Swal.fire({
                title: 'Exportación Grande',
                text: `Se exportarán ${pagination.value.total.toLocaleString()} registros. ¿Continuar?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, exportar',
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) {
                return;
            }
        }

        const response = await api.get('/v1/activity-logs/export', {
            params: filtros.value,
            responseType: 'blob'
        });

        // Verificar si la respuesta es un error JSON en lugar de un archivo
        if (response.data.type === 'application/json') {
            const errorText = await response.data.text();
            const errorData = JSON.parse(errorText);
            throw new Error(errorData.message || 'Error al exportar');
        }

        // Crear enlace de descarga
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;

        // Nombre de archivo con filtros aplicados
        const fileName = generarNombreArchivo();
        link.setAttribute('download', fileName);

        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);

        mostrarExito('Reporte exportado correctamente');

    } catch (error) {
        console.error("Error al exportar:", error);

        // Si el error viene de la API y es un blob (que podría ser un JSON de error)
        if (error.response && error.response.data instanceof Blob && error.response.data.type === 'application/json') {
            const errorText = await error.response.data.text();
            const errorData = JSON.parse(errorText);
            mostrarError(errorData.message || 'Error en la exportación.');
        } else if (error.response && error.response.data.message) {
            // Error JSON estándar de la API
            mostrarError(error.response.data.message);
        } else {
            // Error genérico
            mostrarError('No se pudo generar el reporte. Intente con filtros más específicos.');
        }
    } finally {
        exportando.value = false;
    }
};

// --- MÉTODOS DE FORMATO Y UTILIDAD ---
const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
};

const formatSubjectType = (subject) => {
    if (!subject) return 'N/A';
    return subject.split('\\').pop();
};

const formatActionType = (action) => {
    const actionNames = {
        'CREADO': 'Creado',
        'ACTUALIZADO': 'Actualizado',
        'ELIMINADO': 'Eliminado',
        'PERIODO_CERRADO': 'Período Cerrado',
        'PERIODO_REABIERTO': 'Período Reabierto',
        'EXCEPCION_OTORGADA': 'Excepción Otorgada',
        'EXCEPCION_REVOCADA': 'Excepción Revocada'
    };

    return actionNames[action] || action;
};

const getBadgeClass = (actionType) => {
    const actionThemeMap = {
        'CREADO': 'exito',
        'ACTUALIZADO': 'info',
        'ELIMINADO': 'error',
        'PERIODO_CERRADO': 'advertencia',
        'PERIODO_REABIERTO': 'exito',
        'EXCEPCION_OTORGADA': 'alerta',
        'EXCEPCION_REVOCADA': 'error',
    };

    const theme = actionThemeMap[actionType] || actionType;
    return getClassesForAuditoriaBadge(theme);
};
// --- FUNCIONES PARA ESTILOS DE ESTADÍSTICAS ---
const getStatsCardConfig = (tipo) => {
    const configuraciones = {
        total: {
            tema: 'info',
            icono: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            gradiente: 'from-blue-50 to-blue-100',
            borde: 'border-blue-200',
            fondo_icono: 'bg-blue-100',
            color_icono: 'text-blue-600',
            color_numero: 'text-blue-700'
        },
        actividad_hoy: {
            tema: 'exito',
            icono: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            gradiente: 'from-green-50 to-green-100',
            borde: 'border-green-200',
            fondo_icono: 'bg-green-100',
            color_icono: 'text-green-600',
            color_numero: 'text-green-700'
        },
        actividad_semana: {
            tema: 'validacionDj',
            icono: 'M8 7V3a2 2 0 012-2h4a2 2 0 012 2v4m-6 4v4a2 2 0 002 2h2a2 2 0 002-2v-4M8 11h8',
            gradiente: 'from-purple-50 to-purple-100',
            borde: 'border-purple-200',
            fondo_icono: 'bg-purple-100',
            color_icono: 'text-purple-600',
            color_numero: 'text-purple-700'
        },
        usuarios_activos: {
            tema: 'alerta',
            icono: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z',
            gradiente: 'from-yellow-50 to-yellow-100',
            borde: 'border-yellow-200',
            fondo_icono: 'bg-yellow-100',
            color_icono: 'text-yellow-600',
            color_numero: 'text-yellow-700'
        }
    };

    return configuraciones[tipo] || configuraciones.total;
};

// Función para obtener el valor de estadística con animación
const getStatValue = (key) => {
    if (!estadisticas.value.resumen) return 0;
    const value = estadisticas.value.resumen[key] || 0;
    return typeof value === 'number' ? value.toLocaleString() : value;
};

// Función para determinar si una estadística está creciendo (para efectos visuales)
const getGrowthIndicator = (tipo) => {
    // Esto podrías expandirlo con datos de comparación si los tienes
    const crecientes = ['actividad_hoy', 'usuarios_activos'];
    return crecientes.includes(tipo);
};
const procesarEnlacesPaginacion = (links) => {
    return links.map(link => {
        if (link.url) {
            // Corregir URLs de Docker para desarrollo local
            link.url = link.url.replace('http://caja-chica-app', 'http://localhost:8080');
        }
        return link;
    });
};

const generarNombreArchivo = () => {
    const fecha = new Date().toISOString().slice(0, 10);
    let nombre = `auditoria_${fecha}`;

    if (filtros.value.fecha_inicio) {
        nombre += `_desde_${filtros.value.fecha_inicio}`;
    }

    if (filtros.value.fecha_fin) {
        nombre += `_hasta_${filtros.value.fecha_fin}`;
    }

    return `${nombre}.xlsx`;
};

// --- MÉTODOS DE INTERACCIÓN ---
const toggleDetails = async (logId) => {
    if (logAbierto.value === logId) {
        logAbierto.value = null;
        return;
    }

    logAbierto.value = logId;

    // Cargar detalles si es necesario
    const logIndex = logs.value.findIndex(log => log.id === logId);
    if (logIndex !== -1 && !logs.value[logIndex].detalles_cargados) {
        const detalles = await fetchLogDetalle(logId);
        if (detalles) {
            logs.value[logIndex] = { ...logs.value[logIndex], ...detalles, detalles_cargados: true };
        }
    }
};

const cambiarPagina = (url) => {
    if (!url) return;

    try {
        const urlObject = new URL(url);
        const params = new URLSearchParams(urlObject.search);
        const page = params.get('page') || 1;

        fetchActivityLogs(parseInt(page));
    } catch (error) {
        console.error("Error en paginación:", error);
        mostrarError('Error al cambiar de página');
    }
};

const limpiarFiltros = () => {
    filtros.value = {
        fecha_inicio: '',
        fecha_fin: '',
        user_id: null,
        subject_type: null,
        action_type: null
    };

    fetchActivityLogs(1);
};

const aplicarFiltroRapido = (filtro, valor) => {
    filtros.value[filtro] = valor;
};

// --- MÉTODOS DE UI ---
const mostrarError = (mensaje) => {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: mensaje,
        confirmButtonText: 'Entendido'
    });
};

const mostrarExito = (mensaje) => {
    Swal.fire({
        icon: 'success',
        title: 'Éxito',
        text: mensaje,
        timer: 3000,
        showConfirmButton: false
    });
};

const mostrarAdvertencia = (mensaje) => {
    return Swal.fire({
        icon: 'warning',
        title: 'Advertencia',
        text: mensaje,
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Cancelar'
    });
};

// --- FORMATEO DE DATOS ESPECÍFICO ---
const formatChangedData = (properties) => {
    if (!properties || !properties.cambios) return 'Sin cambios específicos';

    return properties.cambios.map(cambio =>
        `${cambio.campo}: "${cambio.valor_anterior}" → "${cambio.valor_nuevo}"`
    ).join('\n');
};

const obtenerUsuarioNombre = (userId) => {
    if (!userId) return 'Sistema Automático';

    const usuario = opcionesFiltro.value.usuarios.find(u => u.value === userId);
    return usuario ? usuario.label : `Usuario ID: ${userId}`;
};

// --- CICLO DE VIDA Y WATCHERS ---
onMounted(async () => {
    await Promise.all([
        fetchActivityLogs(1),
        fetchOpcionesFiltro(),
        fetchEstadisticas()
    ]);
});

// Debounce para filtros
let debounceTimeout;
watch(filtros, () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        fetchActivityLogs(1);
        fetchEstadisticas();
    }, 500);
}, { deep: true });

// Watch para items per page
watch(itemsPorPagina, () => fetchActivityLogs(1));

// Exponer métodos que podrían necesitar otros componentes
defineExpose({
    refrescar: () => fetchActivityLogs(paginaActual.value),
    limpiarFiltros,
    exportar: exportarLogs
});
</script>

<style scoped>
.form-input {
    @apply w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap transition;
}

.form-group {
    @apply flex flex-col gap-1;
}

.form-label {
    @apply block text-sm font-medium text-gray-700;
}

.btn-outline {
    @apply px-4 py-2 border border-gray-300 text-gray-700 bg-white rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150;
}

.btn-secondary {
    @apply px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-150;
}
</style>
