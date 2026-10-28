<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { resolvePostAuthRoute } from '@/utils/navigation'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const status = computed(() => String(route.query.status ?? 'invalid'))
const syncing = ref(false)

const content = computed(() => {
  switch (status.value) {
    case 'success':
      return {
        title: 'Xác minh email thành công',
        body: 'Tài khoản của bạn đã được xác minh. Bạn có thể tiếp tục sử dụng BookStore AI.',
        color: 'success' as const,
      }
    case 'already':
      return {
        title: 'Email đã được xác minh',
        body: 'Tài khoản này đã xác minh email trước đó.',
        color: 'info' as const,
      }
    default:
      return {
        title: 'Liên kết không hợp lệ',
        body: 'Liên kết xác minh hết hạn hoặc không hợp lệ. Hãy đăng nhập và gửi lại email xác minh.',
        color: 'warning' as const,
      }
  }
})

onMounted(async () => {
  if (status.value !== 'success' && status.value !== 'already') {
    return
  }

  syncing.value = true
  try {
    await auth.refreshUser()
  } finally {
    syncing.value = false
  }
})

async function continueApp(): Promise<void> {
  if (auth.isAuthenticated && auth.user) {
    await router.replace(resolvePostAuthRoute(auth.user))
    return
  }

  await router.push({ name: 'login' })
}
</script>

<template>
  <CCard class="p-4 shadow-sm border-0">
    <CCardBody class="d-flex flex-column gap-3 text-center">
      <h1 class="h5 mb-0">{{ content.title }}</h1>
      <CAlert :color="content.color" class="text-start mb-0">
        {{ content.body }}
      </CAlert>
      <div class="d-grid gap-2">
        <CButton color="primary" :disabled="syncing" @click="continueApp">
          {{ syncing ? 'Đang đồng bộ…' : 'Tiếp tục' }}
        </CButton>
        <RouterLink v-if="!auth.isAuthenticated" to="/login" class="btn btn-outline-secondary">
          Đăng nhập
        </RouterLink>
        <RouterLink v-else-if="!auth.isVerified" to="/verify-email" class="btn btn-outline-secondary">
          Trang xác minh email
        </RouterLink>
      </div>
    </CCardBody>
  </CCard>
</template>
