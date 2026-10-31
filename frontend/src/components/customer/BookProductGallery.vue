<script setup lang="ts">
import { cn } from '@/lib/utils'
import { computed, ref, watch } from 'vue'

const props = defineProps<{
  title: string
  coverUrl?: string | null
  class?: string
}>()

const activeIndex = ref(0)
const failed = ref<Set<number>>(new Set())

const images = computed(() => (props.coverUrl ? [props.coverUrl] : []))

watch(
  () => props.coverUrl,
  () => {
    activeIndex.value = 0
    failed.value = new Set()
  },
)

function onError(index: number): void {
  failed.value = new Set([...failed.value, index])
}

const activeSrc = computed(() => {
  const src = images.value[activeIndex.value]
  if (!src || failed.value.has(activeIndex.value)) return null
  return src
})
</script>

<template>
  <div :class="cn('lg:sticky lg:top-24 lg:self-start', props.class)">
    <div
      class="overflow-hidden rounded-lg bg-[#ece8e2] ring-1 ring-border/60 aspect-[2/3] w-full max-w-md mx-auto lg:max-w-none"
    >
      <img
        v-if="activeSrc"
        :src="activeSrc"
        :alt="`Bìa sách ${title}`"
        class="h-full w-full bg-white object-contain"
        decoding="async"
        fetchpriority="high"
        @error="onError(activeIndex)"
      />
      <div
        v-else
        class="flex h-full w-full items-center justify-center bg-[linear-gradient(160deg,#f5f2ee,#e7e2db)] p-6 text-center"
      >
        <span class="font-display text-3xl text-muted-foreground">{{ title.charAt(0) }}</span>
      </div>
    </div>

    <ul
      v-if="images.length > 1"
      class="mt-3 flex gap-2 overflow-x-auto pb-1"
      role="list"
      aria-label="Ảnh sách"
    >
      <li v-for="(src, index) in images" :key="`${src}-${index}`">
        <button
          type="button"
          class="size-16 shrink-0 overflow-hidden rounded-md ring-2 ring-offset-2 ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-ring"
          :class="index === activeIndex ? 'ring-foreground' : 'ring-transparent hover:ring-border'"
          :aria-label="`Ảnh ${index + 1}`"
          :aria-current="index === activeIndex ? 'true' : undefined"
          @click="activeIndex = index"
        >
          <img
            v-if="!failed.has(index)"
            :src="src"
            alt=""
            class="h-full w-full bg-white object-cover"
            loading="lazy"
            @error="onError(index)"
          />
          <span v-else class="flex h-full w-full items-center justify-center bg-muted text-xs text-muted-foreground">—</span>
        </button>
      </li>
    </ul>
  </div>
</template>
