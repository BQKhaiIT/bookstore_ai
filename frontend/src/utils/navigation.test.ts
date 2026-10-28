import { describe, expect, it } from 'vitest'
import {
  resolvePostAdminAuthRoute,
  resolvePostCustomerAuthRoute,
  sanitizeAdminRedirect,
  sanitizeCustomerRedirect,
} from '@/utils/navigation'

describe('sanitizeCustomerRedirect', () => {
  it('allows customer paths', () => {
    expect(sanitizeCustomerRedirect('/account')).toBe('/account')
  })

  it('rejects admin paths and open redirects', () => {
    expect(sanitizeCustomerRedirect('/admin/dashboard')).toBeNull()
    expect(sanitizeCustomerRedirect('//evil.test')).toBeNull()
    expect(sanitizeCustomerRedirect('https://evil.test')).toBeNull()
  })
})

describe('sanitizeAdminRedirect', () => {
  it('allows admin internal paths', () => {
    expect(sanitizeAdminRedirect('/admin/dashboard')).toBe('/admin/dashboard')
  })

  it('rejects customer paths and admin login loop', () => {
    expect(sanitizeAdminRedirect('/account')).toBeNull()
    expect(sanitizeAdminRedirect('/admin/login')).toBeNull()
  })
})

describe('resolvePostCustomerAuthRoute', () => {
  it('never redirects to admin', () => {
    const route = resolvePostCustomerAuthRoute(
      { role: 'customer', is_email_verified: true } as never,
      '/admin/dashboard',
    )
    expect(route).toEqual({ name: 'home' })
  })
})

describe('resolvePostAdminAuthRoute', () => {
  it('defaults to admin dashboard', () => {
    expect(
      resolvePostAdminAuthRoute({ role: 'admin', is_email_verified: true } as never),
    ).toEqual({ name: 'admin-dashboard' })
  })
})
