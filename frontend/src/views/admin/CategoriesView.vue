<script setup lang="ts">
import { createCategory, deleteCategory, fetchCategories, updateCategory } from '@/api/admin'
import { ApiError, firstFieldError } from '@/api/errors'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import { useConfirm } from '@/composables/useConfirm'
import { usePaginatedList } from '@/composables/usePaginatedList'
import { useToastStore } from '@/stores/toast'
import type { Category } from '@/types/admin'
import { formatDateTime } from '@/utils/format'
import { reactive, ref } from 'vue'

const toast = useToastStore()
const { confirm } = useConfirm()
const searchInput = ref('')
const modalVisible = ref(false)
const editing = ref<Category | null>(null)
const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const form = reactive({ name: '', slug: '' })

const { items, meta, loading, error, setQuery, load } = usePaginatedList<Category>(fetchCategories)

function openCreate(): void {
  editing.value = null
  form.name = ''
  form.slug = ''
  fieldErrors.value = {}
  modalVisible.value = true
}

function openEdit(cat: Category): void {
  editing.value = cat
  form.name = cat.name
  form.slug = cat.slug
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
      await updateCategory(editing.value.id, { name: form.name, slug: form.slug || undefined })
      toast.success('Đã cập nhật danh mục.')
    } else {
      await createCategory({ name: form.name, slug: form.slug || undefined })
      toast.success('Đã tạo danh mục.')
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

async function onDelete(cat: Category): Promise<void> {
  const ok = await confirm({ message: `Xóa danh mục "${cat.name}"?` })
  if (!ok) return
  try {
    await deleteCategory(cat.id)
    toast.success('Đã xóa danh mục.')
    await load()
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Không thể xóa.')
  }
}

function search(): void {
  setQuery({ page: 1, search: searchInput.value || undefined })
}
</script>

<template>
  <CCard class="admin-page">
    <CCardHeader class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong>Danh mục</strong>
      <CButton color="primary" @click="openCreate"><CIcon icon="cil-plus" class="me-1" /> Thêm</CButton>
    </CCardHeader>
    <CCardBody>
      <CInputGroup class="admin-filters mb-3" style="max-width: 24rem">
        <CFormInput v-model="searchInput" placeholder="Tìm tên..." @keyup.enter="search" />
        <CButton color="secondary" @click="search">Tìm</CButton>
      </CInputGroup>
      <CAlert v-if="error" color="danger">{{ error }}</CAlert>
      <div v-if="loading" class="admin-state"><CSpinner color="primary" /></div>
      <CTable v-else hover responsive align="middle">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>Tên</CTableHeaderCell>
            <CTableHeaderCell>Slug</CTableHeaderCell>
            <CTableHeaderCell>Cập nhật</CTableHeaderCell>
            <CTableHeaderCell class="text-end">Thao tác</CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-if="!items.length">
            <CTableDataCell colspan="4" class="text-center text-body-secondary py-4">Không có danh mục.</CTableDataCell>
          </CTableRow>
          <CTableRow v-for="cat in items" :key="cat.id">
            <CTableDataCell>{{ cat.name }}</CTableDataCell>
            <CTableDataCell><code>{{ cat.slug }}</code></CTableDataCell>
            <CTableDataCell>{{ formatDateTime(cat.updated_at) }}</CTableDataCell>
            <CTableDataCell class="text-end">
              <CButton color="primary" variant="ghost" size="sm" @click="openEdit(cat)"><CIcon icon="cil-pencil" /></CButton>
              <CButton color="danger" variant="ghost" size="sm" @click="onDelete(cat)"><CIcon icon="cil-trash" /></CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
      <AdminPagination :meta="meta" @page="(p) => setQuery({ page: p })" />
    </CCardBody>
  </CCard>

  <CModal class="admin-modal" alignment="center" :visible="modalVisible" @close="modalVisible = false">
    <CModalHeader><CModalTitle>{{ editing ? 'Sửa danh mục' : 'Thêm danh mục' }}</CModalTitle></CModalHeader>
    <CModalBody>
      <CForm @submit.prevent="save">
        <CFormLabel for="cat-name">Tên *</CFormLabel>
        <CFormInput id="cat-name" v-model="form.name" required :invalid="!!err('name')" class="mb-2" />
        <CFormFeedback invalid>{{ err('name') }}</CFormFeedback>
        <CFormLabel for="cat-slug">Slug</CFormLabel>
        <CFormInput id="cat-slug" v-model="form.slug" :invalid="!!err('slug')" />
        <CFormFeedback invalid>{{ err('slug') }}</CFormFeedback>
      </CForm>
    </CModalBody>
    <CModalFooter>
      <CButton color="secondary" @click="modalVisible = false">Hủy</CButton>
      <CButton color="primary" :disabled="saving" @click="save">{{ saving ? 'Đang lưu...' : 'Lưu' }}</CButton>
    </CModalFooter>
  </CModal>
</template>
