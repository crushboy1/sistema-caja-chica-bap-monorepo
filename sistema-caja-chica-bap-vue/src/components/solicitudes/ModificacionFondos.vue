<template>
  <div class="p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-3xl font-bold text-gray-800 mb-6">Modificación de Fondos de Caja Chica</h2>

    <div>
      <Transition name="fade-slide" mode="out-in">
        <!-- VISTA LISTA - Mejorada con mejor organización -->
        <div v-if="vistaActual === 'lista'" key="mod-list-view">
          <div class="mb-6">
            <h3 class="text-2xl font-semibold text-gray-800 mb-2">Fondos Disponibles para Modificar</h3>
            <p class="text-gray-600">Selecciona un fondo activo para realizar modificaciones</p>
          </div>

          <!-- Filtros de Búsqueda - Reorganizados -->
          <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-6 rounded-xl shadow-inner mb-6 border border-gray-200">
            <div class="flex items-center mb-4">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
              </svg>
              <h4 class="text-lg font-semibold text-gray-700">Filtros de Búsqueda</h4>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <!-- Filtro Código de Fondo -->
              <div class="relative">
                <label for="filter_codigo_fondo" class="block text-sm font-medium text-gray-700 mb-2">
                  Código de Fondo
                </label>
                <input 
                  type="text" 
                  id="filter_codigo_fondo" 
                  v-model="filtro.codigo_fondo"
                  class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-2 focus:ring-verde-bap focus:ring-opacity-50 transition-all"
                  placeholder="Ej: FNRO-00001" 
                />
                <div v-if="buscandoFondos && filtro.codigo_fondo.length > 0" class="absolute right-3 top-11 text-verde-bap">
                  <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </div>
              </div>

              <!-- Filtro Área del Fondo -->
              <div v-if="usuarioActual && (usuarioActual.role.name === 'jefe_administracion' || usuarioActual.role.name === 'super_admin')">
                <label for="filter_area" class="block text-sm font-medium text-gray-700 mb-2">Área del Fondo</label>
                <select 
                  id="filter_area" 
                  v-model="filtro.area_id"
                  class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-2 focus:ring-verde-bap focus:ring-opacity-50 transition-all"
                >
                  <option value="">Todas las Áreas</option>
                  <option v-for="area in areasDisponibles" :key="area.id" :value="area.id">{{ area.name }}</option>
                </select>
              </div>

              <!-- Filtro Fecha de Inicio -->
              <div>
                <label for="filter_fecha_inicio" class="block text-sm font-medium text-gray-700 mb-2">Fecha Inicio</label>
                <input 
                  type="date" 
                  id="filter_fecha_inicio" 
                  v-model="filtro.fecha_inicio"
                  class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-2 focus:ring-verde-bap focus:ring-opacity-50 transition-all"
                />
              </div>

              <!-- Filtro Fecha de Fin -->
              <div>
                <label for="filter_fecha_fin" class="block text-sm font-medium text-gray-700 mb-2">Fecha Fin</label>
                <input 
                  type="date" 
                  id="filter_fecha_fin" 
                  v-model="filtro.fecha_fin"
                  class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-2 focus:ring-verde-bap focus:ring-opacity-50 transition-all"
                />
              </div>
            </div>

            <!-- Indicador de búsqueda -->
            <div v-if="buscandoFondos" class="mt-4 text-sm text-verde-bap flex items-center justify-center">
              <svg class="animate-spin mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
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
          <div v-else-if="fondosExistentes.length === 0" class="text-center text-gray-500 py-8">
            No hay fondos de caja chica activos que coincidan con los criterios de búsqueda.
          </div>

          <div v-else class="overflow-x-auto shadow-lg rounded-lg">
            <table class="min-w-full bg-white border border-gray-200 rounded-lg">
              <thead>
                <tr class="bg-gray-100 text-gray-700 uppercase text-sm leading-normal">
                  <th class="py-3 px-6 text-center">Código de Fondo</th>
                  <th class="py-3 px-6 text-center">Responsable</th>
                  <th class="py-3 px-6 text-center">Área</th>
                  <th class="py-3 px-6 text-center">Estado del Fondo</th>
                  <th class="py-3 px-6 text-center">Monto Actual</th>
                  <th class="py-3 px-6 text-center">Fecha de Apertura</th>
                  <th class="py-3 px-6 text-center">Acciones</th>
                </tr>
              </thead>
              <tbody class="text-gray-600 text-sm font-light">
                <tr v-for="fondo in fondosFormateados" :key="fondo.id_fondo"
                  class="border-b border-gray-200 hover:bg-gray-50">
                  <td class="py-3 px-6 text-center whitespace-nowrap">{{ fondo.codigo_fondo }}</td>
                  <td class="py-3 px-6 text-center">{{ fondo.responsable?.name }} {{ fondo.responsable?.last_name }}
                  </td>
                  <td class="py-3 px-6 text-center">{{ fondo.area?.name }}</td>
                  <td class="py-3 px-6 text-center">
                    <span :class="{
                      'bg-green-200 text-green-600': fondo.estado === 'Activo',
                      'bg-red-200 text-red-600': fondo.estado === 'Cerrado'
                    }" class="py-1 px-3 rounded-full text-xs font-semibold">
                      {{ fondo.estado }}
                    </span>
                  </td>
                  <td class="py-3 px-6 text-center">S/. {{ fondo.monto_aprobado.toFixed(2) }}</td>
                  <td class="py-3 px-6 text-center">{{ fondo.fecha_apertura_formateada }}</td>
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

        <!-- VISTA FORMULARIO -->
        <div v-else-if="vistaActual === 'formulario' && fondoParaEditar" key="mod-form-view">

          <!-- Encabezado del Formulario -->
          <div class="bg-gradient-to-r from-verde-bap to-emerald-600 text-white p-6 rounded-xl mb-8 shadow-lg">
            <h3 class="text-2xl font-bold mb-2">Modificar Fondo de Caja Chica</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
              <div>
                <span class="font-medium text-sm">Código:</span> {{ fondoParaEditar.codigo_fondo }}
              </div>
              <div>
                <span class="font-medium text-sm">Monto Actual:</span> S/. {{ fondoParaEditar.monto_aprobado.toFixed(2) }}
              </div>
              <div>
                <span class="font-medium text-sm">Estado:</span> {{ fondoParaEditar.estado }}
              </div>
            </div>
          </div>

          <form @submit.prevent="manejarEnvio" class="space-y-8">

            <!-- SECCIÓN 1: Información del Fondo (Solo Lectura) -->
            <div class="bg-gray-50 p-6 rounded-xl border-l-4 border-gray-400">
              <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-600" fill="none"
                  viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Información del Fondo Actual
              </h4>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Responsable del Fondo</label>
                  <div class="p-3 bg-white rounded-lg border border-gray-200 text-gray-800">
                    {{ (fondoParaEditar?.responsable?.name || '') + ' ' + (fondoParaEditar?.responsable?.last_name ||
                    '') }}
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Cargo</label>
                  <div class="p-3 bg-white rounded-lg border border-gray-200 text-gray-800">
                    {{ fondoParaEditar?.responsable?.cargo || 'No especificado' }}
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Área</label>
                  <div class="p-3 bg-white rounded-lg border border-gray-200 text-gray-800">
                    {{ fondoParaEditar?.area?.name || 'No especificado' }}
                  </div>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Fecha de Solicitud</label>
                  <div class="p-3 bg-white rounded-lg border border-gray-200 text-gray-800">
                    {{ new Date().toLocaleDateString() }}
                  </div>
                </div>
              </div>
            </div>

            <!-- SECCIÓN 2: Tipo de Modificación -->
            <div class="bg-white p-6 rounded-xl border-l-4 border-blue-500 shadow-sm">
              <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-blue-500" fill="none"
                  viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Tipo de Modificación Solicitada
              </h4>

              <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label for="modification_type" class="block text-sm font-medium text-gray-700 mb-2">
                    Tipo de Modificación <span class="text-red-500">*</span>
                  </label>
                  <select id="modification_type" v-model="formData.tipo_solicitud"
                    class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-2 focus:ring-verde-bap focus:ring-opacity-50 transition-all"
                    required>
                    <option value="">Selecciona el tipo</option>
                    <option value="Incremento">Incremento de Fondos</option>
                    <option value="Decremento">Decremento de Fondos</option>
                    <option value="Cierre">Cierre de Fondo</option>
                  </select>
                </div>
                <div>
                  <label for="prioridad" class="block text-sm font-medium text-gray-700 mb-2">
                    Prioridad <span class="text-red-500">*</span>
                  </label>
                  <select id="prioridad" v-model="formData.prioridad"
                    class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-2 focus:ring-verde-bap focus:ring-opacity-50 transition-all"
                    required>
                    <option value="">Selecciona la prioridad</option>
                    <option v-for="opcion in opcionesPrioridad" :key="opcion.value" :value="opcion.value">
                      {{ opcion.text }}
                    </option>
                  </select>
                </div>
              </div>

              <div class="mt-6">
                <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">
                  Motivo de la {{ formData.tipo_solicitud || 'Modificación' }}
                  <span class="text-red-500">*</span>
                </label>
                <textarea id="reason" v-model="formData.motivo_detalle" rows="4"
                  class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-2 focus:ring-verde-bap focus:ring-opacity-50 resize-none transition-all"
                  placeholder="Describe detalladamente el motivo de la modificación..." required></textarea>
              </div>

              <!-- Nuevo Monto Calculado -->
              <div v-if="!isCierre"
                class="mt-6 p-4 bg-gradient-to-r from-blue-50 to-emerald-50 rounded-lg border border-blue-200">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Nuevo Monto Solicitado
                  <span class="text-gray-500 text-xs">(Calculado automáticamente)</span>
                </label>
                <div class="text-2xl font-bold text-blue-600">
                  S/. {{ nuevoMontoSolicitado.toFixed(2) }}
                </div>
              </div>
            </div>

            <!-- SECCIÓN 3: Gastos Proyectados (Solo si NO es cierre) -->
            <div v-if="!isCierre" class="bg-white p-6 rounded-xl border-l-4 border-verde-bap shadow-sm">
              <div class="flex justify-between items-center mb-4">
                <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-verde-bap" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                  </svg>
                  Gastos Proyectados para la Modificación
                </h4>
                <button type="button" @click="agregarGastoProyectado"
                  class="bg-verde-bap hover:bg-verde-bap-hover text-white font-semibold py-2 px-4 rounded-lg transition-all transform hover:scale-105 shadow-lg flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                      d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                      clip-rule="evenodd" />
                  </svg>
                  Agregar Gasto
                </button>
              </div>

              <div v-if="!formData.gastos_proyectados || formData.gastos_proyectados.length === 0"
                class="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-8 w-8 text-gray-400 mb-3" fill="none"
                  viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <p class="text-gray-500">No hay gastos proyectados agregados</p>
                <p class="text-gray-400 text-sm mt-1">Haz clic en "Agregar Gasto" para empezar</p>
              </div>

              <div v-else class="space-y-4">
                <div v-for="(gasto, index) in formData.gastos_proyectados" :key="'gasto-' + index"
                  class="bg-gray-50 p-4 rounded-lg border border-gray-200 hover:shadow-md transition-shadow">
                  <div class="flex justify-between items-start mb-3">
                    <span class="bg-verde-bap-light text-verde-bap text-xs font-semibold px-2 py-1 rounded">
                      Gasto #{{ index + 1 }}
                    </span>
                    <button v-if="formData.gastos_proyectados.length > 1" type="button"
                      @click="removerGastoProyectado(index)"
                      class="text-red-500 hover:text-red-700 hover:bg-red-50 p-1 rounded transition-colors"
                      aria-label="Eliminar gasto">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
                    </button>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label :for="'gasto_proyectado_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo de Gasto <span class="text-red-500">*</span>
                      </label>
                      <select :id="'gasto_proyectado_' + index" v-model="gasto.gasto_proyectado_id"
                        class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-2 focus:ring-verde-bap focus:ring-opacity-50 transition-all"
                        required>
                        <option value="">Selecciona un tipo de gasto</option>
                        <option v-for="gastoProyectado in gastosProyectadosCatalogo"
                          :key="gastoProyectado.id_gasto_proyectado" :value="gastoProyectado.id_gasto_proyectado"
                          :disabled="esOpcionDeshabilitada(gastoProyectado.id_gasto_proyectado, gasto.gasto_proyectado_id)">
                          {{ gastoProyectado.descripcion }}
                        </option>
                      </select>
                    </div>
                    <div>
                      <label :for="'monto_estimado_' + index" class="block text-sm font-medium text-gray-700 mb-2">
                        Monto Estimado (S/.) <span class="text-red-500">*</span>
                      </label>
                      <input type="number" :id="'monto_estimado_' + index" v-model.number="gasto.monto_estimado"
                        step="0.01" min="0"
                        class="w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-2 focus:ring-verde-bap focus:ring-opacity-50 transition-all"
                        placeholder="0.00" required />
                    </div>
                  </div>
                </div>

                <!-- Total de Gastos -->
                <div class="mt-6 p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-lg border border-green-200">
                  <div class="flex justify-between items-center">
                    <span class="text-lg font-medium text-gray-700">Total de Gastos Proyectados:</span>
                    <span class="text-2xl font-bold text-verde-bap">S/. {{ nuevoMontoSolicitado.toFixed(2) }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- SECCIÓN 4: Gastos Proyectados Originales (Solo Lectura) -->
            <div
              v-if="fondoParaEditar.solicitud_apertura && fondoParaEditar.solicitud_apertura.gastos_proyectados && fondoParaEditar.solicitud_apertura.gastos_proyectados.length > 0"
              class="bg-gray-50 p-6 rounded-xl border-l-4 border-gray-300 shadow-sm">
              <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-gray-600" fill="none"
                  viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Gastos Proyectados Originales
                <span class="ml-2 text-sm text-gray-500 bg-gray-200 px-2 py-1 rounded-full">Solo Lectura</span>
              </h4>

              <div class="space-y-3">
                <div v-for="(gastoOriginal, index) in fondoParaEditar.solicitud_apertura.gastos_proyectados"
                  :key="'original-' + index" class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-600 mb-1">Descripción del Gasto</label>
                      <div class="p-3 bg-gray-50 rounded-lg border text-gray-800">
                        {{ gastoOriginal.descripcion }}
                      </div>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-600 mb-1">Monto Estimado</label>
                      <div class="p-3 bg-gray-50 rounded-lg border text-gray-800 font-semibold">
                        S/. {{ parseFloat(gastoOriginal.pivot.monto_estimado).toFixed(2) }}
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Total de gastos originales -->
                <div class="bg-gray-100 p-4 rounded-lg border border-gray-300">
                  <div class="flex justify-between items-center">
                    <span class="text-lg font-medium text-gray-700">Total Gastos Originales:</span>
                    <span class="text-xl font-bold text-gray-800">
                      S/. {{fondoParaEditar.solicitud_apertura.gastos_proyectados.reduce((total, gasto) => total +
                        parseFloat(gasto.pivot.monto_estimado), 0).toFixed(2) }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- SECCIÓN 5: Resumen y Acciones -->
            <div class="bg-white p-6 rounded-xl border-l-4 border-verde-bap shadow-lg">
              <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-verde-bap" fill="none"
                  viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Resumen de la Modificación
              </h4>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                  <div class="text-sm text-blue-600 font-medium">Monto Actual</div>
                  <div class="text-xl font-bold text-blue-800">S/. {{ fondoParaEditar.monto_aprobado.toFixed(2) }}</div>
                </div>
                <div v-if="!isCierre" :class="{
                  'bg-green-50 border-green-200': formData.tipo_solicitud === 'Incremento',
                  'bg-red-50 border-red-200': formData.tipo_solicitud === 'Decremento',
                  'bg-gray-50 border-gray-200': !formData.tipo_solicitud
                }" class="p-4 rounded-lg border">
                  <div class="text-sm font-medium" :class="{
                    'text-green-600': formData.tipo_solicitud === 'Incremento',
                    'text-red-600': formData.tipo_solicitud === 'Decremento',
                    'text-gray-600': !formData.tipo_solicitud
                  }">
                    {{ formData.tipo_solicitud === 'Incremento' ? 'Incremento' :
                      formData.tipo_solicitud === 'Decremento' ? 'Decremento' : 'Modificación' }}
                  </div>
                  <div class="text-xl font-bold" :class="{
                    'text-green-800': formData.tipo_solicitud === 'Incremento',
                    'text-red-800': formData.tipo_solicitud === 'Decremento',
                    'text-gray-800': !formData.tipo_solicitud
                  }">
                    {{ formData.tipo_solicitud === 'Incremento' ? '+' :
                      formData.tipo_solicitud === 'Decremento' ? '-' : '' }}S/. {{ Math.abs(nuevoMontoSolicitado -
                      fondoParaEditar.monto_aprobado).toFixed(2) }}
                  </div>
                </div>
                <div v-if="!isCierre" class="bg-verde-bap bg-opacity-10 p-4 rounded-lg border border-verde-bap">
                  <div class="text-sm text-verde-bap font-medium">Nuevo Monto</div>
                  <div class="text-xl font-bold text-verde-bap">S/. {{ nuevoMontoSolicitado.toFixed(2) }}</div>
                </div>
                <div v-if="isCierre" class="bg-red-50 p-4 rounded-lg border border-red-200 md:col-span-2">
                  <div class="text-sm text-red-600 font-medium">Estado Final</div>
                  <div class="text-xl font-bold text-red-800">Fondo Cerrado</div>
                </div>
              </div>

              <!-- Botones de Acción -->
              <div class="flex flex-col sm:flex-row gap-4 justify-end">
                <button type="button" @click="volverALaListaInterna"
                  class="order-2 sm:order-1 bg-gray-500 hover:bg-gray-600 text-white font-semibold py-3 px-8 rounded-lg transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 inline" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 16l-4-4m0 0l4-4m-4 4h18" />
                  </svg>
                  Volver a la Lista
                </button>
                <button type="submit"
                  class="order-1 sm:order-2 bg-verde-bap hover:bg-emerald-600 text-white font-bold py-3 px-8 rounded-lg transition-all shadow-lg hover:shadow-xl transform hover:scale-105">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2 inline" fill="none" viewBox="0 0 24 24"
                    stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                  </svg>
                  Enviar Solicitud de Modificación
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- Estado de error o sin fondo seleccionado -->
        <div v-else class="text-center py-12">
          <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none"
            viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <h3 class="text-lg font-medium text-gray-900 mb-2">No hay fondo seleccionado</h3>
          <p class="text-gray-500">Selecciona un fondo de la lista para continuar con la modificación</p>
        </div>
      </Transition>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { cloneDeep } from 'lodash-es';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
const formatearFechaSinHora = (fechaString) => {
  if (!fechaString) return '';

  try {
    // Para fechas que vienen como "2025-07-04" sin hora
    const [año, mes, dia] = fechaString.split('-');
    const fechaLocal = new Date(parseInt(año), parseInt(mes) - 1, parseInt(dia));

    return fechaLocal.toLocaleDateString('es-PE', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  } catch (error) {
    console.error('Error al formatear fecha sin hora:', error);
    return '';
  }
};
// ANOTACIÓN: Se definen las props que el componente necesita de su padre.
const props = defineProps({
  usuarioActual: {
    type: Object,
    required: true
  },
  gastosProyectadosCatalogo: {
    type: Array,
    required: true
  },
});

const emit = defineEmits(['close', 'solicitudEnviada']);

// --- ESTADO DEL COMPONENTE ---
const vistaActual = ref('lista'); // 'lista' o 'formulario'
const cargandoFondos = ref(true);
const buscandoFondos = ref(false);
const fondosExistentes = ref([]);
const areasDisponibles = ref([]);

const filtro = ref({
  codigo_fondo: '',
  fecha_inicio: '',
  fecha_fin: '',
  area_id: '',
});

// --- ESTADO DEL FORMULARIO ---
const fondoParaEditar = ref(null);

// CORRECCIÓN: Inicializar formData con estructura completa
const formData = ref({
  id_solicitud_original: null,
  tipo_solicitud: 'Incremento',
  motivo_detalle: '',
  prioridad: 'Media',
  gastos_proyectados: []
});

// --- PROPIEDADES COMPUTADAS ---
const fondosFormateados = computed(() => {
  if (!fondosExistentes.value || !Array.isArray(fondosExistentes.value)) {
    return [];
  }

  return fondosExistentes.value.map(fondo => ({
    ...fondo,
    fecha_apertura_formateada: formatearFechaSinHora(fondo.fecha_apertura),
    created_at_formateado: new Date(fondo.created_at).toLocaleDateString('es-PE')
  }));
});
// CORRECCIÓN: Agregar guardias de seguridad
const isCierre = computed(() => {
  return formData.value?.tipo_solicitud === 'Cierre';
});

// CORRECCIÓN: Verificar que formData y gastos_proyectados existan
const nuevoMontoSolicitado = computed(() => {
  if (isCierre.value) {
    return 0;
  }
  if (!formData.value?.gastos_proyectados || !Array.isArray(formData.value.gastos_proyectados)) {
    return 0;
  }
  return formData.value.gastos_proyectados.reduce((sum, item) => {
    return sum + (parseFloat(item?.monto_estimado) || 0);
  }, 0);
});

// Opciones para el dropdown de prioridad
const opcionesPrioridad = ref([
  { value: 'Urgente', text: 'Urgente - Atención Inmediata' },
  { value: 'Alta', text: 'Alta - Atención en 24-48 horas' },
  { value: 'Media', text: 'Media - Atención en 3-5 días' },
  { value: 'Baja', text: 'Baja - Atención según disponibilidad' }
]);

// CORRECCIÓN: Agregar guardias de seguridad
const idsGastosSeleccionados = computed(() => {
  if (!formData.value?.gastos_proyectados || !Array.isArray(formData.value.gastos_proyectados)) {
    return [];
  }
  return formData.value.gastos_proyectados
    .map(g => g?.gasto_proyectado_id)
    .filter(id => id !== null && id !== undefined);
});

const esOpcionDeshabilitada = (gastoId, gastoIdActualFila) => {
  return idsGastosSeleccionados.value.includes(gastoId) && gastoId !== gastoIdActualFila;
};

const agregarGastoProyectado = () => {
  if (idsGastosSeleccionados.value.length >= props.gastosProyectadosCatalogo.length) {
    Swal.fire('Límite alcanzado', 'Ya has seleccionado todos los tipos de gastos disponibles.', 'info');
    return;
  }

  // CORRECCIÓN: Verificar que gastos_proyectados sea un array
  if (!formData.value.gastos_proyectados) {
    formData.value.gastos_proyectados = [];
  }

  formData.value.gastos_proyectados.push({
    gasto_proyectado_id: null,
    monto_estimado: null
  });
};

const removerGastoProyectado = (index) => {
  if (formData.value?.gastos_proyectados && Array.isArray(formData.value.gastos_proyectados) && formData.value.gastos_proyectados.length > 0) {
    formData.value.gastos_proyectados.splice(index, 1);
  }
};

// --- Funciones de Carga y Navegación ---
const obtenerFondosExistentes = async () => {
  if (!buscandoFondos.value) cargandoFondos.value = true;
  try {
    const params = { estado: 'Activo', ...filtro.value };
    if (props.usuarioActual.role.name !== 'jefe_administracion' && props.usuarioActual.role.name !== 'super_admin' && props.usuarioActual.role.name !== 'gerente_general') {
      params.id_responsable = props.usuarioActual.id;
    }
    const response = await api.get('/v1/fondos-efectivo', { params });
    fondosExistentes.value = response.data.fondos.map(fondo => ({
      ...fondo,
      monto_aprobado: parseFloat(fondo.monto_aprobado)
    }));
  } catch (error) {
    console.error('Error al obtener fondos existentes:', error);
    Swal.fire('Error', 'No se pudieron cargar los fondos activos.', 'error');
  } finally {
    cargandoFondos.value = false;
    buscandoFondos.value = false;
  }
};

const obtenerAreas = async () => {
  try {
    const response = await api.get('/v1/areas');
    areasDisponibles.value = response.data.areas;
  } catch (error) {
    console.error('Error al obtener áreas:', error);
  }
};

// CORRECCIÓN: Función principal con mejores guardias de seguridad
const seleccionarFondoParaEditar = (fondo) => {
  try {
    fondoParaEditar.value = fondo;

    // CORRECCIÓN: Verificar que el fondo tenga la estructura esperada
    if (!fondo) {
      throw new Error('Fondo no válido');
    }

    // Verificar que exista solicitud_apertura
    if (!fondo.solicitud_apertura) {
      console.warn('El fondo no tiene solicitud_apertura:', fondo);
      // Inicializar con valores por defecto
      formData.value = {
        id_solicitud_original: fondo.id || null,
        tipo_solicitud: 'Incremento',
        motivo_detalle: '',
        prioridad: 'Media',
        gastos_proyectados: []
      };
    } else {
      // Procesar gastos proyectados si existen
      let gastosProyectados = [];
      if (fondo.solicitud_apertura.gastos_proyectados && Array.isArray(fondo.solicitud_apertura.gastos_proyectados)) {
        gastosProyectados = cloneDeep(fondo.solicitud_apertura.gastos_proyectados.map(g => ({
          gasto_proyectado_id: g?.id_gasto_proyectado || null,
          monto_estimado: parseFloat(g?.pivot?.monto_estimado || 0)
        })));
      }

      formData.value = {
        id_solicitud_original: fondo.solicitud_apertura.id || null,
        tipo_solicitud: 'Incremento',
        motivo_detalle: '',
        prioridad: 'Media',
        gastos_proyectados: gastosProyectados
      };
    }

    vistaActual.value = 'formulario';

  } catch (error) {
    console.error('Error al seleccionar fondo para editar:', error);
    Swal.fire('Error', 'No se pudo cargar la información del fondo seleccionado.', 'error');
  }
};

const volverALaListaInterna = () => {
  vistaActual.value = 'lista';
  fondoParaEditar.value = null;
  // CORRECCIÓN: Reinicializar formData
  formData.value = {
    id_solicitud_original: null,
    tipo_solicitud: 'Incremento',
    motivo_detalle: '',
    prioridad: 'Media',
    gastos_proyectados: []
  };
  obtenerFondosExistentes();
};

const cerrarComponente = () => emit('close');

// --- Lógica de Envío del Formulario ---
const manejarEnvio = async () => {
  try {
    // CORRECCIÓN: Verificar que fondoParaEditar existe
    if (!fondoParaEditar.value) {
      Swal.fire('Error', 'No hay un fondo seleccionado para modificar.', 'error');
      return;
    }

    // --- Validaciones Estrictas de Frontend ---
    if (isCierre.value) {
      if (!formData.value.motivo_detalle || !formData.value.prioridad) {
        Swal.fire('Error de Validación', 'Para un cierre, el motivo y la prioridad son obligatorios.', 'error');
        return;
      }
    } else {
      if (!formData.value.gastos_proyectados || formData.value.gastos_proyectados.length === 0) {
        Swal.fire('Error de Validación', 'Debe haber al menos un gasto proyectado para un incremento o decremento.', 'error');
        return;
      }
      if (formData.value.gastos_proyectados.some(g => !g.gasto_proyectado_id || g.monto_estimado === null || g.monto_estimado <= 0)) {
        Swal.fire('Error de Validación', 'Todos los gastos proyectados deben tener un tipo y un monto válido y mayor a cero.', 'error');
        return;
      }
      if (formData.value.tipo_solicitud === 'Incremento' && nuevoMontoSolicitado.value <= fondoParaEditar.value.monto_aprobado) {
        Swal.fire('Error de Lógica', 'Para un incremento, el nuevo monto total debe ser mayor al monto vigente.', 'error');
        return;
      }
      if (formData.value.tipo_solicitud === 'Decremento' && nuevoMontoSolicitado.value >= fondoParaEditar.value.monto_aprobado) {
        Swal.fire('Error de Lógica', 'Para un decremento, el nuevo monto total debe ser menor al monto vigente.', 'error');
        return;
      }
    }

    // --- Construcción del Payload para la API ---
    const payload = {
      id_solicitud_original: formData.value.id_solicitud_original,
      tipo_solicitud: formData.value.tipo_solicitud,
      motivo_detalle: formData.value.motivo_detalle,
      prioridad: formData.value.prioridad,
      monto_solicitado: nuevoMontoSolicitado.value,
    };

    if (!isCierre.value) {
      payload.gastos_proyectados = formData.value.gastos_proyectados;
    }

    // --- Construcción del Resumen para Swal ---
    const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
    let resumenHtml = `
      <div style="text-align: left; padding: 0 1rem;">
          <p><strong>Fondo a Modificar:</strong> ${fondoParaEditar.value.codigo_fondo}</p>
          <p><strong>Monto Vigente:</strong> ${currencyFormatter.format(fondoParaEditar.value.monto_aprobado)}</p>
          <hr style="margin: 1rem 0;" />
          <p><strong>Tipo de Modificación:</strong> ${payload.tipo_solicitud}</p>
          <p><strong>Nuevo Monto a Solicitar:</strong> ${currencyFormatter.format(payload.monto_solicitado)}</p>
          <p><strong>Prioridad:</strong> ${payload.prioridad}</p>
          <p><strong>Motivo:</strong> ${payload.motivo_detalle}</p>
    `;

    if (!isCierre.value && formData.value.gastos_proyectados.length > 0) {
      const gastosHtml = `
        <div class="text-sm" style="max-height: 150px; overflow-y: auto; border: 1px solid #eee; padding: 10px; border-radius: 8px; margin-top: 10px;">
            <ul>
            ${formData.value.gastos_proyectados.map(gasto => {
        const desc = props.gastosProyectadosCatalogo.find(cat => cat.id_gasto_proyectado === gasto.gasto_proyectado_id)?.descripcion || 'N/A';
        return `<li><strong>${desc}:</strong> ${currencyFormatter.format(gasto.monto_estimado || 0)}</li>`;
      }).join('')}
            </ul>
        </div>`;
      resumenHtml += `<hr style="margin: 1rem 0;" /><strong>Gasto Proyectado para Sustentar:</strong>${gastosHtml}`;
    }
    resumenHtml += `</div>`;

    // --- Confirmación con Swal ---
    const { isConfirmed } = await Swal.fire({
      title: '¿Confirmar Solicitud de Modificación?',
      html: resumenHtml,
      icon: 'info',
      showCancelButton: true,
      confirmButtonText: 'Sí, Enviar Solicitud',
      cancelButtonText: 'Cancelar',
      customClass: {
        htmlContainer: 'swal-gastos-container'
      }
    });

    if (isConfirmed) {
      const response = await api.post('/v1/solicitudes', payload);
      Swal.fire('¡Éxito!', response.data.message || 'Solicitud enviada correctamente', 'success');
      volverALaListaInterna();
      emit('solicitudEnviada');
    }
  } catch (error) {
    console.error('Error en manejarEnvio:', error);
    const errorMessage = error.response?.data?.message || 'Ocurrió un error al enviar la solicitud.';
    Swal.fire('Error', errorMessage, 'error');
  }
};

// --- Ciclo de Vida y Watchers ---
onMounted(() => {
  obtenerFondosExistentes();
  if (props.usuarioActual.role.name === 'jefe_administracion' || props.usuarioActual.role.name === 'super_admin') {
    obtenerAreas();
  }
});

let debounceTimer;
watch(filtro, () => {
  clearTimeout(debounceTimer);
  buscandoFondos.value = true;
  debounceTimer = setTimeout(() => {
    obtenerFondosExistentes();
  }, 500);
}, { deep: true });

// CORRECCIÓN: Mejorar el watcher con guardias de seguridad
watch(() => formData.value?.tipo_solicitud, (newVal) => {
  if (newVal === 'Cierre') {
    formData.value.gastos_proyectados = [];
  } else {
    // Solo reinicializar si no hay gastos y hay un fondo seleccionado
    if ((!formData.value.gastos_proyectados || formData.value.gastos_proyectados.length === 0) &&
      fondoParaEditar.value?.solicitud_apertura?.gastos_proyectados) {
      formData.value.gastos_proyectados = cloneDeep(
        fondoParaEditar.value.solicitud_apertura.gastos_proyectados.map(g => ({
          gasto_proyectado_id: g?.id_gasto_proyectado || null,
          monto_estimado: parseFloat(g?.pivot?.monto_estimado || 0)
        }))
      );
    }
  }
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
