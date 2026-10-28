<script setup lang="ts">
import { deleteBook, fetchBooks, fetchCategories, fetchPublishers } from '@/api/admin'
import { ApiError } from '@/api/errors'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import BookImagePlaceholder from '@/components/admin/BookImagePlaceholder.vue'
import StatusBadge from '@/components/admin/StatusBadge.vue'
import { useConfirm } from '@/composables/useConfirm'
import { usePaginatedList } from '@/composables/usePaginatedList'
import { useToastStore } from '@/stores/toast'
import type { AdminBook, Category, Publisher } from '@/types/admin'
import { bookStatusLabel } from '@/utils/adminLabels'
import { formatVnd } from '@/utils/format'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const toast = useToastStore()
const { confirm } = useConfirm()
const searchInput = ref('')
const statusFilter = ref('')
const publisherFilter = ref<number | ''>('')
const categoryFilter = ref<number | ''>('')
const publishers = ref<Publisher[]>([])
const categories = ref<Category[]>([])

const { items, meta, loading, error, setQuery, load } = usePaginatedList<AdminBook>(fetchBooks)

onMounted(async () => {
  const [pubs, cats] = await Promise.all([
    fetchPublishers({ per_page: 100 }),
    fetchCategories({ per_page: 100 }),
  ])
  publishers.value = pubs.data
  categories.value = cats.data
})

function applyFilters(): void {
  setQuery({
    page: 1,
    search: searchInput.value || undefined,
    status: statusFilter.value || undefined,
    publisher_id: publisherFilter.value || undefined,
    category_id: categoryFilter.value || undefined,
  })
}

async function onDelete(book: AdminBook): Promise<void> {
  const ok = await confirm({ message: `Xóa hoặc lưu trữ sách "${book.title}"?` })
  if (!ok) return
  try {
    await deleteBook(book.id)
    toast.success('Đã xử lý sách.')
    await load()
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Thao tác thất bại.')
  }
}
</script>

<template>
  <CCard class="admin-page">
    <CCardHeader>
      <strong>Danh sách sách</strong>
      <CButton color="primary" @click="router.push({ name: 'admin-book-create' })">
        <CIcon icon="cil-plus" class="me-1" /> Thêm sách
      </CButton>
    </CCardHeader>
    <CCardBody>
      <CRow class="admin-filters g-2 mb-3">
        <CCol md="4">
          <CInputGroup>
            <CInputGroupText><CIcon icon="cil-magnifying-glass" /></CInputGroupText>
            <CFormInput v-model="searchInput" placeholder="Tìm theo tên, ISBN..." @keyup.enter="applyFilters" />
          </CInputGroup>
        </CCol>
        <CCol md="2">
          <CFormSelect v-model="statusFilter" @change="applyFilters">
            <option value="">Tất cả trạng thái</option>
            <option v-for="(label, key) in bookStatusLabel" :key="key" :value="key">{{ label }}</option>
          </CFormSelect>
        </CCol>
        <CCol md="2">
          <CFormSelect v-model="publisherFilter" @change="applyFilters">
            <option value="">Tất cả NXB</option>
            <option v-for="p in publishers" :key="p.id" :value="p.id">{{ p.name }}</option>
          </CFormSelect>
        </CCol>
        <CCol md="2">
          <CFormSelect v-model="categoryFilter" @change="applyFilters">
            <option value="">Tất cả danh mục</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </CFormSelect>
        </CCol>
        <CCol md="2">
          <CButton color="secondary" class="w-100" @click="applyFilters">Lọc</CButton>
        </CCol>
      </CRow>

      <CAlert v-if="error" color="danger">{{ error }}</CAlert>
      <div v-if="loading" class="admin-state"><CSpinner color="primary" /></div>
      <CTable v-else hover responsive align="middle">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell />
            <CTableHeaderCell>Tên sách</CTableHeaderCell>
            <CTableHeaderCell>NXB</CTableHeaderCell>
            <CTableHeaderCell>Giá</CTableHeaderCell>
            <CTableHeaderCell>Tồn</CTableHeaderCell>
            <CTableHeaderCell>Trạng thái</CTableHeaderCell>
            <CTableHeaderCell class="text-end">Thao tác</CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-if="!items.length">
            <CTableDataCell colspan="7" class="text-center text-body-secondary py-4">Không có sách.</CTableDataCell>
          </CTableRow>
          <CTableRow v-for="book in items" :key="book.id">
            <CTableDataCell><BookImagePlaceholder /></CTableDataCell>
            <CTableDataCell>
              <div class="fw-medium">{{ book.title }}</div>
              <small class="text-body-secondary">{{ book.isbn ?? '—' }}</small>
            </CTableDataCell>
            <CTableDataCell>{{ book.publisher?.name ?? '—' }}</CTableDataCell>
            <CTableDataCell>{{ formatVnd(book.price) }}</CTableDataCell>
            <CTableDataCell>{{ book.stock_quantity }}</CTableDataCell>
            <CTableDataCell><StatusBadge kind="book" :value="book.status" /></CTableDataCell>
            <CTableDataCell class="text-end text-nowrap">
              <CButton
                color="primary"
                variant="ghost"
                size="sm"
                @click="router.push({ name: 'admin-book-edit', params: { id: book.id } })"
              >
                <CIcon icon="cil-pencil" />
              </CButton>
              <CButton color="danger" variant="ghost" size="sm" @click="onDelete(book)">
                <CIcon icon="cil-trash" />
              </CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
      <AdminPagination :meta="meta" @page="(p) => setQuery({ page: p })" />
    </CCardBody>
  </CCard>
</template>
