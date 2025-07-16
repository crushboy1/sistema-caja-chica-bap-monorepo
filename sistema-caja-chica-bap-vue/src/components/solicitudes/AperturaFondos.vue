<script setup>
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import api from '@/plugins/axios';
import Swal from 'sweetalert2';
import { isEqual, cloneDeep } from 'lodash-es';

const props = defineProps({
  // Objeto con los datos del usuario autenticado.
  usuarioActual: {
    type: Object,
    required: true
  },
  // Array con la lista de proyectos disponibles.
  proyectos: {
    type: Array,
    required: true
  },
  // Array con el catálogo de gastos proyectados.
  gastosProyectadosCatalogo: {
    type: Array,
    required: true
  },
  areasCatalogo: {
    type: Array,
    required: true
  },
  // Prop opcional para el modo de edición.
  solicitudAEditar: {
    type: Object,
    default: null
  },
  // Define el contexto de la edición para llamar al endpoint correcto.
  modoEdicion: {
    type: String,
    default: 'crear' // 'crear', 'pendiente', 'observada'
  }
});

// Definir eventos que este componente puede emitir a su padre
const emit = defineEmits(['solicitudEnviada', 'solicitudActualizada', 'cancelar']);

// --- Variables reactivas para los datos del formulario (en español) ---

const cargando = ref(true);
const formData = ref({
  tipo_solicitud: 'Apertura',
  tipo_fondo_solicitado: 'Regular',
  id_proyecto: null,
  areas_participantes: [],
  motivo_detalle: '',
  monto_solicitado: 0,
  prioridad: 'Media',
  gastos_proyectados: [],
  comentario_descargo: ''
});

const originalData = ref(null);

// Computed properties

const puedeCrearSolicitudProyecto = computed(() => {
  return props.usuarioActual?.role?.name === 'jefe_area' &&
    props.usuarioActual?.area?.name?.toLowerCase() === 'proyectos';
});

const opcionesTipoFondo = computed(() => {
  const opciones = [
    { value: 'Regular', text: 'Fondo Regular' },
    { value: 'Excepcional', text: 'Fondo Excepcional' }
  ];

  // Si el usuario tiene permisos, se añade la opción "Proyecto" en la segunda posición.
  if (puedeCrearSolicitudProyecto.value) {
    opciones.splice(1, 0, { value: 'Proyecto', text: 'Fondo de Proyecto' });
  }

  return opciones;
});
const mostrarAlertaPermisos = () => {
  Swal.fire({
    icon: 'warning',
    title: 'Permisos Insuficientes',
    text: 'Solo los Jefes del área de Proyectos pueden crear solicitudes de este tipo.',
    confirmButtonText: 'Entendido',
    confirmButtonColor: '#38a169' // Un color verde similar al de BAP
  });
};
const onTipoFondoChange = () => {
  if (formData.value.tipo_fondo_solicitado === 'Proyecto' && !puedeCrearSolicitudProyecto.value) {
    mostrarAlertaPermisos();
    // Resetea a una opción válida por defecto
    formData.value.tipo_fondo_solicitado = 'Regular';
  }
};
const mostrarPrioridad = computed(() => {
  // En modo creación (sin solicitudAEditar), solo mostrar si NO es Apertura
  if (!props.solicitudAEditar) {
    return formData.value.tipo_solicitud !== 'Apertura';
  }
  // En modo edición, mostrar según el tipo de la solicitud existente
  return props.solicitudAEditar.tipo_solicitud !== 'Apertura';
});

// Nueva computed property para mostrar el selector de áreas participantes
const mostrarAreasParticipantes = computed(() => {
  return formData.value.tipo_fondo_solicitado === 'Proyecto' && formData.value.id_proyecto;
});

// Computed property para obtener las áreas disponibles para el proyecto seleccionado
const areasDisponiblesParaProyecto = computed(() => {
  if (!formData.value.id_proyecto || !props.proyectos.length) {
    return props.areasCatalogo || [];
  }

  // Aquí puedes filtrar las áreas según el proyecto si es necesario
  // Por ahora retornamos todas las áreas disponibles
  return props.areasCatalogo || [];
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
const inicializarFormulario = () => {
  cargando.value = true;
  try {
    if (props.solicitudAEditar) {
      // MODO EDICIÓN: El formulario se llena con los datos de la prop 'solicitudAEditar'.
      const solicitudData = JSON.parse(JSON.stringify(props.solicitudAEditar));
      formData.value = {
        ...solicitudData,
        tipo_fondo_solicitado: solicitudData.tipo_fondo_solicitado || 'Regular',
        id_proyecto: solicitudData.id_proyecto || null,
        areas_participantes: solicitudData.areas_participantes || [],
        // Se adapta al nuevo formato de la tabla pivote que viene del backend.
        gastos_proyectados: solicitudData.gastos_proyectados?.map(g => ({
          gasto_proyectado_id: g.id_gasto_proyectado,
          monto_estimado: g.pivot.monto_estimado
        })) || [],
        comentario_descargo: ''
      };
      originalData.value = cloneDeep(formData.value);
    } else {
      // MODO CREACIÓN: Simplemente se resetea el formulario. El usuario ya viene en las props.
      resetearFormulario();
    }
  } catch (error) {
    console.error('Error al inicializar el formulario de apertura:', error);
    Swal.fire('Error', 'Ocurrió un error al preparar el formulario.', 'error');
  } finally {
    cargando.value = false;
  }
};

const resetearFormulario = () => {
  formData.value = {
    tipo_solicitud: 'Apertura',
    tipo_fondo_solicitado: 'Regular',
    id_proyecto: null,
    areas_participantes: [],
    motivo_detalle: '',
    monto_solicitado: 0,
    prioridad: 'Media',
    gastos_proyectados: [{ gasto_proyectado_id: null, monto_estimado: null }],
    comentario_descargo: ''
  };
};

// Función para manejar la selección/deselección de áreas participantes
const toggleAreaParticipante = (areaId) => {
  const index = formData.value.areas_participantes.indexOf(areaId);
  if (index > -1) {
    // Si ya está seleccionada, la removemos
    formData.value.areas_participantes.splice(index, 1);
  } else {
    // Si no está seleccionada, la agregamos
    formData.value.areas_participantes.push(areaId);
  }
};

// Función para verificar si un área está seleccionada
const isAreaSeleccionada = (areaId) => {
  return formData.value.areas_participantes.includes(areaId);
};
/**
 * Propiedad computada que devuelve un array de los IDs de los gastos ya seleccionados.
 * Esto nos ayudará a determinar qué opciones deshabilitar.
 */
const idsGastosSeleccionados = computed(() => {
  // Mapeamos el array de gastos para obtener solo los IDs que no son nulos.
  return formData.value.gastos_proyectados
    .map(g => g.gasto_proyectado_id)
    .filter(id => id !== null);
});

/**
 * Función que determina si una opción de gasto proyectado debe estar deshabilitada en un dropdown.
 * @param {number} gastoId - El ID del gasto del catálogo que se está evaluando.
 * @param {number} gastoIdActualFila - El ID del gasto que ya está seleccionado en la fila actual.
 * @returns {boolean} - True si la opción debe estar deshabilitada.
 */
const esOpcionDeshabilitada = (gastoId, gastoIdActualFila) => {
  // Una opción está deshabilitada si:
  // 1. Ya está en la lista de IDs seleccionados.
  // 2. Y NO es el gasto que ya está seleccionado en esta misma fila (para permitir que se vea la selección actual).
  return idsGastosSeleccionados.value.includes(gastoId) && gastoId !== gastoIdActualFila;
};
// Función para añadir un nuevo ítem de gasto proyectado (simplificado)
const agregarGastoProyectado = () => {
  // Antes de añadir, verificamos si hay opciones disponibles.
  if (idsGastosSeleccionados.value.length >= props.gastosProyectadosCatalogo.length) {
    Swal.fire('Límite alcanzado', 'Ya has seleccionado todos los tipos de gastos proyectados disponibles.', 'info');
    return;
  }
  formData.value.gastos_proyectados.push({ gasto_proyectado_id: null, monto_estimado: null });
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
  if (data.gastos_proyectados.some(g => !g.gasto_proyectado_id || g.monto_estimado === null || g.monto_estimado <= 0)) {
    Swal.fire('Error de Validación', 'Todos los gastos proyectados deben tener un tipo seleccionado y un monto estimado válido (> 0).', 'error');
    return;
  }
  if (data.tipo_fondo_solicitado === 'Proyecto' && !data.id_proyecto) {
    Swal.fire('Error de Validación', 'Debe seleccionar un proyecto cuando el tipo de fondo es "Proyecto".', 'error');
    return;
  }
  // Nueva validación para áreas participantes
  if (data.tipo_fondo_solicitado === 'Proyecto' && (!data.areas_participantes || data.areas_participantes.length === 0)) {
    Swal.fire('Error de Validación', 'Debe seleccionar al menos un área participante para el proyecto.', 'error');
    return;
  }
  const tipoRealDeSolicitud = props.solicitudAEditar?.tipo_solicitud || data.tipo_solicitud;

  if (tipoRealDeSolicitud !== 'Apertura') {
    if (!data.prioridad) {
      Swal.fire({
        icon: 'error',
        title: 'Error de Validación',
        text: 'Por favor, selecciona una prioridad de solicitud.'
      });
      return;
    }
  }
  if (!data.gastos_proyectados || data.gastos_proyectados.length === 0) {
    Swal.fire({
      icon: 'error',
      title: 'Error de Validación',
      text: 'Debes añadir al menos un gasto proyectado.'
    });
    return;
  }
  if (parseFloat(data.monto_solicitado) !== parseFloat(totalGastosProyectados.value)) {
    Swal.fire('Error de Consistencia', 'El monto solicitado no coincide con el total de gastos proyectados. Esto no debería ocurrir.', 'error');
    return;
  }

  // Lógica para determinar el endpoint y el método HTTP
  const esModoEdicion = !!props.solicitudAEditar;
  let endpoint = '/v1/solicitudes';
  let metodoApi = 'post';

  if (esModoEdicion) {
    metodoApi = 'put';
    if (props.modoEdicion === 'pendiente') {
      endpoint = `/v1/solicitudes/${props.solicitudAEditar.id}/editar-pendiente`;
    } else if (props.modoEdicion === 'observada') {
      endpoint = `/v1/solicitudes/${props.solicitudAEditar.id}/editar-observada`;
    }
  }

  // --- Construir el contenido HTML para el modal de resumen ---
  const gastosHtml = `
        <div class="text-sm scroll-modal" style="max-height: 150px; overflow-y: auto; border: 1px solid #eee; padding: 10px; border-radius: 8px; margin-top: 10px;">
            <ul>
            ${data.gastos_proyectados.map(g => {
    const desc = props.gastosProyectadosCatalogo.find(cat => cat.id_gasto_proyectado === g.gasto_proyectado_id)?.descripcion || 'N/A';
    return `<li><strong>${desc}:</strong> S/. ${parseFloat(g.monto_estimado || 0).toFixed(2)}</li>`;
  }).join('')}
            </ul>
        </div>
        <p class="mt-2 text-right"><strong>Total: S/. ${totalGastosProyectados.value.toFixed(2)}</strong></p>
      `;
  let tipoFondoHtml = `<p><strong>Tipo de Fondo:</strong> ${data.tipo_fondo_solicitado}</p>`;

  // Agregar información del proyecto si es tipo Proyecto
  let proyectoHtml = '';
  if (data.tipo_fondo_solicitado === 'Proyecto' && data.id_proyecto) {
    const proyectoSeleccionado = props.proyectos.find(p => p.id_proyecto === data.id_proyecto);
    if (proyectoSeleccionado) {
      proyectoHtml = `<p><strong>Proyecto:</strong> ${proyectoSeleccionado.nombre}</p>`;
    }
  }
  // Agregar información de áreas participantes al resumen si es tipo Proyecto
  let areasHtml = '';
  if (data.tipo_fondo_solicitado === 'Proyecto' && data.areas_participantes.length > 0) {
    const nombresAreas = data.areas_participantes.map(areaId => {
      const area = props.areasCatalogo.find(a => a.id === areaId || a.id_area === areaId);
      return area ? area.name || area.id : 'Área desconocida';
    });
    areasHtml = `<p><strong>Áreas Participantes:</strong> ${nombresAreas.join(', ')}</p>`;
  }

  const resumenHtml = `<div style="text-align: left; padding: 0 1rem;">
    <p><strong>Motivo:</strong> ${data.motivo_detalle}</p>
    <p><strong>Monto Solicitado:</strong> ${currencyFormatter.format(data.monto_solicitado || 0)}</p>
    ${tipoFondoHtml}
    ${proyectoHtml}
    ${areasHtml}
    <hr style="margin: 1rem 0;" />
    <strong>Gastos Proyectados:</strong>${gastosHtml}
  </div>`;

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
    // 1. Creamos una copia del objeto 'data' para no modificar el estado original del formulario.
    const payload = { ...data };

    // 2. Verificamos el tipo de fondo y manejamos los campos específicos
    // Esto asegura que no se envíe ningún valor para este campo.
    if (tipoRealDeSolicitud === 'Apertura') {
      delete payload.prioridad;
    }
    if (payload.tipo_fondo_solicitado !== 'Proyecto') {
      // Si NO es 'Proyecto', eliminamos ambos campos del payload
      delete payload.id_proyecto;
      delete payload.areas_participantes;
    } else {
      // Si ES 'Proyecto', verificamos que tenga áreas participantes
      if (!payload.areas_participantes || payload.areas_participantes.length === 0) {
        Swal.fire('Error de Validación', 'Debe seleccionar al menos un área participante para el proyecto.', 'error');
        return;
      }
    }

    // 3. Añadimos el resto de datos necesarios al payload.
    payload.id_solicitante = props.usuarioActual.id;
    payload.id_area = props.usuarioActual.area_id;
    payload.tipo_solicitud = props.solicitudAEditar?.tipo_solicitud || 'Apertura';

    try {
      // Usamos el 'payload' limpio para la solicitud
      const response = await api[metodoApi](endpoint, payload);

      let successTitle = esModoEdicion ? '¡Solicitud Actualizada!' : '¡Acción Completada!';
      let successHtml = response.data.message;

      if (!successHtml && !esModoEdicion && response.data.solicitud?.codigo_solicitud) {
        successHtml = `¡Solicitud procesada! Código: <strong>${response.data.solicitud.codigo_solicitud}</strong>`;
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
        resetearFormulario();
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
            errorMessage += `<li> - ${error.response.data.errors[key].join(', ')}</li>`;
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

watch(() => formData.value.tipo_fondo_solicitado, (newValue, oldValue) => {
  // Si el nuevo valor es 'Proyecto' y el usuario no tiene permisos, se revierte.
  // Esto actúa como una doble seguridad si el cambio no proviene de una interacción directa del usuario.
  if (newValue === 'Proyecto' && !puedeCrearSolicitudProyecto.value) {
    // Usamos nextTick para asegurar que la alerta se muestre después de que el DOM intente actualizarse.
    nextTick(() => {
      mostrarAlertaPermisos();
      formData.value.tipo_fondo_solicitado = 'Regular';
    });
  }

  // Si el tipo de fondo deja de ser 'Proyecto', se limpian los campos relacionados.
  if (newValue !== 'Proyecto') {
    formData.value.id_proyecto = null;
    formData.value.areas_participantes = [];
  }
});

// Cuando cambia el proyecto seleccionado, reseteamos las áreas participantes
watch(() => formData.value.id_proyecto, (newProyecto) => {
  if (newProyecto) {
    // Resetear áreas cuando cambia el proyecto
    formData.value.areas_participantes = [];
  }
});

// Observa la prop para re-inicializar el formulario cuando cambia (para edición).
watch(() => props.solicitudAEditar, () => {
  inicializarFormulario();
}, { immediate: true, deep: true });

onMounted(() => {
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
          <h3 class="text-xl font-semibold text-gray-800 mb-4">Tipo de Fondo</h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="tipo_fondo" class="block text-sm font-medium text-gray-700 mb-1">
                Seleccione el Tipo de Fondo <span class="text-rojo-bap">*</span>
              </label>
              <select id="tipo_fondo" v-model="formData.tipo_fondo_solicitado" @change="onTipoFondoChange"
                class="mt-1 block w-full p-3 border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                required>
                <option v-for="opcion in opcionesTipoFondo" :key="opcion.value" :value="opcion.value">
                  {{ opcion.text }}
                </option>
              </select>

              <!-- Alerta informativa si no tiene permisos -->
              <div v-if="!puedeCrearSolicitudProyecto"
                class="mt-2 p-3 bg-verde-bap-extralight border-b-verde-bap-extralight rounded-r-md">
                <div class="flex">
                  <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-verde-bap-dark" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                        clip-rule="evenodd"></path>
                    </svg>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm text-verde-bap-dark">
                      <strong>Información:</strong> El tipo "Fondo de Proyecto" solo está disponible para usuarios con
                      rol de Jefe de Área del área de Proyectos.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Este campo solo aparece si el tipo de fondo es 'Proyecto' -->
            <div v-if="formData.tipo_fondo_solicitado === 'Proyecto'">
              <label for="proyecto" class="block text-sm font-medium text-gray-700 mb-1">
                Proyecto Asociado <span class="text-rojo-bap">*</span>
              </label>
              <select id="proyecto" v-model="formData.id_proyecto"
                class="mt-1 block w-full p-3 border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                :required="formData.tipo_fondo_solicitado === 'Proyecto'">
                <option :value="null" disabled>Seleccione un proyecto</option>
                <option v-for="proyecto in proyectos" :key="proyecto.id_proyecto" :value="proyecto.id_proyecto">
                  {{ proyecto.nombre }}
                </option>
              </select>
            </div>

            <div v-if="mostrarAreasParticipantes" class="md:col-span-2">
              <label for="areas_participantes" class="block text-sm font-medium text-gray-700 mb-1">
                Áreas Participantes <span class="text-rojo-bap">*</span>
              </label>


              <div class="mt-2 max-h-48 overflow-y-auto border border-gray-300 rounded-md p-3 bg-gray-50">
                <div class="space-y-2">
                  <div v-for="area in areasDisponiblesParaProyecto" :key="area.id || area.id_area"
                    class="flex items-center">
                    <input :id="`area_${area.id || area.id_area}`" type="checkbox" :value="area.id || area.id_area"
                      :checked="isAreaSeleccionada(area.id || area.id_area)"
                      @change="toggleAreaParticipante(area.id || area.id_area)"
                      class="h-4 w-4 text-verde-bap focus:ring-verde-bap border-gray-300 rounded" />
                    <label :for="`area_${area.id || area.id_area}`" class="ml-2 text-sm text-gray-700 cursor-pointer">
                      {{ area.name || area.nombre || area.nombre_area }}
                    </label>
                  </div>
                </div>

                <!-- Contador de áreas seleccionadas -->
                <div v-if="formData.areas_participantes.length > 0" class="mt-3 pt-2 border-t border-gray-200">
                  <span class="text-xs text-verde-bap font-medium">
                    {{ formData.areas_participantes.length }} área(s) seleccionada(s)
                  </span>
                </div>
              </div>
              <div v-if="formData.areas_participantes.length > 0" class="mt-2">
                <div class="flex flex-wrap gap-2">
                  <span v-for="areaId in formData.areas_participantes" :key="areaId"
                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-verde-bap text-white">
                    {{areasDisponiblesParaProyecto.find(a => (a.id || a.id_area) === areaId)?.name ||
                      areasDisponiblesParaProyecto.find(a => (a.id || a.id_area) === areaId)?.nombre ||
                      areasDisponiblesParaProyecto.find(a => (a.id || a.id_area) === areaId)?.nombre_area || 'Área desconocida' }}
                    <button type="button" @click="toggleAreaParticipante(areaId)"
                      class="ml-1.5 inline-flex items-center justify-center w-4 h-4 rounded-full hover:bg-verde-bap-dark focus:outline-none">
                      <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                          d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                          clip-rule="evenodd"></path>
                      </svg>
                    </button>
                  </span>
                </div>
              </div>
            </div>

          </div>
        </div>
        <div class="mb-8 p-6 border border-gray-200 rounded-lg bg-gray-50">
          <h3 class="text-xl font-semibold text-gray-800 mb-4 flex justify-between items-center">
            Gastos Proyectados

          </h3>

          <div v-if="formData.gastos_proyectados.length === 0" class="text-gray-500 text-center py-4">
            No hay gastos proyectados. Haz clic en "Añadir Gasto" para empezar.
          </div>

          <div v-for="(item, index) in formData.gastos_proyectados" :key="index"
            class="bg-white p-4 rounded-lg shadow-sm mb-4 border border-gray-100">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
              <div class="md:col-span-2">
                <label :for="'gasto_proyectado_' + index" class="block text-sm font-medium text-gray-700 mb-1">
                  Gasto Proyectado <span class="text-rojo-bap">*</span>
                </label>
                <select :id="'gasto_proyectado_' + index" v-model="item.gasto_proyectado_id"
                  class="mt-1 block w-full p-2 border border-gray-300 rounded-md shadow-sm focus:border-verde-bap focus:ring-verde-bap"
                  required>
                  <option :value="null" disabled>Seleccione un tipo de gasto</option>
                  <option v-for="gastoCatalogo in gastosProyectadosCatalogo" :key="gastoCatalogo.id_gasto_proyectado"
                    :value="gastoCatalogo.id_gasto_proyectado"
                    :disabled="esOpcionDeshabilitada(gastoCatalogo.id_gasto_proyectado, item.gasto_proyectado_id)">
                    {{ gastoCatalogo.descripcion }}
                  </option>
                </select>
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
            <button type="button" @click="agregarGastoProyectado"
              class="bg-verde-bap hover:bg-verde-bap-hover text-white font-semibold py-2 px-4 rounded-full transition-colors flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                  d="M10 5a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0v-3H6a1 1 0 110-2h3V6a1 1 0 011-1z"
                  clip-rule="evenodd" />
              </svg>
              Añadir Gasto
            </button>
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
