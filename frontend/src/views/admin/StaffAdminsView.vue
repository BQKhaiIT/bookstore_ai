<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import AuthAlert from '@/components/AuthAlert.vue'
import StatusBadge from '@/components/admin/StatusBadge.vue'
import {
  cancelStaffInvitation,
  fetchStaffOverview,
  inviteStaffAdmin,
  updateStaffStatus,
  type StaffOverview,
} from '@/api/admin'
import { ApiError } from '@/api/errors'
import type { User } from '@/types/user'
import { permissionLabel, userRoleLabel } from '@/utils/adminLabels'

const loading = ref(true)
const error = ref<string | null>(null)
const overview = ref<StaffOverview | null>(null)

const invite = reactive({
  name: '',
  email: '',
  permissions: [] as string[],
})

const submitting = ref(false)

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    overview.value = await fetchStaffOverview()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không tải được dữ liệu.'
  } finally {
    loading.value = false
  }
}

function togglePermission(key: string): void {
  if (invite.permissions.includes(key)) {
    invite.permissions = invite.permissions.filter((p) => p !== key)
  } else {
    invite.permissions.push(key)
  }
}

async function submitInvite(): Promise<void> {
  submitting.value = true
  error.value = null
  try {
    await inviteStaffAdmin({ ...invite, email: invite.email.trim(), name: invite.name.trim() })
    invite.name = ''
    invite.email = ''
    invite.permissions = []
    await load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không gửi được lời mời.'
  } finally {
    submitting.value = false
  }
}

async function blockStaff(user: User): Promise<void> {
  await updateStaffStatus(user.id, 'blocked')
  await load()
}

async function activateStaff(user: User): Promise<void> {
  await updateStaffStatus(user.id, 'active')
  await load()
}

async function cancelInvite(id: number): Promise<void> {
  await cancelStaffInvitation(id)
  await load()
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="admin-page d-flex flex-column gap-4">
    <p class="text-body-secondary small mb-0">Mời Admin, cấp quyền module, khóa hoặc mở khóa tài khoản.</p>

    <AuthAlert v-if="error" :message="error" />
    <div v-if="loading && !overview" class="admin-state"><CSpinner color="primary" /></div>

    <CCard v-if="overview">
      <CCardHeader>Mời Admin mới</CCardHeader>
      <CCardBody>
        <CForm class="row gy-3" @submit.prevent="submitInvite">
          <CCol :md="6">
            <CFormLabel for="staff-name">Họ tên</CFormLabel>
            <CFormInput id="staff-name" v-model="invite.name" required autocomplete="name" />
          </CCol>
          <CCol :md="6">
            <CFormLabel for="staff-email">Email</CFormLabel>
            <CFormInput id="staff-email" v-model="invite.email" type="email" required autocomplete="email" />
          </CCol>
          <CCol :xs="12">
            <CFormLabel>Quyền module</CFormLabel>
            <div class="perm-grid">
              <CFormCheck
                v-for="perm in overview.available_permissions"
                :key="perm"
                :id="`perm-${perm}`"
                :label="permissionLabel(perm)"
                :checked="invite.permissions.includes(perm)"
                @change="togglePermission(perm)"
              />
            </div>
          </CCol>
          <CCol :xs="12">
            <CButton type="submit" color="primary" :disabled="submitting">
              {{ submitting ? 'Đang gửi...' : 'Gửi lời mời email' }}
            </CButton>
          </CCol>
        </CForm>
      </CCardBody>
    </CCard>

    <CCard v-if="overview">
      <CCardHeader>Nhân sự quản trị</CCardHeader>
      <CCardBody class="p-0">
        <CTable hover responsive align="middle" class="mb-0">
          <CTableHead>
            <CTableRow>
              <CTableHeaderCell>Email</CTableHeaderCell>
              <CTableHeaderCell>Vai trò</CTableHeaderCell>
              <CTableHeaderCell>Trạng thái</CTableHeaderCell>
              <CTableHeaderCell>Quyền</CTableHeaderCell>
              <CTableHeaderCell class="text-end">Thao tác</CTableHeaderCell>
            </CTableRow>
          </CTableHead>
          <CTableBody>
            <CTableRow v-if="!overview.staff.length">
              <CTableDataCell colspan="5" class="text-center text-body-secondary py-4">Chưa có nhân sự.</CTableDataCell>
            </CTableRow>
            <CTableRow v-for="user in overview.staff" :key="user.id">
              <CTableDataCell>
                <div class="fw-semibold">{{ user.name }}</div>
                <div class="small text-body-secondary">{{ user.email }}</div>
              </CTableDataCell>
              <CTableDataCell>{{ userRoleLabel[user.role] }}</CTableDataCell>
              <CTableDataCell><StatusBadge kind="user" :value="user.status" /></CTableDataCell>
              <CTableDataCell>
                <div class="d-flex flex-wrap gap-1">
                  <CBadge v-for="perm in user.admin_permissions ?? []" :key="perm" color="secondary">
                    {{ permissionLabel(perm) }}
                  </CBadge>
                  <span v-if="!(user.admin_permissions ?? []).length" class="text-body-secondary">—</span>
                </div>
              </CTableDataCell>
              <CTableDataCell class="text-end">
                <CButton
                  v-if="user.role === 'admin' && user.status === 'active'"
                  size="sm"
                  color="danger"
                  variant="outline"
                  @click="blockStaff(user)"
                >
                  Khóa
                </CButton>
                <CButton
                  v-if="user.role === 'admin' && user.status === 'blocked'"
                  size="sm"
                  color="success"
                  variant="outline"
                  @click="activateStaff(user)"
                >
                  Mở khóa
                </CButton>
              </CTableDataCell>
            </CTableRow>
          </CTableBody>
        </CTable>
      </CCardBody>
    </CCard>

    <CCard v-if="overview?.pending_invitations.length">
      <CCardHeader>Lời mời đang chờ</CCardHeader>
      <CCardBody class="p-0">
        <CListGroup flush>
          <CListGroupItem
            v-for="inv in overview.pending_invitations"
            :key="inv.id"
            class="d-flex flex-wrap justify-content-between align-items-center gap-2"
          >
            <div>
              <div class="fw-semibold">{{ inv.email }}</div>
              <div class="small text-body-secondary">Hết hạn {{ inv.expires_at }}</div>
            </div>
            <CButton size="sm" color="danger" variant="ghost" @click="cancelInvite(inv.id)">Hủy</CButton>
          </CListGroupItem>
        </CListGroup>
      </CCardBody>
    </CCard>
  </div>
</template>
