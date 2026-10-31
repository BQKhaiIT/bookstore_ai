import type { RouteRecordRaw } from 'vue-router'

export const adminActivateRoute: RouteRecordRaw = {
  path: '/admin/activate',
  component: () => import('@/layouts/AdminAuthLayout.vue'),
  children: [
    {
      path: '',
      name: 'admin-activate',
      component: () => import('@/views/admin/auth/AdminActivateView.vue'),
    },
  ],
}

export const adminLoginRoute: RouteRecordRaw = {
  path: '/admin/login',
  name: 'admin-login',
  component: () => import('@/layouts/AdminAuthLayout.vue'),
  children: [
    {
      path: '',
      name: 'admin-login-form',
      component: () => import('@/views/admin/auth/AdminLoginView.vue'),
    },
  ],
}

export const adminProtectedRoutes: RouteRecordRaw = {
  path: '/admin',
  component: () => import('@/layouts/AdminLayout.vue'),
  meta: { requiresAuth: true, requiresVerified: true, requiresAdmin: true },
  children: [
    {
      path: '',
      redirect: { name: 'admin-dashboard' },
    },
    {
      path: 'dashboard',
      name: 'admin-dashboard',
      component: () => import('@/views/admin/DashboardView.vue'),
    },
    {
      path: 'books',
      name: 'admin-books',
      component: () => import('@/views/admin/books/BooksListView.vue'),
    },
    {
      path: 'books/new',
      name: 'admin-book-create',
      component: () => import('@/views/admin/books/BookFormView.vue'),
    },
    {
      path: 'books/:id/edit',
      name: 'admin-book-edit',
      component: () => import('@/views/admin/books/BookFormView.vue'),
    },
    {
      path: 'categories',
      name: 'admin-categories',
      component: () => import('@/views/admin/CategoriesView.vue'),
    },
    {
      path: 'authors',
      name: 'admin-authors',
      component: () => import('@/views/admin/AuthorsView.vue'),
    },
    {
      path: 'publishers',
      name: 'admin-publishers',
      component: () => import('@/views/admin/PublishersView.vue'),
    },
    {
      path: 'orders',
      name: 'admin-orders',
      component: () => import('@/views/admin/orders/OrdersListView.vue'),
    },
    {
      path: 'orders/:id',
      name: 'admin-order-detail',
      component: () => import('@/views/admin/orders/OrderDetailView.vue'),
    },
    {
      path: 'payments',
      name: 'admin-payments',
      component: () => import('@/views/admin/PaymentsView.vue'),
    },
    {
      path: 'users',
      name: 'admin-users',
      component: () => import('@/views/admin/UsersView.vue'),
    },
    {
      path: 'reviews',
      name: 'admin-reviews',
      component: () => import('@/views/admin/ReviewsView.vue'),
    },
    {
      path: 'banners',
      name: 'admin-banners',
      component: () => import('@/views/admin/BannersView.vue'),
    },
    {
      path: 'staff',
      name: 'admin-staff',
      meta: { requiresSuperAdmin: true },
      component: () => import('@/views/admin/StaffAdminsView.vue'),
    },
  ],
}

/** @deprecated */
export const adminRoutes = adminProtectedRoutes
