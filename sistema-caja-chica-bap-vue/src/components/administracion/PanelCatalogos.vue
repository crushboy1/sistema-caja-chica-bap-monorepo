<script setup>
import { ref, onMounted, computed } from 'vue';
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

// --- LÓGICA DE DATOS Y API ---
const fetchData = async () => {
    isLoading.value = true;
    try {
        const [proyectosRes, gastosRes, cuentasRes] = await Promise.all([
            api.get('/v1/proyectos?scope=management'),
            api.get('/v1/gastos-proyectados?scope=management'),
            api.get('/v1/cuentas-contables?scope=management')
        ]);
        dataSets.value.proyectos = proyectosRes.data.proyectos;
        dataSets.value.gastosProyectados = gastosRes.data.gastos_proyectados;
        dataSets.value.cuentasContables = cuentasRes.data.cuentas_contables;
    } catch (error) {
        console.error("Error al cargar los catálogos:", error);
        Swal.fire('Error', 'No se pudieron cargar los datos de los catálogos.', 'error');
    } finally {
        isLoading.value = false;
    }
};

onMounted(fetchData);

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
