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
    </button>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const showPassword = ref(false)
const modelValue = defineModel()
</script>
