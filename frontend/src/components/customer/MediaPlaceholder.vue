<script setup lang="ts">
import { cn } from '@/lib/utils'

withDefaults(
  defineProps<{
    label?: string
    aspect?: 'hero' | 'banner' | 'wide' | 'book'
    class?: string
  }>(),
  {
    label: 'Không gian minh họa',
    aspect: 'hero',
  },
)

const aspectClass: Record<string, string> = {
  hero: 'aspect-[4/5]',
  banner: 'aspect-[21/9] min-h-[140px]',
  wide: 'aspect-[16/10]',
  book: 'aspect-[2/3]',
}
</script>

<template>
  <div
    :class="
      cn(
        'relative overflow-hidden rounded-lg bg-[#e8e4df] ring-1 ring-border/60',
        aspectClass[aspect],
        $props.class,
      )
    "
    role="img"
    :aria-label="label"
  >
    <div class="absolute inset-0 bg-[linear-gradient(145deg,#ebe6e0_0%,#d9d3cb_100%)]" />
    <p class="absolute bottom-3 left-3 max-w-[12rem] text-xs font-medium text-muted-foreground">
      {{ label }}
    </p>
  </div>
</template>
