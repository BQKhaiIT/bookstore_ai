<script setup lang="ts">
import * as authApi from '@/api/auth'
import CustomerAuthAlert from '@/components/customer/auth/CustomerAuthAlert.vue'
import CustomerAuthButton from '@/components/customer/auth/CustomerAuthButton.vue'
import CustomerAuthCard from '@/components/customer/auth/CustomerAuthCard.vue'
import CustomerAuthField from '@/components/customer/auth/CustomerAuthField.vue'
import { authFormErrorMessage } from '@/composables/useAuthFormMessage'
import { validateEmail } from '@/composables/usePasswordRules'
import { reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'

const form = reactive({ email: '' })
const fieldError = ref<string | undefined>()
const formError = ref<string | null>(null)
const success = ref<string | null>(null)
const submitting = ref(false)

async function onSubmit(): Promise<void> {
  formError.value = null
  success.value = null
  fieldError.value = validateEmail(form.email)
  if (fieldError.value) {
    return
  }

  submitting.value = true
  try {
    success.value = await authApi.forgotPassword(form.email.trim())
  } catch (error) {
    formError.value = authFormErrorMessage(error, 'Không thể gửi yêu cầu. Vui lòng thử lại.')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <CustomerAuthCard
    title="Quên mật khẩu"
    subtitle="Nhập email để nhận liên kết đặt lại mật khẩu an toàn."
  >
    <CustomerAuthAlert v-if="formError" :message="formError" />
    <CustomerAuthAlert v-if="success" :message="success" variant="success" />

    <form class="ca-form" @submit.prevent="onSubmit">
      <CustomerAuthField
        id="email"
        v-model="form.email"
        label="Email"
        type="email"
        autocomplete="email"
        :invalid="Boolean(fieldError)"
        :error="fieldError"
      />

      <CustomerAuthButton type="submit" :loading="submitting">
        {{ submitting ? 'Đang gửi…' : 'Gửi email đặt lại' }}
      </CustomerAuthButton>
    </form>

    <p class="ca-footer">
      <RouterLink to="/login">Quay lại đăng nhập</RouterLink>
    </p>
  </CustomerAuthCard>
</template>
