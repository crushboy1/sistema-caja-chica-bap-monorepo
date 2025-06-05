<template>
  <div class="p-6">
    <h2 class="text-2xl font-bold mb-4">Test CORS</h2>
    
    <button 
      @click="testCors" 
      :disabled="loading"
      class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
    >
      {{ loading ? 'Probando...' : 'Probar CORS' }}
    </button>
    
    <div v-if="result" class="mt-4 p-4 border rounded">
      <h3 class="font-bold">Resultado:</h3>
      <pre>{{ JSON.stringify(result, null, 2) }}</pre>
    </div>
    
    <div v-if="error" class="mt-4 p-4 border rounded bg-red-100">
      <h3 class="font-bold text-red-700">Error:</h3>
      <pre>{{ error }}</pre>
    </div>
  </div>
</template>

<script>
import apiClient from '../axios.config.js'

export default {
  name: 'TestCors',
  data() {
    return {
      loading: false,
      result: null,
      error: null
    }
  },
  methods: {
    async testCors() {
      this.loading = true
      this.result = null
      this.error = null
      
      try {
        // Primero obtenemos el CSRF token si es necesario
        await apiClient.get('/sanctum/csrf-cookie')
        
        // Luego hacemos la petición de prueba
        const response = await apiClient.get('/test-cors')
        this.result = response.data
        
        console.log('CORS funcionando correctamente:', response.data)
        
      } catch (err) {
        this.error = err.message
        console.error('Error CORS:', err)
        
        if (err.code === 'ERR_NETWORK') {
          this.error = 'Error de red - Verifica que el backend esté corriendo en http://localhost:8080'
        }
      } finally {
        this.loading = false
      }
    }
  }
}
</script>