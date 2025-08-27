// src/router/index.js
import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '@/views/LoginView.vue'
import MainLayout from '@/components/layout/MainLayout.vue'
import SolicitudFondoView from '@/views/SolicitudFondoView.vue'
import GestiondeUsuariosView from '@/views/AdministracionView.vue'
import api from '@/plugins/axios'

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
      meta: { public: true },
    },
    {
      path: '/about',
      name: 'about',
      component: () => import('../views/AboutView.vue'),
      meta: { public: true },
    },
    {
      path: '/dashboard',
      component: MainLayout,
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'dashboard-home',
          component: () => import('@/views/DashboardView.vue'),
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
          path: 'fondos',
          name: 'dashboard-fondos',
          component: () => import('@/components/fondos/GestionFondos.vue'),
        },
        {
          path: 'administracion',
          name: 'administracion',
          component: () => import('@/views/AdministracionView.vue'),
          meta: { permission: 'navigate.administracion' },
        },
      ],
    },
  ],
})

let cachedUser = null

async function getCurrentUser() {
  if (cachedUser) return cachedUser
  try {
    const { data } = await api.get('/auth/user')
    cachedUser = data
    return data
  } catch (e) {
    cachedUser = null
    throw e
  }
}

router.beforeEach(async (to, from, next) => {
  if (to.meta.public) return next()

  if (!to.meta.requiresAuth && !to.matched.some(r => r.meta.requiresAuth)) {
    return next()
  }

  try {
    const data = await getCurrentUser()
    const userRole = data?.role?.name || data?.user?.role?.name
    
    // Verificar permisos en lugar de roles
    const requiredPermission = to.meta.permission || to.matched.find(r => r.meta?.permission)?.meta?.permission
    if (requiredPermission) {
      const hasPermission = data?.role?.permissions?.some(p => p.name === requiredPermission)
      if (!hasPermission) {
        return next('/dashboard')
      }
    }
    
    // Mantener compatibilidad con roles (para otras rutas)
    const requiredRoles = to.meta.roles || to.matched.find(r => r.meta?.roles)?.meta?.roles
    if (requiredRoles && !requiredRoles.includes(userRole)) {
      return next('/dashboard')
    }
    
    return next()
  } catch (e) {
    return next('/login')
  }
})

export default router
