<script setup lang="ts">
import { fetchOrders } from '@/api/commerce'
import { ApiError } from '@/api/errors'
import BookCover from '@/components/customer/BookCover.vue'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import PriceDisplay from '@/components/customer/PriceDisplay.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import { useCartStore } from '@/stores/cart'
import { orderStatusLabel } from '@/utils/adminLabels'
import { formatDateTime, formatVnd } from '@/utils/format'
import type { OrderSummary } from '@/types/customer'
import type { OrderStatus } from '@/types/admin'
import { Minus, Plus, Trash2 } from '@lucide/vue'
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const cart = useCartStore()
const auth = useAuthStore()
const router = useRouter()
const updatingId = ref<number | null>(null)

const recentOrders = ref<OrderSummary[]>([])
const ordersLoading = ref(true)
const ordersError = ref<string | null>(null)

function orderStatusText(status: string): string {
  return orderStatusLabel[status as OrderStatus] ?? status
}

onMounted(async () => {
  if (!auth.isAuthenticated) {
    void router.replace({ name: 'login', query: { redirect: '/cart' } })
    return
  }
  await Promise.all([
    cart.load(true),
    (async () => {
      ordersLoading.value = true
      ordersError.value = null
      try {
        const res = await fetchOrders(1, undefined, 5)
        recentOrders.value = res.data
      } catch (e) {
        ordersError.value = e instanceof ApiError ? e.message : 'Không tải được lịch sử mua.'
      } finally {
        ordersLoading.value = false
      }
    })(),
  ])
})

async function changeQty(itemId: number, quantity: number): Promise<void> {
  updatingId.value = itemId
  try {
    await cart.setQuantity(itemId, quantity)
  } finally {
    updatingId.value = null
  }
}
</script>

<template>
  <CustomerContainer class="py-8 lg:py-10">
    <h1 class="font-display text-3xl font-semibold">Giỏ hàng</h1>
    <p v-if="cart.error" class="mt-2 text-sm text-destructive">{{ cart.error }}</p>

    <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_320px] lg:items-start">
      <div>
        <div v-if="cart.loading && !cart.cart" class="text-muted-foreground">Đang tải…</div>
        <div v-else-if="!cart.cart?.items.length" class="flex flex-col items-start gap-4">
          <p class="text-muted-foreground">Giỏ hàng trống.</p>
          <Button as-child variant="accent"><RouterLink to="/books">Tiếp tục mua sách</RouterLink></Button>
        </div>
        <template v-else>
          <ul class="space-y-4">
            <li
              v-for="item in cart.cart.items"
              :key="item.id"
              class="grid gap-4 rounded-lg border border-border bg-card p-4 sm:grid-cols-[80px_1fr_auto]"
            >
              <BookCover :title="item.book.title" :cover-url="item.book.cover_url" class="w-20" />
              <div class="min-w-0">
                <RouterLink :to="{ name: 'book-detail', params: { slug: item.book.slug } }" class="font-medium hover:text-accent">
                  {{ item.book.title }}
                </RouterLink>
                <div class="mt-1"><PriceDisplay :amount="item.unit_price" size="sm" /></div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                  <Button variant="outline" size="icon" :disabled="updatingId === item.id" @click="changeQty(item.id, item.quantity - 1)">
                    <Minus class="size-4" />
                  </Button>
                  <span class="min-w-8 text-center text-sm font-medium">{{ item.quantity }}</span>
                  <Button variant="outline" size="icon" :disabled="updatingId === item.id" @click="changeQty(item.id, item.quantity + 1)">
                    <Plus class="size-4" />
                  </Button>
                  <Button variant="ghost" size="sm" @click="cart.remove(item.id)"><Trash2 class="size-4" /> Xóa</Button>
                </div>
              </div>
              <div class="text-right font-semibold sm:self-center">{{ formatVnd(item.subtotal) }}</div>
            </li>
          </ul>
          <div class="mt-8 flex flex-col items-end gap-3 border-t border-border pt-6 sm:flex-row sm:justify-end">
            <p class="text-sm">Tạm tính: <strong>{{ formatVnd(cart.cart.subtotal_amount) }}</strong></p>
            <Button as-child variant="accent"><RouterLink to="/checkout">Thanh toán</RouterLink></Button>
            <Button as-child variant="outline"><RouterLink to="/books">Tiếp tục mua</RouterLink></Button>
          </div>
        </template>
      </div>

      <aside class="rounded-lg border border-border bg-card p-5 lg:sticky lg:top-24">
        <div class="flex items-center justify-between gap-2">
          <h2 class="font-display text-lg font-semibold">Lịch sử mua gần đây</h2>
          <RouterLink to="/orders" class="text-xs text-accent hover:underline">Tất cả</RouterLink>
        </div>
        <p v-if="ordersError" class="mt-3 text-sm text-destructive">{{ ordersError }}</p>
        <p v-else-if="ordersLoading" class="mt-4 text-sm text-muted-foreground">Đang tải…</p>
        <p v-else-if="!recentOrders.length" class="mt-4 text-sm text-muted-foreground">Chưa có đơn hàng nào.</p>
        <ul v-else class="mt-4 space-y-3">
          <li v-for="o in recentOrders" :key="o.id">
            <RouterLink
              :to="{ name: 'order-detail', params: { id: o.id } }"
              class="block rounded-md border border-border/80 p-3 transition-colors hover:border-foreground/20 hover:bg-muted/40"
            >
              <p class="text-sm font-medium leading-snug">{{ o.order_code }}</p>
              <p class="mt-1 text-xs text-muted-foreground">{{ formatDateTime(o.created_at) }}</p>
              <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                <Badge variant="outline" class="text-xs font-normal">{{ orderStatusText(o.status) }}</Badge>
                <PriceDisplay :amount="o.total_amount" size="sm" />
              </div>
            </RouterLink>
          </li>
        </ul>
      </aside>
    </div>
  </CustomerContainer>
</template>
