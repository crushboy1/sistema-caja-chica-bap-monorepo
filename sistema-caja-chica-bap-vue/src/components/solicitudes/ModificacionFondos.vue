<template>
  <div class="p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-3xl font-bold text-gray-800 mb-6">Modificación de Fondos de Caja Chica</h2>

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
      <Transition name="fade-slide" mode="out-in">
        <div v-if="vistaActual === 'lista'" key="mod-list-view">
          <h3 class="text-xl font-semibold text-gray-800 mb-4">Selecciona un Fondo Activo para Modificar</h3>

          <div class="bg-gray-100 p-4 rounded-lg shadow-inner mb-6">
            <h4 class="text-lg font-semibold text-gray-700 mb-3">Filtros de Búsqueda</h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <!-- Filtro Código de Fondo -->
              <div class="relative">
                <label for="filter_codigo_fondo" class="block text-sm font-medium text-gray-700 mb-1">Código de
                  Fondo</label>
                <input type="text" id="filter_codigo_fondo" v-model="filtro.codigo_fondo"
                  class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                  placeholder="Ej: FNRO-00001" />
                <div v-if="buscandoFondos && filtro.codigo_fondo.length > 0"
                  class="absolute right-3 top-8 text-gray-400">
                  <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                  </svg>
                </div>
              </div>

              <!-- Filtro Fecha de Apertura -->
              <div class="relative">
                <label for="filter_fecha_apertura" class="block text-sm font-medium text-gray-700 mb-1">Fecha de
                  Apertura</label>
                <input type="date" id="filter_fecha_apertura" v-model="filtro.fecha_apertura"
                  class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap" />
                <div v-if="buscandoFondos && filtro.fecha_apertura"
                  class="absolute right-3 top-8 text-gray-400">
                  <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                  </svg>
                </div>
              </div>

              <!-- Filtro Área del Fondo (solo para JADM/SA) -->
              <div
                v-if="usuarioActual && (usuarioActual.role.name === 'jefe_administracion' || usuarioActual.role.name === 'super_admin')">
                <label for="filter_area" class="block text-sm font-medium text-gray-700 mb-1">Área del Fondo</label>
                <select id="filter_area" v-model="filtro.area_id"
                  class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                  <option value="">Todas las Áreas</option>
                  <option v-for="area in areasDisponibles" :key="area.id" :value="area.id">{{ area.name }}</option>
                </select>
              </div>
            </div>
            <div class="mt-4 flex justify-end space-x-3">
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

          <div v-if="cargandoFondos" class="text-center text-gray-500 py-8">
            <div class="inline-flex items-center">
              <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
              </svg>
              Cargando fondos existentes...
            </div>
          </div>
          <div v-else-if="fondosFiltrados.length === 0" class="text-center text-gray-500 py-8">
            No hay fondos de caja chica activos que coincidan con los criterios de búsqueda.
          </div>

          <div v-else class="overflow-x-auto shadow-lg rounded-lg">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
              <thead>
                <tr class="bg-gray-100 text-gray-700 uppercase text-sm leading-normal">
                  <th class="py-3 px-6 text-left">Código de Fondo</th>
                  <th class="py-3 px-6 text-left">Responsable</th>
                  <th class="py-3 px-6 text-left">Área</th>
                  <th class="py-3 px-6 text-left">Estado del Fondo</th>
                  <th class="py-3 px-6 text-left">Monto Actual</th>
                  <th class="py-3 px-6 text-left">Fecha de Apertura</th>
                  <th class="py-3 px-6 text-center">Acciones</th>
                </tr>
              </thead>
              <tbody class="text-gray-600 text-sm font-light">
                <tr v-for="fondo in fondosFiltrados" :key="fondo.id_fondo"
                  class="border-b border-gray-200 hover:bg-gray-50">
                  <td class="py-3 px-6 text-left whitespace-nowrap">{{ fondo.codigo_fondo }}</td>
                  <td class="py-3 px-6 text-left">{{ fondo.responsable?.name }} {{ fondo.responsable?.last_name }}</td>
                  <td class="py-3 px-6 text-left">{{ fondo.area?.name }}</td>
                  <td class="py-3 px-6 text-left">
                    <span :class="{
                      'bg-green-200 text-green-600': fondo.estado === 'Activo',
                      'bg-red-200 text-red-600': fondo.estado === 'Cerrado'
                    }" class="py-1 px-3 rounded-full text-xs font-semibold">
                      {{ fondo.estado }}
                    </span>
                  </td>
                  <td class="py-3 px-6 text-left">S/. {{ fondo.monto_aprobado.toFixed(2) }}</td>
                  <td class="py-3 px-6 text-left">{{ new Date(fondo.fecha_apertura).toLocaleDateString() }}</td>
                  <td class="py-3 px-6 text-center">
                    <button @click="seleccionarFondoParaEditar(fondo)"
                      class="bg-verde-bap hover:bg-emerald-600 text-white font-bold py-2 px-4 rounded-full transition-colors shadow-lg">
                      Modificar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="flex justify-end mt-6">
            <button type="button" @click="cerrarComponente"
              class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-8 rounded-full transition-colors shadow-lg">
              Cerrar Módulo
            </button>
          </div>
        </div>

        <div v-else-if="vistaActual === 'formulario' && fondoParaEditar" key="mod-form-view">
          <h3 class="text-2xl font-bold text-gray-800 mb-4">Modificar Fondo: Código {{ fondoParaEditar.codigo_fondo }}
          </h3>
          <p class="text-gray-600 mb-6">Monto Aprobado Actual: S/. {{ fondoParaEditar.monto_aprobado.toFixed(2) }}</p>

          <form @submit.prevent="manejarEnvio">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
              <div>
                <label for="solicitante" class="block text-sm font-medium text-gray-700 mb-1">Solicitante del
                  Fondo</label>
                <input type="text" id="solicitante"
                  :value="fondoParaEditar.responsable?.name + ' ' + fondoParaEditar.responsable?.last_name"
                  class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                  disabled />
              </div>
              <div>
                <label for="cargo" class="block text-sm font-medium text-gray-700 mb-1">Cargo del Solicitante</label>
                <input type="text" id="cargo" :value="fondoParaEditar.responsable?.cargo || 'No especificado'"
                  class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                  disabled />
              </div>
              <div>
                <label for="area" class="block text-sm font-medium text-gray-700 mb-1">Área del Fondo</label>
                <input type="text" id="area" :value="fondoParaEditar.area?.name"
                  class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                  disabled />
              </div>
              <div>
                <label for="fecha_solicitud" class="block text-sm font-medium text-gray-700 mb-1">Fecha de
                  Solicitud</label>
                <input type="text" id="fecha_solicitud" :value="new Date().toLocaleDateString()"
                  class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                  disabled />
              </div>
            </div>

            <div
              v-if="fondoParaEditar.solicitud_apertura && fondoParaEditar.solicitud_apertura.detalles_gastos_proyectados && fondoParaEditar.solicitud_apertura.detalles_gastos_proyectados.length > 0"
              class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-100">
              <h3 class="text-xl font-semibold text-gray-800 mb-4">Gastos Proyectados al Momento de la Apertura (Solo
                Lectura)</h3>
              <div v-for="(gastoOriginal, index) in fondoParaEditar.solicitud_apertura.detalles_gastos_proyectados"
                :key="'original-' + index" class="bg-white p-4 rounded-lg shadow-sm mb-2 border border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Descripción del Gasto:</label>
                    <p class="mt-1 block w-full p-3 bg-gray-50 rounded-md text-gray-800 font-medium">{{
                      gastoOriginal.descripcion_gasto }}</p>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Monto Estimado (S/.):</label>
                    <p class="mt-1 block w-full p-3 bg-gray-50 rounded-md text-gray-800 font-medium">S/. {{
                      parseFloat(gastoOriginal.monto_estimado).toFixed(2) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Sección de Gastos Detallados: Visible solo si no es Cierre -->
            <div class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50" v-if="tipoModificacion !== 'Cierre'">
              <h3 class="text-xl font-semibold text-gray-800 mb-4 flex justify-between items-center">
                Gastos Detallados Para Sustentar la Modificación
                <button type="button" @click="agregarGastoDetallado"
                  class="bg-verde-bap hover:bg-emerald-600 text-white font-semibold py-2 px-4 rounded-full transition-colors flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                      d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                      clip-rule="evenodd" />
                  </svg>
                  Agregar Gasto
                </button>
              </h3>

              <div v-if="gastosDetallados.length === 0" class="text-gray-500 text-center py-4">
                No hay gastos detallados. Haz clic en "Agregar Gasto" para empezar.
              </div>

              <div v-for="(gasto, index) in gastosDetallados" :key="index"
                class="bg-white p-4 rounded-lg shadow-sm mb-4 border border-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                  <div class="md:col-span-2">
                    <label :for="'descripcion_gasto_' + index"
                      class="block text-sm font-medium text-gray-700 mb-1">Descripción del Tipo de Gasto <span
                        class="text-rojo-bap">*</span></label>
                    <input type="text" :id="'descripcion_gasto_' + index" v-model="gasto.descripcion_gasto"
                      class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                      required />
                  </div>
                  <div class="flex items-center justify-between">
                    <div>
                      <label :for="'monto_estimado_' + index"
                        class="block text-sm font-medium text-gray-700 mb-1">Monto Mensual Estimado (S/.) <span
                          class="text-rojo-bap">*</span></label>
                      <input type="number" :id="'monto_estimado_' + index"
                        v-model.number="gasto.monto_estimado" step="0.01" min="0"
                        class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-white shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                        required />
                    </div>
                    <button type="button" @click="removerGastoDetallado(index)" v-if="gastosDetallados.length > 1"
                      class="ml-4 p-2 bg-rojo-bap hover:bg-red-600 text-white rounded-full shadow-lg transition-colors"
                      aria-label="Eliminar gasto">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        class="w-4 h-4">
                        <path fill-rule="evenodd"
                          d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm6 0a1 1 0 01-2 0v6a1 1 0 112 0V8z"
                          clip-rule="evenodd" />
                      </svg>
                    </button>
                  </div>
                </div>
              </div>

              <div class="text-right mt-6 pt-4 border-t border-gray-200">
                <span class="text-lg font-semibold text-gray-800">Total Gastos Detallados: S/. {{
                  totalGastosDetallados.toFixed(2) }}</span>

                <div v-if="tipoModificacion === 'Incremento' && montoSugerido !== null" class="mt-2">
                  <span class="text-lg font-semibold text-gray-800">Monto Sugerido (Gastos + Actual): S/. {{
                    montoSugerido }}</span>
                </div>
              </div>
            </div>

            <div class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
              <h3 class="text-xl font-semibold text-gray-800 mb-4">Detalle de la Modificación</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="mb-4 md:mb-0">
                  <label for="modification_type" class="block text-sm font-medium text-gray-700 mb-1">Tipo de
                    Modificación <span class="text-rojo-bap">*</span></label>
                  <select id="modification_type" v-model="tipoModificacion"
                    class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                    required>
                    <option value="Incremento"
                      v-if="usuarioActual && (usuarioActual.role.name === 'jefe_area' || usuarioActual.role.name === 'super_admin' || usuarioActual.role.name === 'gerente_general')">
                      Incremento de Fondos</option>
                    <option value="Decremento">Decremento de Fondos</option>
                    <option value="Cierre">Cierre de Fondo</option>
                  </select>
                </div>
                <div>
                  <div class="mb-4" v-if="tipoModificacion !== 'Cierre'">
                    <label for="new_amount_requested" class="block text-sm font-medium text-gray-700 mb-1">Nuevo Monto
                      Solicitado (S/.) <span class="text-rojo-bap">*</span></label>
                    <input type="number" id="new_amount_requested" v-model.number="nuevoMontoSolicitado" step="0.01"
                      min="0"
                      class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                      required />
                  </div>
                  <div class="mb-4">
                    <label for="prioridad" class="block text-sm font-medium text-gray-700 mb-1">Prioridad de Solicitud
                      <span class="text-rojo-bap">*</span></label>
                    <select id="prioridad" v-model="prioridad"
                      class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                      required>
                      <option value="Urgente">Urgente</option>
                      <option value="Alta">Alta</option>
                      <option value="Media">Media</option>
                      <option value="Baja">Baja</option>
                    </select>
                  </div>
                  <div>
                    <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Motivo del {{
                      tipoModificacion === 'Incremento' ? 'Incremento' : (tipoModificacion === 'Decremento' ?
                      'Decremento' : 'Cierre') }} <span class="text-rojo-bap">*</span></label>
                    <textarea id="reason" v-model="motivo" rows="4"
                      class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap resize-none"
                      required></textarea>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end space-x-4">
              <button type="button" @click="volverALaListaInterna"
                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-8 rounded-full transition-colors shadow-lg">
                Volver a la Lista
              </button>
              <button type="submit"
                class="bg-verde-bap hover:bg-emerald-600 text-white font-bold py-3 px-8 rounded-full transition-colors shadow-lg">
                Enviar Solicitud de Modificación
              </button>
            </div>
          </form>
        </div>
        <div v-else class="text-center text-gray-500 py-8">
          No hay fondo seleccionado para modificar.
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

// Emitir 'close' para que el componente padre (SolicitudFondo.vue) pueda cerrarlo
const emit = defineEmits(['close']);

// --- Variables de Estado Internas ---
const vistaActual = ref('lista'); // 'lista' (de fondos a elegir) o 'formulario' (de modificación)
const fondoParaEditar = ref(null); // Almacena el objeto del fondo seleccionado

// --- Variables Reactivas Generales (del usuario y carga de fondos) ---
const usuarioActual = ref(null);
const cargandoUsuario = ref(true);
const fondosExistentes = ref([]); // Para almacenar los fondos de efectivo existentes (todos los activos para JADM/SA, o solo los del JA)
const cargandoFondos = ref(false); // Estado de carga para los fondos
const buscandoFondos = ref(false); // Nuevo: Indica si hay una búsqueda pendiente por debounce
const areasDisponibles = ref([]); // Para el filtro de áreas (solo si es JADM/SA)

// --- Variables para Filtros ---
const filtro = ref({
  codigo_fondo: '',
  fecha_apertura: '',
  area_id: '',
});

const router = useRouter();

// --- Variables Reactivas del Formulario de Modificación ---
const tipoModificacion = ref('Incremento'); // 'Incremento' o 'Decremento'
const nuevoMontoSolicitado = ref(null); // AHORA ES EL NUEVO MONTO TOTAL DESEADO
const motivo = ref(''); // Motivo de la modificación
const prioridad = ref('Media'); // 'Urgente', 'Alta', 'Media', 'Baja'

// Array para los gastos detallados que sustentan el incremento/decremento
const gastosDetallados = ref([]);

// Propiedad computada para el monto total de los gastos detallados
const totalGastosDetallados = computed(() => {
  return gastosDetallados.value.reduce((sum, item) => sum + (item.monto_estimado || 0), 0);
});

// Monto Sugerido (Monto Aprobado Actual + Total Gastos Detallados)
const montoSugerido = computed(() => {
  if (fondoParaEditar.value && tipoModificacion.value === 'Incremento') {
    // Si no hay gastos detallados, el monto sugerido es 0, de lo contrario, suma los gastos al monto actual.
    // Esto se corrige más adelante en el `watch` para `tipoModificacion` y la validación
    if (totalGastosDetallados.value === 0) {
      return (fondoParaEditar.value.monto_aprobado).toFixed(2); // Si no hay gastos, el monto sugerido es solo el actual.
    }
    return (fondoParaEditar.value.monto_aprobado + totalGastosDetallados.value).toFixed(2);
  }
  return null;
});

// Watcher para resetear el monto y los gastos detallados al cambiar el tipo de modificación
// y para ajustar el nuevoMontoSolicitado
watch(tipoModificacion, (nuevoTipo) => {
  gastosDetallados.value = []; // Siempre limpiar gastos detallados al cambiar el tipo
  if (nuevoTipo === 'Incremento' || nuevoTipo === 'Decremento') {
    // Para incremento o decremento, se requiere al menos un gasto detallado para sustentarlo
    agregarGastoDetallado();
    nuevoMontoSolicitado.value = null; // Se espera que el usuario ingrese un monto
  } else if (nuevoTipo === 'Cierre') {
    // Si es Cierre, el monto solicitado debe ser 0 y no se necesitan gastos detallados
    nuevoMontoSolicitado.value = 0;
  }
});

// --- Funciones para Gastos Detallados ---
const agregarGastoDetallado = () => {
  gastosDetallados.value.push({
    descripcion_gasto: '',
    monto_estimado: null,
  });
};

const removerGastoDetallado = (index) => {
  gastosDetallados.value.splice(index, 1);
};

// --- Funciones de Carga de Datos ---
const obtenerUsuarioAutenticado = async () => {
  try {
    const response = await api.get('/user');
    usuarioActual.value = response.data.user;
    cargandoUsuario.value = false;
  } catch (error) {
    console.error('Error al obtener datos del usuario autenticado:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error de Autenticación',
      text: 'No se pudieron cargar los datos del usuario. Por favor, inicia sesión de nuevo.',
      confirmButtonText: 'Ir a Login'
    }).then(() => {
      router.push('/login');
    });
    cargandoUsuario.value = false;
  }
};

const obtenerFondosExistentes = async () => {
  // Solo mostrar el loader principal si no es una búsqueda debounced
  if (!buscandoFondos.value) {
    cargandoFondos.value = true;
  }

  try {
    // Construir los parámetros de la API para los filtros
    const params = { estado: 'Activo' };

    if (filtro.value.codigo_fondo) {
      params.codigo_fondo = filtro.value.codigo_fondo;
    }
    if (filtro.value.fecha_apertura) {
      params.fecha_apertura = filtro.value.fecha_apertura;
    }
    // Si el usuario es Jefe de Administración o Super Admin, enviar area_id del filtro
    if (usuarioActual.value && (usuarioActual.value.role.name === 'jefe_administracion' || usuarioActual.value.role.name === 'super_admin')) {
      if (filtro.value.area_id) {
        params.area_id = filtro.value.area_id;
      }
    }

    const response = await api.get('/fondos-efectivo', { params });

    // --- CORRECCIÓN CLAVE AQUÍ ---
    // Cambiamos 'response.data.fondo_efectivo' a 'response.data.fondos'
    if (response.data && Array.isArray(response.data.fondos)) {
      fondosExistentes.value = response.data.fondos.map(fondo => ({
        ...fondo,
        monto_aprobado: parseFloat(fondo.monto_aprobado)
      }));
      console.log('Fondos activos cargados exitosamente:', fondosExistentes.value.length, 'fondos.');
    } else {
      console.error('La respuesta de la API no contiene un array de fondos:', response.data);
      fondosExistentes.value = [];
      Swal.fire({
        icon: 'warning',
        title: 'Datos Inesperados',
        text: 'La API devolvió un formato de datos inesperado para los fondos de efectivo. Por favor, contacta a soporte.'
      });
    }

  } catch (error) {
    console.error('Error al obtener fondos existentes:', error);
    let errorMessage = 'No se pudieron cargar los fondos de efectivo activos. Asegúrate de tener fondos activos o verifica tu conexión.';
    if (error.response && error.response.status === 404) {
      errorMessage = 'Error: La ruta para obtener los fondos de efectivo activos no se encontró en el servidor. Por favor, verifica tu configuración de API en el backend.';
    } else if (error.response && error.response.data && error.response.data.message) {
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


const fondosFiltrados = computed(() => {
  return fondosExistentes.value;
});

// --- Función para manejar búsquedas con debounce (general) ---
let debounceTimeout = null;
const DEBOUNCE_DELAY = 800;

const triggerSearchWithDebounce = () => {
  buscandoFondos.value = true; // Indicar que hay una búsqueda pendiente
  clearTimeout(debounceTimeout);
  debounceTimeout = setTimeout(() => {
    obtenerFondosExistentes();
  }, DEBOUNCE_DELAY);
};

const aplicarFiltros = () => {
  clearTimeout(debounceTimeout); // Limpiar cualquier debounce pendiente
  buscandoFondos.value = false; // Resetear indicador de búsqueda pendiente
  obtenerFondosExistentes(); // Vuelve a llamar a la API con los filtros actuales
};

const limpiarFiltros = () => {
  filtro.value.codigo_fondo = '';
  filtro.value.fecha_apertura = '';
  filtro.value.area_id = '';
  clearTimeout(debounceTimeout); // Limpiar cualquier debounce pendiente
  buscandoFondos.value = false; // Resetear indicador de búsqueda pendiente
  obtenerFondosExistentes(); // Vuelve a llamar a la API sin filtros
};


// --- Funciones de Navegación de Vistas Internas ---
const seleccionarFondoParaEditar = (fondo) => {
  fondoParaEditar.value = fondo;
  tipoModificacion.value = 'Incremento'; // Por defecto al abrir el formulario
  nuevoMontoSolicitado.value = null; // Se espera un nuevo monto total
  motivo.value = ''; // Se espera un nuevo motivo
  prioridad.value = 'Media'; // Por defecto
  gastosDetallados.value = [];
  agregarGastoDetallado(); // Añadir un ítem de gasto vacío para empezar (esto es importante para Incrementar/Decrementar)
  vistaActual.value = 'formulario'; // Cambia a la vista del formulario
};

// Esta función es para volver a la lista INTERNA de fondos dentro de este componente
const volverALaListaInterna = () => {
  vistaActual.value = 'lista';
  fondoParaEditar.value = null; // Limpiar el fondo seleccionado
  obtenerFondosExistentes(); // Volver a cargar la lista para ver cualquier cambio
};

// Esta función es para cerrar el componente completo y volver a la vista principal del padre
const cerrarComponente = () => {
  emit('close'); // Notifica al padre para que maneje la transición
};

// --- Función para Manejar el Envío del Formulario ---
const manejarEnvio = async () => {
  // Validaciones básicas del frontend
  if (!fondoParaEditar.value) {
    Swal.fire({ icon: 'error', title: 'Error de Validación', text: 'No se ha seleccionado un fondo para modificar.' });
    return;
  }

  // Validar nuevoMontoSolicitado según el tipo de modificación
  if (tipoModificacion.value === 'Cierre') {
    if (nuevoMontoSolicitado.value !== 0) {
      Swal.fire({ icon: 'error', title: 'Error de Validación', text: 'Para un cierre, el Nuevo Monto Solicitado debe ser 0.' });
      return;
    }
  } else { // Para 'Incremento' o 'Decremento'
    if (nuevoMontoSolicitado.value === null || nuevoMontoSolicitado.value < 0) {
      Swal.fire({ icon: 'error', title: 'Error de Validación', text: `Por favor, ingresa un Nuevo Monto Solicitado válido (mayor o igual a 0).` });
      return;
    }
  }

  if (!motivo.value) {
    Swal.fire({ icon: 'error', title: 'Error de Validación', text: `Por favor, ingresa el motivo del ${tipoModificacion.value.toLowerCase()}.` });
    return;
  }
  if (!prioridad.value) {
    Swal.fire({ icon: 'error', title: 'Error de Validación', text: 'Por favor, selecciona una prioridad de solicitud.' });
    return;
  }

  // VALIDACIÓN CLAVE: Validar gastos detallados solo si NO es un cierre
  if (tipoModificacion.value !== 'Cierre') {
    if (gastosDetallados.value.length === 0) {
      Swal.fire({ icon: 'error', title: 'Error de Validación', text: 'Para un incremento o decremento, por favor, agrega al menos un gasto detallado para sustentar la modificación.' });
      return;
    }
    // Validar cada gasto detallado individualmente
    for (const gasto of gastosDetallados.value) {
      if (!gasto.descripcion_gasto || gasto.monto_estimado === null || gasto.monto_estimado <= 0) {
        Swal.fire({ icon: 'error', title: 'Error de Validación', text: 'Por favor, completa todos los campos de los gastos detallados con valores válidos (descripción y monto estimado > 0).' });
        return;
      }
    }
  }


  // Validaciones de lógica de negocio para el nuevo monto solicitado
  if (tipoModificacion.value === 'Incremento') {
    if (nuevoMontoSolicitado.value <= fondoParaEditar.value.monto_aprobado) {
      Swal.fire({ icon: 'error', title: 'Error de Lógica', text: 'Para un incremento, el Nuevo Monto Solicitado debe ser mayor que el Monto Aprobado Actual.' });
      return;
    }
  } else if (tipoModificacion.value === 'Decremento') {
    if (nuevoMontoSolicitado.value >= fondoParaEditar.value.monto_aprobado) {
      Swal.fire({ icon: 'error', title: 'Error de Lógica', text: 'Para un decremento, el Nuevo Monto Solicitado debe ser menor que el Monto Aprobado Actual.' });
      return;
    }
    if (nuevoMontoSolicitado.value < 0) {
      Swal.fire({ icon: 'error', title: 'Error de Lógica', text: 'El Nuevo Monto Solicitado no puede ser negativo para un decremento.' });
      return;
    }
  } else if (tipoModificacion.value === 'Cierre') {
    // Esta validación ya se hizo al inicio del `manejarEnvio` para 'Cierre'
    // pero la mantengo aquí para redundancia o si la estructura de validaciones cambia.
    if (nuevoMontoSolicitado.value !== 0) {
      Swal.fire({ icon: 'error', title: 'Error de Lógica', text: 'Para un cierre, el Nuevo Monto Solicitado debe ser 0.' });
      return;
    }
  }

  // --- Construir el contenido HTML para el modal de resumen ---
  let resumenHtml = `
    <p><strong>Fondo a Modificar:</strong> ${fondoParaEditar.value.codigo_fondo}</p>
    <p><strong>Monto Aprobado Actual:</strong> S/. ${fondoParaEditar.value.monto_aprobado.toFixed(2)}</p>
    <p><strong>Tipo de Modificación:</strong> ${tipoModificacion.value}</p>
  `;

  if (tipoModificacion.value !== 'Cierre') {
    resumenHtml += `<p><strong>Nuevo Monto Solicitado:</strong> S/. ${nuevoMontoSolicitado.value.toFixed(2)}</p>`;

    // Condicionalmente mostrar el Monto Sugerido si hay gastos detallados
    if (tipoModificacion.value === 'Incremento' && montoSugerido.value !== null && totalGastosDetallados.value > 0) {
      resumenHtml += `<p class="text-sm text-gray-600"><em>(Sugerido por gastos: S/. ${montoSugerido.value})</em></p>`;
    }

    if (gastosDetallados.value.length > 0) {
      resumenHtml += `<br><h4>Gastos Detallados:</h4><ul>`;
      gastosDetallados.value.forEach(gasto => {
        resumenHtml += `<li><strong>${gasto.descripcion_gasto}:</strong> S/. ${gasto.monto_estimado.toFixed(2)}</li>`;
      });
      resumenHtml += `</ul><p><strong>Total Gastos Detallados:</strong> S/. ${totalGastosDetallados.value.toFixed(2)}</p>`;
    }
  } else { // Si es Cierre
    resumenHtml += `<p><strong>Monto Solicitado (Cierre):</strong> S/. 0.00</p>`;
  }

  resumenHtml += `
    <p><strong>Motivo:</strong> ${motivo.value}</p>
    <p><strong>Prioridad:</strong> ${prioridad.value}</p>
  `;

  // Mostrar modal de confirmación
  Swal.fire({
    title: '¿Confirmar Solicitud de Modificación?',
    html: resumenHtml,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Sí, enviar',
    cancelButtonText: 'Cancelar',
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        // Objeto payload base
        const payload = {
          id_solicitud_original: fondoParaEditar.value.id_solicitud_apertura, // ID de la solicitud de apertura original
          tipo_solicitud: tipoModificacion.value,
          motivo_detalle: motivo.value,
          monto_solicitado: nuevoMontoSolicitado.value, // Este es el NUEVO MONTO TOTAL deseado
          prioridad: prioridad.value,
          id_area: fondoParaEditar.value.id_area, // Usar el área del fondo original
        };

        // Condicionalmente añadir gastos_proyectados SOLO si no es Cierre
        if (tipoModificacion.value !== 'Cierre') {
          payload.gastos_proyectados = gastosDetallados.value;
        }
        // else: Si es 'Cierre', el campo 'gastos_proyectados' simplemente no se añade al payload,
        // lo que es el comportamiento deseado para que el backend no lo valide.


        const response = await api.post('/solicitudes-fondo', payload);
        const codigoSolicitudGenerada = response.data.codigo_solicitud || 'N/A';
        Swal.fire({
          icon: 'success',
          title: '¡Solicitud Enviada!',
          text: response.data.message,
          html: `¡Solicitud registrada y enviada al jefe de administración!<br>Código de solicitud: <strong>${codigoSolicitudGenerada}</strong>`,
          confirmButtonText: 'Aceptar'
        });

        // Limpiar formulario y volver a la lista
        volverALaListaInterna();

      } catch (error) {
        console.error('Error al enviar la solicitud de modificación:', error);
        let errorMessage = 'Ocurrió un error al enviar la solicitud de modificación.';
        if (error.response && error.response.data && error.response.data.errors) {
          errorMessage = Object.values(error.response.data.errors).flat().join('<br>');
        } else if (error.response && error.response.data && error.response.data.message) {
          errorMessage = error.response.data.message;
        }
        Swal.fire({
          icon: 'error',
          title: 'Error al Enviar',
          html: errorMessage,
        });
      }
    }
  });
};

// --- Watchers para filtros ---
// Watcher para el filtro de código de fondo (con debounce)
watch(() => filtro.value.codigo_fondo, () => {
  triggerSearchWithDebounce();
});

// Watcher para el filtro de fecha de apertura (con debounce)
watch(() => filtro.value.fecha_apertura, () => {
  triggerSearchWithDebounce();
});

// Watcher para el filtro de área (sin debounce, ya que es un select)
watch(() => filtro.value.area_id, () => {
  obtenerFondosExistentes(); // Llama directamente a la API
});


// --- Ciclo de Vida ---
onMounted(() => {
  obtenerUsuarioAutenticado();
  obtenerFondosExistentes(); // Carga inicial de fondos
  obtenerAreas(); // Carga las áreas para el filtro
});
</script>

<style scoped>
/* Transiciones para el cambio de vista */
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
  padding-right: 2.5rem;
  /* Espacio para el spinner */
}
</style>
