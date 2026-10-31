<script setup lang="ts">
import MediaPlaceholder from '@/components/customer/MediaPlaceholder.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import type { StorefrontBanner } from '@/types/customer'
import { cn } from '@/lib/utils'
import { computed, nextTick, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    banner: StorefrontBanner | null
    aspect?: 'hero' | 'banner'
    priority?: boolean
    class?: string
  }>(),
  { aspect: 'hero', priority: false },
)

const desktop = computed(() => props.banner?.image_url ?? null)
const mobile = computed(() => props.banner?.mobile_image_url ?? props.banner?.image_url ?? null)
const hasImage = computed(() => Boolean(desktop.value || mobile.value))
const imageLoaded = ref(false)
const imgEl = ref<HTMLImageElement | null>(null)

function markLoadedIfReady(el: HTMLImageElement | null): void {
  if (el?.complete && el.naturalWidth > 0) {
    imageLoaded.value = true
  }
}

watch(
  () => desktop.value ?? mobile.value,
  async () => {
    imageLoaded.value = false
    await nextTick()
    markLoadedIfReady(imgEl.value)
  },
)

function onImageLoad(): void {
  imageLoaded.value = true
}
</script>

<template>
  <div v-if="hasImage" :class="cn('relative overflow-hidden rounded-lg', props.class)">
    <Skeleton v-if="!imageLoaded" class="absolute inset-0 h-full w-full rounded-lg" aria-hidden="true" />
    <picture class="block h-full w-full">
      <source v-if="mobile" media="(max-width: 767px)" :srcset="mobile" />
      <img
        ref="imgEl"
        :src="desktop ?? mobile ?? undefined"
        :alt="banner?.title ? `Minh họa: ${banner.title}` : ''"
        :fetchpriority="priority ? 'high' : undefined"
        :loading="priority ? 'eager' : 'lazy'"
        decoding="async"
        class="h-full w-full object-cover transition-opacity duration-300 motion-reduce:transition-none"
        :class="imageLoaded ? 'opacity-100' : 'opacity-0'"
        @load="onImageLoad"
      />
    </picture>
  </div>
  <MediaPlaceholder
    v-else
    :aspect="aspect === 'hero' ? 'hero' : 'wide'"
    :label="banner?.title ?? 'Không gian minh họa'"
    :class="props.class"
  />
</template>
