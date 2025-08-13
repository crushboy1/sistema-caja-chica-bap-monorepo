<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import { X, Users, UserCheck, UserX, Shield } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { getClassesForAuditoriaBadge } from '@/utils/statusStyles.js';

// --- ESTADO REACTIVO ---
const users = ref([]);
const roles = ref([]);
const areas = ref([]);
const isLoading = ref(true);

// Modal
const isModalOpen = ref(false);
const isEditing = ref(false);
const currentUser = ref({});

// Paginación
const currentPage = ref(1);
const itemsPerPage = ref(10);

// Filtros por columna
const filtros = ref({
    nombre: '',
    email: '',
    roleId: null,
    areaId: null,
    estado: '' // 'Activo' | 'Inactivo' | '' (todos)
});

// --- LÓGICA DE DATOS Y API ---

// Carga todos los datos necesarios para el panel en paralelo.
const fetchData = async () => {
    isLoading.value = true;
    try {
        const [usersRes, rolesRes, areasRes] = await Promise.all([
            api.get('/v1/users'),
            api.get('/v1/roles'), // Asumiendo que tienes un endpoint para roles
            api.get('/v1/areas')   // Asumiendo que tienes un endpoint para áreas
        ]);
        users.value = usersRes.data.users;
        roles.value = rolesRes.data.roles;
        areas.value = areasRes.data.areas;
    } catch (error) {
        console.error("Error al cargar los datos para el panel de usuarios:", error);
        Swal.fire('Error', 'No se pudieron cargar los datos necesarios.', 'error');
    } finally {
        isLoading.value = false;
    }
};

onMounted(fetchData);

// Lista filtrada
const filteredUsers = computed(() => {
    const nombreTerm = filtros.value.nombre.trim().toLowerCase();
    const emailTerm = filtros.value.email.trim().toLowerCase();
    const roleId = filtros.value.roleId;
    const areaId = filtros.value.areaId;
    const estado = filtros.value.estado; // texto 'Activo'/'Inactivo'

    return users.value.filter((u) => {
        const fullName = `${u.name || ''} ${u.last_name || ''}`.trim().toLowerCase();
        const email = (u.email || '').toLowerCase();
        const matchesNombre = nombreTerm ? fullName.includes(nombreTerm) : true;
        const matchesEmail = emailTerm ? email.includes(emailTerm) : true;
        const matchesRol = roleId ? (u.role && u.role.id === roleId) : true;
        const matchesArea = areaId ? (u.area && u.area.id === areaId) : true;
        const matchesEstado = estado ? ((u.activo ? 'Activo' : 'Inactivo') === estado) : true;
        return matchesNombre && matchesEmail && matchesRol && matchesArea && matchesEstado;
    });
});

// Reiniciar a la primera página al cambiar filtros
watch(filtros, () => { currentPage.value = 1; }, { deep: true });

// --- PROPIEDADES COMPUTADAS PARA PAGINACIÓN ---
const totalItems = computed(() => filteredUsers.value.length);
const totalPages = computed(() => Math.ceil(totalItems.value / itemsPerPage.value));
const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredUsers.value.slice(start, end);
});

// Estado de filtros activos (para habilitar/deshabilitar Limpiar)
const hayFiltrosActivos = computed(() => {
    return Object.values(filtros.value).some(v => v !== null && v !== '');
});

// Estadísticas para tarjetas (similar a PanelAuditoria)
const userStats = computed(() => {
    const total = users.value.length;
    const activos = users.value.filter(u => !!u.activo).length;
    const inactivos = total - activos;
    const rolesDistintos = new Set(users.value.map(u => u.role?.id).filter(Boolean)).size;
    const areasDistintas = new Set(users.value.map(u => u.area?.id).filter(Boolean)).size;
    return { total, activos, inactivos, rolesDistintos, areasDistintas };
});

// Paginación estilo ReporteGastos
const paginasVisibles = computed(() => {
    if (totalPages.value <= 7) {
        return Array.from({ length: totalPages.value }, (_, i) => i + 1);
    }
    if (currentPage.value < 5) {
        return [1, 2, 3, 4, 5, '...', totalPages.value];
    }
    if (currentPage.value > totalPages.value - 4) {
        return [1, '...', totalPages.value - 4, totalPages.value - 3, totalPages.value - 2, totalPages.value - 1, totalPages.value];
    }
    return [1, '...', currentPage.value - 1, currentPage.value, currentPage.value + 1, '...', totalPages.value];
});

const irAPagina = (pagina) => {
    if (typeof pagina === 'number' && pagina >= 1 && pagina <= totalPages.value) {
        currentPage.value = pagina;
    }
};
const paginaAnterior = () => { if (currentPage.value > 1) currentPage.value--; };
const paginaSiguiente = () => { if (currentPage.value < totalPages.value) currentPage.value++; };

// --- MÉTODOS DEL MODAL Y CRUD ---

const openCreateModal = () => {
    isEditing.value = false;
    currentUser.value = {
        activo: true,
        role_id: null,
        area_id: null,
        jefe_area_id: null,
    };
    isModalOpen.value = true;
};

const openEditModal = (user) => {
    isEditing.value = true;
    // Clonamos el objeto para evitar la reactividad directa en la tabla
    currentUser.value = { ...user };
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    currentUser.value = {};
};

const handleSave = async () => {
    const endpoint = isEditing.value ? `/v1/users/${currentUser.value.id}` : '/v1/users';
    const method = isEditing.value ? 'put' : 'post';

    // Validación de contraseña solo al crear
    if (!isEditing.value && (!currentUser.value.password || currentUser.value.password !== currentUser.value.password_confirmation)) {
        Swal.fire('Error de Validación', 'Las contraseñas son requeridas y deben coincidir.', 'error');
        return;
    }

    try {
        const response = await api[method](endpoint, currentUser.value);
        Swal.fire('¡Éxito!', response.data.message, 'success');
        fetchData(); // Recargar la lista de usuarios
        closeModal();
    } catch (error) {
        console.error("Error al guardar el usuario:", error);
        const errorMessage = error.response?.data?.message || 'Ocurrió un error al guardar.';
        const errors = error.response?.data?.errors;
        let htmlError = `<p>${errorMessage}</p>`;
        if (errors) {
            htmlError += '<ul class="text-left mt-2 list-disc list-inside">';
            for (const key in errors) {
                htmlError += `<li>${errors[key][0]}</li>`;
            }
            htmlError += '</ul>';
        }
        Swal.fire({ icon: 'error', title: 'Error al Guardar', html: htmlError });
    }
};

const handleToggleActive = async (user) => {
    const action = user.activo ? 'desactivar' : 'activar';
    const endpoint = user.activo ? `/v1/users/${user.id}` : `/v1/users/${user.id}/activate`;
    const method = user.activo ? 'delete' : 'post';

    const result = await Swal.fire({
        title: `¿Estás seguro?`,
        text: `Estás a punto de ${action} a ${user.name} ${user.last_name}.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: user.activo ? '#d33' : '#3085d6',
        cancelButtonColor: '#6B7280',
        confirmButtonText: `Sí, ${action}`,
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        try {
            const response = await api[method](endpoint);
            Swal.fire('¡Actualizado!', response.data.message, 'success');
            fetchData();
        } catch (error) {
            Swal.fire('Error', error.response?.data?.message || `No se pudo ${action} el usuario.`, 'error');
        }
    }
};

// --- MÉTODOS DE PAGINACIÓN ---
const goToPage = (page) => {
    if (page >= 1 && page <= totalPages.value) {
        currentPage.value = page;
    }
};
</script>

<template>
    <div class="p-6 bg-white rounded-lg shadow-md animate-fade-in-up">
        <div class="flex items-start justify-between mb-2">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Panel de Administración de Usuarios</h2>
                <p class="text-gray-500 mb-2">Gestiona los usuarios, sus roles y permisos en el sistema.</p>
            </div>
            <button @click="$emit('close')" class="p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                <X class="w-5 h-5 text-gray-500" />
            </button>
        </div>

        <div class="flex justify-end mb-4">
            <button @click="openCreateModal"
                class="bg-verde-bap hover:bg-verde-bap-dark text-white font-bold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition-all duration-300 flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Crear Usuario
            </button>
        </div>

        <!-- Tarjetas de estadísticas de usuarios -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Total Usuarios -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-xl shadow-lg border border-blue-200 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center shadow-sm">
                                <Users class="w-6 h-6 text-blue-600" />
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-blue-600/80">Total Usuarios</p>
                            <p class="text-2xl font-bold text-blue-700">{{ userStats.total.toLocaleString() }}</p>
                        </div>
                    </div>
                    <div class="flex-shrink-0"><div class="w-3 h-3 bg-blue-400 rounded-full animate-pulse"></div></div>
                </div>
            </div>

            <!-- Activos -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-xl shadow-lg border border-green-200 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center shadow-sm">
                                <UserCheck class="w-6 h-6 text-green-600" />
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-green-600/80">Activos</p>
                            <p class="text-2xl font-bold text-green-700">{{ userStats.activos.toLocaleString() }}</p>
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="flex space-x-1">
                            <div class="w-2 h-2 bg-green-400 rounded-full animate-bounce"></div>
                            <div class="w-2 h-2 bg-green-500 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                            <div class="w-2 h-2 bg-green-600 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inactivos -->
            <div class="bg-gradient-to-br from-red-50 to-red-100 p-6 rounded-xl shadow-lg border border-red-200 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center shadow-sm">
                                <UserX class="w-6 h-6 text-red-600" />
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-red-600/80">Inactivos</p>
                            <p class="text-2xl font-bold text-red-700">{{ userStats.inactivos.toLocaleString() }}</p>
                        </div>
                    </div>
                    <div class="flex-shrink-0"><div class="w-8 h-8 bg-red-200 rounded-full animate-ping opacity-75"></div></div>
                </div>
            </div>

            <!-- Roles / Áreas -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-xl shadow-lg border border-purple-200 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center shadow-sm">
                                <Shield class="w-6 h-6 text-purple-600" />
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-purple-600/80">Roles / Áreas</p>
                            <p class="text-2xl font-bold text-purple-700">{{ userStats.rolesDistintos }} / {{ userStats.areasDistintas }}</p>
                        </div>
                    </div>
                    <div class="flex-shrink-0"><div class="w-3 h-3 bg-purple-400 rounded-full"></div></div>
                </div>
            </div>
        </div>

        <!-- Panel de filtros (separado como PanelAuditoria) -->
        <div class="mb-4 p-4 bg-gray-50 rounded-lg shadow-sm border border-gray-200">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="form-group">
                    <label class="form-label" for="filtro-nombre">Nombre Completo</label>
                    <input id="filtro-nombre" type="text" v-model="filtros.nombre" placeholder="Ej: Juan Pérez"
                           class="form-input" />
                </div>
                <div class="form-group">
                    <label class="form-label" for="filtro-email">Email</label>
                    <input id="filtro-email" type="text" v-model="filtros.email" placeholder="Ej: usuario@dominio.com"
                           class="form-input" />
                </div>
                <div class="form-group">
                    <label class="form-label" for="filtro-rol">Rol</label>
                    <select id="filtro-rol" v-model="filtros.roleId" class="form-input">
                        <option :value="null">Todos</option>
                        <option v-for="rol in roles" :key="rol.id" :value="rol.id">{{ rol.display_name }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filtro-area">Área</label>
                    <select id="filtro-area" v-model="filtros.areaId" class="form-input">
                        <option :value="null">Todas</option>
                        <option v-for="area in areas" :key="area.id" :value="area.id">{{ area.name }}</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="filtro-estado">Estado</label>
                    <select id="filtro-estado" v-model="filtros.estado" class="form-input">
                        <option value="">Todos</option>
                        <option value="Activo">Activo</option>
                        <option value="Inactivo">Inactivo</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <button @click="filtros = { nombre: '', email: '', roleId: null, areaId: null, estado: '' }"
                        :disabled="!hayFiltrosActivos"
                        class="px-4 py-2 border border-gray-300 text-gray-700 bg-white rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                    Limpiar filtros
                </button>
            </div>
        </div>

        <div v-if="isLoading" class="text-center py-10 text-gray-500">Cargando usuarios...</div>
        <div v-else class="overflow-x-auto">
            <div class="mb-4 text-sm text-gray-600 text-center" v-if="totalPages > 0">
                Mostrando <strong>{{ (currentPage - 1) * itemsPerPage + 1 }}</strong> -
                <strong>{{ Math.min(currentPage * itemsPerPage, totalItems) }}</strong>
                de <strong>{{ totalItems }}</strong> usuarios
            </div>
            <table class="min-w-full bg-white shadow-md rounded-lg">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Nombre Completo</th>
                        <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Email</th>
                        <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Rol</th>
                        <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Área</th>
                        <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Estado</th>
                        <th class="py-3 px-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                            Acciones</th>
                    </tr>
                </thead>
                <transition-group name="user-list" tag="tbody" class="text-gray-700">
                    <tr v-for="user in paginatedUsers" :key="user.id" class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="py-3 px-4 text-center">{{ user.name }} {{ user.last_name }}</td>
                        <td class="py-3 px-4 text-center">{{ user.email }}</td>
                        <td class="py-3 px-4 text-center">{{ user.role?.display_name || 'N/A' }}</td>
                        <td class="py-3 px-4 text-center">{{ user.area?.name || 'N/A' }}</td>
                        <td class="py-3 px-4 text-center">
                            <span :class="getClassesForAuditoriaBadge(user.activo ? 'Activo' : 'Inactivo')">
                                {{ user.activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-center">
                            <div class="flex item-center justify-center space-x-2">
                                <button @click="openEditModal(user)"
                                    class="p-2 rounded-full hover:bg-gray-200 transition-colors" title="Editar"><svg
                                        class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z">
                                        </path>
                                    </svg></button>
                                <button @click="handleToggleActive(user)"
                                    class="p-2 rounded-full hover:bg-gray-200 transition-colors"
                                    :title="user.activo ? 'Desactivar' : 'Activar'">
                                    <svg v-if="user.activo" class="w-5 h-5 text-red-500" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636">
                                        </path>
                                    </svg>
                                    <svg v-else class="w-5 h-5 text-green-500" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </transition-group>
            </table>
            <p v-if="!paginatedUsers.length" class="text-center py-10 text-gray-500">No hay usuarios para mostrar.</p>

            <div class="mt-6" v-if="totalPages > 0">
                <div class="flex justify-center items-center space-x-1">
                    <button @click="paginaAnterior" :disabled="currentPage === 1"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center"
                        :class="currentPage === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Anterior
                    </button>
                    <button v-for="pagina in paginasVisibles" :key="pagina" @click="irAPagina(pagina)"
                        class="w-10 h-10 rounded-lg text-sm font-medium transition-colors duration-200 border"
                        :class="[
                            currentPage === pagina ? 'bg-verde-bap text-white border-verde-bap-dark shadow-md' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-200',
                            pagina === '...' ? 'cursor-default' : ''
                        ]">
                        {{ pagina }}
                    </button>
                    <button @click="paginaSiguiente" :disabled="currentPage === totalPages"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center"
                        :class="currentPage === totalPages ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'">
                        Siguiente
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                </div>
                <div class="text-center text-sm text-gray-500 mt-2">
                    Página {{ currentPage }} de {{ totalPages }}
                </div>
            </div>
        </div>
        <div v-if="!isLoading && totalPages > 1" class="mt-6 flex justify-between items-center text-sm text-gray-600">
            <span>Página <strong>{{ currentPage }}</strong> de <strong>{{ totalPages }}</strong></span>
            <div class="inline-flex items-center -space-x-px">
                <button @click="goToPage(currentPage - 1)" :disabled="currentPage === 1"
                    class="px-3 py-1 rounded-l-md border bg-white hover:bg-gray-100 disabled:opacity-50">Anterior</button>
                <button @click="goToPage(currentPage + 1)" :disabled="currentPage === totalPages"
                    class="px-3 py-1 rounded-r-md border bg-white hover:bg-gray-100 disabled:opacity-50">Siguiente</button>
            </div>
        </div>

        <!-- Modal para Crear/Editar Usuario -->
        <Transition name="modal-backdrop">
            <div v-if="isModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm"
                @click.self="closeModal">
                <div @click.stop
                    class="bg-white rounded-2xl shadow-xl w-full max-w-2xl mx-auto transform animate-modal-scale">
                    <form @submit.prevent="handleSave">
                        <div class="p-6 border-b">
                            <h3 class="text-xl font-semibold text-gray-800">{{ isEditing ? 'Editar Usuario' : 'Crea Nuevo Usuario' }}</h3>
                        </div>
                        <div class="p-6 space-y-4 max-h-[60vh] overflow-y-auto">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div><label class="form-label">Nombres <span
                                            class="text-rojo-bap">*</span></label><input v-model="currentUser.name"
                                        type="text" class="form-input" required></div>
                                <div><label class="form-label">Apellidos <span
                                            class="text-rojo-bap">*</span></label><input v-model="currentUser.last_name"
                                        type="text" class="form-input" required></div>
                                <div><label class="form-label">Email <span class="text-rojo-bap">*</span></label><input
                                        v-model="currentUser.email" type="email" class="form-input" required></div>
                                <div><label class="form-label">N° Documento <span
                                            class="text-rojo-bap">*</span></label><input
                                        v-model="currentUser.numero_documento_identidad" type="text" class="form-input"
                                        required></div>
                                <div><label class="form-label">Cargo</label><input v-model="currentUser.cargo"
                                        type="text" class="form-input"></div>
                                <div><label class="form-label">Teléfono</label><input v-model="currentUser.telefono"
                                        type="text" class="form-input"></div>
                                <div><label class="form-label">Rol <span class="text-rojo-bap">*</span></label><select
                                        v-model="currentUser.role_id" class="form-input" required>
                                        <option :value="null" disabled>Seleccione un rol</option>
                                        <option v-for="rol in roles" :key="rol.id" :value="rol.id">{{ rol.display_name
                                            }}</option>
                                    </select></div>
                                <div><label class="form-label">Área <span class="text-rojo-bap">*</span></label><select
                                        v-model="currentUser.area_id" class="form-input" required>
                                        <option :value="null" disabled>Seleccione un área</option>
                                        <option v-for="area in areas" :key="area.id" :value="area.id">{{ area.name }}
                                        </option>
                                    </select></div>
                            </div>
                            <div class="border-t pt-4">
                                <p class="text-sm text-gray-600 mb-2">{{ isEditing ? 'Dejar en blanco para no cambiar la contraseña.' : 'Establecer contraseña para el nuevo usuario.' }}</p>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div><label class="form-label">Contraseña <span v-if="!isEditing"
                                                class="text-rojo-bap">*</span></label><input
                                            v-model="currentUser.password" type="password" class="form-input"
                                            :required="!isEditing"></div>
                                    <div><label class="form-label">Confirmar Contraseña <span v-if="!isEditing"
                                                class="text-rojo-bap">*</span></label><input
                                            v-model="currentUser.password_confirmation" type="password"
                                            class="form-input" :required="!isEditing"></div>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 bg-gray-50 rounded-b-2xl flex justify-end space-x-4">
                            <button type="button" @click="closeModal"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded-lg transition-colors">Cancelar</button>
                            <button type="submit"
                                class="bg-verde-bap hover:bg-verde-bap-dark text-white font-bold py-2 px-4 rounded-lg transition-colors">{{
                                    isEditing ? 'Actualizar Usuario' : 'Guardar Usuario' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped lang="postcss">
.user-list-enter-active,
.user-list-leave-active {
    transition: all 0.4s ease-out;
}

.user-list-enter-from,
.user-list-leave-to {
    opacity: 0;
    transform: translateY(12px);
}

.user-list-leave-active {
    position: absolute;
}

.user-list-move {
    transition: transform 0.4s ease;
}
.form-label {
    @apply block text-sm font-medium text-gray-700 mb-1;
}

.form-input {
    @apply mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap;
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
