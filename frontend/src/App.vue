<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div v-if="!auth.initialized" class="min-vh-100 d-flex align-items-center justify-content-center">
    <CSpinner color="primary" />
  </div>
  <RouterView v-else />
</template>
