<script setup lang="ts">
import { createPublisher, deletePublisher, fetchPublishers, updatePublisher } from '@/api/admin'
import { ApiError, firstFieldError } from '@/api/errors'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import { useConfirm } from '@/composables/useConfirm'
import { usePaginatedList } from '@/composables/usePaginatedList'
import { useToastStore } from '@/stores/toast'
import type { Publisher } from '@/types/admin'
import { reactive, ref } from 'vue'

const toast = useToastStore()
const { confirm } = useConfirm()
const searchInput = ref('')
const modalVisible = ref(false)
const editing = ref<Publisher | null>(null)
const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const form = reactive({ name: '' })

const { items, meta, loading, error, setQuery, load } = usePaginatedList<Publisher>(fetchPublishers)

function openCreate(): void {
  editing.value = null
  form.name = ''
  fieldErrors.value = {}
  modalVisible.value = true
}

function openEdit(p: Publisher): void {
  editing.value = p
  form.name = p.name
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
      await updatePublisher(editing.value.id, { name: form.name })
      toast.success('Đã cập nhật NXB.')
    } else {
      await createPublisher({ name: form.name })
      toast.success('Đã tạo NXB.')
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

async function onDelete(p: Publisher): Promise<void> {
  const ok = await confirm({ message: `Xóa NXB "${p.name}"?` })
  if (!ok) return
  try {
    await deletePublisher(p.id)
    toast.success('Đã xóa NXB.')
    await load()
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Không thể xóa.')
  }
}
</script>

<template>
  <CCard class="admin-page">
    <CCardHeader>
      <strong>Nhà xuất bản</strong>
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
            <CTableHeaderCell class="text-end">Thao tác</CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-if="!items.length">
            <CTableDataCell colspan="2" class="text-center text-body-secondary py-4">Không có NXB.</CTableDataCell>
          </CTableRow>
          <CTableRow v-for="p in items" :key="p.id">
            <CTableDataCell>{{ p.name }}</CTableDataCell>
            <CTableDataCell class="text-end">
              <CButton color="primary" variant="ghost" size="sm" @click="openEdit(p)"><CIcon icon="cil-pencil" /></CButton>
              <CButton color="danger" variant="ghost" size="sm" @click="onDelete(p)"><CIcon icon="cil-trash" /></CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
      <AdminPagination :meta="meta" @page="(p) => setQuery({ page: p })" />
    </CCardBody>
  </CCard>

  <CModal class="admin-modal" alignment="center" :visible="modalVisible" @close="modalVisible = false">
    <CModalHeader><CModalTitle>{{ editing ? 'Sửa NXB' : 'Thêm NXB' }}</CModalTitle></CModalHeader>
    <CModalBody>
      <CFormLabel for="pub-name">Tên *</CFormLabel>
      <CFormInput id="pub-name" v-model="form.name" required :invalid="!!err('name')" />
      <CFormFeedback invalid>{{ err('name') }}</CFormFeedback>
    </CModalBody>
    <CModalFooter>
      <CButton color="secondary" @click="modalVisible = false">Hủy</CButton>
      <CButton color="primary" :disabled="saving" @click="save">{{ saving ? 'Đang lưu...' : 'Lưu' }}</CButton>
    </CModalFooter>
  </CModal>
</template>
