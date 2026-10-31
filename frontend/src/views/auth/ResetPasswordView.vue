<script setup lang="ts">
import * as authApi from '@/api/auth'
import { ApiError, firstFieldError } from '@/api/errors'
import CustomerAuthAlert from '@/components/customer/auth/CustomerAuthAlert.vue'
import CustomerAuthButton from '@/components/customer/auth/CustomerAuthButton.vue'
import CustomerAuthCard from '@/components/customer/auth/CustomerAuthCard.vue'
import CustomerAuthPasswordField from '@/components/customer/auth/CustomerAuthPasswordField.vue'
import { authFormErrorMessage } from '@/composables/useAuthFormMessage'
import { validatePassword } from '@/composables/usePasswordRules'
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const token = computed(() => String(route.query.token ?? ''))
const email = computed(() => String(route.query.email ?? ''))

const form = reactive({
  password: '',
  password_confirmation: '',
})

const fieldErrors = reactive<Record<string, string | undefined>>({})
const formError = ref<string | null>(null)
const success = ref<string | null>(null)
const submitting = ref(false)

const missingParams = computed(() => !token.value || !email.value)

function validate(): boolean {
  fieldErrors.password = validatePassword(form.password)
  fieldErrors.password_confirmation =
    form.password_confirmation !== form.password ? 'Xác nhận mật khẩu không khớp.' : undefined

  return Object.values(fieldErrors).every((value) => !value)
}

async function onSubmit(): Promise<void> {
  if (missingParams.value) {
    formError.value = 'Liên kết đặt lại mật khẩu không hợp lệ.'
    return
  }

  formError.value = null
  if (!validate()) {
    return
  }

  submitting.value = true
  try {
    success.value = await authApi.resetPassword({
      email: email.value,
      token: token.value,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    setTimeout(() => {
      void router.push({ name: 'login' })
    }, 2000)
  } catch (error) {
    if (error instanceof ApiError) {
      formError.value = authFormErrorMessage(error, error.message)
      fieldErrors.password = firstFieldError(error.errors, 'password')
    } else {
      formError.value = 'Không thể đặt lại mật khẩu.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <CustomerAuthCard title="Đặt lại mật khẩu" subtitle="Chọn mật khẩu mới cho tài khoản của bạn.">
    <CustomerAuthAlert v-if="missingParams" message="Thiếu token hoặc email trong liên kết." variant="warning" />
    <CustomerAuthAlert v-else-if="formError" :message="formError" />
    <CustomerAuthAlert v-if="success" :message="success" variant="success" />

    <form v-if="!missingParams" class="ca-form" @submit.prevent="onSubmit">
      <div class="ca-field">
        <span class="ca-field__label">Email</span>
        <p class="ca-field__control" aria-readonly="true">{{ email }}</p>
      </div>

      <CustomerAuthPasswordField
        id="password"
        v-model="form.password"
        label="Mật khẩu mới"
        autocomplete="new-password"
        :invalid="Boolean(fieldErrors.password)"
        :error="fieldErrors.password"
      />

      <CustomerAuthPasswordField
        id="password_confirmation"
        v-model="form.password_confirmation"
        label="Xác nhận mật khẩu mới"
        autocomplete="new-password"
        :invalid="Boolean(fieldErrors.password_confirmation)"
        :error="fieldErrors.password_confirmation"
      />

      <CustomerAuthButton type="submit" :loading="submitting">
        {{ submitting ? 'Đang lưu…' : 'Cập nhật mật khẩu' }}
      </CustomerAuthButton>
    </form>

    <p class="ca-footer">
      <RouterLink to="/login">Quay lại đăng nhập</RouterLink>
    </p>
  </CustomerAuthCard>
</template>
