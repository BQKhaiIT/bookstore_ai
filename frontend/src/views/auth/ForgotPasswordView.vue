<script setup lang="ts">
import { reactive, ref } from 'vue'
import { RouterLink } from 'vue-router'
import AuthAlert from '@/components/AuthAlert.vue'
import * as authApi from '@/api/auth'
import { ApiError } from '@/api/errors'
import { validateEmail } from '@/composables/usePasswordRules'

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
    formError.value =
      error instanceof ApiError ? error.message : 'Không thể gửi yêu cầu. Vui lòng thử lại.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <CCard class="p-4 shadow-sm border-0">
    <CCardBody class="d-flex flex-column gap-3">
      <h1 class="h5 text-center mb-0">Quên mật khẩu</h1>
      <p class="small text-body-secondary mb-0 text-center">
        Nhập email để nhận liên kết đặt lại mật khẩu.
      </p>

      <AuthAlert v-if="formError" :message="formError" />
      <AuthAlert v-if="success" :message="success" color="success" />

      <CForm class="row gy-3" @submit.prevent="onSubmit">
        <CCol :xs="12">
          <CFormLabel for="email">Email</CFormLabel>
          <CFormInput
            id="email"
            v-model="form.email"
            type="email"
            autocomplete="email"
            :invalid="Boolean(fieldError)"
          />
          <CFormFeedback v-if="fieldError" invalid>{{ fieldError }}</CFormFeedback>
        </CCol>
        <CCol :xs="12">
          <CButton color="primary" type="submit" class="w-100" :disabled="submitting">
            {{ submitting ? 'Đang gửi…' : 'Gửi email đặt lại' }}
          </CButton>
        </CCol>
      </CForm>

      <p class="text-center small mb-0">
        <RouterLink to="/login">Quay lại đăng nhập</RouterLink>
      </p>
    </CCardBody>
  </CCard>
</template>
