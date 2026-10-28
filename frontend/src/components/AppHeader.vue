<script setup lang="ts">
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

async function logout(): Promise<void> {
  await auth.logout()
  await router.replace({ name: 'login' })
}
</script>

<template>
  <header class="border-bottom bg-white sticky-top">
    <CContainer class="py-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
      <RouterLink to="/" class="auth-brand-title fs-5 text-decoration-none">
        BookStore AI
      </RouterLink>

      <nav class="d-flex flex-wrap align-items-center gap-3" aria-label="Điều hướng chính">
        <RouterLink to="/" class="text-decoration-none small">Trang chủ</RouterLink>
        <RouterLink to="/account" class="text-decoration-none small">Tài khoản</RouterLink>
        <RouterLink
          v-if="auth.isAdmin && auth.isVerified"
          to="/admin/dashboard"
          class="text-decoration-none small"
        >
          Quản trị
        </RouterLink>
        <span class="text-body-secondary small d-none d-sm-inline">{{ auth.user?.name }}</span>
        <CButton size="sm" color="danger" variant="outline" @click="logout">Đăng xuất</CButton>
      </nav>
    </CContainer>
  </header>
</template>
