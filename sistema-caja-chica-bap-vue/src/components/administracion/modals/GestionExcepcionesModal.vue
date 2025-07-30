<template>
    <Transition name="modal-fade">
        <div v-if="mostrar" class="fixed inset-0 bg-black bg-opacity-60 z-50 flex items-center justify-center p-4"
            @click.self="closeModal">
            <div
                class="bg-white rounded-2xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col transform transition-transform duration-300">
                <header
                    class="flex items-center justify-between p-5 border-b border-gray-200 bg-gray-800 text-white rounded-t-2xl">
                    <div>
                        <h3 class="text-xl font-bold">Gestionar Excepciones para {{
                            formatPeriodo(periodoSeleccionado.periodo) }}</h3>
                        <p class="text-sm text-gray-300">Otorga o revoca permisos para registrar gastos en este período
                            cerrado.</p>
                    </div>
                    <button @click="closeModal"
                        class="p-2 rounded-full text-gray-300 hover:bg-black/20 transition-colors">
                        <X class="w-6 h-6" />
                    </button>
                </header>

                <main class="flex-grow overflow-y-auto p-6 space-y-6">
                    <div v-if="cargando" class="text-center py-10">
                        <Loader2 class="animate-spin h-8 w-8 mx-auto mb-2 text-gray-500" />
                        Cargando información...
                    </div>
                    <div v-else>
                        <!-- Formulario para Otorgar Nueva Excepción -->
                        <div class="bg-gray-50 rounded-xl p-6 border border-gray-200">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Otorgar Nueva Excepción</h4>
                            <form @submit.prevent="confirmarOtorgarExcepcion" class="space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="usuario-excepcion"
                                            class="block text-sm font-medium text-gray-700 mb-1">Usuario</label>
                                        <select id="usuario-excepcion" v-model="nuevaExcepcion.id_usuario_excepcion"
                                            class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                                            required>
                                            <option disabled value="">Seleccione un usuario</option>
                                            <option v-for="user in usuarios" :key="user.id" :value="user.id">{{
                                                user.name }} {{ user.last_name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="fecha-expiracion"
                                            class="block text-sm font-medium text-gray-700 mb-1">Fecha de
                                            Expiración</label>
                                        <input type="date" id="fecha-expiracion"
                                            v-model="nuevaExcepcion.fecha_expiracion"
                                            class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                                            required>
                                    </div>
                                </div>
                                <div>
                                    <label for="motivo-excepcion"
                                        class="block text-sm font-medium text-gray-700 mb-1">Motivo /
                                        Justificación</label>
                                    <textarea id="motivo-excepcion" v-model="nuevaExcepcion.motivo" rows="3"
                                        class="w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                                        placeholder="Ej: Corrección de gasto olvidado..." required></textarea>
                                </div>
                                <div class="text-right">
                                    <button type="submit" :disabled="isSubmitting"
                                        class="px-4 py-2 bg-verde-bap text-white font-semibold rounded-lg hover:bg-verde-bap-dark transition-colors disabled:opacity-50">
                                        <span v-if="isSubmitting">Otorgando...</span>
                                        <span v-else>Otorgar Permiso</span>
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Lista de Excepciones Activas -->
                        <div class="mt-8">
                            <h4 class="text-lg font-semibold text-gray-900 mb-4">Excepciones Activas</h4>
                            <div v-if="excepciones.length === 0"
                                class="text-center text-gray-500 py-6 bg-gray-50 rounded-lg">
                                No hay excepciones activas para este período.
                            </div>
                            <ul v-else class="space-y-3">
                                <li v-for="ex in excepciones" :key="ex.id"
                                    class="p-4 bg-white border rounded-lg shadow-sm flex justify-between items-center">
                                    <div>
                                        <p class="font-bold text-gray-800">{{ ex.usuario_excepcion.name }} {{
                                            ex.usuario_excepcion.last_name }}</p>
                                        <p class="text-xs text-gray-500">Expira: {{ formatarFecha(ex.fecha_expiracion)
                                        }} | Otorgado por: {{ ex.usuario_otorga.name }}</p>
                                        <p class="text-sm italic text-gray-600 mt-1">"{{ ex.motivo }}"</p>
                                    </div>
                                    <button @click="confirmarRevocar(ex)"
                                        class="p-2 text-red-500 hover:bg-red-100 rounded-full transition-colors"
                                        title="Revocar Excepción">
                                        <Trash2 class="w-5 h-5" />
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { ref, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { Loader2, X, Trash2 } from 'lucide-vue-next';

const props = defineProps({
    mostrar: Boolean,
    periodoSeleccionado: {
        type: Object,
        default: null
    }
});

const emit = defineEmits(['close', 'excepcion-creada']);

const cargando = ref(true);
const isSubmitting = ref(false);
const excepciones = ref([]);
const usuarios = ref([]);
const nuevaExcepcion = ref({
    id_usuario_excepcion: '',
    fecha_expiracion: '',
    motivo: ''
});

const fetchModalData = async () => {
    if (!props.periodoSeleccionado) return;
    cargando.value = true;
    try {
        // Cargar usuarios y excepciones en paralelo para mayor eficiencia
        const [usersResponse, exceptionsResponse] = await Promise.all([
            api.get('/v1/users/list-for-select'),
            api.get(`/v1/cierres-mensuales/${props.periodoSeleccionado.id}/excepciones`)
        ]);
        usuarios.value = usersResponse.data;
        excepciones.value = exceptionsResponse.data;
    } catch (error) {
        console.error("Error al cargar datos del modal de excepciones:", error);
        Swal.fire('Error', 'No se pudieron cargar los datos necesarios.', 'error');
        closeModal();
    } finally {
        cargando.value = false;
    }
};

watch(
    [() => props.mostrar, () => props.periodoSeleccionado],
    ([mostrar, periodo]) => {
        if (mostrar && periodo) {
            fetchModalData();
        }
    },
    { immediate: true }
);

const formatPeriodo = (periodoStr) => {
    if (!periodoStr) return 'Fecha inválida';

    try {
        // Tomar solo la parte de la fecha (ej. "2025-07-01") por si viene con hora.
        const datePart = periodoStr.split('T')[0];
        const [year, month] = datePart.split('-');

        // Crear la fecha especificando que es UTC para evitar corrimientos de día por zona horaria.
        const date = new Date(Date.UTC(parseInt(year), parseInt(month) - 1, 1));

        return date.toLocaleString('es-PE', {
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC' // Importante para consistencia
        }).replace(/^\w/, c => c.toUpperCase());

    } catch (e) {
        console.error("Error al formatear la fecha:", periodoStr, e);
        return 'Fecha inválida';
    }
};

const formatarFecha = (fechaString) => {
    if (!fechaString) return 'Fecha inválida';
    try {
        const datePart = fechaString.split('T')[0];
        const [year, month, day] = datePart.split('-');

        // Se crea la fecha en UTC para evitar problemas de zona horaria.
        const date = new Date(Date.UTC(parseInt(year), parseInt(month) - 1, parseInt(day)));

        if (isNaN(date.getTime())) {
            return 'Fecha inválida';
        }

        return date.toLocaleDateString('es-PE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            timeZone: 'UTC' 
        });
    } catch (e) {
        console.error("Error al formatear la fecha:", fechaString, e);
        return 'Fecha inválida';
    }
};

const confirmarOtorgarExcepcion = () => {
    Swal.fire({
        title: '¿Otorgar Permiso?',
        text: `Se concederá permiso a este usuario para registrar gastos en ${formatPeriodo(props.periodoSeleccionado.periodo)}.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, otorgar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            otorgarExcepcion();
        }
    });
};

const otorgarExcepcion = async () => {
    isSubmitting.value = true;
    try {
        const payload = {
            ...nuevaExcepcion.value,
            periodo: props.periodoSeleccionado.periodo.substring(0, 7) // Formato YYYY-MM
        };
        await api.post('/v1/excepciones-cierre', payload);
        Swal.fire('¡Éxito!', 'La excepción ha sido otorgada correctamente.', 'success');

        // Limpiar formulario y recargar datos
        nuevaExcepcion.value = { id_usuario_excepcion: '', fecha_expiracion: '', motivo: '' };
        fetchModalData(); // Recargar la lista de excepciones
        emit('excepcion-creada'); // Notificar al padre para que pueda recargar si es necesario
    } catch (error) {
        console.error("Error al otorgar la excepción:", error);
        Swal.fire('Error', error.response?.data?.message || 'No se pudo otorgar la excepción.', 'error');
    } finally {
        isSubmitting.value = false;
    }
};

const confirmarRevocar = (excepcion) => {
    Swal.fire({
        title: '¿Revocar Permiso?',
        text: `Se eliminará la excepción para ${excepcion.usuario_excepcion.name}. Ya no podrá registrar gastos en este período.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, revocar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            revocarExcepcion(excepcion.id);
        }
    });
};

const revocarExcepcion = async (excepcionId) => {
    try {
        await api.delete(`/v1/excepciones-cierre/${excepcionId}`);
        Swal.fire('¡Revocada!', 'La excepción ha sido eliminada.', 'success');
        fetchModalData(); // Recargar la lista
        emit('excepcion-creada');
    } catch (error) {
        console.error("Error al revocar la excepción:", error);
        Swal.fire('Error', error.response?.data?.message || 'No se pudo revocar la excepción.', 'error');
    }
};

const closeModal = () => {
    emit('close');
};
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
</style>
