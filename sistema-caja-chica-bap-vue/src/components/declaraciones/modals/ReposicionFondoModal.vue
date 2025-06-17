<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';

const props = defineProps({
    mostrar: Boolean,
});
const emit = defineEmits(['close', 'fondoRepuesto']);

const fondos = ref([]);
const fondoSeleccionadoId = ref(null);
const isLoading = ref(false);
const cargandoFondos = ref(false);

const fondoSeleccionado = computed(() => {
    if (!fondoSeleccionadoId.value) return null;
    return fondos.value.find(f => f.id_fondo === fondoSeleccionadoId.value);
});

const montoAReponer = computed(() => {
    if (!fondoSeleccionado.value) return 0;
    return parseFloat(fondoSeleccionado.value.monto_aprobado) - parseFloat(fondoSeleccionado.value.monto_disponible);
});

const fetchFondos = async () => {
    cargandoFondos.value = true;
    try {
        const response = await api.get('/fondos-efectivo');
        fondos.value = response.data.fondos;
    } catch (error) {
        console.error("Error al cargar los fondos:", error);
        Swal.fire('Error', 'No se pudieron cargar los fondos disponibles.', 'error');
    } finally {
        cargandoFondos.value = false;
    }
};

const confirmarReposicion = async () => {
    if (!fondoSeleccionado.value) {
        Swal.fire('Atención', 'Por favor, selecciona un fondo para reponer.', 'warning');
        return;
    }

    const result = await Swal.fire({
        title: `¿Reponer Fondo ${fondoSeleccionado.value.codigo_fondo}?`,
        html: `Se añadirá <strong>S/. ${montoAReponer.value.toFixed(2)}</strong> para restaurar el saldo a su monto original de <strong>S/. ${parseFloat(fondoSeleccionado.value.monto_aprobado).toFixed(2)}</strong>.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#34D399',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Sí, reponer ahora',
        cancelButtonText: 'Cancelar'
    });

    if (result.isConfirmed) {
        isLoading.value = true;
        try {
            const response = await api.post(`/fondos-efectivo/${fondoSeleccionadoId.value}/reponer`);
            await Swal.fire('¡Éxito!', response.data.message, 'success');
            emit('fondoRepuesto'); // Notifica al padre
            emit('close');
        } catch (error) {
            Swal.fire('Error', error.response?.data?.message || 'No se pudo reponer el fondo.', 'error');
        } finally {
            isLoading.value = false;
        }
    }
};

onMounted(() => {
    fetchFondos();
});
</script>

<template>
    <Transition name="modal-fade">
        <div v-if="mostrar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-lg mx-auto overflow-hidden transform transition-all">
                <div class="flex justify-between items-center p-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-xl font-semibold text-gray-800">Reposición de Fondos de Caja Chica</h3>
                    <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 space-y-6">
                    <div>
                        <label for="fondo-reposicion" class="block text-sm font-medium text-gray-700 mb-1">Selecciona un
                            Fondo</label>
                        <select id="fondo-reposicion" v-model="fondoSeleccionadoId"
                            class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap">
                            <option disabled :value="null">-- Elige un fondo --</option>
                            <option v-for="fondo in fondos" :key="fondo.id_fondo" :value="fondo.id_fondo">
                                {{ fondo.codigo_fondo }} - {{ fondo.responsable.name }} {{ fondo.responsable.last_name
                                }}
                            </option>
                        </select>
                        <p v-if="cargandoFondos" class="text-xs text-gray-500 mt-1">Cargando fondos...</p>
                    </div>

                    <div v-if="fondoSeleccionado"
                        class="p-4 bg-gray-100 border-l-4 border-verde-bap rounded-r-lg animate-fade-in">
                        <h4 class="font-bold text-gray-700 mb-2">Resumen de Reposición</h4>
                        <div class="grid grid-cols-2 gap-2 text-sm">
                            <span class="text-gray-600">Monto Asignado:</span>
                            <span class="font-semibold text-right">S/. {{
                                parseFloat(fondoSeleccionado.monto_aprobado).toFixed(2) }}</span>

                            <span class="text-gray-600">Saldo Disponible Actual:</span>
                            <span class="font-semibold text-right">S/. {{
                                parseFloat(fondoSeleccionado.monto_disponible).toFixed(2) }}</span>

                            <hr class="col-span-2 my-1 border-gray-300">

                            <span class="text-gray-800 font-bold">Monto a Reponer:</span>
                            <span class="font-bold text-verde-bap text-right text-lg">S/. {{ montoAReponer.toFixed(2)
                                }}</span>
                        </div>
                    </div>
                </div>

                <div class="p-4 border-t border-gray-200 flex justify-end bg-gray-50">
                    <button @click="$emit('close')"
                        class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition-colors mr-3">Cancelar</button>
                    <button @click="confirmarReposicion" :disabled="isLoading || !fondoSeleccionado"
                        class="px-4 py-2 bg-verde-bap text-white rounded-md hover:bg-verde-bap-dark transition-colors">
                        <span v-if="isLoading">Procesando...</span>
                        <span v-else>Confirmar Reposición</span>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>