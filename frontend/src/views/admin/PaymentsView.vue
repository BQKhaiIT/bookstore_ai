<script setup lang="ts">
import { confirmCodPayment, fetchPayments } from '@/api/admin'
import { ApiError } from '@/api/errors'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import StatusBadge from '@/components/admin/StatusBadge.vue'
import { useConfirm } from '@/composables/useConfirm'
import { usePaginatedList } from '@/composables/usePaginatedList'
import { useToastStore } from '@/stores/toast'
import type { AdminPayment } from '@/types/admin'
import { paymentStatusLabel } from '@/utils/adminLabels'
import { formatDateTime, formatVnd } from '@/utils/format'
import { ref } from 'vue'

const toast = useToastStore()
const { confirm } = useConfirm()
const statusFilter = ref('')

const { items, meta, loading, error, setQuery, load } = usePaginatedList<AdminPayment>(fetchPayments)

async function onConfirmCod(payment: AdminPayment): Promise<void> {
  const ok = await confirm({ message: `Xác nhận đã thu COD ${formatVnd(payment.amount)} cho đơn #${payment.order_id}?` })
  if (!ok) return
  try {
    await confirmCodPayment(payment.id)
    toast.success('Đã xác nhận COD.')
    await load()
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Xác nhận thất bại.')
  }
}
</script>

<template>
  <CCard class="admin-page">
    <CCardHeader><strong>Thanh toán</strong></CCardHeader>
    <CCardBody>
      <CFormSelect v-model="statusFilter" class="admin-filters mb-3" style="max-width: 16rem" @change="setQuery({ page: 1, status: statusFilter || undefined })">
        <option value="">Tất cả trạng thái</option>
        <option v-for="(label, key) in paymentStatusLabel" :key="key" :value="key">{{ label }}</option>
      </CFormSelect>
      <CAlert v-if="error" color="danger">{{ error }}</CAlert>
      <div v-if="loading" class="admin-state"><CSpinner color="primary" /></div>
      <CTable v-else hover responsive align="middle">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>ID</CTableHeaderCell>
            <CTableHeaderCell>Đơn hàng</CTableHeaderCell>
            <CTableHeaderCell>Phương thức</CTableHeaderCell>
            <CTableHeaderCell>Cổng / Môi trường</CTableHeaderCell>
            <CTableHeaderCell>Mã giao dịch</CTableHeaderCell>
            <CTableHeaderCell>Số tiền</CTableHeaderCell>
            <CTableHeaderCell>Trạng thái</CTableHeaderCell>
            <CTableHeaderCell>Ngày tạo</CTableHeaderCell>
            <CTableHeaderCell class="text-end">Thao tác</CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-if="!items.length">
            <CTableDataCell colspan="9" class="text-center text-body-secondary py-4">Không có giao dịch.</CTableDataCell>
          </CTableRow>
          <CTableRow v-for="p in items" :key="p.id">
            <CTableDataCell>#{{ p.id }}</CTableDataCell>
            <CTableDataCell>#{{ p.order_id }}</CTableDataCell>
            <CTableDataCell>{{ String(p.method).toUpperCase() }}</CTableDataCell>
            <CTableDataCell>
              <span v-if="p.provider">{{ p.provider }}</span>
              <span v-else class="text-body-secondary">—</span>
              <span v-if="p.environment" class="text-body-secondary"> / {{ p.environment }}</span>
            </CTableDataCell>
            <CTableDataCell>{{ p.transaction_reference || '—' }}</CTableDataCell>
            <CTableDataCell>{{ formatVnd(p.amount) }}</CTableDataCell>
            <CTableDataCell><StatusBadge kind="payment" :value="p.status" /></CTableDataCell>
            <CTableDataCell>{{ formatDateTime(p.created_at) }}</CTableDataCell>
            <CTableDataCell class="text-end">
              <CButton
                v-if="p.method === 'cod' && p.status === 'pending'"
                color="success"
                size="sm"
                @click="onConfirmCod(p)"
              >
                Xác nhận COD
              </CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
      <AdminPagination :meta="meta" @page="(page) => setQuery({ page })" />
    </CCardBody>
  </CCard>
</template>
