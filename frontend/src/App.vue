<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <div v-if="!auth.initialized" class="app-bootstrap-loading" role="status" aria-live="polite">
    <span class="app-bootstrap-loading__spinner" aria-hidden="true" />
    <span class="sr-only">Đang tải…</span>
  </div>
  <RouterView v-else />
</template>
