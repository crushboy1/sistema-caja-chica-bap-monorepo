<template>
    <div class="min-h-screen bg-verde-bap-extralight p-6 scroll-smooth-custom">
        <div class="max-w-7xl mx-auto space-y-8">

            <!-- Encabezado del Módulo con animación de entrada -->
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

            <!-- Grid de Cards con animaciones escalonadas mejoradas -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-12">
                <div v-for="(card, index) in visibleCards" :key="card.section" @click="handleCardClick(card.section)"
                    @keydown="handleKeydown($event, card.section)"
                    class="group relative overflow-hidden rounded-3xl cursor-pointer transform transition-all duration-300 ease-out shadow-soft animate-fade-in-up card-float focus:outline-none"
                    :class="[getCardClasses(card), getCardBackgroundClass(card.section), { 'pointer-events-none': isProcessingClick }]"
                    :style="{ animationDelay: `${(index + 1) * 0.1}s` }" tabindex="0" role="button"
                    :aria-pressed="activeSection === card.section" :aria-label="`Abrir ${card.title}`">

                    <!-- Efecto shimmer -->
                    <div
                        class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000 ease-out">
                    </div>

                    <!-- Contenido de la card -->
                    <div
                        class="relative z-10 p-6 text-center text-white h-full flex flex-col justify-center min-h-[160px]">
                        <div class="mb-3 flex justify-center">
                            <div
                                class="w-12 h-12 bg-white/20 backdrop-blur-sm rounded-2xl flex items-center justify-center transition-all duration-500 group-hover:scale-110 group-hover:rotate-12 shadow-medium">
                                <component :is="card.icon" :size="20" class="text-white" />
                            </div>
                        </div>
                        <h3 class="text-lg font-bold mb-2 transition-all duration-300 leading-tight">{{ card.title }}
                        </h3>
                        <p
                            class="text-xs text-white/90 group-hover:text-white transition-all duration-300 leading-tight">
                            {{ card.subtitle }}
                        </p>
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

            <!-- Contenedor del Componente Activo con transiciones -->
            <Transition enter-active-class="transition-all duration-500 ease-out"
                enter-from-class="opacity-0 transform translate-y-8 scale-95"
                enter-to-class="opacity-100 transform translate-y-0 scale-100"
                leave-active-class="transition-all duration-300 ease-in"
                leave-from-class="opacity-100 transform translate-y-0 scale-100"
                leave-to-class="opacity-0 transform translate-y-4 scale-95">
                <div v-if="activeSection && activeComponent" class="mt-12">
                    <div class="glass-modal rounded-3xl p-4 sm:p-8 relative">
                        <component :is="activeComponent" :usuario-actual="props.user" @close="handleChildClose" />
                    </div>
                </div>
            </Transition>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, shallowRef, defineProps, nextTick } from 'vue';
import { FolderOpen, Calendar, Users, Shield } from 'lucide-vue-next';

// --- IMPORTACIÓN DE SUB-COMPONENTES ---
import PanelCatalogos from '@/components/administracion/PanelCatalogos.vue';
import PanelUsuarios from '@/components/administracion/PanelUsuarios.vue';
import PanelCierres from '@/components/administracion/PanelCierres.vue';
import PanelAuditoria from '@/components/administracion/PanelAuditoria.vue';

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
const isProcessingClick = ref(false);

// --- LÓGICA DE PERMISOS ---
const hasPermission = (permissionName) => {
    if (!props.user?.role?.permissions) {
        return false;
    }
    return props.user.role.permissions.some(p => p.name === permissionName);
};
const ALL_ADMIN_CARDS = [
    {
        section: 'catalogos',
        permission: 'admin.catalogos.manage',
        title: 'Panel de Catálogos',
        subtitle: 'Gestionar proyectos, cuentas, etc.',
        icon: FolderOpen,
    },
    {
        section: 'usuarios',
        permission: 'admin.users.manage',
        title: 'Panel de Usuarios',
        subtitle: 'Administrar accesos y roles',
        icon: Users,
    },
    {
        section: 'cierres',
        permission: 'admin.cierres.manage',
        title: 'Cierres Contables',
        subtitle: 'Gestionar períodos de registro',
        icon: Calendar,
    },
    {
        section: 'auditoria',
        permission: 'admin.audit.view',
        title: 'Panel de Auditoría',
        subtitle: 'Revisar historial de cambios',
        icon: Shield,
    }
];
// --- PROPIEDADES COMPUTADAS PARA VISIBILIDAD DE CARDS ---
const visibleCards = computed(() => {
    if (!props.user || !props.user.role) return [];
    const userRoleName = props.user.role.name;

    return ALL_ADMIN_CARDS.filter(card => {
        // 1. Verificar si el usuario tiene el permiso necesario para la tarjeta.
        const hasRequiredPermission = hasPermission(card.permission);
        if (!hasRequiredPermission) {
            return false;
        }

        // 2. Aplicar reglas de negocio específicas por rol.
        // Regla: El Gerente General solo debe ver la tarjeta de Auditoría.
        if (userRoleName === 'gerente_general' && card.section !== 'auditoria') {
            return false;
        }

        // Si todas las validaciones pasan, la tarjeta es visible.
        return true;
    });
});

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
        case 'auditoria':
            activeComponent.value = PanelAuditoria;
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
const getCardClasses = (card) => {
    const baseClass = activeSection.value === card.section ? 'ring-4 ring-white/50' : '';
    let glowClass = '';

    switch (card.section) {
        case 'catalogos':
            glowClass = 'hover:shadow-glow-blue';
            break;
        case 'cierres':
            glowClass = 'hover:shadow-glow-orange';
            break;
        case 'usuarios':
            glowClass = 'hover:shadow-glow-slate';
            break;
        case 'auditoria':
            glowClass = 'hover:shadow-glow-verde';
            break;
    }
    return `${baseClass} ${glowClass}`;
};

/**
 * Devuelve la clase de color de fondo para cada card
 */
const getCardBackgroundClass = (section) => {
    switch (section) {
        case 'catalogos':
            return 'bg-gradient-to-br from-blue-500 to-blue-700';
        case 'usuarios':
            return 'bg-gradient-to-br from-slate-500 to-slate-700';
        case 'cierres':
            return 'bg-gradient-to-br from-orange-500 to-orange-700';
        case 'auditoria':
            return 'bg-gradient-to-br from-emerald-500 to-emerald-700';
        default:
            return 'bg-gradient-to-br from-gray-500 to-gray-700';
    }
};
</script>

<style scoped>
/* Keyframes personalizados del DeclaracionesView */
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

/* Card floating effect mejorado */
.card-float {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.card-float:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

/* Sombras de "glow" específicas para cada card del AdministracionView */
.shadow-glow-blue {
    box-shadow: 0 0 25px 0 rgba(59, 130, 246, 0.4);
}

.hover\:shadow-glow-blue:hover {
    box-shadow: 0 0 35px 5px rgba(59, 130, 246, 0.5);
}

.shadow-glow-orange {
    box-shadow: 0 0 25px 0 rgba(251, 146, 60, 0.4);
}

.hover\:shadow-glow-orange:hover {
    box-shadow: 0 0 35px 5px rgba(251, 146, 60, 0.5);
}

.shadow-glow-slate {
    box-shadow: 0 0 25px 0 rgba(100, 116, 139, 0.4);
}

.hover\:shadow-glow-slate:hover {
    box-shadow: 0 0 35px 5px rgba(100, 116, 139, 0.5);
}

.shadow-glow-verde {
    box-shadow: 0 0 25px 0 rgba(16, 185, 129, 0.4);
}

.hover\:shadow-glow-verde:hover {
    box-shadow: 0 0 35px 5px rgba(16, 185, 129, 0.5);
}

/* Controladores de tamaño consistentes */
.group {
    min-height: 160px;
    max-height: 160px;
    height: 160px;
}

.group>div {
    height: 100%;
    width: 100%;
}

.group h3 {
    min-height: 2rem;
    max-height: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    letter-spacing: normal !important;
    padding: 0 0.5rem;
    line-height: 1.25;
}

.group p {
    min-height: 1.5rem;
    max-height: 1.5rem;
    height: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 0 0.5rem;
    line-height: 1.25;
}

/* Asegurar que el ícono no cause expansión */
.group .w-12.h-12 {
    flex-shrink: 0;
    width: 3rem !important;
    height: 3rem !important;
    min-width: 3rem;
    min-height: 3rem;
    max-width: 3rem;
    max-height: 3rem;
}

/* Controlar el grid para evitar expansiones */
.grid {
    align-items: start;
}

.grid>* {
    align-self: stretch;
}

/* Para texto largo que pueda causar problemas */
.group h3,
.group p {
    word-break: break-word;
    hyphens: auto;
    text-align: center;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .group h3 {
        font-size: 0.875rem;
    }
}

@media (min-width: 1024px) {
    .group {
        min-height: 170px;
        max-height: 170px;
        height: 170px;
    }

    .group h3 {
        min-height: 2.5rem;
        max-height: 2.5rem;
        height: 2.5rem;
    }
}
</style>