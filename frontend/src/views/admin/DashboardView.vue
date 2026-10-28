<script setup lang="ts">
import { fetchDashboard, fetchOrders } from '@/api/admin'
import { ApiError } from '@/api/errors'
import StatusBadge from '@/components/admin/StatusBadge.vue'
import type { AdminOrder, DashboardStats } from '@/types/admin'
import { formatDateTime, formatVnd } from '@/utils/format'
import { cilBook, cilCart, cilCreditCard, cilPeople, cilReload } from '@coreui/icons'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const stats = ref<DashboardStats | null>(null)
const recentOrders = ref<AdminOrder[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const [dash, orders] = await Promise.all([
      fetchDashboard(),
      fetchOrders({ per_page: 5 }),
    ])
    stats.value = dash
    recentOrders.value = orders.data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không tải được dashboard.'
  } finally {
    loading.value = false
  }
})

const cards: {
  key: keyof DashboardStats
  label: string
  icon: typeof cilBook
  format: (v: number) => string
}[] = [
  { key: 'revenue', label: 'Doanh thu', icon: cilCreditCard, format: (v) => formatVnd(v) },
  { key: 'orders_count', label: 'Tổng đơn hàng', icon: cilCart, format: (v) => String(v) },
  { key: 'pending_orders_count', label: 'Đơn chờ xử lý', icon: cilReload, format: (v) => String(v) },
  { key: 'books_count', label: 'Sách trong kho', icon: cilBook, format: (v) => String(v) },
  { key: 'customers_count', label: 'Khách hàng', icon: cilPeople, format: (v) => String(v) },
]
</script>

<template>
  <div v-if="loading" class="admin-state">
    <CSpinner color="primary" />
  </div>
  <CAlert v-else-if="error" color="danger" class="mb-0">{{ error }}</CAlert>
  <div v-else-if="stats" class="admin-page d-flex flex-column gap-4">
    <div class="kpi-grid">
      <CCard v-for="card in cards" :key="card.key" class="kpi-card h-100">
        <CCardBody>
          <div>
            <div class="kpi-label">{{ card.label }}</div>
            <div class="kpi-value">{{ card.format(stats[card.key]) }}</div>
          </div>
          <div class="kpi-icon" aria-hidden="true">
            <CIcon :icon="card.icon" size="lg" />
          </div>
        </CCardBody>
      </CCard>
    </div>

    <CCard>
      <CCardHeader>
        <strong>Đơn hàng gần đây</strong>
        <CButton color="primary" variant="ghost" size="sm" @click="router.push({ name: 'admin-orders' })">
          Xem tất cả
        </CButton>
      </CCardHeader>
      <CCardBody class="p-0">
        <CTable hover responsive align="middle" class="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Mã đơn</CTableHeaderCell>
              <CTableHeaderCell>Khách</CTableHeaderCell>
              <CTableHeaderCell>Trạng thái</CTableHeaderCell>
              <CTableHeaderCell class="text-end">Tổng tiền</CTableHeaderCell>
              <CTableHeaderCell>Ngày tạo</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-if="!recentOrders.length">
              <CTableDataCell colspan="5" class="text-center text-body-secondary py-4">Chưa có đơn hàng.</CTableDataCell>
            </CTableRow>
            <CTableRow
              v-for="order in recentOrders"
              :key="order.id"
              role="button"
              @click="router.push({ name: 'admin-order-detail', params: { id: order.id } })"
            >
              <CTableDataCell>{{ order.order_code }}</CTableDataCell>
              <CTableDataCell>{{ order.user?.name ?? '—' }}</CTableDataCell>
              <CTableDataCell><StatusBadge kind="order" :value="order.status" /></CTableDataCell>
              <CTableDataCell class="text-end">{{ formatVnd(order.total_amount) }}</CTableDataCell>
              <CTableDataCell>{{ formatDateTime(order.created_at) }}</CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>
  </div>
</template>
