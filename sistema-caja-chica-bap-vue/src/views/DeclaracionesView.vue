<template>
  <div class="min-h-screen bg-verde-bap-extralight p-6">
    <div class="max-w-7xl mx-auto space-y-8">

      <div class="bg-white rounded-xl shadow-lg p-8 mb-8 animate-fade-in-down">
        <div class="text-center">
          <h1 class="text-3xl md:text-4xl font-bold mb-4 tracking-tight text-gris-bap-dark text-shadow">
            MÓDULO DE DECLARACIÓN DE GASTOS
          </h1>
          <p class="text-gris-bap max-w-4xl mx-auto text-base leading-relaxed">
            ¡Bienvenido! En este espacio podrás registrar toda la información relacionada con los montos utilizados,
            adjuntar los comprobantes o justificantes correspondientes y detallar cada gasto realizado. Este proceso
            garantiza la transparencia y el adecuado control de los fondos asignados.
          </p>
        </div>
      </div>

      <!--La visibilidad de las cards ahora se basa en permisos -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mt-12">
        <template v-for="(card, index) in visibleCards" :key="card.tab">
          <div @click="cambiarTab(card.tab)"
            class="group rounded-3xl cursor-pointer transition-all duration-300 ease-out shadow-soft hover:shadow-strong focus:outline-none animate-fade-in-up"
            :class="getCardClasses(card.tab)" :style="{ animationDelay: `${index * 0.1}s` }" tabindex="0">
            <div class="relative overflow-hidden rounded-3xl h-full transition-all duration-500 ease-out">
              <div class="absolute inset-0" :class="card.gradient"></div>
              <div
                class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000 ease-out">
              </div>
              <div class="relative z-10 p-4 text-center h-full flex flex-col justify-center" :class="card.textColor">
                <div class="mb-2 flex justify-center">
                  <div
                    class="w-12 h-12 backdrop-blur-sm rounded-2xl flex items-center justify-center transition-all duration-500 group-hover:scale-110 group-hover:rotate-12 shadow-medium"
                    :class="card.iconBg">
                    <svg class="w-6 h-6" :class="card.iconColor" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                      v-html="card.iconPath"></svg>
                  </div>
                </div>
                <h3 class="text-base md:text-lg font-bold mb-2 transition-all duration-300 leading-tight"
                  v-html="card.title"></h3>
                <p class="text-xs transition-all duration-300 leading-tight" :class="card.subtitleColor"
                  v-html="card.subtitle"></p>
              </div>
            </div>
          </div>
        </template>
      </div>

      <!-- Contenedor del Componente Activo -->
      <div v-if="activeTab" class="mt-12">
        <div class="bg-white/80 backdrop-blur-xl border border-white/20 rounded-3xl p-4 sm:p-8 shadow-strong">
          <component :is="activeComponent" :usuarioActual="props.user" @close="activeTab = ''" />
        </div>
      </div>

      <Transition enter-active-class="transition-all duration-600 ease-out delay-500"
        enter-from-class="opacity-0 transform translate-y-4" enter-to-class="opacity-100 transform translate-y-0">
        <div v-if="!activeTab"
          class="relative overflow-hidden rounded-2xl bg-verde-bap-extralight border-l-4 border-verde-bap p-6 shadow-soft">
          <div class="relative flex items-start space-x-4">
            <div class="flex-shrink-0">
              <div class="w-10 h-10 bg-verde-bap-light rounded-full flex items-center justify-center">
                <svg class="h-6 w-6 text-verde-bap-dark" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                    clip-rule="evenodd" />
                </svg>
              </div>
            </div>
            <div class="flex-1">
              <p class="text-gray-700 text-base leading-relaxed">
                <strong class="font-semibold">Nota importante:</strong> Los gastos deben rendirse hasta el día 30 de
                cada mes. Todo gasto efectuado debe contar con la documentación correspondiente que lo sustente
                (comprobante de pago o declaración jurada).
              </p>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, shallowRef, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import DeclaracionGasto from '@/components/declaraciones/DeclaracionGasto.vue';
import SeguimientoGastos from '@/components/declaraciones/SeguimientoGastos.vue';
import BandejaAprobacionArea from '@/components/declaraciones/BandejaAprobacionArea.vue';
import BandejaValidacionContable from '@/components/declaraciones/BandejaValidacionContable.vue';
import ReporteGastos from '@/components/declaraciones/ReporteGastos.vue';

// --- PROPS ---
// Se recibe el objeto 'user' desde el componente padre (MainLayout.vue)
const props = defineProps({
  user: {
    type: Object,
    default: () => null
  }
});
// --- ESTADO ---
const activeTab = ref('');
const activeComponent = shallowRef(null);
const route = useRoute();
// --- LÓGICA DE PERMISOS ---
const hasPermission = (permission) => {
  if (!props.user?.role?.permissions) {
    return false;
  }
  // Si 'permission' es un array, verificamos si el usuario tiene AL MENOS UNO de los permisos.
  if (Array.isArray(permission)) {
    return permission.some(p => props.user.role.permissions.some(userPerm => userPerm.name === p));
  }
  // Si 'permission' es un string (comportamiento original), se mantiene la misma lógica.
  return props.user.role.permissions.some(p => p.name === permission);
};

// --- CONFIGURACIÓN ESTÁTICA DE CARDS ---
const ALL_CARDS = [
  {
    tab: 'declaracion',
    title: 'REGISTRAR GASTO',
    subtitle: 'Crear una nueva declaración',
    permission: 'declaraciones.create',
    gradient: 'bg-gradient-to-br from-verde-bap to-verde-bap-dark',
    textColor: 'text-white',
    subtitleColor: 'text-white/90 group-hover:text-white',
    iconBg: 'bg-white/25 group-hover:bg-white/35',
    iconColor: 'text-white',
    iconPath: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>',
  },
  {
    tab: 'seguimiento',
    title: 'MIS GASTOS',
    subtitle: 'Seguimiento y correcciones',
    permission: 'declaraciones.create',
    gradient: 'bg-gradient-to-br from-blue-500 to-blue-700',
    textColor: 'text-white',
    subtitleColor: 'text-white/90 group-hover:text-white',
    iconBg: 'bg-white/25 group-hover:bg-white/35',
    iconColor: 'text-white',
    iconPath: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>',
  },
  {
    tab: 'aprobacionArea',
    title: 'BANDEJA DE APROBACIÓN',
    subtitle: 'Gastos de tu equipo',
    permission: 'declaraciones.approve.jefe',
    gradient: 'bg-gradient-to-br from-amarillo-bap to-yellow-600',
    textColor: 'text-gray-800',
    subtitleColor: 'text-white/90 group-hover:text-white',
    iconBg: 'bg-white/40 group-hover:bg-white/50',
    iconColor: 'text-amarillo-bap-dark',
    iconPath: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
  },
  {
    tab: 'validacionContable',
    title: 'BANDEJA DE VALIDACIÓN CONTABLE',
    subtitle: 'Auditoría y contabilización',
    permission: ['declaraciones.approve.adm', 'declaraciones.view.all'],
    gradient: 'bg-gradient-to-br from-rojo-bap to-rojo-bap-dark',
    textColor: 'text-white',
    subtitleColor: 'text-white/90 group-hover:text-white',
    iconBg: 'bg-white/10 group-hover:bg-white/20',
    iconColor: 'text-white',
    iconPath: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4M4 7s0 4 8 4s8-4 8-4"></path><line x1="4" y1="12" x2="4" y2="12"></line><line x1="4" y1="17" x2="4" y2="17"></line><line x1="20" y1="12" x2="20" y2="12"></line>',
  },
  {
    tab: 'reportes',
    title: 'REPORTE DE GASTOS',
    subtitle: 'Generar reportes para SAP',
    permission: ['declaraciones.view.reports', 'declaraciones.view.all'], 
    gradient: 'bg-gradient-to-br from-purple-500 to-purple-700', 
    textColor: 'text-white',
    subtitleColor: 'text-white/90 group-hover:text-white',
    iconBg: 'bg-white/25 group-hover:bg-white/35',
    iconColor: 'text-white',
    iconPath: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-5m3 5v-5m3 5v-5m-9 12h10a2 2 0 002-2V7a2 2 0 00-2-2H9a2 2 0 00-2 2v10a2 2 0 002 2z"></path>', // Icono de gráfico/reporte
  }
];

// --- FUNCIÓN HELPER PARA VERIFICAR PERMISOS ---
const hasCardPermission = (cardPermission) => {
  if (Array.isArray(cardPermission)) {
    // Si es un array, el usuario necesita al menos UNO de esos permisos
    return cardPermission.some(permission => hasPermission(permission));
  }
  // Si es una cadena, el usuario necesita ese permiso específico
  return hasPermission(cardPermission);
};

// --- CARDS VISIBLES (ÚNICA REACTIVIDAD) ---
const visibleCards = computed(() => {
  return ALL_CARDS.filter(card => hasCardPermission(card.permission));
});

// --- MÉTODOS ---
const cambiarTab = (tab) => {
  if (activeTab.value === tab) {
    activeTab.value = '';
    activeComponent.value = null;
  } else {
    activeTab.value = tab;
    switch (tab) {
      case 'declaracion':
        activeComponent.value = DeclaracionGasto;
        break;
      case 'seguimiento':
        activeComponent.value = SeguimientoGastos;
        break;
      case 'aprobacionArea':
        activeComponent.value = BandejaAprobacionArea;
        break;
      case 'validacionContable':
        activeComponent.value = BandejaValidacionContable;
        break;
      case 'reportes':
        activeComponent.value = ReporteGastos;
        break;
      default:
        activeComponent.value = null;
    }
  }
};

const getCardClasses = (tab) => {
  const baseClass = activeTab.value === tab ? 'ring-4 ring-white/50' : '';
  let glowClass = '';

  switch (tab) {
    case 'declaracion':
      glowClass = 'hover:shadow-glow-verde';
      break;
    case 'seguimiento':
      glowClass = 'hover:shadow-glow-blue';
      break;
    case 'aprobacionArea':
      glowClass = 'hover:shadow-glow-amarillo';
      break;
    case 'validacionContable':
      glowClass = 'hover:shadow-glow-rojo';
      break;
    case 'reportes': 
      glowClass = 'hover:shadow-glow-purple';
      break;
  }
  return `${baseClass} ${glowClass}`;
};
onMounted(() => {
  const tabFromUrl = route.query.tab;
  if (tabFromUrl) {
    const cardTarget = visibleCards.value.find(card => card.tab === tabFromUrl || (tabFromUrl === 'aprobaciones' && card.tab === 'validacionContable'));
    if (cardTarget) {
      cambiarTab(cardTarget.tab);
    }
  }
});
</script>

<style scoped>
/* Estilos para el efecto de fade-in y slide de la sección de componentes */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.4s ease, transform 0.4s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(20px);
}
.group {
  /* Altura mínima fija para todas las cards */
  min-height: 140px;
  max-height: 140px;
  height: 140px;
}

/* Evitar que el contenedor interno cambie de tamaño */
.group>div {
  height: 100%;
  width: 100%;
}

/*  Controlar el texto para evitar cambios de línea */
.group h3 {
  /* Altura fija para el título */
  min-height: 2.5rem;
  max-height: 2.5rem;
  height: 2.5rem;
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

/* Solo permitir efectos que no cambien el layout */
.group:hover h3 {
  /* Remover el tracking-wide que causa expansión */
  letter-spacing: normal !important;
}
/* Mantener los efectos visuales sin cambiar dimensiones */
.group:hover {
  /* Solo cambiar sombra y efectos visuales, NO transform scale */
  transform: translateY(-2px);
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
/* Estilos para las sombras de "glow" adaptadas a los colores BAP */
.shadow-glow-verde {
  box-shadow: 0 0 25px 0 rgba(118, 196, 157, 0.4);
}

.hover\:shadow-glow-verde:hover {
  box-shadow: 0 0 35px 5px rgba(118, 196, 157, 0.5);
}

.shadow-glow-amarillo {
  box-shadow: 0 0 25px 0 rgba(251, 191, 36, 0.4);
}

.hover\:shadow-glow-amarillo:hover {
  box-shadow: 0 0 35px 5px rgba(251, 191, 36, 0.5);
}

.shadow-glow-rojo {
  box-shadow: 0 0 25px 0 rgba(239, 68, 68, 0.4);
}

.hover\:shadow-glow-rojo:hover {
  box-shadow: 0 0 35px 5px rgba(239, 68, 68, 0.5);
}

.shadow-glow-blue {
  box-shadow: 0 0 25px 0 rgba(59, 130, 246, 0.4);
}

.hover\:shadow-glow-blue:hover {
  box-shadow: 0 0 35px 5px rgba(59, 130, 246, 0.5);
}

.shadow-glow-purple {
  box-shadow: 0 0 25px 0 rgba(168, 85, 247, 0.4);
}

.hover\:shadow-glow-purple:hover {
  box-shadow: 0 0 35px 5px rgba(168, 85, 247, 0.5);
}
.group h3,
.group p {
  word-break: break-word;
  hyphens: auto;
  text-align: center;
}

@media (max-width: 768px) {
  .group h3 {
    font-size: 0.875rem;
  }
}

/* Asegurar que en desktop también se mantenga el tamaño */
@media (min-width: 1024px) {
  .group {
    min-height: 150px;
    max-height: 150px;
    height: 150px;
  }

  .group h3 {
    min-height: 3rem;
    max-height: 3rem;
    height: 3rem;
  }
}
</style>
