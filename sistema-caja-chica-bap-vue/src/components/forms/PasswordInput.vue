<template>
  <div class="relative">
    <input
      :type="showPassword ? 'text' : 'password'"
      v-model="modelValue"
      placeholder="••••••••"
      autocomplete="current-password"
      class="input-field"
      required
    />
    <button
      type="button"
      @click="showPassword = !showPassword"
      class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700"
      aria-label="Mostrar u ocultar contraseña"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path
          v-if="!showPassword"
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
        />
        <path
          v-else
          stroke-linecap="round"
          stroke-linejoin="round"
          stroke-width="2"
          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-.39 1.295-1.035 2.492-1.905 3.505M15 12a3 3 0 01-6 0"
        />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const showPassword = ref(false)
const modelValue = defineModel()
</script>
