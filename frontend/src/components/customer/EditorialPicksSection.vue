<script setup lang="ts">
import BookCard from '@/components/customer/BookCard.vue'
import MediaPlaceholder from '@/components/customer/MediaPlaceholder.vue'
import SectionHeading from '@/components/customer/SectionHeading.vue'
import StorefrontBannerImage from '@/components/customer/StorefrontBannerImage.vue'
import Button from '@/components/ui/button/Button.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import { useEditorialPicksPager } from '@/composables/useEditorialPicksPager'
import { prefersReducedMotion } from '@/utils/customerScroll'
import type { BookSummary, StorefrontBanner } from '@/types/customer'
import { ChevronLeft, ChevronRight } from '@lucide/vue'
import { computed, ref, toRef } from 'vue'

const props = withDefaults(
  defineProps<{
    books: BookSummary[]
    loading?: boolean
    editorialBanner?: StorefrontBanner | null
  }>(),
  {
    loading: false,
    editorialBanner: null,
  },
)

const booksRef = toRef(props, 'books')
const {
  groupIndex,
  visibleBooks,
  placeholderCount,
  rangeLabel,
  showNavigation,
  canPrev,
  canNext,
  prev,
  next,
} = useEditorialPicksPager(booksRef)

const mobileTrack = ref<HTMLElement | null>(null)

const showSection = computed(() => props.loading || props.books.length > 0)

function scrollMobile(dir: -1 | 1): void {
  const el = mobileTrack.value
  if (!el) return
  const card = el.querySelector<HTMLElement>('[data-editorial-mobile-item]')
  const step = card ? card.offsetWidth + 16 : el.clientWidth * 0.88
  el.scrollBy({ left: dir * step, behavior: prefersReducedMotion() ? 'auto' : 'smooth' })
}

function onKeydown(event: KeyboardEvent): void {
  if (!showNavigation.value) return
  if (event.key === 'ArrowLeft') {
    event.preventDefault()
    prev()
  } else if (event.key === 'ArrowRight') {
    event.preventDefault()
    next()
  }
}
</script>

<template>
  <section v-if="showSection" class="py-14 lg:py-16">
    <CustomerContainer>
      <SectionHeading
        title="Biên tập viên gợi ý"
        description="Tuyển chọn nổi bật — bố cục dễ so sánh, sẵn sàng gợi ý cá nhân hóa."
        :to="{ name: 'books' }"
        link-label="Danh mục đầy đủ"
      />

      <div v-if="loading" class="mt-8 grid gap-8 lg:grid-cols-[45%_1fr] lg:gap-10">
        <Skeleton class="hidden aspect-[16/10] w-full lg:block" />
        <div class="space-y-3">
          <Skeleton v-for="i in 3" :key="i" class="h-[5.5rem] w-full" />
        </div>
      </div>

      <div
        v-else
        class="mt-8 grid min-w-0 gap-8 lg:grid-cols-[45%_1fr] lg:items-stretch lg:gap-10"
        tabindex="0"
        role="region"
        aria-label="Biên tập viên gợi ý"
        @keydown="onKeydown"
      >
        <div class="hidden min-w-0 lg:block">
          <StorefrontBannerImage
            v-if="editorialBanner"
            :banner="editorialBanner"
            aspect="banner"
            class="aspect-[16/10] h-full w-full"
          />
          <MediaPlaceholder v-else aspect="wide" label="Bộ sưu tập — minh họa" class="aspect-[16/10] h-full w-full" />
        </div>

        <!-- Desktop: paginated groups of 3 -->
        <div class="hidden min-w-0 flex-col lg:flex">
          <div
            v-if="showNavigation"
            class="mb-4 flex flex-wrap items-center justify-between gap-3"
          >
            <p class="text-sm tabular-nums text-muted-foreground" aria-live="polite">
              {{ rangeLabel }}
            </p>
            <div class="flex items-center gap-2">
              <Button
                type="button"
                variant="outline"
                size="icon"
                class="size-9"
                :disabled="!canPrev"
                aria-label="Nhóm trước"
                @click="prev"
              >
                <ChevronLeft class="size-4" />
              </Button>
              <Button
                type="button"
                variant="outline"
                size="icon"
                class="size-9"
                :disabled="!canNext"
                aria-label="Nhóm sau"
                @click="next"
              >
                <ChevronRight class="size-4" />
              </Button>
            </div>
          </div>

          <div class="min-h-[18.5rem] flex-1">
            <Transition name="editorial-group" mode="out-in">
              <div :key="groupIndex" class="flex h-full flex-col justify-center gap-3">
                <BookCard
                  v-for="book in visibleBooks"
                  :key="book.id"
                  :book="book"
                  variant="horizontal"
                  class="min-h-[5.5rem]"
                />
                <div
                  v-for="n in placeholderCount"
                  :key="`pad-${groupIndex}-${n}`"
                  class="min-h-[5.5rem] rounded-lg border border-transparent"
                  aria-hidden="true"
                />
              </div>
            </Transition>
          </div>
        </div>

        <!-- Mobile: horizontal swipe + nav -->
        <div class="min-w-0 lg:hidden">
          <div
            v-if="showNavigation"
            class="mb-3 flex items-center justify-between gap-2"
          >
            <p class="text-xs tabular-nums text-muted-foreground">{{ books.length }} đầu sách</p>
            <div class="flex gap-2">
              <Button type="button" variant="outline" size="icon" class="size-9" aria-label="Cuộn trái" @click="scrollMobile(-1)">
                <ChevronLeft class="size-4" />
              </Button>
              <Button type="button" variant="outline" size="icon" class="size-9" aria-label="Cuộn phải" @click="scrollMobile(1)">
                <ChevronRight class="size-4" />
              </Button>
            </div>
          </div>
          <div
            ref="mobileTrack"
            class="-mx-4 flex gap-4 overflow-x-auto overscroll-x-contain px-4 pb-2 snap-x snap-mandatory [scrollbar-width:thin]"
          >
            <div
              v-for="book in books"
              :key="book.id"
              data-editorial-mobile-item
              class="w-[min(88vw,20rem)] shrink-0 snap-start"
            >
              <BookCard :book="book" variant="horizontal" />
            </div>
          </div>
        </div>
      </div>
    </CustomerContainer>
  </section>
</template>

<style scoped>
.editorial-group-enter-active,
.editorial-group-leave-active {
  transition:
    opacity 0.22s ease,
    transform 0.22s ease;
}

.editorial-group-enter-from,
.editorial-group-leave-to {
  opacity: 0;
  transform: translateX(0.5rem);
}

@media (prefers-reduced-motion: reduce) {
  .editorial-group-enter-active,
  .editorial-group-leave-active {
    transition: none;
  }

  .editorial-group-enter-from,
  .editorial-group-leave-to {
    transform: none;
  }
}
</style>
