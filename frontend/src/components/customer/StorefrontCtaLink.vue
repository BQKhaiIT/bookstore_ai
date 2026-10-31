<script setup lang="ts">
import { isInternalBannerLink } from '@/utils/bannerLink'
import { RouterLink } from 'vue-router'

defineProps<{
  href?: string | null
  class?: string
}>()
</script>

<template>
  <RouterLink v-if="href && isInternalBannerLink(href)" :to="href.trim()" :class="$props.class">
    <slot />
  </RouterLink>
  <a
    v-else-if="href"
    :href="href.trim()"
    rel="noopener noreferrer"
    target="_blank"
    :class="$props.class"
  >
    <slot />
  </a>
  <span v-else :class="$props.class"><slot /></span>
</template>
