import { adminIcons } from '@/assets/admin-icons'
import '@/assets/styles/admin-layout.css'
import '@/assets/styles/theme.css'
import App from '@/App.vue'
import router from '@/router'
import '@coreui/coreui/dist/css/coreui.min.css'
import CIcon from '@coreui/icons-vue'
import CoreuiVue from '@coreui/vue'
import { setUnauthorizedHandler } from '@/api/sessionEvents'
import { useAuthStore } from '@/stores/auth'
import { createPinia } from 'pinia'
import { createApp } from 'vue'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(CoreuiVue)
app.provide('icons', adminIcons)
app.component('CIcon', CIcon)

setUnauthorizedHandler(() => {
  const auth = useAuthStore(pinia)
  auth.clearLocalSession()

  const route = router.currentRoute.value
  if (route.path.startsWith('/admin')) {
    void router.replace({
      name: 'admin-login',
      query: route.path === '/admin/login' ? undefined : { redirect: route.fullPath },
    })
  } else if (route.meta.requiresAuth) {
    void router.replace({
      name: 'login',
      query: { redirect: route.fullPath },
    })
  }
})

app.mount('#app')
