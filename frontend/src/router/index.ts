import { adminActivateRoute, adminLoginRoute, adminProtectedRoutes } from '@/router/adminRoutes'
import { evaluateAuthGuard, shouldProbeSessionOnBootstrap } from '@/router/authGuards'
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('@/views/HomeView.vue'),
        },
        {
          path: 'account',
          name: 'account',
          component: () => import('@/views/account/AccountView.vue'),
        },
        {
          path: 'forbidden',
          name: 'forbidden',
          component: () => import('@/views/ForbiddenView.vue'),
          meta: { requiresAuth: true },
        },
      ],
    },
    {
      path: '/',
      component: () => import('@/layouts/AuthLayout.vue'),
      children: [
        {
          path: 'login',
          name: 'login',
          component: () => import('@/views/auth/LoginView.vue'),
          meta: { guest: true },
        },
        {
          path: 'register',
          name: 'register',
          component: () => import('@/views/auth/RegisterView.vue'),
          meta: { guest: true },
        },
        {
          path: 'forgot-password',
          name: 'forgot-password',
          component: () => import('@/views/auth/ForgotPasswordView.vue'),
          meta: { guest: true },
        },
        {
          path: 'reset-password',
          name: 'reset-password',
          component: () => import('@/views/auth/ResetPasswordView.vue'),
          meta: { guest: true },
        },
        {
          path: 'verify-email',
          name: 'verify-email',
          component: () => import('@/views/auth/VerifyEmailNoticeView.vue'),
          meta: { requiresAuth: true },
        },
        {
          path: 'email-verified',
          name: 'email-verified',
          component: () => import('@/views/auth/EmailVerifiedView.vue'),
        },
      ],
    },
    adminLoginRoute,
    adminActivateRoute,
    adminProtectedRoutes,
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'home' },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.bootstrap({
      probeSession: shouldProbeSessionOnBootstrap({
        path: to.path,
        fullPath: to.fullPath,
        name: to.name,
        meta: {},
        query: to.query,
      }),
    })
  }

  const decision = evaluateAuthGuard(
    {
      isAuthenticated: auth.isAuthenticated,
      isStaff: auth.isStaff,
      isSuperAdmin: auth.isSuperAdmin,
      isVerified: auth.isVerified,
      isBlocked: auth.isBlocked,
      user: auth.user,
    },
    {
      path: to.path,
      fullPath: to.fullPath,
      name: to.name,
      meta: {
        requiresAuth: Boolean(to.meta.requiresAuth),
        guest: Boolean(to.meta.guest),
        requiresVerified: Boolean(to.meta.requiresVerified),
        requiresAdmin: Boolean(to.meta.requiresAdmin),
        requiresSuperAdmin: Boolean(to.meta.requiresSuperAdmin),
      },
      query: to.query,
    },
  )

  if (decision.action === 'logout') {
    await auth.logout()
    return decision.to
  }

  if (decision.action === 'redirect') {
    return decision.to
  }

  return true
})

export default router
