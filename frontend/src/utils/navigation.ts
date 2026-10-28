import type { User } from '@/types/user'
import type { RouteLocationRaw } from 'vue-router'

const ADMIN_LOGIN_PATH = '/admin/login'

function isUnsafePath(path: string): boolean {
  if (!path.startsWith('/') || path.startsWith('//') || path.includes('\\')) {
    return true
  }
  if (path.includes('@') || path.toLowerCase().includes('://')) {
    return true
  }
  return false
}

/** Internal path an toàn; tùy chọn giới hạn prefix / loại trừ prefix. */
export function sanitizeInternalRedirect(
  redirect: unknown,
  options: { allowedPrefix?: string; forbiddenPrefixes?: string[] } = {},
): string | null {
  if (typeof redirect !== 'string') {
    return null
  }

  const path = redirect.trim()
  if (isUnsafePath(path)) {
    return null
  }

  const { allowedPrefix, forbiddenPrefixes = [] } = options

  if (allowedPrefix && !path.startsWith(allowedPrefix)) {
    return null
  }

  for (const prefix of forbiddenPrefixes) {
    if (path === prefix || path.startsWith(`${prefix}/`)) {
      return null
    }
  }

  return path
}

export function sanitizeCustomerRedirect(redirect: unknown): string | null {
  return sanitizeInternalRedirect(redirect, { forbiddenPrefixes: ['/admin'] })
}

export function sanitizeAdminRedirect(redirect: unknown): string | null {
  const path = sanitizeInternalRedirect(redirect, { allowedPrefix: '/admin' })
  if (path === null) {
    return null
  }
  if (path === ADMIN_LOGIN_PATH || path.startsWith(`${ADMIN_LOGIN_PATH}?`)) {
    return null
  }
  return path
}

/** @deprecated Dùng resolvePostCustomerAuthRoute / resolvePostAdminAuthRoute */
export function sanitizeRedirect(redirect: unknown): string | null {
  return sanitizeInternalRedirect(redirect)
}

export function resolvePostCustomerAuthRoute(user: User, redirect?: unknown): RouteLocationRaw {
  const safeRedirect = sanitizeCustomerRedirect(redirect)
  if (safeRedirect) {
    return safeRedirect
  }

  if (!user.is_email_verified) {
    return { name: 'verify-email' }
  }

  return { name: 'home' }
}

export function resolvePostAdminAuthRoute(user: User, redirect?: unknown): RouteLocationRaw {
  const safeRedirect = sanitizeAdminRedirect(redirect)
  if (safeRedirect) {
    return safeRedirect
  }

  if (!user.is_email_verified) {
    return { name: 'verify-email' }
  }

  return { name: 'admin-dashboard' }
}

/** Sau xác minh email — phân nhánh theo role. */
export function resolvePostAuthRoute(user: User, redirect?: unknown): RouteLocationRaw {
  if (user.role === 'admin' || user.role === 'super_admin') {
    return resolvePostAdminAuthRoute(user, redirect)
  }

  return resolvePostCustomerAuthRoute(user, redirect)
}
