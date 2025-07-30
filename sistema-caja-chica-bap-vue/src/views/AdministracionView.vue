<script setup>
import { ref, computed, shallowRef, defineProps, nextTick } from 'vue';

// --- IMPORTACIÓN DE SUB-COMPONENTES ---
import PanelCatalogos from '@/components/administracion/PanelCatalogos.vue';
import PanelUsuarios from '@/components/administracion/PanelUsuarios.vue';
import PanelCierres from '@/components/administracion/PanelCierres.vue';

// --- PROPS ---
const props = defineProps({
    user: {
        type: Object,
        default: () => null
    }
});

// --- ESTADO REACTIVO ---
const activeSection = ref(null);
const activeComponent = shallowRef(null);
const isProcessingClick = ref(false); // Prevenir clics múltiples

// --- LÓGICA DE PERMISOS ---
const hasPermission = (permissionName) => {
    if (!props.user?.role?.permissions) {
        return false;
    }
    return props.user.role.permissions.some(p => p.name === permissionName);
};

// --- PROPIEDADES COMPUTADAS PARA VISIBILIDAD DE CARDS ---
const canViewCatalogos = computed(() => hasPermission('admin.catalogos.manage'));
const canViewUsuarios = computed(() => hasPermission('admin.users.manage'));
const canViewCierres = computed(() => hasPermission('admin.cierres.manage'));

// --- MÉTODOS MEJORADOS ---

/**
 * Maneja el clic en una de las cards de sección con protección contra clics múltiples.
 */
const handleCardClick = async (section) => {
    // Prevenir clics múltiples
    if (isProcessingClick.value) {
        return;
    }

    isProcessingClick.value = true;

    try {
        if (activeSection.value === section) {
            // Si se hace clic en la misma card activa, se cierra
            closeSection();
        } else {
            // Se activa la nueva sección
            await openSection(section);
        }
    } catch (error) {
        console.error('Error al cambiar sección:', error);
    } finally {
        // Liberar el lock después de un breve delay
        setTimeout(() => {
            isProcessingClick.value = false;
        }, 300);
    }
};

/**
 * Abre una sección específica
 */
const openSection = async (section) => {
    // Primero cerrar cualquier sección activa
    if (activeSection.value) {
        closeSection();
        // Esperar un tick para que el DOM se actualice
        await nextTick();
    }

    // Activar la nueva sección
    activeSection.value = section;

    // Cargar el componente correspondiente
    switch (section) {
        case 'catalogos':
            activeComponent.value = PanelCatalogos;
            break;
        case 'usuarios':
            activeComponent.value = PanelUsuarios;
            break;
        case 'cierres':
            activeComponent.value = PanelCierres;
            break;
        default:
            console.warn(`Sección desconocida: ${section}`);
            activeComponent.value = null;
    }

    // Log para debugging
    console.log(`Sección activada: ${section}`);
};

/**
 * Cierra la sección activa
 */
const closeSection = () => {
    activeSection.value = null;
    activeComponent.value = null;
    console.log('Sección cerrada');
};

/**
 * Maneja el evento de cierre desde el componente hijo
 */
const handleChildClose = () => {
    closeSection();
};

/**
 * Maneja eventos de teclado para accesibilidad
 */
const handleKeydown = (event, section) => {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        handleCardClick(section);
    }
};

/**
 * Devuelve clases CSS dinámicas para resaltar la card activa.
 */
const getCardClasses = (section) => {
    const baseClasses = 'shadow-soft hover:shadow-strong transition-all duration-300';
    const activeClasses = 'ring-4 ring-white/50 shadow-glow-verde scale-105';

    return activeSection.value === section
        ? `${baseClasses} ${activeClasses}`
        : baseClasses;
};

/**
 * Obtiene el ícono SVG para cada sección
 */
const getSectionIcon = (section) => {
    const icons = {
        catalogos: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2h2"></path>`,
        cierres: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>`,
        usuarios: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.653-.125-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.653.125-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>`
    };
    return icons[section] || '';
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mt-12">

                <!-- Card 1: Panel de Catálogos -->
                <div v-if="canViewCatalogos" @click="handleCardClick('catalogos')"
                    @keydown="handleKeydown($event, 'catalogos')" class="group relative overflow-hidden rounded-3xl cursor-pointer
                           bg-gradient-to-br from-blue-500 to-blue-700
                           transform hover:scale-105 hover:-translate-y-2
                           animate-fade-in-up card-float focus:outline-none focus:ring-4 focus:ring-blue-400/50
                           disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="[getCardClasses('catalogos'), { 'pointer-events-none': isProcessingClick }]"
                    style="animation-delay: 0.1s" tabindex="0" role="button"
                    :aria-pressed="activeSection === 'catalogos'" aria-label="Abrir Panel de Catálogos">

                    <div
                        class="relative z-10 p-8 text-center text-white h-full flex flex-col justify-center min-h-[200px]">
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

                    <!-- Indicador de estado activo -->
                    <div v-if="activeSection === 'catalogos'"
                        class="absolute top-4 right-4 w-3 h-3 bg-white rounded-full animate-pulse">
                    </div>
                </div>

                <!-- Card 2: Cierres Contables -->
                <div v-if="canViewCierres" @click="handleCardClick('cierres')"
                    @keydown="handleKeydown($event, 'cierres')" class="group relative overflow-hidden rounded-3xl cursor-pointer
                           bg-gradient-to-br from-orange-500 to-red-600
                           transform hover:scale-105 hover:-translate-y-2
                           animate-fade-in-up card-float focus:outline-none focus:ring-4 focus:ring-orange-400/50
                           disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="[getCardClasses('cierres'), { 'pointer-events-none': isProcessingClick }]"
                    style="animation-delay: 0.2s" tabindex="0" role="button" :aria-pressed="activeSection === 'cierres'"
                    aria-label="Abrir Panel de Cierres Contables">

                    <div
                        class="relative z-10 p-8 text-center text-white h-full flex flex-col justify-center min-h-[200px]">
                        <div class="mb-4 flex justify-center">
                            <div
                                class="w-16 h-16 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                    </path>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-xl font-bold mb-2">CIERRES CONTABLES</h3>
                        <p class="text-sm text-white/90">Gestionar períodos y excepciones de registro.</p>
                    </div>

                    <!-- Indicador de estado activo -->
                    <div v-if="activeSection === 'cierres'"
                        class="absolute top-4 right-4 w-3 h-3 bg-white rounded-full animate-pulse">
                    </div>
                </div>

                <!-- Card 3: Panel de Usuarios -->
                <div v-if="canViewUsuarios" @click="handleCardClick('usuarios')"
                    @keydown="handleKeydown($event, 'usuarios')" class="group relative overflow-hidden rounded-3xl cursor-pointer
                           bg-gradient-to-br from-slate-600 to-slate-800
                           transform hover:scale-105 hover:-translate-y-2
                           animate-fade-in-up card-float focus:outline-none focus:ring-4 focus:ring-slate-400/50
                           disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="[getCardClasses('usuarios'), { 'pointer-events-none': isProcessingClick }]"
                    style="animation-delay: 0.3s" tabindex="0" role="button"
                    :aria-pressed="activeSection === 'usuarios'" aria-label="Abrir Panel de Usuarios">

                    <div
                        class="relative z-10 p-8 text-center text-white h-full flex flex-col justify-center min-h-[200px]">
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

                    <!-- Indicador de estado activo -->
                    <div v-if="activeSection === 'usuarios'"
                        class="absolute top-4 right-4 w-3 h-3 bg-white rounded-full animate-pulse">
                    </div>
                </div>

            </div>

            <!-- Estado de carga mientras se procesa el clic -->
            <div v-if="isProcessingClick" class="text-center py-4">
                <div class="inline-flex items-center text-gray-600">
                    <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z">
                        </path>
                    </svg>
                    Cargando sección...
                </div>
            </div>

            <!-- Contenedor del Componente Activo -->
            <Transition enter-active-class="transition-all duration-500 ease-out"
                enter-from-class="opacity-0 transform translate-y-8 scale-95"
                enter-to-class="opacity-100 transform translate-y-0 scale-100"
                leave-active-class="transition-all duration-300 ease-in"
                leave-from-class="opacity-100 transform translate-y-0 scale-100"
                leave-to-class="opacity-0 transform translate-y-4 scale-95">
                <div v-if="activeSection && activeComponent" class="mt-12">
                    <div class="glass-modal rounded-3xl p-4 sm:p-8 relative">
                        <!-- Botón de cerrar en la esquina superior derecha -->
                        <button @click="closeSection"
                            class="absolute top-4 right-4 z-10 p-2 hover:bg-gray-100/80 rounded-full transition-colors duration-200 group"
                            aria-label="Cerrar panel">
                            <svg class="w-6 h-6 text-gray-500 group-hover:text-gray-700" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>

                        <!-- Componente dinámico -->
                        <component :is="activeComponent" :usuario-actual="props.user" @close="handleChildClose" />
                    </div>
                </div>
            </Transition>
        </div>
    </div>
</template>

<style scoped>
/* Keyframes personalizados */
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

@keyframes fade-in-down {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Aplicar animaciones */
.animate-fade-in-down {
    animation: fade-in-down 0.6s ease-out;
}

.animate-fade-in-up {
    animation: fade-in-up 0.6s ease-out both;
}

.animate-card-click-custom {
    animation: cardClickCustom 0.15s ease-in-out;
}

/* Glass effect mejorado */
.glass-modal {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Fallback para navegadores sin soporte */
@supports not (backdrop-filter: blur(20px)) {
    .glass-modal {
        background: rgba(255, 255, 255, 0.98);
    }
}

/* Card floating effect */
.card-float {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.card-float:hover {
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}
</style>