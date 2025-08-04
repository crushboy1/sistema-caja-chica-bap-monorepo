<template>
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <h2 class="text-2xl font-bold text-gray-800 text-shadow">{{ title }}</h2>
            <div v-if="subtitle" class="hidden sm:block">
                <span class="text-gray-400">•</span>
                <span class="text-sm text-gray-500 ml-2">{{ subtitle }}</span>
            </div>
        </div>

        <div v-if="$slots.actions" class="flex items-center gap-2">
            <slot name="actions"></slot>
        </div>

        <div v-else-if="showRefresh" class="flex items-center gap-2">
            <button @click="$emit('refresh')"
                class="text-sm text-gray-500 hover:text-verde-bap transition-colors duration-200 flex items-center gap-1"
                :disabled="loading">
                <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'animate-spin': loading }" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="hidden sm:inline">Actualizar</span>
            </button>
        </div>
    </div>

    <!-- Línea divisoria opcional -->
    <div v-if="showDivider" class="border-b border-gray-100 mb-6"></div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true
    },
    subtitle: {
        type: String,
        default: null
    },
    showRefresh: {
        type: Boolean,
        default: false
    },
    showDivider: {
        type: Boolean,
        default: true
    },
    loading: {
        type: Boolean,
        default: false
    }
});
defineEmits(['refresh']);
</script>