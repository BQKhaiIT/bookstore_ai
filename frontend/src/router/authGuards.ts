import {
  resolvePostAdminAuthRoute,
  resolvePostCustomerAuthRoute,
  sanitizeAdminRedirect,
  sanitizeCustomerRedirect,
} from '@/utils/navigation'
import type { User } from '@/types/user'
import type { RouteLocationRaw } from 'vue-router'

export interface AuthSnapshot {
  isAuthenticated: boolean
  isStaff: boolean
  isSuperAdmin: boolean
  isVerified: boolean
  isBlocked: boolean
  user: User | null
}

export interface GuardTarget {
  path: string
  fullPath: string
  name?: string | symbol | null
  meta: {
    requiresAuth?: boolean
    guest?: boolean
    requiresVerified?: boolean
    requiresAdmin?: boolean
    requiresSuperAdmin?: boolean
  }
  query: Record<string, unknown>
}

export type AuthGuardDecision =
  | { action: 'allow' }
  | { action: 'redirect'; to: RouteLocationRaw }
  | { action: 'logout'; to: RouteLocationRaw }

export function isAdminLoginRoute(to: GuardTarget): boolean {
  return to.name === 'admin-login' || to.name === 'admin-login-form'
}

export function isAdminPublicAuthRoute(to: GuardTarget): boolean {
  return isAdminLoginRoute(to) || to.name === 'admin-activate' || to.path === '/admin/activate'
}

export function shouldProbeSessionOnBootstrap(to: GuardTarget): boolean {
  return !isAdminPublicAuthRoute(to) || isAdminLoginRoute(to)
}

export function isAdminProtectedRoute(to: GuardTarget): boolean {
  return to.path.startsWith('/admin') && !isAdminPublicAuthRoute(to)
}

export function evaluateAuthGuard(auth: AuthSnapshot, to: GuardTarget): AuthGuardDecision {
  if (isAdminProtectedRoute(to)) {
    if (!auth.isAuthenticated) {
      return {
        action: 'redirect',
        to: {
          name: 'admin-login',
          query: { redirect: to.fullPath },
        },
      }
    }

    if (auth.isBlocked) {
      return {
        action: 'logout',
        to: {
          name: 'admin-login',
          query: { blocked: '1' },
        },
      }
    }

    if (!auth.isStaff) {
      return { action: 'redirect', to: { name: 'forbidden' } }
    }

    if (!auth.isVerified) {
      return {
        action: 'redirect',
        to: {
          name: 'verify-email',
          query: { redirect: sanitizeAdminRedirect(to.fullPath) ?? '/admin/dashboard' },
        },
      }
    }

    if (Boolean(to.meta.requiresSuperAdmin) && !auth.isSuperAdmin) {
      return { action: 'redirect', to: { name: 'forbidden' } }
    }

    return { action: 'allow' }
  }

  if (isAdminLoginRoute(to)) {
    if (auth.isAuthenticated && auth.isBlocked) {
      return {
        action: 'logout',
        to: {
          name: 'admin-login',
          query: { blocked: '1' },
        },
      }
    }

    if (auth.isAuthenticated && auth.user && auth.isStaff && auth.isVerified) {
      return {
        action: 'redirect',
        to: resolvePostAdminAuthRoute(auth.user, to.query.redirect),
      }
    }

    if (auth.isAuthenticated && auth.isStaff && !auth.isVerified) {
      return { action: 'redirect', to: { name: 'verify-email' } }
    }

    return { action: 'allow' }
  }

  const requiresAuth = Boolean(to.meta.requiresAuth)
  const guestOnly = Boolean(to.meta.guest)
  const requiresVerified = Boolean(to.meta.requiresVerified)

  if (requiresAuth && !auth.isAuthenticated) {
    const redirectPath = sanitizeCustomerRedirect(to.fullPath) ?? to.fullPath
    return {
      action: 'redirect',
      to: {
        name: 'login',
        query: { redirect: redirectPath },
      },
    }
  }

  if (guestOnly && auth.isAuthenticated && auth.user) {
    if (auth.isStaff) {
      return {
        action: 'redirect',
        to: resolvePostAdminAuthRoute(auth.user, to.query.redirect),
      }
    }

    return {
      action: 'redirect',
      to: resolvePostCustomerAuthRoute(auth.user, to.query.redirect),
    }
  }

  if (to.name === 'verify-email' && auth.isAuthenticated && auth.isVerified) {
    return { action: 'redirect', to: { name: 'home' } }
  }

  if (requiresVerified && auth.isAuthenticated && !auth.isVerified) {
    return { action: 'redirect', to: { name: 'verify-email' } }
  }

  if (auth.isAuthenticated && auth.isBlocked && !to.path.startsWith('/admin')) {
    return {
      action: 'logout',
      to: {
        name: 'login',
        query: { blocked: '1' },
      },
    }
  }

  return { action: 'allow' }
}
