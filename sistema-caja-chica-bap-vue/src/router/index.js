// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router';
import LoginView from '@/views/LoginView.vue';
import MainLayout from '@/components/layout/MainLayout.vue'; // Importa el nuevo componente de layout
import SolicitudFondoView from '@/views/SolicitudFondoView.vue';
import GestiondeUsuariosView from '@/views/GestionUsuariosView.vue';
const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      redirect: '/login',
    },
    {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('../views/AboutView.vue'),
    },
    {
      // La ruta /dashboard ahora usa MainLayout como su componente principal
      path: '/dashboard',
      component: MainLayout, // MainLayout contendrá el navbar y el <router-view> para las rutas hijas
      // meta: { requiresAuth: true }, // Puedes añadir esto para proteger la ruta
      children: [
        {
          path: '', // Ruta por defecto para /dashboard (ej. un resumen)
          name: 'dashboard-home',
          component: () => import('@/views/DashboardView.vue'), // Ahora DashboardView es una vista hija
        },
        {
          path: 'solicitudes',
          name: 'solicitudes',
          component: () => import('@/views/SolicitudFondoView.vue'),
        },
        {
          path: 'declaraciones',
          name: 'declaraciones',
          component: () => import('@/views/DeclaracionesView.vue'),
        },
        {
          path: 'gestion-usuarios',
          name: 'gestion-usuarios',
          component: () => import('@/views/GestionUsuariosView.vue'),
        },
        // Puedes añadir más rutas hijas aquí para otros módulos
      ],
    },
  ],
});

// Opcional: Guardia de navegación para proteger rutas
// router.beforeEach((to, from, next) => {
//   // Ejemplo simple: Si la ruta requiere autenticación y el usuario no está autenticado
//   // Esto requeriría una lógica de estado de autenticación global (ej. Pinia/Vuex)
//   if (to.meta.requiresAuth && !localStorage.getItem('auth_token')) { // Ejemplo muy básico
//     next('/login');
//   } else {
//     next();
//   }
// });

export default router;
