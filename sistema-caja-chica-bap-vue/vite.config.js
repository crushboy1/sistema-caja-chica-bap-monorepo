import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools() // Opcional: comenta si no quieres las dev tools
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    host: '0.0.0.0', // Permite conexiones desde cualquier IP (necesario para Docker)
    port: 3000,
    watch: {
      usePolling: true, // Necesario para que funcione el hot reload en Docker
    },
    // Configuración del proxy para la API
    proxy: {
      // Regla específica para sanctum/csrf-cookie: ¡reescribe el /api/!
      '/api/sanctum/csrf-cookie': {
        target: 'http://caja-chica-app:80', // Apunta al puerto interno de Apache en el contenedor Laravel
        changeOrigin: true,
        secure: false,
        rewrite: (path) => path.replace(/^\/api/, ''), // SOLO aquí se reescribe para que llegue como /sanctum/csrf-cookie
        configure: (proxy, options) => {
          proxy.on('error', (err, req, res) => {
            console.log('proxy error', err);
          });
          proxy.on('proxyReq', (proxyReq, req, res) => {
            console.log('Sending Request to the Target:', req.method, req.url);
          });
          proxy.on('proxyRes', (proxyRes, req, res) => {
            console.log('Received Response from the Target:', proxyRes.statusCode, req.url);
          });
        },
      },
      // Regla general para el resto de tus APIs (no reescribe /api/)
      '/api': {
        target: 'http://caja-chica-app:80', // Apunta al puerto interno de Apache en el contenedor Laravel
        changeOrigin: true,
        secure: false,
        // NO HAY REWRITE AQUÍ: La ruta /api/auth/login se envía tal cual a Laravel
        configure: (proxy, options) => {
          proxy.on('error', (err, req, res) => {
            console.log('proxy error', err);
          });
          proxy.on('proxyReq', (proxyReq, req, res) => {
            console.log('Sending Request to the Target:', req.method, req.url);
          });
          proxy.on('proxyRes', (proxyRes, req, res) => {
            console.log('Received Response from the Target:', proxyRes.statusCode, req.url);
          });
        },
      },
    },
  },
  // Variables de entorno que estarán disponibles en el cliente
  define: {
    __VUE_OPTIONS_API__: true,
    __VUE_PROD_DEVTOOLS__: false,
  },
  // Configuración para build de producción
  build: {
    outDir: 'dist',
    sourcemap: true,
  }
})
