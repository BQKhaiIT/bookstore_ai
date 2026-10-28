<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="text-center py-5">
    <h1 class="h4 mb-2">Không đủ quyền truy cập</h1>
    <p class="text-body-secondary mb-4">
      Bạn không có quyền xem trang này. Liên hệ quản trị viên nếu bạn cho rằng đây là nhầm lẫn.
    </p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
      <RouterLink to="/" class="btn btn-primary">Về trang chủ</RouterLink>
      <RouterLink v-if="auth.isAdmin && auth.isVerified" to="/admin/dashboard" class="btn btn-outline-secondary">
        Bảng điều khiển quản trị
      </RouterLink>
    </div>
  </div>
</template>
