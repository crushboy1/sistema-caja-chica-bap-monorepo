<template>
  <div class="w-full max-w-6xl bg-white rounded-3xl shadow-2xl flex flex-col md:flex-row overflow-hidden">
    <!-- Ilustración -->
    <div class="hidden md:flex md:w-1/2 bg-gradient-to-br from-verde-bap to-white items-center justify-center p-8">
      <img src="/src/assets/images/logo-wt.svg" alt="Ilustración" class="w-3/4 object-contain" />
    </div>

    <!-- Slot para contenido -->
    <div class="w-full md:w-1/2 p-10">
      <slot />
    </div>
    
  </div>
</template>
