<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { X } from 'lucide-vue-next';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

// --- ESTADO REACTIVO ---
const activeTab = ref('centrosCosto');
const isLoading = ref(true);
const isModalOpen = ref(false);
const editingItem = ref(null);
const currentItem = ref({});
const currentPage = ref(1);
const itemsPerPage = ref(10);

const dataSets = ref({
    centrosCosto: [],
    proyectos: [],
    gastosProyectados: [],
    cuentasContables: [],
    clasificaciones: [],
    tiposImpuesto: [],
    tiposDocumento: [],
    areas: [],
});

// --- CONFIGURACIÓN CENTRALIZADA DE CATÁLOGOS ---
const catalogConfig = {
    centrosCosto: {
        title: 'Centros de Costo',
        endpoint: '/v1/centros-costo',
        pk: 'id',
        columns: [
            { key: 'codigo', label: 'Código' },
            { key: 'descripcion', label: 'Descripción' },
        ],
        fields: [
            { key: 'codigo', label: 'Código', type: 'text', required: true },
            { key: 'descripcion', label: 'Descripción', type: 'text', required: true },
        ]
    },
    areas: {
        title: 'Áreas',
        endpoint: '/v1/areas',
        pk: 'id',
        columns: [
            { key: 'name', label: 'Nombre del Área' },
            { key: 'acronym', label: 'Acrónimo' },
            { key: 'centro_costo.codigo', label: 'Centro de Costo' },
        ],
        fields: [
            { key: 'name', label: 'Nombre del Área', type: 'text', required: true },
            { key: 'acronym', label: 'Acrónimo (para códigos)', type: 'text', required: false },
            { key: 'description', label: 'Descripción', type: 'text', required: false },
            { 
                key: 'centro_costo_id',
                label: 'Centro de Costo',
                type: 'select',
                options: 'centrosCosto', 
                optionLabel: 'descripcion',
                optionValue: 'id',
                displayFormat: 'codigo - descripcion',
                required: false 
            },
        ]
    },
    proyectos: {
        title: 'Proyectos',
        endpoint: '/v1/proyectos',
        pk: 'id_proyecto',
        columns: [
            { key: 'codigo', label: 'Código' },
            { key: 'nombre', label: 'Nombre del Proyecto' },
        ],
        fields: [
            { key: 'codigo', label: 'Código del Proyecto', type: 'text', required: true, disabledOnEdit: true },
            { key: 'nombre', label: 'Nombre del Proyecto', type: 'text', required: true },
        ]
    },
    gastosProyectados: {
        title: 'Gastos Proyectados',
        endpoint: '/v1/gastos-proyectados',
        pk: 'id_gasto_proyectado',
        columns: [
            { key: 'descripcion', label: 'Descripción' },
            { key: 'cuenta_contable.descripcion', label: 'Cuenta Contable Asociada' },
            { key: 'clasificacion_bien_servicio.nombre', label: 'Clasificación B/S' },
            { key: 'tipo_impuesto.nombre', label: 'Tipo Impuesto' },
        ],
        fields: [
            { key: 'descripcion', label: 'Descripción del Gasto', type: 'text', required: true },
            {
                key: 'id_cuenta_contable',
                label: 'Cuenta Contable Asociada',
                type: 'select',
                options: 'cuentasContables',
                optionLabel: 'descripcion',
                optionValue: 'id',
                displayFormat: 'codigo_cuenta - descripcion',
                required: true
            },
            {
                key: 'clasificacion_bien_servicio_id',
                label: 'Clasificación B/S',
                type: 'select',
                options: 'clasificaciones',
                optionLabel: 'nombre',
                optionValue: 'id_clasificacion_bien_servicio',
                displayFormat: 'codigo - nombre',
                required: true
            },
            {
                key: 'tipo_impuesto_id',
                label: 'Tipo de Impuesto',
                type: 'select',
                options: 'tiposImpuesto',
                optionLabel: 'nombre',
                optionValue: 'id_tipo_impuesto',
                required: true
            },
        ]
    },
    cuentasContables: {
        title: 'Cuentas Contables',
        endpoint: '/v1/cuentas-contables',
        pk: 'id',
        columns: [
            { key: 'codigo_cuenta', label: 'Código de Cuenta' },
            { key: 'descripcion', label: 'Descripción' },
        ],
        fields: [
            { key: 'codigo_cuenta', label: 'Código de Cuenta', type: 'text', required: true, disabledOnEdit: true },
            { key: 'descripcion', label: 'Descripción de la Cuenta', type: 'text', required: true },
        ]
    },
    clasificaciones: {
        title: 'Clasificación B/S',
        endpoint: '/v1/clasificaciones',
        pk: 'id_clasificacion_bien_servicio',
        columns: [
            { key: 'codigo', label: 'Código' },
            { key: 'nombre', label: 'Nombre de la Clasificación' },
        ],
        fields: [
            { key: 'codigo', label: 'Código', type: 'text', required: true },
            { key: 'nombre', label: 'Nombre de la Clasificación', type: 'text', required: true },
        ]
    },
    tiposImpuesto: {
        title: 'Tipos de Impuesto',
        endpoint: '/v1/tipos-impuesto',
        pk: 'id_tipo_impuesto',
        columns: [
            { key: 'nombre', label: 'Nombre' },
            { key: 'porcentaje', label: 'Porcentaje (%)' },
            { key: 'factor_calculo', label: 'Factor de Cálculo' },
        ],
        fields: [
            { key: 'nombre', label: 'Nombre', type: 'text', required: true },
            { key: 'porcentaje', label: 'Porcentaje', type: 'number', required: true },
            { key: 'factor_calculo', label: 'Factor de Cálculo', type: 'number', required: true },
        ]
    },
    tiposDocumento: {
        title: 'Tipos de Documento',
        endpoint: '/v1/tipos-documento-comprobante',
        pk: 'id',
        columns: [
            { key: 'codigo_comprobante', label: 'Código' },
            { key: 'nombre', label: 'Nombre' },
        ],
        fields: [
            { key: 'codigo_comprobante', label: 'Código', type: 'text', required: true },
            { key: 'nombre', label: 'Nombre del Documento', type: 'text', required: true },
        ]
    }
};

// --- FILTROS REACTIVOS ---
const filtros = ref({
    centrosCosto: { codigo: '', descripcion: '', activo: '' },
    areas: { name: '', acronym: '', activo: '' },
    proyectos: { codigo: '', nombre: '', activo: '' },
    gastosProyectados: { descripcion: '', cuenta_contable: '', activo: '' },
    cuentasContables: { codigo_cuenta: '', descripcion: '', activo: '' }
});

// --- DEBOUNCE PARA FILTROS ---
let debounceTimeout = null;
const DEBOUNCE_DELAY = 400;

// --- PROPIEDADES COMPUTADAS ---
const activeDataSet = computed(() => dataSets.value[activeTab.value] || []);
const activeConfig = computed(() => catalogConfig[activeTab.value]);
const isEditing = computed(() => !!editingItem.value);
const activeFiltros = computed(() => filtros.value[activeTab.value] || {});
const totalPages = computed(() => {
    const list = Array.isArray(activeDataSet.value) ? activeDataSet.value : [];
    const pages = Math.ceil(list.length / itemsPerPage.value || 1);
    return pages || 1;
});
const paginatedItems = computed(() => {
    const list = Array.isArray(activeDataSet.value) ? activeDataSet.value : [];
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return list.slice(start, end);
});

// --- LÓGICA DE DATOS Y API OPTIMIZADA ---
const fetchDataWithFilters = () => {
    isLoading.value = true;

    const config = activeConfig.value;
    const currentFilters = filtros.value[activeTab.value];
    const params = { scope: 'management' };

    if (currentFilters) {
        Object.entries(currentFilters).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) params[key] = value;
        });
    }

    api.get(config.endpoint, { params })
        .then(res => {
            const rows = extractRowsSafely(res.data, activeTab.value);
            dataSets.value[activeTab.value] = rows;
            currentPage.value = 1;
        })
        .catch(err => {
            console.error('[Catálogos] fetch error:', err?.response?.data || err);
            Swal.fire('Error', `No se pudieron cargar los datos de ${config.title}.`, 'error');
            // Asegura dejar el dataset como array vacío para no romper renders
            dataSets.value[activeTab.value] = [];
        })
        .finally(() => {
            isLoading.value = false;
        });
};
// Helper para extraer arrays de distintas formas de respuesta
const extractRowsSafely = (payload) => {
    // 1) Si la respuesta YA es un array
    if (Array.isArray(payload)) return payload;
    // 2) Si viene en { data: [...] }
    if (payload && Array.isArray(payload.data)) return payload.data;
    // 3) Si viene en { clasificaciones: [...] }, { proyectos: [...] }, etc.
    const firstArray = Object.values(payload || {}).find(v => Array.isArray(v));
    if (Array.isArray(firstArray)) return firstArray;
    // 4) Fallback seguro
    return [];
};

const triggerDebouncedFetch = () => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(fetchDataWithFilters, DEBOUNCE_DELAY);
};

const limpiarFiltros = () => {
    const currentFilters = filtros.value[activeTab.value];
    if (currentFilters) {
        Object.keys(currentFilters).forEach(key => {
            currentFilters[key] = '';
        });
        fetchDataWithFilters();
    }
};

// Carga datos para selectores (una sola vez)
const fetchSelectOptions = async () => {
    try {
        const [cuentasRes, clasificacionesRes, tiposImpuestoRes, tiposDocumentoRes, centrosCostoRes] = await Promise.all([
            api.get(catalogConfig.cuentasContables.endpoint),
            api.get(catalogConfig.clasificaciones.endpoint),
            api.get(catalogConfig.tiposImpuesto.endpoint),
            api.get(catalogConfig.tiposDocumento.endpoint),
            api.get(catalogConfig.centrosCosto.endpoint)
        ]);

        dataSets.value.cuentasContables = extractRowsSafely(cuentasRes.data, 'cuentasContables');
        dataSets.value.clasificaciones = extractRowsSafely(clasificacionesRes.data, 'clasificaciones');
        dataSets.value.tiposImpuesto = extractRowsSafely(tiposImpuestoRes.data, 'tiposImpuesto');
        dataSets.value.tiposDocumento = extractRowsSafely(tiposDocumentoRes.data, 'tiposDocumento');
        dataSets.value.centrosCosto = extractRowsSafely(centrosCostoRes.data, 'centrosCosto');

    } catch (error) {
        console.error("Error al cargar opciones para selectores:", error);
        Swal.fire('Error', 'No se pudieron cargar las opciones para los selectores.', 'error');
    }
};

// --- WATCHERS OPTIMIZADOS ---
// Crear watchers dinámicamente para evitar repetición
const createFilterWatcher = (tabName, fieldName, immediate = false) => {
    watch(() => filtros.value[tabName]?.[fieldName],
        immediate ? fetchDataWithFilters : triggerDebouncedFetch
    );
};

// Watchers para todos los filtros
Object.keys(filtros.value).forEach(tab => {
    const fields = Object.keys(filtros.value[tab]);
    fields.forEach(field => {
        createFilterWatcher(tab, field, field === 'activo');
    });
});

watch(() => activeTab.value, () => {
    clearTimeout(debounceTimeout);
});

// --- MÉTODOS DE NAVEGACIÓN ---
const changeTab = (tab) => {
    activeTab.value = tab;
    fetchDataWithFilters();
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

// --- MÉTODOS DEL MODAL Y CRUD ---
const resetCurrentItem = () => {
    currentItem.value = { activo: true };
    activeConfig.value.fields.forEach(field => {
        currentItem.value[field.key] = field.type === 'select' ? null : (field.type === 'number' ? 0 : '');
    });
};

const openCreateModal = () => {
    editingItem.value = null;
    resetCurrentItem();
    isModalOpen.value = true;
};

const openEditModal = (item) => {
    editingItem.value = item;
    currentItem.value = { ...item };
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingItem.value = null;
    currentItem.value = {};
};

const handleSave = async () => {
    const config = activeConfig.value;
    const endpoint = isEditing.value
        ? `${config.endpoint}/${editingItem.value[config.pk]}`
        : config.endpoint;
    const method = isEditing.value ? 'put' : 'post';

    try {
        const response = await api[method](endpoint, currentItem.value);
        Swal.fire('¡Éxito!', response.data.message, 'success');
        fetchDataWithFilters();
        closeModal();
    } catch (error) {
        const errorMessage = error.response?.data?.message || 'Ocurrió un error al guardar el registro.';
        Swal.fire('Error', errorMessage, 'error');
    }
};

const handleDesactivate = async (item) => {
    const config = activeConfig.value;
    const result = await Swal.fire({
        title: '¿Estás seguro?',
        text: 'Estás a punto de desactivar este registro.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        try {
            await api.delete(`${config.endpoint}/${item[config.pk]}`);
            Swal.fire('¡Desactivado!', 'El registro ha sido desactivado.', 'success');
            fetchDataWithFilters();
        } catch (error) {
            Swal.fire('Error', error.response?.data?.message || 'No se pudo desactivar el registro.', 'error');
        }
    }
};

const handleActivate = async (item) => {
    const config = activeConfig.value;
    try {
        await api.post(`${config.endpoint}/${item[config.pk]}/activate`);
        Swal.fire('¡Activado!', 'El registro ha sido activado exitosamente.', 'success');
        fetchDataWithFilters();
    } catch (error) {
        Swal.fire('Error', error.response?.data?.message || 'No se pudo activar el registro.', 'error');
    }
};

// --- MÉTODOS DE UTILIDAD ---
const getNestedValue = (obj, path) => {
    return path.split('.').reduce((value, key) => value && value[key], obj);
};

const getSelectOptions = (field) => {
    const options = dataSets.value[field.options];

    return Array.isArray(options)
        ? options.filter(o => o && String(o.activo) === '1')
        : [];
};

const getSimpleOptionLabel = (option, field) => {
    if (!option) return 'Sin datos';

    try {
        // Formato personalizado
        if (field.displayFormat) {
            const parts = field.displayFormat.split(' - ');
            const values = parts.map(key => option[key] || '').filter(v => v !== '');
            if (values.length > 0) return values.join(' - ');
        }

        // Usar optionLabel o fallbacks
        const labelField = field.optionLabel || 'descripcion';
        return String(option[labelField] || option.nombre || option.codigo || `ID: ${getSimpleOptionValue(option, field)}`);
    } catch {
        return 'Error';
    }
};

const getSimpleOptionValue = (option, field) => {
    if (!option) return null;

    const valueField = field.optionValue || 'id';
    return option[valueField] || option.id || option.id_clasificacion_bien_servicio || option.id_tipo_impuesto || null;
};

const handleSelectInput = (selectedOption, field) => {
    currentItem.value[field.key] = selectedOption ? getSimpleOptionValue(selectedOption, field) : null;
};

// --- INICIALIZACIÓN ---
onMounted(async () => {
    await fetchSelectOptions();
    fetchDataWithFilters();
});
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md animate-fade-in-up">
        <div class="flex items-start justify-between mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">Panel de Administración de Catálogos</h2>
                <p class="text-gray-500">Gestiona los datos maestros que se utilizan en todo el sistema.</p>
            </div>
            <button @click="$emit('close')" class="p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                <X class="w-5 h-5 text-gray-500" />
            </button>
        </div>

        <!-- Pestañas de Navegación -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button v-for="(config, tab) in catalogConfig" :key="tab" @click="changeTab(tab)"
                    :class="['whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm', activeTab === tab ? 'border-verde-bap text-verde-bap-dark' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                    {{ config.title }}
                </button>
            </nav>
        </div>

        <!-- Filtros Dinámicos -->
        <div v-if="activeFiltros && Object.keys(activeFiltros).length > 0"
            class="mb-4 bg-gray-50 p-4 rounded-lg shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div v-for="(value, key) in activeFiltros" :key="key">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ key === 'codigo' ? 'Código' :
                            key === 'nombre' ? 'Nombre' :
                                key === 'descripcion' ? 'Descripción' :
                                    key === 'codigo_cuenta' ? 'Código de Cuenta' :
                                        key === 'cuenta_contable' ? 'Cuenta Contable' :
                                            key === 'activo' ? 'Estado' : key }}:
                    </label>
                    <select v-if="key === 'activo'" v-model="activeFiltros[key]" class="form-input">
                        <option value="">Todos</option>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                    <input v-else v-model="activeFiltros[key]" type="text" class="form-input" :placeholder="`Buscar por ${key === 'codigo' ? 'código' :
                        key === 'nombre' ? 'nombre' :
                            key === 'descripcion' ? 'descripción' :
                                key === 'codigo_cuenta' ? 'código de cuenta' :
                                    key === 'cuenta_contable' ? 'cuenta contable' : key}`" />
                </div>
                <div class="flex items-end" v-if="Object.keys(activeFiltros).length > 0">
                    <button @click="limpiarFiltros"
                        class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors duration-200 text-sm font-medium">
                        Limpiar Filtros
                    </button>
                </div>
            </div>
        </div>

        <!-- Contenido de la Pestaña Activa -->
        <div>
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold text-gray-700 capitalize">{{ activeConfig.title }}</h3>
                <button @click="openCreateModal"
                    class="bg-verde-bap hover:bg-verde-bap-dark text-white font-bold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Crear Nuevo
                </button>
            </div>

            <div v-if="isLoading" class="text-center py-10 text-gray-500">Cargando datos...</div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full bg-white shadow-md rounded-lg">
                    <thead class="bg-gray-100">
                        <tr>
                            <th v-for="column in activeConfig.columns" :key="column.key"
                                class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                {{ column.label }}</th>
                            <th
                                class="py-3 px-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Estado</th>
                            <th
                                class="py-3 px-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-700">
                        <tr v-for="item in paginatedItems" :key="item[activeConfig.pk]"
                            class="border-b border-gray-200 hover:bg-gray-50">
                            <td v-for="column in activeConfig.columns" :key="column.key" class="py-3 px-4">{{
                                getNestedValue(item, column.key) || 'N/A' }}</td>
                            <td class="py-3 px-4 text-center">
                                <span
                                    :class="['px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full', item.activo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                    {{ item.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex item-center justify-center space-x-2">
                                    <button @click="openEditModal(item)"
                                        class="p-2 rounded-full hover:bg-gray-200 transition-colors" title="Editar">
                                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z">
                                            </path>
                                        </svg>
                                    </button>
                                    <button v-if="item.activo" @click="handleDesactivate(item)"
                                        class="p-2 rounded-full hover:bg-gray-200 transition-colors" title="Desactivar">
                                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                                            </path>
                                        </svg>
                                    </button>
                                    <button v-else @click="handleActivate(item)"
                                        class="p-2 rounded-full hover:bg-gray-200 transition-colors" title="Activar">
                                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!paginatedItems.length" class="text-center py-10 text-gray-500">No hay registros para mostrar.
                </p>
            </div>

            <!-- Paginación -->
            <div v-if="!isLoading && totalPages > 1"
                class="mt-6 flex justify-between items-center text-sm text-gray-600">
                <span>Página <strong>{{ currentPage }}</strong> de <strong>{{ totalPages }}</strong></span>
                <div class="inline-flex items-center -space-x-px">
                    <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1"
                        class="px-3 py-1 rounded-l-md border bg-white hover:bg-gray-100 disabled:opacity-50">Anterior</button>
                    <button v-for="page in totalPages" :key="page" @click="goToPage(page)" :class="[
                        'px-3 py-1 border',
                        currentPage === page
                            ? 'bg-verde-bap text-white border-verde-bap-dark'
                            : 'bg-white hover:bg-gray-100'
                    ]">
                        {{ page }}
                    </button>
                    <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages"
                        class="px-3 py-1 rounded-r-md border bg-white hover:bg-gray-100 disabled:opacity-50">Siguiente</button>
                </div>
            </div>
        </div>

        <!-- Modal para Crear/Editar -->
        <Transition name="modal-backdrop">
            <div v-if="isModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm"
                @click.self="closeModal">
                <div @click.stop
                    class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-auto transform animate-modal-scale">
                    <form @submit.prevent="handleSave">
                        <div class="p-6 border-b">
                            <h3 class="text-xl font-semibold text-gray-800">{{ isEditing ? 'Editar' : 'Crear' }} {{
                                activeConfig.title }}</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div v-for="field in activeConfig.fields" :key="field.key">
                                <label class="form-label">
                                    {{ field.label }}
                                    <span v-if="field.required" class="text-rojo-bap">*</span>
                                </label>

                                <!-- Input de texto -->
                                <input v-if="field.type === 'text'" v-model="currentItem[field.key]" type="text"
                                    class="form-input" :required="field.required"
                                    :disabled="isEditing && field.disabledOnEdit">

                                <!-- Input de número -->
                                <input v-if="field.type === 'number'" v-model.number="currentItem[field.key]"
                                    type="number" step="any" class="form-input" :required="field.required">

                                <!-- Select -->
                                <div v-if="field.type === 'select'" class="mt-1">
                                    <v-select v-model="currentItem[field.key]" :options="getSelectOptions(field)"
                                        :label="field.optionLabel || 'descripcion'"
                                        :track-by="field.optionValue || 'id'" :placeholder="`Seleccione ${field.label}`"
                                        :required="field.required" :clearable="!field.required"
                                        :reduce="option => option[field.optionValue || 'id']"
                                        @input="handleSelectInput($event, field)">
                                        <template #option="option">
                                            <div class="py-1">
                                                {{ getSimpleOptionLabel(option, field) }}
                                                <small class="block text-gray-400">ID: {{ getSimpleOptionValue(option,
                                                    field) }}</small>
                                            </div>
                                        </template>
                                        <template #singleLabel="props">
                                            {{ getSimpleOptionLabel(props.option, field) }}
                                        </template>
                                        <template #no-options>
                                            <div class="text-center py-2 text-gray-500">
                                                No hay opciones disponibles
                                            </div>
                                        </template>
                                    </v-select>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 bg-gray-50 rounded-b-2xl flex justify-end space-x-4">
                            <button type="button" @click="closeModal"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition-colors">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="bg-verde-bap hover:bg-verde-bap-dark text-white font-bold py-2 px-4 rounded-lg transition-colors">
                                {{ isEditing ? 'Actualizar' : 'Guardar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped lang="postcss">
/* Estilos para v-select */
.vs__dropdown-toggle {
    @apply mt-1 block w-full p-0 border border-gray-300 rounded-md shadow-sm;
}

.vs__selected-options {
    @apply p-2;
}

.vs--open .vs__dropdown-toggle {
    @apply border-verde-bap ring-1 ring-verde-bap;
}

.form-label {
    @apply block text-sm font-medium text-gray-700 mb-1;
}

.form-input {
    @apply mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap disabled:bg-gray-100 disabled:cursor-not-allowed;
}

.modal-backdrop-enter-active,
.modal-backdrop-leave-active {
    transition: opacity 0.3s ease;
}

.modal-backdrop-enter-from,
.modal-backdrop-leave-to {
    opacity: 0;
}

@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(30px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes modal-scale {
    from {
        opacity: 0;
        transform: scale(0.95);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.5s ease-out;
}

.animate-modal-scale {
    animation: modal-scale 0.3s ease-out;
}
</style>