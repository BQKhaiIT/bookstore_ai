<script setup lang="ts">
import { cn } from '@/lib/utils'
import { Star } from '@lucide/vue'
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    rating?: number
    modelValue?: number
    max?: number
    readonly?: boolean
    size?: 'sm' | 'md' | 'lg'
    class?: string
  }>(),
  { max: 5, readonly: false, size: 'md' },
)

const emit = defineEmits<{ 'update:modelValue': [number] }>()

const value = computed(() => {
  if (props.readonly) return Math.round(props.rating ?? 0)
  return Math.round(props.modelValue ?? props.rating ?? 0)
})

const starSize = computed(() => {
  if (props.size === 'sm') return 'size-3.5'
  if (props.size === 'lg') return 'size-6'
  return 'size-4'
})

function setRating(star: number): void {
  if (props.readonly) return
  emit('update:modelValue', star)
}
</script>

<template>
  <div
    class="inline-flex items-center gap-0.5"
    :class="cn(readonly ? '' : 'gap-1', props.class)"
    role="img"
    :aria-label="`${value} trên ${max} sao`"
  >
    <template v-if="readonly">
      <Star
        v-for="star in max"
        :key="star"
        :class="
          cn(
            starSize,
            star <= value ? 'fill-amber-400 text-amber-400' : 'fill-muted text-muted',
          )
        "
        aria-hidden="true"
      />
    </template>
    <template v-else>
      <button
        v-for="star in max"
        :key="star"
        type="button"
        class="rounded-sm transition-colors hover:scale-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        :aria-label="`${star} sao`"
        @click="setRating(star)"
      >
        <Star
          :class="
            cn(
              starSize,
              star <= value ? 'fill-amber-400 text-amber-400' : 'fill-muted text-muted-foreground/40',
            )
          "
          aria-hidden="true"
        />
      </button>
    </template>
  </div>
</template>
