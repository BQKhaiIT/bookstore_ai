import { describe, expect, it } from 'vitest'
import { parseActivationQuery } from '@/utils/activationParams'

describe('parseActivationQuery', () => {
  it('reads email, token and invitation id from query', () => {
    expect(
      parseActivationQuery({
        email: 'Admin@Example.com',
        token: 'abc123',
        invitation: '42',
      }),
    ).toEqual({
      email: 'admin@example.com',
      token: 'abc123',
      invitationId: 42,
    })
  })

  it('uses first value when query param is repeated', () => {
    expect(
      parseActivationQuery({
        token: ['first-token', 'second-token'],
      }),
    ).toEqual({
      email: '',
      token: 'first-token',
      invitationId: null,
    })
  })
})
