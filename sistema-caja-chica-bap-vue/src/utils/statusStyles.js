/**
 * @module statusStyles
 * Este archivo centraliza la configuración de estilos para diferentes componentes
 * basados en el estado de la aplicación (ej. 'Aprobada', 'Pendiente').
 * Utiliza los nombres de colores definidos en `tailwind.config.js`.
 */
// CONFIGURACIÓN MAESTRA DE ESTADOS (ÚNICA FUENTE DE VERDAD)
// Asocia una palabra clave de estado con su tema de color y su ícono.
// Esta es la base para todos los demás estilos.
const masterStatusConfig = new Map([
    // --- Estados de Solicitudes y Gastos ---
    ['pendiente de validación dj', { theme: 'validacionDj', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' }],
    ['pendiente de aprobación', { theme: 'alerta', icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' }],
    ['descargo enviado', { theme: 'alerta', icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z' }],
    ['pendiente de validación contable', { theme: 'info', icon: 'M12 4.5v15m7.5-7.5h-15' }],
    ['creada', { theme: 'info', icon: 'M12 4.5v15m7.5-7.5h-15' }],
    ['pendiente aprobación', { theme: 'alerta', icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z' }],
    ['observada', { theme: 'advertencia', icon: 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z' }],
    ['observado', { theme: 'advertencia', icon: 'M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z M15 12a3 3 0 11-6 0 3 3 0 016 0z' }],
    ['aprobada', { theme: 'exito', icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }],
    ['contabilizado', { theme: 'exito', icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }],
    ['rechazada', { theme: 'error', icon: 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }],
    ['repuesto', { theme: 'neutro', icon: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z' }],
    ['activo', { theme: 'exito', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' }],
    ['inactivo', { theme: 'neutro', icon: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636' }],
    //Estados del Módulo de Auditoría ---
    ['creado', { theme: 'exito', icon: 'M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z' }],
    ['actualizado', { theme: 'modificacion', icon: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10' }],
    ['eliminado', { theme: 'error', icon: 'M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0' }],
    ['período cerrado', { theme: 'cerrado', icon: 'M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z' }],
    ['período reabierto', { theme: 'reabierto', icon: 'M13.5 10.5V6.75a4.5 4.5 0 119 0v3.75M3.75 18.75h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H3.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z' }],
    ['excepcion otorgada', { theme: 'autorizacion', icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }],
    ['excepcion revocada', { theme: 'revocacion', icon: 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z' }],
    ['cerrado', { theme: 'neutro', icon: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z' }],
]);
const defaultConfig = {
  theme: 'default',
  icon: 'M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z',
}
export function getMasterConfig(estado) {
  if (!estado) return defaultConfig
  const estadoNormalizado = estado.toLowerCase()
  for (const [keyword, config] of masterStatusConfig.entries()) {
    if (estadoNormalizado.includes(keyword)) {
      return config
    }
  }
  return defaultConfig
}

// DEFINICIÓN DE ESTILOS POR COMPONENTE
const badgeThemes = {
  validacionDj: 'bg-purple-100 text-purple-800 border-purple-300',
  alerta: 'bg-estado-alerta-bg text-estado-alerta-text border-yellow-300',
  info: 'bg-estado-info-bg text-estado-info-text border-blue-300',
  advertencia: 'bg-estado-advertencia-bg text-estado-advertencia-text border-orange-300',
  exito: 'bg-estado-exito-bg text-estado-exito-text border-green-300',
  error: 'bg-estado-error-bg text-estado-error-text border-red-300',
  neutro: 'bg-estado-neutro-bg text-estado-neutro-text border-gray-300',
  cyan: 'bg-cyan-100 text-cyan-800 border-cyan-300',
  teal: 'bg-teal-100 text-teal-800 border-teal-300',
  indigo: 'bg-indigo-100 text-indigo-800 border-indigo-300',
  pink: 'bg-pink-100 text-pink-800 border-pink-300',
  default: 'bg-gray-100 text-gray-500 border-gray-200',
  modificacion: 'bg-amber-100 text-amber-800 border-amber-300',
  cerrado: 'bg-red-100 text-red-800 border-red-300',
  reabierto: 'bg-lime-100 text-lime-800 border-lime-300',
  autorizacion: 'bg-blue-100 text-blue-800 border-blue-300',
  revocacion: 'bg-purple-100 text-purple-800 border-purple-300',
}
const changeTypeBadgeThemes = {
    'Agregado': 'bg-green-100 text-green-800',
    'Eliminado': 'bg-red-100 text-red-800',
    'Activación': 'bg-blue-100 text-blue-800',
    'Numérico': 'bg-purple-100 text-purple-800',
    'Modificado': 'bg-yellow-100 text-yellow-800',
    'default': 'bg-gray-100 text-gray-800',
};
const solicitudHistorialCardThemes = {
  validacionDj: {
    card: 'bg-purple-50 text-purple-700 border-purple-300/20',
    revisadoPor: 'text-purple-700 bg-purple-100/30',
    userIcon: 'text-purple-600 bg-purple-100/20',
  },
  alerta: {
    card: 'bg-estado-alerta-bg text-estado-alerta-text border-yellow-300/20',
    revisadoPor: 'text-estado-alerta-text bg-estado-alerta-bg/30',
    userIcon: 'text-estado-alerta-text bg-estado-alerta-bg/20',
  },
  info: {
    card: 'bg-estado-info-bg text-estado-info-text border-blue-300/20',
    revisadoPor: 'text-estado-info-text bg-estado-info-bg/30',
    userIcon: 'text-estado-info-text bg-estado-info-bg/20',
  },
  advertencia: {
    card: 'bg-estado-advertencia-bg text-estado-advertencia-text border-orange-300/20',
    revisadoPor: 'text-estado-advertencia-text bg-estado-advertencia-bg/30',
    userIcon: 'text-estado-advertencia-text bg-estado-advertencia-bg/20',
  },
  exito: {
    card: 'bg-estado-exito-bg text-estado-exito-text border-green-300/20',
    revisadoPor: 'text-estado-exito-text bg-estado-exito-bg/30',
    userIcon: 'text-estado-exito-text bg-estado-exito-bg/20',
  },
  error: {
    card: 'bg-estado-error-bg text-estado-error-text border-red-300/20',
    revisadoPor: 'text-estado-error-text bg-estado-error-bg/30',
    userIcon: 'text-estado-error-text bg-estado-error-bg/20',
  },
  neutro: {
    card: 'bg-estado-neutro-bg text-estado-neutro-text border-gray-300/20',
    revisadoPor: 'text-estado-neutro-text bg-estado-neutro-bg/30',
    userIcon: 'text-estado-neutro-text bg-estado-neutro-bg/20',
  },
  default: {
    card: 'bg-gray-100 text-gray-700 border-gray-300/20',
    revisadoPor: 'text-gray-600 bg-gray-100/60',
    userIcon: 'text-gray-500 bg-gray-100',
  },
}
// Define solo la clase de color de texto para cada tema.
const textColorThemes = {
  validacionDj: 'text-purple-800',
  alerta: 'text-estado-alerta-text',
  info: 'text-estado-info-text',
  advertencia: 'text-estado-advertencia-text',
  exito: 'text-estado-exito-text',
  error: 'text-estado-error-text',
  neutro: 'text-estado-neutro-text',
  cyan: 'text-cyan-800',
  teal: 'text-teal-800',
  indigo: 'text-indigo-800',
  pink: 'text-pink-800',
  default: 'text-gray-600',
}
//Define los estilos para los botones de acción principales.
const actionButtonThemes = {
  exito: 'bg-verde-bap hover:bg-verde-bap-dark text-white',
  error: 'bg-rojo-bap hover:bg-rojo-bap-dark text-white',
  advertencia: 'bg-orange-500 hover:bg-orange-600 text-white',
  info: 'bg-blue-500 hover:bg-blue-600 text-white',
  default: 'bg-gray-500 hover:bg-gray-600 text-white',
}
// FUNCIONES EXPORTABLES PARA USAR EN LOS COMPONENTES
/**
 * Para: Badges en Seguimiento de Solicitudes
 * @param {string} estado
 * @returns {string}
 */
export function getClassesForBadge(estado) {
  const baseClasses =
    'inline-block w-48 text-center py-1 px-2 rounded-full text-xs font-semibold border'
  const config = getMasterConfig(estado)
  const themeClasses = badgeThemes[config.theme] || badgeThemes.default
  return `${baseClasses} ${themeClasses}`
}

/**
 * [ETIQUETA: AUDITORIA_BADGE]
 * Para: Badges en la tabla de Auditoría de Gastos.
 * Devuelve las clases de Tailwind para una insignia de estado. Este estilo no tiene
 * un ancho fijo para adaptarse mejor al contenido y usa un padding diferente.
 * @param {string} estado - El texto del estado.
 * @returns {string} Una cadena de clases de Tailwind.
 */
export function getClassesForAuditoriaBadge(estado) {
    const baseClasses = 'py-1 px-3 rounded-full text-xs font-semibold inline-block border';
    const config = getMasterConfig(estado);
    const themeClasses = badgeThemes[config.theme] || badgeThemes.default;
    return `${baseClasses} ${themeClasses}`;
}

/**
 * Para: Tarjetas del Modal "Historial de Solicitudes"
 * @param {string} estado
 * @returns {object}
 */
export function getConfigForSolicitudHistorialCard(estado) {
  const config = getMasterConfig(estado)
  const themeStyles =
    solicitudHistorialCardThemes[config.theme] || solicitudHistorialCardThemes.default

  return {
    icon: config.icon,
    ...themeStyles,
  }
}

/**
 * Para: Badges en la tabla de detalles de auditoría (LogDetailsTable).
 * @param {string} tipo - El tipo de cambio ('Agregado', 'Eliminado', etc.).
 * @returns {string} Una cadena de clases de Tailwind.
 */
export function getClassesForChangeTypeBadge(tipo) {
    const baseClasses = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium';
    const themeClasses = changeTypeBadgeThemes[tipo] || changeTypeBadgeThemes.default;
    return `${baseClasses} ${themeClasses}`;
}
/**
 * [ TEXT_COLOR]
 * Para: Cualquier elemento que solo necesite el color del texto de un estado.
 * Devuelve solo la clase de color de texto de Tailwind para un estado, eliminando
 * la necesidad de usar `.replace()` en los componentes.
 * @param {string} estado - El texto del estado.
 * @returns {string} Una cadena con la clase de color de texto (ej. 'text-green-800').
 */
export function getTextClassForState(estado) {
  const config = getMasterConfig(estado)
  return textColorThemes[config.theme] || textColorThemes.default
}
/**
 * [ETIQUETA: ACTION_BUTTON]
 * Para: Botones de acción principales (Aprobar, Rechazar, Observar).
 * Devuelve un conjunto completo de clases para un botón, incluyendo base y tema.
 * @param {string} theme - El tema del botón ('exito', 'error', 'advertencia').
 * @returns {string} Una cadena de clases de Tailwind para el botón.
 */
export function getClassesForActionButton(theme) {
  const baseClasses =
    'px-4 py-2 rounded-md transition-colors flex items-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed'
  const themeClasses = actionButtonThemes[theme] || actionButtonThemes.default
  return `${baseClasses} ${themeClasses}`
}
