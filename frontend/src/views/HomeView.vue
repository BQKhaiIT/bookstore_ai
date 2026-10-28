<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div class="d-flex flex-column gap-4">
    <CCard class="shadow-sm border-0 overflow-hidden">
      <CCardBody class="p-4 p-md-5">
        <p class="text-uppercase small text-primary fw-semibold mb-2">BookStore AI</p>
        <h1 class="h3 mb-2">Xin chào, {{ auth.user?.name }}</h1>
        <p class="text-body-secondary mb-4">
          Bạn đã đăng nhập thành công. Khám phá sách, quản lý tài khoản hoặc vào khu vực quản trị
          nếu bạn là admin.
        </p>

        <CAlert v-if="!auth.isVerified" color="warning" class="mb-4">
          Email chưa được xác minh. Một số thao tác (thanh toán, đặt hàng) sẽ yêu cầu xác minh.
          <RouterLink to="/verify-email" class="alert-link ms-1">Xác minh ngay</RouterLink>
        </CAlert>

        <div class="d-flex flex-wrap gap-2">
          <RouterLink to="/account" class="btn btn-primary">Quản lý tài khoản</RouterLink>
          <RouterLink
            v-if="auth.isAdmin && auth.isVerified"
            to="/admin/dashboard"
            class="btn btn-outline-primary"
          >
            Bảng quản trị
          </RouterLink>
          <RouterLink v-else-if="auth.isAdmin && !auth.isVerified" to="/verify-email" class="btn btn-outline-secondary">
            Xác minh để vào quản trị
          </RouterLink>
        </div>
      </CCardBody>
    </CCard>

    <CRow class="g-3">
      <CCol :md="4">
        <CCard class="h-100 shadow-sm border-0">
          <CCardBody>
            <h2 class="h6">Vai trò</h2>
            <p class="mb-0 text-capitalize">{{ auth.user?.role }}</p>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol :md="4">
        <CCard class="h-100 shadow-sm border-0">
          <CCardBody>
            <h2 class="h6">Trạng thái tài khoản</h2>
            <p class="mb-0">{{ auth.user?.status === 'active' ? 'Đang hoạt động' : 'Bị khóa' }}</p>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol :md="4">
        <CCard class="h-100 shadow-sm border-0">
          <CCardBody>
            <h2 class="h6">Xác minh email</h2>
            <p class="mb-0">{{ auth.isVerified ? 'Đã xác minh' : 'Chưa xác minh' }}</p>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>
  </div>
</template>
