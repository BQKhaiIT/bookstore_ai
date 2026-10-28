<script setup lang="ts">
import { fetchUsers, updateUserStatus } from '@/api/admin'
import { ApiError } from '@/api/errors'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import StatusBadge from '@/components/admin/StatusBadge.vue'
import { useConfirm } from '@/composables/useConfirm'
import { usePaginatedList } from '@/composables/usePaginatedList'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
import type { User } from '@/types/user'
import { userRoleLabel, userStatusLabel } from '@/utils/adminLabels'
import { formatDateTime } from '@/utils/format'
import { ref } from 'vue'

const auth = useAuthStore()
const toast = useToastStore()
const { confirm } = useConfirm()
const searchInput = ref('')
const statusFilter = ref('')
const roleFilter = ref('customer')

const { items, meta, loading, error, setQuery, load } = usePaginatedList<User>(fetchUsers, { role: 'customer' })

function applyFilters(): void {
  setQuery({
    page: 1,
    search: searchInput.value || undefined,
    status: statusFilter.value || undefined,
    role: roleFilter.value || undefined,
  })
}

async function toggleBlock(user: User): Promise<void> {
  const next = user.status === 'active' ? 'blocked' : 'active'
  const ok = await confirm({
    message: next === 'blocked' ? `Khóa tài khoản ${user.email}?` : `Mở khóa ${user.email}?`,
  })
  if (!ok) return
  try {
    await updateUserStatus(user.id, next)
    toast.success(next === 'blocked' ? 'Đã khóa tài khoản.' : 'Đã mở khóa.')
    await load()
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Thao tác thất bại.')
  }
}
</script>

<template>
  <CCard class="admin-page">
    <CCardHeader><strong>Người dùng</strong></CCardHeader>
    <CCardBody>
      <CRow class="admin-filters g-2 mb-3">
        <CCol md="4">
          <CFormInput v-model="searchInput" placeholder="Tên hoặc email..." @keyup.enter="applyFilters" />
        </CCol>
        <CCol md="2">
          <CFormSelect v-model="roleFilter" @change="applyFilters">
            <option v-for="(label, key) in userRoleLabel" :key="key" :value="key">{{ label }}</option>
          </CFormSelect>
        </CCol>
        <CCol md="2">
          <CFormSelect v-model="statusFilter" @change="applyFilters">
            <option value="">Mọi trạng thái</option>
            <option v-for="(label, key) in userStatusLabel" :key="key" :value="key">{{ label }}</option>
          </CFormSelect>
        </CCol>
        <CCol md="2"><CButton color="secondary" class="w-100" @click="applyFilters">Lọc</CButton></CCol>
      </CRow>
      <CAlert v-if="error" color="danger">{{ error }}</CAlert>
      <div v-if="loading" class="admin-state"><CSpinner color="primary" /></div>
      <CTable v-else hover responsive align="middle">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>Tên</CTableHeaderCell>
            <CTableHeaderCell>Email</CTableHeaderCell>
            <CTableHeaderCell>Vai trò</CTableHeaderCell>
            <CTableHeaderCell>Trạng thái</CTableHeaderCell>
            <CTableHeaderCell>Ngày tạo</CTableHeaderCell>
            <CTableHeaderCell class="text-end">Thao tác</CTableHeaderCell>
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-if="!items.length">
            <CTableDataCell colspan="6" class="text-center text-body-secondary py-4">Không có người dùng.</CTableDataCell>
          </CTableRow>
          <CTableRow v-for="user in items" :key="user.id">
            <CTableDataCell>{{ user.name }}</CTableDataCell>
            <CTableDataCell>{{ user.email }}</CTableDataCell>
            <CTableDataCell>{{ userRoleLabel[user.role] }}</CTableDataCell>
            <CTableDataCell><StatusBadge kind="user" :value="user.status" /></CTableDataCell>
            <CTableDataCell>{{ formatDateTime(user.created_at) }}</CTableDataCell>
            <CTableDataCell class="text-end">
              <CButton
                v-if="user.id !== auth.user?.id && user.role === 'customer'"
                :color="user.status === 'active' ? 'danger' : 'success'"
                size="sm"
                variant="outline"
                @click="toggleBlock(user)"
              >
                {{ user.status === 'active' ? 'Khóa' : 'Mở khóa' }}
              </CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
      <AdminPagination :meta="meta" @page="(p) => setQuery({ page: p })" />
    </CCardBody>
  </CCard>
</template>
