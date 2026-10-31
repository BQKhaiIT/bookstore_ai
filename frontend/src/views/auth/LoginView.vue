<script setup lang="ts">
import CustomerAuthAlert from '@/components/customer/auth/CustomerAuthAlert.vue'
import CustomerAuthButton from '@/components/customer/auth/CustomerAuthButton.vue'
import CustomerAuthCard from '@/components/customer/auth/CustomerAuthCard.vue'
import CustomerAuthField from '@/components/customer/auth/CustomerAuthField.vue'
import CustomerAuthPasswordField from '@/components/customer/auth/CustomerAuthPasswordField.vue'
import { ApiError, firstFieldError } from '@/api/errors'
import { authFormErrorMessage } from '@/composables/useAuthFormMessage'
import { validateEmail } from '@/composables/usePasswordRules'
import { useAuthStore } from '@/stores/auth'
import { resolvePostAuthRoute } from '@/utils/navigation'
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = reactive({
  email: '',
  password: '',
  remember: false,
})

const fieldErrors = reactive<Record<string, string | undefined>>({})
const formError = ref<string | null>(
  route.query.blocked ? 'Tài khoản của bạn đã bị khóa.' : null,
)
const submitting = ref(false)

function validate(): boolean {
  fieldErrors.email = validateEmail(form.email)
  fieldErrors.password = form.password ? undefined : 'Vui lòng nhập mật khẩu.'

  return !fieldErrors.email && !fieldErrors.password
}

async function onSubmit(): Promise<void> {
  formError.value = null
  if (!validate()) {
    return
  }

  submitting.value = true
  try {
    await auth.login({
      email: form.email.trim(),
      password: form.password,
      remember: form.remember,
    })

    if (!auth.user) {
      return
    }
    await router.replace(resolvePostAuthRoute(auth.user, route.query.redirect))
  } catch (error) {
    if (error instanceof ApiError && error.code === 'ACCOUNT_BLOCKED') {
      formError.value = 'Tài khoản của bạn đã bị khóa.'
    } else {
      formError.value = authFormErrorMessage(error, 'Không thể đăng nhập. Vui lòng thử lại.')
    }
    fieldErrors.email = error instanceof ApiError ? firstFieldError(error.errors, 'email') : undefined
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <CustomerAuthCard title="Đăng nhập" subtitle="Chào mừng trở lại — tiếp tục hành trình đọc của bạn.">
    <CustomerAuthAlert v-if="formError" :message="formError" />

    <form class="ca-form" @submit.prevent="onSubmit">
      <CustomerAuthField
        id="email"
        v-model="form.email"
        label="Email"
        type="email"
        autocomplete="email"
        :invalid="Boolean(fieldErrors.email)"
        :error="fieldErrors.email"
      />

      <div>
        <div class="ca-link-row">
          <RouterLink to="/forgot-password">Quên mật khẩu?</RouterLink>
        </div>
        <CustomerAuthPasswordField
          id="password"
          v-model="form.password"
          label="Mật khẩu"
          autocomplete="current-password"
          :invalid="Boolean(fieldErrors.password)"
          :error="fieldErrors.password"
        />
      </div>

      <label class="ca-check">
        <input v-model="form.remember" type="checkbox" />
        <span>Ghi nhớ đăng nhập trên thiết bị này</span>
      </label>

      <CustomerAuthButton type="submit" :loading="submitting">
        {{ submitting ? 'Đang xử lý…' : 'Đăng nhập' }}
      </CustomerAuthButton>
    </form>

    <p class="ca-footer">
      Chưa có tài khoản?
      <RouterLink to="/register">Đăng ký</RouterLink>
    </p>
  </CustomerAuthCard>
</template>
