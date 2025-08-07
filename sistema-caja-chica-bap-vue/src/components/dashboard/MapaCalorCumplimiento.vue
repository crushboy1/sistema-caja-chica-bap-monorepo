<template>
    <div class="w-full bg-gray-50 rounded-lg overflow-hidden" style="min-height: 300px;">
        <div v-if="!data || data.length === 0" class="flex items-center justify-center h-full text-gray-400 p-8">
            <div class="text-center">
                <Map class="w-12 h-12 mx-auto mb-2" />
                <p class="text-sm">No hay datos de cumplimiento disponibles.</p>
            </div>
        </div>
        <div v-else class="p-4">
            <!-- Grid responsive que se ajusta al contenedor -->
            <div class="grid gap-2 mb-4" :class="getGridClass()">
                <div v-for="item in data" :key="item.area"
                    class="p-3 rounded-lg text-white transition-all duration-300 hover:shadow-lg min-w-0 flex flex-col justify-between"
                    :class="getColorForPercentage(item.porcentaje_cumplimiento)" style="min-height: 80px;">
                    <div class="font-bold text-sm truncate leading-tight" :title="item.area">
                        {{ item.area }}
                    </div>
                    <div class="text-xl font-extrabold my-1">
                        {{ item.porcentaje_cumplimiento.toFixed(1) }}<span class="text-sm">%</span>
                    </div>
                    <div class="text-xs opacity-80">
                        {{ item.total_rendidos }} rendiciones
                    </div>
                </div>
            </div>

            <!-- Leyenda -->
            <div class="flex justify-center items-center space-x-3 text-xs text-gray-600 pt-3 border-t border-gray-200">
                <span class="font-medium">Bajo</span>
                <div class="flex space-x-1">
                    <div class="w-4 h-3 rounded bg-red-500"></div>
                    <div class="w-4 h-3 rounded bg-orange-500"></div>
                    <div class="w-4 h-3 rounded bg-yellow-400"></div>
                    <div class="w-4 h-3 rounded bg-lime-500"></div>
                    <div class="w-4 h-3 rounded bg-green-600"></div>
                </div>
                <span class="font-medium">Excelente</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Map } from 'lucide-vue-next';

const props = defineProps({
    data: {
        type: Array,
        required: true,
        default: () => []
    }
});

/**
 * Calcula la clase de grid dinámicamente basada en la cantidad de elementos
 */
const getGridClass = () => {
    if (!props.data || props.data.length === 0) return 'grid-cols-1';

    const count = props.data.length;

    if (count <= 2) {
        return 'grid-cols-1 sm:grid-cols-2';
    } else if (count <= 4) {
        return 'grid-cols-2 sm:grid-cols-2 lg:grid-cols-2';
    } else if (count <= 6) {
        return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-3';
    } else {
        return 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4';
    }
};

/**
 * Determina la clase de color de fondo basada en el porcentaje de cumplimiento.
 * Esto crea el efecto de "mapa de calor".
 * @param {number} percentage - El porcentaje de cumplimiento (0 a 100).
 * @returns {string} - La clase de Tailwind CSS para el color de fondo.
 */
const getColorForPercentage = (percentage) => {
    if (percentage >= 95) {
        return 'bg-green-600'; // Excelente
    } else if (percentage >= 85) {
        return 'bg-lime-500'; // Bueno
    } else if (percentage >= 70) {
        return 'bg-yellow-400 text-yellow-800'; // Regular
    } else if (percentage >= 50) {
        return 'bg-orange-500'; // Deficiente
    } else {
        return 'bg-red-500'; // Crítico
    }
};
</script>