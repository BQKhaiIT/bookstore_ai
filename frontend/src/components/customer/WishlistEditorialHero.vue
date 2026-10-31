<script setup lang="ts">
import BookCover from '@/components/customer/BookCover.vue'
import MediaPlaceholder from '@/components/customer/MediaPlaceholder.vue'
import type { BookSummary } from '@/types/customer'
import { computed } from 'vue'

const props = defineProps<{
  books: BookSummary[]
  total: number
}>()

const heroBooks = computed(() => props.books.slice(0, 3))
const primary = computed(() => heroBooks.value[0] ?? null)
const secondary = computed(() => heroBooks.value[1] ?? null)
const tertiary = computed(() => heroBooks.value[2] ?? null)
</script>

<template>
  <section class="border-b border-border bg-card">
    <div
      class="mx-auto grid w-full max-w-7xl gap-8 px-4 py-10 sm:px-6 lg:grid-cols-12 lg:items-center lg:gap-10 lg:py-14 lg:px-8"
    >
      <div class="wishlist-reveal lg:col-span-5">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Danh sách của bạn</p>
        <h1 class="mt-3 font-display text-4xl font-semibold leading-[1.08] text-balance sm:text-5xl">
          Sách yêu thích
        </h1>
        <p class="mt-4 max-w-md text-base leading-relaxed text-muted-foreground">
          <template v-if="total > 0">
            {{ total }} cuốn đã lưu — quay lại bất cứ lúc nào để đặt hàng hoặc bỏ khỏi danh sách.
          </template>
          <template v-else>
            Lưu những cuốn sách bạn muốn đọc — chúng sẽ hiển thị tại đây.
          </template>
        </p>
      </div>

      <div
        class="wishlist-reveal wishlist-reveal-delay-1 grid grid-cols-12 gap-3 lg:col-span-7 lg:gap-4"
        aria-hidden="true"
      >
        <div class="col-span-7 row-span-2 min-h-[220px] sm:min-h-[280px]">
          <BookCover
            v-if="primary"
            :title="primary.title"
            :cover-url="primary.cover_url"
            aspect="card"
            class="h-full max-w-none shadow-sm"
          />
          <MediaPlaceholder v-else aspect="book" label="Chưa có bìa" class="h-full min-h-[220px]" />
        </div>
        <div class="col-span-5 min-h-[100px]">
          <BookCover
            v-if="secondary"
            :title="secondary.title"
            :cover-url="secondary.cover_url"
            aspect="card"
            class="h-full max-w-none shadow-sm"
          />
          <MediaPlaceholder v-else aspect="book" label="—" class="h-full min-h-[100px]" />
        </div>
        <div class="col-span-5 min-h-[100px]">
          <BookCover
            v-if="tertiary"
            :title="tertiary.title"
            :cover-url="tertiary.cover_url"
            aspect="card"
            class="h-full max-w-none shadow-sm"
          />
          <MediaPlaceholder v-else aspect="book" label="—" class="h-full min-h-[100px]" />
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.wishlist-reveal {
  animation: wishlist-reveal 0.55s ease-out both;
}

.wishlist-reveal-delay-1 {
  animation-delay: 0.08s;
}

@keyframes wishlist-reveal {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wishlist-reveal {
    animation: none;
  }
}
</style>
