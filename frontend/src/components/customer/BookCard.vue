<script setup lang="ts">
import BookCover from '@/components/customer/BookCover.vue'
import PriceDisplay from '@/components/customer/PriceDisplay.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import type { BookSummary } from '@/types/customer'
import { cn } from '@/lib/utils'
import { RouterLink } from 'vue-router'

withDefaults(
  defineProps<{
    book: BookSummary
    variant?: 'grid' | 'horizontal' | 'minimal'
    /** When false, links stay clickable but skip tab order (carousel clones). */
    focusable?: boolean
  }>(),
  { variant: 'grid', focusable: true },
)

function authorLine(book: BookSummary): string {
  return book.authors?.map((a) => a.name).join(', ') || '—'
}
</script>

<template>
  <article
    v-if="variant === 'horizontal'"
    class="group flex gap-4 rounded-lg border border-border bg-card p-3 transition-shadow duration-200 hover:shadow-sm"
  >
    <RouterLink
      :to="{ name: 'book-detail', params: { slug: book.slug } }"
      class="shrink-0 w-20 cursor-pointer"
    >
      <BookCover :title="book.title" :cover-url="book.cover_url" aspect="card" />
    </RouterLink>
    <div class="flex min-w-0 flex-1 flex-col justify-center gap-1">
      <RouterLink
        :to="{ name: 'book-detail', params: { slug: book.slug } }"
        class="line-clamp-2 font-medium leading-snug group-hover:text-accent"
      >
        {{ book.title }}
      </RouterLink>
      <p class="line-clamp-1 text-xs text-muted-foreground">{{ authorLine(book) }}</p>
      <div class="mt-1 flex items-center gap-2">
        <PriceDisplay :amount="book.price" size="sm" />
        <Badge v-if="book.stock_quantity < 1">Hết hàng</Badge>
      </div>
    </div>
  </article>

  <article
    v-else
    :class="
      cn(
        'group overflow-hidden rounded-lg border border-border bg-card transition-shadow duration-200 hover:shadow-sm',
        variant === 'minimal' && 'border-0 bg-transparent shadow-none hover:shadow-none',
      )
    "
  >
    <RouterLink
      :to="{ name: 'book-detail', params: { slug: book.slug } }"
      class="flex h-full cursor-pointer flex-col"
      :tabindex="focusable ? undefined : -1"
    >
      <BookCover :title="book.title" :cover-url="book.cover_url" />
      <div :class="cn('flex flex-1 flex-col gap-1 p-3', variant === 'minimal' && 'px-0 pt-3')">
        <h3 class="line-clamp-2 text-sm font-semibold leading-snug group-hover:text-accent">
          {{ book.title }}
        </h3>
        <p class="line-clamp-1 text-xs text-muted-foreground">{{ authorLine(book) }}</p>
        <div class="mt-auto flex items-center justify-between gap-2 pt-2">
          <PriceDisplay :amount="book.price" size="sm" />
          <Badge v-if="book.stock_quantity < 1">Hết hàng</Badge>
        </div>
      </div>
    </RouterLink>
  </article>
</template>
