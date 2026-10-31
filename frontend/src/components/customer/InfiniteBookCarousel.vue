<script setup lang="ts">
import BookCard from '@/components/customer/BookCard.vue'
import Button from '@/components/ui/button/Button.vue'
import {
  carouselCycleDurationSec,
  shouldUseInfiniteCarousel,
} from '@/utils/infiniteCarousel'
import { prefersReducedMotion } from '@/utils/customerScroll'
import type { BookSummary } from '@/types/customer'
import { Pause, Play } from '@lucide/vue'
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from 'vue'

const props = withDefaults(
  defineProps<{
    books: BookSummary[]
    ariaLabel?: string
  }>(),
  { ariaLabel: 'Danh sách sách cuộn ngang' },
)

const viewport = ref<HTMLElement | null>(null)
const track = ref<HTMLElement | null>(null)
const cycleWidthPx = ref(0)
const infinite = computed(() => shouldUseInfiniteCarousel(props.books.length))
const reducedMotion = ref(prefersReducedMotion())
const useTransformAuto = computed(() => infinite.value && !reducedMotion.value)

const userPaused = ref(false)
const hoverPaused = ref(false)
const focusPaused = ref(false)
const touchPaused = ref(false)
const tabHidden = ref(typeof document !== 'undefined' && document.visibilityState === 'hidden')
/** Avoid pointer-enter pause while cursor rests over the strip on first paint. */
const hoverPauseArmed = ref(false)

const isPlaying = computed(() => {
  if (!useTransformAuto.value) return false
  return (
    !userPaused.value &&
    !hoverPaused.value &&
    !focusPaused.value &&
    !touchPaused.value &&
    !tabHidden.value &&
    cycleWidthPx.value > 0
  )
})

const showPlayPause = computed(() => useTransformAuto.value)

const animationDurationSec = computed(() => carouselCycleDurationSec(cycleWidthPx.value))

const trackStyle = computed(() => {
  if (!useTransformAuto.value || animationDurationSec.value <= 0) return undefined
  return {
    animationDuration: `${animationDurationSec.value}s`,
    animationPlayState: isPlaying.value ? 'running' : 'paused',
  } as Record<string, string>
})

const displayBooks = computed(() =>
  infinite.value ? [...props.books, ...props.books] : props.books,
)

let resizeObserver: ResizeObserver | null = null
let motionQuery: MediaQueryList | null = null
let touchEndTimer = 0
let hoverArmTimer = 0
let measureRaf = 0

function measureCycleWidth(): void {
  const el = track.value
  if (!el || !infinite.value) {
    cycleWidthPx.value = 0
    return
  }
  const half = el.scrollWidth / 2
  cycleWidthPx.value = half > 0 ? half : 0
}

function scheduleMeasureUntilReady(): void {
  cancelAnimationFrame(measureRaf)
  const attempt = (): void => {
    measureCycleWidth()
    if (useTransformAuto.value && cycleWidthPx.value <= 0) {
      measureRaf = requestAnimationFrame(attempt)
    }
  }
  measureRaf = requestAnimationFrame(attempt)
}

function onPointerEnter(event: PointerEvent): void {
  if (!hoverPauseArmed.value || event.pointerType === 'touch') return
  hoverPaused.value = true
}

function onPointerLeave(event: PointerEvent): void {
  if (event.pointerType === 'touch') return
  hoverPaused.value = false
}

function onFocusIn(event: FocusEvent): void {
  const target = event.target
  if (!(target instanceof HTMLElement)) return
  if (!target.closest('[data-carousel-item]')) return
  focusPaused.value = true
}

function onFocusOut(event: FocusEvent): void {
  const root = track.value
  if (root && event.relatedTarget instanceof Node && root.contains(event.relatedTarget)) return
  focusPaused.value = false
}

function onTouchStart(): void {
  touchPaused.value = true
  window.clearTimeout(touchEndTimer)
}

function onTouchEnd(): void {
  window.clearTimeout(touchEndTimer)
  touchEndTimer = window.setTimeout(() => {
    touchPaused.value = false
  }, 450)
}

function togglePlayPause(): void {
  userPaused.value = !userPaused.value
}

function onKeydown(event: KeyboardEvent): void {
  const el = viewport.value
  if (!el) return
  if (event.key === ' ' || event.key === 'Spacebar') {
    if (!showPlayPause.value) return
    event.preventDefault()
    togglePlayPause()
    return
  }
  if (!infinite.value && !reducedMotion.value) return
  if (event.key === 'ArrowLeft') {
    event.preventDefault()
    const card = el.querySelector<HTMLElement>('[data-carousel-item]')
    const step = card ? card.offsetWidth + 16 : el.clientWidth * 0.8
    el.scrollBy({ left: -step, behavior: reducedMotion.value ? 'auto' : 'smooth' })
  } else if (event.key === 'ArrowRight') {
    event.preventDefault()
    const card = el.querySelector<HTMLElement>('[data-carousel-item]')
    const step = card ? card.offsetWidth + 16 : el.clientWidth * 0.8
    el.scrollBy({ left: step, behavior: reducedMotion.value ? 'auto' : 'smooth' })
  }
}

function onVisibilityChange(): void {
  tabHidden.value = document.visibilityState === 'hidden'
}

function onMotionChange(): void {
  reducedMotion.value = prefersReducedMotion()
  if (reducedMotion.value) userPaused.value = false
}

watch(
  () => props.books,
  async () => {
    await nextTick()
    scheduleMeasureUntilReady()
  },
)

onMounted(async () => {
  await nextTick()
  scheduleMeasureUntilReady()

  resizeObserver = new ResizeObserver(() => {
    measureCycleWidth()
  })
  if (track.value) resizeObserver.observe(track.value)

  motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)')
  motionQuery.addEventListener('change', onMotionChange)
  document.addEventListener('visibilitychange', onVisibilityChange)

  hoverArmTimer = window.setTimeout(() => {
    hoverPauseArmed.value = true
  }, 700)
})

onBeforeUnmount(() => {
  cancelAnimationFrame(measureRaf)
  window.clearTimeout(touchEndTimer)
  window.clearTimeout(hoverArmTimer)
  resizeObserver?.disconnect()
  motionQuery?.removeEventListener('change', onMotionChange)
  document.removeEventListener('visibilitychange', onVisibilityChange)
})
</script>

<template>
  <div class="relative">
    <div v-if="showPlayPause" class="mb-3 flex justify-end">
      <Button
        type="button"
        variant="outline"
        size="sm"
        class="gap-2"
        :aria-pressed="isPlaying"
        :aria-label="isPlaying ? 'Tạm dừng cuộn tự động' : 'Tiếp tục cuộn tự động'"
        @click="togglePlayPause"
      >
        <Pause v-if="isPlaying" class="size-4" aria-hidden="true" />
        <Play v-else class="size-4" aria-hidden="true" />
        <span>{{ isPlaying ? 'Tạm dừng' : 'Phát' }}</span>
      </Button>
    </div>

    <div
      ref="viewport"
      tabindex="0"
      role="region"
      :aria-label="ariaLabel"
      class="-mx-4 overscroll-x-contain px-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 sm:mx-0 sm:px-0"
      :class="
        useTransformAuto
          ? 'overflow-x-hidden'
          : 'overflow-x-auto pb-2 snap-x snap-mandatory [scrollbar-width:none] [&::-webkit-scrollbar]:hidden'
      "
      @pointerenter="onPointerEnter"
      @pointerleave="onPointerLeave"
      @focusin="onFocusIn"
      @focusout="onFocusOut"
      @touchstart.passive="onTouchStart"
      @touchend.passive="onTouchEnd"
      @touchcancel.passive="onTouchEnd"
      @keydown="onKeydown"
    >
      <div
        ref="track"
        class="flex w-max gap-4"
        :class="useTransformAuto && cycleWidthPx > 0 && 'infinite-carousel-track'"
        :style="trackStyle"
      >
        <template v-for="(book, index) in displayBooks" :key="`${book.id}-${index}`">
          <div
            data-carousel-item
            class="w-40 shrink-0 sm:w-44"
            :class="!useTransformAuto && 'snap-start'"
            :aria-hidden="infinite && index >= books.length ? true : undefined"
          >
            <BookCard
              :book="book"
              variant="minimal"
              :focusable="!(infinite && index >= books.length)"
            />
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.infinite-carousel-track {
  animation: infinite-carousel-scroll linear infinite;
  will-change: transform;
}

@keyframes infinite-carousel-scroll {
  from {
    transform: translate3d(0, 0, 0);
  }
  to {
    transform: translate3d(-50%, 0, 0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .infinite-carousel-track {
    animation: none !important;
    transform: none !important;
  }
}
</style>
