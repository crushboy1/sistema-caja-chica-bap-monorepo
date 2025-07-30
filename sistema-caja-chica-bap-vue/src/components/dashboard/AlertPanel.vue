<template>
    <div class="bg-white p-6 rounded-xl shadow-lg">
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

        <!-- Contenido específico según el tipo de alerta -->
        <div class="space-y-3 max-h-64 overflow-y-auto">
            <!-- Alertas de Sobregiro -->
            <template v-if="alerta.tipo === 'sobregiro'">
                <div v-for="fondo in alerta.fondos" :key="fondo.id"
                    class="flex items-center justify-between p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div>
                        <p class="font-semibold text-gray-800">{{ fondo.nombre }}</p>
                        <p class="text-sm text-gray-600">
                            Presupuesto: {{ currencyFormatter.format(fondo.monto_aprobado) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-red-600">{{ currencyFormatter.format(fondo.monto_gastado) }}</p>
                        <p class="text-xs text-red-500">
                            Exceso: {{ currencyFormatter.format(fondo.exceso) }}
                        </p>
                    </div>
                </div>
            </template>

            <!-- Alertas de Montos Inusuales -->
            <template v-if="alerta.tipo === 'monto_inusual'">
                <div class="mb-3 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                    <p class="text-sm text-gray-600">
                        <strong>Promedio normal:</strong> {{ currencyFormatter.format(alerta.promedio_normal) }} |
                        <strong>Límite de alerta:</strong> {{ currencyFormatter.format(alerta.limite_alerta) }}
                    </p>
                </div>
                <div v-for="gasto in alerta.gastos" :key="gasto.id"
                    class="flex items-center justify-between p-3 bg-orange-50 border border-orange-200 rounded-lg">
                    <div>
                        <p class="font-semibold text-gray-800">{{ gasto.usuario }}</p>
                        <p class="text-sm text-gray-600">{{ gasto.area }} • {{ formatDate(gasto.fecha) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-orange-600">{{ currencyFormatter.format(gasto.monto) }}</p>
                        <p class="text-xs text-orange-500">ID: {{ gasto.id }}</p>
                    </div>
                </div>
            </template>

            <!-- Alertas de Rendiciones Fuera de Plazo -->
            <template v-if="alerta.tipo === 'rendicion_fuera_plazo'">
                <div v-for="gasto in alerta.gastos" :key="gasto.id"
                    class="flex items-center justify-between p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <div>
                        <p class="font-semibold text-gray-800">{{ gasto.usuario }}</p>
                        <p class="text-sm text-gray-600">{{ gasto.area }}</p>
                        <p class="text-xs text-gray-500">
                            Límite: {{ formatDate(gasto.fecha_limite) }} •
                            Rendido: {{ formatDate(gasto.fecha_rendicion) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-yellow-600">{{ gasto.dias_retraso }} días</p>
                        <p class="text-xs text-yellow-500">de retraso</p>
                    </div>
                </div>
            </template>
        </div>

        <!-- Footer con acción si es necesario -->
        <div v-if="alerta.cantidad > 0" class="mt-4 pt-4 border-t border-gray-200">
            <button :class="actionButtonClasses"
                class="w-full py-2 px-4 rounded-lg text-sm font-medium transition-colors">
                {{ actionText }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { AlertTriangle, TrendingUp, Clock } from 'lucide-vue-next';

const props = defineProps({
    alerta: {
        type: Object,
        required: true
    }
});

const currencyFormatter = new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' });

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleDateString('es-PE');
};

const iconComponent = computed(() => {
    const iconMap = {
        'sobregiro': AlertTriangle,
        'monto_inusual': TrendingUp,
        'rendicion_fuera_plazo': Clock
    };
    return iconMap[props.alerta.tipo] || AlertTriangle;
});

const titulo = computed(() => {
    const tituloMap = {
        'sobregiro': 'Alertas de Sobregiro',
        'monto_inusual': 'Gastos con Montos Inusuales',
        'rendicion_fuera_plazo': 'Rendiciones Fuera de Plazo'
    };
    return tituloMap[props.alerta.tipo] || 'Alerta';
});

const iconClasses = computed(() => {
    const classMap = {
        'sobregiro': 'flex items-center justify-center w-12 h-12 bg-red-100 text-red-600 rounded-full',
        'monto_inusual': 'flex items-center justify-center w-12 h-12 bg-orange-100 text-orange-600 rounded-full',
        'rendicion_fuera_plazo': 'flex items-center justify-center w-12 h-12 bg-yellow-100 text-yellow-600 rounded-full'
    };
    return classMap[props.alerta.tipo] || 'flex items-center justify-center w-12 h-12 bg-gray-100 text-gray-600 rounded-full';
});

const badgeClasses = computed(() => {
    const classMap = {
        'sobregiro': 'bg-red-100 text-red-800',
        'monto_inusual': 'bg-orange-100 text-orange-800',
        'rendicion_fuera_plazo': 'bg-yellow-100 text-yellow-800'
    };
    return classMap[props.alerta.tipo] || 'bg-gray-100 text-gray-800';
});

const actionButtonClasses = computed(() => {
    const classMap = {
        'sobregiro': 'bg-red-600 hover:bg-red-700 text-white',
        'monto_inusual': 'bg-orange-600 hover:bg-orange-700 text-white',
        'rendicion_fuera_plazo': 'bg-yellow-600 hover:bg-yellow-700 text-white'
    };
    return classMap[props.alerta.tipo] || 'bg-gray-600 hover:bg-gray-700 text-white';
});

const actionText = computed(() => {
    const textMap = {
        'sobregiro': 'Revisar Fondos con Sobregiro',
        'monto_inusual': 'Revisar Gastos Inusuales',
        'rendicion_fuera_plazo': 'Gestionar Rendiciones Tardías'
    };
    return textMap[props.alerta.tipo] || 'Ver Detalles';
});
</script>