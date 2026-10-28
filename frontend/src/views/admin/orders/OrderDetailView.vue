<script setup lang="ts">
import { fetchOrder, updateOrderStatus } from '@/api/admin'
import { ApiError } from '@/api/errors'
import BookImagePlaceholder from '@/components/admin/BookImagePlaceholder.vue'
import StatusBadge from '@/components/admin/StatusBadge.vue'
import { useToastStore } from '@/stores/toast'
import type { AdminOrder, OrderStatus } from '@/types/admin'
import { orderStatusLabel, orderTransitions } from '@/utils/adminLabels'
import { formatDateTime, formatVnd } from '@/utils/format'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const order = ref<AdminOrder | null>(null)
const loading = ref(true)
const updating = ref(false)
const nextStatus = ref<OrderStatus | ''>('')

const allowedNext = computed(() => {
  if (!order.value) return []
  return orderTransitions[order.value.status] ?? []
})

onMounted(async () => {
  try {
    order.value = await fetchOrder(Number(route.params.id))
    nextStatus.value = allowedNext.value[0] ?? ''
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Không tải được đơn hàng.')
    router.push({ name: 'admin-orders' })
  } finally {
    loading.value = false
  }
})

async function applyStatus(): Promise<void> {
  if (!order.value || !nextStatus.value) return
  updating.value = true
  try {
    order.value = await updateOrderStatus(order.value.id, nextStatus.value)
    nextStatus.value = allowedNext.value[0] ?? ''
    toast.success('Đã cập nhật trạng thái đơn.')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Cập nhật thất bại.')
  } finally {
    updating.value = false
  }
}

const address = computed(() => order.value?.shipping_address_snapshot)
</script>

<template>
  <div v-if="loading" class="admin-state"><CSpinner color="primary" /></div>
  <div v-else-if="order" class="admin-page">
    <CButton color="secondary" variant="ghost" size="sm" class="mb-3 px-2" @click="router.push({ name: 'admin-orders' })">
      ← Danh sách đơn
    </CButton>
    <CRow class="g-4">
      <CCol lg="8">
        <CCard>
          <CCardHeader class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <strong>{{ order.order_code }}</strong>
              <StatusBadge kind="order" :value="order.status" class="ms-2" />
            </div>
            <small class="text-body-secondary">{{ formatDateTime(order.created_at) }}</small>
          </CCardHeader>
          <CCardBody class="p-0">
            <CTable hover responsive class="mb-0">
              <CTableHead>
                <CTableRow>
                  <CTableHeaderCell>Sản phẩm</CTableHeaderCell>
                  <CTableHeaderCell>SL</CTableHeaderCell>
                  <CTableHeaderCell>Đơn giá</CTableHeaderCell>
                  <CTableHeaderCell class="text-end">Thành tiền</CTableHeaderCell>
                </CTableRow>
              </CTableHead>
              <CTableBody>
                <CTableRow v-for="item in order.items ?? []" :key="item.id">
                  <CTableDataCell>
                    <div class="d-flex align-items-center gap-2">
                      <BookImagePlaceholder width="40px" height="52px" />
                      <span>{{ item.book_title }}</span>
                    </div>
                  </CTableDataCell>
                  <CTableDataCell>{{ item.quantity }}</CTableDataCell>
                  <CTableDataCell>{{ formatVnd(item.unit_price) }}</CTableDataCell>
                  <CTableDataCell class="text-end">{{ formatVnd(item.subtotal) }}</CTableDataCell>
                </CTableRow>
              </CTableBody>
            </CTable>
          </CCardBody>
          <CCardFooter class="text-end">
            <div>Tạm tính: {{ formatVnd(order.subtotal_amount) }}</div>
            <div>Phí ship: {{ formatVnd(order.shipping_fee) }}</div>
            <div class="fw-semibold fs-5">Tổng: {{ formatVnd(order.total_amount) }}</div>
          </CCardFooter>
        </CCard>
      </CCol>
      <CCol lg="4">
        <CCard class="mb-4">
          <CCardHeader><strong>Khách hàng</strong></CCardHeader>
          <CCardBody>
            <div>{{ order.user?.name }}</div>
            <div class="text-body-secondary">{{ order.user?.email }}</div>
          </CCardBody>
        </CCard>
        <CCard class="mb-4">
          <CCardHeader><strong>Giao hàng</strong></CCardHeader>
          <CCardBody>
            <div v-if="address">
              <div>{{ address.recipient_name }}</div>
              <div>{{ address.phone }}</div>
              <div>{{ address.address_line }}</div>
              <div>{{ [address.ward, address.district, address.province].filter(Boolean).join(', ') }}</div>
            </div>
            <div v-else class="text-body-secondary">—</div>
            <div v-if="order.customer_note" class="mt-2">
              <small class="text-body-secondary">Ghi chú:</small>
              <div>{{ order.customer_note }}</div>
            </div>
          </CCardBody>
        </CCard>
        <CCard v-if="allowedNext.length">
          <CCardHeader><strong>Cập nhật trạng thái</strong></CCardHeader>
          <CCardBody>
            <CFormSelect v-model="nextStatus" class="mb-2">
              <option v-for="s in allowedNext" :key="s" :value="s">{{ orderStatusLabel[s] }}</option>
            </CFormSelect>
            <CButton color="primary" class="w-100" :disabled="updating || !nextStatus" @click="applyStatus">
              {{ updating ? 'Đang cập nhật...' : 'Áp dụng' }}
            </CButton>
          </CCardBody>
        </CCard>
        <CCard v-if="order.payment" class="mt-4">
          <CCardHeader><strong>Thanh toán</strong></CCardHeader>
          <CCardBody>
            <StatusBadge kind="payment" :value="order.payment.status" />
            <div class="mt-2">{{ formatVnd(order.payment.amount) }} — {{ order.payment.method?.toUpperCase() }}</div>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>
  </div>
</template>
