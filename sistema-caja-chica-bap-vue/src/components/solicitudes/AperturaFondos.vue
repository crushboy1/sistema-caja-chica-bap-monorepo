<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2'; // Importa SweetAlert2

// Definir eventos que este componente puede emitir a su padre
const emit = defineEmits(['solicitudEnviada']);

// --- Variables reactivas para los datos del formulario (en español) ---
const usuarioActual = ref(null);
const cargandoUsuario = ref(true);
const solicitud = ref({
  motivo_detalle: '',
  monto_solicitado: null,
  prioridad: 'Media',
});
const gastosProyectados = ref([]);

// Propiedad computada para calcular el total de gastos proyectados
const totalGastosProyectados = computed(() => {
  return gastosProyectados.value.reduce((sum, item) => sum + (item.monto_mensual_estimado || 0), 0);
});

// Función para obtener los datos del usuario autenticado y pre-llenar campos
const obtenerUsuarioAutenticado = async () => {
  try {
    const response = await api.get('/user');
    usuarioActual.value = response.data.user;
    cargandoUsuario.value = false;
  } catch (error) {
    console.error('Error al obtener datos del usuario autenticado:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error de Autenticación',
      text: 'No se pudieron cargar los datos del usuario. Por favor, inicia sesión de nuevo.',
      confirmButtonText: 'Aceptar'
    });
    cargandoUsuario.value = false;
  }
};

// Función para añadir un nuevo ítem de gasto proyectado (simplificado)
const agregarGastoProyectado = () => {
  gastosProyectados.value.push({
    descripcion_gasto: '',
    monto_mensual_estimado: null,
  });
};

// Función para eliminar un ítem de gasto proyectado
const removerGastoProyectado = (index) => {
  gastosProyectados.value.splice(index, 1);
};

// Función para manejar el envío del formulario
const manejarEnvio = async () => {
  // Validaciones básicas del frontend (se mantienen para feedback inmediato)
  if (!solicitud.value.motivo_detalle || solicitud.value.monto_solicitado === null || solicitud.value.monto_solicitado <= 0) {
    Swal.fire({
      icon: 'error',
      title: 'Error de Validación',
      text: 'Por favor, completa el motivo y el monto solicitado (debe ser mayor a 0).'
    });
    return;
  }
  if (!solicitud.value.prioridad) {
    Swal.fire({
      icon: 'error',
      title: 'Error de Validación',
      text: 'Por favor, selecciona una prioridad de solicitud.'
    });
    return;
  }
  if (gastosProyectados.value.length === 0) {
    Swal.fire({
      icon: 'error',
      title: 'Error de Validación',
      text: 'Debes añadir al menos un gasto proyectado.'
    });
    return;
  }
  for (const item of gastosProyectados.value) {
    if (!item.descripcion_gasto || item.monto_mensual_estimado === null || item.monto_mensual_estimado <= 0) {
      Swal.fire({
        icon: 'error',
        title: 'Error de Validación',
        text: 'Por favor, completa todos los campos de los gastos proyectados con valores válidos (descripción y monto estimado > 0).'
      });
      return;
    }
  }

  // --- Construir el contenido HTML para el modal de resumen ---
  let gastosHtml = '<ul>';
  gastosProyectados.value.forEach(gasto => {
    gastosHtml += `<li><strong>${gasto.descripcion_gasto}:</strong> S/. ${gasto.monto_mensual_estimado.toFixed(2)}</li>`;
  });
  gastosHtml += `</ul><p><strong>Total Gastos Proyectados:</strong> S/. ${totalGastosProyectados.value.toFixed(2)}</p>`;

  const resumenHtml = `
    <p><strong>Tipo de Solicitud:</strong> Apertura</p>
    <p><strong>Motivo:</strong> ${solicitud.value.motivo_detalle}</p>
    <p><strong>Monto Solicitado:</strong> S/. ${solicitud.value.monto_solicitado.toFixed(2)}</p>
    <p><strong>Prioridad:</strong> ${solicitud.value.prioridad}</p>
    <br>
    <h4>Gastos Proyectados:</h4>
    ${gastosHtml}
  `;

  // --- Mostrar el modal de confirmación con resumen ---
  const { isConfirmed } = await Swal.fire({
    title: '¿Confirmar Envío de Solicitud?',
    html: resumenHtml,
    icon: 'info',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Sí, Enviar Solicitud',
    cancelButtonText: 'Cancelar'
  });

  if (isConfirmed) {
    // Si el usuario confirma, proceder con el envío al backend
    const payload = {
      tipo_solicitud: 'Apertura',
      motivo_detalle: solicitud.value.motivo_detalle,
      monto_solicitado: solicitud.value.monto_solicitado,
      prioridad: solicitud.value.prioridad,
      id_solicitante: usuarioActual.value.id,
      id_area: usuarioActual.value.area_id,

      gastos_proyectados: gastosProyectados.value.map(item => ({
        descripcion_gasto: item.descripcion_gasto,
        monto_estimado: item.monto_mensual_estimado,
      })),
    };

    try {
      const response = await api.post('/solicitudes-fondo', payload);
      const codigoSolicitudGenerada = response.data.solicitud.codigo_solicitud;

      Swal.fire({
        icon: 'success',
        title: '¡Solicitud Enviada!',
        html: `¡Solicitud registrada y enviada al jefe de administración!<br>Código de solicitud: <strong>${codigoSolicitudGenerada}</strong>`,
        confirmButtonText: 'Aceptar'
      }).then(() => {
        // Limpiar formulario después de éxito
        solicitud.value = { motivo_detalle: '', monto_solicitado: null, prioridad: 'Media' };
        gastosProyectados.value = [];
        agregarGastoProyectado(); // Añadir un ítem inicial de nuevo
        emit('solicitudEnviada'); // Emitir evento para que el padre cambie la sección
      });

      console.log('Respuesta del servidor:', response.data);

    } catch (error) {
      console.error('Error al crear la solicitud:', error);
      let errorMessage = 'Hubo un error al crear la solicitud. Por favor, inténtalo de nuevo.';
      if (error.response) {
        if (error.response.data && error.response.data.errors) {
          errorMessage = '<ul>';
          for (const key in error.response.data.errors) {
            errorMessage += `<li>- ${error.response.data.errors[key].join(', ')}</li>`;
          }
          errorMessage += '</ul>';
        } else if (error.response.data && error.response.data.message) {
          errorMessage = error.response.data.message;
        }
      }
      Swal.fire({
        icon: 'error',
        title: 'Error en la Solicitud',
        html: errorMessage
      });
    }
  } else {
    // Si el usuario cancela, no se hace nada y el formulario permanece abierto
    console.log('Envío de solicitud cancelado por el usuario.');
  }
};

onMounted(() => {
  obtenerUsuarioAutenticado();
  agregarGastoProyectado(); // Añadir un ítem inicial para que el usuario pueda empezar a llenar
});
</script>

<template>
  <div class="p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-3xl font-bold text-gray-800 mb-6">Apertura de Fondo de Efectivo</h2>

    <div v-if="cargandoUsuario" class="text-center text-gray-500 py-8">
      Cargando datos del usuario...
    </div>

    <div v-else>
      <form @submit.prevent="manejarEnvio">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
          <div>
            <label for="solicitante" class="block text-sm font-medium text-gray-700 mb-1">Solicitante</label>
            <input
              type="text"
              id="solicitante"
              :value="usuarioActual?.name + ' ' + usuarioActual?.last_name"
              class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
              disabled
            />
          </div>
          <div>
            <label for="cargo" class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
            <input
              type="text"
              id="cargo"
              :value="usuarioActual?.cargo"
              class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
              disabled
            />
          </div>
          <div>
            <label for="area" class="block text-sm font-medium text-gray-700 mb-1">Área</label>
            <input
              type="text"
              id="area"
              :value="usuarioActual?.area?.name"
              class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
              disabled
            />
          </div>
          <div>
            <label for="fecha_solicitud" class="block text-sm font-medium text-gray-700 mb-1">Fecha de Solicitud</label>
            <input
              type="text"
              id="fecha_solicitud"
              :value="new Date().toLocaleDateString()"
              class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed"
              disabled
            />
          </div>
        </div>

        <div class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
          <h3 class="text-xl font-semibold text-gray-800 mb-4 flex justify-between items-center">
            Gastos Proyectados
            <button
              type="button"
              @click="agregarGastoProyectado"
              class="bg-verde-bap hover:bg-verde-bap-hover text-white font-semibold py-2 px-4 rounded-full transition-colors flex items-center"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z" clip-rule="evenodd" />
              </svg>
              Añadir Gasto
            </button>
          </h3>

          <div v-if="gastosProyectados.length === 0" class="text-gray-500 text-center py-4">
            No hay gastos proyectados. Haz clic en "Añadir Gasto" para empezar.
          </div>

          <div v-for="(item, index) in gastosProyectados" :key="index" class="bg-white p-4 rounded-lg shadow-sm mb-4 border border-gray-100">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
              <div class="md:col-span-2">
                <label :for="'descripcion_gasto_' + index" class="block text-sm font-medium text-gray-700 mb-1">Descripción del Tipo de Gasto <span class="text-rojo-bap">*</span></label>
                <input
                  type="text"
                  :id="'descripcion_gasto_' + index"
                  v-model="item.descripcion_gasto"
                  class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                  required
                />
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <label :for="'monto_mensual_estimado_' + index" class="block text-sm font-medium text-gray-700 mb-1">Monto Mensual Estimado (S/.) <span class="text-rojo-bap">*</span></label>
                  <input
                    type="number"
                    :id="'monto_mensual_estimado_' + index"
                    v-model.number="item.monto_mensual_estimado"
                    step="0.01"
                    min="0"
                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md bg-white shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                    required
                  />
                </div>
                <button
                  type="button"
                  @click="removerGastoProyectado(index)"
                  v-if="gastosProyectados.length > 1"
                  class="ml-4 p-2 bg-rojo-bap hover:bg-rojo-bap-hover text-white rounded-full transition-colors self-center"
                  title="Eliminar gasto"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm6 0a1 1 0 01-2 0v6a1 1 0 112 0V8z" clip-rule="evenodd" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <div class="text-right mt-6 pt-4 border-t border-gray-200">
            <span class="text-lg font-semibold text-gray-800">Total Gastos Proyectados: S/. {{ totalGastosProyectados.toFixed(2) }}</span>
          </div>
        </div>

        <div class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
          <h3 class="text-xl font-semibold text-gray-800 mb-4">Detalle de la Solicitud</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="mb-4 md:mb-0">
              <label for="motivo_detalle" class="block text-sm font-medium text-gray-700 mb-1">Motivo de la Solicitud <span class="text-rojo-bap">*</span></label>
              <textarea
                id="motivo_detalle"
                v-model="solicitud.motivo_detalle"
                rows="4"
                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap resize-none"
                required
              ></textarea>
            </div>
            <div>
              <div class="mb-4">
                <label for="monto_solicitado" class="block text-sm font-medium text-gray-700 mb-1">Monto Solicitado (S/.) <span class="text-rojo-bap">*</span></label>
                <input
                  type="number"
                  id="monto_solicitado"
                  v-model.number="solicitud.monto_solicitado"
                  step="0.01"
                  min="0"
                  class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                  required
                />
              </div>
              <div class="mb-4">
                <label for="prioridad" class="block text-sm font-medium text-gray-700 mb-1">Prioridad de Solicitud <span class="text-rojo-bap">*</span></label>
                <select
                  id="prioridad"
                  v-model="solicitud.prioridad"
                  class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                  required
                >
                  <option value="Urgente">Urgente</option>
                  <option value="Alta">Alta</option>
                  <option value="Media">Media</option>
                  <option value="Baja">Baja</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end">
          <button
            type="submit"
            class="bg-verde-bap hover:bg-verde-bap-hover text-white font-bold py-3 px-8 rounded-full transition-colors shadow-lg"
          >
            Enviar Solicitud
          </button>
        </div>
      </form>
    </div>

    </div>
</template>

<style scoped>
.resize-none {
  resize: none;
}
/* No se necesitan definiciones de colores BAP aquí, ya que se usan las clases de Tailwind */
/* Asegúrate de que tu main.css importe Tailwind y que tailwind.config.js tenga los colores definidos */
</style>
