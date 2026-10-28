<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthAlert from '@/components/AuthAlert.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import * as authApi from '@/api/auth'
import { ApiError, firstFieldError } from '@/api/errors'
import { validatePassword } from '@/composables/usePasswordRules'

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
      formError.value = error.message
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
  <CCard class="p-4 shadow-sm border-0">
    <CCardBody class="d-flex flex-column gap-3">
      <h1 class="h5 text-center mb-0">Đặt lại mật khẩu</h1>

      <AuthAlert v-if="missingParams" message="Thiếu token hoặc email trong liên kết." />
      <AuthAlert v-else-if="formError" :message="formError" />
      <AuthAlert v-if="success" :message="success" color="success" />

      <CForm v-if="!missingParams" class="row gy-3" @submit.prevent="onSubmit">
        <CCol :xs="12">
          <CFormLabel>Email</CFormLabel>
          <CFormInput :model-value="email" readonly disabled />
        </CCol>
        <CCol :xs="12">
          <PasswordInput
            id="password"
            v-model="form.password"
            label="Mật khẩu mới"
            autocomplete="new-password"
            :invalid="Boolean(fieldErrors.password)"
            :feedback="fieldErrors.password"
          />
        </CCol>
        <CCol :xs="12">
          <PasswordInput
            id="password_confirmation"
            v-model="form.password_confirmation"
            label="Xác nhận mật khẩu mới"
            autocomplete="new-password"
            :invalid="Boolean(fieldErrors.password_confirmation)"
            :feedback="fieldErrors.password_confirmation"
          />
        </CCol>
        <CCol :xs="12">
          <CButton color="primary" type="submit" class="w-100" :disabled="submitting">
            {{ submitting ? 'Đang lưu…' : 'Cập nhật mật khẩu' }}
          </CButton>
        </CCol>
      </CForm>

      <p class="text-center small mb-0">
        <RouterLink to="/login">Quay lại đăng nhập</RouterLink>
      </p>
    </CCardBody>
  </CCard>
</template>
