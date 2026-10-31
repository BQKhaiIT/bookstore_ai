<script setup lang="ts">
import { cn } from '@/lib/utils'
import { ref } from 'vue'

const props = withDefaults(
  defineProps<{
    title: string
    coverUrl?: string | null
    aspect?: 'card' | 'detail' | 'feature'
    class?: string
  }>(),
  { aspect: 'card' },
)

const failed = ref(false)

const aspectClass = {
  card: 'aspect-[2/3] w-full',
  detail: 'aspect-[2/3] w-full max-w-xs mx-auto',
  feature: 'aspect-[2/3] w-full max-w-[220px]',
}
</script>

<template>
  <div
    :class="
      cn(
        'overflow-hidden rounded-md bg-[#ece8e2] ring-1 ring-border/50',
        aspectClass[aspect],
        props.class,
      )
    "
  >
    <img
      v-if="coverUrl && !failed"
      :src="coverUrl"
      :alt="`Bìa sách ${title}`"
      loading="lazy"
      decoding="async"
      class="h-full w-full bg-white object-contain"
      @error="failed = true"
    />
    <div
      v-else
      class="flex h-full w-full items-center justify-center bg-[linear-gradient(160deg,#f5f2ee,#e7e2db)] p-4 text-center"
    >
      <span class="font-display text-lg text-muted-foreground">{{ title.charAt(0) }}</span>
    </div>
  </div>
</template>
