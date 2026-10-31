<script setup lang="ts">
import { createAddress, deleteAddress, fetchAddresses, updateAddress } from '@/api/commerce'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { useConfirm } from '@/composables/useConfirm'
import { ApiError } from '@/api/errors'
import type { UserAddress } from '@/types/customer'
import { MapPin, Plus } from '@lucide/vue'
import { onMounted, reactive, ref } from 'vue'

const addresses = ref<UserAddress[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const formOpen = ref(false)
const form = reactive({
  recipient_name: '',
  phone: '',
  province: '',
  district: '',
  ward: '',
  address_line: '',
  is_default: false,
})
const saving = ref(false)
const { confirm } = useConfirm()

const textareaClass =
  'flex min-h-[5rem] w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    addresses.value = await fetchAddresses()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không tải được địa chỉ.'
  } finally {
    loading.value = false
  }
}

async function add(): Promise<void> {
  saving.value = true
  error.value = null
  try {
    await createAddress({ ...form })
    Object.assign(form, {
      recipient_name: '',
      phone: '',
      province: '',
      district: '',
      ward: '',
      address_line: '',
      is_default: false,
    })
    formOpen.value = false
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không thêm được địa chỉ.'
  } finally {
    saving.value = false
  }
}

async function makeDefault(addr: UserAddress): Promise<void> {
  error.value = null
  try {
    await updateAddress(addr.id, { is_default: true })
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không cập nhật được địa chỉ mặc định.'
  }
}

async function remove(addr: UserAddress): Promise<void> {
  const ok = await confirm({
    title: 'Xóa địa chỉ',
    message: `Xóa địa chỉ của ${addr.recipient_name}?`,
  })
  if (!ok) return

  error.value = null
  try {
    await deleteAddress(addr.id)
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không xóa được địa chỉ.'
  }
}

function formatAddress(addr: UserAddress): string {
  return [addr.address_line, addr.ward, addr.district, addr.province].filter(Boolean).join(', ')
}

onMounted(() => void load())
</script>

<template>
  <section class="max-w-3xl">
    <header class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div>
        <h2 class="font-display text-2xl font-semibold tracking-tight">Địa chỉ giao hàng</h2>
        <p class="mt-1 max-w-xl text-sm leading-relaxed text-muted-foreground">
          Lưu địa chỉ nhận hàng để điền nhanh khi thanh toán COD. Bạn có thể chọn một địa chỉ mặc định.
        </p>
      </div>
      <Button v-if="!loading" type="button" variant="outline" size="sm" class="shrink-0" @click="formOpen = !formOpen">
        <Plus class="size-4" />
        {{ formOpen ? 'Đóng form' : 'Thêm địa chỉ' }}
      </Button>
    </header>

    <div
      v-if="error"
      class="mb-6 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
      role="alert"
    >
      {{ error }}
    </div>

    <div v-if="loading" class="space-y-4">
      <Skeleton v-for="i in 2" :key="i" class="h-28 w-full rounded-xl" />
    </div>

    <div
      v-else-if="!addresses.length"
      class="rounded-xl border border-dashed border-border bg-card/60 px-6 py-12 text-center"
    >
      <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-muted">
        <MapPin class="size-5 text-muted-foreground" />
      </div>
      <p class="mt-4 font-medium">Chưa có địa chỉ nào</p>
      <p class="mt-1 text-sm text-muted-foreground">Thêm địa chỉ để rút ngắn bước checkout.</p>
      <Button type="button" class="mt-6" @click="formOpen = true">
        <Plus class="size-4" />
        Thêm địa chỉ đầu tiên
      </Button>
    </div>

    <ul v-else class="space-y-4">
      <li
        v-for="addr in addresses"
        :key="addr.id"
        class="rounded-xl border border-border bg-card p-5 shadow-sm transition-colors hover:border-foreground/15"
      >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex gap-4">
            <div
              class="flex size-10 shrink-0 items-center justify-center rounded-full bg-secondary text-muted-foreground"
              aria-hidden="true"
            >
              <MapPin class="size-4" />
            </div>
            <div class="min-w-0 text-sm">
              <div class="flex flex-wrap items-center gap-2">
                <p class="font-semibold text-foreground">{{ addr.recipient_name }}</p>
                <Badge v-if="addr.is_default" variant="secondary">Mặc định</Badge>
              </div>
              <p class="mt-1 text-muted-foreground">{{ addr.phone }}</p>
              <p class="mt-2 leading-relaxed text-foreground/90">{{ formatAddress(addr) }}</p>
            </div>
          </div>
          <div class="flex flex-wrap gap-2 sm:justify-end">
            <Button v-if="!addr.is_default" variant="outline" size="sm" @click="makeDefault(addr)">
              Đặt mặc định
            </Button>
            <Button variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="remove(addr)">
              Xóa
            </Button>
          </div>
        </div>
      </li>
    </ul>

    <form
      v-if="formOpen"
      class="mt-8 rounded-xl border border-border bg-card p-6 shadow-sm"
      @submit.prevent="add"
    >
      <h3 class="font-display text-lg font-semibold">Thêm địa chỉ mới</h3>
      <p class="mt-1 text-sm text-muted-foreground">Thông tin khớp với đơn giao hàng thực tế.</p>

      <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-1">
          <label class="mb-1.5 block text-sm font-medium" for="addr-name">Người nhận</label>
          <Input id="addr-name" v-model="form.recipient_name" placeholder="Họ tên người nhận" required />
        </div>
        <div class="sm:col-span-1">
          <label class="mb-1.5 block text-sm font-medium" for="addr-phone">Số điện thoại</label>
          <Input id="addr-phone" v-model="form.phone" type="tel" placeholder="09xxxxxxxx" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium" for="addr-province">Tỉnh / Thành phố</label>
          <Input id="addr-province" v-model="form.province" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium" for="addr-district">Quận / Huyện</label>
          <Input id="addr-district" v-model="form.district" required />
        </div>
        <div class="sm:col-span-2 sm:grid sm:grid-cols-2 sm:gap-4">
          <div>
            <label class="mb-1.5 block text-sm font-medium" for="addr-ward">Phường / Xã</label>
            <Input id="addr-ward" v-model="form.ward" required />
          </div>
          <div class="mt-4 flex items-end sm:mt-0">
            <label class="flex cursor-pointer items-center gap-2 text-sm">
              <input
                v-model="form.is_default"
                type="checkbox"
                class="size-4 rounded border-input accent-accent"
              />
              Đặt làm địa chỉ mặc định
            </label>
          </div>
        </div>
        <div class="sm:col-span-2">
          <label class="mb-1.5 block text-sm font-medium" for="addr-line">Địa chỉ cụ thể</label>
          <textarea
            id="addr-line"
            v-model="form.address_line"
            rows="3"
            required
            placeholder="Số nhà, tên đường, tòa nhà…"
            :class="textareaClass"
          />
        </div>
      </div>

      <div class="mt-6 flex flex-wrap gap-3">
        <Button type="submit" :disabled="saving">{{ saving ? 'Đang lưu…' : 'Lưu địa chỉ' }}</Button>
        <Button type="button" variant="ghost" @click="formOpen = false">Hủy</Button>
      </div>
    </form>
  </section>
</template>
