<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import { validatePassword } from '@/composables/usePasswordRules'
import { ApiError, firstFieldError } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'
import { ShieldCheck } from '@lucide/vue'
import { computed, reactive, ref } from 'vue'

const auth = useAuthStore()
const form = reactive({ current_password: '', password: '', password_confirmation: '' })
const errors = reactive<Record<string, string | undefined>>({})
const message = ref<string | null>(null)
const error = ref<string | null>(null)
const saving = ref(false)

const showCurrent = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)

const passwordHints = [
  'Ít nhất 8 ký tự',
  'Có chữ hoa và chữ thường',
  'Có ít nhất một chữ số',
]

const emailDisplay = computed(() => auth.user?.email ?? '')

async function save(): Promise<void> {
  errors.current_password = form.current_password ? undefined : 'Nhập mật khẩu hiện tại.'
  errors.password = validatePassword(form.password)
  errors.password_confirmation =
    form.password_confirmation !== form.password ? 'Xác nhận không khớp.' : undefined
  if (Object.values(errors).some(Boolean)) return

  saving.value = true
  message.value = null
  error.value = null
  try {
    message.value = await auth.changePassword({ ...form })
    form.current_password = ''
    form.password = ''
    form.password_confirmation = ''
    showCurrent.value = false
    showNew.value = false
    showConfirm.value = false
  } catch (e) {
    if (e instanceof ApiError) {
      error.value = e.message
      errors.current_password = firstFieldError(e.errors, 'current_password') ?? errors.current_password
      errors.password = firstFieldError(e.errors, 'password') ?? errors.password
    } else {
      error.value = 'Không đổi được mật khẩu.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <section class="max-w-3xl">
    <header class="mb-8">
      <h2 class="font-display text-2xl font-semibold tracking-tight">Bảo mật</h2>
      <p class="mt-1 max-w-xl text-sm leading-relaxed text-muted-foreground">
        Cập nhật mật khẩu đăng nhập. Phiên hiện tại vẫn giữ sau khi đổi mật khẩu thành công.
      </p>
    </header>

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_260px]">
      <div class="rounded-xl border border-border bg-card p-6 shadow-sm">
        <div
          v-if="error"
          class="mb-6 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
          role="alert"
        >
          {{ error }}
        </div>
        <div
          v-if="message"
          class="mb-6 rounded-lg border border-accent/30 bg-accent/5 px-4 py-3 text-sm text-foreground"
          role="status"
        >
          {{ message }}
        </div>

        <form class="space-y-5" @submit.prevent="save">
          <div>
            <label class="mb-1.5 block text-sm font-medium" for="current-password">Mật khẩu hiện tại</label>
            <div class="relative">
              <Input
                id="current-password"
                v-model="form.current_password"
                :type="showCurrent ? 'text' : 'password'"
                autocomplete="current-password"
                class="pr-16"
                :aria-invalid="Boolean(errors.current_password)"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground hover:text-foreground"
                @click="showCurrent = !showCurrent"
              >
                {{ showCurrent ? 'Ẩn' : 'Hiện' }}
              </button>
            </div>
            <p v-if="errors.current_password" class="mt-1 text-xs text-destructive">{{ errors.current_password }}</p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium" for="new-password">Mật khẩu mới</label>
            <div class="relative">
              <Input
                id="new-password"
                v-model="form.password"
                :type="showNew ? 'text' : 'password'"
                autocomplete="new-password"
                class="pr-16"
                :aria-invalid="Boolean(errors.password)"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground hover:text-foreground"
                @click="showNew = !showNew"
              >
                {{ showNew ? 'Ẩn' : 'Hiện' }}
              </button>
            </div>
            <p v-if="errors.password" class="mt-1 text-xs text-destructive">{{ errors.password }}</p>
          </div>

          <div>
            <label class="mb-1.5 block text-sm font-medium" for="confirm-password">Xác nhận mật khẩu mới</label>
            <div class="relative">
              <Input
                id="confirm-password"
                v-model="form.password_confirmation"
                :type="showConfirm ? 'text' : 'password'"
                autocomplete="new-password"
                class="pr-16"
                :aria-invalid="Boolean(errors.password_confirmation)"
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-muted-foreground hover:text-foreground"
                @click="showConfirm = !showConfirm"
              >
                {{ showConfirm ? 'Ẩn' : 'Hiện' }}
              </button>
            </div>
            <p v-if="errors.password_confirmation" class="mt-1 text-xs text-destructive">
              {{ errors.password_confirmation }}
            </p>
          </div>

          <Button type="submit" :disabled="saving">{{ saving ? 'Đang cập nhật…' : 'Cập nhật mật khẩu' }}</Button>
        </form>
      </div>

      <aside class="space-y-4">
        <div class="rounded-xl border border-border bg-secondary/40 p-5">
          <div class="flex items-center gap-2 text-sm font-semibold">
            <ShieldCheck class="size-4 text-accent" />
            Tài khoản
          </div>
          <p class="mt-3 break-all text-sm text-muted-foreground">{{ emailDisplay }}</p>
        </div>
        <div class="rounded-xl border border-border bg-card p-5">
          <p class="text-sm font-semibold">Yêu cầu mật khẩu</p>
          <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
            <li v-for="hint in passwordHints" :key="hint" class="flex gap-2">
              <span class="text-accent" aria-hidden="true">•</span>
              <span>{{ hint }}</span>
            </li>
          </ul>
        </div>
      </aside>
    </div>
  </section>
</template>
