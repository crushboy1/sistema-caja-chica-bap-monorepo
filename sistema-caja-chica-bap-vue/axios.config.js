// axios.config.js
import axios from 'axios'

// Configuración base para la API
const apiClient = axios.create({
  baseURL: 'http://localhost:8080/api', // URL de tu backend Laravel
  withCredentials: true, // Importante para CORS con cookies/sesiones
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest'
  }
})

// Interceptor para manejo de errores CORS
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.code === 'ERR_NETWORK') {
      console.error('Error de red - posible problema CORS:', error)
    }
    return Promise.reject(error)
  }
)

export default apiClient