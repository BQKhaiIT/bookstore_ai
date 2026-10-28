<script setup lang="ts">
import AdminBreadcrumb from '@/layouts/admin/AdminBreadcrumb.vue'
import { useAuthStore } from '@/stores/auth'
import { useSidebarStore } from '@/stores/sidebar'
import { userRoleLabel } from '@/utils/adminLabels'
import type { UserRole } from '@/types/user'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

const sidebar = useSidebarStore()
const auth = useAuthStore()
const router = useRouter()
const scrolled = ref(false)

const initial = computed(() => auth.user?.name?.charAt(0)?.toUpperCase() || 'A')
const roleLabel = computed(() => {
  const role = auth.user?.role
  if (!role) return ''
  return userRoleLabel[role as UserRole] ?? role
})

function onScroll(): void {
  scrolled.value = window.scrollY > 0
  document.querySelector('.admin-shell')?.classList.toggle('is-scrolled', scrolled.value)
}

onMounted(() => {
  onScroll()
  window.addEventListener('scroll', onScroll, { passive: true })
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll)
  document.querySelector('.admin-shell')?.classList.remove('is-scrolled')
})

async function logout(): Promise<void> {
  await auth.logout()
  router.replace({ name: 'admin-login' })
}
</script>

<template>
  <CHeader position="sticky" class="admin-header mb-0 p-0 border-0" :class="{ 'is-scrolled': scrolled }">
    <div class="admin-bar admin-bar-main">
      <CHeaderToggler class="admin-toggler" aria-label="Mở hoặc đóng menu" @click="sidebar.toggleVisible()">
        <CIcon icon="cil-menu" size="lg" />
      </CHeaderToggler>
      <div class="admin-header-title d-lg-none">BookStore</div>
      <CHeaderNav class="ms-auto align-items-center">
        <div class="d-none d-md-flex flex-column align-items-end me-2 lh-sm">
          <span class="fw-semibold small">{{ auth.user?.name }}</span>
          <span class="text-body-secondary" style="font-size: 0.75rem">{{ roleLabel }}</span>
        </div>
        <CDropdown variant="nav-item" placement="bottom-end">
          <CDropdownToggle class="py-0 pe-0" :caret="false" aria-label="Menu tài khoản">
            <CAvatar color="primary" text-color="white" size="md">{{ initial }}</CAvatar>
          </CDropdownToggle>
          <CDropdownMenu class="admin-user-menu">
            <CDropdownHeader class="bg-body-secondary py-2">
              <div class="fw-semibold text-body">{{ auth.user?.name }}</div>
              <div class="small text-body-secondary fw-normal">{{ auth.user?.email }}</div>
            </CDropdownHeader>
            <CDropdownItem component="button" type="button" @click="router.push({ name: 'home' })">
              <CIcon icon="cil-home" class="me-2" /> Về cửa hàng
            </CDropdownItem>
            <CDropdownItem component="button" type="button" @click="router.push({ name: 'account' })">
              <CIcon icon="cil-user" class="me-2" /> Tài khoản
            </CDropdownItem>
            <CDropdownDivider />
            <CDropdownItem component="button" type="button" @click="logout">
              <CIcon icon="cil-account-logout" class="me-2" /> Đăng xuất
            </CDropdownItem>
          </CDropdownMenu>
        </CDropdown>
      </CHeaderNav>
    </div>
    <div class="admin-bar admin-bar-sub">
      <AdminBreadcrumb />
    </div>
  </CHeader>
</template>
