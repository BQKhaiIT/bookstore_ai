<script setup lang="ts">
import { fetchOrders } from '@/api/admin'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import StatusBadge from '@/components/admin/StatusBadge.vue'
import { usePaginatedList } from '@/composables/usePaginatedList'
import type { AdminOrder } from '@/types/admin'
import { orderStatusLabel } from '@/utils/adminLabels'
import { formatDateTime, formatVnd } from '@/utils/format'
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const searchInput = ref('')
const statusFilter = ref('')

const { items, meta, loading, error, setQuery } = usePaginatedList<AdminOrder>(fetchOrders)

function applyFilters(): void {
  setQuery({
    page: 1,
    search: searchInput.value || undefined,
    status: statusFilter.value || undefined,
  })
}
</script>

<template>
  <CCard class="admin-page">
    <CCardHeader><strong>Đơn hàng</strong></CCardHeader>
    <CCardBody>
      <CRow class="admin-filters g-2 mb-3">
        <CCol md="5">
          <CFormInput v-model="searchInput" placeholder="Mã đơn hoặc email khách..." @keyup.enter="applyFilters" />
        </CCol>
        <CCol md="3">
          <CFormSelect v-model="statusFilter" @change="applyFilters">
            <option value="">Tất cả trạng thái</option>
            <option v-for="(label, key) in orderStatusLabel" :key="key" :value="key">{{ label }}</option>
          </CFormSelect>
        </CCol>
        <CCol md="2"><CButton color="secondary" class="w-100" @click="applyFilters">Lọc</CButton></CCol>
      </CRow>
      <CAlert v-if="error" color="danger">{{ error }}</CAlert>
      <div v-if="loading" class="admin-state"><CSpinner color="primary" /></div>
      <CTable v-else hover responsive align="middle">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>Mã đơn</CTableHeaderCell>
            <CTableHeaderCell>Khách hàng</CTableHeaderCell>
            <CTableHeaderCell>Trạng thái</CTableHeaderCell>
            <CTableHeaderCell class="text-end">Tổng</CTableHeaderCell>
            <CTableHeaderCell>Ngày tạo</CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-if="!items.length">
            <CTableDataCell colspan="5" class="text-center text-body-secondary py-4">Không có đơn hàng.</CTableDataCell>
          </CTableRow>
          <CTableRow
            v-for="order in items"
            :key="order.id"
            role="button"
            @click="router.push({ name: 'admin-order-detail', params: { id: order.id } })"
          >
            <CTableDataCell>{{ order.order_code }}</CTableDataCell>
            <CTableDataCell>
              <div>{{ order.user?.name ?? '—' }}</div>
              <small class="text-body-secondary">{{ order.user?.email }}</small>
            </CTableDataCell>
            <CTableDataCell><StatusBadge kind="order" :value="order.status" /></CTableDataCell>
            <CTableDataCell class="text-end">{{ formatVnd(order.total_amount) }}</CTableDataCell>
            <CTableDataCell>{{ formatDateTime(order.created_at) }}</CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
      <AdminPagination :meta="meta" @page="(p) => setQuery({ page: p })" />
    </CCardBody>
  </CCard>
</template>
