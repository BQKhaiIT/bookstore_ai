<script setup lang="ts">
import { cilLibrary } from '@coreui/icons'
import { ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    src?: string | null
    width?: string
    height?: string
    alt?: string
  }>(),
  {
    src: null,
    width: '48px',
    height: '64px',
    alt: '',
  },
)

const imageFailed = ref(false)

watch(
  () => props.src,
  () => {
    imageFailed.value = false
  },
)

function onError(): void {
  imageFailed.value = true
}
</script>

<template>
  <img
    v-if="src && !imageFailed"
    :src="src"
    :alt="alt"
    class="book-cover-image flex-shrink-0 object-fit-cover"
    :style="{ width, height }"
    @error="onError"
  />
  <div
    v-else
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

.book-cover-image {
  border-radius: 0.25rem;
  border: 1px solid var(--cui-border-color-translucent, rgba(0, 0, 0, 0.08));
  background-color: var(--cui-secondary-bg, #e9ecef);
}
</style>
