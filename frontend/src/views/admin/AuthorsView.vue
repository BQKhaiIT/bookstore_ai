<script setup lang="ts">
import { createAuthor, deleteAuthor, fetchAuthors, updateAuthor } from '@/api/admin'
import { ApiError, firstFieldError } from '@/api/errors'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import { useConfirm } from '@/composables/useConfirm'
import { usePaginatedList } from '@/composables/usePaginatedList'
import { useToastStore } from '@/stores/toast'
import type { Author } from '@/types/admin'
import { reactive, ref } from 'vue'

const toast = useToastStore()
const { confirm } = useConfirm()
const searchInput = ref('')
const modalVisible = ref(false)
const editing = ref<Author | null>(null)
const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const form = reactive({ name: '', biography: '' })

const { items, meta, loading, error, setQuery, load } = usePaginatedList<Author>(fetchAuthors)

function openCreate(): void {
  editing.value = null
  form.name = ''
  form.biography = ''
  fieldErrors.value = {}
  modalVisible.value = true
}

function openEdit(a: Author): void {
  editing.value = a
  form.name = a.name
  form.biography = a.biography ?? ''
  fieldErrors.value = {}
  modalVisible.value = true
}

function err(field: string): string | undefined {
  return firstFieldError(fieldErrors.value, field)
}

async function save(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  try {
    if (editing.value) {
      await updateAuthor(editing.value.id, { name: form.name, biography: form.biography || undefined })
      toast.success('Đã cập nhật tác giả.')
    } else {
      await createAuthor({ name: form.name, biography: form.biography || undefined })
      toast.success('Đã tạo tác giả.')
    }
    modalVisible.value = false
    await load()
  } catch (e) {
    if (e instanceof ApiError) {
      fieldErrors.value = e.errors
      toast.error(e.message)
    }
  } finally {
    saving.value = false
  }
}

async function onDelete(a: Author): Promise<void> {
  const ok = await confirm({ message: `Xóa tác giả "${a.name}"?` })
  if (!ok) return
  try {
    await deleteAuthor(a.id)
    toast.success('Đã xóa tác giả.')
    await load()
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Không thể xóa.')
  }
}
</script>

<template>
  <CCard class="admin-page">
    <CCardHeader>
      <strong>Tác giả</strong>
      <CButton color="primary" @click="openCreate"><CIcon icon="cil-plus" class="me-1" /> Thêm</CButton>
    </CCardHeader>
    <CCardBody>
      <CInputGroup class="admin-filters mb-3" style="max-width: 24rem">
        <CFormInput v-model="searchInput" placeholder="Tìm tên..." @keyup.enter="setQuery({ page: 1, search: searchInput || undefined })" />
        <CButton color="secondary" @click="setQuery({ page: 1, search: searchInput || undefined })">Tìm</CButton>
      </CInputGroup>
      <CAlert v-if="error" color="danger">{{ error }}</CAlert>
      <div v-if="loading" class="admin-state"><CSpinner color="primary" /></div>
      <CTable v-else hover responsive align="middle">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>Tên</CTableHeaderCell>
            <CTableHeaderCell>Tiểu sử</CTableHeaderCell>
            <CTableHeaderCell class="text-end">Thao tác</CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-if="!items.length">
            <CTableDataCell colspan="3" class="text-center text-body-secondary py-4">Không có tác giả.</CTableDataCell>
          </CTableRow>
          <CTableRow v-for="a in items" :key="a.id">
            <CTableDataCell>{{ a.name }}</CTableDataCell>
            <CTableDataCell class="admin-clip text-truncate">{{ a.biography ?? '—' }}</CTableDataCell>
            <CTableDataCell class="text-end">
              <CButton color="primary" variant="ghost" size="sm" @click="openEdit(a)"><CIcon icon="cil-pencil" /></CButton>
              <CButton color="danger" variant="ghost" size="sm" @click="onDelete(a)"><CIcon icon="cil-trash" /></CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
      <AdminPagination :meta="meta" @page="(p) => setQuery({ page: p })" />
    </CCardBody>
  </CCard>

  <CModal class="admin-modal" alignment="center" :visible="modalVisible" @close="modalVisible = false">
    <CModalHeader><CModalTitle>{{ editing ? 'Sửa tác giả' : 'Thêm tác giả' }}</CModalTitle></CModalHeader>
    <CModalBody>
      <CFormLabel for="author-name">Tên *</CFormLabel>
      <CFormInput id="author-name" v-model="form.name" required :invalid="!!err('name')" class="mb-2" />
      <CFormFeedback invalid>{{ err('name') }}</CFormFeedback>
      <CFormLabel for="author-bio">Tiểu sử</CFormLabel>
      <CFormTextarea id="author-bio" v-model="form.biography" rows="3" />
    </CModalBody>
    <CModalFooter>
      <CButton color="secondary" @click="modalVisible = false">Hủy</CButton>
      <CButton color="primary" :disabled="saving" @click="save">{{ saving ? 'Đang lưu...' : 'Lưu' }}</CButton>
    </CModalFooter>
  </CModal>
</template>
