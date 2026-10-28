<script setup lang="ts">
import { fetchReviews, updateReviewStatus } from '@/api/admin'
import { ApiError } from '@/api/errors'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import BookImagePlaceholder from '@/components/admin/BookImagePlaceholder.vue'
import StatusBadge from '@/components/admin/StatusBadge.vue'
import { usePaginatedList } from '@/composables/usePaginatedList'
import { useToastStore } from '@/stores/toast'
import type { AdminReview } from '@/types/admin'
import { reviewStatusLabel } from '@/utils/adminLabels'
import { formatDateTime } from '@/utils/format'
import { ref } from 'vue'

const toast = useToastStore()
const statusFilter = ref('')

const { items, meta, loading, error, setQuery, load } = usePaginatedList<AdminReview>(fetchReviews)

async function setStatus(review: AdminReview, status: 'approved' | 'rejected'): Promise<void> {
  try {
    await updateReviewStatus(review.id, status)
    toast.success(status === 'approved' ? 'Đã duyệt đánh giá.' : 'Đã từ chối đánh giá.')
    await load()
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Thao tác thất bại.')
  }
}
</script>

<template>
  <CCard class="admin-page">
    <CCardHeader><strong>Đánh giá</strong></CCardHeader>
    <CCardBody>
      <CFormSelect v-model="statusFilter" class="admin-filters mb-3" style="max-width: 16rem" @change="setQuery({ page: 1, status: statusFilter || undefined })">
        <option value="">Tất cả trạng thái</option>
        <option v-for="(label, key) in reviewStatusLabel" :key="key" :value="key">{{ label }}</option>
      </CFormSelect>
      <CAlert v-if="error" color="danger">{{ error }}</CAlert>
      <div v-if="loading" class="admin-state"><CSpinner color="primary" /></div>
      <CTable v-else hover responsive align="middle">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>Sách</CTableHeaderCell>
            <CTableHeaderCell>Khách</CTableHeaderCell>
            <CTableHeaderCell>Điểm</CTableHeaderCell>
            <CTableHeaderCell>Nội dung</CTableHeaderCell>
            <CTableHeaderCell>Trạng thái</CTableHeaderCell>
            <CTableHeaderCell>Ngày</CTableHeaderCell>
            <CTableHeaderCell class="text-end">Duyệt</CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-if="!items.length">
            <CTableDataCell colspan="7" class="text-center text-body-secondary py-4">Không có đánh giá.</CTableDataCell>
          </CTableRow>
          <CTableRow v-for="r in items" :key="r.id">
            <CTableDataCell>
              <div class="d-flex align-items-center gap-2">
                <BookImagePlaceholder width="36px" height="48px" />
                <span>{{ r.book?.title ?? `#${r.book_id}` }}</span>
              </div>
            </CTableDataCell>
            <CTableDataCell>{{ r.user?.name ?? '—' }}</CTableDataCell>
            <CTableDataCell>{{ r.rating }}/5</CTableDataCell>
            <CTableDataCell class="admin-clip text-truncate">{{ r.comment ?? '—' }}</CTableDataCell>
            <CTableDataCell><StatusBadge kind="review" :value="r.status" /></CTableDataCell>
            <CTableDataCell>{{ formatDateTime(r.created_at) }}</CTableDataCell>
            <CTableDataCell class="text-end text-nowrap">
              <CButton
                v-if="r.status === 'pending'"
                color="success"
                size="sm"
                variant="ghost"
                @click="setStatus(r, 'approved')"
              >
                <CIcon icon="cil-check-circle" />
              </CButton>
              <CButton
                v-if="r.status === 'pending'"
                color="danger"
                size="sm"
                variant="ghost"
                @click="setStatus(r, 'rejected')"
              >
                <CIcon icon="cil-x-circle" />
              </CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
      <AdminPagination :meta="meta" @page="(p) => setQuery({ page: p })" />
    </CCardBody>
  </CCard>
</template>
