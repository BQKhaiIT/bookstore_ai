<script setup lang="ts">
defineProps<{
  message: string
  color?: 'danger' | 'success' | 'warning' | 'info'
}>()
</script>

<template>
  <CAlert :color="color ?? 'danger'" role="alert" class="mb-0">
    {{ message }}
  </CAlert>
</template>
