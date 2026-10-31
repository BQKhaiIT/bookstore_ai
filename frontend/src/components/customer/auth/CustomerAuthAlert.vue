<script setup lang="ts">
defineProps<{
  message: string
  variant?: 'danger' | 'success' | 'warning' | 'info'
}>()
</script>

<template>
  <p role="alert" class="ca-alert" :class="`ca-alert--${variant ?? 'danger'}`">
    {{ message }}
  </p>
</template>
