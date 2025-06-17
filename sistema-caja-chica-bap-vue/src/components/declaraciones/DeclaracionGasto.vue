<template>
    <!-- Contenedor principal del formulario con estilos de padding y fondo. -->
    <div class="p-6 bg-gray-50 min-h-screen">
        <h2 class="text-3xl font-bold text-gray-800 mb-8 text-center">Registrar Declaración de Gasto</h2>

        <!-- Estado de Carga Inicial -->
        <div v-if="cargandoInicial" class="text-center text-gray-500 py-10">
            <p>Cargando información inicial...</p>
            <!-- Aquí podría ir un componente de spinner más elaborado -->
        </div>

        <!-- Mensaje si el usuario no tiene fondos activos en su área -->
        <div v-else-if="!fondosActivos.length"
            class="text-center bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg shadow-md max-w-2xl mx-auto">
            <p class="font-bold">No se encontraron fondos activos para tu área.</p>
            <p class="mt-1">Para registrar un gasto, tu área debe tener un fondo de caja chica activo.</p>
        </div>

        <!-- Formulario Principal: se muestra solo si hay fondos activos -->
        <form v-else @submit.prevent="confirmarEnvio" class="space-y-6 max-w-4xl mx-auto">

            <!-- PASO 1: SELECCIÓN DEL FONDO Y PROYECCIÓN -->
            <div class="p-6 border border-gray-200 rounded-lg bg-white shadow-sm">
                <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">1. Seleccione el Gasto a Declarar
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Desplegable para seleccionar el Fondo del Área -->
                    <div>
                        <label for="fondo" class="block text-sm font-medium text-gray-700 mb-1">Fondo de Caja Chica
                            <span class="text-rojo-bap">*</span></label>
                        <select id="fondo" v-model="fondoSeleccionadoId"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                            required>
                            <option disabled value="">Selecciona un fondo</option>
                            <option v-for="fondo in fondosActivos" :key="fondo.id_fondo" :value="fondo.id_fondo">
                                {{ fondo.codigo_fondo }} (Aprobado: S/. {{ parseFloat(fondo.monto_aprobado).toFixed(2)
                                }})
                            </option>
                        </select>
                        <p v-if="fondoSeleccionadoId" class="text-xs text-gray-500 mt-1">
                            Saldo Disponible Actual: S/. {{ parseFloat(fondoSeleccionado?.monto_disponible ||
                                0).toFixed(2) }}
                        </p>
                    </div>

                    <!-- Desplegable para seleccionar el Gasto Proyectado (se activa al elegir un fondo) -->
                    <div>
                        <label for="proyeccion" class="block text-sm font-medium text-gray-700 mb-1">Gasto Proyectado a
                            Declarar <span class="text-rojo-bap">*</span></label>
                        <select id="proyeccion" v-model="form.detalle_gasto_proyectado_id"
                            :disabled="!fondoSeleccionadoId || cargandoProyecciones"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap disabled:bg-gray-200 disabled:cursor-not-allowed"
                            required>
                            <option disabled value="">{{ cargandoProyecciones ? 'Cargando...' : 'Selecciona una proyección' }}</option>
                            <option
                                v-if="!cargandoProyecciones && !proyeccionesPendientes.length && fondoSeleccionadoId"
                                value="" disabled>No hay proyecciones pendientes</option>
                            <option v-for="proyeccion in proyeccionesPendientes" :key="proyeccion.id"
                                :value="proyeccion.id">
                                {{ proyeccion.descripcion_gasto }} (Est. S/. {{
                                    parseFloat(proyeccion.monto_estimado).toFixed(2) }})
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- El resto del formulario se muestra solo si se ha seleccionado una proyección -->
            <template v-if="form.detalle_gasto_proyectado_id">

                <!-- PASO 2: DETALLES DEL COMPROBANTE -->
                <div class="p-6 border border-gray-200 rounded-xl bg-white shadow-soft">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">2. Detalles del Gasto y
                        Comprobante</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Campos añadidos según prototipo -->
                        <div>
                            <label for="fecha_documento" class="block text-sm font-medium text-gray-700 mb-1">Fecha del
                                Documento <span class="text-rojo-bap">*</span></label>
                            <input type="date" id="fecha_documento" v-model="form.fecha_documento"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap"
                                required />
                        </div>
                        <div>
                            <label for="moneda" class="block text-sm font-medium text-gray-700 mb-1">Moneda del
                                Documento <span class="text-rojo-bap">*</span></label>
                            <input type="text" id="moneda" value="Soles (PEN)"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                                disabled />
                        </div>
                        <div>
                            <label for="monto_total" class="block text-sm font-medium text-gray-700 mb-1">Monto del
                                Documento (S/.) <span class="text-rojo-bap">*</span></label>
                            <input type="number" id="monto_total" v-model.number="form.monto_total" step="0.01"
                                min="0.01"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap"
                                placeholder="Ej: 25.50" required />
                        </div>

                        <div class="md:col-span-3">
                            <label for="responsable_gasto"
                                class="block text-sm font-medium text-gray-700 mb-1">Responsable del Gasto</label>
                            <input type="text" id="responsable_gasto"
                                :value="usuarioActual ? `${usuarioActual.name} ${usuarioActual.last_name}` : ''"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                                disabled />
                        </div>

                        <div>
                            <label for="tipo_documento" class="block text-sm font-medium text-gray-700 mb-1">Tipo de
                                Documento</label>
                            <select id="tipo_documento" v-model="form.tipo_documento"
                                :disabled="form.es_declaracion_jurada"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap disabled:bg-gray-200">
                                <option>Boleta de Venta</option>
                                <option>Factura</option>
                                <option>Recibo por Honorarios</option>
                                <option>Declaración Jurada</option>
                                <option>Otro</option>
                            </select>
                        </div>
                        <div>
                            <label for="serie_documento"
                                class="block text-sm font-medium text-gray-700 mb-1">Serie</label>
                            <input type="text" id="serie_documento" v-model="form.serie_documento"
                                :disabled="form.es_declaracion_jurada"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap disabled:bg-gray-200"
                                placeholder="Ej: F001" />
                        </div>
                        <div>
                            <label for="correlativo_documento"
                                class="block text-sm font-medium text-gray-700 mb-1">Correlativo</label>
                            <input type="text" id="correlativo_documento" v-model="form.correlativo_documento"
                                :disabled="form.es_declaracion_jurada"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap disabled:bg-gray-200"
                                placeholder="Ej: 0012345" />
                        </div>
                    </div>
                </div>

                <!-- PASO 3: CLASIFICACIÓN CONTABLE Y EVIDENCIA -->
                <div class="p-6 border border-gray-200 rounded-xl bg-white shadow-soft">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-2">3. Clasificación y Comentarios
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                        <div>

                            <label for="id_cuenta_contable"
                                class="flex items-center text-sm font-medium text-gray-700 mb-1">
                                Cuenta Contable <span class="text-rojo-bap ml-1">*</span>
                                <div class="relative ml-2 group">
                                    <svg class="w-4 h-4 text-gray-400 cursor-pointer" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div
                                        class="absolute z-10 bottom-full mb-2 w-64 bg-gray-800 text-white text-xs rounded-lg py-2 px-3 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none shadow-lg">
                                        Seleccione la categoría que mejor describa su gasto.
                                        <br><b>Ej:</b> Para un taxi, use 'Movilidad Local'.
                                        <br><b>Ej:</b> Para útiles, use 'Útiles de Escritorio'.
                                    </div>
                                </div>
                            </label>
                            <select id="id_cuenta_contable" v-model="form.id_cuenta_contable"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap"
                                required>
                                <option disabled value="">Selecciona una cuenta</option>
                                <option v-for="cuenta in cuentasContables" :key="cuenta.id" :value="cuenta.id">
                                    {{ cuenta.codigo_cuenta }} - {{ cuenta.descripcion }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label for="glosa" class="block text-sm font-medium text-gray-700 mb-1">Glosa para el
                                Asiento</label>
                            <input type="text" id="glosa" v-model="form.glosa"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                                placeholder="Se asigna automáticamente" disabled />
                        </div>
                        <div>
                            <label for="pertenece_proyecto"
                                class="block text-sm font-medium text-gray-700 mb-1">¿Pertenece a un Proyecto?</label>
                            <select id="pertenece_proyecto" v-model="form.pertenece_proyecto"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap">
                                <option :value="false">No</option>
                                <option :value="true">Sí</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="comentario" class="block text-sm font-medium text-gray-700 mb-1">Comentario
                                (Opcional)</label>
                            <textarea id="comentario" v-model="form.comentario" rows="2"
                                class="mt-1 block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap resize-none"
                                placeholder="Añade cualquier nota adicional aquí..."></textarea>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label for="evidencia" class="block text-sm font-medium text-gray-700 mb-1">Archivo de Evidencia
                            (Boleta, Factura, DJ Firmada) <span class="text-rojo-bap">*</span></label>
                        <input type="file" id="evidencia" @change="handleFileChange"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-verde-bap-light file:text-verde-bap-dark hover:file:bg-verde-bap-light/80"
                            required>
                        <p v-if="form.evidencia" class="text-xs text-gray-500 mt-1">Archivo seleccionado: {{
                            form.evidencia.name }}</p>
                    </div>

                    <div class="mt-6 flex items-center justify-between">
                        <label class="flex items-center">
                            <input type="checkbox" v-model="form.es_declaracion_jurada"
                                class="h-4 w-4 text-verde-bap rounded-lg border-gray-300 focus:ring-verde-bap-dark">
                            <span class="ml-3 text-sm text-gray-600">Este gasto se sustenta con Declaración
                                Jurada</span>
                        </label>
                        <button v-if="form.es_declaracion_jurada" type="button" @click="generarYDescargarDJ"
                            :disabled="!form.monto_total"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-full transition-colors shadow-md flex items-center disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                            </svg>
                            Generar Plantilla DJ
                        </button>
                    </div>
                </div>

                <!-- Botones de Acción Finales -->
                <div class="flex justify-end space-x-4 pt-4">
                    <button type="button" @click="$emit('close')"
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-8 rounded-full transition-colors shadow-lg">Cancelar</button>
                    <button type="submit" :disabled="enviando"
                        class="bg-verde-bap hover:bg-verde-bap-dark text-white font-bold py-3 px-8 rounded-full transition-colors shadow-lg flex items-center justify-center min-w-[150px]">
                        <svg v-if="enviando" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        <span>{{ enviando ? 'Enviando...' : 'Guardar Gasto' }}</span>
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

const emit = defineEmits(['close', 'gastoCreado']);

// --- ESTADO REACTIVO DEL COMPONENTE ---

// Datos del formulario
const form = ref({
    detalle_gasto_proyectado_id: '',
    fecha_documento: new Date().toISOString().slice(0, 10),
    tipo_documento: 'Boleta de Venta',
    serie_documento: '',
    correlativo_documento: '',
    moneda: 'PEN',
    monto_total: null,
    id_cuenta_contable: '',
    glosa: '',
    pertenece_proyecto: false,
    comentario: '',
    evidencia: null,
    es_declaracion_jurada: false,
});

// Datos de soporte y UI
const usuarioActual = ref(null);
const fondosActivos = ref([]);
const fondoSeleccionadoId = ref(''); // ID del fondo que el usuario selecciona
const proyeccionesPendientes = ref([]); // Lista de proyecciones para el fondo seleccionado
const cuentasContables = ref([]);
const cargandoInicial = ref(true);
const cargandoProyecciones = ref(false);
const enviando = ref(false);

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
        const response = await api.get('/user');
        usuarioActual.value = response.data;
    } catch (error) {
        console.error("Error al obtener datos del usuario:", error);
        // Manejo de error
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
// Propiedad computada para obtener el objeto completo del fondo seleccionado.
const fondoSeleccionado = computed(() => {
    return fondosActivos.value.find(f => f.id_fondo === fondoSeleccionadoId.value);
});


// --- WATCHERS (Observadores de cambios) ---

// WATCHER 1: Observa cambios en el fondo seleccionado para cargar sus proyecciones.
watch(fondoSeleccionadoId, async (newFondoId) => {
    // Limpiar selecciones anteriores
    proyeccionesPendientes.value = [];
    form.value.detalle_gasto_proyectado_id = '';

    if (newFondoId) {
        cargandoProyecciones.value = true;
        try {
            // Se llama al nuevo endpoint para obtener solo las proyecciones pendientes.
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

// WATCHER 2: Observa la cuenta contable seleccionada para autocompletar la glosa.
watch(() => form.value.id_cuenta_contable, (newCuentaId) => {
    if (newCuentaId) {
        const cuenta = cuentasContables.value.find(c => c.id === newCuentaId);
        if (cuenta) {
            // La glosa será la descripción de la cuenta contable.
            form.value.glosa = cuenta.descripcion;
        }
    } else {
        form.value.glosa = '';
    }
});

// WATCHER 3: Observa si se marca la Declaración Jurada para ajustar el formulario.
watch(() => form.value.es_declaracion_jurada, (isDJ) => {
    if (isDJ) {
        form.value.tipo_documento = 'Declaración Jurada';
        form.value.serie_documento = '';
        form.value.correlativo_documento = '';
    } else {
        form.value.tipo_documento = 'Boleta de Venta';
    }
});

// --- MÉTODOS DE ACCIÓN ---

const handleFileChange = (event) => {
    form.value.evidencia = event.target.files[0];
};

const generarYDescargarDJ = async () => {
    if (!form.value.monto_total || !form.value.glosa) {
        Swal.fire('Datos incompletos', 'Por favor, ingrese el Monto Total y la Glosa del gasto antes de generar la DJ.', 'warning');
        return;
    }

    try {
        const response = await api.post('/documentos/generar-dj', {
            monto: form.value.monto_total,
            glosa: form.value.glosa,
        }, {
            responseType: 'blob' // ¡MUY IMPORTANTE para recibir archivos!
        });

        // Crear un enlace temporal para iniciar la descarga del archivo
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
        Swal.fire('Error', 'No se pudo generar el documento PDF. Por favor, contacta a soporte.', 'error');
    }
};

const confirmarEnvio = () => {
    const proyeccion = proyeccionesPendientes.value.find(p => p.id === form.value.detalle_gasto_proyectado_id);
    const cuenta = cuentasContables.value.find(c => c.id === form.value.id_cuenta_contable);

    const resumenHtml = `
    <div class="text-left text-sm space-y-3 p-2 bg-gray-50 rounded-lg border">
        <div class="flex justify-between"><strong>Fecha Doc.:</strong><span>${form.value.fecha_documento}</span></div>
        <div class="flex justify-between"><strong>Tipo Doc.:</strong><span>${form.value.tipo_documento}</span></div>
        <div class="flex justify-between"><strong>Serie-Correlativo:</strong><span>${form.value.serie_documento || 'N/A'} - ${form.value.correlativo_documento || 'N/A'}</span></div>
        <div class="flex justify-between font-bold text-base text-verde-bap-dark"><strong>Monto Total:</strong><span>S/. ${parseFloat(form.value.monto_total || 0).toFixed(2)}</span></div>
        <hr class="my-2"/>
        <div class="text-center text-xs text-gray-500 pt-1">CLASIFICACIÓN</div>
        <div class="flex justify-between"><strong>Proyección:</strong><span class="text-right pl-2">${proyeccion?.descripcion_gasto || 'N/A'}</span></div>
        <div class="flex justify-between"><strong>Cta. Contable:</strong><span class="text-right pl-2">${cuenta?.codigo_cuenta || 'N/A'}</span></div>
        <div class="flex justify-between"><strong>Glosa:</strong><span class="text-right pl-2">${form.value.glosa}</span></div>
    </div>
    <p class="mt-4 text-gray-700">¿Desea registrar este gasto?</p>
    `;

    Swal.fire({
        title: 'Revisar y Confirmar Gasto',
        html: resumenHtml,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Sí, Registrar',
        cancelButtonText: 'Cancelar',
        customClass: {
            title: 'text-2xl font-bold text-gray-800',
            htmlContainer: 'text-base',
        }
    }).then((result) => {
        if (result.isConfirmed) {
            enviarFormulario();
        }
    });
};

const enviarFormulario = async () => {
    enviando.value = true;
    const formData = new FormData();
    for (const key in form.value) {
        if (key === 'es_declaracion_jurada' || key === 'pertenece_proyecto') {
            formData.append(key, form.value[key] ? 1 : 0);
        } else if (form.value[key] !== null) {
            formData.append(key, form.value[key]);
        }
    }

    try {
        const response = await api.post('/gastos', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        Swal.fire({
            icon: 'success',
            title: '¡Gasto Registrado!',
            text: `El gasto con código ${response.data.gasto.codigo_gasto} ha sido registrado y enviado para aprobación.`,
        });
        emit('gastoCreado');
        emit('close');
    } catch (error) {
        console.error("Error al registrar el gasto:", error);
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
