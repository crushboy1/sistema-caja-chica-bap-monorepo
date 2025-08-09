<template>
    <div class="bg-white rounded-md border border-gray-200 text-sm">
        <!-- Header del detalle -->
        <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 py-3 border-b border-gray-200 rounded-t-md">
            <div class="flex items-center justify-between">
                <h4 class="font-semibold text-gray-800 flex items-center">
                    <FileText class="w-5 h-5 mr-2 text-blue-500" />
                    Detalle de Cambios
                </h4>
                <div class="flex items-center text-xs text-gray-500">
                    <Clock class="w-4 h-4 mr-1" />
                    {{ cambiosCount }} {{ cambiosCount === 1 ? 'campo modificado' : 'campos modificados' }}
                </div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="p-4">
            <!-- Información adicional si existe -->
            <div v-if="log.descripcion && log.descripcion !== defaultDescription"
                class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-md">
                <div class="flex">
                    <Info class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm font-medium text-blue-800">Descripción</p>
                        <p class="text-sm text-blue-700 mt-1">{{ log.descripcion }}</p>
                    </div>
                </div>
            </div>

            <!-- Tabla de cambios -->
            <div v-if="formattedDetails.length > 0" class="overflow-hidden border border-gray-200 rounded-md">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Campo Modificado
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Valor Anterior
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Valor Nuevo
                            </th>
                            <th
                                class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Tipo de Cambio
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="detalle in formattedDetails" :key="detalle.campo"
                            class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-2 h-2 bg-blue-400 rounded-full mr-3"></div>
                                    <span class="font-mono text-sm font-medium text-gray-800">{{ detalle.campo }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-red-50 text-red-800 border border-red-200">
                                        <Minus class="w-3 h-3 mr-1" />
                                        {{ detalle.anterior }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center">
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-green-50 text-green-800 border border-green-200">
                                        <Plus class="w-3 h-3 mr-1" />
                                        {{ detalle.nuevo }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span :class="getChangeTypeClass(detalle.tipo)"
                                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium">
                                    <component :is="getChangeTypeIcon(detalle.tipo)" class="w-3 h-3 mr-1" />
                                    {{ detalle.tipo }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Metadata adicional -->
            <div v-if="log.modelo_afectado" class="mt-4 p-3 bg-gray-50 border border-gray-200 rounded-md">
                <div class="flex items-center text-sm text-gray-600">
                    <Database class="w-4 h-4 mr-2" />
                    <span class="font-medium">Registro afectado:</span>
                    <span class="ml-1">{{ log.modelo_afectado.tipo }} (ID: {{ log.modelo_afectado.id }})</span>
                </div>
            </div>

            <!-- Estado vacío -->
            <div v-if="formattedDetails.length === 0" class="text-center py-8">
                <FileX class="mx-auto h-12 w-12 text-gray-400" />
                <p class="mt-2 text-sm text-gray-500">No hay cambios específicos para mostrar</p>
                <p class="text-xs text-gray-400 mt-1">Esta acción no generó modificaciones en campos individuales</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import {
    FileText,
    Clock,
    Info,
    Minus,
    Plus,
    FileX,
    Database,
    UserPlus,
    UserMinus,
    ToggleLeft,
    Hash,
    Edit
} from 'lucide-vue-next';
import { getClassesForChangeTypeBadge } from '@/utils/statusStyles.js';

const props = defineProps({
    log: {
        type: Object,
        required: true,
    }
});

/**
 * Descripción por defecto para comparación
 */
const defaultDescription = computed(() => {
    const cambios = props.log.propiedades?.cambios || [];
    if (cambios.length === 0) return 'Sin cambios específicos';
    if (cambios.length === 1) return 'Se modificó 1 campo';
    return `Se modificaron ${cambios.length} campos`;
});

/**
 * Cuenta de cambios realizados
 */
const cambiosCount = computed(() => {
    return props.log.propiedades?.cambios?.length || 0;
});

/**
 * Propiedad computada que transforma los cambios del log
 * en un array formateado para la tabla
 */
const formattedDetails = computed(() => {
    const cambios = props.log.propiedades?.cambios || [];

    return cambios.map(cambio => {
        return {
            campo: cambio.campo,
            anterior: formatValue(cambio.valor_anterior),
            nuevo: formatValue(cambio.valor_nuevo),
            tipo: getChangeType(cambio.valor_anterior, cambio.valor_nuevo)
        };
    });
});

/**
 * Formatea valores para que sean más legibles
 */
const formatValue = (value) => {
    if (value === null || value === undefined) return 'Sin valor';
    if (value === '') return 'Vacío';
    if (value === true) return 'Activado';
    if (value === false) return 'Desactivado';

    // Si es un número, formatearlo
    if (typeof value === 'number') {
        if (value.toString().includes('.')) {
            return value.toLocaleString('es-PE', { minimumFractionDigits: 2 });
        }
        return value.toLocaleString('es-PE');
    }

    // Si es una fecha, intentar formatearla
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
        try {
            const date = new Date(value);
            if (!isNaN(date.getTime())) {
                return date.toLocaleDateString('es-PE', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    ...(value.includes('T') && { hour: '2-digit', minute: '2-digit' })
                });
            }
        } catch (e) {
            // Si falla el parseo de fecha, usar el valor original
        }
    }

    // Truncar strings muy largos
    if (typeof value === 'string' && value.length > 50) {
        return value.substring(0, 47) + '...';
    }

    return String(value);
};

/**
 * Determina el tipo de cambio realizado
 */
const getChangeType = (oldValue, newValue) => {
    if (oldValue === null || oldValue === undefined || oldValue === '') {
        return 'Agregado';
    }
    if (newValue === null || newValue === undefined || newValue === '') {
        return 'Eliminado';
    }
    if (typeof oldValue === 'boolean' || typeof newValue === 'boolean') {
        return 'Activación';
    }
    if (typeof oldValue === 'number' || typeof newValue === 'number') {
        return 'Numérico';
    }
    return 'Modificado';
};

/**
 * Obtiene las clases CSS para el tipo de cambio usando statusStyles
 */
const getChangeTypeClass = (tipo) => {
    return getClassesForChangeTypeBadge(tipo);
};

/**
 * Obtiene el icono apropiado para cada tipo de cambio
 */
const getChangeTypeIcon = (tipo) => {
    const icons = {
        'Agregado': UserPlus,
        'Eliminado': UserMinus,
        'Activación': ToggleLeft,
        'Numérico': Hash,
        'Modificado': Edit
    };

    return icons[tipo] || Edit;
};
</script>