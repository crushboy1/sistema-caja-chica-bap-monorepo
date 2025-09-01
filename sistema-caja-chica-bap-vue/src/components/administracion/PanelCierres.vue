<template>
    <div class="p-6 bg-white rounded-xl shadow-lg animate-fade-in-up">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-2xl font-bold text-gray-800">Gestión de Cierres Contables</h3>
                <p class="text-gray-600 text-sm mt-1">
                    Controla los períodos de registro de gastos. Cierra un mes para prevenir nuevas declaraciones o
                    reábrelo temporalmente.
                </p>
            </div>
            <button @click="$emit('close')" class="p-2 hover:bg-gray-100 rounded-full transition-colors duration-200">
                <X class="w-5 h-5 text-gray-500" />
            </button>
        </div>

        <!-- Controles de navegación de año y filtro -->
        <div
            class="flex flex-col lg:flex-row justify-between items-start lg:items-center mb-6 p-5 bg-gradient-to-r from-gray-50 to-gray-100 rounded-xl border border-gray-200">
            <div class="flex items-center space-x-3 mb-4 lg:mb-0">
                <span class="text-sm font-medium text-gray-600">Año:</span>
                <div class="flex items-center bg-white rounded-lg border border-gray-300 shadow-sm">
                    <button @click="cambiarAnio(-1)" :disabled="cargando"
                        class="p-2 hover:bg-gray-50 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed rounded-l-lg">
                        <ChevronLeft class="w-4 h-4 text-gray-600" />
                    </button>
                    <span
                        class="text-lg font-bold text-gray-800 px-4 py-2 min-w-[80px] text-center border-x border-gray-200">
                        {{ anioSeleccionado }}
                    </span>
                    <button @click="cambiarAnio(1)" :disabled="cargando"
                        class="p-2 hover:bg-gray-50 transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed rounded-r-lg">
                        <ChevronRight class="w-4 h-4 text-gray-600" />
                    </button>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                <label for="filtro-estado" class="text-sm font-medium text-gray-600 whitespace-nowrap">
                    Filtrar por estado:
                </label>
                <select id="filtro-estado" v-model="filtroEstado" @change="fetchCierres" :disabled="cargando"
                    class=" bg-white border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed">
                    <option value="">Todos los estados</option>
                    <option value="Abierto">Solo abiertos</option>
                    <option value="Cerrado">Solo cerrados</option>
                </select>
            </div>
        </div>

        <!-- Estado de carga con skeleton -->
        <div v-if="cargando" class="space-y-4">
            <div class="animate-pulse">
                <div class="bg-gray-200 h-12 rounded-lg mb-4"></div>
                <div class="space-y-3">
                    <div v-for="i in 6" :key="i" class="bg-gray-200 h-16 rounded-lg"></div>
                </div>
            </div>
        </div>

        <!-- Sin resultados -->
        <div v-else-if="cierres.length === 0" class="text-center py-16">
            <div class="mx-auto w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                <Calendar class="w-8 h-8 text-gray-400" />
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay períodos disponibles</h3>
            <p class="text-gray-500">
                {{ filtroEstado ? `No se encontraron períodos ${filtroEstado.toLowerCase()}s para ${anioSeleccionado}` :
                    `No hay períodos configurados para ${anioSeleccionado}` }}
            </p>
        </div>

        <!-- Tabla de períodos con mejor diseño -->
        <div v-else class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th
                                class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Período
                            </th>
                            <th
                                class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Estado
                            </th>
                            <th
                                class="px-6 py-4 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="cierre in cierres" :key="cierre.periodo"
                            class="hover:bg-gray-50 transition-colors duration-200">
                            <!-- Columna del período -->
                            <td class="px-6 py-5 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div
                                        class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-blue-100 to-blue-200 rounded-lg flex items-center justify-center mr-3">
                                        <Calendar class="w-5 h-5 text-blue-600" />
                                    </div>
                                    <div>
                                        <div class="text-sm font-semibold text-gray-900">
                                            {{ formatPeriodo(cierre.periodo) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ formatarFechaSinHora(cierre.periodo) }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Columna del estado -->
                            <td class="px-6 py-5 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-full"
                                    :class="cierre.estado === 'Cerrado'
                                        ? 'bg-red-100 text-red-800 border border-red-200'
                                        : 'bg-green-100 text-green-800 border border-green-200'">
                                    <div class="w-2 h-2 rounded-full mr-2"
                                        :class="cierre.estado === 'Cerrado' ? 'bg-red-400' : 'bg-green-400'">
                                    </div>
                                    {{ cierre.estado }}
                                </span>
                            </td>

                            <!-- Columna de acciones -->
                            <td class="px-6 py-5 whitespace-nowrap text-center">
                                <div class="flex items-center justify-center space-x-3">
                                    <div v-if="cierre.estado === 'Cerrado' && cierre.tiene_excepciones_activas"
                                        class="relative group">
                                        <div
                                            class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-3 py-2 text-sm text-white bg-gray-900 rounded-lg opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 whitespace-nowrap z-10">
                                            No se puede reabrir : existen excepciones activas
                                            <div
                                                class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900">
                                            </div>
                                        </div>
                                        <button disabled
                                            class="relative px-4 py-2 rounded-lg text-sm font-semibold text-white transition-all duration-200 bg-green-500 opacity-50 cursor-not-allowed">
                                            Reabrir
                                        </button>
                                    </div>

                                    <button v-else @click.stop="confirmarCambioEstado(cierre)"
                                        :disabled="procesandoCambio === cierre.periodo"
                                        class="relative px-4 py-2 rounded-lg text-sm font-semibold text-white transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-75 disabled:cursor-not-allowed disabled:transform-none"
                                        :class="cierre.estado === 'Cerrado'
                                            ? 'bg-green-500 hover:bg-green-600 focus:ring-green-500 shadow-green-200'
                                            : 'bg-rojo-bap hover:bg-rojo-bap-dark focus:ring-rojo-bap shadow-red-200'">
                                        <span v-if="procesandoCambio === cierre.periodo" class="flex items-center">
                                            <Loader2 class="animate-spin w-4 h-4 mr-2" />
                                            Procesando...
                                        </span>
                                        <span v-else>
                                            {{ cierre.estado === 'Cerrado' ? 'Reabrir' : 'Cerrar' }}
                                        </span>
                                    </button>

                                    <button v-if="cierre.estado === 'Cerrado'"
                                        @click.stop="abrirModalExcepciones(cierre)"
                                        class="px-4 py-2 rounded-lg bg-amarillo-bap hover:bg-amarillo-bap-dark text-white text-sm font-semibold transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-amarillo-bap focus:ring-offset-2">
                                        <Settings class="w-4 h-4 inline mr-2" />
                                        Excepciones
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal de excepciones sin Teleport -->
        <Transition enter-active-class="transition-all duration-300 ease-out" enter-from-class="opacity-0"
            enter-to-class="opacity-100" leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="mostrarModal"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
                style="position: fixed; top: 0; left: 0; right: 0; bottom: 0;" @click.self="cerrarModal">
                <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden" @click.stop>
                    <GestionExcepcionesModal :mostrar="mostrarModal" :periodoSeleccionado="periodoSeleccionado"
                        @close="cerrarModal" @excepcion-creada="onExcepcionCreada" />
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { Loader2, ChevronLeft, ChevronRight, X, Calendar, Settings } from 'lucide-vue-next';
import GestionExcepcionesModal from './modals/GestionExcepcionesModal.vue';

const props = defineProps({
    usuarioActual: {
        type: Object,
        default: () => null
    }
});

// Estados reactivos
const cargando = ref(true);
const cierres = ref([]);
const periodoSeleccionado = ref(null);
const mostrarModal = ref(false);
const anioSeleccionado = ref(new Date().getFullYear());
const filtroEstado = ref('');
const procesandoCambio = ref(null); // Para mostrar loading en botones individuales

// Función para obtener los cierres del servidor
const fetchCierres = async () => {
    cargando.value = true;
    try {
        const params = {
            year: anioSeleccionado.value,
        };

        // Solo agregar el filtro de estado si está seleccionado
        if (filtroEstado.value) {
            params.estado = filtroEstado.value;
        }

        const response = await api.get('/v1/cierres-mensuales', { params });
        cierres.value = response.data || [];

    } catch (error) {
        console.error("Error al cargar los cierres mensuales:", error);
        cierres.value = [];
        Swal.fire({
            title: 'Error',
            text: 'No se pudo cargar la configuración de cierres.',
            icon: 'error',
            confirmButtonText: 'Entendido'
        });
    } finally {
        cargando.value = false;
    }
};

// Función para cambiar el año
const cambiarAnio = (direccion) => {
    anioSeleccionado.value += direccion;
    fetchCierres();
};

// Función para formatear el período
const formatPeriodo = (periodoStr) => {
    if (!periodoStr) return 'Fecha inválida';

    try {
        // Si el período viene como "YYYY-MM", crear la fecha correctamente
        const [año, mes] = periodoStr.split('-');
        const fecha = new Date(parseInt(año), parseInt(mes) - 1, 1);

        if (isNaN(fecha.getTime())) {
            return 'Fecha inválida';
        }

        return fecha.toLocaleString('es-PE', {
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC'
        }).replace(/^\w/, c => c.toUpperCase());

    } catch (error) {
        console.error('Error al formatear período:', error);
        return 'Fecha inválida';
    }
};

const formatarFechaSinHora = (fechaString) => {
    if (!fechaString) return '';
    try {
        const datePart = fechaString.split('T')[0];
        const [year, month, day] = datePart.split('-');
        return `${day}/${month}/${year}`;
    } catch {
        return 'Fecha inválida';
    }
};

// Función para confirmar cambio de estado
const confirmarCambioEstado = (cierre) => {
    const nuevoEstado = cierre.estado === 'Cerrado' ? 'Abierto' : 'Cerrado';
    if (nuevoEstado === 'Abierto' && cierre.tiene_excepciones_activas) {
        Swal.fire({
            icon: 'error',
            title: 'Acción Bloqueada',
            html: `No se puede reabrir el período de <strong>${formatPeriodo(cierre.periodo)}</strong> porque tiene excepciones activas.<br><br>Por favor, revoque todas las excepciones para este mes desde el panel de gestión de excepciones antes de continuar.`,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Entendido'
        });
        return;
    }
    const accion = nuevoEstado === 'Cerrado' ? 'cerrar' : 'reabrir';
    const textoAccion = nuevoEstado === 'Cerrado'
        ? 'cerrar el período. Los usuarios ya no podrán registrar gastos en este mes.'
        : 'reabrir el período. Los usuarios podrán volver a registrar gastos en este mes.';

    Swal.fire({
        title: `¿Estás seguro de ${accion} el período?`,
        text: `Esta acción va a ${textoAccion}`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: nuevoEstado === 'Cerrado' ? '#dc2626' : '#16a34a',
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Sí, ¡${accion.charAt(0).toUpperCase() + accion.slice(1)}!`,
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            actualizarEstadoCierre(cierre, nuevoEstado);
        }
    });
};

// Función para actualizar el estado del cierre
const actualizarEstadoCierre = async (cierre, nuevoEstado) => {
    procesandoCambio.value = cierre.periodo;

    try {
        const periodo = cierre.periodo.substring(0, 7);
        await api.put('/v1/cierres-mensuales', { periodo, estado: nuevoEstado });

        // Actualizar el estado localmente para mejor UX
        const index = cierres.value.findIndex(c => c.periodo === cierre.periodo);
        if (index !== -1) {
            cierres.value[index].estado = nuevoEstado;
        }

        // Mostrar notificación de éxito
        Swal.fire({
            title: '¡Actualizado!',
            text: `El período ${formatPeriodo(cierre.periodo)} ha sido ${nuevoEstado.toLowerCase()}.`,
            icon: 'success',
            showConfirmButton: true
        });

        await fetchCierres()

    } catch (error) {
        console.error("Error al actualizar el estado:", error);
        Swal.fire('Error', error.response?.data?.message || 'No se pudo actualizar el estado.', 'error');
        cargando.value = false;
    } finally {
        procesandoCambio.value = null;
    }
};

// Función para cerrar modal de forma controlada
const cerrarModal = () => {
    mostrarModal.value = false;
    periodoSeleccionado.value = null;
};

// Función para manejar cuando se crea una excepción
const onExcepcionCreada = async () => {
    // Cerrar modal
    cerrarModal();

    // Mostrar notificación
    Swal.fire({
        title: '¡Excepción creada!',
        text: 'El permiso ha sido configurado correctamente.',
        icon: 'success',
        showConfirmButton: true
    });

    // Recargar datos
    fetchCierres();
};

// Función para abrir modal de excepciones de forma controlada
const abrirModalExcepciones = (cierre) => {
    periodoSeleccionado.value = { ...cierre };
    nextTick(() => {
        mostrarModal.value = true;
    });
};

// Cargar datos al montar el componente
onMounted(() => {
    fetchCierres();
});
</script>

<style scoped>
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in-up {
    animation: fade-in-up 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Transiciones suaves para estados hover */
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}

/* Mejorar la experiencia de los botones */
button:focus {
    outline: none;
}

/* Skeleton loading más suave */
@keyframes pulse {

    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: .5;
    }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Tooltip personalizado */
.group:hover .group-hover\:opacity-100 {
    opacity: 1;
}

.group:hover .group-hover\:visible {
    visibility: visible;
}
</style>