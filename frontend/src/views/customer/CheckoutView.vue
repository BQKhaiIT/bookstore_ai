<script setup lang="ts">
import { fetchAddresses, fetchCheckoutPreview, placeOrder } from '@/api/commerce'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import PriceDisplay from '@/components/customer/PriceDisplay.vue'
import Button from '@/components/ui/button/Button.vue'
import { ApiError } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import type { CheckoutPreview, UserAddress } from '@/types/customer'
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

const auth = useAuthStore()
const cart = useCartStore()
const router = useRouter()

const addresses = ref<UserAddress[]>([])
const selectedAddressId = ref<number | null>(null)
const preview = ref<CheckoutPreview | null>(null)
const note = ref('')
const paymentMethod = ref('cod')
const loading = ref(false)
const submitting = ref(false)
const error = ref<string | null>(null)

onMounted(async () => {
  if (!auth.isAuthenticated) {
    void router.replace({ name: 'login', query: { redirect: '/checkout' } })
    return
  }
  if (!auth.isVerified) {
    void router.replace({ name: 'verify-email', query: { redirect: '/checkout' } })
    return
  }
  await cart.load(true)
  loading.value = true
  try {
    addresses.value = await fetchAddresses()
    selectedAddressId.value = addresses.value.find((a) => a.is_default)?.id ?? addresses.value[0]?.id ?? null
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không tải được địa chỉ.'
  } finally {
    loading.value = false
  }
})

watch(
  selectedAddressId,
  async (id) => {
    if (!id) {
      preview.value = null
      return
    }
    try {
      preview.value = await fetchCheckoutPreview(id)
      if (preview.value?.payment_methods.length) {
        paymentMethod.value = preview.value.payment_methods[0]?.code ?? 'cod'
      }
    } catch (e) {
      error.value = e instanceof ApiError ? e.message : 'Không tải được xem trước thanh toán.'
    }
  },
  { immediate: true },
)

async function submit(): Promise<void> {
  if (!selectedAddressId.value || submitting.value) return
  submitting.value = true
  error.value = null
  const key = crypto.randomUUID()
  try {
    const order = await placeOrder(
      selectedAddressId.value,
      key,
      note.value.trim() || undefined,
      paymentMethod.value,
    )
    await cart.load(true)
    if (order.payment?.method === 'sandbox_online' && order.payment.status === 'pending') {
      void router.push({ name: 'order-payment', params: { id: order.id } })
    } else {
      void router.push({ name: 'order-detail', params: { id: order.id } })
    }
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không đặt hàng được.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <CustomerContainer class="py-8 lg:py-10">
    <h1 class="font-display text-3xl font-semibold">Thanh toán</h1>
    <p v-if="error" class="mt-2 text-sm text-destructive">{{ error }}</p>
    <p v-if="loading" class="mt-8 text-muted-foreground">Đang tải…</p>
    <div v-else-if="!addresses.length" class="mt-8 text-muted-foreground">
      Chưa có địa chỉ giao hàng.
      <RouterLink to="/account/addresses" class="text-accent hover:underline">Thêm địa chỉ</RouterLink>
    </div>
    <div v-else class="mt-8 grid gap-8 lg:grid-cols-[1fr_340px]">
      <section class="space-y-4">
        <h2 class="font-display text-xl font-semibold">Địa chỉ giao hàng</h2>
        <label
          v-for="addr in addresses"
          :key="addr.id"
          class="flex cursor-pointer gap-3 rounded-lg border border-border bg-card p-4 has-[:checked]:border-primary"
        >
          <input v-model="selectedAddressId" type="radio" :value="addr.id" class="mt-1" />
          <span class="text-sm">
            <strong>{{ addr.recipient_name }}</strong> — {{ addr.phone }}<br />
            {{ addr.address_line }}, {{ addr.ward }}, {{ addr.district }}, {{ addr.province }}
          </span>
        </label>
        <h2 class="font-display text-xl font-semibold">Phương thức thanh toán</h2>
        <div v-if="preview?.payment_methods.length" class="space-y-2">
          <label
            v-for="method in preview.payment_methods"
            :key="method.code"
            class="flex cursor-pointer gap-3 rounded-lg border border-border bg-card p-4 has-[:checked]:border-primary"
          >
            <input v-model="paymentMethod" type="radio" :value="method.code" class="mt-1" />
            <span class="text-sm">{{ method.label }}</span>
          </label>
        </div>
        <label class="block text-sm font-medium" for="note">Ghi chú</label>
        <textarea
          id="note"
          v-model="note"
          rows="2"
          class="flex w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />
      </section>
      <aside v-if="preview" class="h-fit rounded-lg border border-border bg-card p-5">
        <h2 class="font-display text-lg font-semibold">Tóm tắt đơn</h2>
        <ul class="mt-4 list-disc space-y-1 pl-4 text-sm text-muted-foreground">
          <li v-for="line in preview.items" :key="line.book_id">{{ line.book_title }} × {{ line.quantity }}</li>
        </ul>
        <div class="mt-4 space-y-2 text-sm">
          <p class="flex justify-between"><span>Tạm tính</span> <PriceDisplay :amount="preview.subtotal_amount" size="sm" /></p>
          <p class="flex justify-between"><span>Phí ship</span> <PriceDisplay :amount="preview.shipping_fee" size="sm" /></p>
          <p class="flex justify-between border-t border-border pt-2 font-semibold">
            <span>Tổng</span> <PriceDisplay :amount="preview.total_amount" />
          </p>
        </div>
        <p class="mt-3 text-xs text-muted-foreground">
          {{
            paymentMethod === 'sandbox_online'
              ? 'Bạn sẽ được chuyển tới cổng SePay Sandbox (QR thử nghiệm).'
              : 'Thanh toán COD khi nhận hàng'
          }}
        </p>
        <Button class="mt-4 w-full" variant="accent" :disabled="submitting" @click="submit">
          {{ submitting ? 'Đang đặt hàng…' : 'Đặt hàng' }}
        </Button>
      </aside>
    </div>
  </CustomerContainer>
</template>
