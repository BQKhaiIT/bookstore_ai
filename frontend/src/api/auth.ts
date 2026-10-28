import { api, ensureCsrfCookie } from '@/api/client'
import type { User } from '@/types/user'

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface ResetPasswordPayload {
  email: string
  token: string
  password: string
  password_confirmation: string
}

async function withCsrf<T>(action: () => Promise<T>): Promise<T> {
  await ensureCsrfCookie()
  return action()
}

export async function fetchCurrentUser(): Promise<User> {
  const { data } = await api.get<{ data: User }>('/api/v1/me')
  return data.data
}

export async function login(payload: LoginPayload): Promise<User> {
  return withCsrf(async () => {
    const { data } = await api.post<{ data: User }>('/login', payload)
    return data.data
  })
}

export async function register(payload: RegisterPayload): Promise<User> {
  return withCsrf(async () => {
    const { data } = await api.post<{ data: User }>('/register', payload)
    return data.data
  })
}

export async function logout(): Promise<void> {
  await withCsrf(async () => {
    await api.post('/logout')
  })
}

export async function resendVerificationEmail(): Promise<string> {
  return withCsrf(async () => {
    const { data } = await api.post<{ message: string }>('/email/verification-notification')
    return data.message
  })
}

export async function forgotPassword(email: string): Promise<string> {
  return withCsrf(async () => {
    const { data } = await api.post<{ message: string }>('/forgot-password', { email })
    return data.message
  })
}

export async function resetPassword(payload: ResetPasswordPayload): Promise<string> {
  return withCsrf(async () => {
    const { data } = await api.post<{ message: string }>('/reset-password', payload)
    return data.message
  })
}

export interface UpdateProfilePayload {
  name?: string
  email?: string
}

export async function updateProfile(payload: UpdateProfilePayload): Promise<User> {
  return withCsrf(async () => {
    const { data } = await api.patch<{ data: User }>('/api/v1/me', payload)
    return data.data
  })
}

export interface ChangePasswordPayload {
  current_password: string
  password: string
  password_confirmation: string
}

export async function changePassword(payload: ChangePasswordPayload): Promise<string> {
  return withCsrf(async () => {
    const { data } = await api.patch<{ message: string }>('/api/v1/me/password', payload)
    return data.message
  })
}

export interface AdminActivationPayload {
  email: string
  token: string
  password: string
  password_confirmation: string
  invitation?: number
}

export async function activateAdminAccount(payload: AdminActivationPayload): Promise<string> {
  return withCsrf(async () => {
    const { data } = await api.post<{ message: string }>('/admin/activate', payload)
    return data.message
  })
}
