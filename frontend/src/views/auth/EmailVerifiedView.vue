<script setup lang="ts">
import CustomerAuthAlert from '@/components/customer/auth/CustomerAuthAlert.vue'
import CustomerAuthButton from '@/components/customer/auth/CustomerAuthButton.vue'
import CustomerAuthCard from '@/components/customer/auth/CustomerAuthCard.vue'
import { useAuthStore } from '@/stores/auth'
import { resolvePostAuthRoute } from '@/utils/navigation'
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

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
        body: 'Tài khoản của bạn đã được xác minh. Bạn có thể tiếp tục sử dụng BookStore.',
        variant: 'success' as const,
      }
    case 'already':
      return {
        title: 'Email đã được xác minh',
        body: 'Tài khoản này đã xác minh email trước đó.',
        variant: 'info' as const,
      }
    default:
      return {
        title: 'Liên kết không hợp lệ',
        body: 'Liên kết xác minh hết hạn hoặc không hợp lệ. Hãy đăng nhập và gửi lại email xác minh.',
        variant: 'warning' as const,
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
  <CustomerAuthCard :title="content.title">
    <CustomerAuthAlert :message="content.body" :variant="content.variant" />

    <div class="ca-stack">
      <CustomerAuthButton :loading="syncing" @click="continueApp">
        {{ syncing ? 'Đang đồng bộ…' : 'Tiếp tục' }}
      </CustomerAuthButton>
      <RouterLink
        v-if="!auth.isAuthenticated"
        to="/login"
        class="ca-btn ca-btn--secondary text-decoration-none"
      >
        Đăng nhập
      </RouterLink>
      <RouterLink
        v-else-if="!auth.isVerified"
        to="/verify-email"
        class="ca-btn ca-btn--secondary text-decoration-none"
      >
        Trang xác minh email
      </RouterLink>
    </div>
  </CustomerAuthCard>
</template>
