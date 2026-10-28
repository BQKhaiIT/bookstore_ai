<script setup lang="ts">
import { cilLibrary } from '@coreui/icons'

withDefaults(
  defineProps<{
    width?: string
    height?: string
  }>(),
  {
    width: '48px',
    height: '64px',
  },
)
</script>

<template>
  <div
    class="book-image-placeholder d-flex align-items-center justify-content-center flex-shrink-0"
    :style="{ width, height }"
    aria-hidden="true"
  >
    <CIcon :icon="cilLibrary" size="lg" class="text-secondary opacity-50" />
  </div>
</template>

<style scoped>
.book-image-placeholder {
  background-color: var(--cui-secondary-bg, #e9ecef);
  border-radius: 0.25rem;
  border: 1px solid var(--cui-border-color-translucent, rgba(0, 0, 0, 0.08));
}
</style>
