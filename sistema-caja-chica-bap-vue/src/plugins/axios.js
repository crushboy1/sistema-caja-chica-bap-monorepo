// plugins/axios.js
import axios from 'axios';
import router from '@/router';

// Configuración base de Axios
const api = axios.create({
  // ¡CORREGIDO! Usar una ruta relativa para que el proxy de Vite pueda interceptarla
  baseURL: '/api', // El proxy de Vite redirigirá esto a http://app:80/api
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Variable para controlar si ya se obtuvo el CSRF token
let csrfInitialized = false;

// Función para inicializar CSRF token
const initializeCsrf = async () => {
  if (!csrfInitialized) {
    try {
      // ¡CORREGIDO! Usar la instancia 'api' y una ruta relativa para el CSRF cookie
      await api.get('/sanctum/csrf-cookie'); // Esto se resolverá a /api/sanctum/csrf-cookie
      csrfInitialized = true;
      console.log('CSRF token obtenido exitosamente');
    } catch (error) {
      console.error('Error al obtener CSRF token:', error);
      if (error.response?.status === 404) {
        console.error('La ruta /sanctum/csrf-cookie no existe en el backend. Verifica la configuración de Sanctum.');
      }
      throw error;
    }
  }
};

// Interceptor de peticiones - obtener CSRF token para métodos que lo necesitan
api.interceptors.request.use(
  async (config) => {
    // Solo obtener CSRF token para métodos que lo necesitan y si no es la ruta de CSRF en sí misma
    if (['post', 'put', 'patch', 'delete'].includes(config.method.toLowerCase()) &&
        !config.url.includes('sanctum/csrf-cookie')) { // Asegúrate de que no intente obtener CSRF para la propia llamada CSRF
      await initializeCsrf();
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Interceptor para manejar errores globalmente
api.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error('Error en petición:', error);

    if (error.response?.status === 401) {
      // Token expirado o no autorizado
      csrfInitialized = false; // Reset CSRF flag
      router.push('/login');
    } else if (error.response?.status === 419) {
      // CSRF token mismatch - reinicializar
      csrfInitialized = false;
      console.warn('CSRF token expirado, reintentando...');
      // Puedes añadir aquí una lógica para recargar la página o volver a intentar automáticamente
      // window.location.reload(); // Esto podría ser una opción si el 419 ocurre con frecuencia
    }

    return Promise.reject(error);
  }
);

export default api;