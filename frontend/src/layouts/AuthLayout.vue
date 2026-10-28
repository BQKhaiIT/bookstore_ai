<script setup lang="ts">
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="bg-body-tertiary min-vh-100 d-flex flex-row align-items-center py-4">
    <CContainer>
      <CRow class="justify-content-center">
        <CCol :md="8" :lg="6" :xl="5">
          <div class="d-flex flex-column gap-3 mb-3 text-center">
            <div class="auth-brand-title fs-3">BookStore AI</div>
            <p class="auth-subtitle mb-0 small">
              Nền tảng mua sách thông minh — đăng nhập để tiếp tục
            </p>
          </div>
          <RouterView />
        </CCol>
      </CRow>
    </CContainer>
  </div>
</template>
