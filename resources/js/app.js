import './bootstrap'
import '../css/app.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import VueApexCharts from 'vue3-apexcharts'
import App from './App.vue'
import router from './router'
import i18n from './i18n'

// Create Vue app
const app = createApp(App)

// Use plugins
app.use(createPinia())
app.use(router)
app.use(i18n)
app.use(VueApexCharts)

// Mount app
app.mount('#app')
