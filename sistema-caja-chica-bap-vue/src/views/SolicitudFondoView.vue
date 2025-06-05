<template>
  <div class="min-h-screen bg-verde-bap-extralight p-6">
    <div class="max-w-7xl mx-auto space-y-8">

      <div class="bg-verde-bap-extralight rounded-xl shadow-lg p-8 mb-8 animate-fade-in-down">
        <div class="text-center">
          <h1 class="text-3xl md:text-4xl font-bold mb-4 tracking-tight text-gray-700 text-shadow">
            MÓDULO DE SOLICITUDES
          </h1>
          <p class="text-gray-600 max-w-4xl mx-auto text-base leading-relaxed">
            ¡Bienvenido al Módulo de Gestión de Solicitudes de Caja Chica! A través de esta plataforma
            podrás realizar de manera ágil y segura tus solicitudes de apertura y modificaciones de caja
            chica. Además, tendrás la posibilidad de consultar el estado de cada solicitud en tiempo real,
            permitiéndote hacer un seguimiento oportuno y mantener un control eficiente de tus gestiones.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mt-12">

        <div
          v-if="showAperturaCard"
          @click="handleCardClick('apertura')"
          @mouseenter="handleMouseEnter"
          @mouseleave="handleMouseLeave"
          class="group relative overflow-hidden rounded-3xl cursor-pointer
                 bg-gradient-to-br from-verde-bap to-verde-bap-dark
                 transition-all duration-500 ease-out
                 transform hover:scale-105 hover:-translate-y-3
                 shadow-soft hover:shadow-glow-verde
                 focus:outline-none focus:ring-4 focus:ring-verde-bap/30
                 animate-fade-in-up border-2 border-transparent
                 hover:border-verde-bap-light/50"
          :class="getCardClasses('apertura')"
          style="animation-delay: 0.1s"
          tabindex="0"
          @keydown.enter="handleCardClick('apertura')"
          @keydown.space.prevent="handleCardClick('apertura')"
        >
          <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent
                       transform -skew-x-12 -translate-x-full
                       group-hover:translate-x-full transition-transform duration-1000 ease-out"></div>

          <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 left-0 w-32 h-32 bg-white rounded-full
                         transform -translate-x-16 -translate-y-16 group-hover:scale-150
                         transition-transform duration-700"></div>
            <div class="absolute bottom-0 right-0 w-24 h-24 bg-white rounded-full
                         transform translate-x-12 translate-y-12 group-hover:scale-125
                         transition-transform duration-500 delay-200"></div>
          </div>

          <div class="relative z-10 p-4 text-center text-white h-full flex flex-col justify-center">
            <div class="mb-2 flex justify-center">
              <div class="relative">
                <div class="w-12 h-12 bg-white/25 backdrop-blur-sm rounded-2xl flex items-center justify-center
                             group-hover:bg-white/35 transition-all duration-500
                             group-hover:scale-110 group-hover:rotate-12 shadow-medium">
                  <svg class="w-6 h-6 text-white group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                  </svg>
                </div>
                <div class="absolute inset-0 rounded-2xl border-2 border-white/30
                             group-hover:animate-ping"></div>
              </div>
            </div>

            <h3 class="text-base md:text-lg font-bold mb-2 group-hover:text-white/95 transition-all duration-300
                       group-hover:tracking-wide">
              APERTURA DE FONDOS
            </h3>

            <p class="text-xs text-white/90 group-hover:text-white transition-all duration-300">
              Crear nuevos fondos de caja chica
            </p>

            <div class="mt-2 flex justify-center space-x-2">
              <div class="w-3 h-3 bg-white/60 rounded-full group-hover:bg-white
                           group-hover:scale-150 transition-all duration-300"
                   :class="{ 'bg-white scale-150 animate-pulse-soft': activeSection === 'apertura' }"></div>
              <div class="w-2 h-2 bg-white/40 rounded-full group-hover:bg-white/80
                           group-hover:scale-125 transition-all duration-300 delay-75"></div>
              <div class="w-1 h-1 bg-white/30 rounded-full group-hover:bg-white/60
                           group-hover:scale-110 transition-all duration-300 delay-150"></div>
            </div>
          </div>

          <div class="absolute inset-0 rounded-3xl opacity-0 group-hover:opacity-100
                       transition-opacity duration-500 pointer-events-none
                       bg-gradient-to-t from-verde-bap-light/20 to-transparent"></div>
        </div>

        <div
          v-if="showModificacionCard"
          @click="handleCardClick('modificacion')"
          @mouseenter="handleMouseEnter"
          @mouseleave="handleMouseLeave"
          class="group relative overflow-hidden rounded-3xl cursor-pointer
                 bg-gradient-to-br from-amarillo-bap to-amarillo-bap-dark
                 transition-all duration-500 ease-out
                 transform hover:scale-105 hover:-translate-y-3
                 shadow-soft hover:shadow-glow-amarillo
                 focus:outline-none focus:ring-4 focus:ring-amarillo-bap/30
                 animate-fade-in-up border-2 border-transparent
                 hover:border-amarillo-bap-light/50"
          :class="getCardClasses('modificacion')"
          style="animation-delay: 0.2s"
          tabindex="0"
          @keydown.enter="handleCardClick('modificacion')"
          @keydown.space.prevent="handleCardClick('modificacion')"
        >
          <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/25 to-transparent
                       transform -skew-x-12 -translate-x-full
                       group-hover:translate-x-full transition-transform duration-1000 ease-out"></div>

          <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 right-0 w-28 h-28 bg-white rounded-full
                         transform translate-x-14 -translate-y-14 group-hover:scale-150
                         transition-transform duration-600"></div>
            <div class="absolute bottom-0 left-0 w-20 h-20 bg-white rounded-full
                         transform -translate-x-10 translate-y-10 group-hover:scale-125
                         transition-transform duration-400 delay-300"></div>
          </div>

          <div class="relative z-10 p-4 text-center text-gray-800 h-full flex flex-col justify-center">
            <div class="mb-2 flex justify-center">
              <div class="relative">
                <div class="w-12 h-12 bg-white/40 backdrop-blur-sm rounded-2xl flex items-center justify-center
                             group-hover:bg-white/50 transition-all duration-500
                             group-hover:scale-110 group-hover:-rotate-12 shadow-medium">
                  <svg class="w-6 h-6 text-amarillo-bap-dark group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </div>
                <div class="absolute inset-0 rounded-2xl border-2 border-gray-800/20
                             group-hover:animate-ping"></div>
              </div>
            </div>

            <h3 class="text-base md:text-lg font-bold mb-2 group-hover:text-gray-900 transition-all duration-300
                       group-hover:tracking-wide">
              MODIFICACIÓN DE FONDOS
            </h3>

            <p class="text-xs text-gray-700 group-hover:text-gray-800 transition-all duration-300">
              Incrementar o modificar fondos existentes
            </p>

            <div class="mt-2 flex justify-center space-x-2">
              <div class="w-3 h-3 bg-gray-600/60 rounded-full group-hover:bg-gray-800
                           group-hover:scale-150 transition-all duration-300"
                   :class="{ 'bg-gray-800 scale-150 animate-pulse-soft': activeSection === 'modificacion' }"></div>
              <div class="w-2 h-2 bg-gray-500/40 rounded-full group-hover:bg-gray-700
                           group-hover:scale-125 transition-all duration-300 delay-75"></div>
              <div class="w-1 h-1 bg-gray-400/30 rounded-full group-hover:bg-gray-600
                           group-hover:scale-110 transition-all duration-300 delay-150"></div>
            </div>
          </div>
        </div>

        <div
          v-if="showSeguimientoCard"
          @click="handleCardClick('seguimiento')"
          @mouseenter="handleMouseEnter"
          @mouseleave="handleMouseLeave"
          class="group relative overflow-hidden rounded-3xl cursor-pointer
                 bg-gradient-to-br from-rojo-bap to-rojo-bap-dark
                 transition-all duration-500 ease-out
                 transform hover:scale-105 hover:-translate-y-3
                 shadow-soft hover:shadow-glow-rojo
                 focus:outline-none focus:ring-4 focus:ring-rojo-bap/30
                 animate-fade-in-up border-2 border-transparent
                 hover:border-rojo-bap-light/50"
          :class="getCardClasses('seguimiento')"
          style="animation-delay: 0.3s"
          tabindex="0"
          @keydown.enter="handleCardClick('seguimiento')"
          @keydown.space.prevent="handleCardClick('seguimiento')"
        >
          <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent
                       transform -skew-x-12 -translate-x-full
                       group-hover:translate-x-full transition-transform duration-1000 ease-out"></div>

          <div class="absolute inset-0 opacity-10">
            <div class="absolute top-1/2 left-1/2 w-36 h-36 bg-white rounded-full
                         transform -translate-x-1/2 -translate-y-1/2 group-hover:scale-125
                         transition-transform duration-800"></div>
          </div>

          <div class="relative z-10 p-4 text-center text-white h-full flex flex-col justify-center">
            <div class="mb-2 flex justify-center">
              <div class="relative">
                <div class="w-12 h-12 bg-white/25 backdrop-blur-sm rounded-2xl flex items-center justify-center
                             group-hover:bg-white/35 transition-all duration-500
                             group-hover:scale-110 group-hover:animate-bounce-gentle shadow-medium">
                  <svg class="w-6 h-6 text-white group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                  </svg>
                </div>
                <div class="absolute inset-0 rounded-2xl border-2 border-white/30
                             group-hover:animate-ping"></div>
              </div>
            </div>

            <h3 class="text-base md:text-lg font-bold mb-2 group-hover:text-white/95 transition-all duration-300
                       group-hover:tracking-wide">
              SEGUIMIENTO SOLICITUDES
            </h3>

            <p class="text-xs text-white/90 group-hover:text-white transition-all duration-300">
              Consultar estado de solicitudes en tiempo real
            </p>

            <div class="mt-2 flex justify-center space-x-2">
              <div class="w-3 h-3 bg-white/60 rounded-full group-hover:bg-white
                           group-hover:scale-150 transition-all duration-300"
                   :class="{ 'bg-white scale-150 animate-pulse-soft': activeSection === 'seguimiento' }"></div>
              <div class="w-2 h-2 bg-white/40 rounded-full group-hover:bg-white/80
                           group-hover:scale-125 transition-all duration-300 delay-75"></div>
              <div class="w-1 h-1 bg-white/30 rounded-full group-hover:bg-white/60
                           group-hover:scale-110 transition-all duration-300 delay-150"></div>
            </div>
          </div>
        </div>
      </div>

      <Transition
        enter-active-class="transition-all duration-500 ease-out"
        enter-from-class="opacity-0 transform translate-y-8 scale-95"
        enter-to-class="opacity-100 transform translate-y-0 scale-100"
        leave-active-class="transition-all duration-300 ease-in"
        leave-from-class="opacity-100 transform translate-y-0 scale-100"
        leave-to-class="opacity-0 transform -translate-y-4 scale-95"
      >
        <div v-if="activeSection" class="mt-12">
          <div class="glass rounded-3xl p-8 shadow-strong backdrop-blur-xl border border-white/20">
            <AperturaFondos
              v-if="activeSection === 'apertura'"
              @solicitudEnviada="handleSolicitudEnviada"
              class="animate-fade-in-up"
            />
            <ModificacionFondos
              v-if="activeSection === 'modificacion'"
              @close="handleClose"
              class="animate-fade-in-up"
            />
            <SeguimientoSolicitudes
              v-if="activeSection === 'seguimiento'"
              @close="handleClose"
              class="animate-fade-in-up"
            />
          </div>
        </div>
      </Transition>

      <Transition
        enter-active-class="transition-all duration-600 ease-out delay-500"
        enter-from-class="opacity-0 transform translate-y-4"
        enter-to-class="opacity-100 transform translate-y-0"
      >
        <div v-if="!activeSection"
              class="relative overflow-hidden rounded-2xl
                     bg-gradient-to-r from-verde-bap-extralight to-verde-bap-light
                     border-l-4 border-verde-bap p-6 shadow-soft">
          <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent
                       transform -skew-x-12 translate-x-full animate-shimmer"></div>

          <div class="relative flex items-start space-x-4">
            <div class="flex-shrink-0">
              <div class="w-10 h-10 bg-verde-bap-light rounded-full flex items-center justify-center">
                <svg class="h-6 w-6 text-verde-bap animate-bounce-gentle" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
              </div>
            </div>
            <div class="flex-1">
              <p class="text-gray-700 text-base leading-relaxed">
                <strong class="font-semibold">Nota importante:</strong> Solo los usuarios tipo "Jefatura de Área" en adelante podrán acceder al módulo de solicitudes.
                Las solicitudes serán aprobadas por la Jefatura de Administración y Gerencia General.
              </p>
            </div>
          </div>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick, onUnmounted, computed } from 'vue'
import api from '@/plugins/axios'

// Importar componentes
import AperturaFondos from '@/components/solicitudes/AperturaFondos.vue'
import ModificacionFondos from '@/components/solicitudes/ModificacionFondos.vue'
import SeguimientoSolicitudes from '@/components/solicitudes/SeguimientoSolicitudes.vue'

// Estado reactivo
const activeSection = ref(null)
const user = ref(null)
const isLoading = ref(false)
const cardAnimationState = ref({})

// Roles definidos para mayor claridad y evitar errores de escritura
const ROLES = {
  JEFE_AREA: 'jefe_area',
  JEFE_ADM: 'jefe_administracion',
  GERENTE_GENERAL: 'gerente_general',
  SUPER_ADMIN: 'super_admin',
  COLABORADOR: 'colaborador'
};

// Propiedades computadas para la visibilidad de las tarjetas
const showAperturaCard = computed(() => {
  return user.value && (user.value.role.name === ROLES.JEFE_AREA || user.value.role.name === ROLES.SUPER_ADMIN);
});

const showModificacionCard = computed(() => {
  // Ahora visible también para Jefe de Administración
  return user.value && (user.value.role.name === ROLES.JEFE_AREA || user.value.role.name === ROLES.JEFE_ADM || user.value.role.name === ROLES.SUPER_ADMIN);
});

const showSeguimientoCard = computed(() => {
  // Seguimiento es visible para todos los roles que acceden al módulo
  return user.value;
});


// Métodos mejorados
const handleCardClick = async (section) => {
  try {
    isLoading.value = true

    // Efecto de retroalimentación visual
    cardAnimationState.value[section] = 'clicked'

    // Cambiar sección activa
    activeSection.value = activeSection.value === section ? null : section

    // Scroll suave hacia el contenido si se abrió una sección
    if (activeSection.value) {
      await nextTick()
      const element = document.querySelector('.glass')
      if (element) {
        element.scrollIntoView({
          behavior: 'smooth',
          block: 'center'
        })
      }
    }

    // Limpiar estado de animación
    setTimeout(() => {
      cardAnimationState.value[section] = null
    }, 200)

  } catch (error) {
    console.error('Error al procesar click:', error)
  } finally {
    setTimeout(() => {
      isLoading.value = false
    }, 150)
  }
}

const handleMouseEnter = (event) => {
  // Efecto de sonido sutil (opcional)
  // new Audio('/sounds/hover.mp3').play().catch(() => {})
}

const handleMouseLeave = (event) => {
  // Limpiar cualquier estado temporal
}

const handleSolicitudEnviada = () => {
  activeSection.value = null
  // Mostrar notificación de éxito
  showSuccessNotification('Solicitud enviada correctamente')
}

const handleClose = () => {
  activeSection.value = null
}

const getCardClasses = (section) => {
  const baseClasses = []

  if (activeSection.value === section) {
    baseClasses.push('ring-4', 'scale-105', '-translate-y-3')

    // Clases específicas por sección
    switch (section) {
      case 'apertura':
        baseClasses.push('ring-verde-bap/50', 'shadow-glow-verde')
        break
      case 'modificacion':
        baseClasses.push('ring-amarillo-bap/50', 'shadow-glow-amarillo')
        break
      case 'seguimiento':
        baseClasses.push('ring-rojo-bap/50', 'shadow-glow-rojo')
        break
    }
  }

  if (cardAnimationState.value[section] === 'clicked') {
    baseClasses.push('animate-card-click')
  }

  return baseClasses
}

const showSuccessNotification = (message) => {
  // Implementar notificación toast
  console.log(message)
}

// Obtener datos del usuario al montar
onMounted(async () => {
  try {
    const response = await api.get('/user')
    user.value = response.data.user
  } catch (error) {
    console.error('Error al obtener datos del usuario:', error)
  }
})

// Manejo de teclado para accesibilidad
const handleKeyNavigation = (event) => {
  if (event.key === 'Escape' && activeSection.value) {
    activeSection.value = null
  }
}

// Agregar listener para ESC
onMounted(() => {
  document.addEventListener('keydown', handleKeyNavigation)
})

// Limpiar listener
onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyNavigation)
})
</script>

<style scoped>
/* Animaciones adicionales personalizadas */
@keyframes cardClick {
  0% { transform: scale(1) translateY(0); }
  50% { transform: scale(0.98) translateY(2px); }
  100% { transform: scale(1) translateY(0); }
}

.animate-card-click {
  animation: cardClick 0.15s ease-in-out;
}

/* Mejora del scroll personalizado */
.scroll-smooth-custom {
  scroll-behavior: smooth;
}

.scroll-smooth-custom::-webkit-scrollbar {
  width: 8px;
}

.scroll-smooth-custom::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.1);
  border-radius: 10px;
}

.scroll-smooth-custom::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #76C49D, #5da887);
  border-radius: 10px;
}

.scroll-smooth-custom::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #5da887, #4a9975);
}

/* Efecto de cristal mejorado */
.glass {
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(20px) saturate(180%);
  border: 1px solid rgba(255, 255, 255, 0.3);
}

/* Estados de focus mejorados */
.group:focus-visible {
  outline: none;
}

/* Estilo para el texto con gradiente (se mantiene por si se usa en otros elementos) */
.text-gradient {
  background: linear-gradient(90deg, var(--tw-colors-verde-bap-dark), var(--tw-colors-amarillo-bap-dark), var(--tw-colors-rojo-bap-dark));
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
  color: transparent; /* Fallback para navegadores que no soportan background-clip */
  background-size: 200% auto;
  animation: pulse-gradient 4s infinite alternate;
}

/* Keyframes para el gradiente de texto (si no están en tailwind.config.js) */
@keyframes pulse-gradient {
  0% { background-position: 0% 50%; }
  100% { background-position: 100% 50%; }
}
</style>
