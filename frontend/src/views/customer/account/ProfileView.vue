<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import { reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError, firstFieldError } from '@/api/errors'
import { validateEmail } from '@/composables/usePasswordRules'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const profile = reactive({ name: auth.user?.name ?? '', email: auth.user?.email ?? '' })
const errors = reactive<Record<string, string | undefined>>({})
const message = ref<string | null>(null)
const error = ref<string | null>(null)
const saving = ref(false)

watch(
  () => auth.user,
  (u) => {
    if (u) {
      profile.name = u.name
      profile.email = u.email
    }
  },
  { immediate: true },
)

async function save(): Promise<void> {
  errors.name = !profile.name.trim() ? 'Vui lòng nhập họ tên.' : undefined
  errors.email = validateEmail(profile.email)
  if (errors.name || errors.email) return

  saving.value = true
  message.value = null
  error.value = null
  try {
    const updated = await auth.updateProfile({ name: profile.name.trim(), email: profile.email.trim() })
    message.value = updated.is_email_verified ? 'Đã cập nhật.' : 'Cập nhật — vui lòng xác minh email mới.'
    if (!updated.is_email_verified) await router.push({ name: 'verify-email' })
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = e.message
      errors.email = firstFieldError(e.errors, 'email') ?? errors.email
    } else {
      error.value = 'Không cập nhật được.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="max-w-lg rounded-lg border border-border bg-card p-6">
    <h2 class="font-display text-xl font-semibold">Hồ sơ</h2>
    <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
    <p v-if="message" class="mt-3 text-sm text-accent">{{ message }}</p>
    <p class="mt-2 text-sm text-muted-foreground">
      Xác minh email: {{ auth.isVerified ? 'Đã xác minh' : 'Chưa xác minh' }}
    </p>
    <form class="mt-6 space-y-4" @submit.prevent="save">
      <div>
        <label class="mb-1 block text-sm font-medium" for="name">Họ tên</label>
        <Input id="name" v-model="profile.name" />
        <p v-if="errors.name" class="mt-1 text-xs text-destructive">{{ errors.name }}</p>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium" for="email">Email</label>
        <Input id="email" v-model="profile.email" type="email" />
        <p v-if="errors.email" class="mt-1 text-xs text-destructive">{{ errors.email }}</p>
      </div>
      <Button type="submit" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Lưu hồ sơ' }}</Button>
    </form>
  </section>
</template>
