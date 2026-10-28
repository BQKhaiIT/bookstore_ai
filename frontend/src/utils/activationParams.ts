import type { LocationQuery } from 'vue-router'

function queryString(query: LocationQuery, key: string): string {
  const value = query[key]
  if (Array.isArray(value)) {
    return String(value[0] ?? '').trim()
  }

  return String(value ?? '').trim()
}

export interface ActivationLinkParams {
  email: string
  token: string
  invitationId: number | null
}

export function parseActivationQuery(query: LocationQuery): ActivationLinkParams {
  const invitationRaw = queryString(query, 'invitation')
  const invitationId =
    invitationRaw.length > 0 && /^\d+$/.test(invitationRaw) ? Number(invitationRaw) : null

  return {
    email: queryString(query, 'email').toLowerCase(),
    token: queryString(query, 'token'),
    invitationId,
  }
}
