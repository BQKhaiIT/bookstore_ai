<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import AuthAlert from '@/components/AuthAlert.vue'
import * as authApi from '@/api/auth'
import { ApiError } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const message = ref<string | null>(null)
const error = ref<string | null>(null)
const submitting = ref(false)

async function resend(): Promise<void> {
  error.value = null
  message.value = null
  submitting.value = true
  try {
    message.value = await authApi.resendVerificationEmail()
  } catch (err) {
    error.value =
      err instanceof ApiError ? err.message : 'Không thể gửi lại email xác minh.'
  } finally {
    submitting.value = false
  }
}

async function refreshStatus(): Promise<void> {
  submitting.value = true
  try {
    const user = await auth.refreshUser()
    if (user?.is_email_verified) {
      await router.push({ name: 'home' })
    } else {
      message.value = 'Email vẫn chưa được xác minh. Vui lòng kiểm tra hộp thư.'
    }
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'Không thể tải trạng thái tài khoản.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <CCard class="p-4 shadow-sm border-0">
    <CCardBody class="d-flex flex-column gap-3">
      <h1 class="h5 text-center mb-0">Xác minh email</h1>
      <p class="small text-body-secondary mb-0 text-center">
        Chúng tôi đã gửi liên kết xác minh tới
        <strong>{{ auth.user?.email }}</strong
        >. Mở email và nhấn liên kết để kích hoạt tài khoản.
      </p>

      <AuthAlert v-if="error" :message="error" />
      <AuthAlert v-if="message" :message="message" color="success" />

      <div class="d-grid gap-2">
        <CButton color="primary" :disabled="submitting" @click="resend">
          {{ submitting ? 'Đang gửi…' : 'Gửi lại email xác minh' }}
        </CButton>
        <CButton color="secondary" variant="outline" :disabled="submitting" @click="refreshStatus">
          Tôi đã xác minh — kiểm tra lại
        </CButton>
        <CButton
          color="link"
          class="text-decoration-none"
          @click="async () => { await auth.logout(); await router.push({ name: 'login' }) }"
        >
          Đăng xuất
        </CButton>
      </div>

      <p class="text-center small mb-0">
        <RouterLink to="/">Về trang chủ</RouterLink>
      </p>
    </CCardBody>
  </CCard>
</template>
