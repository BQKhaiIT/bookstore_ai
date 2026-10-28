<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthAlert from '@/components/AuthAlert.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { activateAdminAccount } from '@/api/auth'
import { ApiError } from '@/api/errors'
import { validatePassword } from '@/composables/usePasswordRules'
import { parseActivationQuery } from '@/utils/activationParams'

type ActivationPhase = 'invalid' | 'ready' | 'loading' | 'success' | 'expired' | 'superseded'

const route = useRoute()
const router = useRouter()

const linkParams = parseActivationQuery(route.query)

const form = reactive({
  email: linkParams.email,
  token: linkParams.token,
  invitationId: linkParams.invitationId,
  password: '',
  password_confirmation: '',
})

const fieldErrors = reactive<Record<string, string | undefined>>({})
const formError = ref<string | null>(null)
const success = ref<string | null>(null)
const phase = ref<ActivationPhase>(
  form.email && form.token.length >= 32 ? 'ready' : 'invalid',
)
const submitLocked = ref(false)

const isFormDisabled = computed(
  () =>
    phase.value === 'invalid' ||
    phase.value === 'loading' ||
    phase.value === 'success' ||
    phase.value === 'superseded' ||
    submitLocked.value,
)

function mapActivationError(error: unknown): void {
  if (error instanceof ApiError) {
    if (error.status === 419) {
      phase.value = 'ready'
      formError.value = 'Phiên CSRF hết hạn. Tải lại trang và thử lại.'
      return
    }

    if (error.status === 0) {
      phase.value = 'ready'
      formError.value = 'Không kết nối được máy chủ (CORS hoặc mạng).'
      return
    }

    if (error.status >= 500) {
      phase.value = 'ready'
      formError.value = 'Máy chủ đang gặp sự cố. Thử lại sau.'
      return
    }

    switch (error.code) {
      case 'ACTIVATION_EXPIRED':
        phase.value = 'expired'
        formError.value = error.message
        return
      case 'ACTIVATION_SUPERSEDED':
        phase.value = 'superseded'
        formError.value = error.message
        return
      case 'ACTIVATION_USED':
        phase.value = 'ready'
        formError.value = error.message
        return
      case 'ACTIVATION_INVALID':
        phase.value = 'ready'
        formError.value = error.message
        return
    }

    if (error.status === 422) {
      phase.value = 'ready'
      formError.value = error.message
      return
    }
  }

  phase.value = 'ready'
  formError.value = 'Không thể kích hoạt tài khoản.'
}

function validate(): boolean {
  fieldErrors.email = form.email ? undefined : 'Thiếu email kích hoạt.'
  fieldErrors.token =
    form.token.length >= 32 ? undefined : 'Liên kết kích hoạt không hợp lệ.'
  fieldErrors.password = validatePassword(form.password)
  fieldErrors.password_confirmation =
    form.password_confirmation !== form.password ? 'Xác nhận mật khẩu không khớp.' : undefined

  return Object.values(fieldErrors).every((v) => !v)
}

async function onSubmit(): Promise<void> {
  if (submitLocked.value || phase.value === 'loading') {
    return
  }

  formError.value = null
  success.value = null
  if (!validate()) {
    return
  }

  submitLocked.value = true
  phase.value = 'loading'
  try {
    success.value = await activateAdminAccount({
      email: form.email.trim(),
      token: form.token.trim(),
      invitation: form.invitationId ?? undefined,
      password: form.password,
      password_confirmation: form.password_confirmation,
    })
    phase.value = 'success'
    await router.replace({ name: 'admin-login', query: { activated: '1' } })
  } catch (error) {
    mapActivationError(error)
  } finally {
    submitLocked.value = false
  }
}
</script>

<template>
  <div class="d-flex flex-column gap-3 mb-3 text-center text-white">
    <div class="fs-3 fw-semibold">Kích hoạt quản trị</div>
    <p class="mb-0 small opacity-75">Đặt mật khẩu để hoàn tất kích hoạt tài khoản</p>
  </div>

  <CCard class="shadow border-0">
    <CCardBody class="d-flex flex-column gap-3 p-4">
      <AuthAlert
        v-if="phase === 'invalid'"
        message="Liên kết kích hoạt không hợp lệ. Mở lại email mời hoặc yêu cầu gửi lại."
      />
      <AuthAlert
        v-else-if="phase === 'expired'"
        :message="formError ?? 'Liên kết kích hoạt đã hết hạn.'"
      />
      <AuthAlert
        v-else-if="phase === 'superseded'"
        :message="formError ?? 'Liên kết không còn hiệu lực. Dùng email kích hoạt mới nhất.'"
      />
      <AuthAlert v-else-if="formError" :message="formError" />
      <AuthAlert v-else-if="success" :message="success" color="success" />

      <CForm v-if="phase !== 'invalid' && phase !== 'superseded'" class="row gy-3" @submit.prevent="onSubmit">
        <CCol :xs="12">
          <CFormLabel for="activate-email">Email</CFormLabel>
          <CFormInput id="activate-email" v-model="form.email" type="email" readonly />
        </CCol>
        <CCol :xs="12">
          <PasswordInput
            id="activate-password"
            v-model="form.password"
            label="Mật khẩu mới"
            autocomplete="new-password"
            :invalid="Boolean(fieldErrors.password)"
            :feedback="fieldErrors.password"
          />
        </CCol>
        <CCol :xs="12">
          <PasswordInput
            id="activate-password-confirm"
            v-model="form.password_confirmation"
            label="Xác nhận mật khẩu"
            autocomplete="new-password"
            :invalid="Boolean(fieldErrors.password_confirmation)"
            :feedback="fieldErrors.password_confirmation"
          />
        </CCol>
        <CCol :xs="12">
          <CButton color="primary" type="submit" class="w-100" :disabled="isFormDisabled">
            {{
              phase === 'loading'
                ? 'Đang xử lý…'
                : phase === 'success'
                  ? 'Đã kích hoạt'
                  : 'Kích hoạt tài khoản'
            }}
          </CButton>
        </CCol>
      </CForm>

      <p class="text-center small text-body-secondary mb-0">
        <RouterLink to="/admin/login">Quay lại đăng nhập quản trị</RouterLink>
      </p>
    </CCardBody>
  </CCard>
</template>
