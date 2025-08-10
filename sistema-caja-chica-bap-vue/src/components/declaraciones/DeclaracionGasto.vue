<template>
    <!-- Contenedor principal del formulario con estilos de padding y fondo. -->
    <div class="p-6 bg-gray-50 min-h-screen">
        <h2 class="text-3xl font-bold text-gray-800 mb-8 text-center animate-fade-in-down">Registrar Declaración de
            Gastos</h2>

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
                    <label for="fondo" class="form-label">Fondo de Caja Chica <span
                            class="text-rojo-bap">*</span></label>
                    <select id="fondo" v-model="fondoSeleccionadoId"
                        class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap transition-colors duration-200"
                        required>
                        <option :value="null" disabled>-- Seleccione un fondo --</option>
                        <option v-for="fondo in fondosActivos" :key="fondo.id_fondo" :value="fondo.id_fondo">
                            {{ fondo.codigo_fondo }} - (Saldo Disp: {{ currencyFormatter.format(fondo.monto_disponible)
                            }})
                        </option>
                    </select>
                    <transition name="fade-in">
                        <p v-if="fondoSeleccionadoId"
                            class="text-sm text-gray-600 mt-2 p-2 rounded-lg border-l-4 border-verde-bap bg-verde-bap-extralight">
                            💰 Saldo Disponible Actual: <strong class="text-verde-bap-dark">{{
                                currencyFormatter.format(fondoSeleccionado?.monto_disponible || 0) }}</strong>
                        </p>
                    </transition>
                </div>
            </div>

            <!-- Estado de carga de proyecciones -->
            <transition name="fade" mode="out-in">
                <div v-if="cargandoGastosProyectados" class="text-center py-6">
                    <div class="animate-pulse flex items-center justify-center text-gray-500">
                        <svg class="animate-spin h-5 w-5 mr-3 text-gray-400" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 4V2A10 10 0 002 12h2a8 8 0 018-8z"></path>
                        </svg>
                        Cargando gastos autorizados para el fondo...
                    </div>
                </div>
            </transition>

            <!-- PASO 2: GASTOS A DECLARAR -->
            <div v-if="fondoSeleccionadoId && !cargandoGastosProyectados" class="space-y-4">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">
                    2. Detalle de Gastos a Declarar
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
                                            class="flex-shrink-0 inline-flex items-center justify-center w-8 h-8 rounded-full bg-verde-bap text-white text-sm font-bold">{{
                                                index + 1 }}
                                        </span>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-gray-800 font-medium">
                                            <!-- Muestra la descripción del gasto proyectado seleccionado, o "Nuevo Gasto" si aún no se ha seleccionado -->
                                            {{gasto.id_gasto_proyectado ? gastosProyectadosDisponibles.find(p =>
                                                p.id_gasto_proyectado === gasto.id_gasto_proyectado)?.descripcion : 'Nuevo Gasto' }}
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
                                        <div v-if="isGastoCompleto(gasto)" class="w-3 h-3 bg-green-500 rounded-full"
                                            title="Completo"></div>
                                        <div v-else class="w-3 h-3 bg-yellow-500 rounded-full" title="Incompleto"></div>
                                    </div>
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
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
                                                class="block text-sm font-medium text-gray-700">Gasto Proyectado <span
                                                    class="text-rojo-bap">*</span></label>
                                            <select :id="'proyeccion_' + index" v-model="gasto.id_gasto_proyectado"
                                                class="mt-1 block w-full p-3 border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                                                required>
                                                <option :value="null" disabled>Seleccione un Gasto Proyectado</option>
                                                <option v-for="proyeccion in gastosProyectadosDisponibles"
                                                    :key="proyeccion.id_gasto_proyectado"
                                                    :value="proyeccion.id_gasto_proyectado"
                                                    :disabled="esOpcionDeshabilitada(proyeccion, gasto)">
                                                    {{ proyeccion.descripcion }} (Saldo: {{
                                                        currencyFormatter.format(getSaldoMaximoParaGasto(proyeccion.id_gasto_proyectado,
                                                            index)) }})
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
                                                        <option>Declaración Jurada</option>
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
                                                    <div v-if="gasto.es_declaracion_jurada"
                                                        class="dj-warning-message mt-2">
                                                        <svg class="w-5 h-5 flex-shrink-0" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24"
                                                            xmlns="http://www.w3.org/2000/svg">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                                            </path>
                                                        </svg>
                                                        <p>
                                                            <strong>Importante:</strong> Asegúrese de que esta fecha sea
                                                            el día <strong>real de la transacción</strong>. Si es un
                                                            gasto de un mes anterior (declaración por excepción), debe
                                                            usar la fecha correspondiente a ese mes.
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Monto del Documento -->
                                                <div>
                                                    <label :for="'monto_total_' + index"
                                                        class="block text-sm font-medium text-gray-700 mb-2">
                                                        Monto del Documento (S/.) <span class="text-rojo-bap">*</span>
                                                    </label>
                                                    <input type="number" :id="'monto_total_' + index"
                                                        v-model.number="gasto.monto_total"
                                                        :max="getSaldoMaximoParaGasto(gasto.id_gasto_proyectado, index)"
                                                        :class="getMontoInputClasses(gasto, index)" step="0.01"
                                                        min="0.01" @input="validarMontoEnTiempoReal(gasto, index)"
                                                        placeholder="0.00" required />
                                                    <!-- Mensaje de saldo disponible -->
                                                    <transition name="fade" mode="out-in">
                                                        <div v-if="gasto.id_gasto_proyectado" class="mt-2">
                                                            <p
                                                                class="text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded">
                                                                💰 Saldo disponible: <strong>{{
                                                                    currencyFormatter.format(getSaldoMaximoParaGasto(gasto.id_gasto_proyectado,
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
                                                        <span
                                                            v-if="gasto.tipo_documento === 'Boleta de Venta' || gasto.tipo_documento === 'Factura'"
                                                            class="text-rojo-bap">*</span>
                                                    </label>
                                                    <input type="text" :id="'serie_documento_' + index"
                                                        v-model="gasto.serie_documento"
                                                        :disabled="gasto.tipo_documento === 'Declaración Jurada'"
                                                        :required="gasto.tipo_documento === 'Boleta de Venta' || gasto.tipo_documento === 'Factura'"
                                                        class="mt-1 block w-full p-3 border border-gray-300 rounded-lg disabled:bg-gray-200 disabled:cursor-not-allowed focus:border-verde-bap focus:ring-verde-bap transition-colors duration-200"
                                                        placeholder="Ej: F001" />
                                                </div>

                                                <!-- Correlativo del Documento -->
                                                <div>
                                                    <label :for="'correlativo_documento_' + index"
                                                        class="block text-sm font-medium text-gray-700 mb-2">
                                                        Correlativo del Documento
                                                        <span
                                                            v-if="gasto.tipo_documento === 'Boleta de Venta' || gasto.tipo_documento === 'Factura'"
                                                            class="text-rojo-bap">*</span>
                                                    </label>
                                                    <input type="text" :id="'correlativo_documento_' + index"
                                                        v-model="gasto.correlativo_documento"
                                                        :disabled="gasto.tipo_documento === 'Declaración Jurada'"
                                                        :required="gasto.tipo_documento === 'Boleta de Venta' || gasto.tipo_documento === 'Factura'"
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
                                                <!-- Glosa para el asiento -->
                                                <div class="md:col-span-2">
                                                    <label :for="'glosa_' + index"
                                                        class="block text-sm font-medium text-gray-700">Glosa /
                                                        Descripción del Gasto <span
                                                            class="text-rojo-bap">*</span></label>
                                                    <input type="text" :id="'glosa_' + index" v-model="gasto.glosa"
                                                        class="mt-1 block w-full p-3 border-gray-300 rounded-md"
                                                        placeholder="Ej: Movilidad para reunión con cliente" required />
                                                </div>

                                                <!-- Cuenta Contable -->
                                                <div>
                                                    <label :for="'cuenta_contable_' + index"
                                                        class="block text-sm font-medium text-gray-700">Cuenta
                                                        Contable</label>
                                                    <input type="text" :id="'cuenta_contable_' + index"
                                                        :value="getCuentaContableInfo(gasto.id_gasto_proyectado)"
                                                        class="mt-1 block w-full p-3 border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                                                        disabled />
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

                                    <!-- ====== CATEGORÍA 5: EVIDENCIA Y SUSTENTO ====== -->
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

                                            <!-- Checkbox Declaración Jurada -->
                                            <div class="flex items-center mb-4">
                                                <input type="checkbox" :id="'dj_' + index"
                                                    v-model="gasto.es_declaracion_jurada"
                                                    @change="onDeclaracionJuradaChange(gasto)"
                                                    class="h-4 w-4 text-verde-bap rounded border-gray-300 focus:ring-verde-bap transition-colors duration-200">
                                                <label :for="'dj_' + index" class="ml-2 text-sm text-gray-900">
                                                    Este gasto se sustenta con Declaración Jurada
                                                </label>
                                            </div>

                                            <!-- MODIFICACIÓN: El área para subir evidencia individual ahora es condicional -->
                                            <!-- Solo se muestra si el gasto NO es una DJ -->
                                            <transition name="slide-down">
                                                <div v-if="!gasto.es_declaracion_jurada">
                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                        Archivo de Evidencia (Boleta/Factura) <span
                                                            class="text-rojo-bap">*</span>
                                                    </label>
                                                    <div
                                                        class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-verde-bap transition-colors duration-200">
                                                        <input type="file" :id="'evidencia_' + index"
                                                            @change="handleFileChange($event, index)" class="hidden"
                                                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                        <label :for="'evidencia_' + index" class="cursor-pointer block">
                                                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4"
                                                                stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                                <path
                                                                    d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                                                    stroke-width="2" stroke-linecap="round"
                                                                    stroke-linejoin="round" />
                                                            </svg>
                                                            <span class="text-lg font-medium text-gray-700">
                                                                {{ gasto.evidencia ? gasto.evidencia.name : 'Haz clic para subir archivo' }}
                                                            </span>
                                                            <p class="text-sm text-gray-500 mt-2">
                                                                📎 PDF, JPG, PNG, DOC (máx. 10MB)
                                                            </p>
                                                        </label>
                                                    </div>
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
                                            </transition>
                                        </div>
                                    </transition>
                                </div>

                                <!-- Botón para minimizar y pasar al siguiente gasto -->
                                <div class="flex justify-center pt-4 border-t mt-6">
                                    <button type="button" @click="minimizarYContinuar(index)"
                                        class="bg-verde-bap hover:bg-verde-bap-hover text-white font-semibold py-2 px-6 rounded-lg transition-colors shadow-md flex items-center">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Guardar y Continuar
                                    </button>
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

            <transition name="fade-in-up">
                <div v-if="mostrarSeccionDJConsolidada"
                    class="p-6 border border-gray-200 rounded-xl bg-white shadow-soft">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">
                        3. Declaración Jurada Consolidada
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">
                            <p class="text-sm text-blue-800 font-medium">Paso 1: Generar Plantilla</p>
                            <p class="text-xs text-blue-600 mt-1 mb-3">
                                Descarga el documento que agrupa todos los gastos marcados como DJ.
                            </p>
                            <button type="button" @click="generarDJConsolidada" :disabled="!puedeGenerarDJConsolidada"
                                class="bg-verde-bap hover:bg-verde-bap-hover text-white font-semibold py-2 px-4 rounded-lg transition-colors shadow-sm flex items-center disabled:opacity-50 disabled:cursor-not-allowed mx-auto">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Generar Plantilla
                            </button>
                        </div>

                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
                            <p class="text-sm text-green-800 font-medium">Paso 2: Subir Documento Firmado</p>
                            <p class="text-xs text-green-600 mt-1 mb-3">
                                Adjunta aquí la plantilla después de haberla firmado.
                            </p>
                            <input type="file" id="dj_consolidada_input" @change="handleDJConsolidadaFileChange"
                                class="hidden" accept=".pdf,.jpg,.jpeg,.png">
                            <label for="dj_consolidada_input"
                                class="cursor-pointer bg-white hover:bg-gray-100 text-gray-700 font-semibold py-2 px-4 rounded-lg transition-colors shadow-sm flex items-center border border-gray-300 mx-auto justify-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                {{ djConsolidadaFile ? 'Cambiar Archivo' : 'Seleccionar Archivo' }}
                            </label>
                            <transition name="fade-in">
                                <p v-if="djConsolidadaFile" class="text-xs text-green-700 mt-2 truncate">
                                    Archivo: {{ djConsolidadaFile.name }}
                                </p>
                            </transition>
                        </div>
                    </div>
                </div>
            </transition>
            <!-- Botones de Acción Finales -->
            <div class="flex justify-end space-x-4 pt-6 mt-6 border-t border-gray-200">
                <button type="button" @click="$emit('close')"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-8 rounded-full transition-colors duration-200">
                    Cancelar
                </button>
                <button type="submit" :disabled="enviando || hayErroresValidacion"
                    :class="getClassesForActionButton('exito')"
                    class="font-bold py-3 px-8 rounded-full min-w-[200px] disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200 flex items-center justify-center">
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

const fondosActivos = ref([]);
const fondoSeleccionadoId = ref(null);
const gastosProyectadosDisponibles = ref([]);
const cuentasContables = ref([]);
const cargandoInicial = ref(true);
const cargandoGastosProyectados = ref(false);
const enviando = ref(false);
const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const gastoActivoIndex = ref(0);
const djConsolidadaFile = ref(null);

// --- LÓGICA DE CARGA DE DATOS ---
onMounted(async () => {
    cargandoInicial.value = true;
    await Promise.all([

        obtenerFondosActivos(),
        obtenerCuentasContables()

    ]);
    cargandoInicial.value = false;
});
const props = defineProps({
    usuarioActual: {
        type: Object,
        required: true
    }
});
const obtenerFondosActivos = async () => {
    try {
        const response = await api.get('/v1/fondos-activos-usuario');
        fondosActivos.value = response.data;
    } catch (error) {
        console.error("Error al obtener fondos activos:", error);
        Swal.fire('Error', 'No se pudieron cargar tus fondos activos.', 'error');
    }
};
const obtenerCuentasContables = async () => {
    try {
        const response = await api.get('/v1/cuentas-contables');
        if (response.data && Array.isArray(response.data.cuentas_contables)) {
            cuentasContables.value = response.data.cuentas_contables;
        } else if (Array.isArray(response.data)) {
            cuentasContables.value = response.data;
        } else {
            console.error("La respuesta de la API de cuentas contables no tiene el formato esperado:", response.data);
            cuentasContables.value = []; // Asignar array vacío para prevenir errores.
        }
    } catch (error) {
        console.error("Error al obtener cuentas contables:", error);
        cuentasContables.value = []; // Asegurarse de que sea un array en caso de error.
    }
};
// --- COMPUTED PROPERTIES ---
// MODIFICACIÓN: Propiedad computada para mostrar la sección de DJ consolidada
const mostrarSeccionDJConsolidada = computed(() => {
    return gastosADeclarar.value.some(gasto => gasto.es_declaracion_jurada || gasto.tipo_documento === 'Declaración Jurada');
});
const puedeGenerarDJConsolidada = computed(() => {
    const gastosParaDJ = gastosADeclarar.value.filter(g => g.es_declaracion_jurada || g.tipo_documento === 'Declaración Jurada');
    if (gastosParaDJ.length === 0) {
        return false;
    }
    // El botón se habilita solo si TODOS los gastos para DJ tienen monto y glosa.
    return gastosParaDJ.every(g => g.monto_total && g.glosa);
});
// Determina qué secciones son visibles para un gasto específico
const seccionesVisibles = (gasto) => {
    return {
        documento: !!gasto.id_gasto_proyectado,
        clasificacion: !!gasto.id_gasto_proyectado && !!gasto.tipo_documento && !!gasto.monto_total && !!gasto.fecha_documento,
        adicional: !!gasto.id_gasto_proyectado && !!gasto.tipo_documento && !!gasto.monto_total && !!gasto.fecha_documento && !!gasto.glosa,
        evidencia: !!gasto.id_gasto_proyectado && !!gasto.tipo_documento && !!gasto.monto_total && !!gasto.fecha_documento && !!gasto.glosa,
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

// Propiedad computada para obtener el objeto completo del fondo seleccionado
const fondoSeleccionado = computed(() => {
    return fondosActivos.value.find(f => f.id_fondo === fondoSeleccionadoId.value);
});
// Computed para verificar si hay errores de validación
const hayErroresValidacion = computed(() => {
    if (gastosADeclarar.value.length === 0) return true;
    // Validar cada gasto individualmente
    const algunGastoIncompleto = gastosADeclarar.value.some(gasto => !isGastoCompleto(gasto));
    if (algunGastoIncompleto) return true;
    // Validar globalmente si se necesita una DJ consolidada y no se ha subido
    if (mostrarSeccionDJConsolidada.value && !djConsolidadaFile.value) {
        return true;
    }
    return false;
});
// --- WATCHERS ---
// Observa cambios en el fondo seleccionado para cargar sus proyecciones
watch(fondoSeleccionadoId, async (newFondoId) => {
    gastosProyectadosDisponibles.value = [];
    gastosADeclarar.value = [];

    if (newFondoId) {
        cargandoGastosProyectados.value = true;
        try {
            const response = await api.get(`/v1/fondos-efectivo/${newFondoId}/gastos-para-declarar`);
            gastosProyectadosDisponibles.value = response.data;
            agregarGasto();
        } catch (error) {
            console.error("Error al obtener gastos proyectados para el fondo:", error);
            Swal.fire('Error', 'No se pudieron cargar los gastos autorizados para este fondo.', 'error');
        } finally {
            cargandoGastosProyectados.value = false;
        }
    }
});
watch(() => gastosADeclarar.value.map(g => g.id_gasto_proyectado), (nuevosIds, viejosIds) => {
    nuevosIds.forEach((id, index) => {
        // Si el ID del gasto proyectado en una fila ha cambiado...
        if (id !== viejosIds[index]) {
            const gasto = gastosADeclarar.value[index];
            if (id) {
                const proyeccion = gastosProyectadosDisponibles.value.find(p => p.id_gasto_proyectado === id);
                // Asigna el ID de la cuenta contable al modelo del gasto.
                gasto.id_cuenta_contable = proyeccion ? proyeccion.id_cuenta_contable : null;
            } else {
                gasto.id_cuenta_contable = null;
            }
        }
    });
});

const getCuentaContableInfo = (gastoProyectadoId) => {
    if (!gastoProyectadoId) return 'Se asigna automáticamente';
    const proyeccion = gastosProyectadosDisponibles.value.find(p => p.id_gasto_proyectado === gastoProyectadoId);
    if (!proyeccion || !proyeccion.id_cuenta_contable) return 'Cuenta no asignada a la proyección';
    const cuenta = cuentasContables.value.find(c => c.id === proyeccion.id_cuenta_contable);
    return cuenta ? `${cuenta.codigo_cuenta} - ${cuenta.descripcion}` : 'Buscando...';
};
// --- MÉTODOS DE GESTIÓN DE GASTOS ---
// Función para añadir una nueva fila de gasto
const agregarGasto = async () => {
    // Minimizamos todos los gastos existentes antes de añadir uno nuevo.
    gastoActivoIndex.value = gastosADeclarar.value.length;
    gastosADeclarar.value.push({
        id: Date.now(),
        id_gasto_proyectado: null,
        id_cuenta_contable: null,
        glosa: '',
        fecha_documento: '',
        fecha_registro: new Date().toISOString().slice(0, 10),
        moneda: 'PEN',
        tipo_documento: '',
        serie_documento: '',
        correlativo_documento: '',
        monto_total: null,

        comentario: '',
        evidencia: null,
        es_declaracion_jurada: false,
    });
    // Esperar a que el DOM se actualice y luego hacer focus
    await nextTick();
    maximizarGasto(gastoActivoIndex);
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
const getSaldoMaximoParaGasto = (gastoProyectadoId, indexFilaActual) => {
    if (!gastoProyectadoId) return 0;
    const proyeccion = gastosProyectadosDisponibles.value.find(p => p.id_gasto_proyectado === gastoProyectadoId);
    if (!proyeccion) return 0;
    // Saldo inicial es el que nos devolvió el backend.
    let saldoDisponible = parseFloat(proyeccion.saldo_restante) || 0;
    // Restamos los montos de OTRAS filas que usen la misma proyección.
    gastosADeclarar.value.forEach((gasto, index) => {
        if (index !== indexFilaActual && gasto.id_gasto_proyectado === gastoProyectadoId) {
            saldoDisponible -= parseFloat(gasto.monto_total) || 0;
        }
    });
    return saldoDisponible;
};
// Validar monto en tiempo real
const validarMontoEnTiempoReal = (gasto, index) => {
    if (!gasto.id_gasto_proyectado || !gasto.monto_total) return;
    const saldoMaximo = getSaldoMaximoParaGasto(gasto.id_gasto_proyectado, index);
    if (parseFloat(gasto.monto_total) > saldoMaximo) {
        const proyeccion = gastosProyectadosDisponibles.value.find(p => p.id_gasto_proyectado === gasto.id_gasto_proyectado);
        Swal.fire({
            icon: 'warning',
            title: 'Monto Excede el Saldo Disponible',
            html: `
                <div class="text-left p-2 border-t border-b">
                    <p class="mb-2"><strong>Proyección:</strong> ${proyeccion?.descripcion}</p>
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
            confirmButtonColor: '#DB3D47',
        });
    }
};
// Obtener clases CSS para el input de monto
const getMontoInputClasses = (gasto, index) => {
    const baseClasses = 'mt-1 block w-full p-3 border rounded-lg focus:ring-1';
    if (!gasto.id_gasto_proyectado || !gasto.monto_total) {
        return `${baseClasses} border-gray-300 focus:border-verde-bap focus:ring-verde-bap`;
    }
    const saldoMaximo = getSaldoMaximoParaGasto(gasto.id_gasto_proyectado, index);
    const montoExcedido = parseFloat(gasto.monto_total) > saldoMaximo;
    const themeClasses = montoExcedido
        ? 'border-rojo-bap focus:border-rojo-bap-dark focus:ring-rojo-bap'
        : 'border-verde-bap focus:border-verde-bap-dark focus:ring-verde-bap';
    return `${baseClasses} ${themeClasses}`;
};
// Obtener mensaje de error para monto excedido
const getErrorMontoExcedido = (gasto, index) => {
    if (!gasto.id_gasto_proyectado || !gasto.monto_total) return '';
    const saldoMaximo = getSaldoMaximoParaGasto(gasto.id_gasto_proyectado, index);
    const montoExcedido = parseFloat(gasto.monto_total) > saldoMaximo;
    if (montoExcedido) {
        const exceso = parseFloat(gasto.monto_total) - saldoMaximo;
        return `Excede el saldo disponible en S/. ${exceso.toFixed(2)}`;
    }
    return '';
};
const esOpcionDeshabilitada = () => {
    return false;
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
// MODIFICACIÓN: Nuevo método para manejar el cambio de archivo de la DJ consolidada
const handleDJConsolidadaFileChange = (event) => {
    djConsolidadaFile.value = event.target.files[0];
};
// MODIFICACIÓN: Nuevo método para generar la DJ consolidada
const generarDJConsolidada = async () => {
    const gastosValidosParaDJ = gastosADeclarar.value.filter(g => g.es_declaracion_jurada && isGastoCompleto(g));

    if (gastosValidosParaDJ.length < 0) {
        Swal.fire('Gastos Insuficientes para DJ', 'Necesitas al menos 1 gastos marcados como "Declaración Jurada" y completos para generar la plantilla de DJ Consolidada.', 'info');
        return;
    }

    // Preparar FormData para enviar los datos de los gastos al backend
    const formDataForDJGen = new FormData();
    formDataForDJGen.append('id_fondo_efectivo', fondoSeleccionadoId.value);
    formDataForDJGen.append('id_registrador', props.usuarioActual.id);

    gastosValidosParaDJ.forEach((gasto, index) => {
        Object.keys(gasto).forEach(key => {
            // No enviar el ID temporal de Vue ni campos que no son del modelo
            if (key === 'id' || key === 'responsable_gasto_nombre' || key === 'evidencia' || key === 'ruta_evidencia' || key === 'monto_proyectado_original') return;

            // Convertir booleanos a 0 o 1
            const value = typeof gasto[key] === 'boolean' ? (gasto[key] ? 1 : 0) : gasto[key];
            formDataForDJGen.append(`gastos[${index}][${key}]`, value);
        });
    });

    try {
        Swal.fire({
            title: 'Generando Plantilla...',
            text: 'Por favor, espera mientras preparamos el documento. No cierres esta ventana.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Enviar los datos completos de los gastos al backend para la generación de la plantilla
        // El backend VALIDARÁ estos datos y usará los que necesite para la plantilla PDF.
        const response = await api.post('/v1/documentos/generar-dj-nuevos', formDataForDJGen, {
            responseType: 'blob', // Para manejar la descarga del archivo
            headers: { 'Content-Type': 'multipart/form-data' } // Importante para FormData, ya que incluye archivos
        });

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const filename = `DJ-Consolidada-Plantilla-${Date.now()}.pdf`;
        link.setAttribute('download', filename);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        Swal.fire('¡Plantilla Generada!', 'La plantilla de DJ Consolidada ha sido descargada. Por favor, fírmala y súbela en el siguiente paso.', 'success');
    } catch (error) {
        console.error("Error al generar la DJ Consolidada:", error);
        const errorMessage = error.response?.data?.message || 'No se pudo generar el documento PDF consolidado.';
        const errors = error.response?.data?.errors;
        let htmlError = `<p>${errorMessage}</p>`;
        if (errors) {
            htmlError += '<ul class="text-left mt-2 list-disc list-inside">';
            for (const key in errors) {
                // Formato de error más amigable para errores de validación de gastos anidados
                const fieldName = key.replace(/gastos\.(\d+)\.(\w+)/, (match, gastoIndex, field) => `Gasto #${parseInt(gastoIndex) + 1} (${field.replace(/_/g, ' ')})`);
                htmlError += `<li>${fieldName}: ${errors[key][0]}</li>`;
            }
            htmlError += '</ul>';
        }
        Swal.fire({
            icon: 'error',
            title: 'Error al Generar DJ',
            html: htmlError
        });
    }
};

//validación
const isGastoCompleto = (gasto) => {
    const camposBaseCompletos = gasto.id_gasto_proyectado &&
        gasto.tipo_documento &&
        gasto.fecha_documento &&
        gasto.monto_total &&
        gasto.glosa;

    if (!camposBaseCompletos) return false;

    if (gasto.es_declaracion_jurada) {
        // Si es una DJ, solo necesitamos los campos base. La evidencia se valida globalmente.
        return true;
    } else {
        // Si no es DJ, necesita su propia evidencia y, si aplica, serie/correlativo.
        const evidenciaCompleta = !!gasto.evidencia;
        const comprobanteCompleto = (gasto.tipo_documento === 'Boleta de Venta' || gasto.tipo_documento === 'Factura')
            ? (!!gasto.serie_documento && !!gasto.correlativo_documento)
            : true;
        return evidenciaCompleta && comprobanteCompleto;
    }
};
// --- MÉTODOS DE ENVÍO ---
// Confirmar envío del formulario
const confirmarEnvio = () => {
    if (hayErroresValidacion.value) {
        let mensaje = 'Por favor, revise todos los gastos. Asegúrese de que todos los campos obligatorios (*) estén completos.';
        if (mostrarSeccionDJConsolidada.value && !djConsolidadaFile.value) {
            mensaje += '<br><br>Además, debe adjuntar el archivo de la <strong>Declaración Jurada Consolidada</strong>.';
        }
        Swal.fire('Formulario Incompleto', mensaje, 'warning');
        return;
    }
    const totalGeneral = gastosADeclarar.value.reduce((total, gasto) => total + (parseFloat(gasto.monto_total) || 0), 0);
    const resumenHtml = `
        <div class="text-left text-sm space-y-3 p-2 bg-gray-50 rounded-lg border" style="max-height: 300px; overflow-y: auto;">
            <h4 class="font-bold text-center text-base">Resumen de Gastos a Declarar</h4>
            ${gastosADeclarar.value.map((gasto, index) => {
        const proyeccion = gastosProyectadosDisponibles.value.find(p => p.id_gasto_proyectado === gasto.id_gasto_proyectado);
        return `<div class="border-t pt-2 mt-2">
                            <div class="flex justify-between"><strong>Gasto #${index + 1}:</strong><span class="text-right pl-2">${proyeccion?.descripcion || 'N/A'}</span></div>
                            <div class="flex justify-between"><strong>Documento:</strong><span>${gasto.tipo_documento}</span></div>
                            <div class="flex justify-between font-semibold"><strong>Monto:</strong><span>${currencyFormatter.format(gasto.monto_total || 0)}</span></div>
                        </div>`;
    }).join('')}
            <div class="border-t pt-2 mt-2 font-bold text-base">
                <div class="flex justify-between"><strong>Total a Declarar:</strong><span>${currencyFormatter.format(totalGeneral)}</span></div>
            </div>
        </div>
        <p class="mt-4 text-gray-700">¿Desea registrar esta declaración con ${gastosADeclarar.value.length} gasto(s)?</p>
    `;
    Swal.fire({
        title: 'Revisar y Confirmar',
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
    // 1. Doble verificación de completitud antes de enviar (buena práctica).
    for (const [index, gasto] of gastosADeclarar.value.entries()) {
        if (!isGastoCompleto(gasto)) {
            Swal.fire('Campos Incompletos', `Por favor, complete todos los campos requeridos (*) para el Gasto #${index + 1}.`, 'warning');
            return;
        }
    }
    if (mostrarSeccionDJConsolidada.value && !djConsolidadaFile.value) {
        Swal.fire('Falta Archivo', 'Debe adjuntar el archivo de la Declaración Jurada Consolidada.', 'warning');
        return;
    }
    enviando.value = true;
    const formDataPayload = new FormData();
    formDataPayload.append('id_fondo_efectivo', fondoSeleccionadoId.value);

    // 2. Añadir el archivo de DJ consolidada si existe.
    if (djConsolidadaFile.value) {
        formDataPayload.append('dj_consolidada_file', djConsolidadaFile.value);
    }
    // 3. Construir el payload de gastos.
    gastosADeclarar.value.forEach((gasto, index) => {
        Object.keys(gasto).forEach(key => {
            // No enviar el ID temporal del frontend.
            if (key === 'id') return;
            // Determinar si el gasto actual es una DJ.
            const esDJ = gasto.es_declaracion_jurada || gasto.tipo_documento === 'Declaración Jurada';
            // Si el gasto es una DJ, NO se envía su evidencia individual.
            if (key === 'evidencia' && esDJ) {
                return;
            }
            // Añadir el resto de los datos al FormData.
            if (gasto[key] !== null && gasto[key] !== '') {
                const value = typeof gasto[key] === 'boolean' ? (gasto[key] ? 1 : 0) : gasto[key];
                formDataPayload.append(`gastos[${index}][${key}]`, value);
            }
        });
    });
    // 4. Enviar la petición.
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
        if (error.response && error.response.status === 403) {
            Swal.fire({
                icon: 'error',
                title: 'Acción No Permitida',
                html: `<p>No se puede registrar el gasto.</p><p class="mt-2 text-sm">${error.response.data.message || 'El período contable para una de las fechas seleccionadas ya ha sido cerrado.'}</p>`
            });
        } else {
            const errorMessage = error.response?.data?.message || 'Ocurrió un error inesperado.';
            const errors = error.response?.data?.errors;
            let htmlError = `<p>${errorMessage}</p>`;
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
        }
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

/* Esto crea el efecto de acordeón suave al expandir y contraer. */
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.3s ease-out;
    max-height: 1000px;
    /* Altura máxima esperada del contenido */
}

.slide-up-enter-from,
.slide-up-leave-to {
    max-height: 0;
    opacity: 0;
    transform: translateY(-10px);
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

.shake-enter-active {
    animation: shake 0.5s;
}

@keyframes shake {

    10%,
    90% {
        transform: translate3d(-1px, 0, 0);
    }

    20%,
    80% {
        transform: translate3d(2px, 0, 0);
    }

    30%,
    50%,
    70% {
        transform: translate3d(-4px, 0, 0);
    }

    40%,
    60% {
        transform: translate3d(4px, 0, 0);
    }
}

.dj-warning-message {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    /* 8px */
    padding: 0.75rem;
    /* 12px */
    background-color: #fefce8;
    /* bg-yellow-50 */
    border: 1px solid #fde047;
    /* border-yellow-300 */
    border-radius: 0.5rem;
    /* rounded-lg */
    color: #a16207;
    /* text-yellow-800 */
}

.dj-warning-message p {
    margin: 0;
    font-size: 12px;
    line-height: 1.4;
}
</style>