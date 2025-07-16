<script setup>
import { ref, computed, shallowRef, defineProps } from 'vue';

// --- IMPORTACIÓN DE SUB-COMPONENTES ---
// Se importan los componentes que se renderizarán dinámicamente.
// Por ahora, son marcadores de posición. Deberás crear estos archivos.
import PanelCatalogos from '@/components/administracion/PanelCatalogos.vue';
import PanelUsuarios from '@/components/administracion/PanelUsuarios.vue';

// --- PROPS ---
// Se recibe el objeto 'user' desde el componente padre (MainLayout.vue)
// para utilizar su información de rol y permisos.
const props = defineProps({
    user: {
        type: Object,
        default: () => null
    }
});

// --- ESTADO REACTIVO ---
// 'activeSection' controla qué panel se muestra actualmente.
const activeSection = ref(null);
// 'activeComponent' almacena la referencia al componente que se debe renderizar.
// 'shallowRef' es una optimización para componentes.
const activeComponent = shallowRef(null);

// --- LÓGICA DE PERMISOS ---
/**
 * Función de ayuda para verificar si el usuario tiene un permiso específico.
 * @param {string} permissionName - El nombre del permiso a verificar.
 * @returns {boolean}
 */
const hasPermission = (permissionName) => {
    if (!props.user?.role?.permissions) {
        return false;
    }
    return props.user.role.permissions.some(p => p.name === permissionName);
};

// --- PROPIEDADES COMPUTADAS PARA VISIBILIDAD DE CARDS ---

/**
 * Determina si se debe mostrar la card del "Panel de Catálogos".
 * Visible solo para usuarios con el permiso 'admin.catalogos.manage'.
 * Según los seeders, esto aplica al Jefe de Administración y Super Admin.
 */
const canViewCatalogos = computed(() => hasPermission('admin.catalogos.manage'));

/**
 * Determina si se debe mostrar la card del "Panel de Usuarios".
 * Visible solo para usuarios con el permiso 'admin.users.manage'.
 * Según los seeders, esto aplica al Jefe de Administración y Super Admin.
 */
const canViewUsuarios = computed(() => hasPermission('admin.users.manage'));


// --- MÉTODOS ---

/**
 * Maneja el clic en una de las cards de sección.
 * Carga el componente correspondiente o lo oculta si ya estaba activo.
 * @param {string} section - El nombre de la sección ('catalogos' o 'usuarios').
 */
const handleCardClick = (section) => {
    if (activeSection.value === section) {
        // Si se hace clic en la misma card, se cierra la sección.
        activeSection.value = null;
        activeComponent.value = null;
    } else {
        // Se activa la nueva sección y se carga el componente correspondiente.
        activeSection.value = section;
        if (section === 'catalogos') {
            activeComponent.value = PanelCatalogos;
        } else if (section === 'usuarios') {
            activeComponent.value = PanelUsuarios;
        }
    }
};

/**
 * Devuelve clases CSS dinámicas para resaltar la card activa.
 * @param {string} section - El nombre de la sección a verificar.
 * @returns {string} Clases de Tailwind CSS.
 */
const getCardClasses = (section) => {
    return activeSection.value === section
        ? 'ring-4 ring-white/50 shadow-glow-verde animate-bounce-scale'
        : 'shadow-soft hover:shadow-strong';
};
</script>

<template>
    <div class="min-h-screen bg-verde-bap-extralight p-6 scroll-smooth-custom">
        <div class="max-w-7xl mx-auto space-y-8">

            <!-- Encabezado del Módulo -->
            <div class="bg-white rounded-xl shadow-lg p-8 mb-8 animate-fade-in-down">
                <div class="text-center">
                    <h1 class="text-3xl md:text-4xl font-bold mb-4 tracking-tight text-gray-700 text-shadow">
                        MÓDULO DE ADMINISTRACIÓN
                    </h1>
                    <p class="text-gray-600 max-w-4xl mx-auto text-base leading-relaxed">
                        Gestiona los catálogos del sistema y administra los usuarios y sus roles desde este panel
                        central.
                    </p>
                </div>
            </div>

            <!-- Contenedor de las Cards de Navegación -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-12">

                <!-- Card 1: Panel de Catálogos -->
                <div v-if="canViewCatalogos" @click="handleCardClick('catalogos')" class="group relative overflow-hidden rounded-3xl cursor-pointer
                           bg-gradient-to-br from-blue-500 to-blue-700
                           transition-all duration-500 ease-out
                           transform hover:scale-105 hover:-translate-y-3
                           animate-fade-in-up card-float focus:outline-none focus:ring-4 focus:ring-blue-400/50"
                    :class="getCardClasses('catalogos')" style="animation-delay: 0.1s" tabindex="0">
                    <div class="relative z-10 p-8 text-center text-white h-full flex flex-col justify-center">
                        <div class="mb-4 flex justify-center">
                            <div
                                class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2h2">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold mb-2">PANEL DE CATÁLOGOS</h3>
                        <p class="text-sm text-white/90">Gestionar Proyectos, Gastos y Cuentas Contables.</p>
                    </div>
                </div>

                <!-- Card 2: Panel de Usuarios -->
                <div v-if="canViewUsuarios" @click="handleCardClick('usuarios')" class="group relative overflow-hidden rounded-3xl cursor-pointer
                           bg-gradient-to-br from-slate-600 to-slate-800
                           transition-all duration-500 ease-out
                           transform hover:scale-105 hover:-translate-y-3
                           animate-fade-in-up card-float focus:outline-none focus:ring-4 focus:ring-slate-400/50"
                    :class="getCardClasses('usuarios')" style="animation-delay: 0.2s" tabindex="0">
                    <div class="relative z-10 p-8 text-center text-white h-full flex flex-col justify-center">
                        <div class="mb-4 flex justify-center">
                            <div
                                class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.653-.125-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.653.125-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold mb-2">PANEL DE USUARIOS</h3>
                        <p class="text-sm text-white/90">Administrar usuarios, roles y permisos del sistema.</p>
                    </div>
                </div>
            </div>

            <!-- Contenedor del Componente Activo -->
            <div v-if="activeSection" class="mt-12">
                <div class="glass-modal rounded-3xl p-4 sm:p-8 animate-scale-in">
                    <component :is="activeComponent" :usuario-actual="props.user" @close="activeSection = null" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Solo mantener estilos específicos que no se pueden hacer con Tailwind */

/* Keyframe personalizado para el click de card (si no está en tailwind.config.js) */
@keyframes cardClickCustom {
    0% {
        transform: scale(1) translateY(0);
    }

    50% {
        transform: scale(0.98) translateY(2px);
    }

    100% {
        transform: scale(1) translateY(0);
    }
}

/* Aplicar animación de click al hacer active */
.animate-card-click-custom {
    animation: cardClickCustom 0.15s ease-in-out;
}

/* Fallback para navegadores que no soportan las utilidades de Tailwind */
@supports not (backdrop-filter: blur(20px)) {
    .glass-modal {
        background: rgba(255, 255, 255, 0.95);
    }
}
</style>