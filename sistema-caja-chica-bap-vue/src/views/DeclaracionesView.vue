<template>
  <div class="min-h-screen bg-verde-bap-extralight p-6">
    <div class="max-w-7xl mx-auto space-y-8">

      <div class="bg-white rounded-xl shadow-lg p-8 mb-8 animate-fade-in-down">
        <div class="text-center">
          <h1 class="text-3xl md:text-4xl font-bold mb-4 tracking-tight text-gray-700 text-shadow">
            MÓDULO DE DECLARACIÓN DE GASTOS
          </h1>
          <p class="text-gray-600 max-w-4xl mx-auto text-base leading-relaxed">
            ¡Bienvenido! En este espacio podrás registrar toda la información relacionada con los montos utilizados,
            adjuntar los comprobantes o justificantes correspondientes y detallar cada gasto realizado. Este proceso
            garantiza la transparencia y el adecuado control de los fondos asignados.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">

        <!-- Card 1: Declaración de Gasto -->
        <div v-if="showDeclaracionCard" @click="handleCardClick('declaracion')" class="group relative overflow-hidden rounded-3xl cursor-pointer
                     bg-gradient-to-br from-verde-bap to-verde-bap-dark
                     transition-all duration-500 ease-out
                     transform hover:scale-105 hover:-translate-y-3
                     shadow-soft hover:shadow-glow-verde
                     focus:outline-none focus:ring-4 focus:ring-verde-bap/30
                     animate-fade-in-up border-2 border-transparent
                     hover:border-verde-bap-light/50" :class="getCardClasses('declaracion')"
          style="animation-delay: 0.1s" tabindex="0" @keydown.enter="handleCardClick('declaracion')"
          @keydown.space.prevent="handleCardClick('declaracion')">
          <div
            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000 ease-out">
          </div>
          <div class="relative z-10 p-4 text-center text-white h-full flex flex-col justify-center">
            <div class="mb-2 flex justify-center">
              <div
                class="w-12 h-12 bg-white/25 backdrop-blur-sm rounded-2xl flex items-center justify-center group-hover:bg-white/35 transition-all duration-500 group-hover:scale-110 group-hover:rotate-12 shadow-medium">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                  </path>
                </svg>
              </div>
            </div>
            <h3 class="text-base md:text-lg font-bold mb-2 group-hover:tracking-wide transition-all duration-300">
              DECLARACIÓN DE GASTO</h3>
            <p class="text-xs text-white/90 group-hover:text-white transition-all duration-300">Registrar un nuevo gasto
            </p>
          </div>
        </div>

        <!-- Card 2: Aprobaciones -->
        <div v-if="showAprobacionesCard" @click="handleCardClick('aprobaciones')" class="group relative overflow-hidden rounded-3xl cursor-pointer
                     bg-gradient-to-br from-amarillo-bap to-amarillo-bap-dark
                     transition-all duration-500 ease-out
                     transform hover:scale-105 hover:-translate-y-3
                     shadow-soft hover:shadow-glow-amarillo
                     focus:outline-none focus:ring-4 focus:ring-amarillo-bap/30
                     animate-fade-in-up border-2 border-transparent
                     hover:border-amarillo-bap-light/50" :class="getCardClasses('aprobaciones')"
          style="animation-delay: 0.2s" tabindex="0" @keydown.enter="handleCardClick('aprobaciones')"
          @keydown.space.prevent="handleCardClick('aprobaciones')">
          <div
            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000 ease-out">
          </div>
          <div class="relative z-10 p-4 text-center text-gray-800 h-full flex flex-col justify-center">
            <div class="mb-2 flex justify-center">
              <div
                class="w-12 h-12 bg-white/40 backdrop-blur-sm rounded-2xl flex items-center justify-center group-hover:bg-white/50 transition-all duration-500 group-hover:scale-110 group-hover:-rotate-12 shadow-medium">
                <svg class="w-6 h-6 text-amarillo-bap-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
            </div>
            <h3 class="text-base md:text-lg font-bold mb-2 group-hover:text-gray-900 transition-all duration-300">
              APROBACIONES DE GASTOS</h3>
            <p class="text-xs text-gray-700 group-hover:text-gray-800 transition-all duration-300">Validar gastos de tu
              equipo</p>
          </div>
        </div>

        <!-- Card 3: Auditoría y Reportes -->
        <div v-if="showAuditoriaCard" @click="handleCardClick('auditoria')" class="group relative overflow-hidden rounded-3xl cursor-pointer
                     bg-gradient-to-br from-rojo-bap to-rojo-bap-dark
                     transition-all duration-500 ease-out
                     transform hover:scale-105 hover:-translate-y-3
                     shadow-soft hover:shadow-glow-rojo
                     focus:outline-none focus:ring-4 focus:ring-rojo-bap/30
                     animate-fade-in-up border-2 border-transparent
                     hover:border-rojo-bap-light/50" :class="getCardClasses('auditoria')" style="animation-delay: 0.3s"
          tabindex="0" @keydown.enter="handleCardClick('auditoria')"
          @keydown.space.prevent="handleCardClick('auditoria')">
          <div
            class="absolute inset-0 bg-gradient-to-r from-transparent via-white/10 to-transparent transform -skew-x-12 -translate-x-full group-hover:translate-x-full transition-transform duration-1000 ease-out">
          </div>
          <div class="relative z-10 p-4 text-center text-white h-full flex flex-col justify-center">
            <div class="mb-2 flex justify-center">
              <div
                class="w-12 h-12 bg-white/10 backdrop-blur-sm rounded-2xl flex items-center justify-center group-hover:bg-white/20 transition-all duration-500 group-hover:scale-110 group-hover:animate-bounce-gentle shadow-medium">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4M4 7s0 4 8 4s8-4 8-4">
                  </path>
                  <line x1="4" y1="12" x2="4" y2="12"></line>
                  <line x1="4" y1="17" x2="4" y2="17"></line>
                  <line x1="20" y1="12" x2="20" y2="12"></line>
                </svg>
              </div>
            </div>
            <h3 class="text-base md:text-lg font-bold mb-2 group-hover:tracking-wide transition-all duration-300">
              AUDITORÍA Y REPORTES</h3>
            <p class="text-xs text-white/90 group-hover:text-white transition-all duration-300">Revisar gastos y
              exportar a SAP</p>
          </div>
        </div>

      </div>

      <!-- SECCIÓN DE COMPONENTE ACTIVO (AJUSTADA PARA SOLUCIONAR EL ERROR) -->
      <Transition name="fade-slide" mode="out-in">
        <div v-if="activeComponent" class="mt-12">
          <div class="bg-white/80 backdrop-blur-xl border border-white/20 rounded-3xl p-8 shadow-strong">
            <!-- Se utiliza <component :is> para renderizar el componente activo -->
            <!-- Se pasa el usuario como prop para que los hijos tengan el contexto necesario -->
            <component :is="activeComponent" :usuarioActual="user" @close="handleClose" />
          </div>
        </div>
      </Transition>

      <Transition enter-active-class="transition-all duration-600 ease-out delay-500"
        enter-from-class="opacity-0 transform translate-y-4" enter-to-class="opacity-100 transform translate-y-0">
        <div v-if="!activeSection"
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
import { ref, onMounted, computed } from 'vue';
import api from '@/plugins/axios';

// Importar los componentes de los submódulos
import DeclaracionGasto from '@/components/declaraciones/DeclaracionGasto.vue';
import AprobacionGastos from '@/components/declaraciones/AprobacionGastos.vue';
import AuditoriaGastos from '@/components/declaraciones/AuditoriaGastos.vue';

const activeSection = ref(null);
const user = ref(null);

const ROLES = {
  JEFE_AREA: 'jefe_area',
  JEFE_ADM: 'jefe_administracion',
  GERENTE_GENERAL: 'gerente_general',
  SUPER_ADMIN: 'super_admin',
  COLABORADOR: 'colaborador'
};

// --- CAMBIO PRINCIPAL: Se usa un computed para determinar qué componente mostrar ---
const activeComponent = computed(() => {
  if (!activeSection.value) return null;
  switch (activeSection.value) {
    case 'declaracion': return DeclaracionGasto;
    case 'aprobaciones': return AprobacionGastos;
    case 'auditoria': return AuditoriaGastos;
    default: return null;
  }
});


// Lógica de visibilidad de las tarjetas basada en roles
const showDeclaracionCard = computed(() => {
  return user.value;
});

const showAprobacionesCard = computed(() => {
  return user.value && [ROLES.JEFE_AREA, ROLES.JEFE_ADM, ROLES.SUPER_ADMIN].includes(user.value.role.name);
});

const showAuditoriaCard = computed(() => {
  return user.value && [ROLES.JEFE_ADM, ROLES.GERENTE_GENERAL, ROLES.SUPER_ADMIN].includes(user.value.role.name);
});

// Métodos para manejar la interacción
const handleCardClick = (section) => {
  activeSection.value = activeSection.value === section ? null : section;
};

const handleClose = () => {
  activeSection.value = null;
};

const getCardClasses = (section) => {
  const classes = [];
  if (activeSection.value === section) {
    classes.push('scale-105', '-translate-y-3');
    switch (section) {
      case 'declaracion':
        classes.push('ring-4', 'ring-verde-bap/50', 'shadow-glow-verde');
        break;
      case 'aprobaciones':
        classes.push('ring-4', 'ring-amarillo-bap/50', 'shadow-glow-amarillo');
        break;
      case 'auditoria':
        classes.push('ring-4', 'ring-rojo-bap/50', 'shadow-glow-rojo');
        break;
    }
  }
  return classes;
};

// Obtener datos del usuario al montar el componente
onMounted(async () => {
  try {
    const response = await api.get('/user');
    user.value = response.data;
  } catch (error) {
    console.error('Error al obtener datos del usuario:', error);
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
</style>
