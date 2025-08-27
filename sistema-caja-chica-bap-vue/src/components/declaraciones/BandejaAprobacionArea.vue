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
        <!-- Filtro por texto (código o glosa) -->
        <div>
          <label for="filtro_texto" class="block text-sm font-medium text-gray-700 mb-1">Código/Glosa</label>
          <input type="text" id="filtro_texto" v-model="filtros.texto"
            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
            placeholder="Buscar por código o glosa...">
        </div>
        <!-- Filtro por registrador -->
        <div>
          <label for="filtro_registrador" class="block text-sm font-medium text-gray-700 mb-1">Buscar por
            Registrador</label>
          <input type="text" id="filtro_registrador" v-model="filtros.registrador_name"
            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
            placeholder="Nombre o Apellido...">
        </div>
        <!-- Filtro fecha desde -->
        <div>
          <label for="filtro_fecha_inicio" class="block text-sm font-medium text-gray-700 mb-1">Fecha Desde</label>
          <input type="date" id="filtro_fecha_inicio" v-model="filtros.fecha_inicio"
            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
        </div>
        <!-- Filtro fecha hasta -->
        <div>
          <label for="filtro_fecha_fin" class="block text-sm font-medium text-gray-700 mb-1">Fecha Hasta</label>
          <input type="date" id="filtro_fecha_fin" v-model="filtros.fecha_fin"
            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
        </div>
      </div>
      <div class="mt-4 flex justify-end">
        <!-- Botón Limpiar -->
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

    <div v-else-if="itemsPaginados.length === 0"
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
        Mostrando <strong>{{ rangoInicio }} - {{ rangoFin }}</strong> de <strong>{{ totalItems }}</strong>
        {{ totalItems === 1 ? 'elemento' : 'elementos' }}
      </div>
      <div class="overflow-x-auto shadow-lg rounded-lg">
        <table class="min-w-full bg-white border border-gray-200 rounded-lg">
          <thead class="bg-gray-100">
            <tr class="bg-gray-100 text-gray-700 uppercase text-xs leading-normal">
              <th class="py-3 px-2 text-center font-semibold"></th>
              <th class="py-3 px-2 text-center font-semibold">Tipo</th>
              <th class="py-3 px-2 text-center font-semibold">Código</th>
              <th class="py-3 px-2 text-center font-semibold">Glosa / Descripción</th>
              <th class="py-3 px-2 text-center font-semibold">Monto</th>
              <th class="py-3 px-2 text-center font-semibold w-48">Estado</th>
              <th class="py-3 px-2 text-center font-semibold">Registrador</th>
              <th class="py-3 px-2 text-center font-semibold">Fecha Registro</th>
              <th class="py-3 px-2 text-center font-semibold">Acciones</th>
            </tr>
          </thead>

          <tbody class="text-gray-600 text-sm divide-y divide-gray-200">
            <template v-for="item in itemsPaginados"
              :key="item.es_grupo ? `grupo-${item.id_dj_consolidada || ''}` : `gasto-${item.gasto?.id || ''}`">
              <!-- Fila de Grupo DJ -->
              <tr v-if="item.es_grupo"
                class="bg-gradient-to-r from-blue-50 to-indigo-50 border-l-4 border-blue-500 hover:from-blue-100 hover:to-indigo-100 transition-all duration-200">
                <td class="py-3 px-2 text-center">
                  <button @click="toggleGroup(item.id_dj_consolidada)"
                    class="p-2 rounded-full hover:bg-blue-200 transition-colors">
                    <svg class="w-5 h-5 text-blue-600 transition-transform duration-200"
                      :class="{ 'rotate-90': expandedGroups.has(item.id_dj_consolidada) }" fill="none"
                      stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                  </button>
                </td>
                <td class="py-3 px-2">
                  <div class="flex items-center space-x-2">
                    <div class="flex-shrink-0">
                      <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10">
                          </path>
                        </svg>
                      </div>
                    </div>
                    <div>
                      <div class="font-bold text-blue-800 text-sm">DJ Grupal</div>
                      <div class="text-xs text-blue-600 font-medium">{{ item.gastos?.length || 0 }} gastos</div>
                    </div>
                  </div>
                </td>
                <td class="py-3 px-2">
                  <div class="font-mono text-sm font-medium text-blue-800">DJ-{{ item.id_dj_consolidada }}</div>
                  <div class="text-xs text-blue-600">Consolidada</div>
                </td>
                <td class="py-3 px-2">
                  <div class="text-sm text-gray-700 font-medium text-center">Gastos consolidados</div>
                  <div class="text-xs text-gray-500 text-center">Múltiples conceptos de gasto</div>
                </td>
                <td class="py-3 px-2 text-center">
                  <!-- Usa la propiedad de resumen del grupo directamente -->
                  <div class="font-bold text-lg text-blue-800">{{ currencyFormatter.format(item.monto_total_grupo || 0)
                    }}</div>
                  <div class="text-xs text-gray-500">Total consolidado</div>
                </td>
                <td class="py-3 px-2 text-center">
                  <!-- Usa la propiedad de resumen del grupo directamente -->
                  <span :class="getClassesForAuditoriaBadge(item.estado_grupo)">{{ item.estado_grupo }}</span>
                </td>
                <td class="py-3 px-2 text-center">
                  <!-- Usa la propiedad de resumen del grupo directamente -->
                  <div class="text-sm font-medium text-gray-900">{{ item.registrador?.name }}</div>
                  <div class="text-xs text-gray-500">{{ item.registrador?.last_name }}</div>
                </td>
                <td class="py-3 px-2 text-center text-gray-500">
                  <!-- Usa la propiedad de resumen del grupo directamente -->
                  {{ formatDate(item.fecha_registro) }}
                </td>
                <td class="py-3 px-2 text-center">
                  <div class="flex flex-col items-center justify-center space-y-2">
                    <!-- Primera fila de botones - Se elimina el botón "Ver Detalles" y "Observar" de la fila grupal -->
                    <div class="flex space-x-1">
                      <button @click="gestionarAccion(item, 'approve')" title="Aprobar Grupo"
                        class="p-2 rounded-full bg-verde-bap-light hover:bg-verde-bap text-verde-bap-dark hover:text-white transition-all duration-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                      </button>
                    </div>
                    <!-- Segunda fila de botones - El botón "Observar" de grupo se elimina de aquí también -->
                    <div class="flex space-x-1">
                      <!-- El botón de observar para el grupo completo se ha quitado -->
                      <!-- El botón de rechazar se mantiene para el grupo completo -->
                      <button @click="gestionarAccion(item, 'reject')" title="Rechazar Grupo"
                        class="p-2 rounded-full bg-rojo-bap-light hover:bg-rojo-bap text-rojo-bap-dark hover:text-white transition-all duration-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>

              <!-- Filas de gastos individuales del grupo (solo si está expandido) -->
              <template v-if="item.es_grupo && expandedGroups.has(item.id_dj_consolidada)">
                <tr v-for="(gasto, index) in item.gastos" :key="`${item.id_dj_consolidada}-${gasto.id}`"
                  class="bg-gray-50 hover:bg-gray-100 transition-colors text-xs"
                  :class="{ 'border-b-2 border-blue-200': index === item.gastos.length - 1 }">
                  <td class="py-3 px-2 text-center border-l-4 border-blue-400">
                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center">
                      <span class="text-blue-600 text-xs font-bold">{{ index + 1 }}</span>
                    </div>
                  </td>
                  <td class="py-3 px-2 text-center text-gray-600">
                    <span class="text-xs bg-gray-200 px-2 py-1 rounded">Parte del grupo</span>
                  </td>
                  <td class="py-3 px-2 text-center text-gray-600 font-mono">{{ gasto.codigo_gasto }}</td>
                  <td class="py-3 px-2 text-center text-gray-700">{{ gasto.glosa }}</td>
                  <td class="py-3 px-2 text-center text-gray-800 font-semibold">
                    {{ currencyFormatter.format(parseFloat(gasto.monto_total || 0)) }}
                  </td>
                  <td class="py-3 px-2 text-center">
                    <span class="text-xs text-gray-500">-</span>
                  </td>
                  <td class="py-3 px-2 text-center text-gray-500">-</td>
                  <td class="py-3 px-2 text-center text-gray-500">-</td>
                  <td class="py-3 px-2 text-center">
                    <div class="flex space-x-1 justify-center">
                      <!-- Botón de Observar para gasto individual - icono más grande -->
                      <button @click="gestionarAccion(gasto, 'observe')"
                        class="p-2 rounded-full bg-orange-100 hover:bg-orange-200 text-orange-600 transition-colors"
                        title="Observar este Gasto (invalidará la DJ)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                      </button>
                      <!-- Botón de Ver Detalles para gasto individual - icono más grande -->
                      <button @click="verDetalles(gasto)"
                        class="p-2 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-600 transition-colors"
                        title="Ver Detalle Individual">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                      </button>
                    </div>
                  </td>
                </tr>
              </template>

              <!-- Fila de Gasto Individual -->
              <tr v-if="!item.es_grupo" class="hover:bg-gray-50 transition-colors">
                <td class="py-3 px-2">
                  <div class="w-8 h-8 bg-gray-200 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                      </path>
                    </svg>
                  </div>
                </td>
                <td class="py-3 px-2">
                  <div class="flex items-center space-x-2">
                    <div class="flex-shrink-0">
                      <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                          </path>
                        </svg>
                      </div>
                    </div>
                    <div>
                      <div class="font-bold text-verde-bap-dark text-sm">Individual</div>
                      <div class="text-xs text-verde-bap">Gasto único</div>
                    </div>
                  </div>
                </td>
                <td class="py-3 px-2">
                  <div class="font-mono text-sm font-medium text-verde-bap-dark">{{ item.gasto?.codigo_gasto }}</div>
                  <div v-if="item.gasto?.fondo_efectivo?.proyecto"
                    class="text-xs text-verde-bap font-semibold mt-1 p-1 bg-verde-bap-extralight rounded-md inline-block">
                    <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 4h5m-5 4h5">
                      </path>
                    </svg>
                    Proyecto
                  </div>
                  <div class="text-xs text-verde-bap">Código único</div>
                </td>
                <td class="py-3 px-2 text-center text-gray-700">{{ item.gasto?.glosa }}</td>
                <td class="py-3 px-2 text-center font-semibold text-lg text-verde-bap-dark">
                  {{ currencyFormatter.format(parseFloat(item.gasto?.monto_total || 0)) }}
                </td>
                <td class="py-3 px-2 text-center">
                  <span :class="getClassesForAuditoriaBadge(item.gasto?.estado)">{{ item.gasto?.estado }}</span>
                </td>
                <td class="py-3 px-2 text-center">
                  <div class="text-sm font-medium text-gray-900">{{ item.gasto?.registrador?.name }}</div>
                  <div class="text-xs text-gray-500">{{ item.gasto?.registrador?.last_name }}</div>
                </td>
                <td class="py-3 px-2 text-center text-gray-500">{{ formatDate(item.gasto?.created_at) }}</td>
                <td class="py-3 px-2 text-center">
                  <div class="flex flex-col items-center justify-center space-y-2">
                    <!-- Primera fila de botones -->
                    <div class="flex space-x-1">
                      <button @click="verDetalles(item.gasto)"
                        class="p-2 rounded-full bg-gray-200 hover:bg-gray-300 text-gray-700 transition-colors"
                        title="Ver Detalles">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                      </button>
                      <button @click="gestionarAccion(item, 'approve')" title="Aprobar Gasto"
                        class="p-2 rounded-full bg-verde-bap-light hover:bg-verde-bap text-verde-bap-dark hover:text-white transition-all duration-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                      </button>
                    </div>
                    <!-- Segunda fila de botones -->
                    <div class="flex space-x-1">
                      <button @click="gestionarAccion(item, 'observe')" title="Observar Gasto"
                        class="p-2 rounded-full bg-estado-advertencia-bg hover:bg-orange-500 text-estado-advertencia-text hover:text-white transition-all duration-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                      </button>
                      <button @click="gestionarAccion(item, 'reject')" title="Rechazar Gasto"
                        class="p-2 rounded-full bg-rojo-bap-light hover:bg-rojo-bap text-rojo-bap-dark hover:text-white transition-all duration-300">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
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

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import GastoDetalleModal from './modals/GastoDetalleModal.vue';
import { getClassesForAuditoriaBadge } from '@/utils/statusStyles.js';
import { debounce } from 'lodash';

// --- ESTADO DEL COMPONENTE ---
const items = ref([]); // Almacena la data cruda de la API tal como viene del backend
const cargando = ref(true);
const filtros = ref({
  texto: '', // Usamos 'texto' para el filtro de código/glosa, coherente con la plantilla
  registrador_name: '',
  fecha_inicio: '',
  fecha_fin: '',
});

// --- ESTADO DE PAGINACIÓN ---
const paginaActual = ref(1);
const registrosPorPagina = ref(10);

// --- ESTADO DE MODALES ---
const gastoSeleccionado = ref(null);
const mostrarDetalleModal = ref(false);
const expandedGroups = ref(new Set());

// --- UTILIDADES ---
const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const formatDate = (dateString) => {
  if (!dateString) return '';
  return new Date(dateString).toLocaleDateString('es-PE');
};

// --- PROPIEDADES COMPUTADAS ---
const hayFiltrosActivos = computed(() => {
  return Object.values(filtros.value).some(value => value && String(value).trim() !== '');
});

// Lógica de filtrado unificada - devuelve los ítems en su estructura original
const itemsFiltrados = computed(() => {
  let data = [...items.value]; // Copia de los ítems originales
  const textoBusqueda = filtros.value.texto.toLowerCase().trim(); // Usa filtros.texto
  const registradorBusqueda = filtros.value.registrador_name.toLowerCase().trim();

  const filteredItems = data.filter(item => {
    const esGrupo = item.es_grupo;
    let fechaItem = null;
    let registradorItem = null;

    // Determinar la fecha y el registrador para el filtrado
    if (esGrupo) {
      // Para grupos, usamos las propiedades de resumen que ya vienen del backend
      if (!item.gastos || item.gastos.length === 0) {
        return false; // Si es un grupo pero no tiene gastos, lo descartamos
      }
      fechaItem = item.fecha_registro; // Usar la propiedad de resumen
      registradorItem = item.registrador; // Usar la propiedad de resumen
    } else if (item.gasto) {
      // Para gastos individuales
      fechaItem = item.gasto.created_at;
      registradorItem = item.gasto.registrador;
    } else {
      // Si el item no tiene ni 'es_grupo' ni 'gasto', es una estructura inesperada
      return false;
    }

    const registradorFullName = registradorItem ? `${registradorItem.name || ''} ${registradorItem.last_name || ''}`.toLowerCase() : '';

    // Aplicar filtro por fecha
    const pasaFecha = (!filtros.value.fecha_inicio || (fechaItem && new Date(fechaItem) >= new Date(filtros.value.fecha_inicio))) &&
      (!filtros.value.fecha_fin || (fechaItem && new Date(fechaItem) <= new Date(filtros.value.fecha_fin)));

    // Aplicar filtro por registrador
    const pasaRegistrador = !registradorBusqueda || registradorFullName.includes(registradorBusqueda);

    // Si no pasa los filtros básicos de fecha o registrador, descartar
    if (!pasaFecha || !pasaRegistrador) {
      return false;
    }

    // Aplicar filtro por texto (código o glosa)
    if (textoBusqueda) {
      let textMatch = false;
      if (esGrupo && item.gastos) {
        // Buscar en cualquier gasto del grupo
        textMatch = item.gastos.some(g =>
          g.codigo_gasto?.toLowerCase().includes(textoBusqueda) ||
          g.glosa?.toLowerCase().includes(textoBusqueda)
        );
      } else if (!esGrupo && item.gasto) {
        // Buscar en el gasto individual
        textMatch = item.gasto.codigo_gasto?.toLowerCase().includes(textoBusqueda) ||
          item.gasto.glosa?.toLowerCase().includes(textoBusqueda);
      }
      return textMatch;
    }
    // Si no hay filtro de texto, pero pasó los otros, se incluye
    return true;
  });
  return filteredItems;
});

// Cálculos de paginación
const totalItems = computed(() => itemsFiltrados.value.length);
const totalPaginas = computed(() => Math.ceil(totalItems.value / registrosPorPagina.value));
const itemsPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * registrosPorPagina.value;
  const paginatedItems = itemsFiltrados.value.slice(inicio, inicio + registrosPorPagina.value);
  return paginatedItems;
});
const rangoInicio = computed(() => totalItems.value === 0 ? 0 : (paginaActual.value - 1) * registrosPorPagina.value + 1);
const rangoFin = computed(() => Math.min(paginaActual.value * registrosPorPagina.value, totalItems.value));
const paginasVisibles = computed(() => {
  if (totalPaginas.value <= 7) return Array.from({ length: totalPaginas.value }, (_, i) => i + 1);
  if (paginaActual.value < 5) return [1, 2, 3, 4, 5, '...', totalPaginas.value];
  if (paginaActual.value > totalPaginas.value - 4) return [1, '...', totalPaginas.value - 4, totalPaginas.value - 3, totalPaginas.value - 2, totalPaginas.value - 1, totalPaginas.value];
  return [1, '...', paginaActual.value - 1, paginaActual.value, paginaActual.value + 1, '...', totalPaginas.value];
});

// --- MÉTODOS ---
const fetchGastos = async () => {
  cargando.value = true;
  try {
    const response = await api.get('/v1/gastos/para-aprobacion', {
      params: { scope: 'aprobacion_jefe' }
    });
    items.value = response.data;

    // Ajusta la página actual si es necesario después de cargar los datos
    if (paginaActual.value > totalPaginas.value && totalPaginas.value > 0) {
      paginaActual.value = totalPaginas.value;
    }
  } catch (error) {
    console.error("Error al cargar gastos para aprobación:", error);
    Swal.fire('Error', error.response?.data?.message || 'Ocurrió un error.', 'error');
  } finally {
    cargando.value = false;
  }
};

const limpiarFiltros = () => {
  filtros.value = { texto: '', registrador_name: '', fecha_inicio: '', fecha_fin: '' }; // Resetea 'texto'
  paginaActual.value = 1;
};

const verDetalles = (item) => {
  // Pasa el 'item' completo. GastoDetalleModal debe manejar si es grupo o individual.
  gastoSeleccionado.value = item;
  mostrarDetalleModal.value = true;
};

const toggleGroup = (groupId) => {
  if (expandedGroups.value.has(groupId)) {
    expandedGroups.value.delete(groupId);
  } else {
    expandedGroups.value.add(groupId);
  }
};

const getGastoDetailsForAction = (item) => {
  let id = null;
  let codigo = 'N/A';
  let isGroup = false;

  if (item.es_grupo) { // Caso 1: Es un objeto de resumen de grupo ({es_grupo: true, ...})
    id = item.id_dj_consolidada;
    codigo = `DJ-${item.id_dj_consolidada}`;
    isGroup = true;
  } else if (item.gasto) { // Caso 2: Es un wrapper de gasto individual ({es_grupo: false, gasto: {...}})
    id = item.gasto.id;
    codigo = item.gasto.codigo_gasto;
    isGroup = false;
  } else { // Caso 3: Es un objeto de gasto directo (como los que vienen de /v1/mis-gastos o dentro de item.gastos[])
    id = item.id;
    codigo = item.codigo_gasto;
    isGroup = false; // Un gasto directo no es un "grupo"
  }

  // Asegurarse de que el ID sea un valor válido antes de retornarlo
  if (id === undefined || id === null) {
    console.error("ERROR: ID del gasto/grupo es nulo o indefinido. Item problemático:", item);
    return { id: null, codigo: 'ID_NO_VALIDO', isGroup: isGroup }; // Retornar un valor nulo para que la acción falle en el frontend
  }

  return { id, codigo, isGroup };
};

// --- Método gestionarAccion (Completo y Refactorizado) ---
const gestionarAccion = async (itemOriginal, accion) => { // Renombrado a itemOriginal para mayor claridad
  // Usamos el helper para obtener el ID, código y si es grupo de forma segura
  const { id, codigo, isGroup } = getGastoDetailsForAction(itemOriginal);

  if (id === null) { // Si getGastoDetailsForAction no pudo obtener un ID válido
    Swal.fire('Error', 'No se pudo identificar el gasto para realizar la acción. Por favor, recargue la página.', 'error');
    return;
  }

  // Definición de configuraciones base para cada tipo de acción
  const configBase = {
    approve: { title: 'Aprobar', icon: 'success', confirmButtonText: 'Sí, ¡Aprobar!', needsComment: false },
    observe: { title: 'Observar', icon: 'warning', confirmButtonText: 'Sí, ¡Observar!', needsComment: true, commentLabel: 'Motivo de la observación:' },
    reject: { title: 'Rechazar', icon: 'error', confirmButtonText: 'Sí, ¡Rechazar!', needsComment: true, commentLabel: 'Motivo del rechazo:' }
  };

  // Construye la configuración específica para la acción y el tipo de ítem
  const config = {
    ...configBase[accion],
    title: `${configBase[accion].title} ${isGroup ? 'Grupo de DJ' : 'Gasto'}`,
    text: isGroup
      ? `¿Estás seguro de ${accion.toLowerCase()} el grupo de DJ completo?`
      : `¿Estás seguro de ${accion.toLowerCase()} el gasto ${codigo}?`, // Usar 'codigo' de la función auxiliar
    endpoint: isGroup
      ? `/v1/dj-groups/${id || ''}/${accion}` // Usar 'id' de la función auxiliar
      : `/v1/gastos/${id || ''}/${accion}`, // Usar 'id' de la función auxiliar
    method: 'put' // Todas las acciones son PUT
  };

  // Lógica de negocio: No se puede observar un grupo directamente, se debe observar un gasto hijo.
  if (isGroup && accion === 'observe') { // Usar 'isGroup'
    Swal.fire('Acción no permitida', 'Para observar un grupo, debe expandirlo y observar un gasto individual específico. Esto invalidará la DJ para su corrección.', 'info');
    return;
  }

  let comentario = '';
  // Si la acción requiere comentario, mostrar el input de SweetAlert
  if (config.needsComment) {
    const { value: text } = await Swal.fire({
      title: config.title,
      input: 'textarea',
      inputLabel: config.commentLabel,
      inputPlaceholder: 'Escribe tu comentario aquí...',
      showCancelButton: true,
      confirmButtonText: config.confirmButtonText,
      cancelButtonText: 'Cancelar', // Añadir botón de cancelar
      inputValidator: (value) => !value && '¡Necesitas escribir un motivo!'
    });
    if (!text) return; // El usuario canceló o no escribió nada.
    comentario = text;
  } else {
    // Si no requiere comentario, mostrar solo el modal de confirmación
    const result = await Swal.fire({
      title: config.title,
      text: config.text,
      icon: config.icon,
      showCancelButton: true,
      confirmButtonColor: '#3085d6', // Color por defecto de confirmación
      cancelButtonColor: '#d33', // Color por defecto de cancelación
      confirmButtonText: config.confirmButtonText,
      cancelButtonText: 'Cancelar'
    });
    if (!result.isConfirmed) return; // El usuario canceló la confirmación
  }

  // Ejecutar la llamada a la API
  try {
    await api.put(config.endpoint, { comentario }); // Todas las acciones son PUT y pueden llevar comentario
    Swal.fire('¡Acción Completada!', 'La operación se realizó con éxito.', 'success');
    fetchGastos(); // Refrescar la tabla para reflejar los cambios de estado
  } catch (error) {
    console.error(`Error al ejecutar la acción ${accion}:`, error);
    Swal.fire('Error', error.response?.data?.message || 'Ocurrió un error inesperado.', 'error');
  }
};

// Métodos de paginación
const irAPagina = (pagina) => {
  if (typeof pagina === 'number') {
    paginaActual.value = pagina;
  }
};
const paginaAnterior = () => { if (paginaActual.value > 1) paginaActual.value--; };
const paginaSiguiente = () => { if (paginaActual.value < totalPaginas.value) paginaActual.value++; };

// --- WATCHERS Y LIFECYCLE ---
watch(filtros, () => {
  paginaActual.value = 1;
}, { deep: true }); // 'deep: true' para observar cambios en propiedades anidadas de 'filtros'

onMounted(() => {
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
