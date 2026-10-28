<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AuthAlert from '@/components/AuthAlert.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { ApiError, firstFieldError } from '@/api/errors'
import { validateEmail, validatePassword } from '@/composables/usePasswordRules'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const profile = reactive({
  name: auth.user?.name ?? '',
  email: auth.user?.email ?? '',
})

watch(
  () => auth.user,
  (user) => {
    if (user) {
      profile.name = user.name
      profile.email = user.email
    }
  },
  { immediate: true },
)

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const profileErrors = reactive<Record<string, string | undefined>>({})
const passwordErrors = reactive<Record<string, string | undefined>>({})
const profileMessage = ref<string | null>(null)
const profileError = ref<string | null>(null)
const passwordMessage = ref<string | null>(null)
const passwordError = ref<string | null>(null)
const savingProfile = ref(false)
const savingPassword = ref(false)

function validateProfile(): boolean {
  profileErrors.name = !profile.name.trim()
    ? 'Vui lòng nhập họ tên.'
    : profile.name.length > 120
      ? 'Họ tên tối đa 120 ký tự.'
      : undefined
  profileErrors.email = validateEmail(profile.email)

  return !profileErrors.name && !profileErrors.email
}

async function saveProfile(): Promise<void> {
  profileMessage.value = null
  profileError.value = null
  if (!validateProfile()) {
    return
  }

  savingProfile.value = true
  try {
    const updated = await auth.updateProfile({
      name: profile.name.trim(),
      email: profile.email.trim(),
    })
    profileMessage.value = updated.is_email_verified
      ? 'Đã cập nhật hồ sơ.'
      : 'Đã cập nhật hồ sơ. Vui lòng xác minh email mới.'
    if (!updated.is_email_verified) {
      await router.push({ name: 'verify-email' })
    }
  } catch (error) {
    if (error instanceof ApiError) {
      profileError.value = error.message
      profileErrors.email = firstFieldError(error.errors, 'email') ?? profileErrors.email
      profileErrors.name = firstFieldError(error.errors, 'name') ?? profileErrors.name
    } else {
      profileError.value = 'Không thể cập nhật hồ sơ.'
    }
  } finally {
    savingProfile.value = false
  }
}

function validatePasswordForm(): boolean {
  passwordErrors.current_password = passwordForm.current_password
    ? undefined
    : 'Vui lòng nhập mật khẩu hiện tại.'
  passwordErrors.password = validatePassword(passwordForm.password)
  passwordErrors.password_confirmation =
    passwordForm.password_confirmation !== passwordForm.password
      ? 'Xác nhận mật khẩu không khớp.'
      : undefined

  return Object.values(passwordErrors).every((value) => !value)
}

async function savePassword(): Promise<void> {
  passwordMessage.value = null
  passwordError.value = null
  if (!validatePasswordForm()) {
    return
  }

  savingPassword.value = true
  try {
    passwordMessage.value = await auth.changePassword({ ...passwordForm })
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  } catch (error) {
    if (error instanceof ApiError) {
      passwordError.value = error.message
      passwordErrors.current_password =
        firstFieldError(error.errors, 'current_password') ?? passwordErrors.current_password
      passwordErrors.password =
        firstFieldError(error.errors, 'password') ?? passwordErrors.password
    } else {
      passwordError.value = 'Không thể đổi mật khẩu.'
    }
  } finally {
    savingPassword.value = false
  }
}
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <div>
      <h1 class="h4 mb-1">Tài khoản</h1>
      <p class="text-body-secondary small mb-0">Quản lý hồ sơ và bảo mật đăng nhập.</p>
    </div>

    <CCard class="shadow-sm border-0">
      <CCardHeader class="bg-white fw-semibold">Hồ sơ</CCardHeader>
      <CCardBody class="d-flex flex-column gap-3">
        <AuthAlert v-if="profileError" :message="profileError" />
        <AuthAlert v-if="profileMessage" :message="profileMessage" color="success" />

        <CForm class="row gy-3" @submit.prevent="saveProfile">
          <CCol :md="6">
            <CFormLabel for="profile-name">Họ tên</CFormLabel>
            <CFormInput
              id="profile-name"
              v-model="profile.name"
              autocomplete="name"
              :invalid="Boolean(profileErrors.name)"
            />
            <CFormFeedback v-if="profileErrors.name" invalid>{{ profileErrors.name }}</CFormFeedback>
          </CCol>
          <CCol :md="6">
            <CFormLabel for="profile-email">Email</CFormLabel>
            <CFormInput
              id="profile-email"
              v-model="profile.email"
              type="email"
              autocomplete="email"
              :invalid="Boolean(profileErrors.email)"
            />
            <CFormFeedback v-if="profileErrors.email" invalid>{{ profileErrors.email }}</CFormFeedback>
          </CCol>
          <CCol :xs="12">
            <CButton type="submit" color="primary" :disabled="savingProfile">
              {{ savingProfile ? 'Đang lưu…' : 'Lưu hồ sơ' }}
            </CButton>
          </CCol>
        </CForm>
      </CCardBody>
    </CCard>

    <CCard class="shadow-sm border-0">
      <CCardHeader class="bg-white fw-semibold">Đổi mật khẩu</CCardHeader>
      <CCardBody class="d-flex flex-column gap-3">
        <p class="small text-body-secondary mb-0">
          Sau khi đổi mật khẩu, các phiên đăng nhập khác sẽ bị đăng xuất.
        </p>
        <AuthAlert v-if="passwordError" :message="passwordError" />
        <AuthAlert v-if="passwordMessage" :message="passwordMessage" color="success" />

        <CForm class="row gy-3" @submit.prevent="savePassword">
          <CCol :xs="12">
            <PasswordInput
              id="current-password"
              v-model="passwordForm.current_password"
              label="Mật khẩu hiện tại"
              autocomplete="current-password"
              :invalid="Boolean(passwordErrors.current_password)"
              :feedback="passwordErrors.current_password"
            />
          </CCol>
          <CCol :md="6">
            <PasswordInput
              id="new-password"
              v-model="passwordForm.password"
              label="Mật khẩu mới"
              autocomplete="new-password"
              :invalid="Boolean(passwordErrors.password)"
              :feedback="passwordErrors.password"
            />
          </CCol>
          <CCol :md="6">
            <PasswordInput
              id="new-password-confirm"
              v-model="passwordForm.password_confirmation"
              label="Xác nhận mật khẩu mới"
              autocomplete="new-password"
              :invalid="Boolean(passwordErrors.password_confirmation)"
              :feedback="passwordErrors.password_confirmation"
            />
          </CCol>
          <CCol :xs="12">
            <CButton type="submit" color="primary" :disabled="savingPassword">
              {{ savingPassword ? 'Đang lưu…' : 'Cập nhật mật khẩu' }}
            </CButton>
          </CCol>
        </CForm>
      </CCardBody>
    </CCard>
  </div>
</template>
