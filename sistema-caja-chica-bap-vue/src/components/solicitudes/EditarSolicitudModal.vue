<template>
    <Transition name="modal-fade">
        <div v-if="mostrar"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-60 backdrop-blur-sm">
            <!-- MODIFICACIÓN: Se aumenta el max-w para dar más espacio al formulario -->
            <div
                class="bg-white rounded-lg shadow-xl w-full max-w-3xl mx-auto transform transition-all animate-modal-scale">

                <div class="flex justify-between items-center p-4 border-b bg-gray-50 rounded-t-lg">
                    <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-verde-bap" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <!-- Se usa la prop correcta 'solicitudAEditar' -->
                        <span v-if="modo === 'observada'">Subsanar Solicitud: {{ solicitudAEditar?.codigo_solicitud
                        }}</span>
                        <span v-else>Editar Solicitud: {{ solicitudAEditar?.codigo_solicitud }}</span>
                    </h3>
                    <button @click="cerrar" class="text-gray-500 hover:text-gray-700 transition-colors">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 max-h-[80vh] overflow-y-auto scroll-modal">
                    <!-- 
                        ¡LÓGICA CLAVE!
                        Aquí inyectamos el componente AperturaFondos.vue.
                        Le pasamos la solicitud a editar y el modo de edición.
                        Escuchamos sus eventos para saber cuándo se actualizó o se canceló.
                    -->
                    <AperturaFondos :solicitudAEditar="props.solicitudAEditar" :modoEdicion="props.modo"
                        :usuario-actual="props.usuarioActual" :proyectos="props.proyectos"
                        :gastos-proyectados-catalogo="props.gastosProyectadosCatalogo"
                        :areas-catalogo="props.areasCatalogo" @solicitud-actualizada="handleActualizacionExitosa"
                        @cancelar="cerrar" />
                </div>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { defineProps, defineEmits } from 'vue';
import AperturaFondos from './AperturaFondos.vue';

const props = defineProps({
    mostrar: { type: Boolean, default: false },
    solicitudAEditar: { type: Object, required: true },
    modo: { type: String, required: true }, // 'pendiente' o 'observada'
    // Props que se pasarán hacia abajo
    usuarioActual: { type: Object, required: true },
    proyectos: { type: Array, required: true },
    gastosProyectadosCatalogo: { type: Array, required: true },
    areasCatalogo: { type: Array, required: true }
});

const emit = defineEmits(['cancelar', 'solicitud-actualizada']);

/**
 * Esta función simplemente re-emite el evento hacia arriba.
 * Cuando AperturaFondos dice "me actualicé", este modal le dice
 * a SeguimientoSolicitudes "la edición terminó, puedes refrescar la tabla".
 */
const handleActualizacionExitosa = (solicitudActualizada) => {
    emit('solicitud-actualizada', solicitudActualizada);
};

/**
 * Emite el evento para cerrar el modal.
 */
const cerrar = () => {
    emit('cancelar');
};
</script>

<style scoped>
/* Estilos para la transición del modal */
.modal-fade-enter-active,
.modal-fade-leave-active {
    transition: opacity 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.modal-fade-enter-from,
.modal-fade-leave-to {
    opacity: 0;
}
</style>
