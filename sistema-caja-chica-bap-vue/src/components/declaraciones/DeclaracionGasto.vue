<template>
    <!-- Contenedor principal del formulario con estilos de padding y fondo. -->
    <div class="p-6 bg-gray-50 min-h-screen">
        <h2 class="text-3xl font-bold text-gray-800 mb-8 text-center animate-fade-in-down">Registrar Declaración de Gastos</h2>

        <!-- Estado de Carga Inicial -->
        <div v-if="cargandoInicial" class="text-center text-gray-500 py-10">
            <div class="animate-pulse">
                <div class="h-4 bg-gray-300 rounded w-1/4 mx-auto mb-4"></div>
                <p>Cargando información inicial...</p>
            </div>
        </div>

        <!-- Mensaje si el usuario no tiene fondos activos -->
        <div v-else-if="!fondosActivos.length"
            class="text-center bg-estado-alerta-bg border-l-4 border-amarillo-bap-dark text-estado-alerta-text p-4 rounded-lg shadow-md max-w-2xl mx-auto">
            <p class="font-bold">No se encontraron fondos activos para tu área.</p>
            <p class="mt-1">Para registrar gastos, tu área debe tener un fondo de caja chica activo.</p>
        </div>

        <!-- Formulario Principal: se muestra solo si hay fondos activos -->
        <form v-else @submit.prevent="confirmarEnvio" class="space-y-6 max-w-5xl mx-auto">

            <!-- PASO 1: SELECCIÓN DEL FONDO -->
            <div class="p-6 border border-gray-200 rounded-xl bg-white shadow-soft">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">
                    1. Selección del Fondo de Caja Chica
                </h3>
                <div>
                    <label for="fondo" class="form-label">Fondo de Caja Chica <span class="text-rojo-bap">*</span></label>
                    <select id="fondo" v-model="fondoSeleccionadoId"
                        class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap transition-colors duration-200"
                        required>
                        <option disabled value="">Selecciona un fondo</option>
                        <option v-for="fondo in fondosActivos" :key="fondo.id_fondo" :value="fondo.id_fondo">
                            {{ fondo.codigo_fondo }} (Aprobado: S/. {{ parseFloat(fondo.monto_aprobado).toFixed(2) }})
                        </option>
                    </select>
                    <transition name="fade-in">
                        <p v-if="fondoSeleccionadoId" class="text-sm text-gray-600 mt-2 p-2 rounded-lg border-l-4 border-verde-bap bg-verde-bap-extralight">
                            💰 Saldo Disponible Actual: <strong class="text-verde-bap-dark">{{ currencyFormatter.format(fondoSeleccionado?.monto_disponible || 0) }}</strong>
                        </p>
                    </transition>
                </div>
            </div>

            <!-- Estado de carga de proyecciones -->
            <transition name="fade" mode="out-in">
                <div v-if="cargandoProyecciones" class="text-center py-6">
                    <div class="animate-pulse flex items-center justify-center text-gray-500">
                        <svg class="animate-spin h-5 w-5 mr-3" viewBox="0 0 24 24"></svg>
                        Cargando proyecciones disponibles...
                    </div>
                </div>
            </transition>

            <!-- PASO 2: GASTOS A DECLARAR -->
            <div v-if="fondoSeleccionadoId && !cargandoProyecciones" class="space-y-4">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
                    2. Gastos a Declarar
                </h3>

                <!-- Lista de gastos con transiciones -->
                <transition-group name="gasto-list" tag="div" class="space-y-4">
                    <div v-for="(gasto, index) in gastosADeclarar" :key="gasto.id"
                        class="bg-white rounded-xl shadow-soft border transition-all duration-300 overflow-hidden"
                        :class="gastoActivoIndex === index ? 'ring-2 ring-verde-bap shadow-strong' : 'ring-1 ring-gray-200 hover:ring-verde-bap hover:shadow-medium'">

                        <!-- VISTA MINIMIZADA DEL GASTO -->
                        <transition name="slide-down" mode="out-in">
                            <div v-if="gastoActivoIndex !== index" @click="maximizarGasto(index)"
                                class="p-4 cursor-pointer hover:bg-gray-50 transition-colors duration-200 flex justify-between items-center">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-shrink-0">
                                        <span
                                            class="flex-shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-verde-bap text-white text-sm font-bold">{{ index + 1 }}
                                        </span>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-gray-800 font-medium">
                                            {{gasto.detalle_gasto_proyectado_id ?
                                                proyeccionesPendientes.find(p => p.id ===
                                                    gasto.detalle_gasto_proyectado_id)?.descripcion_gasto :
                                                'Gasto sin asignar'}}
                                        </p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Monto: <span class="font-semibold text-verde-bap-dark">{{
                                                currencyFormatter.format(gasto.monto_total || 0) }}</span>
                                            <span v-if="gasto.tipo_documento" class="ml-2">• {{ gasto.tipo_documento
                                                }}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <!-- Indicador de completitud -->
                                    <div class="flex items-center">
                                        <div v-if="gasto.detalle_gasto_proyectado_id && gasto.monto_total && gasto.tipo_documento && gasto.id_cuenta_contable && gasto.evidencia"
                                            class="w-3 h-3 bg-green-500 rounded-full"></div>
                                        <div v-else class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                    </div>
                                    <button type="button"
                                        class="text-verde-bap hover:text-verde-bap-dark transition-colors duration-200 text-sm font-medium px-3 py-1 rounded-md hover:bg-verde-bap hover:bg-opacity-10">
                                        Editar
                                    </button>
                                    <svg class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                        </transition>

                        <!-- VISTA EXPANDIDA (FORMULARIO) -->
                        <transition name="slide-up" mode="out-in">
                            <div v-if="gastoActivoIndex === index" class="p-6">
                                <!-- Header del gasto expandido -->
                                <div class="flex justify-between items-center mb-6">
                                    <div class="flex items-center space-x-3">
                                        <span
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-verde-bap text-white text-sm font-bold">
                                            {{ index + 1 }}
                                        </span>
                                        <h3 class="text-xl font-semibold text-gray-800">Detalle del Gasto #{{ index + 1
                                            }}</h3>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button type="button" @click="minimizarGasto(index)"
                                            class="text-gray-500 hover:text-gray-700 transition-colors duration-200 p-1">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 15l7-7 7 7"></path>
                                            </svg>
                                        </button>
                                        <button v-if="gastosADeclarar.length > 1" @click="removerGasto(index)"
                                            type="button"
                                            class="text-rojo-bap hover:text-rojo-bap-dark transition-colors duration-200 p-1 rounded-full hover:bg-red-50">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <!-- Formulario del gasto -->
                                <div class="space-y-6">
                                    <!-- ====== CATEGORÍA 1: INFORMACIÓN GENERAL ====== -->
                                    <div
                                        class="border-l-4 border-blue-400 pl-4 bg-blue-50 bg-opacity-30 p-4 rounded-r-lg">
                                        <h4 class="text-lg font-medium text-gray-800 mb-4 flex items-center">
                                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                                </path>
                                            </svg>
                                            Información General
                                        </h4>

                                        <!-- Gasto Proyectado -->
                                        <div>
                                            <label :for="'proyeccion_' + index"
                                                class="block text-sm font-medium text-gray-700 mb-2">
                                                Gasto Proyectado <span class="text-rojo-bap">*</span>
                                            </label>
                                            <select :id="'proyeccion_' + index"
                                                v-model="gasto.detalle_gasto_proyectado_id"
                                                :disabled="cargandoProyecciones"
                                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg focus:border-verde-bap focus:ring-verde-bap transition-colors duration-200"
                                                required>
                                                <option disabled value="">{{ cargandoProyecciones ? 'Cargando...' :
                                                    'Selecciona una proyección' }}</option>
                                                <option v-if="!cargandoProyecciones && !proyeccionesPendientes.length"
                                                    value="" disabled>
                                                    No hay proyecciones pendientes
                                                </option>
                                                <option v-for="p in proyeccionesDisponibles(index)" :key="p.id"
                                                    :value="p.id">
                                                    {{ p.descripcion_gasto }} (Disponible: {{
                                                        currencyFormatter.format(p.saldo_a_mostrar) }})
                                                </option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- ====== CATEGORÍA 2: DOCUMENTO DE SUSTENTO ====== -->
                                    <transition name="slide-down" mode="out-in">
                                        <div v-if="seccionesVisibles(gasto).documento"
                                            class="border-l-4 border-green-400 pl-4 bg-green-50 bg-opacity-30 p-4 rounded-r-lg">
                                            <h4 class="text-lg font-medium text-gray-800 mb-4 flex items-center">
                                                <svg class="w-5 h-5 mr-2 text-green-600" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                                                    </path>
                                                </svg>
                                                Documento de Sustento
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                                <!-- Tipo de Documento -->
                                                <div>
                                                    <label :for="'tipo_documento_' + index"
                                                        class="block text-sm font-medium text-gray-700 mb-2">
                                                        Tipo de Documento <span class="text-rojo-bap">*</span>
                                                    </label>
                                                    <select :id="'tipo_documento_' + index"
                                                        v-model="gasto.tipo_documento"
                                                        @change="onTipoDocumentoChange(gasto)"
                                                        class="mt-1 block w-full p-3 border border-gray-300 rounded-lg focus:border-verde-bap focus:ring-verde-bap transition-colors duration-200"
                                                        required>
                                                        <option disabled value="">Selecciona un tipo</option>
                                                        <option>Boleta de Venta</option>
                                                        <option>Factura</option>
                                                        <option>Recibo por Honorarios</option>
                                                        <option>Declaración Jurada</option>
                                                        <option>Otro</option>
                                                    </select>
                                                </div>

                                                <!-- Fecha del Documento -->
                                                <div>
                                                    <label :for="'fecha_documento_' + index"
                                                        class="block text-sm font-medium text-gray-700 mb-2">
                                                        Fecha del Documento <span class="text-rojo-bap">*</span>
                                                    </label>
                                                    <input type="date" :id="'fecha_documento_' + index"
                                                        v-model="gasto.fecha_documento"
                                                        class="mt-1 block w-full p-3 border border-gray-300 rounded-lg focus:border-verde-bap focus:ring-verde-bap transition-colors duration-200"
                                                        required />
                                                    <p class="text-xs text-gray-500 mt-1">Fecha de emisión del documento
                                                    </p>
                                                </div>

                                                <!-- Monto del Documento -->
                                                <div>
                                                    <label :for="'monto_total_' + index"
                                                        class="block text-sm font-medium text-gray-700 mb-2">
                                                        Monto del Documento (S/.) <span class="text-rojo-bap">*</span>
                                                    </label>
                                                    <input type="number" :id="'monto_total_' + index"
                                                        v-model.number="gasto.monto_total"
                                                        :max="getSaldoMaximoParaGasto(gasto.detalle_gasto_proyectado_id, index)"
                                                        :class="getMontoInputClasses(gasto, index)" step="0.01"
                                                        min="0.01" @input="validarMontoEnTiempoReal(gasto, index)"
                                                        placeholder="0.00" required />
                                                    <!-- Mensaje de saldo disponible -->
                                                    <transition name="fade" mode="out-in">
                                                        <div v-if="gasto.detalle_gasto_proyectado_id" class="mt-2">
                                                            <p
                                                                class="text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded">
                                                                💰 Saldo disponible: <strong>{{
                                                                    currencyFormatter.format(getSaldoMaximoParaGasto(gasto.detalle_gasto_proyectado_id,
                                                                        index)) }}</strong>
                                                            </p>
                                                            <!-- Mensaje de error para monto excedido -->
                                                            <transition name="shake">
                                                                <p v-if="getErrorMontoExcedido(gasto, index)"
                                                                    class="text-xs text-rojo-bap font-medium bg-red-50 px-2 py-1 rounded mt-1 border-l-2 border-rojo-bap">
                                                                    ⚠️ {{ getErrorMontoExcedido(gasto, index) }}
                                                                </p>
                                                            </transition>
                                                        </div>
                                                    </transition>
                                                </div>

                                                <!-- Serie del Documento -->
                                                <div>
                                                    <label :for="'serie_documento_' + index"
                                                        class="block text-sm font-medium text-gray-700 mb-2">
                                                        Serie del Documento
                                                    </label>
                                                    <input type="text" :id="'serie_documento_' + index"
                                                        v-model="gasto.serie_documento"
                                                        :disabled="gasto.tipo_documento === 'Declaración Jurada'"
                                                        class="mt-1 block w-full p-3 border border-gray-300 rounded-lg disabled:bg-gray-200 disabled:cursor-not-allowed focus:border-verde-bap focus:ring-verde-bap transition-colors duration-200"
                                                        placeholder="Ej: F001" />
                                                </div>

                                                <!-- Correlativo del Documento -->
                                                <div>
                                                    <label :for="'correlativo_documento_' + index"
                                                        class="block text-sm font-medium text-gray-700 mb-2">
                                                        Correlativo del Documento
                                                    </label>
                                                    <input type="text" :id="'correlativo_documento_' + index"
                                                        v-model="gasto.correlativo_documento"
                                                        :disabled="gasto.tipo_documento === 'Declaración Jurada'"
                                                        class="mt-1 block w-full p-3 border border-gray-300 rounded-lg disabled:bg-gray-200 disabled:cursor-not-allowed focus:border-verde-bap focus:ring-verde-bap transition-colors duration-200"
                                                        placeholder="Ej: 0012345" />
                                                </div>
                                            </div>
                                        </div>
                                    </transition>

                                    <!-- ====== CATEGORÍA 3: CLASIFICACIÓN CONTABLE ====== -->
                                    <transition name="slide-down" mode="out-in">
                                        <div v-if="seccionesVisibles(gasto).clasificacion"
                                            class="border-l-4 border-purple-400 pl-4 bg-purple-50 bg-opacity-30 p-4 rounded-r-lg">
                                            <h4 class="text-lg font-medium text-gray-800 mb-4 flex items-center">
                                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                                    </path>
                                                </svg>
                                                Clasificación Contable
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <!-- Cuenta Contable -->
                                                <div>
                                                    <label :for="'id_cuenta_contable_' + index"
                                                        class="block text-sm font-medium text-gray-700 mb-2">
                                                        Cuenta Contable <span class="text-rojo-bap">*</span>
                                                    </label>
                                                    <select :id="'id_cuenta_contable_' + index"
                                                        v-model="gasto.id_cuenta_contable"
                                                        class="mt-1 block w-full p-3 border border-gray-300 rounded-lg focus:border-verde-bap focus:ring-verde-bap transition-colors duration-200"
                                                        required>
                                                        <option disabled value="">Selecciona una cuenta</option>
                                                        <option v-for="cuenta in cuentasContables" :key="cuenta.id"
                                                            :value="cuenta.id">
                                                            {{ cuenta.codigo_cuenta }} - {{ cuenta.descripcion }}
                                                        </option>
                                                    </select>
                                                </div>

                                                <!-- Glosa para el Asiento -->
                                                <div>
                                                    <label :for="'glosa_' + index"
                                                        class="block text-sm font-medium text-gray-700 mb-2">
                                                        Glosa para el Asiento
                                                    </label>
                                                    <input type="text" :id="'glosa_' + index" v-model="gasto.glosa"
                                                        class="mt-1 block w-full p-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600 transition-colors duration-200"
                                                        placeholder="Se asigna automáticamente" readonly />
                                                </div>
                                            </div>
                                        </div>
                                    </transition>

                                    <!-- ====== CATEGORÍA 4: INFORMACIÓN ADICIONAL ====== -->
                                    <transition name="slide-down" mode="out-in">
                                        <div v-if="seccionesVisibles(gasto).adicional"
                                            class="border-l-4 border-yellow-400 pl-4 bg-yellow-50 bg-opacity-30 p-4 rounded-r-lg">
                                            <h4 class="text-lg font-medium text-gray-800 mb-4 flex items-center">
                                                <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z">
                                                    </path>
                                                </svg>
                                                Información Adicional
                                            </h4>
                                            <div class="space-y-4">
                                                <!-- Checkbox Proyecto -->
                                                <div class="flex items-center">
                                                    <input :id="'pertenece_proyecto_' + index" type="checkbox"
                                                        v-model="gasto.pertenece_proyecto"
                                                        class="h-4 w-4 text-verde-bap rounded border-gray-300 focus:ring-verde-bap transition-colors duration-200">
                                                    <label :for="'pertenece_proyecto_' + index"
                                                        class="ml-3 block text-sm text-gray-900">
                                                        ¿Pertenece a un Proyecto?
                                                    </label>
                                                </div>

                                                <!-- Comentario -->
                                                <div>
                                                    <label :for="'comentario_' + index"
                                                        class="block text-sm font-medium text-gray-700 mb-2">
                                                        Comentario Adicional
                                                    </label>
                                                    <textarea :id="'comentario_' + index" v-model="gasto.comentario"
                                                        rows="3"
                                                        class="mt-1 block w-full p-3 border border-gray-300 rounded-lg focus:border-verde-bap focus:ring-verde-bap resize-none transition-colors duration-200"
                                                        placeholder="Añade un comentario adicional si es necesario (opcional)..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </transition>

                                    <transition name="slide-down" mode="out-in">
                                        <div v-if="seccionesVisibles(gasto).evidencia"
                                            class="border-l-4 border-red-400 pl-4 bg-red-50 bg-opacity-30 p-4 rounded-r-lg">
                                            <h4 class="text-lg font-medium text-gray-800 mb-4 flex items-center">
                                                <svg class="w-5 h-5 mr-2 text-red-600" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13">
                                                    </path>
                                                </svg>
                                                Evidencia y Sustento
                                            </h4>

                                            <div class="space-y-4">
                                                <!-- Archivo de Evidencia -->
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Archivo de Evidencia <span class="text-rojo-bap">*</span>
                                                    </label>

                                                    <!-- Área de subida de archivos -->
                                                    <div
                                                        class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-verde-bap transition-colors duration-200">
                                                        <input type="file" :id="'evidencia_' + index"
                                                            @change="handleFileChange($event, index)" class="hidden"
                                                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>

                                                        <label :for="'evidencia_' + index" class="cursor-pointer block">
                                                            <!-- Icono de archivo -->
                                                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4"
                                                                stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                                <path
                                                                    d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                                                    stroke-width="2" stroke-linecap="round"
                                                                    stroke-linejoin="round" />
                                                            </svg>

                                                            <!-- Texto dinámico -->
                                                            <span class="text-lg font-medium text-gray-700">
                                                                {{ gasto.evidencia ? gasto.evidencia.name : 'Haz clic para subir archivo' }}
                                                            </span>

                                                            <!-- Información de formatos -->
                                                            <p class="text-sm text-gray-500 mt-2">
                                                                📎 PDF, JPG, PNG, DOC (máx. 10MB)
                                                            </p>
                                                        </label>
                                                    </div>

                                                    <!-- Mostrar archivo seleccionado -->
                                                    <transition name="fade" mode="out-in">
                                                        <div v-if="gasto.evidencia"
                                                            class="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                                            <div class="flex items-center justify-between">
                                                                <div class="flex items-center">
                                                                    <svg class="w-5 h-5 text-green-600 mr-2" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z">
                                                                        </path>
                                                                    </svg>
                                                                    <span class="text-sm font-medium text-green-800">{{
                                                                        gasto.evidencia.name }}</span>
                                                                    <span class="text-xs text-green-600 ml-2">({{
                                                                        formatFileSize(gasto.evidencia.size) }})</span>
                                                                </div>
                                                                <button type="button" @click="removerArchivo(index)"
                                                                    class="text-red-600 hover:text-red-800 transition-colors duration-200">
                                                                    <svg class="w-4 h-4" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M6 18L18 6M6 6l12 12"></path>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </transition>
                                                </div>

                                                <!-- Checkbox Declaración Jurada -->
                                                <div class="flex items-center">
                                                    <input type="checkbox" :id="'dj_' + index"
                                                        v-model="gasto.es_declaracion_jurada"
                                                        @change="onDeclaracionJuradaChange(gasto)"
                                                        class="h-4 w-4 text-verde-bap rounded border-gray-300 focus:ring-verde-bap transition-colors duration-200">
                                                    <label :for="'dj_' + index" class="ml-2 text-sm text-gray-900">
                                                        Este gasto se sustenta con Declaración Jurada
                                                    </label>
                                                </div>

                                                <!-- Botón para generar Declaración Jurada (solo si está marcado) -->
                                                <transition name="slide-down" mode="out-in">
                                                    <div v-if="gasto.es_declaracion_jurada" class="mt-4">
                                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                                            <div class="flex items-start">
                                                                <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5"
                                                                    fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                                                    </path>
                                                                </svg>
                                                                <div class="flex-1">
                                                                    <p class="text-sm text-blue-800 font-medium">
                                                                        Declaración Jurada</p>
                                                                    <p class="text-xs text-blue-600 mt-1">Genera la
                                                                        plantilla de declaración jurada para este gasto.
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            <div class="mt-3 text-center">
                                                                <button type="button"
                                                                    @click="generarYDescargarDJ(gasto)"
                                                                    :disabled="!gasto.monto_total || !gasto.glosa"
                                                                    class="bg-verde-bap hover:bg-verde-bap-hover text-white font-semibold py-2 px-4 rounded-lg transition-colors shadow-sm flex items-center disabled:opacity-50 disabled:cursor-not-allowed mx-auto">
                                                                    <svg class="w-4 h-4 mr-2" fill="none"
                                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4">
                                                                        </path>
                                                                    </svg>
                                                                    Generar Plantilla DJ
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </transition>
                                            </div>
                                        </div>
                                    </transition>

                                    <!-- Botón para minimizar y pasar al siguiente gasto -->
                                    <div class="flex justify-center pt-4 border-t">
                                        <button type="button" @click="minimizarYContinuar(index)"
                                            class="bg-verde-bap hover:bg-verde-bap-hover text-white font-semibold py-2 px-6 rounded-lg transition-colors shadow-md flex items-center">
                                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 15l7-7 7 7"></path>
                                            </svg>
                                            Guardar y Continuar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </transition>
                    </div>
                </transition-group>

                <!-- Botón para añadir más gastos -->
                <div class="flex justify-center pt-6">
                    <button @click="agregarGasto" type="button" :class="getClassesForActionButton('info')"
                        class="font-bold py-3 px-6 rounded-full shadow-lg transition-transform transform hover:scale-105 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Añadir Otro Gasto
                    </button>
                </div>
            </div>

            <!-- Botones de Acción Finales -->
            <div class="flex justify-end space-x-4 pt-6 mt-6 border-t border-gray-200">
                <button type="button" @click="$emit('close')"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-8 rounded-full transition-colors duration-200">
                    Cancelar
                </button>
                <button type="submit" :disabled="enviando || hayErroresValidacion"
                    :class="getClassesForActionButton('exito')"
                    class="font-bold py-3 px-8 rounded-full min-w-[200px] disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200">
                    <span v-if="enviando" class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        Enviando...
                    </span>
                    <span v-else>Guardar Declaración</span>
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { getClassesForActionButton } from '@/utils/statusStyles.js';
const emit = defineEmits(['close', 'gastoCreado']);
// --- ESTADO REACTIVO DEL COMPONENTE ---
const gastosADeclarar = ref([]);
const usuarioActual = ref(null);
const fondosActivos = ref([]);
const fondoSeleccionadoId = ref(''); // ID del fondo que el usuario selecciona
const proyeccionesPendientes = ref([]); // Lista de proyecciones para el fondo seleccionado
const cuentasContables = ref([]);
const cargandoInicial = ref(true);
const cargandoProyecciones = ref(false);
const enviando = ref(false);
const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const gastoActivoIndex = ref(0);
// --- LÓGICA DE CARGA DE DATOS ---
onMounted(async () => {
    cargandoInicial.value = true;
    await Promise.all([
        obtenerUsuarioLogueado(),
        obtenerFondosActivos(),
        obtenerCuentasContables()
    ]);
    cargandoInicial.value = false;
});
const obtenerUsuarioLogueado = async () => {
    try {
        const response = await api.get('/auth/user');
        usuarioActual.value = response.data;
        const firstName = usuarioActual.value.name || '';
        const lastName = usuarioActual.value.last_name || '';
        usuarioActual.value.fullName = `${firstName} ${lastName}`.trim();
    } catch (error) {
        console.error("Error al obtener datos del usuario:", error);
    }
};
const obtenerFondosActivos = async () => {
    try {
        const response = await api.get('/v1/fondos-activos-usuario');
        fondosActivos.value = response.data;
        // Si solo hay un fondo, se pre-selecciona automáticamente.
        //if (fondosActivos.value.length === 1) {
          //  fondoSeleccionadoId.value = fondosActivos.value[0].id_fondo;
       // }
    } catch (error) {
        console.error("Error al obtener fondos activos:", error);
        Swal.fire('Error', 'No se pudieron cargar tus fondos activos.', 'error');
    }
};
const obtenerCuentasContables = async () => {
    try {
        const response = await api.get('/v1/cuentas-contables');
        cuentasContables.value = response.data;
    } catch (error) {
        console.error("Error al obtener cuentas contables:", error);
        Swal.fire('Error', 'No se pudieron cargar las cuentas contables.', 'error');
    }
};
// --- COMPUTED PROPERTIES ---
// Determina qué secciones son visibles para un gasto específico
const seccionesVisibles = (gasto) => {
    return {
        documento: !!gasto.detalle_gasto_proyectado_id,
        clasificacion: !!gasto.tipo_documento && !!gasto.monto_total,
        adicional: !!gasto.id_cuenta_contable,
        evidencia: !!gasto.id_cuenta_contable, // Se muestra junto con clasificación
    };
};
const minimizarGasto = (index) => {
    if (gastoActivoIndex.value === index) {
        gastoActivoIndex.value = null; // Cierra la sección si se hace clic en la activa
    }
};
const minimizarYContinuar = (index) => {
    minimizarGasto(index);
    // Si hay más gastos, abrir el siguiente
    if (index + 1 < gastosADeclarar.value.length) {
        maximizarGasto(index + 1);
    }
};

const maximizarGasto = (index) => {
    gastoActivoIndex.value = index; // Abre la sección de gasto en la que se hizo clic
};
// Función para obtener proyecciones disponibles para un gasto específico
const proyeccionesDisponibles = (indexGastoActual) => {
    const proyeccionSeleccionadaActual = gastosADeclarar.value[indexGastoActual]?.detalle_gasto_proyectado_id;
    return proyeccionesPendientes.value
        .map(p => ({
            ...p,
            // Mostramos el saldo que viene de la API para la lista. La validación usará el cálculo en tiempo real.
            saldo_a_mostrar: p.saldo_restante
        }))
        .filter(p => {
            // El filtro ahora es más robusto: se muestra si es la proyección ya seleccionada en la fila actual,
            // O si el saldo real calculado (considerando otras filas del form) es mayor a cero.
            const saldoRealParaEstaFila = getSaldoMaximoParaGasto(p.id, -1); // -1 para no excluir ninguna fila
            return p.id === proyeccionSeleccionadaActual || saldoRealParaEstaFila > 0.005;
        });
};
// Propiedad computada para obtener el objeto completo del fondo seleccionado
const fondoSeleccionado = computed(() => {
    return fondosActivos.value.find(f => f.id_fondo === fondoSeleccionadoId.value);
});
// Computed para verificar si hay errores de validación
const hayErroresValidacion = computed(() => {
    return gastosADeclarar.value.some(gasto => {
        // Verificar campos obligatorios
        if (!gasto.detalle_gasto_proyectado_id || !gasto.monto_total || !gasto.tipo_documento || !gasto.id_cuenta_contable || !gasto.evidencia) {
            return true;
        }
        // Verificar si el monto excede el saldo disponible
        const saldoMaximo = getSaldoMaximoParaGasto(gasto.detalle_gasto_proyectado_id, gastosADeclarar.value.indexOf(gasto));
        if (gasto.monto_total > saldoMaximo) {
            return true;
        }
        return false;
    });
});
// --- WATCHERS ---
// Observa cambios en el fondo seleccionado para cargar sus proyecciones
watch(fondoSeleccionadoId, async (newFondoId) => {
    proyeccionesPendientes.value = [];
    gastosADeclarar.value = []; // Limpia los gastos anteriores
    if (newFondoId) {
        cargandoProyecciones.value = true;
        try {
            const response = await api.get(`/v1/fondos-efectivo/${newFondoId}/proyecciones-pendientes`);
            proyeccionesPendientes.value = response.data;
            // Solo después de cargar las proyecciones, se añade el primer gasto.
            agregarGasto();
        } catch (error) {
            console.error("Error al obtener proyecciones pendientes:", error);
            Swal.fire('Error', 'No se pudieron cargar los gastos proyectados para este fondo.', 'error');
        } finally {
            cargandoProyecciones.value = false;
        }
    }
});
// Observa cambios en los gastos para autocompletar campos
watch(gastosADeclarar, (nuevosGastos) => {
    nuevosGastos.forEach(gasto => {
        // Autocompletar glosa basada en cuenta contable
        if (gasto.id_cuenta_contable) {
            const cuenta = cuentasContables.value.find(c => c.id === gasto.id_cuenta_contable);
            if (cuenta && gasto.glosa !== cuenta.descripcion) {
                gasto.glosa = cuenta.descripcion;
            }
        }
    });
}, { deep: true });
// --- MÉTODOS DE GESTIÓN DE GASTOS ---
// Función para añadir una nueva fila de gasto
const agregarGasto = async () => {
    // Minimizamos todos los gastos existentes antes de añadir uno nuevo.
    gastoActivoIndex.value = gastosADeclarar.value.length;
    gastosADeclarar.value.push({
        id: Date.now(),
        detalle_gasto_proyectado_id: '',
        fecha_documento: '',
        fecha_registro: new Date().toISOString().slice(0, 10),
        moneda: 'PEN',
        tipo_documento: '',
        serie_documento: '',
        correlativo_documento: '',
        monto_total: null,
        id_cuenta_contable: '',
        glosa: '',
        responsable_gasto: usuarioActual.value?.fullName || '',
        pertenece_proyecto: false,
        comentario: '',
        evidencia: null,
        es_declaracion_jurada: false,
    });
    // Esperar a que el DOM se actualice y luego hacer focus
    await nextTick();
    const el = document.getElementById(`proyeccion_${gastoActivoIndex.value}`);
    if (el) el.focus();
};
// Función para remover una fila de gasto
const removerGasto = (index) => {
    if (gastosADeclarar.value.length > 1) {
        gastosADeclarar.value.splice(index, 1);
        // Si eliminamos el gasto activo, enfocamos el último
        if (gastoActivoIndex.value === index) {
            gastoActivoIndex.value = gastosADeclarar.value.length - 1;
        }
    } else {
        Swal.fire('Acción no permitida', 'Debe declarar al menos un gasto.', 'warning');
    }
};
// --- MÉTODOS DE VALIDACIÓN Y CÁLCULO ---
// Obtener el saldo máximo disponible para un gasto específico
const getSaldoMaximoParaGasto = (proyeccionId, indexGastoActual) => {
    if (!proyeccionId) return 0;
    // 1. Encuentra la proyección original en la lista que vino de la API.
    // Esta contiene el `saldo_restante` real calculado por el backend.
    const proyeccionOriginal = proyeccionesPendientes.value.find(p => p.id === proyeccionId);
    if (!proyeccionOriginal) return 0;
    // 2. Este es nuestro punto de partida: el saldo real que nos dio el backend.
    let saldoDisponible = parseFloat(proyeccionOriginal.saldo_restante) || 0;
    // 3. Ahora, solo restamos los montos de OTRAS filas de gasto que están en el formulario
    // para obtener el saldo en tiempo real para la fila actual.
    gastosADeclarar.value.forEach((gasto, index) => {
        // Se descuenta el monto de las otras filas que usen la misma proyección.
        if (index !== indexGastoActual && gasto.detalle_gasto_proyectado_id === proyeccionId) {
            saldoDisponible -= parseFloat(gasto.monto_total) || 0;
        }
    });
    return saldoDisponible;
};
// Validar monto en tiempo real
const validarMontoEnTiempoReal = (gasto, index) => {
    if (!gasto.detalle_gasto_proyectado_id || !gasto.monto_total) return;
    const saldoMaximo = getSaldoMaximoParaGasto(gasto.detalle_gasto_proyectado_id, index);
    if (parseFloat(gasto.monto_total) > saldoMaximo) {
        const proyeccion = proyeccionesPendientes.value.find(p => p.id === gasto.detalle_gasto_proyectado_id);
        Swal.fire({
            icon: 'warning',
            title: 'Monto Excede el Saldo Disponible',
            html: `
                <div class="text-left p-2 border-t border-b">
                    <p class="mb-2"><strong>Proyección:</strong> ${proyeccion?.descripcion_gasto}</p>
                    <div class="flex justify-between text-sm">
                        <span>Saldo Disponible:</span>
                        <span class="font-medium">${currencyFormatter.format(saldoMaximo)}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span>Monto Ingresado:</span>
                        <span class="font-medium">${currencyFormatter.format(gasto.monto_total)}</span>
                    </div>
                    <hr class="my-2">
                    <div class="flex justify-between text-base font-bold text-rojo-bap">
                        <span>Excede en:</span>
                        <span>${currencyFormatter.format(parseFloat(gasto.monto_total) - saldoMaximo)}</span>
                    </div>
                </div>
            `,
            confirmButtonText: 'Entendido',
            // Se usan los colores personalizados de tu configuración de Tailwind.
            confirmButtonColor: '#DB3D47', // Corresponde a 'rojo-bap'
        });
    }
};
// Obtener clases CSS para el input de monto
const getMontoInputClasses = (gasto, index) => {
    // Clases base y de foco
    const baseClasses = 'mt-1 block w-full p-3 border rounded-lg focus:ring-1';
    if (!gasto.detalle_gasto_proyectado_id || !gasto.monto_total) {
        return `${baseClasses} border-gray-300 focus:border-verde-bap focus:ring-verde-bap`;
    }
    const saldoMaximo = getSaldoMaximoParaGasto(gasto.detalle_gasto_proyectado_id, index);
    const montoExcedido = parseFloat(gasto.monto_total) > saldoMaximo;
    // Se aplican las clases de borde y foco según el estado de validación.
    const themeClasses = montoExcedido
        ? 'border-rojo-bap focus:border-rojo-bap-dark focus:ring-rojo-bap'
        : 'border-verde-bap focus:border-verde-bap-dark focus:ring-verde-bap';
    return `${baseClasses} ${themeClasses}`;
};
// Obtener mensaje de error para monto excedido
const getErrorMontoExcedido = (gasto, index) => {
    if (!gasto.detalle_gasto_proyectado_id || !gasto.monto_total) return '';
    const saldoMaximo = getSaldoMaximoParaGasto(gasto.detalle_gasto_proyectado_id, index);
    const montoExcedido = parseFloat(gasto.monto_total) > saldoMaximo;
    if (montoExcedido) {
        const exceso = parseFloat(gasto.monto_total) - saldoMaximo;
        return `Excede el saldo disponible en S/. ${exceso.toFixed(2)}`;
    }
    return '';
};
// --- MÉTODOS DE EVENTOS ---
// Manejar cambio en tipo de documento
const onTipoDocumentoChange = (gasto) => {
    if (gasto.tipo_documento === 'Declaración Jurada') {
        gasto.es_declaracion_jurada = true;
        gasto.serie_documento = '';
        gasto.correlativo_documento = '';
    } else {
        gasto.es_declaracion_jurada = false;
        // Habilitar campos serie y correlativo cuando no es DJ
    }
};
const removerArchivo = (index) => {
    gastosADeclarar.value[index].evidencia = null;
    const fileInput = document.getElementById(`evidencia_${index}`);
    if (fileInput) fileInput.value = '';
};
const formatFileSize = (bytes) => {
    if (!bytes || bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};
// Manejar cambio en checkbox de declaración jurada
const onDeclaracionJuradaChange = (gasto) => {
    if (gasto.es_declaracion_jurada) {
        gasto.tipo_documento = 'Declaración Jurada';
        gasto.serie_documento = '';
        gasto.correlativo_documento = '';
    } else {
        // Si desmarca DJ, cambiar a Boleta de Venta por defecto
        gasto.tipo_documento = 'Boleta de Venta';
    }
};
// Manejar cambio de archivo
const handleFileChange = (event, index) => {
    gastosADeclarar.value[index].evidencia = event.target.files[0];
};
// Generar y descargar declaración jurada
const generarYDescargarDJ = async (gasto) => {
    if (!gasto.monto_total || !gasto.glosa) {
        Swal.fire('Datos incompletos', 'Por favor, ingrese el Monto Total y la Glosa del gasto antes de generar la DJ.', 'warning');
        return;
    }
    try {
        const response = await api.post('/v1/documentos/generar-dj', {
            monto: gasto.monto_total,
            glosa: gasto.glosa,
        }, {
            responseType: 'blob'
        });
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const filename = `DJ-${usuarioActual.value.name}-${Date.now()}.pdf`;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error("Error al generar la Declaración Jurada:", error);
        Swal.fire('Error', 'No se pudo generar el documento PDF.', 'error');
    }
};
// --- MÉTODOS DE ENVÍO ---
// Confirmar envío del formulario
const confirmarEnvio = () => {
    // Validar cada gasto
    for (const [index, gasto] of gastosADeclarar.value.entries()) {
        // Validar campos obligatorios
        if (!gasto.detalle_gasto_proyectado_id || !gasto.monto_total || !gasto.tipo_documento || !gasto.id_cuenta_contable || !gasto.evidencia) {
            Swal.fire('Campos incompletos', `Por favor, complete todos los campos requeridos (*) para el Gasto #${index + 1}.`, 'warning');
            return;
        }
        // Validar que el monto no exceda el saldo disponible
        const saldoMaximo = getSaldoMaximoParaGasto(gasto.detalle_gasto_proyectado_id, index);
        if (parseFloat(gasto.monto_total) > saldoMaximo) {
            const proyeccion = proyeccionesPendientes.value.find(p => p.id === gasto.detalle_gasto_proyectado_id);
            Swal.fire({
                icon: 'error',
                title: 'Monto Excedido',
                html: `
                    <div class="text-left">
                        <p><strong>Gasto #${index + 1}</strong></p>
                        <p><strong>Proyección:</strong> ${proyeccion?.descripcion_gasto}</p>
                        <p><strong>Saldo disponible:</strong> S/. ${saldoMaximo.toFixed(2)}</p>
                        <p><strong>Monto ingresado:</strong> S/. ${parseFloat(gasto.monto_total).toFixed(2)}</p>
                        <p class="text-red-600 mt-2">Por favor, ajuste el monto para continuar.</p>
                    </div>
                `
            });
            return;
        }
    }
    // Construir resumen para confirmación
    const resumenHtml = `
        <div class="text-left text-sm space-y-3 p-2 bg-gray-50 rounded-lg border">
            <h4 class="font-bold text-center">Resumen de Gastos a Declarar</h4>
            ${gastosADeclarar.value.map((gasto, index) => {
        const proyeccion = proyeccionesPendientes.value.find(p => p.id === gasto.detalle_gasto_proyectado_id);
        return `<div class="border-t pt-2 mt-2">
                    <div class="flex justify-between"><strong>Gasto #${index + 1}:</strong><span class="text-right pl-2">${proyeccion?.descripcion_gasto || 'N/A'}</span></div>
                    <div class="flex justify-between"><strong>Tipo Documento:</strong><span>${gasto.tipo_documento}</span></div>
                    <div class="flex justify-between font-semibold"><strong>Monto:</strong><span>S/. ${parseFloat(gasto.monto_total || 0).toFixed(2)}</span></div>
                </div>`;
    }).join('')}
            <div class="border-t pt-2 mt-2 font-bold">
                <div class="flex justify-between"><strong>Total:</strong><span>S/. ${gastosADeclarar.value.reduce((total, gasto) => total + (parseFloat(gasto.monto_total) || 0), 0).toFixed(2)}</span></div>
            </div>
        </div>
        <p class="mt-4 text-gray-700">¿Desea registrar esta declaración con ${gastosADeclarar.value.length} gasto(s)?</p>
    `;
    Swal.fire({
        title: 'Revisar y Confirmar Declaración',
        html: resumenHtml,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Sí, Registrar',
        cancelButtonText: 'Cancelar',
    }).then((result) => {
        if (result.isConfirmed) {
            enviarFormulario();
        }
    });
};
// Enviar formulario al backend
const enviarFormulario = async () => {
    enviando.value = true;
    const formDataPayload = new FormData();
    formDataPayload.append('id_fondo_efectivo', fondoSeleccionadoId.value);
    // Agregar cada gasto al FormData
    gastosADeclarar.value.forEach((gasto, index) => {
        const { id, ...gastoData } = gasto; // Omitir ID temporal
        for (const key in gastoData) {
            if (gastoData[key] !== null && gastoData[key] !== '') {
                // Convertir booleanos a 1 o 0 para el backend
                if (typeof gastoData[key] === 'boolean') {
                    formDataPayload.append(`gastos[${index}][${key}]`, gastoData[key] ? 1 : 0);
                } else {
                    formDataPayload.append(`gastos[${index}][${key}]`, gastoData[key]);
                }
            }
        }
    });
    try {
        const response = await api.post('/v1/gastos', formDataPayload, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        Swal.fire({
            icon: 'success',
            title: '¡Declaración Registrada!',
            text: response.data.message,
        });
        emit('gastoCreado');
        emit('close');
    } catch (error) {
        console.error("Error al registrar la declaración:", error);
        const errorMessage = error.response?.data?.message || 'Ocurrió un error inesperado.';
        const errors = error.response?.data?.errors;
        let htmlError = errorMessage;
        if (errors) {
            htmlError += '<ul class="text-left mt-2 list-disc list-inside">';
            for (const key in errors) {
                htmlError += `<li>${errors[key][0]}</li>`;
            }
            htmlError += '</ul>';
        }
        Swal.fire({
            icon: 'error',
            title: 'Error al Registrar',
            html: htmlError
        });
    } finally {
        enviando.value = false;
    }
};
</script>
<style scoped>
/* Estilos específicos para el componente si fueran necesarios */
.resize-none {
    resize: none;
}

/* Transiciones suaves para la lista de gastos */
.gasto-list-enter-active,
.gasto-list-leave-active {
    transition: all 0.5s cubic-bezier(0.55, 0, 0.1, 1);
}

.gasto-list-enter-from,
.gasto-list-leave-to {
    opacity: 0;
    transform: scale(0.95) translateY(-20px);
}

.gasto-list-move {
    transition: transform 0.5s ease;
}

/* Transiciones para las secciones internas del formulario */
.fade-in-up-enter-active,
.fade-in-up-leave-active {
    transition: all 0.4s ease-out;
}

.fade-in-up-enter-from,
.fade-in-up-leave-to {
    opacity: 0;
    transform: translateY(15px);
}

.fade-in-enter-active,
.fade-in-leave-active {
    transition: opacity 0.3s ease;
}

.fade-in-enter-from,
.fade-in-leave-to {
    opacity: 0;
}
</style>