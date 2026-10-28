import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import * as authApi from '@/api/auth'
import { ApiError } from '@/api/errors'
import { resetCsrfCookie } from '@/api/client'
import type { User } from '@/types/user'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const initialized = ref(false)
  const loading = ref(false)
  const lastError = ref<string | null>(null)

  const isAuthenticated = computed(() => user.value !== null)
  const isVerified = computed(() => user.value?.is_email_verified === true)
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')
  const isStaff = computed(
    () => user.value?.role === 'admin' || user.value?.role === 'super_admin',
  )
  const isAdmin = isStaff
  const isBlocked = computed(() => user.value?.status === 'blocked')

  function hasPermission(permission: string): boolean {
    if (isSuperAdmin.value) {
      return true
    }
    return user.value?.admin_permissions?.includes(permission) ?? false
  }

  async function bootstrap(options?: { probeSession?: boolean }): Promise<void> {
    if (initialized.value) {
      return
    }

    loading.value = true
    try {
      if (options?.probeSession === false) {
        user.value = null
      } else {
        await refreshUser()
      }
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        user.value = null
      } else if (error instanceof ApiError && error.code === 'ACCOUNT_BLOCKED') {
        user.value = null
        lastError.value = error.message
      }
    } finally {
      initialized.value = true
      loading.value = false
    }
  }

  async function refreshUser(): Promise<User | null> {
    try {
      const me = await authApi.fetchCurrentUser()
      user.value = me
      lastError.value = null
      return me
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        user.value = null
        return null
      }
      if (error instanceof ApiError && error.status === 403 && error.code === 'ACCOUNT_BLOCKED') {
        user.value = null
        lastError.value = error.message
        return null
      }
      throw error
    }
  }

  async function login(payload: authApi.LoginPayload): Promise<void> {
    loading.value = true
    lastError.value = null
    try {
      user.value = await authApi.login(payload)
    } finally {
      loading.value = false
    }
  }

  async function register(payload: authApi.RegisterPayload): Promise<void> {
    loading.value = true
    lastError.value = null
    try {
      user.value = await authApi.register(payload)
    } finally {
      loading.value = false
    }
  }

  function clearLocalSession(): void {
    user.value = null
    resetCsrfCookie()
    lastError.value = null
  }

  async function logout(): Promise<void> {
    loading.value = true
    try {
      await authApi.logout()
    } catch {
      // Vẫn xóa state local nếu session đã hết hạn phía server.
    } finally {
      clearLocalSession()
      loading.value = false
    }
  }

  async function updateProfile(payload: authApi.UpdateProfilePayload): Promise<User> {
    loading.value = true
    try {
      user.value = await authApi.updateProfile(payload)
      return user.value
    } finally {
      loading.value = false
    }
  }

  async function changePassword(payload: authApi.ChangePasswordPayload): Promise<string> {
    loading.value = true
    try {
      return await authApi.changePassword(payload)
    } finally {
      loading.value = false
    }
  }

  return {
    user,
    initialized,
    loading,
    lastError,
    isAuthenticated,
    isVerified,
    isAdmin,
    isStaff,
    isSuperAdmin,
    isBlocked,
    hasPermission,
    bootstrap,
    refreshUser,
    login,
    register,
    logout,
    clearLocalSession,
    updateProfile,
    changePassword,
  }
})
