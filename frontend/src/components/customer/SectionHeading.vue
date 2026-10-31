<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'
import { RouterLink } from 'vue-router'

defineProps<{
  title: string
  description?: string
  to?: RouteLocationRaw
  linkLabel?: string
}>()
</script>

<template>
  <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <h2 class="font-display text-2xl font-semibold sm:text-3xl">{{ title }}</h2>
      <p v-if="description" class="mt-1 max-w-2xl text-sm text-muted-foreground">{{ description }}</p>
    </div>
    <RouterLink
      v-if="to && linkLabel"
      :to="to"
      class="text-sm font-medium text-accent hover:text-accent/80"
    >
      {{ linkLabel }} →
    </RouterLink>
  </div>
</template>
