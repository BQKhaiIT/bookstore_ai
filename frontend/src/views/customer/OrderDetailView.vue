<script setup lang="ts">
import { cancelOrder, fetchOrder } from '@/api/commerce'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import PriceDisplay from '@/components/customer/PriceDisplay.vue'
import Button from '@/components/ui/button/Button.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import { ApiError } from '@/api/errors'
import { formatDateTime, formatVnd } from '@/utils/format'
import type { OrderSummary } from '@/types/customer'
import { onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const order = ref<OrderSummary | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  if (!auth.isAuthenticated) {
    void router.replace({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  try {
    order.value = await fetchOrder(Number(route.params.id))
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không tải được đơn hàng.'
  } finally {
    loading.value = false
  }
})

async function cancel(): Promise<void> {
  if (!order.value) return
  try {
    order.value = await cancelOrder(order.value.id)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không hủy được đơn.'
  }
}
</script>

<template>
  <CustomerContainer class="py-8 lg:py-10">
    <h1 class="font-display text-3xl font-semibold">Chi tiết đơn hàng</h1>
    <p v-if="error" class="mt-2 text-sm text-destructive">{{ error }}</p>
    <p v-if="loading" class="mt-8 text-muted-foreground">Đang tải…</p>
    <div v-else-if="order" class="mt-8 max-w-2xl space-y-6 rounded-lg border border-border bg-card p-6">
      <div class="flex flex-wrap items-center gap-3">
        <p class="font-semibold">{{ order.order_code }}</p>
        <Badge variant="outline">{{ order.status }}</Badge>
      </div>
      <p class="text-sm text-muted-foreground">{{ formatDateTime(order.created_at) }}</p>
      <p v-if="order.payment" class="text-sm">
        Thanh toán: {{ order.payment.method }} ({{ order.payment.status }})
        <span v-if="order.payment.paid_at"> — {{ formatDateTime(order.payment.paid_at) }}</span>
      </p>
      <Button
        v-if="order.payment?.method === 'sandbox_online' && order.payment.status === 'pending'"
        variant="accent"
        as-child
      >
        <RouterLink :to="{ name: 'order-payment', params: { id: order.id } }">Tiếp tục thanh toán SePay</RouterLink>
      </Button>
      <ul v-if="order.items?.length" class="list-disc space-y-1 pl-5 text-sm">
        <li v-for="item in order.items" :key="item.id">{{ item.book_title }} × {{ item.quantity }} — {{ formatVnd(item.subtotal) }}</li>
      </ul>
      <p class="text-lg font-semibold">Tổng: <PriceDisplay :amount="order.total_amount" /></p>
      <Button v-if="order.status === 'pending'" variant="outline" @click="cancel">Hủy đơn</Button>
    </div>
  </CustomerContainer>
</template>
