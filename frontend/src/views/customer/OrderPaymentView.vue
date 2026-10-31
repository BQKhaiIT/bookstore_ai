<script setup lang="ts">
import { createSePayPaymentSession, fetchOrder, fetchOrderPayment } from '@/api/commerce'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import PriceDisplay from '@/components/customer/PriceDisplay.vue'
import Button from '@/components/ui/button/Button.vue'
import { ApiError } from '@/api/errors'
import type { OrderSummary, PaymentSummary, SePayCheckoutSession } from '@/types/customer'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const orderId = computed(() => Number(route.params.id))

const order = ref<OrderSummary | null>(null)
const payment = ref<PaymentSummary | null>(null)
const checkout = ref<SePayCheckoutSession | null>(null)
const loading = ref(true)
const submitting = ref(false)
const polling = ref(false)
const pollTimedOut = ref(false)
const rechecking = ref(false)
const error = ref<string | null>(null)

const outcome = computed(() => {
  const raw = route.query.outcome
  return typeof raw === 'string' ? raw : null
})

const statusMessage = computed(() => {
  if (!payment.value) return null
  switch (payment.value.status) {
    case 'paid':
      return 'Thanh toán thành công'
    case 'failed':
      return 'Thanh toán thất bại. Bạn có thể thử lại.'
    case 'pending':
      if (pollTimedOut.value) {
        return 'Đã chờ quá lâu mà chưa có xác nhận. Hãy kiểm tra lại trên SePay Sandbox rồi bấm Kiểm tra lại. Nếu SePay đã báo thành công, không tạo thêm giao dịch.'
      }
      if (outcome.value === 'cancel') return 'Bạn đã hủy thanh toán. Trạng thái sẽ cập nhật khi có xác nhận từ cổng.'
      if (outcome.value === 'error') return 'Cổng thanh toán báo lỗi. Vui lòng thử lại hoặc chờ xác nhận.'
      if (outcome.value === 'success') return 'Đang chờ xác nhận thanh toán từ SePay…'
      return 'Đang chờ thanh toán qua SePay Sandbox.'
    default:
      return `Trạng thái: ${payment.value.status}`
  }
})

let pollTimer: ReturnType<typeof setInterval> | null = null
let pollStartedAt = 0
const POLL_MS = 2500
const POLL_TIMEOUT_MS = 120000

async function loadPaymentState(): Promise<void> {
  payment.value = await fetchOrderPayment(orderId.value)
}

async function refreshAll(): Promise<void> {
  order.value = await fetchOrder(orderId.value)
  await loadPaymentState()
}

function stopPolling(): void {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
  polling.value = false
}

function startPollingIfNeeded(): void {
  stopPolling()
  pollTimedOut.value = false
  if (payment.value?.status !== 'pending') return
  if (outcome.value !== 'success' && outcome.value !== null) return

  polling.value = true
  pollStartedAt = Date.now()
  pollTimer = setInterval(async () => {
    if (Date.now() - pollStartedAt > POLL_TIMEOUT_MS) {
      pollTimedOut.value = true
      stopPolling()
      return
    }
    try {
      await loadPaymentState()
      if (payment.value?.status === 'paid') {
        pollTimedOut.value = false
        stopPolling()
      }
    } catch {
      /* ignore transient poll errors */
    }
  }, POLL_MS)
}

async function recheckPayment(): Promise<void> {
  if (rechecking.value) return
  rechecking.value = true
  error.value = null
  try {
    await loadPaymentState()
    if (payment.value?.status === 'paid') {
      pollTimedOut.value = false
      stopPolling()
    }
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không kiểm tra được trạng thái thanh toán.'
  } finally {
    rechecking.value = false
  }
}

async function beginCheckout(): Promise<void> {
  if (submitting.value || payment.value?.status === 'paid') return
  submitting.value = true
  error.value = null
  try {
    const session = await createSePayPaymentSession(orderId.value)
    checkout.value = session.checkout
    payment.value = session.payment
    await submitCheckoutForm()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không tạo được phiên thanh toán.'
    submitting.value = false
  }
}

async function submitCheckoutForm(): Promise<void> {
  if (!checkout.value) return
  await new Promise<void>((resolve) => {
    requestAnimationFrame(() => {
      const form = document.getElementById('sepay-checkout-form') as HTMLFormElement | null
      form?.submit()
      resolve()
    })
  })
}

onMounted(async () => {
  loading.value = true
  try {
    await refreshAll()
    if (payment.value?.method !== 'sandbox_online') {
      void router.replace({ name: 'order-detail', params: { id: orderId.value } })
      return
    }
    if (payment.value.status === 'pending' && !outcome.value) {
      await beginCheckout()
    } else {
      startPollingIfNeeded()
    }
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không tải được thông tin thanh toán.'
  } finally {
    loading.value = false
    submitting.value = false
  }
})

watch(
  () => payment.value?.status,
  (status) => {
    if (status === 'paid') {
      pollTimedOut.value = false
      stopPolling()
    }
  },
)

onBeforeUnmount(() => stopPolling())
</script>

<template>
  <CustomerContainer class="py-8 lg:py-10">
    <h1 class="font-display text-3xl font-semibold">Thanh toán SePay Sandbox</h1>
    <p v-if="order" class="mt-1 text-sm text-muted-foreground">Mã đơn: {{ order.order_code }}</p>
    <p v-if="error" class="mt-3 text-sm text-destructive">{{ error }}</p>
    <p v-if="loading" class="mt-8 text-muted-foreground">Đang tải…</p>

    <div v-else-if="payment && order" class="mt-8 max-w-lg space-y-4 rounded-lg border border-border bg-card p-6">
      <p class="text-sm">
        Mã đơn: <span class="font-medium text-foreground">{{ order.order_code }}</span>
      </p>
      <p class="text-sm">
        Số tiền: <PriceDisplay :amount="payment.amount" size="sm" />
      </p>
      <p
        v-if="statusMessage"
        class="text-sm"
        :class="payment.status === 'paid' ? 'font-medium text-foreground' : 'text-muted-foreground'"
      >
        {{ statusMessage }}
      </p>
      <p v-if="polling" class="text-xs text-muted-foreground">Đang kiểm tra trạng thái…</p>

      <form
        v-if="checkout"
        id="sepay-checkout-form"
        :action="checkout.action_url"
        :method="checkout.method"
        class="hidden"
      >
        <input
          v-for="name in checkout.field_order"
          :key="name"
          type="hidden"
          :name="name"
          :value="checkout.fields[name]"
        />
      </form>

      <div class="flex flex-wrap gap-3 pt-2">
        <Button
          v-if="payment.status === 'pending' && pollTimedOut"
          variant="outline"
          :disabled="rechecking"
          @click="recheckPayment"
        >
          {{ rechecking ? 'Đang kiểm tra…' : 'Kiểm tra lại' }}
        </Button>
        <Button
          v-if="payment.status === 'pending'"
          variant="accent"
          :disabled="submitting"
          @click="beginCheckout"
        >
          {{ submitting ? 'Đang chuyển tới SePay…' : 'Tiếp tục thanh toán QR' }}
        </Button>
        <Button v-if="payment.status === 'paid'" variant="outline" as-child>
          <RouterLink :to="{ name: 'order-detail', params: { id: orderId } }">Xem đơn hàng</RouterLink>
        </Button>
        <Button v-else variant="ghost" as-child>
          <RouterLink :to="{ name: 'orders' }">Danh sách đơn</RouterLink>
        </Button>
      </div>
    </div>
  </CustomerContainer>
</template>
