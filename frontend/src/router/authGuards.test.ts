import { describe, expect, it } from 'vitest'
import { evaluateAuthGuard } from '@/router/authGuards'

const customer = {
  isAuthenticated: true,
  isStaff: false,
  isSuperAdmin: false,
  isVerified: true,
  isBlocked: false,
  user: { role: 'customer', is_email_verified: true, status: 'active' } as never,
}

const admin = {
  isAuthenticated: true,
  isStaff: true,
  isSuperAdmin: false,
  isVerified: true,
  isBlocked: false,
  user: { role: 'admin', is_email_verified: true, status: 'active' } as never,
}

const guest = {
  isAuthenticated: false,
  isStaff: false,
  isSuperAdmin: false,
  isVerified: false,
  isBlocked: false,
  user: null,
}

describe('evaluateAuthGuard admin area', () => {
  it('guest admin dashboard -> admin login', () => {
    const d = evaluateAuthGuard(guest, {
      path: '/admin/dashboard',
      fullPath: '/admin/dashboard',
      name: 'admin-dashboard',
      meta: { requiresAdmin: true },
      query: {},
    })
    expect(d).toEqual({
      action: 'redirect',
      to: { name: 'admin-login', query: { redirect: '/admin/dashboard' } },
    })
  })

  it('customer admin dashboard -> forbidden without logout', () => {
    const d = evaluateAuthGuard(customer, {
      path: '/admin/dashboard',
      fullPath: '/admin/dashboard',
      name: 'admin-dashboard',
      meta: { requiresAdmin: true },
      query: {},
    })
    expect(d).toEqual({ action: 'redirect', to: { name: 'forbidden' } })
  })

  it('guest admin activate -> allow', () => {
    const d = evaluateAuthGuard(guest, {
      path: '/admin/activate',
      fullPath: '/admin/activate?email=a@b.com&token=x',
      name: 'admin-activate',
      meta: {},
      query: { email: 'a@b.com', token: 'x' },
    })
    expect(d).toEqual({ action: 'allow' })
  })

  it('admin dashboard -> allow', () => {
    const d = evaluateAuthGuard(admin, {
      path: '/admin/dashboard',
      fullPath: '/admin/dashboard',
      name: 'admin-dashboard',
      meta: { requiresAdmin: true },
      query: {},
    })
    expect(d).toEqual({ action: 'allow' })
  })
})

describe('evaluateAuthGuard customer area', () => {
  it('guest account -> customer login', () => {
    const d = evaluateAuthGuard(guest, {
      path: '/account',
      fullPath: '/account',
      name: 'account',
      meta: { requiresAuth: true },
      query: {},
    })
    expect(d.action).toBe('redirect')
    if (d.action === 'redirect') {
      expect(d.to).toEqual({ name: 'login', query: { redirect: '/account' } })
    }
  })

  it('staff on customer login -> admin dashboard', () => {
    const d = evaluateAuthGuard(admin, {
      path: '/login',
      fullPath: '/login',
      name: 'login',
      meta: { guest: true },
      query: {},
    })
    expect(d).toEqual({ action: 'redirect', to: { name: 'admin-dashboard' } })
  })

  it('blocked customer home -> logout', () => {
    const d = evaluateAuthGuard(
      { ...customer, isBlocked: true },
      {
        path: '/',
        fullPath: '/',
        name: 'home',
        meta: {},
        query: {},
      },
    )
    expect(d).toEqual({
      action: 'logout',
      to: { name: 'login', query: { blocked: '1' } },
    })
  })

  it('guest home -> allow', () => {
    const d = evaluateAuthGuard(guest, {
      path: '/',
      fullPath: '/',
      name: 'home',
      meta: {},
      query: {},
    })
    expect(d).toEqual({ action: 'allow' })
  })
})
