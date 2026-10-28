<script setup lang="ts">
import ConfirmModal from '@/components/admin/ConfirmModal.vue'
import ToastContainer from '@/components/admin/ToastContainer.vue'
import AdminHeader from '@/layouts/admin/AdminHeader.vue'
import AdminSidebar from '@/layouts/admin/AdminSidebar.vue'
</script>

<template>
  <div class="admin-shell">
    <AdminSidebar />
    <div class="wrapper d-flex flex-column min-vh-100">
      <AdminHeader />
      <div class="body flex-grow-1">
        <div class="admin-content">
          <router-view />
        </div>
      </div>
      <CFooter class="admin-footer px-0">
        <div class="admin-bar admin-footer-bar">
          <span class="fw-semibold">BookStore AI</span>
          <span class="ms-auto text-body-secondary">Khu vực quản trị</span>
        </div>
      </CFooter>
    </div>
    <ToastContainer />
    <ConfirmModal />
  </div>
</template>
