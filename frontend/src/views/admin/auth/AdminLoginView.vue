<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthAlert from '@/components/AuthAlert.vue'
import PasswordInput from '@/components/PasswordInput.vue'
import { ApiError, firstFieldError } from '@/api/errors'
import { validateEmail } from '@/composables/usePasswordRules'
import { useAuthStore } from '@/stores/auth'
import { resolvePostAdminAuthRoute } from '@/utils/navigation'

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

const customerSessionActive = computed(
  () => auth.isAuthenticated && !auth.isAdmin && !auth.isBlocked,
)

function validate(): boolean {
  fieldErrors.email = validateEmail(form.email)
  fieldErrors.password = form.password ? undefined : 'Vui lòng nhập mật khẩu.'

  return !fieldErrors.email && !fieldErrors.password
}

async function rejectNonAdminSession(message: string): Promise<void> {
  formError.value = message
  await auth.logout()
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

    const user = auth.user
    if (!user) {
      return
    }

    if (user.status === 'blocked') {
      await rejectNonAdminSession('Tài khoản của bạn đã bị khóa.')
      return
    }

    if (user.role !== 'admin' && user.role !== 'super_admin') {
      await rejectNonAdminSession(
        'Tài khoản không có quyền truy cập khu vực quản trị. Vui lòng đăng nhập tại cửa hàng.',
      )
      return
    }

    if (!user.is_email_verified) {
      await router.replace({
        name: 'verify-email',
        query: { redirect: route.query.redirect ?? '/admin/dashboard' },
      })
      return
    }

    await router.replace(resolvePostAdminAuthRoute(user, route.query.redirect))
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
  <div class="d-flex flex-column gap-3 mb-3 text-center text-white">
    <div class="fs-3 fw-semibold">BookStore Admin</div>
    <p class="mb-0 small opacity-75">Đăng nhập quản trị — chỉ tài khoản được cấp quyền admin</p>
  </div>

  <CCard class="shadow border-0">
    <CCardBody class="d-flex flex-column gap-3 p-4">
      <h1 class="h5 text-center mb-0">Đăng nhập quản trị</h1>

      <AuthAlert
        v-if="customerSessionActive"
        color="warning"
        message="Bạn đang đăng nhập tài khoản khách hàng. Khu vực quản trị yêu cầu tài khoản admin riêng."
      />

      <AuthAlert v-if="formError" :message="formError" />

      <CForm class="row gy-3" @submit.prevent="onSubmit">
        <CCol :xs="12">
          <CFormLabel for="admin-email">Email</CFormLabel>
          <CFormInput
            id="admin-email"
            v-model="form.email"
            type="email"
            autocomplete="username"
            :invalid="Boolean(fieldErrors.email)"
          />
          <CFormFeedback v-if="fieldErrors.email" invalid>{{ fieldErrors.email }}</CFormFeedback>
        </CCol>

        <CCol :xs="12">
          <PasswordInput
            id="admin-password"
            v-model="form.password"
            label="Mật khẩu"
            autocomplete="current-password"
            :invalid="Boolean(fieldErrors.password)"
            :feedback="fieldErrors.password"
          />
        </CCol>

        <CCol :xs="12">
          <CFormCheck
            id="admin-remember"
            v-model="form.remember"
            label="Ghi nhớ đăng nhập trên thiết bị này"
          />
        </CCol>

        <CCol :xs="12">
          <CButton color="primary" type="submit" class="w-100" :disabled="submitting">
            {{ submitting ? 'Đang xử lý…' : 'Đăng nhập quản trị' }}
          </CButton>
        </CCol>
      </CForm>

      <p class="text-center small text-body-secondary mb-0">
        <RouterLink to="/">← Về cửa hàng</RouterLink>
        <span class="mx-2">·</span>
        <RouterLink to="/login">Đăng nhập khách hàng</RouterLink>
      </p>
    </CCardBody>
  </CCard>
</template>
