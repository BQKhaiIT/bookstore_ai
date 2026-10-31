<script setup lang="ts">
import '@/assets/styles/admin-coreui.css'
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="admin-auth-shell min-vh-100 d-flex flex-row align-items-center">
    <CContainer>
      <CRow class="justify-content-center">
        <CCol :md="8" :lg="6" :xl="5">
          <RouterView />
        </CCol>
      </CRow>
    </CContainer>
  </div>
</template>

<style scoped>
.admin-auth-shell :deep(.card) {
  border: 0;
  border-radius: var(--admin-radius, 0.5rem);
  box-shadow: var(--admin-shadow, 0 8px 24px rgba(0, 0, 0, 0.18));
}
</style>
