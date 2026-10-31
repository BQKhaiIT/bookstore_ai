<script setup lang="ts">
import { fetchOrders } from '@/api/commerce'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import PriceDisplay from '@/components/customer/PriceDisplay.vue'
import { ApiError } from '@/api/errors'
import { formatDateTime } from '@/utils/format'
import type { OrderSummary } from '@/types/customer'
import Badge from '@/components/ui/badge/Badge.vue'
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const orders = ref<OrderSummary[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  if (!auth.isAuthenticated) {
    void router.replace({ name: 'login', query: { redirect: '/orders' } })
    return
  }
  try {
    const res = await fetchOrders()
    orders.value = res.data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không tải được đơn hàng.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <CustomerContainer class="py-8 lg:py-10">
    <h1 class="font-display text-3xl font-semibold">Đơn hàng của tôi</h1>
    <p v-if="error" class="mt-2 text-sm text-destructive">{{ error }}</p>
    <p v-if="loading" class="mt-8 text-muted-foreground">Đang tải…</p>
    <p v-else-if="!orders.length" class="mt-8 text-muted-foreground">Chưa có đơn hàng.</p>
    <ul v-else class="mt-8 space-y-3">
      <li v-for="o in orders" :key="o.id">
        <RouterLink
          :to="{ name: 'order-detail', params: { id: o.id } }"
          class="flex flex-col gap-2 rounded-lg border border-border bg-card p-4 transition-colors hover:border-foreground/20 sm:flex-row sm:items-center sm:justify-between"
        >
          <div>
            <p class="font-medium">{{ o.order_code }}</p>
            <p class="text-xs text-muted-foreground">{{ formatDateTime(o.created_at) }}</p>
          </div>
          <div class="flex items-center gap-3">
            <Badge variant="outline">{{ o.status }}</Badge>
            <PriceDisplay :amount="o.total_amount" size="sm" />
          </div>
        </RouterLink>
      </li>
    </ul>
  </CustomerContainer>
</template>
