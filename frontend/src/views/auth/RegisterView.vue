<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import AuthAlert from '@/components/AuthAlert.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { ApiError, firstFieldError } from '@/api/errors'
import { validateEmail, validatePassword } from '@/composables/usePasswordRules'
import { useAuthStore } from '@/stores/auth'

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
      formError.value = error.message
      fieldErrors.email = firstFieldError(error.errors, 'email') ?? fieldErrors.email
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
  <CCard class="p-4 shadow-sm border-0">
    <CCardBody class="d-flex flex-column gap-3">
      <h1 class="h5 text-center mb-0">Tạo tài khoản</h1>

      <AuthAlert v-if="formError" :message="formError" />

      <CForm class="row gy-3" @submit.prevent="onSubmit">
        <CCol :xs="12">
          <CFormLabel for="name">Họ tên</CFormLabel>
          <CFormInput
            id="name"
            v-model="form.name"
            autocomplete="name"
            :invalid="Boolean(fieldErrors.name)"
          />
          <CFormFeedback v-if="fieldErrors.name" invalid>{{ fieldErrors.name }}</CFormFeedback>
        </CCol>

        <CCol :xs="12">
          <CFormLabel for="email">Email</CFormLabel>
          <CFormInput
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            :invalid="Boolean(fieldErrors.email)"
          />
          <CFormFeedback v-if="fieldErrors.email" invalid>{{ fieldErrors.email }}</CFormFeedback>
        </CCol>

        <CCol :xs="12">
          <PasswordInput
            id="password"
            v-model="form.password"
            label="Mật khẩu"
            autocomplete="new-password"
            :invalid="Boolean(fieldErrors.password)"
            :feedback="fieldErrors.password"
          />
        </CCol>

        <CCol :xs="12">
          <PasswordInput
            id="password_confirmation"
            v-model="form.password_confirmation"
            label="Xác nhận mật khẩu"
            autocomplete="new-password"
            :invalid="Boolean(fieldErrors.password_confirmation)"
            :feedback="fieldErrors.password_confirmation"
          />
        </CCol>

        <CCol :xs="12">
          <CButton color="primary" type="submit" class="w-100" :disabled="submitting">
            {{ submitting ? 'Đang xử lý…' : 'Đăng ký' }}
          </CButton>
        </CCol>
      </CForm>

      <p class="text-center small mb-0">
        Đã có tài khoản?
        <RouterLink to="/login">Đăng nhập</RouterLink>
      </p>
    </CCardBody>
  </CCard>
</template>
