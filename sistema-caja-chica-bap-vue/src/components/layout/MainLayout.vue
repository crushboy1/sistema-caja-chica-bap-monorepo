<script setup>
import { ref, onMounted, computed } from 'vue'; // Importar 'computed'
import { useRouter } from 'vue-router';
import api from '@/plugins/axios'; // Importa tu instancia de Axios configurada

const user = ref(null);
const isLoadingUser = ref(true);

const router = useRouter();

// --- Roles de Usuario Definidos (para referencia) ---
const ROLES = {
  JEFE_AREA: 'jefe_area',
  JEFE_ADM: 'jefe_administracion',
  GERENTE_GENERAL: 'gerente_general',
  SUPER_ADMIN: 'super_admin',
  COLABORADOR: 'colaborador'
};

// --- Propiedades Computadas para el Usuario ---
const rolUsuario = computed(() => {
  const roleName = user.value?.role?.name || null;
  console.log('DEBUG MainLayout: rolUsuario computed ->', roleName);
  return roleName;
});

const userPermissions = computed(() => {
  const permissions = user.value?.role?.permissions || [];
  console.log('DEBUG MainLayout: userPermissions computed ->', permissions);
  return permissions;
});

// --- Función de Ayuda para Verificar Permisos ---
const hasPermission = (permissionName) => {
  if (!user.value || !user.value.role || !user.value.role.permissions) {
    console.log(`DEBUG MainLayout: hasPermission('${permissionName}') -> false (user, role o permissions no definidos)`);
    return false;
  }
  const permissionNames = user.value.role.permissions.map(p => p.name);
  const hasPerm = permissionNames.includes(permissionName);
  console.log(`DEBUG MainLayout: hasPermission('${permissionName}') -> ${hasPerm}. Permisos disponibles:`, permissionNames);
  return hasPerm;
};


// Función para obtener los datos del usuario autenticado
const fetchAuthenticatedUser = async () => {
  try {
    const response = await api.get('/user');
    console.log('DEBUG: Respuesta completa de /user:', response.data);
    console.log('DEBUG: Solo user de /user:', response.data.user);
    console.log('DEBUG: Role desde /user:', response.data.user?.role);
    console.log('DEBUG: Permissions desde /user:', response.data.user?.role?.permissions);
    
    user.value = response.data.user;
  } catch (error) {
    console.error('Error:', error);
  } finally {
    isLoadingUser.value = false;
  }
};

// Función para manejar el logout
const handleLogout = async () => {
  try {
    await api.post('/auth/logout');
    console.log('Logout exitoso.');
    router.push('/login');
  } catch (error) {
    console.error('Error durante el logout:', error);
    router.push('/login'); // Redirigir al login incluso si hay un error en el logout
  }
};

onMounted(() => {
  fetchAuthenticatedUser();
});
</script>

<template>
  <div class="min-h-screen bg-verde-bap-light flex flex-col"> 
    <nav class="glass shadow-soft p-6 flex justify-between items-center backdrop-blur-sm">
      <div class="flex items-center">
        <img src="/src/assets/images/logo-wt.svg" alt="Logo BAP" class="h-10 mr-4" />
        <span class="text-xl font-semibold text-gray-800">Sistema Gestión de Fondos</span>
      </div>

      <div class="flex space-x-6 ">
        <router-link to="/dashboard" class="text-gray-600 font-medium nav-link-item" active-class="router-link-exact-active">
          Dashboard
        </router-link>
        <router-link to="/dashboard/solicitudes" 
        class="text-gray-600 font-medium nav-link-item"
        style="--underline-color: var(--color-rojo-bap);"
        >
          Solicitudes
        </router-link>
        <router-link
          to="/dashboard/declaraciones"
          class="text-gray-600 font-medium nav-link-item"
          style="--underline-color: var(--color-amarillo-bap);"
        >
          Declaraciones
        </router-link>
        <router-link to="/dashboard/gestion-usuarios" 
        class="text-gray-600 font-medium nav-link-item"
        >
          Gestión de Usuarios
        </router-link>
        </div>

      <div class="flex items-center space-x-4">
        <div v-if="isLoadingUser" class="text-gray-500">Cargando...</div>
        <div v-else class="text-gray-700 font-medium">
          <h2>Bienvenido!</h2>{{ user ? user.name : 'Invitado' }}
        </div>

        <button
          @click="handleLogout"
          class="bg-rojo-bap hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-full transition-colors"
        >
          Cerrar Sesión
        </button>
      </div>
    </nav>

    <main class="bg-verde-bap-light flex-grow p-8">
      <div class="max-w-7xl mx-auto glass rounded-2xl shadow-soft p-8 animate-fade-in-up">
        <router-view></router-view> 
      </div>
    </main>

    <footer class="bg-verde-bap text-white p-4 text-center text-sm">
      © 2025 Sistema Gestión de Fondos BAP. Todos los derechos reservados.
    </footer>
  </div>
</template>

<style scoped>
/* No se necesitan estilos scoped adicionales en este componente.
    Los estilos para la barra inferior y router-link-active se manejan en main.css. */
</style>
