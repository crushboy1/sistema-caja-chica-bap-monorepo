<template>
  <button 
    type="submit" 
    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-soft text-sm font-semibold text-white bg-verde-bap hover:bg-verde-bap-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-verde-bap-dark transition-all duration-300 ease-in-out transform hover:-translate-y-0.5 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
  >
    <slot />
  </button>
</template>
