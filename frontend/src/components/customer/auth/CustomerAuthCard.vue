<script setup lang="ts">
defineProps<{
  title: string
  subtitle?: string
}>()
</script>

<template>
  <section class="ca-card" aria-labelledby="ca-card-title">
    <h1 id="ca-card-title" class="ca-card__title">{{ title }}</h1>
    <p v-if="subtitle" class="ca-card__subtitle">{{ subtitle }}</p>
    <slot />
  </section>
</template>
