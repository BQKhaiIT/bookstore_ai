<script setup lang="ts">
import { adminNavGroups, type AdminNavItem } from '@/admin/nav'
import { cilHome } from '@coreui/icons'
import { useAuthStore } from '@/stores/auth'
import { useSidebarStore } from '@/stores/sidebar'
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

const sidebar = useSidebarStore()
const auth = useAuthStore()
const route = useRoute()

function canSee(item: AdminNavItem): boolean {
  if (item.superAdminOnly) {
    return auth.isSuperAdmin
  }
  if (!item.permission) {
    return true
  }
  return auth.hasPermission(item.permission)
}

const groups = computed(() =>
  adminNavGroups
    .map((group) => ({
      title: group.title,
      items: group.items.filter(canSee),
    }))
    .filter((group) => group.items.length > 0),
)

function isActive(to: string): boolean {
  if (to === '/admin/dashboard') {
    return route.path === to
  }
  return route.path === to || route.path.startsWith(`${to}/`)
}

const desktopNav = window.matchMedia('(min-width: 992px)')

function syncSidebarToViewport(): void {
  sidebar.toggleVisible(desktopNav.matches)
}

onMounted(() => {
  syncSidebarToViewport()
  desktopNav.addEventListener('change', syncSidebarToViewport)
})

onBeforeUnmount(() => {
  desktopNav.removeEventListener('change', syncSidebarToViewport)
})
</script>

<template>
  <CSidebar
    class="border-end admin-sidebar"
    colorScheme="dark"
    position="fixed"
    :unfoldable="sidebar.unfoldable"
    :visible="sidebar.visible"
    @visible-change="(value: boolean) => sidebar.toggleVisible(value)"
  >
    <CSidebarHeader class="border-bottom">
      <RouterLink custom to="/admin/dashboard" v-slot="{ href, navigate }">
        <CSidebarBrand as="a" :href="href" @click="navigate">
          <span class="sidebar-brand-full">BookStore</span>
          <span class="sidebar-brand-narrow">BS</span>
        </CSidebarBrand>
      </RouterLink>
      <CCloseButton class="d-lg-none" dark aria-label="Đóng menu" @click="sidebar.toggleVisible(false)" />
    </CSidebarHeader>
    <CSidebarNav>
      <template v-for="group in groups" :key="group.title">
        <CNavTitle>{{ group.title }}</CNavTitle>
        <CNavItem v-for="item in group.items" :key="item.to">
          <RouterLink :to="item.to" custom v-slot="{ href, navigate }">
            <CNavLink :active="isActive(item.to)" :href="href" @click="navigate">
              <CIcon custom-class-name="nav-icon" :icon="item.icon" />
              {{ item.name }}
            </CNavLink>
          </RouterLink>
        </CNavItem>
      </template>
      <CNavTitle>Liên kết</CNavTitle>
      <CNavItem>
        <RouterLink to="/" custom v-slot="{ href, navigate }">
          <CNavLink :href="href" @click="navigate">
            <CIcon custom-class-name="nav-icon" :icon="cilHome" />
            Cửa hàng
          </CNavLink>
        </RouterLink>
      </CNavItem>
    </CSidebarNav>
    <CSidebarFooter class="border-top d-none d-lg-flex">
      <CSidebarToggler aria-label="Thu gọn sidebar" @click="sidebar.toggleUnfoldable()" />
    </CSidebarFooter>
  </CSidebar>
</template>
