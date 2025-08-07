<template>
    <div class="w-full bg-gray-50 rounded-lg overflow-hidden" style="height: 420px;">
        <div v-if="!timelines || timelines.length === 0" class="flex items-center justify-center h-full text-gray-400">
            <div class="text-center">
                <History class="w-12 h-12 mx-auto mb-2" />
                <p class="text-sm">No hay actividad reciente en los fondos para mostrar.</p>
            </div>
        </div>

        <div v-else class="flex flex-col h-full p-4">
            <!-- Header de navegación -->
            <div class="flex-shrink-0 flex items-center justify-between mb-4">
                <button @click="prevTimeline" :disabled="currentTimelineIndex === 0"
                    class="p-2 rounded-full hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <ChevronLeft class="w-5 h-5 text-gray-600" />
                </button>
                <div class="text-center">
                    <h4 class="font-bold text-gray-800 text-lg">{{ currentTimeline.codigo_fondo }}</h4>
                    <p class="text-sm text-gray-500">{{ currentTimelineIndex + 1 }} de {{ timelines.length }}</p>
                </div>
                <button @click="nextTimeline" :disabled="currentTimelineIndex === timelines.length - 1"
                    class="p-2 rounded-full hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                    <ChevronRight class="w-5 h-5 text-gray-600" />
                </button>
            </div>

            <!-- Contenedor del timeline con scroll controlado -->
            <div class="flex-1 overflow-y-auto pr-2" style="min-height: 0;">
                <transition name="fade" mode="out-in">
                    <div class="pl-6" :key="currentTimelineIndex">
                        <ol class="relative border-l border-gray-200 ml-4">
                            <li v-for="(evento, index) in currentTimeline.eventos" :key="index" class="mb-4 ml-6">
                                <span
                                    class="absolute flex items-center justify-center w-8 h-8 rounded-full -left-4 ring-4 ring-white"
                                    :class="getEventVisuals(evento.tipo).color">
                                    <component :is="getEventVisuals(evento.tipo).icon" class="w-4 h-4 text-white" />
                                </span>

                                <div class="p-3 bg-white rounded-lg border-l-4 shadow-sm"
                                    :class="getEventVisuals(evento.tipo).borderColor">
                                    <div class="items-center justify-between sm:flex">
                                        <time class="mb-1 text-xs font-normal text-gray-500 sm:order-last sm:mb-0">
                                            {{ formatDate(evento.fecha) }}
                                        </time>
                                        <div class="text-sm font-semibold text-gray-800">
                                            {{ getEventVisuals(evento.tipo).title }}
                                        </div>
                                    </div>
                                    <div class="p-2 mt-2 text-sm font-normal text-gray-600 bg-gray-50 rounded-lg">
                                        <p class="font-medium text-gray-700 text-sm">{{ evento.motivo }}</p>
                                        <div class="flex justify-between items-center mt-2 text-xs">
                                            <div class="flex flex-col">
                                                <span class="text-gray-800 font-medium">{{ evento.usuario }}</span>
                                                <span
                                                    class="text-gray-500 px-2 py-0.5 bg-gray-100 rounded-full mt-1 text-xs">{{
                                                        evento.usuario_rol }}</span>
                                            </div>
                                            <span class="font-bold text-sm"
                                                :class="getEventVisuals(evento.tipo).textColor">
                                                {{ formatCurrency(evento.monto) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </div>
                </transition>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import {
    History,
    PlayCircle,
    CheckCircle2,
    ArrowUpCircle,
    ArrowDownCircle,
    RefreshCw,
    ChevronsUp,
    ChevronsDown,
    ChevronLeft,
    ChevronRight
} from 'lucide-vue-next';

const props = defineProps({
    timelines: {
        type: Array,
        required: true,
        default: () => []
    }
});

const currentTimelineIndex = ref(0);

const currentTimeline = computed(() => {
    if (!props.timelines || props.timelines.length === 0) {
        return { codigo_fondo: '', eventos: [] };
    }
    return props.timelines[currentTimelineIndex.value];
});

const nextTimeline = () => {
    if (currentTimelineIndex.value < props.timelines.length - 1) {
        currentTimelineIndex.value++;
    }
};

const prevTimeline = () => {
    if (currentTimelineIndex.value > 0) {
        currentTimelineIndex.value--;
    }
};

const getEventVisuals = (tipo) => {
    const visuals = {
        'Apertura': { icon: PlayCircle, color: 'bg-green-500', title: 'Fondo Creado', textColor: 'text-green-600', borderColor: 'border-green-500' },
        'Cierre': { icon: CheckCircle2, color: 'bg-blue-500', title: 'Fondo Cerrado', textColor: 'text-blue-600', borderColor: 'border-blue-500' },
        'Incremento': { icon: ChevronsUp, color: 'bg-sky-500', title: 'Incremento Aprobado', textColor: 'text-sky-600', borderColor: 'border-sky-500' },
        'Decremento': { icon: ChevronsDown, color: 'bg-amber-500', title: 'Decremento Aprobado', textColor: 'text-amber-600', borderColor: 'border-amber-500' },
        'Reposicion por Excedente': { icon: ArrowUpCircle, color: 'bg-orange-500', title: 'Reposición por Excedente', textColor: 'text-orange-600', borderColor: 'border-orange-500' },
        'Devolucion por Sobrante': { icon: ArrowDownCircle, color: 'bg-teal-500', title: 'Devolución de Sobrante', textColor: 'text-teal-600', borderColor: 'border-teal-500' },
        'Restauracion Mensual': { icon: RefreshCw, color: 'bg-indigo-500', title: 'Reinicio de Saldo Mensual', textColor: 'text-indigo-600', borderColor: 'border-indigo-500' }
    };
    return visuals[tipo] || { icon: History, color: 'bg-gray-400', title: tipo, textColor: 'text-gray-600', borderColor: 'border-gray-400' };
};

const formatDate = (dateString) => {
    if (!dateString) return 'Fecha no disponible';
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('es-PE', options);
};

const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(value || 0);
};
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* Estilo personalizado para el scrollbar */
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>