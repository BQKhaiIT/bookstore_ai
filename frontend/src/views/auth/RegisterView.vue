<script setup lang="ts">
import CustomerAuthAlert from '@/components/customer/auth/CustomerAuthAlert.vue'
import CustomerAuthButton from '@/components/customer/auth/CustomerAuthButton.vue'
import CustomerAuthCard from '@/components/customer/auth/CustomerAuthCard.vue'
import CustomerAuthField from '@/components/customer/auth/CustomerAuthField.vue'
import CustomerAuthPasswordField from '@/components/customer/auth/CustomerAuthPasswordField.vue'
import { ApiError, firstFieldError } from '@/api/errors'
import { authFormErrorMessage } from '@/composables/useAuthFormMessage'
import { validateEmail, validatePassword } from '@/composables/usePasswordRules'
import { useAuthStore } from '@/stores/auth'
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const fieldErrors = reactive<Record<string, string | undefined>>({})
const formError = ref<string | null>(null)
const submitting = ref(false)

function validate(): boolean {
  fieldErrors.name = !form.name.trim()
    ? 'Vui lòng nhập họ tên.'
    : form.name.length > 120
      ? 'Họ tên tối đa 120 ký tự.'
      : undefined
  fieldErrors.email = validateEmail(form.email)
  fieldErrors.password = validatePassword(form.password)
  fieldErrors.password_confirmation =
    form.password_confirmation !== form.password ? 'Xác nhận mật khẩu không khớp.' : undefined

  return Object.values(fieldErrors).every((value) => !value)
}

async function onSubmit(): Promise<void> {
  formError.value = null
  if (!validate()) {
    return
  }

  submitting.value = true
  try {
    await auth.register({
      name: form.name.trim(),
      email: form.email.trim(),
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    await router.push({ name: 'verify-email' })
  } catch (error) {
    if (error instanceof ApiError) {
      formError.value = authFormErrorMessage(error, error.message)
      const emailErr = firstFieldError(error.errors, 'email')
      fieldErrors.email = emailErr ?? fieldErrors.email
      if (emailErr?.includes('đã được sử dụng')) {
        formError.value =
          'Email này đã có tài khoản (có thể do lần đăng ký trước). Hãy đăng nhập hoặc dùng Quên mật khẩu.'
      }
      fieldErrors.name = firstFieldError(error.errors, 'name') ?? fieldErrors.name
      fieldErrors.password = firstFieldError(error.errors, 'password') ?? fieldErrors.password
    } else {
      formError.value = 'Không thể đăng ký. Vui lòng thử lại.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <CustomerAuthCard title="Tạo tài khoản" subtitle="Tham gia BookStore — mua sách và theo dõi đơn hàng dễ dàng.">
    <CustomerAuthAlert v-if="formError" :message="formError" />

    <form class="ca-form" @submit.prevent="onSubmit">
      <CustomerAuthField
        id="name"
        v-model="form.name"
        label="Họ tên"
        autocomplete="name"
        :invalid="Boolean(fieldErrors.name)"
        :error="fieldErrors.name"
      />

      <CustomerAuthField
        id="email"
        v-model="form.email"
        label="Email"
        type="email"
        autocomplete="email"
        :invalid="Boolean(fieldErrors.email)"
        :error="fieldErrors.email"
      />

      <CustomerAuthPasswordField
        id="password"
        v-model="form.password"
        label="Mật khẩu"
        autocomplete="new-password"
        :invalid="Boolean(fieldErrors.password)"
        :error="fieldErrors.password"
      />

      <CustomerAuthPasswordField
        id="password_confirmation"
        v-model="form.password_confirmation"
        label="Xác nhận mật khẩu"
        autocomplete="new-password"
        :invalid="Boolean(fieldErrors.password_confirmation)"
        :error="fieldErrors.password_confirmation"
      />

      <CustomerAuthButton type="submit" :loading="submitting">
        {{ submitting ? 'Đang xử lý…' : 'Đăng ký' }}
      </CustomerAuthButton>
    </form>

    <p class="ca-footer">
      Đã có tài khoản?
      <RouterLink to="/login">Đăng nhập</RouterLink>
      ·
      <RouterLink to="/forgot-password">Quên mật khẩu</RouterLink>
    </p>
  </CustomerAuthCard>
</template>
