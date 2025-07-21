<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

// --- ESTADO REACTIVO ---
const activeTab = ref('proyectos');
const isLoading = ref(true);
const isModalOpen = ref(false);
const editingItem = ref(null);
const currentItem = ref({});
const currentPage = ref(1);
const itemsPerPage = ref(10);

const dataSets = ref({
    proyectos: [],
    gastosProyectados: [],
    cuentasContables: [],
});

// --- CONFIGURACIÓN CENTRALIZADA DE CATÁLOGOS ---
const catalogConfig = {
    proyectos: {
        title: 'Proyectos',
        endpoint: '/v1/proyectos',
        pk: 'id_proyecto',
        columns: [
            { key: 'codigo', label: 'Código' },
            { key: 'nombre', label: 'Nombre del Proyecto' },
        ],
        fields: [
            // [MODIFICADO] Se añade 'disabledOnEdit' para que el código no sea editable.
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
        ],
        fields: [
            { key: 'descripcion', label: 'Descripción del Gasto', type: 'text', required: true },
            { key: 'id_cuenta_contable', label: 'Cuenta Contable Asociada', type: 'select', options: 'cuentasContables', required: true },
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
            // [MODIFICADO] Se añade 'disabledOnEdit' para que el código no sea editable.
            { key: 'codigo_cuenta', label: 'Código de Cuenta', type: 'text', required: true, disabledOnEdit: true },
            { key: 'descripcion', label: 'Descripción de la Cuenta', type: 'text', required: true },
        ]
    }
};

// --- FILTROS REACTIVOS PARA PROYECTOS ---
const filtrosProyectos = ref({
  codigo: '',
  nombre: '',
  activo: '' // '', '1', '0'
});

// --- FILTROS REACTIVOS PARA GASTOS PROYECTADOS ---
const filtrosGastosProyectados = ref({
  descripcion: '',
  cuenta_contable: '', // ahora texto, antes id_cuenta_contable
  activo: '' // '', '1', '0'
});

// --- FILTROS REACTIVOS PARA CUENTAS CONTABLES ---
const filtrosCuentasContables = ref({
  codigo_cuenta: '',
  descripcion: '',
  activo: '' // '', '1', '0'
});

// --- DEBOUNCE PARA FILTROS DE TEXTO ---
let debounceTimeout = null;
const DEBOUNCE_DELAY = 400;

function fetchProyectosConFiltros() {
  isLoading.value = true;
  const params = { scope: 'management' };
  if (filtrosProyectos.value.codigo) params.codigo = filtrosProyectos.value.codigo;
  if (filtrosProyectos.value.nombre) params.nombre = filtrosProyectos.value.nombre;
  if (filtrosProyectos.value.activo !== '') params.activo = filtrosProyectos.value.activo;
  api.get('/v1/proyectos', { params })
    .then(res => { dataSets.value.proyectos = res.data.proyectos; currentPage.value = 1; })
    .catch(() => Swal.fire('Error', 'No se pudieron cargar los proyectos.', 'error'))
    .finally(() => { isLoading.value = false; });
}

function triggerDebouncedFetchProyectos() {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(fetchProyectosConFiltros, DEBOUNCE_DELAY);
}

function limpiarFiltrosProyectos() {
  filtrosProyectos.value = { codigo: '', nombre: '', activo: '' };
  fetchProyectosConFiltros();
}

function fetchGastosProyectadosConFiltros() {
  isLoading.value = true;
  const params = { scope: 'management' };
  if (filtrosGastosProyectados.value.descripcion) params.descripcion = filtrosGastosProyectados.value.descripcion;
  if (filtrosGastosProyectados.value.cuenta_contable) params.cuenta_contable = filtrosGastosProyectados.value.cuenta_contable;
  if (filtrosGastosProyectados.value.activo !== '') params.activo = filtrosGastosProyectados.value.activo;
  api.get('/v1/gastos-proyectados', { params })
    .then(res => { dataSets.value.gastosProyectados = res.data.gastos_proyectados; currentPage.value = 1; })
    .catch(() => Swal.fire('Error', 'No se pudieron cargar los gastos proyectados.', 'error'))
    .finally(() => { isLoading.value = false; });
}

function triggerDebouncedFetchGastosProyectados() {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(fetchGastosProyectadosConFiltros, DEBOUNCE_DELAY);
}

function limpiarFiltrosGastosProyectados() {
  filtrosGastosProyectados.value = { descripcion: '', cuenta_contable: '', activo: '' };
  fetchGastosProyectadosConFiltros();
}

function fetchCuentasContablesConFiltros() {
  isLoading.value = true;
  const params = { scope: 'management' };
  if (filtrosCuentasContables.value.codigo_cuenta) params.codigo_cuenta = filtrosCuentasContables.value.codigo_cuenta;
  if (filtrosCuentasContables.value.descripcion) params.descripcion = filtrosCuentasContables.value.descripcion;
  if (filtrosCuentasContables.value.activo !== '') params.activo = filtrosCuentasContables.value.activo;
  api.get('/v1/cuentas-contables', { params })
    .then(res => { dataSets.value.cuentasContables = res.data.cuentas_contables; currentPage.value = 1; })
    .catch(() => Swal.fire('Error', 'No se pudieron cargar las cuentas contables.', 'error'))
    .finally(() => { isLoading.value = false; });
}

function triggerDebouncedFetchCuentasContables() {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(fetchCuentasContablesConFiltros, DEBOUNCE_DELAY);
}

function limpiarFiltrosCuentasContables() {
  filtrosCuentasContables.value = { codigo_cuenta: '', descripcion: '', activo: '' };
  fetchCuentasContablesConFiltros();
}

// --- LÓGICA DE DATOS Y API ---
const fetchData = async () => {
    isLoading.value = true;
    try {
        if (activeTab.value === 'proyectos') {
            fetchProyectosConFiltros();
            return;
        }
        if (activeTab.value === 'gastosProyectados') {
            fetchGastosProyectadosConFiltros();
            return;
        }
        if (activeTab.value === 'cuentasContables') {
            fetchCuentasContablesConFiltros();
            return;
        }
    } catch (error) {
        console.error("Error al cargar los catálogos:", error);
        Swal.fire('Error', 'No se pudieron cargar los datos de los catálogos.', 'error');
    } finally {
        isLoading.value = false;
    }
};

onMounted(fetchData);

// --- WATCHERS PARA FILTROS DE PROYECTOS ---
watch(() => filtrosProyectos.value.codigo, triggerDebouncedFetchProyectos);
watch(() => filtrosProyectos.value.nombre, triggerDebouncedFetchProyectos);
watch(() => filtrosProyectos.value.activo, fetchProyectosConFiltros);

// --- WATCHERS PARA FILTROS DE GASTOS PROYECTADOS ---
watch(() => filtrosGastosProyectados.value.descripcion, triggerDebouncedFetchGastosProyectados);
watch(() => filtrosGastosProyectados.value.cuenta_contable, triggerDebouncedFetchGastosProyectados);
watch(() => filtrosGastosProyectados.value.activo, fetchGastosProyectadosConFiltros);

// --- WATCHERS PARA FILTROS DE CUENTAS CONTABLES ---
watch(() => filtrosCuentasContables.value.codigo_cuenta, triggerDebouncedFetchCuentasContables);
watch(() => filtrosCuentasContables.value.descripcion, triggerDebouncedFetchCuentasContables);
watch(() => filtrosCuentasContables.value.activo, fetchCuentasContablesConFiltros);

// --- PROPIEDADES COMPUTADAS ---
const activeDataSet = computed(() => dataSets.value[activeTab.value] || []);
const activeConfig = computed(() => catalogConfig[activeTab.value]);
// [NUEVO] Propiedad computada para saber si estamos en modo edición.
const isEditing = computed(() => !!editingItem.value);

const totalPages = computed(() => Math.ceil(activeDataSet.value.length / itemsPerPage.value));
const paginatedItems = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return activeDataSet.value.slice(start, end);
});

// --- MÉTODOS ---
const changeTab = (tab) => {
    activeTab.value = tab;
    currentPage.value = 1;
    if (tab === 'proyectos') {
      fetchProyectosConFiltros();
    } else if (tab === 'gastosProyectados') {
      fetchGastosProyectadosConFiltros();
    } else if (tab === 'cuentasContables') {
      fetchCuentasContablesConFiltros();
    } else {
      fetchData();
    }
};

const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};

// --- MÉTODOS DEL MODAL Y CRUD ---
const openCreateModal = () => {
    editingItem.value = null;
    currentItem.value = { activo: true };
    activeConfig.value.fields.forEach(field => {
        currentItem.value[field.key] = field.type === 'select' ? null : '';
    });
    currentItem.value.activo = true;
    if (activeTab.value === 'gastosProyectados') {
        fetchCuentasContablesConFiltros();
    }
    isModalOpen.value = true;
};

const openEditModal = (item) => {
    editingItem.value = item;
    currentItem.value = { ...item };
    if (activeTab.value === 'gastosProyectados') {
        fetchCuentasContablesConFiltros();
    }
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
        fetchData();
        closeModal();
    } catch (error) {
        console.error("Error al guardar:", error);
        const errorMessage = error.response?.data?.message || 'Ocurrió un error al guardar el registro.';
        Swal.fire('Error', errorMessage, 'error');
    }
};

const handleDesactivate = async (item) => {
    const config = activeConfig.value;
    const result = await Swal.fire({
        title: '¿Estás seguro?',
        text: `Estás a punto de desactivar este registro.`,
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
            fetchData();
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
        fetchData();
    } catch (error) {
        Swal.fire('Error', error.response?.data?.message || 'No se pudo activar el registro.', 'error');
    }
};

const getNestedValue = (obj, path) => {
    return path.split('.').reduce((value, key) => value && value[key], obj);
};

</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md animate-fade-in-up">
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Panel de Administración de Catálogos</h2>
        <p class="text-gray-500 mb-6">Gestiona los datos maestros que se utilizan en todo el sistema.</p>

        <!-- Pestañas de Navegación -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button v-for="(config, tab) in catalogConfig" :key="tab" @click="changeTab(tab)"
                    :class="['whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm', activeTab === tab ? 'border-verde-bap text-verde-bap-dark' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300']">
                    {{ config.title }}
                </button>
            </nav>
        </div>

        <!-- FILTROS SOLO PARA PROYECTOS -->
        <div v-if="activeTab === 'proyectos'" class="mb-4 bg-gray-50 p-4 rounded-lg shadow-sm">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Código:</label>
              <input v-model="filtrosProyectos.codigo" type="text" class="form-input" placeholder="Buscar por código" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Nombre:</label>
              <input v-model="filtrosProyectos.nombre" type="text" class="form-input" placeholder="Buscar por nombre" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Estado:</label>
              <select v-model="filtrosProyectos.activo" class="form-input">
                <option value="">Todos</option>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
              </select>
            </div>
            <div class="flex items-end">
              <button @click="limpiarFiltrosProyectos" class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors duration-200 text-sm font-medium">
                Limpiar Filtros
              </button>
            </div>
          </div>
        </div>
        <!-- FILTROS SOLO PARA GASTOS PROYECTADOS -->
        <div v-if="activeTab === 'gastosProyectados'" class="mb-4 bg-gray-50 p-4 rounded-lg shadow-sm">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Descripción:</label>
              <input v-model="filtrosGastosProyectados.descripcion" type="text" class="form-input" placeholder="Buscar por descripción" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Cuenta Contable:</label>
              <input v-model="filtrosGastosProyectados.cuenta_contable" type="text" class="form-input" placeholder="Buscar por cuenta contable" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Estado:</label>
              <select v-model="filtrosGastosProyectados.activo" class="form-input">
                <option value="">Todos</option>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
              </select>
            </div>
            <div class="flex items-end">
              <button @click="limpiarFiltrosGastosProyectados" class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors duration-200 text-sm font-medium">
                Limpiar Filtros
              </button>
            </div>
          </div>
        </div>
        <!-- FILTROS SOLO PARA CUENTAS CONTABLES -->
        <div v-if="activeTab === 'cuentasContables'" class="mb-4 bg-gray-50 p-4 rounded-lg shadow-sm">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Código de Cuenta:</label>
              <input v-model="filtrosCuentasContables.codigo_cuenta" type="text" class="form-input" placeholder="Buscar por código" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Descripción:</label>
              <input v-model="filtrosCuentasContables.descripcion" type="text" class="form-input" placeholder="Buscar por descripción" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Estado:</label>
              <select v-model="filtrosCuentasContables.activo" class="form-input">
                <option value="">Todos</option>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
              </select>
            </div>
            <div class="flex items-end">
              <button @click="limpiarFiltrosCuentasContables" class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition-colors duration-200 text-sm font-medium">
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
                                        class="p-2 rounded-full hover:bg-gray-200 transition-colors" title="Editar"><svg
                                            class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z">
                                            </path>
                                        </svg></button>
                                    <button v-if="item.activo" @click="handleDesactivate(item)"
                                        class="p-2 rounded-full hover:bg-gray-200 transition-colors"
                                        title="Desactivar"><svg class="w-5 h-5 text-red-500" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                                            </path>
                                        </svg></button>
                                    <button v-else @click="handleActivate(item)"
                                        class="p-2 rounded-full hover:bg-gray-200 transition-colors"
                                        title="Activar"><svg class="w-5 h-5 text-green-500" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!paginatedItems.length" class="text-center py-10 text-gray-500">No hay registros para mostrar.
                </p>
            </div>
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
                                <label class="form-label">{{ field.label }}<span v-if="field.required"
                                        class="text-rojo-bap">*</span></label>
                                <!-- [MODIFICADO] Se añade :disabled para los campos de código en modo edición -->
                                <input v-if="field.type === 'text'" v-model="currentItem[field.key]" type="text"
                                    class="form-input" :required="field.required"
                                    :disabled="isEditing && field.disabledOnEdit">
                                <select v-if="field.type === 'select'" v-model="currentItem[field.key]"
                                    class="form-input" :required="field.required">
                                    <option :value="null" disabled>Seleccione una cuenta</option>
                                    <option v-for="option in dataSets[field.options].filter(o => o.activo)"
                                        :key="option.id" :value="option.id">
                                        {{ option.codigo_cuenta }} - {{ option.descripcion }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div class="p-6 bg-gray-50 rounded-b-2xl flex justify-end space-x-4">
                            <button type="button" @click="closeModal"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition-colors">Cancelar</button>
                            <button type="submit"
                                class="bg-verde-bap hover:bg-verde-bap-dark text-white font-bold py-2 px-4 rounded-lg transition-colors">{{
                                    isEditing ? 'Actualizar' : 'Guardar' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped lang="postcss">
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
</style>
