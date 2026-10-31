<script setup lang="ts">
import * as authApi from '@/api/auth'
import CustomerAuthAlert from '@/components/customer/auth/CustomerAuthAlert.vue'
import CustomerAuthButton from '@/components/customer/auth/CustomerAuthButton.vue'
import CustomerAuthCard from '@/components/customer/auth/CustomerAuthCard.vue'
import { authFormErrorMessage } from '@/composables/useAuthFormMessage'
import { useAuthStore } from '@/stores/auth'
import { resolvePostAuthRoute } from '@/utils/navigation'
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

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
    error.value = authFormErrorMessage(err, 'Không thể gửi lại email xác minh.')
  } finally {
    submitting.value = false
  }
}

async function refreshStatus(): Promise<void> {
  submitting.value = true
  try {
    const user = await auth.refreshUser()
    if (user?.is_email_verified) {
      await router.push(resolvePostAuthRoute(user))
    } else {
      message.value = 'Email vẫn chưa được xác minh. Vui lòng kiểm tra hộp thư.'
    }
  } catch (err) {
    error.value = authFormErrorMessage(err, 'Không thể tải trạng thái tài khoản.')
  } finally {
    submitting.value = false
  }
}

async function logout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <CustomerAuthCard
    title="Xác minh email"
    :subtitle="`Liên kết đã gửi tới ${auth.user?.email ?? 'email của bạn'}. Mở hộp thư và xác minh để tiếp tục.`"
  >

    <CustomerAuthAlert v-if="error" :message="error" />
    <CustomerAuthAlert v-if="message" :message="message" variant="success" />

    <div class="ca-stack">
      <CustomerAuthButton :loading="submitting" @click="resend">Gửi lại email xác minh</CustomerAuthButton>
      <CustomerAuthButton variant="secondary" :loading="submitting" @click="refreshStatus">
        Tôi đã xác minh — kiểm tra lại
      </CustomerAuthButton>
      <CustomerAuthButton variant="ghost" @click="logout">Đăng xuất</CustomerAuthButton>
    </div>

    <p class="ca-footer">
      <RouterLink to="/">Về trang chủ</RouterLink>
    </p>
  </CustomerAuthCard>
</template>
