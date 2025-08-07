<template>
    <div class="bg-white p-6 rounded-xl shadow-lg">
        <!-- Encabezado de la Alerta -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-3">
                <div :class="iconClasses">
                    <component :is="iconComponent" class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">{{ titulo }}</h3>
                    <p class="text-sm text-gray-500">{{ alerta.mensaje }}</p>
                </div>
            </div>
            <span :class="badgeClasses" class="px-3 py-1 rounded-full text-sm font-medium">
                {{ alerta.cantidad }}
            </span>
        </div>

        <!-- Contenido Detallado por Tipo de Alerta -->
        <div class="space-y-3 max-h-64 overflow-y-auto pr-2">

            <!-- 1. Alerta de Sobregiro de Fondo (Crítica) -->
            <template v-if="alerta.tipo === 'sobregiro_fondo'">
                <div v-for="(detalle, index) in alerta.detalles" :key="`sg-${index}`"
                    class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div>
                        <p class="font-semibold text-gray-800">{{ detalle.codigo_fondo }}</p>
                        <p class="text-xs text-gray-500 mt-1">{{ detalle.responsable_nombre }} | {{ detalle.area_nombre
                            }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-red-600">
                            Exceso: {{ currencyFormatter.format(detalle.exceso) }}
                        </p>
                        <p class="text-sm text-red-500">Requiere liquidación</p>
                    </div>
                </div>
            </template>

            <!-- 2. Alerta de Desviación de Proyección (Advertencia) -->
            <template v-if="alerta.tipo === 'desviacion_proyeccion'">
                <div v-for="(detalle, index) in alerta.detalles" :key="`dp-${index}`"
                    class="flex items-center justify-between p-3 bg-blue-50 border border-blue-200 rounded-lg">
                    <div>
                        <p class="font-semibold text-gray-800">{{ detalle.codigo_gasto }}</p>
                        <!-- ENRIQUECIMIENTO: Mostrar categoría y monto proyectado -->
                        <p class="text-xs text-gray-500 mt-1">
                            Cat: {{ detalle.categoria_nombre }} | Proyectado: {{
                                currencyFormatter.format(detalle.monto_proyectado) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-blue-600">
                            Exceso: {{ currencyFormatter.format(detalle.exceso) }}
                        </p>
                    </div>
                </div>
            </template>

            <!-- 3. Alertas de Montos Inusuales (Advertencia) -->
            <template v-if="alerta.tipo === 'monto_inusual'">
                <div v-for="detalle in alerta.detalles" :key="detalle.codigo_gasto"
                    class="flex items-center justify-between p-3 rounded-lg transition-opacity"
                    :class="detalle.es_accionable ? 'bg-orange-50 border border-orange-200' : 'bg-gray-50 border border-gray-200 opacity-70'">
                    <div>
                        <p class="font-semibold text-gray-800">{{ detalle.usuario }} ({{ detalle.codigo_gasto }})</p>
                        <!-- CORRECCIÓN: Mostrar el estado del gasto -->
                        <p class="text-xs text-gray-500 mt-1">
                            Estado: <span class="font-medium">{{ detalle.estado }}</span>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold" :class="detalle.es_accionable ? 'text-orange-600' : 'text-gray-600'">
                            {{ currencyFormatter.format(detalle.monto) }}
                        </p>
                    </div>
                </div>
            </template>

            <!-- 4. Alertas de Rendición Fuera de Plazo (Informativa) -->
            <template v-if="alerta.tipo === 'rendicion_fuera_plazo'">
                <div v-for="detalle in alerta.detalles" :key="detalle.codigo_gasto"
                    class="flex items-center justify-between p-3 rounded-lg transition-opacity bg-yellow-50 border border-yellow-200">
                    <div>
                        <p class="font-semibold text-gray-800">{{ detalle.usuario }} ({{ detalle.codigo_gasto }})</p>
                        <!-- ENRIQUECIMIENTO: Mostrar el área -->
                        <p class="text-sm text-gray-600">{{ detalle.area }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-yellow-800">
                            {{ detalle.dias_retraso }} día(s) tarde
                        </p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Footer con Botón de Acción -->
        <div v-if="puedeAccionarAlerta && alerta.cantidad > 0" class="mt-4 pt-4 border-t border-gray-200">
            <button @click="$emit('action-clicked', alerta)" :disabled="botonDeshabilitado" :class="actionButtonClasses"
                class="w-full py-2 px-4 rounded-lg text-sm font-medium transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                {{ actionText }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { AlertTriangle, TrendingUp, Clock, AlertCircle } from 'lucide-vue-next';

const props = defineProps({
    alerta: {
        type: Object,
        required: true
    },
    user: {
        type: Object,
        required: true
    }
});

defineEmits(['action-clicked']);

const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });
const puedeAccionarAlerta = computed(() => {
    if (!props.user || !props.user.role) return false;

    const userRole = props.user.role.name;
    const rolesAdmin = ['super_admin', 'jefe_administracion', 'gerente_general'];

    // Se define qué roles pueden accionar cada tipo de alerta.
    const permisosPorAlerta = {
        'sobregiro_fondo': rolesAdmin,
        'monto_inusual': rolesAdmin,
        'desviacion_proyeccion': rolesAdmin,
        'rendicion_fuera_plazo': rolesAdmin,
        // Si un Jefe de Área necesitara ver un reporte, se añadiría aquí:
        // 'rendicion_fuera_plazo': [...rolesAdmin, 'jefe_area'],
    };

    const rolesPermitidos = permisosPorAlerta[props.alerta.tipo];

    // Si no hay una regla definida para esta alerta, se oculta el botón por seguridad.
    if (!rolesPermitidos) return false;

    // Devuelve true si el rol del usuario está en la lista de roles permitidos para esta alerta.
    return rolesPermitidos.includes(userRole);
});
const cantidadAccionable = computed(() => {
    // Las alertas de sobregiro y montos inusuales son las únicas que pueden tener acciones directas.
    if (props.alerta.tipo === 'sobregiro_fondo' || props.alerta.tipo === 'monto_inusual') {
        return (props.alerta.detalles || []).filter(d => d.es_accionable).length;
    }
    return 0; // Las otras alertas son informativas o de reporte.
});

const iconMap = {
    'sobregiro_fondo': AlertTriangle,
    'desviacion_proyeccion': AlertCircle,
    'monto_inusual': TrendingUp,
    'rendicion_fuera_plazo': Clock
};

const tituloMap = {
    'sobregiro_fondo': 'Alertas de Sobregiro',
    'desviacion_proyeccion': 'Desviación de Proyección',
    'monto_inusual': 'Gastos con Montos Inusuales',
    'rendicion_fuera_plazo': 'Rendiciones Fuera de Plazo'
};

const classMap = {
    'sobregiro_fondo': {
        icon: 'bg-red-100 text-red-600',
        badge: 'bg-red-100 text-red-800',
        button: 'bg-red-600 hover:bg-red-700 text-white',
    },
    'desviacion_proyeccion': {
        icon: 'bg-blue-100 text-blue-600',
        badge: 'bg-blue-100 text-blue-800',
        button: 'bg-blue-600 hover:bg-blue-700 text-white',
    },
    'monto_inusual': {
        icon: 'bg-orange-100 text-orange-600',
        badge: 'bg-orange-100 text-orange-800',
        button: 'bg-orange-600 hover:bg-orange-700 text-white',
    },
    'rendicion_fuera_plazo': {
        icon: 'bg-yellow-100 text-yellow-600',
        badge: 'bg-yellow-100 text-yellow-800',
        button: 'bg-yellow-600 hover:bg-yellow-700 text-white',
    }
};

const defaultClasses = {
    icon: 'bg-gray-100 text-gray-600',
    badge: 'bg-gray-100 text-gray-800',
    button: 'bg-gray-600 hover:bg-gray-700 text-white',
};

const iconComponent = computed(() => iconMap[props.alerta.tipo] || AlertTriangle);
const titulo = computed(() => tituloMap[props.alerta.tipo] || 'Alerta');
const iconClasses = computed(() => `flex items-center justify-center w-12 h-12 rounded-full ${classMap[props.alerta.tipo]?.icon || defaultClasses.icon}`);
const badgeClasses = computed(() => classMap[props.alerta.tipo]?.badge || defaultClasses.badge);
const actionButtonClasses = computed(() => classMap[props.alerta.tipo]?.button || defaultClasses.button);

const botonDeshabilitado = computed(() => {
    // Solo se deshabilita si la alerta es accionable y no hay items accionables.
    if (props.alerta.tipo === 'sobregiro_fondo' || props.alerta.tipo === 'monto_inusual') {
        return cantidadAccionable.value === 0;
    }
    // Las demás alertas siempre tienen su botón habilitado.
    return false;
});

const actionText = computed(() => {
    const textMap = { 'sobregiro_fondo': 'Revisar Fondos', 'desviacion_proyeccion': 'Ver Gastos con Desviación', 'monto_inusual': 'Revisar Gastos Inusuales', 'rendicion_fuera_plazo': 'Ir al Reporte de Atrasos' };
    const baseText = textMap[props.alerta.tipo] || 'Ver Detalles';
    if (cantidadAccionable.value > 0) {
        return `${baseText} (${cantidadAccionable.value} Accionable${cantidadAccionable.value > 1 ? 's' : ''})`;
    }
    if (botonDeshabilitado.value) { return 'No hay acciones pendientes'; }
    return baseText;
});
</script>
