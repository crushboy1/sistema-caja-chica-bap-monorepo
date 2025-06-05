<template>
  <input
    :type="type"
    :placeholder="placeholder"
    v-model="modelValue"
    :autocomplete="autocomplete"
    class="input-field"
    required
  />
</template>

<script setup>
defineProps(['type', 'placeholder', 'autocomplete'])
defineEmits(['update:modelValue'])
const modelValue = defineModel()
</script>
