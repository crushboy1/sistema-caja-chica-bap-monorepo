<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/plugins/axios';

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
    console.log('Intentando login...');
    const response = await api.post('/auth/login', {
      email: email.value,
      password: password.value,
    });

    // Redirigir al dashboard en caso de éxito
    router.push('/dashboard');

  } catch (error) {
    console.error('Error de login:', error);

    if (error.response) {
      const status = error.response.status;
      const data = error.response.data;

      if (status === 422) {
        if (data.errors && data.errors.email) {
          errorMessage.value = data.errors.email[0];
        } else {
          errorMessage.value = data.message || 'Los datos proporcionados no son válidos.';
        }
      } else if (status === 401 || status === 403) {
        errorMessage.value = 'Credenciales incorrectas. Por favor, verifica tu correo y contraseña.';
      } else if (status === 419) {
        errorMessage.value = 'Tu sesión ha expirado. Recargando la página...';
        setTimeout(() => window.location.reload(), 2000);
      } else {
        errorMessage.value = data.message || 'Ocurrió un error en el servidor. Por favor, inténtalo más tarde.';
      }
    } else if (error.request) {
      errorMessage.value = 'No se pudo conectar con el servidor. Verifica tu conexión y que el backend esté funcionando.';
    } else {
      errorMessage.value = 'Ocurrió un error inesperado al preparar la solicitud.';
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
    alert(`✅ ${response.data.message}`);
  } catch (error) {
    console.error('Error de conexión:', error);
    let errorMessage = '❌ Error de conexión con el backend.';
    if (error.request) {
      errorMessage += ' No se pudo recibir respuesta del servidor. ¿Está encendido y accesible?';
    }
    alert(errorMessage);
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
        <h2 class="text-3xl font-bold text-gris-bap-dark text-center mb-8">Iniciar Sesión</h2>

        <div v-if="errorMessage"
          class="bg-estado-error-bg border border-rojo-bap text-estado-error-text px-4 py-3 rounded-lg relative mb-6 animate-shake">
          <strong class="font-bold">¡Error!</strong>
          <span class="block sm:inline ml-2">{{ errorMessage }}</span>
        </div>
        
        <form @submit.prevent="handleLogin" class="space-y-4">
          <BaseInput type="email" v-model="email" placeholder="correo@ejemplo.com" autocomplete="email"
            :disabled="isLoading" />
          <PasswordInput v-model="password" :disabled="isLoading" />
          <SubmitButton :disabled="isLoading" class="mt-2">
            <span v-if="isLoading">Ingresando...</span>
            <span v-else>Iniciar sesión</span>
          </SubmitButton>
        </form>

        <!-- Botón de prueba de conexión (puedes quitarlo en producción) -->
        <div class="mt-4">
          <button @click="testConnection" type="button"
            class="w-full py-2 px-4 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
            🔧 Probar conexión con backend
          </button>
        </div>

        <div class="text-center mt-6 text-sm text-gris-bap">
          <p>¿Olvidaste tu contraseña?</p>
          <p>Comunícate con el Administrador del sistema.</p>
        </div>

        <AuthFooter />
      </AuthCard>
    </main>
  </div>
</template>