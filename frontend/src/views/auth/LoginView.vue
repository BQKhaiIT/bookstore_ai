<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthAlert from '@/components/AuthAlert.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { ApiError, firstFieldError } from '@/api/errors'
import { validateEmail } from '@/composables/usePasswordRules'
import { useAuthStore } from '@/stores/auth'
import { resolvePostCustomerAuthRoute } from '@/utils/navigation'

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
    await router.replace(resolvePostCustomerAuthRoute(auth.user, route.query.redirect))
  } catch (error) {
    if (error instanceof ApiError) {
      formError.value = error.message
      fieldErrors.email = firstFieldError(error.errors, 'email')
      if (error.code === 'ACCOUNT_BLOCKED') {
        formError.value = 'Tài khoản của bạn đã bị khóa.'
      }
    } else {
      formError.value = 'Không thể đăng nhập. Vui lòng thử lại.'
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <CCard class="p-4 shadow-sm border-0">
    <CCardBody class="d-flex flex-column gap-3">
      <h1 class="h5 text-center mb-0">Đăng nhập</h1>

      <AuthAlert v-if="formError" :message="formError" />

      <CForm class="row gy-3" @submit.prevent="onSubmit">
        <CCol :xs="12">
          <CFormLabel for="email">Email</CFormLabel>
          <CFormInput
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            :invalid="Boolean(fieldErrors.email)"
            :aria-describedby="fieldErrors.email ? 'email-feedback' : undefined"
          />
          <CFormFeedback v-if="fieldErrors.email" id="email-feedback" invalid>
            {{ fieldErrors.email }}
          </CFormFeedback>
        </CCol>

        <CCol :xs="12">
          <div class="d-flex justify-content-between align-items-center">
            <span />
            <RouterLink to="/forgot-password" class="small">Quên mật khẩu?</RouterLink>
          </div>
          <PasswordInput
            id="password"
            v-model="form.password"
            label="Mật khẩu"
            autocomplete="current-password"
            :invalid="Boolean(fieldErrors.password)"
            :feedback="fieldErrors.password"
          />
        </CCol>

        <CCol :xs="12">
          <CFormCheck
            id="remember"
            v-model="form.remember"
            label="Ghi nhớ đăng nhập trên thiết bị này"
          />
        </CCol>

        <CCol :xs="12">
          <CButton color="primary" type="submit" class="w-100" :disabled="submitting">
            {{ submitting ? 'Đang xử lý…' : 'Đăng nhập' }}
          </CButton>
        </CCol>
      </CForm>

      <p class="text-center small mb-0">
        Chưa có tài khoản?
        <RouterLink to="/register">Đăng ký</RouterLink>
      </p>
    </CCardBody>
  </CCard>
</template>
