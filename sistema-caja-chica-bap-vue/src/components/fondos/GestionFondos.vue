<template>
  <div class="p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Gestión y Seguimiento de Fondos de Caja Chica</h2>

    <div v-if="cargandoUsuario" class="text-center text-gray-500 py-8">
      <div class="inline-flex items-center">
        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
          viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor"
            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
          </path>
        </svg>
        Cargando datos del usuario...
      </div>
    </div>

    <div v-else>
      <!-- Panel de filtros -->
      <div class="mb-6 p-4 bg-gray-50 rounded-lg shadow-sm">
        <h3 class="text-xl font-semibold text-gray-700 mb-4">Filtros de Búsqueda</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 mb-4">
          <!-- Filtro Código de Fondo -->
          <div class="relative">
            <label for="filter_codigo_fondo" class="block text-sm font-medium text-gray-700 mb-1">Código de
              Fondo</label>
            <input type="text" id="filter_codigo_fondo" v-model="filtro.codigo_fondo"
              class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
              placeholder="Ej: FNRO-00001" />
            <div v-if="buscandoFondos && filtro.codigo_fondo.length > 0" class="absolute right-3 top-8 text-gray-400">
              <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
              </svg>
            </div>
          </div>

          <!-- Filtro Responsable -->
          <div class="relative">
            <label for="filter_responsable_name" class="block text-sm font-medium text-gray-700 mb-1">Responsable</label>
            <input type="text" id="filter_responsable_name" v-model="filtro.responsable_name"
              class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
              placeholder="Nombre o Apellido" />
            <div v-if="buscandoFondos && filtro.responsable_name.length > 0" class="absolute right-3 top-8 text-gray-400">
              <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
              </svg>
            </div>
          </div>

          <!-- Filtro Estado del Fondo -->
          <div>
            <label for="filter_estado" class="block text-sm font-medium text-gray-700 mb-1">Estado del Fondo</label>
            <select id="filter_estado" v-model="filtro.estado"
              class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
              <option value="Todos">Todos</option>
              <option value="Activo">Activo</option>
              <option value="Cerrado">Cerrado</option>
            </select>
          </div>

          <!-- Filtro Área del Fondo (solo para JADM/SA) -->
          <div v-if="usuarioActual && (usuarioActual.role.name === 'jefe_administracion' || usuarioActual.role.name === 'super_admin')">
            <label for="filter_area" class="block text-sm font-medium text-gray-700 mb-1">Área del Fondo</label>
            <select id="filter_area" v-model="filtro.area_id"
              class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
              <option value="">Todas las Áreas</option>
              <option v-for="area in areasDisponibles" :key="area.id" :value="area.id">{{ area.name }}</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end space-x-3 mt-4">
          <button @click="aplicarFiltros"
            class="bg-verde-bap hover:bg-emerald-600 text-white font-bold py-2 px-5 rounded-full transition-colors shadow-lg flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
              stroke="currentColor" class="w-5 h-5 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round"
                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            Buscar
          </button>
          <button @click="limpiarFiltros"
            class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-5 rounded-full transition-colors shadow-lg flex items-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2"
              stroke="currentColor" class="w-5 h-5 mr-2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
            Limpiar
          </button>
        </div>

        <!-- Indicador de estado de búsqueda con debounce -->
        <div v-if="buscandoFondos" class="mt-3 text-sm text-verde-bap flex items-center">
          <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-verde-bap" xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
            </path>
          </svg>
          Buscando fondos...
        </div>
      </div>

      <!-- Tabla de Fondos -->
      <div v-if="cargandoFondos" class="text-center text-gray-500 py-8">
        <div class="inline-flex items-center">
          <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
            viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
            </path>
          </svg>
          Cargando fondos...
        </div>
      </div>
      <div v-else-if="fondosMostrados.length === 0" class="text-center text-gray-500 py-8">
        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="text-lg font-medium">No se encontraron fondos de caja chica</p>
        <p class="text-sm text-gray-400 mt-1">
          {{ hayFiltrosActivos ? 'Intenta ajustar los filtros de búsqueda' : 'No hay fondos registrados o activos.' }}
        </p>
        <button v-if="hayFiltrosActivos" @click="limpiarFiltros"
          class="mt-3 px-4 py-2 bg-verde-bap text-white rounded-md hover:bg-verde-bap-dark transition-colors duration-200 text-sm">
          Limpiar filtros
        </button>
      </div>

      <div v-else>
        <div class="mb-4 text-sm text-gray-600 text-center">
          Mostrando {{ (paginaActual - 1) * registrosPorPagina + 1 }} -
          {{ Math.min(paginaActual * registrosPorPagina, fondosFiltrados.length) }}
          de {{ fondosFiltrados.length }} fondos
        </div>
        <div class="overflow-x-auto shadow-lg rounded-lg">
          <table class="min-w-full bg-white border border-gray-200 rounded-lg">
            <thead>
              <tr class="bg-gray-100 text-gray-700 uppercase text-sm leading-normal">
                <th class="py-4 px-4 text-left font-semibold">Código Fondo</th>
                <th class="py-4 px-4 text-left font-semibold">Responsable Fondo</th>
                <th class="py-4 px-4 text-left font-semibold">Área Fondo</th>
                <th class="py-4 px-4 text-left font-semibold">Monto Aprobado</th>
                <th class="py-4 px-4 text-left font-semibold">Estado</th>
                <th class="py-4 px-4 text-left font-semibold">Fecha Apertura</th>
                <th class="py-4 px-4 text-left font-semibold">Solicitud Apertura</th>
                <th class="py-4 px-4 text-left font-semibold">Aprobador ADM</th>
                <th class="py-4 px-4 text-left font-semibold">Aprobador GRTE</th>
                <th class="py-4 px-4 text-center font-semibold w-4">Historial</th>
              </tr>
            </thead>
            <tbody class="text-gray-600 text-sm">
              <tr v-for="fondo in fondosMostrados" :key="fondo.id_fondo"
                class="border-b border-gray-200 hover:bg-gray-50 transition-colors duration-200">
                <td class="py-4 px-4 text-left whitespace-nowrap">{{ fondo.codigo_fondo }}</td>
                <td class="py-4 px-4 text-left">{{ fondo.responsable?.name }} {{ fondo.responsable?.last_name }}</td>
                <td class="py-4 px-4 text-left">{{ fondo.area?.name }}</td>
                <td class="py-4 px-4 text-left font-medium whitespace-nowrap">S/. {{ fondo.monto_aprobado ? parseFloat(fondo.monto_aprobado).toFixed(2) : '0.00' }}</td>
                <td class="py-4 px-4 text-left">
                  <span :class="{
                    'bg-green-200 text-green-600': fondo.estado === 'Activo',
                    'bg-red-200 text-red-600': fondo.estado === 'Cerrado'
                  }" class="py-2 px-3 rounded-full text-xs font-semibold inline-block text-center w-24">
                    {{ fondo.estado }}
                  </span>
                </td>
                <td class="py-4 px-4 text-left">
                  {{ new Date(fondo.fecha_apertura).toLocaleDateString('es-ES') }}
                </td>
                <td class="py-4 px-4 text-left">
                  {{ fondo.solicitud_apertura?.codigo_solicitud || 'N/A' }}
                </td>
                <td class="py-4 px-4 text-left">
                  {{ fondo.solicitud_apertura?.revisor_adm?.name ? `${fondo.solicitud_apertura.revisor_adm.name} ${fondo.solicitud_apertura.revisor_adm.last_name}` : 'N/A' }}
                </td>
                <td class="py-4 px-4 text-left">
                  {{ fondo.solicitud_apertura?.aprobador_gerente?.name ? `${fondo.solicitud_apertura.aprobador_gerente.name} ${fondo.solicitud_apertura.aprobador_gerente.last_name}` : 'N/A' }}
                </td>
                <td class="py-4 px-4 text-center">
                  <button @click="verHistorialFondo(fondo)"
                    class="w-8 h-8 rounded-full bg-blue-200 hover:bg-blue-300 flex items-center justify-center text-blue-700 transition-colors duration-200"
                    title="Ver Historial de Cambios">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                      class="w-4 h-4">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-6 flex justify-center items-center space-x-2">
          <button @click="paginaAnterior" :disabled="paginaActual === 1" :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200',
            paginaActual === 1
                ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                : 'bg-verde-bap text-white hover:bg-verde-bap-dark'
          ]">
            Anterior
          </button>

          <div class="flex space-x-1">
            <button v-for="pagina in Math.min(totalPaginas, 5)" :key="pagina" @click="irAPagina(pagina)" :class="[
                'w-10 h-10 rounded-lg text-sm font-medium transition-colors duration-200',
                paginaActual === pagina
                    ? 'bg-verde-bap text-white'
                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
            ]">
              {{ pagina }}
            </button>

            <span v-if="totalPaginas > 5" class="flex items-center px-2 text-gray-500">...</span>

            <button v-if="totalPaginas > 5" @click="irAPagina(totalPaginas)" :class="[
                'w-10 h-10 rounded-lg text-sm font-medium transition-colors duration-200',
                paginaActual === totalPaginas
                    ? 'bg-verde-bap text-white'
                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
            ]">
              {{ totalPaginas }}
            </button>
          </div>

          <button @click="paginaSiguiente" :disabled="paginaActual === totalPaginas" :class="[
            'px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200',
            paginaActual === totalPaginas
                ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                : 'bg-verde-bap text-white hover:bg-verde-bap-dark'
          ]">
            Siguiente
          </button>
        </div>

        <div class="mt-4 text-center text-sm text-gray-600">
          Página {{ paginaActual }} de {{ totalPaginas }}
        </div>
      </div>
    </div>

    <!-- Modal para el historial de estados de la solicitud de apertura -->
    <HistorialEstadosModal :mostrar="mostrarHistorialModal" :solicitud="solicitudHistorialSeleccionada"
      @close="cerrarHistorialModal" />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';
// Asumiendo que ya tienes este modal:
import HistorialEstadosModal from './HistorialEstadosModal.vue';

// --- Variables de Estado ---
const usuarioActual = ref(null);
const cargandoUsuario = ref(true);
const fondos = ref([]); // Almacena todos los fondos obtenidos de la API
const cargandoFondos = ref(true);
const buscandoFondos = ref(false); // Indica si hay una búsqueda pendiente por debounce
const areasDisponibles = ref([]);

// Variables para el modal de historial de estados
const mostrarHistorialModal = ref(false);
const solicitudHistorialSeleccionada = ref(null); // Almacenará la solicitud de apertura asociada al fondo

// --- Variables para Filtros y Búsqueda ---
const filtro = ref({
  codigo_fondo: '',
  responsable_name: '',
  estado: 'Todos',
  area_id: '',
});

// Variables para el debounce de los campos de texto
let debounceTimeout = null;
const DEBOUNCE_DELAY = 800; // Aumentado para mejor UX
const MIN_SEARCH_LENGTH = 3;

// --- Variables para Paginación ---
const paginaActual = ref(1);
const registrosPorPagina = ref(10);

const router = useRouter(); // Para redirección si no hay autenticación

// --- Propiedades Computadas ---
const rolUsuario = computed(() => {
  return usuarioActual.value?.role?.name || null;
});

const hayFiltrosActivos = computed(() => {
  return filtro.value.codigo_fondo.length > 0 ||
         filtro.value.responsable_name.length > 0 ||
         filtro.value.estado !== 'Todos' ||
         filtro.value.area_id !== '';
});

// Filtros para la lista mostrada (se aplica al array 'fondos')
const fondosFiltrados = computed(() => {
    // La lógica de filtrado principal se hará en el backend.
    // Este computed simplemente retorna los fondos tal como los recibe.
    // Esto se mantiene así porque la paginación local trabaja sobre el array completo de fondos.
    return fondos.value;
});

// Paginación
const totalPaginas = computed(() => {
  return Math.ceil(fondosFiltrados.value.length / registrosPorPagina.value);
});

const fondosMostrados = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value;
  const fin = inicio + registrosPorPagina.value;
  return fondosFiltrados.value.slice(inicio, fin);
});


// --- Funciones de Carga de Datos ---
const obtenerUsuarioAutenticado = async () => {
  cargandoUsuario.value = true;
  try {
    const response = await api.get('/user');
    usuarioActual.value = response.data.user;
    console.log('✅ Usuario y Rol asignados correctamente:', usuarioActual.value?.name, rolUsuario.value);
  } catch (error) {
    console.error('❌ Error al obtener datos del usuario autenticado:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error de Autenticación',
      text: 'No se pudieron cargar los datos del usuario. Por favor, inicia sesión de nuevo.',
      confirmButtonText: 'Ir a Login'
    }).then(() => {
      router.push('/login');
    });
  } finally {
    cargandoUsuario.value = false;
  }
};

const obtenerFondos = async () => {
  if (!buscandoFondos.value) {
    cargandoFondos.value = true;
  }

  try {
    const params = {};

    // Aplicar filtros de texto (solo si cumplen el mínimo de caracteres o están vacíos)
    if (filtro.value.codigo_fondo.length >= MIN_SEARCH_LENGTH || filtro.value.codigo_fondo.length === 0) {
      params.codigo_fondo = filtro.value.codigo_fondo;
    }
    if (filtro.value.responsable_name.length >= MIN_SEARCH_LENGTH || filtro.value.responsable_name.length === 0) {
      params.responsable_name = filtro.value.responsable_name;
    }

    // Aplicar filtros de selección
    if (filtro.value.estado !== 'Todos') {
      params.estado = filtro.value.estado;
    }
    if (filtro.value.area_id) {
      params.area_id = filtro.value.area_id;
    }

    console.log('📤 Parámetros de búsqueda de fondos:', params);

    const response = await api.get('/fondos-efectivo', { params });

    if (response.data && Array.isArray(response.data.fondos)) {
      fondos.value = response.data.fondos.map(fondo => ({
        ...fondo,
        monto_aprobado: parseFloat(fondo.monto_aprobado)
      }));
      console.log(`📥 Fondos cargados: ${fondos.value.length}`);
    } else {
      console.error('La respuesta de la API no contiene un array de fondos:', response.data);
      fondos.value = [];
      Swal.fire({
        icon: 'warning',
        title: 'Datos Inesperados',
        text: 'La API devolvió un formato de datos inesperado para los fondos de efectivo. Por favor, contacta a soporte.'
      });
    }
    // Siempre resetear a la primera página cuando se aplican filtros/se recargan los datos
    paginaActual.value = 1;

  } catch (error) {
    console.error('❌ Error al obtener fondos:', error);
    let errorMessage = 'No se pudieron cargar los fondos de efectivo. Por favor, inténtalo de nuevo.';
    if (error.response && error.response.data && error.response.data.message) {
      errorMessage = error.response.data.message;
    }
    Swal.fire({
      icon: 'error',
      title: 'Error al cargar fondos',
      text: errorMessage
    });
  } finally {
    cargandoFondos.value = false;
    buscandoFondos.value = false; // Resetear indicador de búsqueda pendiente
  }
};

// Función para obtener las áreas
const obtenerAreas = async () => {
  try {
    const response = await api.get('/areas');
    areasDisponibles.value = response.data.areas;
  } catch (error) {
    console.error('Error al obtener áreas:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'No se pudieron cargar las áreas disponibles. Por favor, verifica la configuración de la API de áreas.'
    });
  }
};

// --- Funciones de Filtrado y Búsqueda ---
const triggerSearchWithDebounce = () => {
  buscandoFondos.value = true; // Indicar que hay una búsqueda pendiente
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    obtenerFondos();
  }, DEBOUNCE_DELAY);
};

const aplicarFiltros = () => {
  clearTimeout(debounceTimeout); // Limpiar cualquier debounce pendiente
  buscandoFondos.value = false; // Resetear indicador de búsqueda pendiente
  obtenerFondos(); // Vuelve a llamar a la API con los filtros actuales
};

const limpiarFiltros = () => {
  filtro.value.codigo_fondo = '';
  filtro.value.responsable_name = '';
  filtro.value.estado = 'Todos';
  filtro.value.area_id = '';
  clearTimeout(debounceTimeout); // Limpiar cualquier debounce pendiente
  buscandoFondos.value = false; // Resetear indicador de búsqueda pendiente
  obtenerFondos(); // Vuelve a llamar a la API sin filtros
};

// --- Funciones de Paginación ---
const irAPagina = (pagina) => {
  if (pagina >= 1 && pagina <= totalPaginas.value) {
    paginaActual.value = pagina;
  }
};

const paginaAnterior = () => {
  if (paginaActual.value > 1) {
    paginaActual.value--;
  }
};

const paginaSiguiente = () => {
  if (paginaActual.value < totalPaginas.value) {
    paginaActual.value++;
  }
};

// --- Funciones para el Historial de Fondos ---
const verHistorialFondo = async (fondo) => {
    // Para ver el historial de un fondo, necesitamos la solicitud de apertura original
    // ya que es a la solicitud de apertura a la que se le adjunta el historial de estados
    // y las solicitudes de incremento/decremento/cierre.
    if (!fondo.id_solicitud_apertura) {
        Swal.fire('Información', 'Este fondo no tiene una solicitud de apertura asociada para ver el historial.', 'info');
        return;
    }

    try {
        // Obtenemos la solicitud de apertura completa con su historial
        // El backend ya debería cargar las relaciones de revisor_adm y aprobador_gerente
        // si la solicitud de apertura las tiene.
        // NOTA: Si el `fondo` ya trae la relación `solicitud_apertura` completa (con historial, revisores),
        // no sería necesario hacer otra llamada a `/solicitudes-fondo/{id}`.
        // Para simplificar, asumimos que `obtenerFondos` ya carga `solicitudApertura` con todo lo necesario.
        solicitudHistorialSeleccionada.value = fondo.solicitud_apertura;
        mostrarHistorialModal.value = true;
    } catch (error) {
        console.error('Error al obtener la solicitud de apertura para el historial:', error);
        let errorMessage = 'No se pudo cargar el historial de la solicitud. Por favor, inténtalo de nuevo.';
        if (error.response && error.response.data && error.response.data.message) {
            errorMessage = error.response.data.message;
        }
        Swal.fire({
            icon: 'error',
            title: 'Error de Historial',
            text: errorMessage
        });
    }
};

const cerrarHistorialModal = () => {
  mostrarHistorialModal.value = false;
  solicitudHistorialSeleccionada.value = null;
};


// --- Watchers ---
// Watchers para filtros de texto (debounced, con lógica de longitud mínima)
watch(() => filtro.value.codigo_fondo, (newValue) => {
  if (newValue.length >= MIN_SEARCH_LENGTH || newValue.length === 0) {
    triggerSearchWithDebounce();
  } else {
    // Si no cumple la longitud mínima, pero hay algo escrito, mostrar como "buscando"
    // Esto evita llamadas a la API innecesarias para cadenas muy cortas
    buscandoFondos.value = true;
  }
});

watch(() => filtro.value.responsable_name, (newValue) => {
  if (newValue.length >= MIN_SEARCH_LENGTH || newValue.length === 0) {
    triggerSearchWithDebounce();
  } else {
    buscandoFondos.value = true;
  }
});

// Watchers para filtros de selección (disparan búsqueda inmediata)
watch(() => filtro.value.estado, () => {
  clearTimeout(debounceTimeout);
  buscandoFondos.value = false;
  obtenerFondos();
});

watch(() => filtro.value.area_id, () => {
  clearTimeout(debounceTimeout);
  buscandoFondos.value = false;
  obtenerFondos();
});

// --- Ciclo de Vida ---
onMounted(() => {
  obtenerUsuarioAutenticado();
  obtenerAreas(); // Carga las áreas para el filtro
  obtenerFondos(); // Carga inicial de todos los fondos
});
</script>

<style scoped>
/* Las transiciones del modal y los estilos del scrollbar personalizados se mantienen aquí
   porque son estilos específicos del componente y no utilidades de Tailwind. */

.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(20px);
}

/* Estilos para el spinner de carga en los inputs */
.relative input[type="text"],
.relative input[type="date"] {
  padding-right: 2.5rem; /* Espacio para el spinner */
}

</style>
