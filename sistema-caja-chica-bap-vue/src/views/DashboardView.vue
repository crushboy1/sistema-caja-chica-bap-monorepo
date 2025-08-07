<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="max-w-7xl mx-auto p-6">

      <!-- Header Section -->
      <header class="bg-white rounded-2xl shadow-lg p-8 mb-8 border-l-4 border-verde-bap">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
          <div class="flex-1">
            <h1 class="text-4xl font-bold text-gray-800 mb-2">Dashboard de Control</h1>
            <p class="text-gray-600 text-lg">Resumen financiero y operativo de la gestión de fondos</p>
            <div class="flex items-center gap-4 mt-3">
              <span
                class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-verde-bap bg-opacity-10 text-verde-bap">
                📊 Actualizado en tiempo real
              </span>
              <span class="text-sm text-gray-500">
                Última actualización: {{ new Date().toLocaleString('es-PE') }}
              </span>
            </div>
          </div>

          <!-- Quick Actions -->
          <div class="flex gap-3">
            <div class="flex gap-3">
              <button @click="exportarDatos" class="btn-secondary">
                <Download class="w-4 h-4 mr-2" /> Exportar
              </button>
              <button @click="fetchDashboardData" class="btn-primary">
                <RefreshCw class="w-4 h-4 mr-2" /> Actualizar
              </button>
            </div>
          </div>
        </div>
      </header>

      <!-- Filters Section -->
      <section class="bg-white rounded-2xl shadow-md p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2">
            🔍 Filtros de Consulta
          </h3>
          <button @click="resetearFiltros" class="text-sm text-gray-500 hover:text-gray-700">
            Limpiar filtros
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="form-group">
            <label class="form-label">Fecha Inicio</label>
            <input type="date" v-model="filtros.fecha_inicio" class="form-input" />
          </div>

          <div class="form-group">
            <label class="form-label">Fecha Fin</label>
            <input type="date" v-model="filtros.fecha_fin" class="form-input" />
          </div>

          <div v-if="puedeVerFiltrosAdmin" class="form-group">
            <label class="form-label">Área</label>
            <select v-model="filtros.area_id" class="form-input">
              <option :value="null">Todas las Áreas</option>
              <option v-for="area in areas" :key="area.id" :value="area.id">
                {{ area.name }}
              </option>
            </select>
          </div>

          <div v-if="puedeVerFiltrosAdmin" class="form-group">
            <label class="form-label">Responsable</label>
            <select v-model="filtros.responsable_id" class="form-input">
              <option :value="null">Todos los Responsables</option>
              <option v-for="user in usuarios" :key="user.id" :value="user.id">
                {{ user.name }} {{ user.last_name }}
              </option>
            </select>
          </div>
        </div>
      </section>

      <!-- Loading State -->
      <div v-if="cargando" class="space-y-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div v-for="i in 8" :key="i" class="bg-white p-6 rounded-xl shadow-md animate-pulse">
            <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
            <div class="h-8 bg-gray-300 rounded w-1/2"></div>
          </div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="!dashboardData" class="text-center py-16">
        <div class="bg-white rounded-2xl shadow-lg p-12">
          <div class="text-6xl mb-4">⚠️</div>
          <h3 class="text-xl font-semibold text-gray-800 mb-2">Error al cargar datos</h3>
          <p class="text-gray-500 mb-6">No se pudieron cargar los datos del dashboard</p>
          <button @click="fetchDashboardData" class="btn-primary">
            <RefreshCw class="w-4 h-4 mr-2" /> Reintentar
          </button>
        </div>
      </div>

      <!-- Dashboard Content -->
      <div v-else class="space-y-8">

        <!-- KPIs Principales -->
        <section>
          <SectionHeader title="📊 Indicadores Principales" />
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <KpiCard titulo="Fondos Activos" :valor="dashboardData.kpisGenerales.total_fondos_activos"
              icono="FolderKanban" color="blue" />
            <KpiCard titulo="Monto Total Asignado" :valor="dashboardData.kpisGenerales.monto_total_asignado"
              formato="moneda" icono="CircleDollarSign" color="green" />
            <KpiCard titulo="Monto Total Ejecutado" :valor="dashboardData.kpisGenerales.monto_total_ejecutado"
              formato="moneda" icono="TrendingDown" color="orange" />
            <KpiCard titulo="% de Ejecución" :valor="dashboardData.kpisGenerales.porcentaje_ejecucion"
              formato="porcentaje" icono="PieChart" color="purple" />
          </div>
        </section>

        <!-- Gastos Declarados -->
        <section>
          <SectionHeader title="💳 Análisis de Gastos" />
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <KpiCard titulo="Monto Total Ejecutado" :valor="dashboardData.kpisGastos.monto_total_ejecutado"
              formato="moneda" icono="Receipt" color="indigo"
              descripcion="Suma de gastos en estado Contabilizado o Repuesto." />
            <KpiCard titulo="Monto en Proceso" :valor="dashboardData.kpisGastos.monto_total_en_proceso" formato="moneda"
              icono="FileClock" color="yellow" descripcion="Suma de gastos pendientes de aprobación o validación." />
            <KpiCard titulo="% Gastos Rechazados" :valor="dashboardData.kpisGastos.porcentaje_gastos_rechazados"
              formato="porcentaje" icono="Ban" color="pink"
              descripcion="Porcentaje de gastos rechazados sobre el total." />
            <KpiCard titulo="Monto Total Excedido" :valor="dashboardData.kpisGastos.monto_total_excedido"
              formato="moneda" icono="AlertCircle" color="red"
              descripcion="Suma de montos que superaron lo proyectado al registrar." />
          </div>

          <!-- Gráficos de Gastos -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <ChartCard title="Top 10 Gastos por Categoría">
              <GraficoBarras v-if="datosGraficoBarras" :chart-data="datosGraficoBarras" />
            </ChartCard>

            <ChartCard title="Distribución de Gastos por Estado">
              <GraficoCircular v-if="datosGraficoEstados" :chart-data="datosGraficoEstados" />
            </ChartCard>

            <ChartCard title="Evolución Mensual: Presupuesto vs Gasto Ejecutado" height="h-[420px]">
              <GraficoEvolucionMensual v-if="datosEvolucionMensual && datosEvolucionMensual.length > 0"
                :chart-data="datosEvolucionMensual" :show-controls="true" :show-summary="true" :default-period="12"
                @period-changed="actualizarPeriodoEvolucion" />
              <div v-else class="chart-placeholder">
                <TrendingUp class="w-12 h-12 text-gray-400 mb-2" />
                <p class="text-gray-500">Datos en preparación</p>
              </div>
            </ChartCard>

            <ChartCard title="Distribución de Fondos por Tipo">
              <GraficoCircular v-if="datosGraficoTipos" :chart-data="datosGraficoTipos" />
            </ChartCard>
          </div>
        </section>

        <!-- Análisis por Usuario/Área -->
        <section>
          <SectionHeader title="👥 Análisis por Usuario y Área " />
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Top Usuarios -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
              <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">🏆 Top 5 Usuarios con más Gastos Declarados</h3>
                <span class="text-sm text-gray-500">Período actual</span>
              </div>

              <div class="space-y-3 max-h-80 overflow-y-auto custom-scrollbar">
                <div v-for="(usuario, index) in dashboardData.usuariosConMayorGastos" :key="usuario.usuario"
                  class="ranking-item" :class="getRankingClass(index)">
                  <div class="flex items-center gap-4">
                    <div class="ranking-number" :class="getRankingNumberClass(index)">
                      {{ index + 1 }}
                    </div>
                    <div class="flex-1">
                      <p class="font-semibold text-gray-800">{{ usuario.usuario }}</p>
                      <p class="text-sm text-gray-500">{{ usuario.cantidad_gastos }} gastos</p>
                    </div>
                    <div class="text-right">
                      <p class="font-bold text-verde-bap">{{ currencyFormatter.format(usuario.monto_total) }}</p>
                      <div class="w-20 bg-gray-200 rounded-full h-2 mt-1">
                        <div class="bg-verde-bap h-2 rounded-full transition-all duration-500"
                          :style="{ width: getPercentageWidth(usuario.monto_total, dashboardData.usuariosConMayorGastos[0].monto_total) + '%' }">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Cumplimiento de Rendiciones -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
              <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">📋 Cumplimiento en Rendiciones</h3>
                <span class="text-sm text-gray-500">Estado actual</span>
              </div>

              <div class="space-y-6">
                <!-- Indicador principal -->
                <div class="text-center">
                  <div class="relative inline-flex items-center justify-center w-32 h-32">
                    <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 36 36">
                      <path class="text-gray-200" stroke="currentColor" stroke-width="3" fill="none"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                      <path class="text-verde-bap transition-all duration-1000" stroke="currentColor" stroke-width="3"
                        fill="none" stroke-linecap="round"
                        :stroke-dasharray="`${dashboardData.cumplimientoRendiciones.porcentaje_cumplimiento}, 100`"
                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                      <span class="text-2xl font-bold text-verde-bap">
                        {{ dashboardData.cumplimientoRendiciones.porcentaje_cumplimiento }}%
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Detalle -->
                <div class="grid grid-cols-3 gap-4">
                  <div class="text-center p-3 bg-green-50 rounded-xl">
                    <div class="text-2xl font-bold text-green-600">
                      {{ dashboardData.cumplimientoRendiciones.rendiciones_a_tiempo }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">A tiempo</div>
                  </div>
                  <div class="text-center p-3 bg-red-50 rounded-xl">
                    <div class="text-2xl font-bold text-red-600">
                      {{ dashboardData.cumplimientoRendiciones.rendiciones_fuera_plazo }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">Fuera de plazo</div>
                  </div>
                  <div class="text-center p-3 bg-yellow-50 rounded-xl">
                    <div class="text-2xl font-bold text-yellow-600">
                      {{ dashboardData.cumplimientoRendiciones.pendientes_rendicion }}
                    </div>
                    <div class="text-sm text-gray-600 mt-1">Pendientes</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Top Áreas (Solo Admins) -->
            <div v-if="puedeVerFiltrosAdmin && dashboardData.topAreasPorGasto"
              class="bg-white rounded-2xl shadow-lg p-6">
              <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">🏢 Top 5 Áreas - Mayor Gasto Ejecutado</h3>
              </div>

              <div class="space-y-3">
                <div v-for="(area, index) in dashboardData.topAreasPorGasto" :key="area.area"
                  class="flex items-center justify-between p-4 bg-gray-50 rounded-xl hover:bg-gray-100 transition-colors">
                  <div class="flex items-center gap-3">
                    <span
                      class="flex items-center justify-center w-8 h-8 bg-blue-100 text-blue-600 rounded-full text-sm font-bold">
                      {{ index + 1 }}
                    </span>
                    <p class="font-semibold text-gray-800">{{ area.area }}</p>
                  </div>
                  <p class="font-bold text-blue-600">{{ currencyFormatter.format(area.total) }}</p>
                </div>
              </div>
            </div>

            <!-- Mapa de Calor-->
            <div v-if="puedeVerFiltrosAdmin" class="bg-white rounded-2xl shadow-lg p-6">
              <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-semibold text-gray-800">🗺️ Mapa de Calor - Cumplimiento por Área</h3>
              </div>
              <MapaCalorCumplimiento v-if="datosMapaCalor" :data="datosMapaCalor" />
            </div>
          </div>
        </section>

        <!-- Alertas y Control -->
        <section>
          <SectionHeader title="🚨 Centro de Alertas y Control" />
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- COMENTARIO BAP: Se ajusta el `find` para buscar los nuevos tipos de alerta del backend. -->
            <KpiCard titulo="Sobregiro de Fondos"
              :valor="dashboardData.alertas.find(a => a.tipo === 'sobregiro_fondo')?.cantidad || 0"
              icono="AlertTriangle" color="red" />
            <!-- COMENTARIO BAP: Se añade un nuevo KPI para la nueva alerta de Desviación de Proyección. -->
            <KpiCard titulo="Desviación de Proyección"
              :valor="dashboardData.alertas.find(a => a.tipo === 'desviacion_proyeccion')?.cantidad || 0"
              icono="AlertCircle" color="blue" />
            <KpiCard titulo="Montos Inusuales"
              :valor="dashboardData.alertas.find(a => a.tipo === 'monto_inusual')?.cantidad || 0" icono="TrendingUp"
              color="orange" />
            <KpiCard titulo="Rendiciones Tardías"
              :valor="dashboardData.alertas.find(a => a.tipo === 'rendicion_fuera_plazo')?.cantidad || 0" icono="Clock"
              color="yellow" />
          </div>

          <!-- Detalle de Alertas -->
          <div v-if="dashboardData.alertas && dashboardData.alertas.length > 0"
            class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <AlertPanel v-for="alerta in dashboardData.alertas" :key="alerta.tipo" :alerta="alerta" :user="user"
              @action-clicked="handleAlertaAction" />
          </div>
          <div v-else class="bg-white rounded-2xl shadow-lg p-8 text-center">
            <CheckCircle2 class="w-16 h-16 text-green-500 mx-auto mb-4" />
            <h3 class="text-xl font-semibold text-gray-800">Todo en Orden</h3>
            <p class="text-gray-500 mt-2">No se han detectado alertas en el período seleccionado.</p>
          </div>
        </section>

        <!-- Línea de Tiempo -->
        <section>
          <SectionHeader title="📈 Análisis Temporal y Tendencias" />
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

            <!-- Línea de Tiempo del Ciclo de Vida -->
            <div class="bg-white rounded-2xl shadow-lg p-6">
              <h3 class="text-lg font-semibold text-gray-800 mb-6">🕐 Actividad Reciente de Fondos</h3>
              <TimelineCicloDeVida v-if="datosTimeline" :timelines="datosTimeline" />
            </div>

            <!-- Evolución Mensual de Gastos por Categoría -->
            <ChartCard title="Evolución Mensual de Gastos por Categoría" height="h-[420px]">
              <GraficoBarras v-if="datosGraficoEvolucionCategoria" :chart-data="datosGraficoEvolucionCategoria"
                :stacked="true" />
              <div v-else class="chart-placeholder">
                <BarChart3 class="w-12 h-12 text-gray-400 mb-2" />
                <p class="text-gray-500">No hay datos para esta visualización</p>
              </div>
            </ChartCard>
          </div>
        </section>
      </div>
    </div>
  </div>

</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { TrendingUp, BarChart3, FileClock, CheckCircle2, AlertCircle, Ban, Download, RefreshCw, FolderKanban, CircleDollarSign, PieChart, Receipt, Clock } from 'lucide-vue-next';
import api from '@/plugins/axios';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';
import KpiCard from '@/components/dashboard/KpiCard.vue';
import GraficoBarras from '@/components/dashboard/GraficoBarras.vue';
import GraficoCircular from '@/components/dashboard/GraficoCircular.vue';
import AlertPanel from '@/components/dashboard/AlertPanel.vue';
import SectionHeader from '@/components/dashboard/SectionHeader.vue';
import ChartCard from '@/components/dashboard/ChartCard.vue';
import GraficoEvolucionMensual from '@/components/dashboard/GraficoEvolucionMensual.vue';
import MapaCalorCumplimiento from '@/components/dashboard/MapaCalorCumplimiento.vue';
import TimelineCicloDeVida from '@/components/dashboard/TimelineCicloDeVida.vue';
const props = defineProps({
  user: {
    type: Object,
    required: true,
  }
});
const router = useRouter();
// Estado
const cargando = ref(true);
const dashboardData = ref(null);
const getLocalDateISOString = (date) => {
  const tzoffset = date.getTimezoneOffset() * 60000;
  const localISOTime = (new Date(date - tzoffset)).toISOString().slice(0, 10);
  return localISOTime;
};

const filtros = ref({
  fecha_inicio: getLocalDateISOString(new Date(new Date().getFullYear(), new Date().getMonth(), 1)),
  fecha_fin: getLocalDateISOString(new Date()),
  area_id: null,
  responsable_id: null,
});
const areas = ref([]);
const usuarios = ref([]);

// Utilidades
const currencyFormatter = new Intl.NumberFormat('es-PE', {
  style: 'currency',
  currency: 'PEN'
});

// Computed
const puedeVerFiltrosAdmin = computed(() => {
  return props.user && ['super_admin', 'jefe_administracion', 'gerente_general'].includes(props.user.role.name);
});

const datosGraficoEstados = computed(() => {
  if (!dashboardData.value?.gastosPorEstado) return null;
  const labels = Object.keys(dashboardData.value.gastosPorEstado);
  const data = Object.values(dashboardData.value.gastosPorEstado);
  return {
    labels,
    datasets: [{
      data,
      backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#3B82F6', '#8B5CF6'],
    }]
  };
});

const datosGraficoTipos = computed(() => {
  if (!dashboardData.value?.fondosPorTipo) return null;
  return {
    labels: dashboardData.value.fondosPorTipo.labels,
    datasets: [{
      data: dashboardData.value.fondosPorTipo.data,
      backgroundColor: ['#6366F1', '#EC4899', '#F59E0B', '#10B981'],
    }]
  };
});

const datosGraficoBarras = computed(() => {
  if (!dashboardData.value?.gastosPorCategoria) return null;
  return {
    labels: dashboardData.value.gastosPorCategoria.labels,
    datasets: [{
      label: 'Monto Gastado',
      data: dashboardData.value.gastosPorCategoria.data,
      backgroundColor: '#10B981',
      borderColor: '#059669',
      borderWidth: 1,
    }]
  };
});

const datosEvolucionMensual = computed(() => {
  // 1. Verificar que los datos existan. Si no, devolver null para ocultar el gráfico.
  if (!dashboardData.value?.evolucionMensual || dashboardData.value.evolucionMensual.length === 0) {
    return null;
  }
  return dashboardData.value.evolucionMensual;
});
const datosGraficoEvolucionCategoria = computed(() => {
  if (!dashboardData.value?.evolucionGastosPorCategoria) return null;
  return dashboardData.value.evolucionGastosPorCategoria;
});
const datosMapaCalor = computed(() => {
  if (!dashboardData.value?.cumplimientoPorArea) return null;
  return dashboardData.value.cumplimientoPorArea;
});
const datosTimeline = computed(() => {
  if (!dashboardData.value?.timelines) return null;
  return dashboardData.value.timelines;
});
// Métodos
const fetchDashboardData = async () => {
  cargando.value = true;
  dashboardData.value = null;
  try {
    const response = await api.get('/v1/dashboard', { params: filtros.value });
    dashboardData.value = response.data;
  } catch (error) {
    console.error("Error al cargar datos del dashboard:", error);
    Swal.fire({ title: 'Error', text: 'No se pudieron cargar los datos del dashboard.', icon: 'error', confirmButtonColor: '#10B981' });
  } finally {
    cargando.value = false;
  }
};
const fetchFiltersData = async () => {
  if (puedeVerFiltrosAdmin.value) {
    try {
      const [areasRes, usuariosRes] = await Promise.all([
        api.get('/v1/areas'),
        api.get('/v1/users/list-for-select')
      ]);
      areas.value = areasRes.data.areas;
      usuarios.value = usuariosRes.data;
    } catch (error) {
      console.error("Error al cargar datos para filtros:", error);
    }
  }
};

const resetearFiltros = () => {
  filtros.value = {
    fecha_inicio: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    fecha_fin: new Date().toISOString().slice(0, 10),
    area_id: null,
    responsable_id: null,
  };
};

const exportarDatos = async () => {
  try {
    const response = await api.get('/v1/dashboard/export', {
      params: filtros.value,
      responseType: 'blob'
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `dashboard-${new Date().toISOString().slice(0, 10)}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();

    await Swal.fire({
      title: 'Exportación exitosa',
      text: 'Los datos se han exportado correctamente.',
      icon: 'success',
      confirmButtonColor: '#10B981'
    });
  } catch (error) {
    console.error("Error al exportar:", error);
    await Swal.fire({
      title: 'Error',
      text: 'No se pudo exportar los datos.',
      icon: 'error',
      confirmButtonColor: '#10B981'
    });
  }
};
const actualizarPeriodoEvolucion = (nuevoPeriodo) => {
  console.log(`El período del gráfico de evolución ha cambiado a: ${nuevoPeriodo} meses`);
  // Aquí podríamos añadir lógica en el futuro si fuera necesario.
};

const getRankingClass = (index) => {
  if (index < 3) return 'bg-gradient-to-r from-yellow-50 to-orange-50 border-l-4 border-yellow-400';
  return 'bg-gray-50 hover:bg-gray-100';
};

const getRankingNumberClass = (index) => {
  if (index === 0) return 'bg-yellow-400 text-white';
  if (index === 1) return 'bg-gray-400 text-white';
  if (index === 2) return 'bg-orange-400 text-white';
  return 'bg-gray-200 text-gray-600';
};

const getPercentageWidth = (valor, maximo) => {
  return (valor / maximo) * 100;
};

//Captura evento de click de los botones de alertas.
const handleAlertaAction = (alerta) => {
  // Se define un mapa de acciones para cada tipo de alerta. Esto es más limpio que un switch.
  const actions = {
    'sobregiro_fondo': () => {
      const codigos = (alerta.detalles || []).map(d => d.codigo_fondo).join(',');
      if (codigos) router.push({ path: '/dashboard/fondos', query: { alerta: 'sobregiro', codigos } });
    },
    'desviacion_proyeccion': () => {
      const codigos = (alerta.detalles || []).map(d => d.codigo_gasto).join(',');
      if (codigos) router.push({ path: '/dashboard/declaraciones', query: { tab: 'reportes', alerta: 'desviacion', codigos } });
    },
    'monto_inusual': () => {
      const accionables = (alerta.detalles || []).filter(d => d.es_accionable);
      if (accionables.length === 0) {
        Swal.fire('Información', 'Todos los gastos inusuales detectados ya han sido procesados.', 'info');
        return;
      }
      const codigos = accionables.map(d => d.codigo_gasto).join(',');
      router.push({ path: '/dashboard/declaraciones', query: { tab: 'validacionContable', alerta: 'inusual', codigos } });
    },
    'rendicion_fuera_plazo': () => {
      const codigos = (alerta.detalles || []).map(d => d.codigo_gasto).join(',');
      if (codigos) router.push({ path: '/dashboard/declaraciones', query: { tab: 'reportes', alerta: 'tardia', codigos } });
    }
  };

  const action = actions[alerta.tipo];
  if (action) {
    action();
  } else {
    console.warn('Acción no definida para el tipo de alerta:', alerta.tipo);
  }
};
// Lifecycle
onMounted(() => {
  fetchDashboardData();
  fetchFiltersData();
});

// Watchers
watch(filtros, () => {
  fetchDashboardData();
}, { deep: true });
</script>

<style scoped>
.form-input {
  @apply p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-1 focus:ring-verde-bap transition;
}

.form-group {
  @apply space-y-2;
}

.form-label {
  @apply block text-sm font-medium text-gray-700;
}

.form-input {
  @apply w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:border-verde-bap focus:ring-2 focus:ring-verde-bap focus:ring-opacity-20 transition-all duration-200;
}

.ranking-item {
  @apply p-4 rounded-xl transition-all duration-200 hover:shadow-md;
}

.ranking-number {
  @apply flex items-center justify-center w-8 h-8 rounded-full text-sm font-bold;
}
</style>