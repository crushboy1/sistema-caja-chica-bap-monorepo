<template>
  <!-- Contenedor principal con animación de entrada y padding general -->
  <div class="p-6 bg-white rounded-lg shadow-md animate-fade-in-up">
    <!-- Encabezado del Módulo -->
    <div class="text-center mb-8">
      <h2 class="text-3xl font-bold text-gray-800 mb-2">Aprobación de Gastos Pendientes</h2>
      <p class="text-gray-500 max-w-2xl mx-auto">
        Aquí se listan los gastos registrados por los miembros de tu área que requieren tu validación.
      </p>
    </div>

    <!-- Panel de Filtros (Estilo BaseCard) -->
    <div class="mb-8 p-4 bg-gray-50 rounded-lg shadow-inner">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <!-- Cada filtro sigue el patrón de BaseInput (label + input) -->
        <div>
          <label for="filtro_codigo_gasto" class="block text-sm font-medium text-gray-700 mb-1">Código de Gasto</label>
          <input type="text" id="filtro_codigo_gasto" v-model="filtros.codigo_gasto"
            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
            placeholder="Ej: GTO-00001">
        </div>
        <div>
          <label for="filtro_registrador" class="block text-sm font-medium text-gray-700 mb-1">Buscar por
            Registrador</label>
          <input type="text" id="filtro_registrador" v-model="filtros.registrador_name"
            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
            placeholder="Nombre o Apellido...">
        </div>
        <div>
          <label for="filtro_fecha_inicio" class="block text-sm font-medium text-gray-700 mb-1">Fecha Desde</label>
          <input type="date" id="filtro_fecha_inicio" v-model="filtros.fecha_inicio"
            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
        </div>
        <div>
          <label for="filtro_fecha_fin" class="block text-sm font-medium text-gray-700 mb-1">Fecha Hasta</label>
          <input type="date" id="filtro_fecha_fin" v-model="filtros.fecha_fin"
            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
        </div>
      </div>
      <div class="mt-4 flex justify-end">
        <!-- Botón Limpiar (Estilo BaseButton secundario) -->
        <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
          class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-5 rounded-full transition-colors shadow-lg flex items-center">
          <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
          </svg>
          Limpiar Filtros
        </button>
      </div>
    </div>

    <!-- Contenido Principal: Estados de Carga, Vacío y Tabla de Datos -->
    <div v-if="cargando" class="text-center text-gray-500 py-16">
      <div class="inline-flex items-center">
        <!-- Spinner consistente con el diseño -->
        <svg class="animate-spin -ml-1 mr-3 h-6 w-6 text-verde-bap" xmlns="http://www.w3.org/2000/svg" fill="none"
          viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
          </path>
        </svg>
        Cargando gastos pendientes...
      </div>
    </div>

    <div v-else-if="gastosPaginados.length === 0"
      class="text-center text-gray-500 py-16 px-6 bg-gray-50 rounded-lg shadow-inner">
      <svg class="mx-auto h-12 w-12 text-verde-bap" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="mt-2 text-xl font-medium text-gray-900">¡Todo al día!</h3>
      <p class="mt-1 text-md">
        {{ hayFiltrosActivos ? "No se encontraron gastos con los filtros aplicados." : "No tienes gastos pendientes de aprobación en este momento." }}
      </p>
    </div>

    <div v-else>
      <!-- Contador de registros y Tabla -->
      <div class="mb-4 text-sm text-gray-600 text-center">
        Mostrando <strong>{{ (paginaActual - 1) * registrosPorPagina + 1 }} - {{ Math.min(paginaActual *
          registrosPorPagina,
          gastos.length) }}</strong> de <strong>{{ gastos.length }}</strong> registros
      </div>
      <div class="overflow-x-auto shadow-lg rounded-lg">
        <table class="min-w-full bg-white border border-gray-200 rounded-lg">
          <thead class="bg-gray-100">
            <tr class="bg-gray-100 text-gray-700 uppercase text-sm leading-normal">
              <th scope="col" class="py-4 px-4 text-center font-semibold">Código Gasto</th>
              <th scope="col" class="py-4 px-4 text-center font-semibold">Monto</th>
              <th scope="col" class="py-4 px-4 text-center font-semibold">Estado</th>
              <th scope="col" class="py-4 px-4 text-center font-semibold">Registrador</th>
              <th scope="col" class="py-4 px-4 text-center font-semibold">Fecha Registro</th>
              <th scope="col" class="py-4 px-4 text-center font-semibold">Acciones</th>
            </tr>
          </thead>

          <tbody class="text-gray-600 text-sm">
            <tr v-for="gasto in gastosPaginados" :key="gasto.id" class="hover:bg-gray-50 transition-colors text-center">
              <td class="py-4 px-6 text-center font-medium whitespace-nowrap">{{ gasto.codigo_gasto }}</td>
              <td class="py-4 px-6 text-center font-medium whitespace-nowrap">
                {{ gasto.moneda === 'PEN' ? 'S/.' : 'USD' }} {{ parseFloat(gasto.monto_total).toFixed(2) }}
              </td>
              <td class="py-4 px-6 whitespace-normal">
                <span :class="getClassesForAuditoriaBadge(gasto.estado)">
                  {{ gasto.estado }}
                </span>
              </td>
              <td class="py-4 px-4 text-center">{{ gasto.registrador.name }} {{
                gasto.registrador.last_name }}</td>
              <td class="py-4 px-4 text-center text-gray-500">{{ new
                Date(gasto.created_at).toLocaleDateString('es-ES') }}</td>
              <td class="py-4 px-4 text-center">

                <div class="flex items-center justify-center space-x-2">
                  <!-- Botón Ver Detalles (siempre visible) -->
                  <button @click="verDetalles(gasto)"
                    class="w-9 h-9 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 flex items-center justify-center transition-all duration-300 hover:scale-110"
                    title="Ver Detalles y Evidencia">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                  </button>

                  <!-- Acciones para Jefe de Área -->
                  <div v-if="esJefeDeArea && gasto.estado === 'Pendiente de Aprobación'"
                    class="flex items-center space-x-2">
                    <button @click="gestionarAccion(gasto, 'approve')" title="Aprobar Gasto"
                      class="w-9 h-9 rounded-full bg-verde-bap-light hover:bg-verde-bap text-verde-bap-dark hover:text-white flex items-center justify-center transition-all duration-300 hover:scale-110">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                      </svg>
                    </button>
                    <button @click="gestionarAccion(gasto, 'observeByJefe')" title="Observar Gasto"
                      class="w-9 h-9 rounded-full bg-estado-advertencia-bg hover:bg-orange-500 text-estado-advertencia-text hover:text-white flex items-center justify-center transition-all duration-300 hover:scale-110">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                      </svg>
                    </button>
                    <button @click="gestionarAccion(gasto, 'rejectByJefe')" title="Rechazar Gasto"
                      class="w-9 h-9 rounded-full bg-rojo-bap-light hover:bg-rojo-bap text-rojo-bap-dark hover:text-white flex items-center justify-center transition-all duration-300 hover:scale-110">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>

                  <!-- Acciones para gestionar observaciones (Jefe o Colaborador) -->
                  <div v-if="gasto.estado === 'Observado'">
                    <button v-if="esJefeDeArea" @click="gestionarAccion(gasto, 'returnToCollaborator')"
                      title="Devolver con Directriz"
                      class="w-9 h-9 rounded-full bg-estado-info-bg hover:bg-blue-500 text-estado-info-text hover:text-white flex items-center justify-center transition-all duration-300 hover:scale-110">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                      </svg>
                    </button>
                    <button v-if="esColaborador" @click="gestionarAccion(gasto, 'resubmit')"
                      title="Corregir y Reenviar Gasto"
                      class="w-9 h-9 rounded-full bg-estado-info-bg hover:bg-blue-500 text-estado-info-text hover:text-white flex items-center justify-center transition-all duration-300 hover:scale-110">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                  </div>

                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-6">
        <div class="flex justify-center items-center space-x-1">
          <button @click="paginaAnterior" :disabled="paginaActual === 1"
            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center"
            :class="paginaActual === 1 ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Anterior
          </button>
          <button v-for="pagina in paginasVisibles" :key="pagina" @click="irAPagina(pagina)"
            class="w-10 h-10 rounded-lg text-sm font-medium transition-colors duration-200 border" :class="[
              paginaActual === pagina ? 'bg-verde-bap text-white border-verde-bap-dark shadow-md' : 'bg-white text-gray-700 hover:bg-gray-100 border-gray-200',
              pagina === '...' ? 'cursor-default' : ''
            ]">
            {{ pagina }}
          </button>
          <button @click="paginaSiguiente" :disabled="paginaActual === totalPaginas"
            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200 flex items-center"
            :class="paginaActual === totalPaginas ? 'bg-gray-100 text-gray-400 cursor-not-allowed' : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200'">
            Siguiente
            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
          </button>
        </div>
        <div v-if="totalPaginas > 0" class="text-center text-sm text-gray-500 mt-2">
          Página {{ paginaActual }} de {{ totalPaginas }}
        </div>
      </div>
    </div>

    <!-- Modales -->
    <GastoDetalleModal :mostrar="mostrarDetalleModal" :gasto="gastoSeleccionado" @close="mostrarDetalleModal = false" />

    <!-- CAMBIO: Se pasa la prop `usuarioActual` y se escucha el evento `accionRealizada` -->
    <GestionGastoModal :mostrar="mostrarGestionModal" :gasto="gastoParaGestionar" :usuarioActual="usuarioActual"
      @close="cerrarGestionModal" @accionRealizada="handleAccionRealizada" />

    <GestionObservacionModal :mostrar="mostrarObservacionModal" :gasto="gastoParaObservacion"
      :usuarioActual="usuarioActual" @close="cerrarObservacionModal" @accionRealizada="handleAccionRealizada" />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import GastoDetalleModal from './modals/GastoDetalleModal.vue';
import GestionGastoModal from './modals/GestionGastoModal.vue';
import GestionObservacionModal from './modals/GestionObservacionModal.vue';
import { getClassesForAuditoriaBadge } from '@/utils/statusStyles.js';
// --- ESTADO DEL COMPONENTE ---
const usuarioActual = ref(null);
const gastos = ref([]);
const cargando = ref(true);
const filtros = ref({
  registrador_name: '',
  fecha_inicio: '',
  fecha_fin: '',
  codigo_gasto: ''
});

// --- ESTADO DE PAGINACIÓN ---
const paginaActual = ref(1);
const registrosPorPagina = ref(10);

// --- ESTADO DE MODALES ---
const gastoSeleccionado = ref(null);
const mostrarDetalleModal = ref(false);
const gastoParaGestionar = ref(null);
const mostrarGestionModal = ref(false);
const gastoParaObservacion = ref(null);
const mostrarObservacionModal = ref(false);
// --- PROPIEDADES COMPUTADAS ---

const esJefeDeArea = computed(() => {
  return usuarioActual.value?.role?.name === 'jefe_area';
});
// Se crea una propiedad computada para verificar si el usuario es Colaborador.
const esColaborador = computed(() => {
  return usuarioActual.value?.role?.name === 'colaborador';
});
const hayFiltrosActivos = computed(() => {
  return filtros.value.registrador_name || filtros.value.fecha_inicio || filtros.value.fecha_fin || filtros.value.codigo_gasto;
});

const totalPaginas = computed(() => {
  return Math.ceil(gastos.value.length / registrosPorPagina.value);
});

const gastosPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value;
  return gastos.value.slice(inicio, inicio + registrosPorPagina.value);
});

const paginasVisibles = computed(() => {
  if (totalPaginas.value <= 7) {
    return Array.from({ length: totalPaginas.value }, (_, i) => i + 1);
  }
  if (paginaActual.value < 5) {
    return [1, 2, 3, 4, 5, '...', totalPaginas.value];
  }
  if (paginaActual.value > totalPaginas.value - 4) {
    return [1, '...', totalPaginas.value - 4, totalPaginas.value - 3, totalPaginas.value - 2, totalPaginas.value - 1, totalPaginas.value];
  }
  return [1, '...', paginaActual.value - 1, paginaActual.value, paginaActual.value + 1, '...', totalPaginas.value];
});

// --- MÉTODOS ---

// CAMBIO: Se obtiene el usuario logueado para pasarlo al modal de gestión.
const obtenerUsuarioActual = async () => {
  try {
    const response = await api.get('/user');
    usuarioActual.value = response.data;
  } catch (error) {
    console.error("Error al obtener el usuario actual:", error);
    Swal.fire('Error', 'No se pudo obtener la información del usuario.', 'error');
  }
};

let debounceTimeout = null;
const fetchGastos = async () => {
  cargando.value = true;
  try {
    const params = {
      ...filtros.value,
      scope: 'aprobaciones'
    };
    const response = await api.get('/gastos', { params });
    gastos.value = response.data;

    if (paginaActual.value > totalPaginas.value && totalPaginas.value > 0) {
      paginaActual.value = totalPaginas.value;
    } else if (totalPaginas.value === 0) {
      paginaActual.value = 1;
    }
  } catch (error) {
    console.error("Error al cargar gastos:", error);
    Swal.fire('Error', 'No se pudieron cargar los gastos pendientes.', 'error');
  } finally {
    cargando.value = false;
  }
};

const limpiarFiltros = () => {
  filtros.value = { registrador_name: '', fecha_inicio: '', fecha_fin: '', estado: 'Pendiente de Aprobación Jefatura', codigo_gasto: '' };
};

const verDetalles = (gasto) => {
  gastoSeleccionado.value = gasto;
  mostrarDetalleModal.value = true;
};

const gestionarAccion = async (gasto, accion) => {
  let config;

  // Configuración para cada tipo de acción
  switch (accion) {
    case 'approve':
      config = {
        title: 'Aprobar Gasto',
        text: `¿Estás seguro de que deseas aprobar el gasto con código ${gasto.codigo_gasto}?`,
        icon: 'success',
        confirmButtonText: 'Sí, ¡Aprobar!',
        endpoint: `/gastos/${gasto.id}/approve`,
        method: 'post',
        needsComment: false
      };
      break;
    case 'observeByJefe':
      config = {
        title: 'Observar Gasto',
        text: `Vas a devolver el gasto ${gasto.codigo_gasto} para su corrección.`,
        icon: 'warning',
        confirmButtonText: 'Sí, ¡Observar!',
        endpoint: `/gastos/${gasto.id}/observe-by-jefe`,
        method: 'post',
        needsComment: true,
        commentLabel: 'Motivo de la observación:'
      };
      break;
    case 'rejectByJefe':
      config = {
        title: 'Rechazar Gasto',
        text: `Esta acción es definitiva. ¿Estás seguro de que deseas rechazar el gasto ${gasto.codigo_gasto}?`,
        icon: 'error',
        confirmButtonText: 'Sí, ¡Rechazar!',
        endpoint: `/gastos/${gasto.id}/reject-by-jefe`,
        method: 'post',
        needsComment: true,
        commentLabel: 'Motivo del rechazo:'
      };
      break;
    case 'returnToCollaborator':
      config = {
        title: 'Añadir Directriz',
        // AJUSTE: Se añade el motivo de la observación de ADM al texto del modal
        html: `
                    <div class="text-left">
                        <p class="mb-2">Añade una instrucción clara para que el colaborador corrija el gasto <strong>${gasto.codigo_gasto}</strong>.</p>
                        <div class="p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="font-semibold text-red-800">Observación de Administración:</p>
                            <p class="text-red-700 italic">"${gasto.motivo_observacion_adm || 'No se especificó un motivo.'}"</p>
                        </div>
                    </div>
                `,
        icon: 'info',
        confirmButtonText: 'Enviar Directriz',
        endpoint: `/gastos/${gasto.id}/return-to-collaborator`,
        method: 'post',
        needsComment: true,
        commentLabel: 'Tu instrucción para el colaborador:'
      };
      break;
    case 'resubmit':
      config = {
        title: 'Corregir y Reenviar Gasto',
        // AJUSTE: Se añade el motivo de la observación de ADM al texto del modal
        html: `
                    <div class="text-left">
                        <p class="mb-2">El gasto <strong>${gasto.codigo_gasto}</strong> fue observado.</p>
                        <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="font-semibold text-yellow-800">Motivo de la observación:</p>
                            <p class="text-yellow-700 italic">"${gasto.motivo_observacion_adm || 'No se especificó un motivo.'}"</p>
                        </div>
                        <p class="mt-4">Por favor, describe la corrección realizada.</p>
                    </div>
                `,
        icon: 'info',
        confirmButtonText: 'Reenviar Gasto',
        endpoint: `/gastos/${gasto.id}/resubmit`,
        method: 'put',
        needsComment: true,
        commentLabel: 'Comentario de corrección:'
      };
      break;
    default:
      return;
  }

  let comentario = '';
  if (config.needsComment) {
    const { value: text } = await Swal.fire({
      title: config.title,
      html: config.html, // Se usa 'html' para mostrar el contenido enriquecido
      input: 'textarea',
      inputLabel: config.commentLabel,
      inputPlaceholder: 'Escribe tu comentario aquí...',
      showCancelButton: true,
      confirmButtonText: config.confirmButtonText,
      confirmButtonColor: '#3085d6',
      cancelButtonText: 'Cancelar'
    });

    if (text) {
      comentario = text;
    } else {
      return;
    }
  } else {
    const result = await Swal.fire({
      title: config.title,
      text: config.text,
      icon: config.icon,
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: config.confirmButtonText,
      cancelButtonText: 'Cancelar'
    });
    if (!result.isConfirmed) {
      return;
    }
  }

  try {
    await api[config.method](config.endpoint, { comentario });
    Swal.fire('¡Acción Completada!', 'La operación se realizó con éxito.', 'success');
    fetchGastos();
  } catch (error) {
    console.error(`Error al ejecutar la acción ${accion}:`, error);
    const errorMessage = error.response?.data?.message || 'Ocurrió un error inesperado.';
    Swal.fire('Error', errorMessage, 'error');
  }
};

const irAPagina = (pagina) => {
  if (typeof pagina === 'number') {
    paginaActual.value = pagina;
  }
};
const paginaAnterior = () => { if (paginaActual.value > 1) paginaActual.value--; };
const paginaSiguiente = () => { if (paginaActual.value < totalPaginas.value) paginaActual.value++; };



// --- WATCHERS Y LIFECYCLE ---
watch(filtros, () => {
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    paginaActual.value = 1;
    fetchGastos();
  }, 500);
}, { deep: true });

onMounted(() => {
  // Se obtiene el usuario actual para determinar los permisos de visualización.
  api.get('/user').then(response => usuarioActual.value = response.data);
  fetchGastos();
});
</script>

<style scoped>
/* Estilos sin cambios */
.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
  opacity: 0;
  transform: translateY(20px);
}
</style>
