export type UserRole = 'customer' | 'admin' | 'super_admin'
export type UserStatus = 'active' | 'blocked'

export interface User {
  id: number
  name: string
  email: string
  role: UserRole
  status: UserStatus
  email_verified_at: string | null
  is_email_verified: boolean
  admin_permissions?: string[]
  created_at?: string | null
}

export interface ApiErrorBody {
  message?: string
  code?: string
  errors?: Record<string, string[]>
}
