<script setup>
import { defineProps, defineEmits, computed } from 'vue';

// Define las propiedades que este componente recibe de su padre
const props = defineProps({
    mostrar: {
        type: Boolean,
        default: false
    },
    solicitud: {
        type: Object,
        default: null
    }
});

// Define los eventos que este componente puede emitir
const emit = defineEmits(['close']);

// Propiedad computada para el historial de estados, asegurando que siempre sea un array
// Accede a 'historial_estados' que es como se carga la relación en el backend.
const historialDeEstados = computed(() => {
    if (props.solicitud && Array.isArray(props.solicitud.historial_estados)) {
        return props.solicitud.historial_estados;
    }
    return [];
});

/**
 * Formatea una cadena de fecha a un formato legible en español.
 * @param {string} fechaString - La cadena de fecha a formatear.
 * @returns {string} Fecha formateada o 'N/A' si no es válida.
 */
const formatearFecha = (fechaString) => {
    if (!fechaString) return 'N/A';
    const opciones = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(fechaString).toLocaleDateString('es-ES', opciones);
};

/**
 * Procesa las observaciones para insertar saltos de línea después de un punto y un espacio.
 * @param {string} observaciones - La cadena de observaciones a procesar.
 * @returns {string} Observaciones con saltos de línea HTML.
 */
const formattedObservaciones = (observaciones) => {
    if (!observaciones) return '';
    // Reemplaza un punto seguido de un espacio con un punto y un salto de línea HTML
    return observaciones.replace(/\. /g, '.<br>');
};

/**
 * Obtiene las clases CSS para el color de fondo y texto principal de una tarjeta de estado.
 * Las clases 'border-color/20' se utilizan para un borde sutil basado en el color del estado.
 * @param {string} estado - El nombre del estado.
 * @returns {string} Clases CSS.
 */
const getEstadoColor = (estado) => {
    if (!estado) return 'bg-gray-100 text-gray-700 border-gray-300/20';

    const estadoTrimmed = estado.trim();

    if (estadoTrimmed === 'Creada') {
        return 'bg-estado-creada text-estado-creada-text border-estado-creada-text/20';
    } else if (estadoTrimmed === 'Pendiente Aprobación ADM' || estadoTrimmed === 'Pendiente Aprobación GRTE') {
        return 'bg-estado-pendiente text-estado-pendiente-text border-estado-pendiente-text/20';
    } else if (estadoTrimmed.includes('Observada')) {
        return 'bg-estado-observada text-estado-observada-text border-estado-observada-text/20';
    } else if (estadoTrimmed.includes('Descargo Enviado')) {
        return 'bg-estado-descargo text-estado-descargo-text border-estado-descargo-text/20';
    } else if (estadoTrimmed === 'Aprobada ADM') {
        return 'bg-estado-aprobada-adm text-estado-aprobada-adm-text border-estado-aprobada-adm-text/20';
    } else if (estadoTrimmed === 'Aprobada') {
        return 'bg-estado-aprobada-final text-estado-aprobada-final-text border-estado-aprobada-final-text/20';
    } else if (estadoTrimmed === 'Rechazada Final') {
        return 'bg-estado-rechazada text-estado-rechazada-text border-estado-rechazada-text/20';
    }

    return 'bg-gray-100 text-gray-700 border-gray-300/20';
};

/**
 * Obtiene las clases CSS para los colores de la sección "Revisado por", adaptándose al estado.
 * Se utiliza un fondo más transparente para distinguirlo del fondo principal del estado.
 * @param {string} estado - El nombre del estado.
 * @returns {string} Clases CSS.
 */
const getRevisadoPorColors = (estado) => {
    if (!estado) return 'text-gray-600 bg-gray-100/60';

    const estadoTrimmed = estado.trim();

    switch (estadoTrimmed) {
        case 'Creada':
            return 'text-estado-creada-text bg-estado-creada/30';
        case 'Pendiente Aprobación ADM':
        case 'Pendiente Aprobación GRTE':
            return 'text-estado-pendiente-text bg-estado-pendiente/30';
        case 'Observada ADM':
        case 'Observada GRTE':
            return 'text-estado-observada-text bg-estado-observada/30';
        case 'Descargo Enviado ADM':
        case 'Descargo Enviado GRTE':
            return 'text-estado-descargo-text bg-estado-descargo/30';
        case 'Aprobada ADM':
        case 'Aprobada':
            return 'text-estado-aprobada-adm-text bg-estado-aprobada-adm/30';
        case 'Rechazada Final':
            return 'text-estado-rechazada-text bg-estado-rechazada/30';
        default:
            return 'text-gray-600 bg-gray-100/60';
    }
};

/**
 * Obtiene las clases CSS para el icono de usuario dentro de la sección "Revisado por", adaptándose al estado.
 * @param {string} estado - El nombre del estado.
 * @returns {string} Clases CSS.
 */
const getUserIconColors = (estado) => {
    if (!estado) return 'text-gray-500 bg-gray-100';

    const estadoTrimmed = estado.trim();

    switch (estadoTrimmed) {
        case 'Creada':
            return 'text-estado-creada-text bg-estado-creada/20';
        case 'Pendiente Aprobación ADM':
        case 'Pendiente Aprobación GRTE':
            return 'text-estado-pendiente-text bg-estado-pendiente/20';
        case 'Observada ADM':
        case 'Observada GRTE':
            return 'text-estado-observada-text bg-estado-observada/20';
        case 'Descargo Enviado ADM':
        case 'Descargo Enviado GRTE':
            return 'text-estado-descargo-text bg-estado-descargo/20';
        case 'Aprobada ADM':
        case 'Aprobada':
            return 'text-estado-aprobada-adm-text bg-estado-aprobada-adm/20';
        case 'Rechazada Final':
            return 'text-estado-rechazada-text bg-estado-rechazada/20';
        default:
            return 'text-gray-500 bg-gray-100';
    }
};

/**
 * Obtiene la ruta del atributo 'd' para el icono SVG que representa el estado.
 * @param {string} estado - El nombre del estado.
 * @returns {string} Ruta SVG para el icono.
 */
const getEstadoIcon = (estado) => {
    if (!estado) return 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z'; // Icono de información por defecto

    const estadoTrimmed = estado.trim();

    switch (estadoTrimmed) {
        case 'Creada':
            return 'M12 4.5v15m7.5-7.5h-15'; // Icono de "más" o creación
        case 'Pendiente Aprobación ADM':
        case 'Pendiente Aprobación GRTE':
            return 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z'; // Icono de reloj (pendiente)
        case 'Observada ADM':
        case 'Observada GRTE':
            return 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z'; // Icono de ojo (observada)
        case 'Descargo Enviado ADM':
        case 'Descargo Enviado GRTE':
            return 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z'; // Icono de documento con flecha (envío)
        case 'Aprobada ADM':
        case 'Aprobada':
            return 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z'; // Icono de verificación (aprobado)
        case 'Rechazada Final':
            return 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z'; // Icono de "x" (rechazado)
        default:
            return 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z'; // Icono de información por defecto
    }
};

/**
 * Emite un evento 'close' para cerrar el modal.
 */
const cerrarModal = () => {
    emit('close');
};
</script>

<template>
    <!-- Transición para el fondo del modal -->
    <Transition name="modal-backdrop">
        <!-- Contenedor principal del modal, visible si la prop 'mostrar' es verdadera -->
        <div v-if="mostrar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <!-- Transición para el contenido del modal -->
            <Transition name="modal-content" appear>
                <!-- Contenedor del contenido del modal -->
                <div class="glass rounded-3xl shadow-strong w-full max-w-2xl mx-auto overflow-hidden transform animate-scale-in border border-white/20">
                    <!-- Encabezado del modal con fondo degradado -->
                    <div class="relative bg-gradient-to-r from-verde-bap to-verde-bap-dark p-6 text-white">
                        <!-- Capa de fondo para el efecto de degradado -->
                        <div class="absolute inset-0 bg-gradient-to-r from-verde-bap/90 to-verde-bap-dark/90"></div>
                        <div class="relative flex justify-between items-center">
                            <div>
                                <h3 class="text-2xl font-bold text-white drop-shadow-lg">
                                    Historial de Estados
                                </h3>
                                <p class="text-verde-bap-extralight mt-1 font-medium">
                                    <!-- Muestra el código de la solicitud o 'N/A' si no está disponible -->
                                    {{ solicitud?.codigo_solicitud || 'N/A' }}
                                </p>
                            </div>
                            <!-- Botón para cerrar el modal -->
                            <button
                                @click="cerrarModal"
                                class="text-white/80 hover:text-white hover:bg-white/20 p-2 rounded-xl transition-all duration-300 hover:scale-110"
                            >
                                <!-- Icono de cerrar (SVG) -->
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <!-- Cuerpo del modal: Contenido del historial de estados -->
                    <div class="p-6 max-h-[70vh] overflow-y-auto scroll-smooth-custom bg-gradient-to-br from-white/95 to-verde-bap-extralight/50">
                        <!-- Contenido si hay historial de estados -->
                        <div v-if="historialDeEstados.length > 0" class="space-y-4">
                            <!-- Bucle para renderizar cada estado del historial -->
                            <div
                                v-for="(estado, index) in historialDeEstados"
                                :key="index"
                                class="group relative overflow-hidden rounded-2xl border transition-all duration-300 hover:shadow-medium hover:-translate-y-1 animate-fade-in-up"
                                :style="{ animationDelay: `${index * 0.1}s` }"
                                :class="getEstadoColor(estado.estado_nuevo)"
                            >
                                <!-- Efecto de brillo al pasar el mouse -->
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-1000"></div>

                                <div class="relative p-5">
                                    <!-- Sección de estado principal y fecha -->
                                    <div class="flex items-center space-x-3 mb-4">
                                        <!-- Contenedor del icono de estado -->
                                        <div class="flex-shrink-0 p-2 rounded-xl bg-white/30 backdrop-blur-sm">
                                            <!-- Icono SVG del estado -->
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" :d="getEstadoIcon(estado.estado_nuevo)" />
                                            </svg>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-lg">{{ estado.estado_nuevo }}</h4>
                                            <p class="text-sm opacity-80 font-medium">{{ formatearFecha(estado.fecha_cambio) }}</p>
                                        </div>
                                    </div>
                                    
                                    <!-- SECCIÓN MEJORADA: "Revisado por" con colores adaptativos -->
                                    <div v-if="estado.usuario_accion" 
                                            class="mb-4 rounded-xl p-3 backdrop-blur-sm transition-all duration-300 hover:scale-[1.02]"
                                            :class="getRevisadoPorColors(estado.estado_nuevo)">
                                        <div class="flex items-center space-x-3">
                                            <!-- Icono de usuario con colores adaptativos -->
                                            <div class="flex-shrink-0 p-2 rounded-lg transition-all duration-300"
                                                    :class="getUserIconColors(estado.estado_nuevo)">
                                                <svg xmlns="http://www.w3.org/2000/svg" 
                                                        fill="none" 
                                                        viewBox="0 0 24 24" 
                                                        stroke-width="2" 
                                                        stroke="currentColor" 
                                                        class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a8.967 8.967 0 0015.002 0m-12.932-6.012a4.996 4.996 0 017.5 0" />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-2 space-y-1 sm:space-y-0">
                                                    <span class="text-sm font-bold uppercase tracking-wider opacity-90">
                                                        Revisado y gestionado por:
                                                    </span>
                                                    <span class="font-bold text-base">
                                                        {{ estado.usuario_accion.name }} {{ estado.usuario_accion.last_name }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Contenedores para Observaciones, Descargo y Rechazo Final -->
                                    <div class="space-y-3">
                                        <!-- Observaciones -->
                                        <div v-if="estado.observaciones" class="bg-white/40 backdrop-blur-sm rounded-xl p-4 border border-white/30">
                                            <div class="flex items-start space-x-3">
                                                <svg class="h-5 w-5 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <div>
                                                    <p class="font-semibold text-sm">Observaciones</p>
                                                    <!-- Usamos la propiedad computada para las observaciones formateadas -->
                                                    <p class="text-sm opacity-90 mt-1" v-html="formattedObservaciones(estado.observaciones)"></p>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Motivo de Descargo -->
                                        <div v-if="estado.motivo_descargo" class="bg-white/40 backdrop-blur-sm rounded-xl p-4 border border-white/30">
                                            <div class="flex items-start space-x-3">
                                                <svg class="h-5 w-5 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.375 12l-.375 1.5 5.25-1.5L17.25 12l-5.25 1.5L11.625 15" />
                                                </svg>
                                                <div>
                                                    <p class="font-semibold text-sm">Motivo de Descargo</p>
                                                    <p class="text-sm opacity-90 mt-1">{{ estado.motivo_descargo }}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Motivo de Rechazo Final -->
                                        <div v-if="estado.motivo_rechazo_final" class="bg-white/40 backdrop-blur-sm rounded-xl p-4 border border-white/30">
                                            <div class="flex items-start space-x-3">
                                                <svg class="h-5 w-5 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                                </svg>
                                                <div>
                                                    <p class="font-semibold text-sm">Motivo de Rechazo</p>
                                                    <p class="text-sm opacity-90 mt-1">{{ estado.motivo_rechazo_final }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Mensaje si no hay historial disponible -->
                        <div v-else class="text-center py-12 animate-fade-in">
                            <div class="mx-auto w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mb-6">
                                <svg class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m6.75 18H3a2.25 2.25 0 01-2.25-2.25V6.108c0-1.135.845-2.098 1.976-2.192.373-.03.748-.057 1.123-.08M15.75 18H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08M15.75 18v-2.625c0-.621-.504-1.125-1.125-1.125h-9.75a1.125 1.125 0 00-1.125 1.125v3.375c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125z" />
                                </svg>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-600 mb-2">Sin historial disponible</h3>
                            <p class="text-gray-500">No hay historial de estados disponible para esta solicitud.</p>
                        </div>
                    </div>
                    <!-- Pie de página del modal con botón de cierre -->
                    <div class="bg-gradient-to-r from-gray-50 to-verde-bap-extralight/30 px-6 py-4 border-t border-gray-200/50">
                        <div class="flex justify-end">
                            <button
                                @click="cerrarModal"
                                class="group relative overflow-hidden px-6 py-3 bg-gradient-to-r from-verde-bap to-verde-bap-dark text-white font-semibold rounded-xl shadow-soft hover:shadow-glow-verde transition-all duration-300 hover:scale-105 focus:outline-none focus:ring-4 focus:ring-verde-bap/30"
                            >
                                <span class="relative z-10 flex items-center space-x-2">
                                    <span>Cerrar</span>
                                    <svg class="h-4 w-4 group-hover:translate-x-1 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </span>
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/20 to-transparent -translate-x-full group-hover:translate-x-full transition-transform duration-700"></div>
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </Transition>
</template>

<style scoped>
/* Transiciones del fondo del modal */
.modal-backdrop-enter-active,
.modal-backdrop-leave-active {
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}
.modal-backdrop-enter-from,
.modal-backdrop-leave-to {
    opacity: 0;
    backdrop-filter: blur(0px);
}
/* Transiciones del contenido del modal */
.modal-content-enter-active {
    transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-content-leave-active {
    transition: all 0.3s cubic-bezier(0.4, 0, 1, 1);
}
.modal-content-enter-from {
    opacity: 0;
    transform: scale(0.8) translateY(50px);
}
.modal-content-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(-20px);
}

/* Animación de entrada secuencial para las tarjetas (estados) */
@keyframes fadeInUp {
    0% {
        opacity: 0;
        transform: translateY(30px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fade-in-up {
    animation: fadeInUp 0.6s ease-out forwards;
    opacity: 0;
}
</style>
