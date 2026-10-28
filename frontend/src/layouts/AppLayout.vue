<script setup lang="ts">
import { RouterView } from 'vue-router'
import AppHeader from '@/components/AppHeader.vue'
</script>

<template>
  <div class="min-vh-100 bg-light d-flex flex-column">
    <AppHeader />
    <main class="py-4 flex-grow-1">
      <CContainer>
        <RouterView />
      </CContainer>
    </main>
  </div>
</template>
