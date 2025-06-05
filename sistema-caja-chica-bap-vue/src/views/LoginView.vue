<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/plugins/axios'; // Solo usa esta instancia

import AuthCard from '@/components/layout/AuthCard.vue';
import AuthFooter from '@/components/layout/AuthFooter.vue';
import BaseInput from '@/components/forms/BaseInput.vue';
import PasswordInput from '@/components/forms/PasswordInput.vue';
import SubmitButton from '@/components/forms/SubmitButton.vue';

const email = ref('');
const password = ref('');
const errorMessage = ref('');
const isLoading = ref(false);

const router = useRouter();

const handleLogin = async () => {
  errorMessage.value = '';
  isLoading.value = true;

  if (!email.value || !password.value) {
    errorMessage.value = 'Por favor completa ambos campos (correo y contraseña).';
    isLoading.value = false;
    return;
  }
  
  try {
    // ¡YA NO ES NECESARIO HACER api.get('/sanctum/csrf-cookie') AQUÍ!
    // El interceptor en plugins/axios.js se encargará automáticamente.

    console.log('Intentando login...');
    const response = await api.post('/auth/login', {
      email: email.value,
      password: password.value,
    });

    console.log('Login exitoso:', response.data);

    // Redirigir al dashboard
    router.push('/dashboard');

  } catch (error) {
    console.error('Error de login:', error);

    if (error.response) {
      const status = error.response.status;
      
      if (status === 422) {
        // Errores de validación
        const errors = error.response.data.errors;
        if (errors) {
          const validationErrors = [];
          for (const key in errors) {
            validationErrors.push(errors[key].join(', '));
          }
          errorMessage.value = validationErrors.join('\n');
        } else {
          errorMessage.value = 'Datos de entrada inválidos.';
        }
      } else if (status === 401 || status === 403) {
        errorMessage.value = 'Credenciales inválidas. Por favor, verifica tu correo y contraseña.';
      } else if (status === 419) {
        errorMessage.value = 'Sesión expirada. Recargando página...';
        // Recargar la página para obtener un nuevo token CSRF
        setTimeout(() => window.location.reload(), 2000);
      } else {
        errorMessage.value = error.response.data.message || 'Error del servidor. Inténtalo de nuevo.';
      }
    } else if (error.request) {
      errorMessage.value = 'No se pudo conectar con el servidor. Verifica que el backend esté funcionando.';
    } else {
      errorMessage.value = 'Ocurrió un error inesperado. Por favor, inténtalo de nuevo.';
    }
  } finally {
    isLoading.value = false;
  }
};

// Función para probar la conexión con el backend
const testConnection = async () => {
  try {
    const response = await api.get('/health');
    console.log('Conexión exitosa:', response.data);
    alert('✅ Conexión con el backend exitosa!');
  } catch (error) {
    console.error('Error de conexión:', error);
    alert('❌ Error de conexión con el backend');
  }
};
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-white to-verde-bap flex flex-col font-sans">
    <header class="w-full bg-white shadow py-4 flex justify-center">
      <img src="/src/assets/images/logo-wt.svg" alt="Logo del BAP" class="h-20" />
    </header>

    <main class="flex-grow flex items-center justify-center p-4">
      <AuthCard class=" min-h-[580px]">
        <h2 class="text-3xl font-bold text-gray-800 text-center mb-8">Iniciar sesión</h2>

        <div v-if="errorMessage" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
          <strong class="font-bold">¡Error!</strong>
          <span class="block sm:inline whitespace-pre-line">{{ errorMessage }}</span>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-4">
          <BaseInput 
            type="email" 
            v-model="email" 
            placeholder="correo@ejemplo.com" 
            autocomplete="email"
            :disabled="isLoading" 
          />
          <PasswordInput 
            v-model="password"
            :disabled="isLoading" 
          />
          <SubmitButton :disabled="isLoading" class="mt-2">
            <span v-if="isLoading">Ingresando...</span>
            <span v-else>Iniciar sesión</span>
          </SubmitButton>
        </form>

        <!-- Botón de prueba de conexión (puedes quitarlo en producción) -->
        <div class="mt-4">
          <button 
            @click="testConnection"
            type="button"
            class="w-full py-2 px-4 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            🔧 Probar conexión con backend
          </button>
        </div>

        <div class="text-center mt-6 text-sm text-gray-500">
          <p>¿Olvidaste tu contraseña?</p>
          <p>Comunícate con el Administrador del sistema</p>
        </div>

        <AuthFooter />
      </AuthCard>
    </main>
  </div>
</template>