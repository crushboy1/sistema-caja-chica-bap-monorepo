<template>
    <!-- Contenedor principal del formulario con estilos de padding y fondo. -->
    <div class="p-6 bg-gray-50 min-h-screen">
        <h2 class="text-3xl font-bold text-gray-800 mb-8 text-center">Registrar Declaración de Gastos</h2>

        <!-- Estado de Carga Inicial -->
        <div v-if="cargandoInicial" class="text-center text-gray-500 py-10">
            <p>Cargando información inicial...</p>
        </div>

        <!-- Mensaje si el usuario no tiene fondos activos -->
        <div v-else-if="!fondosActivos.length"
            class="text-center bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow-md max-w-2xl mx-auto">
            <p class="font-bold">No se encontraron fondos activos para tu área.</p>
            <p class="mt-1">Para registrar gastos, tu área debe tener un fondo de caja chica activo.</p>
        </div>
        <!-- Formulario Principal: se muestra solo si hay fondos activos -->
        <form v-else @submit.prevent="confirmarEnvio" class="space-y-6 max-w-5xl mx-auto">

            <!-- PASO 1: SELECCIÓN DEL FONDO -->
            <div class="p-6 border border-gray-200 rounded-lg bg-white shadow-sm">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">1. Selección del Fondo de Caja Chica
                </h3>
                <div>
                    <label for="fondo" class="block text-sm font-medium text-gray-700 mb-1">Fondo de Caja Chica <span
                            class="text-rojo-bap">*</span></label>
                    <select id="fondo" v-model="fondoSeleccionadoId"
                        class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                        required>
                        <option disabled value="">Selecciona un fondo</option>
                        <option v-for="fondo in fondosActivos" :key="fondo.id_fondo" :value="fondo.id_fondo">
                            {{ fondo.codigo_fondo }} (Aprobado: S/. {{ parseFloat(fondo.monto_aprobado).toFixed(2) }})
                        </option>
                    </select>
                    <p v-if="fondoSeleccionadoId" class="text-xs text-gray-500 mt-1">
                        Saldo Disponible Actual: S/. {{ parseFloat(fondoSeleccionado?.monto_disponible || 0).toFixed(2)
                        }}
                    </p>
                </div>
            </div>
            <!-- El resto del formulario se muestra solo si se ha seleccionado un fondo -->
            <template v-if="fondoSeleccionadoId">
                <div v-for="(gasto, index) in gastosADeclarar" :key="gasto.id"
                    class="p-6 border border-gray-200 rounded-xl bg-white shadow-soft relative animate-fade-in-up"
                    :style="{ animationDelay: `${index * 100}ms` }">
                    <!-- Encabezado y botón de eliminar para cada tarjeta de gasto -->
                    <div class="flex justify-between items-center border-b pb-2 mb-4">
                        <h3 class="text-xl font-semibold text-gray-700">Detalle del Gasto #{{ index + 1 }}</h3>
                        <button v-if="gastosADeclarar.length > 1" @click="removerGasto(index)" type="button" 
                                :class="getClassesForActionButton('error')"
                                class="px-3 py-1 text-xs rounded-full"
                                title="Eliminar este gasto">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                        </button>
                    </div>
                    <!-- Todos los campos del formulario ahora están vinculados a `gasto` y su `index` -->
                    <div class="space-y-4">
                        <!-- Fila 1: Gasto Proyectado, Fecha y Monto -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-2">
                                <label :for="'proyeccion_' + index"
                                    class="block text-sm font-medium text-gray-700 mb-1 w-full">Gasto Proyectado <span
                                        class="text-rojo-bap">*</span></label>
                                <select :id="'proyeccion_' + index" v-model="gasto.detalle_gasto_proyectado_id"
                                    :disabled="cargandoProyecciones" class="mt-1 block w-full p-3 border rounded-md focus:border-verde-bap focus:ring-verde-bap"
                                    required>
                                    <option disabled value="">{{ cargandoProyecciones ? 'Cargando...' : 'Selecciona una proyección' }}</option>
                                    <option v-if="!cargandoProyecciones && !proyeccionesPendientes.length" value=""
                                        disabled>No hay proyecciones pendientes</option>
                                    <option v-for="p in proyeccionesDisponibles(index)" :key="p.id" :value="p.id">
                                        {{ p.descripcion_gasto }} (Disponible: {{
                                            currencyFormatter.format(p.saldo_restante) }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label :for="'fecha_documento_' + index"
                                    class="block text-sm font-medium text-gray-700 mb-1">Fecha del Documento <span
                                        class="text-rojo-bap">*</span></label>
                                <input type="date" :id="'fecha_documento_' + index" v-model="gasto.fecha_documento"
                                    class="mt-1 block w-full p-3 border rounded-lg focus:border-verde-bap focus:ring-verde-bap" required />
                            </div>
                            <div>
                                <label :for="'monto_total_' + index"
                                    class="block text-sm font-medium text-gray-700 mb-1">Monto del Documento (S/.) <span
                                        class="text-rojo-bap">*</span></label>
                                <input type="number" :id="'monto_total_' + index" v-model.number="gasto.monto_total"
                                    :max="getSaldoMaximoParaGasto(gasto.detalle_gasto_proyectado_id, index)"
                                    :class="getMontoInputClasses(gasto, index)" step="0.01" min="0.01"
                                    @input="validarMontoEnTiempoReal(gasto, index)"
                                    class="mt-1 block w-full p-3 border rounded-lg " required />

                                <!-- Mensaje de saldo disponible -->
                                <div v-if="gasto.detalle_gasto_proyectado_id" class="mt-1">
                                    <p class="text-xs text-gray-500">
                                        Saldo disponible: {{
                                            currencyFormatter.format(getSaldoMaximoParaGasto(gasto.detalle_gasto_proyectado_id,
                                        index)) }}
                                    </p>
                                    <!-- Mensaje de error para monto excedido -->
                                    <p v-if="getErrorMontoExcedido(gasto, index)"
                                        class="text-xs text-red-600 font-medium">
                                        {{ getErrorMontoExcedido(gasto, index) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Fila 2: Detalles del Documento -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label :for="'tipo_documento_' + index"
                                    class="block text-sm font-medium text-gray-700 mb-1">Tipo de Documento <span
                                        class="text-rojo-bap">*</span></label>
                                <select :id="'tipo_documento_' + index" v-model="gasto.tipo_documento"
                                    @change="onTipoDocumentoChange(gasto)"
                                    class="mt-1 block w-full p-3 border rounded-lg focus:border-verde-bap focus:ring-verde-bap" required>
                                    <option disabled value="">Selecciona un tipo</option>
                                    <option>Boleta de Venta</option>
                                    <option>Factura</option>
                                    <option>Recibo por Honorarios</option>
                                    <option>Declaración Jurada</option>
                                    <option>Otro</option>
                                </select>
                            </div>
                            <div>
                                <label :for="'serie_documento_' + index"
                                    class="block text-sm font-medium text-gray-700 mb-1">Serie</label>
                                <input type="text" :id="'serie_documento_' + index" v-model="gasto.serie_documento"
                                    :disabled="gasto.tipo_documento === 'Declaración Jurada'"
                                    class="mt-1 block w-full p-3 border rounded-lg disabled:bg-gray-200 disabled:cursor-not-allowed focus:border-verde-bap focus:ring-verde-bap"
                                    placeholder="Ej: F001" />
                            </div>
                            <div>
                                <label :for="'correlativo_documento_' + index"
                                    class="block text-sm font-medium text-gray-700 mb-1">Correlativo</label>
                                <input type="text" :id="'correlativo_documento_' + index"
                                    v-model="gasto.correlativo_documento"
                                    :disabled="gasto.tipo_documento === 'Declaración Jurada'"
                                    class="mt-1 block w-full p-3 border rounded-lg disabled:bg-gray-200 disabled:cursor-not-allowed focus:border-verde-bap focus:ring-verde-bap"
                                    placeholder="Ej: 0012345" />
                            </div>
                        </div>

                        <!-- Fila 3: Clasificación Contable -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                            <div>
                                <label :for="'id_cuenta_contable_' + index"
                                    class="block text-sm font-medium text-gray-700 mb-1">Cuenta Contable <span
                                        class="text-rojo-bap">*</span></label>
                                <select :id="'id_cuenta_contable_' + index" v-model="gasto.id_cuenta_contable"
                                    class="mt-1 block w-full p-3 border rounded-lg focus:border-verde-bap focus:ring-verde-bap" required>
                                    <option disabled value="">Selecciona una cuenta</option>
                                    <option v-for="cuenta in cuentasContables" :key="cuenta.id" :value="cuenta.id">{{
                                        cuenta.codigo_cuenta }} - {{ cuenta.descripcion }}</option>
                                </select>
                            </div>
                            <div>
                                <label :for="'glosa_' + index"
                                    class="block text-sm font-medium text-gray-700 mb-1">Glosa para el Asiento</label>
                                <input type="text" :id="'glosa_' + index" v-model="gasto.glosa"
                                    class="mt-1 block w-full p-3 border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                                    placeholder="Se asigna automáticamente" disabled />
                            </div>
                        </div>

                        <!-- Fila 4: Evidencia y Opciones -->
                        <div>
                            <label :for="'evidencia_' + index"
                                class="block text-sm font-medium text-gray-700 mb-1">Archivo de Evidencia <span
                                    class="text-rojo-bap">*</span></label>
                            <input type="file" :id="'evidencia_' + index" @change="handleFileChange($event, index)"
                                class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0"
                                required>
                        </div>
                        <div class="flex items-center mt-4">
                            <input type="checkbox" :id="'dj_' + index" v-model="gasto.es_declaracion_jurada"
                                @change="onDeclaracionJuradaChange(gasto)" class="h-4 w-4 text-verde-bap rounded">
                            <label :for="'dj_' + index" class="ml-3 text-sm text-gray-600">Este gasto se sustenta con
                                Declaración Jurada</label>
                        </div>

                        <!-- Botón centrado debajo del checkbox -->
                        <div v-if="gasto.es_declaracion_jurada" class="flex justify-center mt-4">
                            <button type="button" @click="generarYDescargarDJ(gasto)"
                                :disabled="!gasto.monto_total || !gasto.glosa"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full transition-colors shadow-md flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                </svg>
                                Generar Plantilla DJ
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Botón para añadir más gastos -->
                <div class="flex justify-center pt-4">
                    <button @click="agregarGasto" type="button" 
                            :class="getClassesForActionButton('info')"
                            class="font-bold py-2 px-6 rounded-full shadow-lg transition-transform transform hover:scale-105">
                        <svg class="w-5 h-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        Añadir Otro Gasto
                    </button>
                </div>

                <!-- Botones de Acción Finales -->
                <div class="flex justify-end space-x-4 pt-6 mt-6 border-t">
                    <button type="button" @click="$emit('close')" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-8 rounded-full">Cancelar</button>
                    <button type="submit" :disabled="enviando || hayErroresValidacion" 
                            :class="getClassesForActionButton('exito')"
                            class="font-bold py-3 px-8 rounded-full min-w-[200px] disabled:opacity-50 disabled:cursor-not-allowed">
                        <span>{{ enviando ? 'Enviando...' : 'Guardar Declaración' }}</span>
                    </button>
                </div>
            </template>
        </form>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { getClassesForActionButton } from '@/utils/statusStyles.js';
const emit = defineEmits(['close', 'gastoCreado']);

// --- ESTADO REACTIVO DEL COMPONENTE ---

// Datos del formulario
const gastosADeclarar = ref([]);

// Datos de soporte y UI
const usuarioActual = ref(null);
const fondosActivos = ref([]);
const fondoSeleccionadoId = ref(''); // ID del fondo que el usuario selecciona
const proyeccionesPendientes = ref([]); // Lista de proyecciones para el fondo seleccionado
const cuentasContables = ref([]);
const cargandoInicial = ref(true);
const cargandoProyecciones = ref(false);
const enviando = ref(false);
const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

// --- LÓGICA DE CARGA DE DATOS ---

onMounted(async () => {
    cargandoInicial.value = true;
    await Promise.all([
        obtenerUsuarioLogueado(),
        obtenerFondosActivos(),
        obtenerCuentasContables()
    ]);
    agregarGasto();
    cargandoInicial.value = false;
});

const obtenerUsuarioLogueado = async () => {
    try {
        const response = await api.get('/user');
        usuarioActual.value = response.data;
    } catch (error) {
        console.error("Error al obtener datos del usuario:", error);
    }
};

const obtenerFondosActivos = async () => {
    try {
        const response = await api.get('/fondos-activos-usuario');
        fondosActivos.value = response.data;
        // Si solo hay un fondo, se pre-selecciona automáticamente.
        if (fondosActivos.value.length === 1) {
            fondoSeleccionadoId.value = fondosActivos.value[0].id_fondo;
        }
    } catch (error) {
        console.error("Error al obtener fondos activos:", error);
        Swal.fire('Error', 'No se pudieron cargar tus fondos activos.', 'error');
    }
};

const obtenerCuentasContables = async () => {
    try {
        const response = await api.get('/cuentas-contables');
        cuentasContables.value = response.data;
    } catch (error) {
        console.error("Error al obtener cuentas contables:", error);
        Swal.fire('Error', 'No se pudieron cargar las cuentas contables.', 'error');
    }
};

// --- COMPUTED PROPERTIES ---

// Propiedad computada para calcular saldos disponibles por proyección
const saldosProyecciones = computed(() => {
    const saldos = new Map();
    
    // 1. Inicializa el mapa con los montos estimados originales
    proyeccionesPendientes.value.forEach(p => {
        saldos.set(p.id, parseFloat(p.monto_estimado));
    });

    // 2. Resta los montos de los gastos que ya están siendo declarados
    gastosADeclarar.value.forEach(gasto => {
        if (gasto.detalle_gasto_proyectado_id && saldos.has(gasto.detalle_gasto_proyectado_id)) {
            const saldoActual = saldos.get(gasto.detalle_gasto_proyectado_id);
            const montoDeclarado = parseFloat(gasto.monto_total) || 0;
            saldos.set(gasto.detalle_gasto_proyectado_id, saldoActual - montoDeclarado);
        }
    });

    return saldos;
});

// Función para obtener proyecciones disponibles para un gasto específico
const proyeccionesDisponibles = (indexGastoActual) => {
    const proyeccionSeleccionadaActual = gastosADeclarar.value[indexGastoActual]?.detalle_gasto_proyectado_id;

    return proyeccionesPendientes.value
        .map(p => ({
            ...p,
            saldo_restante: saldosProyecciones.value.get(p.id) || 0,
        }))
        .filter(p => {
            return p.id === proyeccionSeleccionadaActual || p.saldo_restante > 0.005;
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
    // Al cambiar de fondo, se resetea el formulario de gastos
    gastosADeclarar.value = [];
    agregarGasto();

    if (newFondoId) {
        cargandoProyecciones.value = true;
        try {
            const response = await api.get(`/fondos-efectivo/${newFondoId}/proyecciones-pendientes`);
            proyeccionesPendientes.value = response.data;
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
const agregarGasto = () => {
    gastosADeclarar.value.push({
        id: Date.now(), // ID temporal para el :key en el v-for
        detalle_gasto_proyectado_id: '',
        fecha_documento: new Date().toISOString().slice(0, 10),
        tipo_documento: '',
        serie_documento: '',
        correlativo_documento: '',
        monto_total: null,
        id_cuenta_contable: '',
        glosa: '',
        pertenece_proyecto: false,
        comentario: '',
        evidencia: null,
        es_declaracion_jurada: false,
    });
};

// Función para remover una fila de gasto
const removerGasto = (index) => {
    if (gastosADeclarar.value.length > 1) {
        gastosADeclarar.value.splice(index, 1);
    } else {
        Swal.fire('Acción no permitida', 'Debe declarar al menos un gasto.', 'warning');
    }
};

// --- MÉTODOS DE VALIDACIÓN Y CÁLCULO ---
// Obtener el saldo máximo disponible para un gasto específico
const getSaldoMaximoParaGasto = (proyeccionId, indexGastoActual) => {
    if (!proyeccionId) return 0;
    
    const proyeccion = proyeccionesPendientes.value.find(p => p.id === proyeccionId);
    if (!proyeccion) return 0;
    
    // Calcular cuánto se ha usado de esta proyección en otros gastos
    let montoUsadoEnOtrosGastos = 0;
    gastosADeclarar.value.forEach((gasto, index) => {
        if (index !== indexGastoActual && gasto.detalle_gasto_proyectado_id === proyeccionId) {
            montoUsadoEnOtrosGastos += parseFloat(gasto.monto_total) || 0;
        }
    });
    
    return parseFloat(proyeccion.monto_estimado) - montoUsadoEnOtrosGastos;
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
        const response = await api.post('/documentos/generar-dj', {
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
        const response = await api.post('/gastos', formDataPayload, {
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
</style>