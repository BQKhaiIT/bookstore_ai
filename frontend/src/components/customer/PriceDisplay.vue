<script setup lang="ts">
import { formatVnd } from '@/utils/format'
import { cn } from '@/lib/utils'

defineProps<{
  amount: number
  size?: 'sm' | 'md' | 'lg'
  class?: string
}>()
</script>

<template>
  <span
    :class="
      cn(
        'font-semibold tabular-nums text-foreground',
        size === 'sm' && 'text-sm',
        size === 'lg' && 'text-xl',
        (!size || size === 'md') && 'text-base',
        $props.class,
      )
    "
  >
    {{ formatVnd(amount) }}
  </span>
</template>
