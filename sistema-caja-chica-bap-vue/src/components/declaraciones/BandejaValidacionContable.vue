<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { useRoute, useRouter } from 'vue-router';
import GastoDetalleModal from './modals/GastoDetalleModal.vue';
import { getClassesForAuditoriaBadge } from '@/utils/statusStyles.js';


// --- ESTADO DEL COMPONENTE ---
const props = defineProps({
    usuarioActual: {
        type: Object,
        required: true
    }
});

// --- LÓGICA DE PERMISOS ---
const hasPermission = (permissionName) => {
    if (!props.usuarioActual?.role?.permissions) {
        return false;
    }
    return props.usuarioActual.role.permissions.some(p => p.name === permissionName);
};

// --- ESTADO DE DATOS ---
const items = ref([]);
const areas = ref([]);
const cargando = ref(true);
const buscando = ref(false);
const exportando = ref(false);
const router = useRouter();
const route = useRoute();
const inicializacionCompleta = ref(false);

// --- ESTADO DE FILTROS ---
const filtros = ref({
    codigo_gasto: '',
    registrador_name: '',
    fecha_inicio: '',
    fecha_fin: '',
    estado: 'Todos', // 'Pendiente de Validación DJ', 'Pendiente de Validación Contable', 'Observado', 'Rechazado', 'Contabilizado'
    area_id: '',
});

// --- CONSTANTES PARA FILTROS ---
let debounceTimeout = null;
const DEBOUNCE_DELAY = 500;
const MIN_SEARCH_LENGTH = 3;

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
    // Asegurarse de que la fecha sea un objeto Date válido antes de formatear
    const date = new Date(dateString);
    if (isNaN(date)) return 'N/A';
    return date.toLocaleDateString('es-PE');
};

// --- PROPIEDADES COMPUTADAS ---
const hayFiltrosActivos = computed(() => {
    return Object.values(filtros.value).some(value => value && String(value).trim() !== '' && value !== 'Todos');
});

// Lógica de filtrado unificada para items (individuales o grupos DJ)
const itemsFiltrados = computed(() => {
    let data = [...items.value]; // Copia de los ítems originales
    const codigoBusqueda = filtros.value.codigo_gasto.toLowerCase().trim();
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
            // No hay un 'codigo_gasto' o 'glosa' directo en el nivel superior del grupo para filtrar por ellos aquí.
            // La búsqueda de texto para grupos se maneja dentro del bloque 'if (codigoBusqueda)'
        } else if (item.gasto) {
            fechaItem = item.gasto.created_at;
            registradorItem = item.gasto.registrador;
            codigoGastoItem = item.gasto.codigo_gasto?.toLowerCase();
            glosaItem = item.gasto.glosa?.toLowerCase();
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

        if (!pasaFecha || !pasaRegistrador || !pasaEstado || !pasaArea) {
            return false;
        }

        // Aplicar filtro por código/glosa (texto)
        if (codigoBusqueda.length >= MIN_SEARCH_LENGTH) {
            if (esGrupo && item.gastos) {
                return item.gastos.some(g =>
                    g.codigo_gasto?.toLowerCase().includes(codigoBusqueda) ||
                    g.glosa?.toLowerCase().includes(codigoBusqueda)
                );
            } else if (!esGrupo && item.gasto) {
                return codigoGastoItem.includes(codigoBusqueda) || glosaItem.includes(codigoBusqueda);
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


// --- MÉTODOS DE PERMISOS PARA ACCIONES ---
const canFinalizeGasto = (item) => {
    const tienePermiso = hasPermission('declaraciones.approve.adm');
    if (item.es_grupo) {
        return tienePermiso && item.estado_grupo === 'Pendiente de Validación Contable';
    } else {
        return tienePermiso && item.gasto?.estado === 'Pendiente de Validación Contable';
    }
};

const canObserveGastoAdm = (item) => {
    const tienePermiso = hasPermission('declaraciones.approve.adm');
    const estadosValidos = ['Pendiente de Validación Contable', 'Pendiente de Validación DJ'];
    if (item.es_grupo) {
        // La acción de observar siempre es a nivel de gasto individual, no de grupo completo.
        // Por lo tanto, el botón de observar no debe aparecer en la fila principal del grupo.
        return false;
    } else {
        return tienePermiso && estadosValidos.includes(item.gasto?.estado);
    }
};

const canRejectGastoAdm = (item) => {
    const tienePermiso = hasPermission('declaraciones.approve.adm');
    // Un administrador puede rechazar si está Pendiente de Validación DJ o Pendiente de Validación Contable
    const estadosValidos = ['Pendiente de Validación Contable', 'Pendiente de Validación DJ'];
    if (item.es_grupo) {
        return tienePermiso && estadosValidos.includes(item.estado_grupo);
    } else {
        return tienePermiso && estadosValidos.includes(item.gasto?.estado);
    }
};

const canValidateDjDocument = (item) => {
    const tienePermiso = hasPermission('declaraciones.approve.adm');
    // Solo aplica a grupos y solo si está en estado 'Pendiente de Validación DJ'
    return tienePermiso && item.es_grupo && item.estado_grupo === 'Pendiente de Validación DJ';
};

// --- MÉTODOS DE DATOS Y ACCIONES ---
const fetchGastos = async () => {
    cargando.value = true;
    try {
        const params = { ...filtros.value, scope: 'aprobaciones' }; // Asegurarse de enviar el scope correcto
        // Los filtros de longitud mínima se aplican en la propiedad computada itemsFiltrados
        // y en los watchers, no es necesario borrarlos aquí.

        const response = await api.get('/v1/gastos/para-aprobacion', { params });
        items.value = response.data; // La data ya viene lista y agrupada/formateada del backend

        // Ajusta la página actual si es necesario después de cargar los datos
        if (paginaActual.value > totalPaginas.value && totalPaginas.value > 0) {
            paginaActual.value = totalPaginas.value;
        } else if (totalPaginas.value === 0) {
            paginaActual.value = 1; // Si no hay páginas, siempre en la primera
        }

    } catch (error) {
        console.error("Error al cargar gastos para validación contable:", error);
        Swal.fire('Error', error.response?.data?.message || 'Ocurrió un error al cargar los gastos.', 'error');
    } finally {
        cargando.value = false;
        buscando.value = false;
    }
};
const aplicarFiltrosDesdeURL = () => {
    const query = route.query;
    // Se busca específicamente la alerta de 'monto_inusual' y los códigos.
    if (query.alerta === 'monto_inusual' && query.codigos) {
        filtros.value.codigo_gasto = query.codigos;

        // Opcional: Mostrar un mensaje al usuario para darle contexto.
        Swal.fire({
            title: 'Filtro Aplicado',
            text: `Mostrando los gastos con montos inusuales detectados.`,
            icon: 'info',
            showConfirmButton: true
        });

        // Limpiamos la URL después de aplicar los filtros para que el usuario no vea los parámetros.
        router.replace({ query: {} });
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
        codigo_gasto: '',
        registrador_name: '',
        fecha_inicio: '',
        fecha_fin: '',
        estado: 'Todos',
        area_id: '',

    };
    router.replace({ query: {} });
    // El watcher se encargará de llamar a fetchGastos y resetear la paginación
};

const verDetalles = (item) => {
    // Siempre pasamos el 'item' tal como viene (individual o de grupo).
    // El modal GastoDetalleModal se encargará de mostrar los detalles del gasto individual.
    gastoSeleccionado.value = item;
    mostrarDetalleModal.value = true;
};

const toggleGroup = (groupId) => {
    if (expandedGroups.value.has(groupId)) {
        expandedGroups.value.delete(groupId);
    } else {
        expandedGroups.value.add(groupId);
    }
};

const getGastoDetailsForAction = (item) => {
    let id = null;
    let codigo = 'N/A';
    let isGroup = false;

    if (item.es_grupo) { // Caso 1: Es un objeto de resumen de grupo ({es_grupo: true, ...})
        id = item.id_dj_consolidada;
        codigo = `DJ-${item.id_dj_consolidada}`;
        isGroup = true;
    } else if (item.gasto) { // Caso 2: Es un wrapper de gasto individual ({es_grupo: false, gasto: {...}})
        id = item.gasto.id;
        codigo = item.gasto.codigo_gasto;
        isGroup = false;
    } else { // Caso 3: Es un objeto de gasto directo (como los que vienen de /v1/mis-gastos o dentro de item.gastos[])
        id = item.id;
        codigo = item.codigo_gasto;
        isGroup = false; // Un gasto directo no es un "grupo"
    }

    // Asegurarse de que el ID sea un valor válido antes de retornarlo
    if (id === undefined || id === null) {
        console.error("ERROR: ID del gasto/grupo es nulo o indefinido. Item problemático:", item);
        return { id: null, codigo: 'ID_NO_VALIDO', isGroup: isGroup }; // Retornar un valor nulo para que la acción falle en el frontend
    }

    return { id, codigo, isGroup };
};

// --- Método gestionarAccionAdm (Completo y Refactorizado) ---
const gestionarAccionAdm = async (itemOriginal, accion) => {
    // Usamos el helper para obtener el ID, código y si es grupo de forma segura
    const { id, codigo, isGroup } = getGastoDetailsForAction(itemOriginal);

    if (id === null) { // Si el helper no pudo obtener un ID válido
        Swal.fire('Error', 'No se pudo identificar el gasto para realizar la acción. Por favor, recargue la página.', 'error');
        return;
    }

    let config;
    const endpointPrefix = '/v1';

    // Definición de configuraciones base para cada tipo de acción
    const configBase = {
        finalize: { title: 'Contabilizar', icon: 'success', confirmButtonText: 'Sí, Contabilizar', needsComment: false },
        observe: { title: 'Observar', icon: 'warning', confirmButtonText: 'Sí, Observar', needsComment: true, commentLabel: 'Motivo de la observación:' },
        reject: { title: 'Rechazar', icon: 'error', confirmButtonText: 'Sí, Rechazar', needsComment: true, commentLabel: 'Motivo del rechazo:' },
        validateDjDocument: { title: 'Validar Documento DJ', icon: 'info', confirmButtonText: 'Sí, Validar', needsComment: false }
    };

    // Construcción de la configuración específica para la acción y el tipo de ítem
    switch (accion) {
        case 'finalize': // Contabilizar (individual o grupo)
            config = {
                ...configBase.finalize,
                title: `${configBase.finalize.title} ${isGroup ? 'Grupo de DJ' : 'Gasto'}`,
                text: `¿Estás seguro de finalizar y contabilizar ${isGroup ? 'el grupo de DJ completo' : `el gasto ${codigo}`}? Esta acción descontará el monto del fondo y no se puede revertir.`,
                endpoint: isGroup ? `${endpointPrefix}/dj-groups/${id}/finalize` : `${endpointPrefix}/gastos/${id}/finalize`,
            };
            break;
        case 'observe': // Observar (siempre individual, el backend se encarga de la DJ)
            // Lógica de negocio: No se puede observar un grupo directamente, se debe observar un gasto hijo.
            if (isGroup) {
                Swal.fire('Acción no permitida', 'Para observar un grupo, debe expandirlo y observar un gasto individual específico. Esto invalidará la DJ para su corrección.', 'info');
                return;
            }
            config = {
                ...configBase.observe,
                title: `${configBase.observe.title} Gasto`,
                text: `Vas a devolver el gasto ${codigo} para su corrección.`,
                endpoint: `${endpointPrefix}/gastos/${id}/observe`,
            };
            break;
        case 'reject': // Rechazar (individual o grupo)
            config = {
                ...configBase.reject,
                title: `${configBase.reject.title} ${isGroup ? 'Grupo de DJ' : 'Gasto'} Definitivamente`,
                text: `Esta acción es final. ¿Estás seguro de rechazar ${isGroup ? 'el grupo de DJ completo' : `el gasto ${codigo}`}?`,
                endpoint: isGroup ? `${endpointPrefix}/dj-groups/${id}/reject` : `${endpointPrefix}/gastos/${id}/reject`,
            };
            break;
        case 'validateDjDocument': // Validar Documento DJ (solo aplica a grupos)
            if (!isGroup) {
                Swal.fire('Acción no permitida', 'Esta acción solo aplica a grupos de Declaración Jurada.', 'info');
                return;
            }
            config = {
                ...configBase.validateDjDocument,
                title: `${configBase.validateDjDocument.title} ${codigo}`,
                text: `¿Estás seguro de validar el documento del grupo DJ ${codigo}? Esto moverá los gastos a Pendiente de Validación Contable.`,
                endpoint: `${endpointPrefix}/dj-groups/${id}/validate-document`,
            };
            break;
        default: return; // Si la acción no es reconocida, no hacer nada
    }

    let comentario = '';
    // Si la acción requiere comentario, mostrar el input de SweetAlert
    if (config.needsComment) {
        const { value: text } = await Swal.fire({
            title: config.title,
            input: 'textarea',
            inputLabel: config.commentLabel,
            inputPlaceholder: 'Escribe tu comentario aquí...',
            showCancelButton: true,
            confirmButtonText: config.confirmButtonText,
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => !value && '¡Necesitas escribir un motivo!'
        });
        if (!text) return; // El usuario canceló o no escribió nada.
        comentario = text;
    } else {
        // Si no requiere comentario, mostrar solo el modal de confirmación
        const result = await Swal.fire({
            title: config.title,
            text: config.text,
            icon: config.icon,
            showCancelButton: true,
            confirmButtonColor: '#3085d6', // Color por defecto de confirmación
            cancelButtonColor: '#d33', // Color por defecto de cancelación
            confirmButtonText: config.confirmButtonText,
            cancelButtonText: 'Cancelar'
        });
        if (!result.isConfirmed) return; // El usuario canceló la confirmación
    }

    // Ejecutar la llamada a la API
    try {
        await api.put(config.endpoint, { comentario }); // Todas las acciones son PUT y pueden llevar comentario
        Swal.fire('¡Acción Completada!', 'La operación se realizó con éxito.', 'success');
        fetchGastos(); // Refrescar la tabla para reflejar los cambios de estado
    } catch (error) {
        console.error(`Error al ejecutar la acción ${accion}:`, error);
        Swal.fire('Error', error.response?.data?.message || 'Ocurrió un error inesperado.', 'error');
    }
};

const exportarGastos = async () => {
    exportando.value = true;
    try {
        // Asegurarse de que los filtros se envíen correctamente al backend
        const params = { ...filtros.value, scope: 'exportar' }; // Puedes añadir un scope específico para la exportación si tu backend lo usa

        const response = await api.post('/v1/gastos/exportar', params, {
            responseType: 'blob', // Importante para manejar la descarga de archivos
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const filename = 'reporte_gastos_sap_' + new Date().toISOString().slice(0, 10) + '.xlsx';
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();

        // Limpieza
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        Swal.fire({
            icon: 'success', title: '¡Exportación Exitosa!',
            text: 'El archivo Excel ha sido generado y descargado.',
            timer: 3000, showConfirmButton: false,
        });

        // No es necesario llamar a fetchGastos aquí a menos que la exportación cambie el estado de los gastos en la DB.
        // Si la exportación marca los gastos como "Contabilizado", entonces sí, llama a fetchGastos().
        // Si no, la lista no necesita actualizarse automáticamente por la exportación.
        // fetchGastos(); 

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

// --- WATCHERS Y LIFECYCLE ---
// Watcher principal para filtros que reinicia la paginación y dispara la búsqueda
watch(filtros, () => {
    if (!inicializacionCompleta.value) return;
    triggerSearchWithDebounce();
}, { deep: true });

// Watchers individuales para campos de texto con debounce y longitud mínima
watch(() => filtros.value.codigo_gasto, (newValue) => {
    if (newValue.length === 0 || newValue.length >= MIN_SEARCH_LENGTH) {
        // No se necesita llamar a triggerSearchWithDebounce aquí si el watcher 'filtros' ya lo hace.
        // Si este watcher se dispara antes que el 'deep' de filtros, podría causar doble llamada.
        // Se deja el watcher principal para manejar esto.
    }
});

watch(() => filtros.value.registrador_name, (newValue) => {
    if (newValue.length === 0 || newValue.length >= MIN_SEARCH_LENGTH) {
        // Igual que el anterior, el watcher 'filtros' lo maneja.
    }
});

onMounted(async () => {
    // Primero se aplican los filtros que puedan venir de la URL.
    aplicarFiltrosDesdeURL();
    // Luego se cargan los datos iniciales (que ya respetarán los filtros pre-cargados).
    await fetchGastos();
    await fetchAreas();
    // Finalmente, se marca la inicialización como completa para activar los watchers.
    inicializacionCompleta.value = true;
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md animate-fade-in-up">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800 mb-2">Bandeja de Validación Contable</h2>
            <p class="text-gray-500 max-w-2xl mx-auto">
                Aquí puedes validar documentos de Declaraciones Juradas, contabilizar gastos y gestionar su flujo con tu
                equipo.
            </p>
        </div>

        <!-- Panel de Filtros -->
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
                        <option value="Pendiente de Validación DJ">Pendiente Validación DJ</option>
                        <option value="Pendiente de Validación Contable">Pendiente Contable</option>
                        <option value="Observado">Observado</option>
                        <option value="Rechazado">Rechazado</option>
                        <option value="Contabilizado">Contabilizado</option>
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
        <div v-else-if="!itemsPaginados.length" class="text-center py-16">
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
                    registrosPorPagina, totalItems) }}</strong> de <strong>{{ totalItems }}</strong> registros
            </div>
            <div class="overflow-x-auto shadow-strong rounded-lg">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr class="text-gray-700 uppercase text-xs leading-normal">
                            <th scope="col" class="py-3 px-4 text-center font-semibold"></th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Tipo</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Código</th>
                            <th class="py-3 px-4 text-center font-semibold">Glosa / Descripción</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Monto</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold w-48">Estado</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Registrador</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Fecha Registro</th>
                            <th scope="col" class="py-3 px-4 text-center font-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm">
                        <template v-for="item in itemsPaginados"
                            :key="item.es_grupo ? `grupo-${item.id_dj_consolidada || ''}` : `gasto-${item.gasto?.id || ''}`">
                            <!-- Fila de Grupo DJ -->
                            <tr v-if="item.es_grupo"
                                class="bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 hover:from-blue-100 hover:to-indigo-100 transition-all duration-200">
                                <td class="py-3 px-2 text-center">
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
                                <td class="py-3 px-2">
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
                                <td class="py-3 px-2">
                                    <div class="font-mono text-sm font-medium text-blue-800">DJ-{{
                                        item.id_dj_consolidada }}</div>
                                    <div class="text-xs text-blue-600">Consolidada</div>
                                </td>
                                <td class="py-3 px-2">
                                    <div class="text-sm text-gray-700 font-medium text-center">Gastos consolidados</div>
                                    <div class="text-xs text-gray-500 text-center">Múltiples conceptos de gasto</div>
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <div class="font-bold text-lg text-blue-800">{{
                                        currencyFormatter.format(item.monto_total_grupo || 0) }}</div>
                                    <div class="text-xs text-gray-500">Total consolidado</div>
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <span :class="getClassesForAuditoriaBadge(item.estado_grupo)">{{ item.estado_grupo
                                        }}</span>
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <div class="text-sm font-medium text-gray-900">{{ item.registrador?.name }}</div>
                                    <div class="text-xs text-gray-500">{{ item.registrador?.last_name }}</div>
                                </td>
                                <td class="py-3 px-2 text-center text-gray-500">{{ formatDate(item.fecha_registro) }}
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <div class="flex space-x-1">
                                            <!-- NUEVO: Botón Validar Documento DJ -->
                                            <button v-if="canValidateDjDocument(item)"
                                                @click="gestionarAccionAdm(item, 'validateDjDocument')"
                                                class="p-2 rounded-full bg-blue-100 hover:bg-blue-200 text-blue-600 transition-all duration-300"
                                                title="Validar Documento DJ">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </button>
                                            <!-- Botón Contabilizar Grupo -->
                                            <button v-else-if="canFinalizeGasto(item)"
                                                @click="gestionarAccionAdm(item, 'finalize')"
                                                class="p-2 rounded-full bg-verde-bap-light hover:bg-verde-bap text-verde-bap-dark hover:text-white transition-all duration-300"
                                                title="Contabilizar Grupo DJ">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>

                                        </div>
                                        <div class="flex space-x-1">
                                            <!-- Botón Rechazar Grupo -->
                                            <button v-if="canRejectGastoAdm(item)"
                                                @click="gestionarAccionAdm(item, 'reject')"
                                                class="p-2 rounded-full bg-rojo-bap-light hover:bg-rojo-bap text-rojo-bap-dark hover:text-white transition-all duration-300"
                                                title="Rechazar Grupo DJ">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- Filas de gastos individuales del grupo (solo si está expandido) -->
                            <template v-if="item.es_grupo && expandedGroups.has(item.id_dj_consolidada)">
                                <tr v-for="(gasto, index) in item.gastos" :key="`${item.id_dj_consolidada}-${gasto.id}`"
                                    class="bg-gray-50 hover:bg-gray-100 transition-colors text-xs"
                                    :class="{ 'border-b-2 border-blue-200': index === item.gastos.length - 1 }">
                                    <td class="py-3 px-2 text-center border-l-4 border-blue-400">
                                        <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-600 text-xs font-bold">{{ index + 1 }}</span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2 text-center text-gray-600">
                                        <span class="text-xs bg-gray-200 px-2 py-1 rounded">Parte del grupo</span>
                                    </td>
                                    <td class="py-3 px-2 text-center text-gray-600 font-mono">{{ gasto.codigo_gasto }}
                                    </td>
                                    <td class="py-3 px-2 text-center text-gray-700">{{ gasto.glosa }}</td>
                                    <td class="py-3 px-2 text-center text-gray-800 font-semibold">
                                        {{ currencyFormatter.format(parseFloat(gasto.monto_total || 0)) }}
                                    </td>
                                    <td class="py-3 px-2 text-center">
                                        <span class="text-xs text-gray-500">-</span>
                                    </td>
                                    <td class="py-3 px-2 text-center text-gray-500">-</td>
                                    <td class="py-3 px-2 text-center text-gray-500">-</td>
                                    <td class="py-3 px-2 text-center">
                                        <div class="flex space-x-1 justify-center">
                                            <button @click="gestionarAccionAdm(gasto, 'observe')"
                                                class="p-2 rounded-full bg-orange-100 hover:bg-orange-200 text-orange-600 transition-all duration-300"
                                                title="Observar este Gasto (invalidará la DJ)">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                </svg>
                                            </button>
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
                                            <!-- Botón de Rechazar para gastos individuales en grupos - SE ELIMINA -->
                                        </div>
                                    </td>
                                </tr>
                            </template>

                            <!-- Fila de Gasto Individual (Standalone) -->
                            <tr v-if="!item.es_grupo" class="hover:bg-gray-50 transition-colors">
                                <td class="py-3 px-2">
                                    <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                            </path>
                                        </svg>
                                    </div>
                                </td>
                                <td class="py-3 px-2">
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
                                <td class="py-3 px-2">
                                    <div class="font-mono text-sm font-medium text-verde-bap-dark">{{
                                        item.gasto?.codigo_gasto }}</div>
                                    <div class="text-xs text-verde-bap">Código único</div>
                                </td>
                                <td class="py-3 px-2 text-center text-gray-700">{{ item.gasto?.glosa }}</td>
                                <td class="py-3 px-2 text-center font-semibold text-lg text-verde-bap-dark">
                                    {{ currencyFormatter.format(parseFloat(item.gasto?.monto_total || 0)) }}
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <span :class="getClassesForAuditoriaBadge(item.gasto?.estado)">{{ item.gasto?.estado
                                        }}</span>
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <div class="text-sm font-medium text-gray-900">{{ item.gasto?.registrador?.name }}
                                    </div>
                                    <div class="text-xs text-gray-500">{{ item.gasto?.registrador?.last_name }}</div>
                                </td>
                                <td class="py-3 px-2 text-center text-gray-500">{{ formatDate(item.gasto?.created_at) }}
                                </td>
                                <td class="py-3 px-2 text-center">
                                    <div class="flex flex-col items-center justify-center space-y-2">
                                        <div class="flex space-x-1">
                                            <button @click="verDetalles(item.gasto)"
                                                class="p-2 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 transition-colors"
                                                title="Ver Detalles">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                            <button v-if="canFinalizeGasto(item)"
                                                @click="gestionarAccionAdm(item, 'finalize')"
                                                class="p-2 rounded-full bg-verde-bap-light hover:bg-verde-bap text-verde-bap-dark hover:text-white transition-all duration-300"
                                                title="Contabilizar Gasto">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="flex space-x-1">
                                            <button v-if="canObserveGastoAdm(item)"
                                                @click="gestionarAccionAdm(item, 'observe')"
                                                class="p-2 rounded-full bg-estado-advertencia-bg hover:bg-orange-500 text-estado-advertencia-text hover:text-white transition-all duration-300"
                                                title="Observar Gasto">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                                </svg>
                                            </button>
                                            <button v-if="canRejectGastoAdm(item)"
                                                @click="gestionarAccionAdm(item, 'reject')"
                                                class="p-2 rounded-full bg-rojo-bap-light hover:bg-rojo-bap text-rojo-bap-dark hover:text-white transition-all duration-300"
                                                title="Rechazar Gasto">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
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

<style scoped>
/* Estilos adicionales se pueden añadir aquí si son muy específicos del componente */
</style>