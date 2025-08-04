import './assets/main.css'       // 1. Tus estilos Tailwind y personalizados
import '@/assets/css/forms.css'  // 2. Otros estilos de formularios

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'

import vSelect from 'vue-select' // 3. Importa Vue Select
import 'vue-select/dist/vue-select.css' // 4. Sus estilos base

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.component('v-select', vSelect) // Registro global del componente

app.mount('#app')
