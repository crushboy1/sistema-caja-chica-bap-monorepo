<template>
    <div class="p-4">
        <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">Registrar Nuevo Gasto</h2>

        <div v-if="cargandoInicial" class="text-center text-gray-500 py-10">
            <!-- ... spinner de carga ... -->
        </div>

        <div v-else-if="!fondosActivos.length"
            class="text-center bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-lg">
            <p class="font-bold">No tienes fondos activos</p>
            <p>Para registrar un gasto, primero necesitas tener un fondo de caja chica activo asignado.</p>
        </div>

        <form v-else @submit.prevent="confirmarEnvio" class="space-y-8">
            <div class="p-6 border border-gray-200 rounded-lg bg-gray-50">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Datos del Responsable del Gasto</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="responsable_gasto" class="block text-sm font-medium text-gray-700 mb-1">Responsable
                        </label>
                        <input type="text" id="responsable_gasto"
                            :value="usuarioActual ? `${usuarioActual.name} ${usuarioActual.last_name}` : ''"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                            disabled />
                    </div>
                    <div>
                        <label for="area_responsable" class="block text-sm font-medium text-gray-700 mb-1">Área</label>
                        <input type="text" id="area_responsable" :value="usuarioActual?.area?.name || 'No asignada'"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                            disabled />
                    </div>
                    <div>
                        <label for="cargo_responsable"
                            class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
                        <input type="text" id="cargo_responsable" :value="usuarioActual?.cargo || 'No especificado'"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
                            disabled />
                    </div>
                </div>
            </div>


            <!-- Sección de Datos Generales del Gasto -->
            <div class="p-6 border border-gray-200 rounded-lg bg-gray-50">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Información del Gasto</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <div>
                        <label for="fondo" class="block text-sm font-medium text-gray-700 mb-1">Fondo de Caja Chica
                            <span class="text-rojo-bap">*</span></label>
                        <select id="fondo" v-model="form.id_fondo_efectivo"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                            required>
                            <option disabled value="">Selecciona un fondo</option>
                            <option v-for="fondo in fondosActivos" :key="fondo.id_fondo" :value="fondo.id_fondo">
                                {{ fondo.codigo_fondo }} (Saldo: S/. {{ parseFloat(fondo.monto_disponible ||
                                0).toFixed(2) }})
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="fecha_documento" class="block text-sm font-medium text-gray-700 mb-1">Fecha del
                            Documento <span class="text-rojo-bap">*</span></label>
                        <input type="date" id="fecha_documento" v-model="form.fecha_documento"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                            required />
                    </div>
                    <div>
                        <label for="moneda" class="block text-sm font-medium text-gray-700 mb-1">Moneda del Documento
                            <span class="text-rojo-bap">*</span></label>
                        <select id="moneda" v-model="form.moneda"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                            required>
                            <option value="PEN">Soles (PEN)</option>
                            <option value="USD">Dólares (USD)</option>
                        </select>
                    </div>
                    <div>
                        <label for="monto_total" class="block text-sm font-medium text-gray-700 mb-1">Monto Total (S/.)
                            <span class="text-rojo-bap">*</span></label>
                        <input type="number" id="monto_total" v-model.number="form.monto_total" step="0.01" min="0.01"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                            placeholder="Ej: 25.50" required />
                    </div>

                    <div class="md:col-span-3">
                        <label for="glosa" class="block text-sm font-medium text-gray-700 mb-1">Glosa / Descripción del
                            Gasto <span class="text-rojo-bap">*</span></label>
                        <textarea id="glosa" v-model="form.glosa" rows="3"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                            placeholder="Ej: Movilidad para reunión con cliente en Miraflores" required></textarea>
                    </div>

                    <div>
                        <label for="pertenece_proyecto" class="block text-sm font-medium text-gray-700 mb-1">¿Pertenece
                            a un Proyecto?</label>
                        <select id="pertenece_proyecto" v-model="form.pertenece_proyecto"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                            <option :value="false">No</option>
                            <option :value="true">Sí</option>
                        </select>
                    </div>

                </div>
            </div>


            <!-- Sección de Datos del Comprobante -->
            <div class="p-6 border border-gray-200 rounded-lg bg-gray-50">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Detalles del Comprobante</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="tipo_documento" class="block text-sm font-medium text-gray-700 mb-1">Tipo de
                            Documento <span class="text-rojo-bap">*</span></label>
                        <select id="tipo_documento" v-model="form.tipo_documento"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                            required>
                            <option>Boleta de Venta</option>
                            <option>Factura</option>
                            <option>Recibo por Honorarios</option>
                            <option>Otro</option>
                        </select>
                    </div>

                    <div>
                        <label for="serie_documento" class="block text-sm font-medium text-gray-700 mb-1">Serie</label>
                        <input type="text" id="serie_documento" v-model="form.serie_documento"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                            placeholder="Ej: F001" />
                    </div>

                    <div>
                        <label for="correlativo_documento"
                            class="block text-sm font-medium text-gray-700 mb-1">Correlativo</label>
                        <input type="text" id="correlativo_documento" v-model="form.correlativo_documento"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                            placeholder="Ej: 0012345" />
                    </div>
                </div>
            </div>

            <!-- Sección de Contabilidad y Evidencia -->
            <div class="p-6 border border-gray-200 rounded-lg bg-gray-50">
                <h3 class="text-xl font-semibold text-gray-800 mb-4">Clasificación y Evidencia</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="id_cuenta_contable" class="block text-sm font-medium text-gray-700 mb-1">Cuenta
                            Contable <span class="text-rojo-bap">*</span></label>
                        <select id="id_cuenta_contable" v-model="form.id_cuenta_contable"
                            class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                            required>
                            <option disabled value="">Selecciona una cuenta</option>
                            <option v-for="cuenta in cuentasContables" :key="cuenta.id" :value="cuenta.id">
                                {{ cuenta.codigo_cuenta }} - {{ cuenta.descripcion }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label for="evidencia" class="block text-sm font-medium text-gray-700 mb-1">Archivo de Evidencia
                            <span class="text-rojo-bap">*</span></label>
                        <input type="file" id="evidencia" @change="handleFileChange"
                            class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-verde-bap-light file:text-verde-bap-dark hover:file:bg-verde-bap-light/80"
                            required>
                        <p v-if="form.evidencia" class="text-xs text-gray-500 mt-1">Archivo seleccionado: {{
                            form.evidencia.name }}</p>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="flex items-center">
                        <input type="checkbox" v-model="form.es_declaracion_jurada"
                            class="h-4 w-4 text-verde-bap rounded border-gray-300 focus:ring-verde-bap-dark">
                        <span class="ml-2 text-sm text-gray-600">Marcar si este gasto se sustenta con Declaración
                            Jurada</span>
                    </label>
                </div>

                <div class="md:col-span-2">
                    <label for="comentario" class="block text-sm font-medium text-gray-700 mb-1">Comentario
                        (Opcional)</label>
                    <textarea id="comentario" v-model="form.comentario" rows="2"
                        class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                        placeholder="Añade cualquier nota adicional aquí..."></textarea>
                </div>
            </div>

            <!-- Botones de Acción -->
            <div class="flex justify-end space-x-4">
                <button type="button" @click="$emit('close')"
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-3 px-8 rounded-full transition-colors shadow-lg">Cancelar</button>
                <button type="submit" :disabled="enviando"
                    class="bg-verde-bap hover:bg-verde-bap-dark text-white font-bold py-3 px-8 rounded-full transition-colors shadow-lg flex items-center justify-center">
                    <svg v-if="enviando" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    {{ enviando ? 'Enviando...' : 'Guardar Gasto' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>


import { ref, onMounted } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

const emit = defineEmits(['close', 'gastoCreado']);

// --- Estado del Componente ---
const form = ref({
    id_fondo_efectivo: '',
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
const usuarioActual = ref(null);
const fondosActivos = ref([]);
const cuentasContables = ref([]);
const cargandoInicial = ref(true);
const enviando = ref(false);

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
        usuarioActual.value = response.data.user;
    } catch (error) {
        console.error("Error al obtener datos del usuario:", error);
    }
};
const obtenerFondosActivos = async () => {
    try {
        const response = await api.get('/fondos-efectivo', { params: { estado: 'Activo' } });
        fondosActivos.value = response.data.fondos;
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

const handleFileChange = (event) => {
    form.value.evidencia = event.target.files[0];
};

const confirmarEnvio = () => {
    Swal.fire({
        title: '¿Confirmar Registro?',
        text: "Se registrará un nuevo gasto. ¿Deseas continuar?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#34D399',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Sí, registrar',
        cancelButtonText: 'Cancelar'
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
        if (key === 'es_declaracion_jurada') {
            formData.append(key, form.value[key] ? 1 : 0);
        } else {
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
            htmlError += '<ul class="text-left mt-2">';
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

<!-- No se necesita la etiqueta <style scoped> -->
