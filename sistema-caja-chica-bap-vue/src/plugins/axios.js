// plugins/axios.js
import axios from 'axios';
import router from '@/router';

// Configuración base de Axios
const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

// Instancia dedicada para obtener el CSRF cookie desde la raíz del backend
const csrfApi = axios.create({
  baseURL: '/',
  withCredentials: true,
});

// Variable para controlar si ya se obtuvo el CSRF token
let csrfInitialized = false;

// Función para inicializar CSRF token
const initializeCsrf = async () => {
  if (!csrfInitialized) {
    try {
      await csrfApi.get('/sanctum/csrf-cookie');
      csrfInitialized = true;
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
    if (['post', 'put', 'patch', 'delete'].includes(config.method.toLowerCase()) &&
        !config.url.includes('sanctum/csrf-cookie')) {
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
      csrfInitialized = false; // Reset CSRF flag
      router.push('/login');
    } else if (error.response?.status === 419) {
      csrfInitialized = false;
      console.warn('CSRF token expirado, reintentando...');
    }

    return Promise.reject(error);
  }
);

export default api;