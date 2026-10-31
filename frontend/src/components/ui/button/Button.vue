<script setup lang="ts">
import { cn } from '@/lib/utils'
import { buttonVariants } from '@/components/ui/button/index'
import { Primitive } from 'reka-ui'
import { computed } from 'vue'

type Variant = 'default' | 'secondary' | 'outline' | 'ghost' | 'accent' | 'link'
type Size = 'default' | 'sm' | 'lg' | 'icon'

const props = withDefaults(
  defineProps<{
    variant?: Variant
    size?: Size
    class?: string
    as?: string
    asChild?: boolean
  }>(),
  {
    variant: 'default',
    size: 'default',
    as: 'button',
  },
)

const classes = computed(() => cn(buttonVariants({ variant: props.variant, size: props.size }), props.class))
</script>

<template>
  <Primitive :as="as" :as-child="asChild" :class="classes">
    <slot />
  </Primitive>
</template>
