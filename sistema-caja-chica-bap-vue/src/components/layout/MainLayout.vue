<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/plugins/axios';

const user = ref(null);
const isLoadingUser = ref(true);
const showMobileMenu = ref(false);
const router = useRouter();

// --- Lógica de Permisos y Roles ---

/**
 * Función de ayuda para verificar si el usuario tiene un permiso específico.
 * @param {string} permissionName - El nombre del permiso a verificar (ej. 'solicitudes.navigate').
 * @returns {boolean} - True si el usuario tiene el permiso, de lo contrario false.
 */
const hasPermission = (permissionName) => {
  // Si no hay usuario o permisos definidos, no tiene permiso.
  if (!user.value?.role?.permissions) {
    return false;
  }
  // Comprueba si algún permiso en el array del usuario coincide con el nombre buscado.
  return user.value.role.permissions.some(p => p.name === permissionName);
};

// --- Propiedades Computadas para la Visibilidad de los Enlaces de Navegación ---

// Determina si se debe mostrar el enlace a "Solicitudes".
const canNavigateSolicitudes = computed(() => {
  return hasPermission('navigate.solicitudes');
});

// Determina si se debe mostrar el enlace a "Declaraciones".
const canNavigateDeclaraciones = computed(() => {
  return hasPermission('navigate.declaraciones');
});

// Determina si se debe mostrar el enlace a "Fondos".
const canNavigateFondos = computed(() => {
  return hasPermission('navigate.fondos');
});

// Determina si se debe mostrar el enlace a "Administración".
const canNavigateAdministracion = computed(() => {
  return hasPermission('navigate.administracion');
});

// --- Propiedades Computadas para Información del Usuario ---

// Propiedad para obtener el nombre completo del usuario.
const userFullName = computed(() => {
  if (!user.value) return 'Invitado';
  return `${user.value.name || ''} ${user.value.last_name || ''}`.trim();
});

//  Propiedad para obtener el área del usuario.
const userArea = computed(() => user.value?.area?.name || 'Área no asignada');

// Propiedad para obtener el cargo del usuario.
const userCargo = computed(() => user.value?.cargo || 'Cargo no asignado');

// --- Lógica del Componente ---

// Función para obtener los datos del usuario autenticado
const fetchAuthenticatedUser = async () => {
  isLoadingUser.value = true;
  try {
    const response = await api.get('/auth/user');
    user.value = response.data;
  } catch {
    // Error al obtener datos del usuario, redirigir al login
    router.push('/login');
  } finally {
    isLoadingUser.value = false;
  }
};

// Función para manejar el logout del usuario.
const handleLogout = async () => {
  try {
    await api.post('/auth/logout');
  } catch {
    // Error durante el logout, continuar con la limpieza del estado
  } finally {
    user.value = null;
    router.push('/login');
  }
};

// Funciones para el menú móvil
const toggleMobileMenu = () => {
  showMobileMenu.value = !showMobileMenu.value;
};

const closeMobileMenu = () => {
  showMobileMenu.value = false;
};

onMounted(() => {
  fetchAuthenticatedUser();
});
</script>

<template>
  <div class="min-h-screen bg-verde-bap-light flex flex-col">
    <nav class="bg-white/70 shadow-soft p-4 backdrop-blur-sm sticky top-0 z-50 border-b border-white/30">
      <div class="max-w-screen-2xl mx-auto flex items-center justify-between">
        <!-- Logo y Título - Sección Izquierda -->
        <div class="flex items-center flex-shrink-0 min-w-0">
          <img src="/src/assets/images/logo-wt.svg" alt="Logo BAP" class="h-10 mr-3 flex-shrink-0" />
          <span class="text-lg xl:text-xl font-semibold text-gris-bap-dark truncate">Sistema Gestión de Fondos</span>
        </div>

        <!-- Enlaces de Navegación - Sección Central -->
        <div class="hidden lg:flex items-center justify-center flex-1 mx-8">
          <div class="flex space-x-6 xl:space-x-8">
            <router-link to="/dashboard" class="text-gris-bap-dark font-medium nav-link-item whitespace-nowrap"
              active-class="router-link-exact-active">
              Dashboard
            </router-link>
            <router-link v-if="canNavigateSolicitudes" to="/dashboard/solicitudes"
              class="text-gris-bap-dark font-medium nav-link-item whitespace-nowrap"
              style="--underline-color: var(--color-rojo-bap);">
              Solicitudes
            </router-link>
            <router-link v-if="canNavigateDeclaraciones" to="/dashboard/declaraciones"
              class="text-gris-bap-dark font-medium nav-link-item whitespace-nowrap"
              style="--underline-color: var(--color-amarillo-bap);">
              Declaraciones
            </router-link>
            <router-link v-if="canNavigateFondos" to="/dashboard/fondos"
              class="text-gris-bap-dark font-medium nav-link-item whitespace-nowrap"
              style="--underline-color: var(--color-verde-bap);">
              Fondos
            </router-link>
            <router-link v-if="canNavigateAdministracion" to="/dashboard/administracion"
              class="text-gris-bap-dark font-medium nav-link-item whitespace-nowrap"
              style="--underline-color: var(--color-rojo-bap);">
              Administración
            </router-link>
          </div>
        </div>

        <!-- Menú hamburguesa para pantallas medianas -->
        <div class="lg:hidden flex items-center">
          <button @click="toggleMobileMenu" class="text-gris-bap-dark hover:text-gris-bap p-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
            </svg>
          </button>
        </div>

        <!-- Información de Usuario y Logout - Sección Derecha -->
        <div class="hidden lg:flex items-center space-x-3 xl:space-x-4 flex-shrink-0">
          <div v-if="isLoadingUser" class="text-sm text-gray-500">Cargando...</div>
          <div v-else class="flex items-center space-x-2 xl:space-x-3 bg-white/50 p-2 rounded-full">
            <div class="p-2 bg-verde-bap-light rounded-full border border-verde-bap/20">
              <svg class="w-5 h-5 xl:w-6 xl:h-6 text-verde-bap-dark" fill="none" stroke="currentColor"
                viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
              </svg>
            </div>
            <div class="text-right pr-1 xl:pr-2 min-w-0">
              <p class="font-bold text-gris-bap-dark text-xs xl:text-sm leading-tight truncate">{{ userFullName }}</p>
              <p class="text-xs text-gris-bap leading-tight truncate">{{ userArea }} / {{ userCargo }}</p>
            </div>
          </div>
          <button @click="handleLogout"
            class="bg-rojo-bap hover:bg-rojo-bap-dark text-white font-semibold py-2 px-3 xl:px-4 rounded-full transition-colors text-xs xl:text-sm whitespace-nowrap">
            Cerrar Sesión
          </button>
        </div>
      </div>

      <!-- Menú móvil desplegable -->
      <div v-if="showMobileMenu" class="lg:hidden mt-4 pt-4 border-t border-gray-200">
        <div class="flex flex-col space-y-2">
          <router-link to="/dashboard" class="text-gris-bap-dark font-medium py-2 px-3 rounded hover:bg-gray-100"
            active-class="bg-gray-100 text-gray-800" @click="closeMobileMenu">
            Dashboard
          </router-link>
          <router-link v-if="canNavigateSolicitudes" to="/dashboard/solicitudes"
            class="text-gris-bap-dark font-medium py-2 px-3 rounded hover:bg-gray-100"
            active-class="bg-gray-100 text-gray-800" @click="closeMobileMenu">
            Solicitudes
          </router-link>
          <router-link v-if="canNavigateDeclaraciones" to="/dashboard/declaraciones"
            class="text-gray-600 font-medium py-2 px-3 rounded hover:bg-gray-100"
            active-class="bg-gray-100 text-gray-800" @click="closeMobileMenu">
            Declaraciones
          </router-link>
          <router-link v-if="canNavigateFondos" to="/dashboard/fondos"
            class="text-gray-600 font-medium py-2 px-3 rounded hover:bg-gray-100"
            active-class="bg-gray-100 text-gray-800" @click="closeMobileMenu">
            Fondos
          </router-link>
          <router-link v-if="canNavigateAdministracion" to="/dashboard/administracion"
            class="text-gray-600 font-medium py-2 px-3 rounded hover:bg-gray-100"
            active-class="bg-gray-100 text-gray-800" @click="closeMobileMenu">
            Administración
          </router-link>
        </div>
      </div>
    </nav>

    <main class="bg-verde-bap-light flex-grow p-8">
      <div class="max-w-screen-2xl mx-auto bg-white/70 backdrop-blur-sm border border-white/30 rounded-2xl shadow-soft p-4 sm:p-6 md:p-8 animate-fade-in-up">
        <router-view v-if="user" :user="user" />
      </div>
    </main>

    <footer class="bg-verde-bap-dark text-white p-4 text-center text-sm">
      © 2025 Sistema Gestión de Fondos BAP. Todos los derechos reservados.
    </footer>
  </div>
</template>

<style scoped>
/* No se necesitan estilos scoped adicionales en este componente.
    Los estilos para la barra inferior y router-link-active se manejan en main.css. */
</style>