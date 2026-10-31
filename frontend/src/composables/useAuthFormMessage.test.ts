import { ApiError } from '@/api/errors'
import { describe, expect, it } from 'vitest'
import { authFormErrorMessage } from './useAuthFormMessage'

describe('authFormErrorMessage', () => {
  it('maps rate limit responses', () => {
    const msg = authFormErrorMessage(new ApiError(429, { message: 'Too Many Attempts.' }), 'fallback')
    expect(msg).toContain('Quá nhiều lần thử')
  })

  it('uses api message for validation errors', () => {
    const msg = authFormErrorMessage(new ApiError(422, { message: 'Email invalid' }), 'fallback')
    expect(msg).toBe('Email invalid')
  })
})
