<script setup>
// DashboardView.vue ya no necesita lógica de usuario o logout,
// ya que el MainLayout se encarga de eso.
// Solo necesitas la lógica específica para el contenido del dashboard.
</script>

<template>
  <div>
    <h1 class="text-3xl font-bold text-gray-800 mb-4">¡Bienvenido al Dashboard!</h1>
    <p class="text-gray-600 mb-6">
      Esta es la vista principal que verán todos los usuarios al iniciar sesión.
      Utiliza la barra de navegación superior para acceder a los diferentes módulos del sistema.
    </p>
    </div>
</template>

<style scoped>
/* Estilos específicos para este componente */
/* Usamos el color personalizado verde-bap en el estilo de router-link-active */
.router-link-active {
  color: 'bg-verde-vap'; /* Usamos el valor hexadecimal directo o si prefieres, puedes usar una variable CSS si la defines */
  font-weight: bold;
}
</style>
