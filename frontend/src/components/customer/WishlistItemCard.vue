<script setup lang="ts">
import BookCover from '@/components/customer/BookCover.vue'
import PriceDisplay from '@/components/customer/PriceDisplay.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import type { BookSummary } from '@/types/customer'
import { HeartOff, ShoppingBag } from '@lucide/vue'
import { RouterLink } from 'vue-router'

defineProps<{
  book: BookSummary
  busy?: boolean
}>()

const emit = defineEmits<{
  addToCart: []
  remove: []
}>()

function authorLine(book: BookSummary): string {
  return book.authors?.map((a) => a.name).join(', ') || '—'
}
</script>

<template>
  <article
    class="wishlist-card-reveal flex h-full flex-col overflow-hidden rounded-lg border border-border bg-card transition-shadow duration-200 hover:shadow-sm"
  >
    <RouterLink
      :to="{ name: 'book-detail', params: { slug: book.slug } }"
      class="block shrink-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
    >
      <BookCover :title="book.title" :cover-url="book.cover_url" />
    </RouterLink>
    <div class="flex flex-1 flex-col gap-2 p-3 sm:p-4">
      <RouterLink
        :to="{ name: 'book-detail', params: { slug: book.slug } }"
        class="line-clamp-2 text-sm font-semibold leading-snug hover:text-accent"
      >
        {{ book.title }}
      </RouterLink>
      <p class="line-clamp-1 text-xs text-muted-foreground">{{ authorLine(book) }}</p>
      <div class="mt-auto flex flex-wrap items-center gap-2 pt-2">
        <PriceDisplay :amount="book.price" size="sm" />
        <Badge v-if="book.stock_quantity < 1">Hết hàng</Badge>
      </div>
      <div class="mt-3 grid grid-cols-2 gap-2">
        <Button as-child variant="outline" size="sm" class="w-full">
          <RouterLink :to="{ name: 'book-detail', params: { slug: book.slug } }">Chi tiết</RouterLink>
        </Button>
        <Button
          variant="accent"
          size="sm"
          class="w-full"
          :disabled="busy || book.stock_quantity < 1"
          @click="emit('addToCart')"
        >
          <ShoppingBag class="size-4 shrink-0" aria-hidden="true" />
          Giỏ hàng
        </Button>
        <Button
          variant="ghost"
          size="sm"
          class="col-span-2 w-full"
          :disabled="busy"
          aria-label="Bỏ yêu thích"
          @click="emit('remove')"
        >
          <HeartOff class="size-4 shrink-0" aria-hidden="true" />
          Bỏ yêu thích
        </Button>
      </div>
    </div>
  </article>
</template>

<style scoped>
.wishlist-card-reveal {
  animation: wishlist-card-reveal 0.45s ease-out both;
}

@keyframes wishlist-card-reveal {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .wishlist-card-reveal {
    animation: none;
  }
}
</style>
