<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { isEqual, cloneDeep } from 'lodash-es';
const props = defineProps({
  /**
   * Prop opcional. Si se proporciona, el componente entra en modo "Edición".
   * Si es null, el componente está en modo "Creación".
   */
  solicitudAEditar: {
    type: Object,
    default: null
  },
  /**
   * Define el contexto de la edición para llamar al endpoint correcto.
   * Puede ser 'pendiente' o 'observada'.
   */
  modoEdicion: {
    type: String,
    default: 'crear' // 'crear', 'pendiente', 'observada'
  }
});
// Definir eventos que este componente puede emitir a su padre
const emit = defineEmits(['solicitudEnviada', 'solicitudActualizada', 'cancelar']);

// --- Variables reactivas para los datos del formulario (en español) ---
const usuarioActual = ref(null);
const cargando = ref(true);
const formData = ref({
  tipo_solicitud: 'Apertura',
  motivo_detalle: '',
  monto_solicitado: 0,
  prioridad: 'Media',
  gastos_proyectados: [],
  comentario_descargo: ''
});

const originalData = ref(null);
const mostrarPrioridad = computed(() => {
  // En modo creación (sin solicitudAEditar), solo mostrar si NO es Apertura
  if (!props.solicitudAEditar) {
    return formData.value.tipo_solicitud !== 'Apertura';
  }
  // En modo edición, mostrar según el tipo de la solicitud existente
  return props.solicitudAEditar.tipo_solicitud !== 'Apertura';
});
const hayCambios = computed(() => {
  if (!props.solicitudAEditar || !originalData.value) {
    // Si no estamos en modo edición, no aplicamos esta lógica.
    return true;
  }
  // Devuelve 'true' solo si el formData actual es diferente al original.
  // Usamos isEqual de lodash para una comparación profunda y fiable de objetos y arrays.
  return !isEqual(formData.value, originalData.value);
});
const tituloComponente = computed(() => {
  return props.solicitudAEditar ? 'Editar Solicitud de Fondo' : 'Apertura de Fondo de Efectivo';
});

const textoBotonEnvio = computed(() => {
  return props.solicitudAEditar ? 'Guardar Cambios' : 'Enviar Solicitud';
});

//formateador para asegurar consistencia en toda la UI.
const currencyFormatter = new Intl.NumberFormat('es-PE', {
  style: 'currency',
  currency: 'PEN',
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

// Propiedad computada para calcular el total de gastos proyectados
const totalGastosProyectados = computed(() => {
  if (!formData.value.gastos_proyectados) return 0;
  return formData.value.gastos_proyectados.reduce((sum, item) => {
    return sum + (parseFloat(item.monto_estimado) || 0);
  }, 0);
});

// Función para obtener los datos del usuario autenticado y pre-llenar campos
const inicializarFormulario = async () => {
  cargando.value = true;
  try {
    if (props.solicitudAEditar) {
      // MODO EDICIÓN
      const solicitudData = JSON.parse(JSON.stringify(props.solicitudAEditar));
      formData.value = {
        ...solicitudData,
        // Si la solicitud a editar tiene gastos, los mapeamos. Si no, el array queda vacío.
        gastos_proyectados: solicitudData.detalles_gastos_proyectados.map(g => ({
          descripcion_gasto: g.descripcion_gasto,
          monto_estimado: g.monto_estimado
        })) || [],
        comentario_descargo: ''
      };
      originalData.value = cloneDeep(formData.value);
      usuarioActual.value = solicitudData.solicitante;
    } else {
      // MODO CREACIÓN
      const response = await api.get('/user');
      usuarioActual.value = response.data;
      resetearFormulario();
    }
  } catch (error) {
    console.error('Error durante la inicialización:', error);
    Swal.fire('Error', 'No se pudieron cargar los datos necesarios.', 'error');
  } finally {
    cargando.value = false;
  }
};

const resetearFormulario = () => {
  formData.value = {
    tipo_solicitud: 'Apertura',
    motivo_detalle: '',
    monto_solicitado: 0,
    prioridad: 'Media',
    gastos_proyectados: [{ descripcion_gasto: '', monto_estimado: null }],
    comentario_descargo: ''
  };
};

// Función para añadir un nuevo ítem de gasto proyectado (simplificado)
const agregarGastoProyectado = () => {
  formData.value.gastos_proyectados.push({ descripcion_gasto: '', monto_estimado: null });
};

// Función para eliminar un ítem de gasto proyectado
const removerGastoProyectado = (index) => {
  if (formData.value.gastos_proyectados.length > 1) {
    formData.value.gastos_proyectados.splice(index, 1);
  }
};

// Función para manejar el envío del formulario
const manejarEnvio = async () => {
  // Validaciones básicas del frontend (se mantienen para feedback inmediato)
  const data = formData.value;

  // --- Validaciones de Frontend ---
  // CORRECCIÓN: Todas las validaciones ahora acceden directamente a las propiedades de 'data'.
  if (!data.motivo_detalle || !data.monto_solicitado || data.monto_solicitado <= 0) {
    Swal.fire({
      icon: 'error',
      title: 'Error de Validación',
      text: 'Por favor, completa el motivo y el monto solicitado (debe ser mayor a 0).'
    });
    return;
  }
  if (!data.prioridad) {
    Swal.fire({
      icon: 'error',
      title: 'Error de Validación',
      text: 'Por favor, selecciona una prioridad de solicitud.'
    });
    return;
  }
  if (!data.gastos_proyectados || data.gastos_proyectados.length === 0) {
    Swal.fire({
      icon: 'error',
      title: 'Error de Validación',
      text: 'Debes añadir al menos un gasto proyectado.'
    });
    return;
  }
  if (data.gastos_proyectados.some(g => !g.descripcion_gasto || !g.monto_estimado || g.monto_estimado <= 0)) {
    Swal.fire('Error de Validación', 'Todos los gastos proyectados deben tener una descripción y un monto estimado válido (> 0).', 'error');
    return;
  }
  if (parseFloat(data.monto_solicitado) !== parseFloat(totalGastosProyectados.value)) {
    Swal.fire('Error de Consistencia', 'El monto solicitado no coincide con el total de gastos proyectados. Esto no debería ocurrir.', 'error');
    return;
  }

  // Lógica para determinar el endpoint y el método HTTP
  const esModoEdicion = !!props.solicitudAEditar;
  let endpoint = '/solicitudes';
  let metodoApi = 'post';

  if (esModoEdicion) {
    metodoApi = 'put';
    if (props.modoEdicion === 'pendiente') {
      endpoint = `/solicitudes/${props.solicitudAEditar.id}/editar-pendiente`;
    } else if (props.modoEdicion === 'observada') {
      endpoint = `/solicitudes/${props.solicitudAEditar.id}/editar-observada`;
    }
  }
  // --- Construir el contenido HTML para el modal de resumen ---
  const gastosHtml = `
        <div class="scroll-modal" style="max-height: 150px; overflow-y: auto; border: 1px solid #eee; padding: 10px; border-radius: 8px; margin-top: 10px;">
            <ul>
                ${data.gastos_proyectados.map(g => `<li><strong>${g.descripcion_gasto}:</strong> S/. ${parseFloat(g.monto_estimado || 0).toFixed(2)}</li>`).join('')}
            </ul>
        </div>
        <p class="mt-2 text-right"><strong>Total: S/. ${totalGastosProyectados.value.toFixed(2)}</strong></p>
    `;
  const resumenHtml = `<div style="text-align: left; padding: 0 1rem;"><p><strong>Motivo:</strong> ${data.motivo_detalle}</p><p><strong>Monto Solicitado:</strong> ${currencyFormatter.format(data.monto_solicitado || 0)}</p><hr style="margin: 1rem 0;" /><Strong>Gastos Proyectados:</Strong>${gastosHtml}</div>`;

  // --- Mostrar el modal de confirmación con resumen ---
  const { isConfirmed } = await Swal.fire({
    customClass: {
      htmlContainer: 'swal-gastos-container'
    },
    title: esModoEdicion ? '¿Confirmar Cambios?' : '¿Confirmar Envío de Solicitud?',
    html: resumenHtml,
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: esModoEdicion ? 'Sí, Guardar Cambios' : 'Sí, Enviar',
    cancelButtonText: 'Cancelar'
  });


  if (isConfirmed) {
    // Si el usuario confirma, proceder con el envío al backend
    const payload = {
      ...data,
      id_solicitante: usuarioActual.value.id,
      id_area: usuarioActual.value.area_id,
      tipo_solicitud: props.solicitudAEditar?.tipo_solicitud || 'Apertura',
    };

    try {
      const response = await api[metodoApi](endpoint, payload);

      let successTitle = esModoEdicion ? '¡Solicitud Actualizada!' : '¡Solicitud Enviada!';
      let successHtml = response.data.message;

      if (!esModoEdicion && response.data.solicitud?.codigo_solicitud) {
        successHtml = `¡Solicitud registrada y enviada! Código: <strong>${response.data.solicitud.codigo_solicitud}</strong>`;
      }

      Swal.fire({
        icon: 'success',
        title: successTitle,
        html: successHtml,
        confirmButtonText: 'Aceptar'
      });

      if (esModoEdicion) {
        emit('solicitudActualizada', response.data.solicitud);
      } else {
        resetearFormulario(); // Solo reseteamos si estamos creando.
        emit('solicitudEnviada', response.data.solicitud);
      }

      console.log('Respuesta del servidor:', response.data);

    } catch (error) {
      console.error('Error al crear la solicitud:', error);
      let errorMessage = 'Error en la operación de solicitud. Por favor, inténtalo de nuevo.';
      if (error.response?.data) {
        if (error.response.data.errors) {
          errorMessage = '<ul style="text-align: left; list-style-position: inside;">';
          for (const key in error.response.data.errors) {
            errorMessage += `<li li > - ${error.response.data.errors[key].join(', ')}</li > `;
          }
          errorMessage += '</ul>';
        } else if (error.response.data.message) {
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

// Observa cualquier cambio en el total de gastos y actualiza automáticamente el monto solicitado.
watch(totalGastosProyectados, (newTotal) => {
  formData.value.monto_solicitado = newTotal;
}, { deep: true });

// Observa la prop para re-inicializar el formulario cuando cambia (para edición).
watch(() => props.solicitudAEditar, () => {
  inicializarFormulario();
}, { immediate: true, deep: true });
onMounted(() => {
  //obtenerUsuarioAutenticado();
  inicializarFormulario();
});
</script>

<template>
  <div class="p-6 bg-white rounded-lg shadow-md">
    <h2 class="text-3xl font-bold text-gray-800 mb-6">{{ tituloComponente }}</h2>

    <div v-if="cargando" class="text-center text-gray-500 py-8">
      Cargando datos del usuario...
    </div>

    <div v-else>
      <form @submit.prevent="manejarEnvio">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
          <div>
            <label for="solicitante" class="block text-sm font-medium text-gray-700 mb-1">Solicitante</label>
            <input type="text" id="solicitante" :value="usuarioActual?.name + ' ' + usuarioActual?.last_name"
              class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed" disabled />
          </div>
          <div>
            <label for="cargo" class="block text-sm font-medium text-gray-700 mb-1">Cargo</label>
            <input type="text" id="cargo" :value="usuarioActual?.cargo"
              class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed" disabled />
          </div>
          <div>
            <label for="area" class="block text-sm font-medium text-gray-700 mb-1">Área</label>
            <input type="text" id="area" :value="usuarioActual?.area?.name"
              class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed" disabled />
          </div>
          <div>
            <label for="fecha_solicitud" class="block text-sm font-medium text-gray-700 mb-1">Fecha de Solicitud</label>
            <input type="text" id="fecha_solicitud" :value="new Date().toLocaleDateString()"
              class="mt-1 block w-full p-3 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed" disabled />
          </div>
        </div>

        <div class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
          <h3 class="text-xl font-semibold text-gray-800 mb-4 flex justify-between items-center">
            Gastos Proyectados
            <button type="button" @click="agregarGastoProyectado"
              class="bg-verde-bap hover:bg-verde-bap-hover text-white font-semibold py-2 px-4 rounded-full transition-colors flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                  d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                  clip-rule="evenodd" />
              </svg>
              Añadir Gasto
            </button>
          </h3>

          <div v-if="formData.gastos_proyectados.length === 0" class="text-gray-500 text-center py-4">
            No hay gastos proyectados. Haz clic en "Añadir Gasto" para empezar.
          </div>

          <div v-for="(item, index) in formData.gastos_proyectados" :key="index"
            class="bg-white p-4 rounded-lg shadow-sm mb-4 border border-gray-100">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
              <div class="md:col-span-2">
                <label :for="'descripcion_gasto_' + index"
                  class="block text-sm font-medium text-gray-700 mb-1">Descripción del Tipo de Gasto <span
                    class="text-rojo-bap">*</span></label>
                <input type="text" :id="'descripcion_gasto_' + index" v-model="item.descripcion_gasto"
                  class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                  required />
              </div>
              <div class="flex items-center justify-between">
                <div>
                  <label :for="'monto_estimado_' + index" class="block text-sm font-medium text-gray-700 mb-1">Monto
                    Mensual Estimado (S/.) <span class="text-rojo-bap">*</span></label>
                  <input type="number" :id="'monto_estimado_' + index" v-model.number="item.monto_estimado" step="0.01"
                    min="0"
                    class="mt-1 block w-full p-2 border border-gray-300 rounded-md bg-white shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                    required />
                </div>
                <button type="button" @click="removerGastoProyectado(index)"
                  v-if="formData.gastos_proyectados.length > 1"
                  class="ml-4 p-2 bg-rojo-bap hover:bg-rojo-bap-hover text-white rounded-full transition-colors self-center"
                  title="Eliminar gasto">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                      d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm6 0a1 1 0 01-2 0v6a1 1 0 112 0V8z"
                      clip-rule="evenodd" />
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <div class="text-right mt-6 pt-4 border-t border-gray-200">
            <span class="text-lg font-semibold text-gray-800">Total Gastos Proyectados: {{
              currencyFormatter.format(totalGastosProyectados) }}</span>
          </div>
        </div>

        <div class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
          <h3 class="text-xl font-semibold text-gray-800 mb-4">Detalle de la Solicitud</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="mb-4 md:mb-0">
              <label for="motivo_detalle" class="block text-sm font-medium text-gray-700 mb-1">Motivo de la Solicitud
                <span class="text-rojo-bap">*</span></label>
              <textarea id="motivo_detalle" v-model="formData.motivo_detalle" rows="4"
                class="mt-1 block w-full p-3 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap resize-none"
                required></textarea>
            </div>
            <div>
              <div class="mb-4">
                <label for="monto_solicitado" class="block text-sm font-medium text-gray-700 mb-1">Monto Solicitado
                  (S/.) <span class="text-rojo-bap">*</span></label>
                <input type="text" id="monto_solicitado" :value="currencyFormatter.format(totalGastosProyectados)"
                  class="mt-1 block w-full p-3 border-gray-300 rounded-md bg-gray-100 cursor-not-allowed" disabled />
              </div>
              <div class="mb-4">
                <div v-if="mostrarPrioridad" class="mb-4">
                  <label for="prioridad" class="block text-sm font-medium text-gray-700 mb-1">Prioridad <span
                      class="text-rojo-bap">*</span></label>
                  <select id="prioridad" v-model="formData.prioridad"
                    class="mt-1 block w-full p-3 border-gray-300 rounded-md" required>
                    <option value="Urgente">Urgente</option>
                    <option value="Alta">Alta</option>
                    <option value="Media">Media</option>
                    <option value="Baja">Baja</option>
                  </select>
                </div>
                <!-- ¡NUEVO! Campo de comentario de descargo, solo para modo edición de observadas -->
                <div v-if="modoEdicion === 'observada'"
                  class="mb-8 p-4 md:p-6 border-l-4 border-yellow-400 bg-yellow-50">
                  <label for="comentario_descargo" class="block text-sm font-medium text-yellow-800 mb-1">Comentario
                    Adicional (Opcional)</label>
                  <p class="text-xs text-gray-600 mb-2">Añade un comentario para explicar los cambios realizados.</p>
                  <textarea id="comentario_descargo" v-model="formData.comentario_descargo" rows="3"
                    class="mt-1 block w-full p-2 border-gray-300 rounded-md"></textarea>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end space-x-4">
          <button v-if="solicitudAEditar" type="button" @click="$emit('cancelar')"
            class="px-6 py-3 bg-gray-300 hover:bg-gray-400 rounded-full font-bold">
            Cancelar
          </button>
          <button type="submit" :disabled="!hayCambios"
            class="bg-verde-bap text-white font-bold py-3 px-8 rounded-full transition-colors"
            :class="{ 'hover:bg-verde-bap-hover': hayCambios, 'opacity-50 cursor-not-allowed': !hayCambios }">
            {{ textoBotonEnvio }}
          </button>
        </div>
      </form>
    </div>

  </div>
</template>

<style>
.resize-none {
  resize: none;
}

.modal-backdrop-enter-active,
.modal-backdrop-leave-active {
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.modal-backdrop-enter-from,
.modal-backdrop-leave-to {
  opacity: 0;
  backdrop-filter: blur(0px);
}

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

/* Animación de entrada secuencial para las tarjetas (no usada directamente aquí, pero útil si se aplica a elementos dentro) */
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

/* Scroll personalizado mejorado */
.swal-gastos-container .scroll-modal::-webkit-scrollbar {
  width: 8px;
}

.swal-gastos-container .scroll-modal::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.05);
  border-radius: 10px;
}

.swal-gastos-container .scroll-modal::-webkit-scrollbar-thumb {
  background: linear-gradient(180deg, #76C49D, #5da887);
  border-radius: 10px;
  transition: all 0.3s ease;
}

.swal-gastos-container .scroll-modal::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(180deg, #5da887, #4a9470);
  box-shadow: 0 0 10px rgba(118, 196, 157, 0.5);
}
</style>
