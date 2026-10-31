import { adminActivateRoute, adminLoginRoute, adminProtectedRoutes } from '@/router/adminRoutes'
import { evaluateAuthGuard, shouldProbeSessionOnBootstrap } from '@/router/authGuards'
import { customerScrollBehavior } from '@/router/scrollBehavior'
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: customerScrollBehavior,
  routes: [
    {
      path: '/',
      component: () => import('@/layouts/customer/CustomerLayout.vue'),
      children: [
        {
          path: '',
          name: 'home',
          component: () => import('@/views/customer/HomeView.vue'),
        },
        {
          path: 'books',
          name: 'books',
          component: () => import('@/views/customer/BooksCatalogView.vue'),
        },
        {
          path: 'books/:slug',
          name: 'book-detail',
          component: () => import('@/views/customer/BookDetailView.vue'),
        },
        {
          path: 'cart',
          name: 'cart',
          meta: { requiresAuth: true },
          component: () => import('@/views/customer/CartView.vue'),
        },
        {
          path: 'checkout',
          name: 'checkout',
          meta: { requiresAuth: true, requiresVerified: true },
          component: () => import('@/views/customer/CheckoutView.vue'),
        },
        {
          path: 'orders',
          name: 'orders',
          meta: { requiresAuth: true },
          component: () => import('@/views/customer/OrdersListView.vue'),
        },
        {
          path: 'orders/:id',
          name: 'order-detail',
          meta: { requiresAuth: true },
          component: () => import('@/views/customer/OrderDetailView.vue'),
        },
        {
          path: 'orders/:id/payment',
          name: 'order-payment',
          meta: { requiresAuth: true, requiresVerified: true },
          component: () => import('@/views/customer/OrderPaymentView.vue'),
        },
        {
          path: 'wishlist',
          name: 'wishlist',
          meta: { requiresAuth: true },
          component: () => import('@/views/customer/WishlistView.vue'),
        },
        {
          path: 'account',
          meta: { requiresAuth: true },
          component: () => import('@/views/customer/account/AccountShellView.vue'),
          children: [
            { path: '', redirect: { name: 'account-profile' } },
            {
              path: 'profile',
              name: 'account-profile',
              component: () => import('@/views/customer/account/ProfileView.vue'),
            },
            {
              path: 'addresses',
              name: 'account-addresses',
              component: () => import('@/views/customer/account/AddressesView.vue'),
            },
            {
              path: 'security',
              name: 'account-security',
              component: () => import('@/views/customer/account/SecurityView.vue'),
            },
          ],
        },
        {
          path: 'forbidden',
          name: 'forbidden',
          component: () => import('@/views/ForbiddenView.vue'),
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
