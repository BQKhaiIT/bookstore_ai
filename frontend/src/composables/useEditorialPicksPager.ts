import type { BookSummary } from '@/types/customer'
import { computed, ref, watch, type Ref } from 'vue'

export const EDITORIAL_PICKS_PAGE_SIZE = 3

export function editorialRangeLabel(groupIndex: number, total: number, pageSize = EDITORIAL_PICKS_PAGE_SIZE): string {
  if (total <= 0) return '0 / 0'
  const start = groupIndex * pageSize + 1
  const end = Math.min((groupIndex + 1) * pageSize, total)
  return `${start}–${end} / ${total}`
}

export function editorialVisibleSlice(
  books: BookSummary[],
  groupIndex: number,
  pageSize = EDITORIAL_PICKS_PAGE_SIZE,
): BookSummary[] {
  return books.slice(groupIndex * pageSize, groupIndex * pageSize + pageSize)
}

export function editorialPlaceholderCount(visibleCount: number, pageSize = EDITORIAL_PICKS_PAGE_SIZE): number {
  if (visibleCount <= 0 || visibleCount >= pageSize) return 0
  return pageSize - visibleCount
}

export function useEditorialPicksPager(books: Ref<BookSummary[]>, pageSize = EDITORIAL_PICKS_PAGE_SIZE) {
  const groupIndex = ref(0)

  const total = computed(() => books.value.length)
  const totalGroups = computed(() => Math.max(1, Math.ceil(total.value / pageSize)))
  const showNavigation = computed(() => total.value > pageSize)

  const visibleBooks = computed(() => editorialVisibleSlice(books.value, groupIndex.value, pageSize))
  const placeholderCount = computed(() => editorialPlaceholderCount(visibleBooks.value.length, pageSize))
  const rangeLabel = computed(() => editorialRangeLabel(groupIndex.value, total.value, pageSize))

  const canPrev = computed(() => groupIndex.value > 0)
  const canNext = computed(() => groupIndex.value < totalGroups.value - 1)

  function prev(): void {
    if (canPrev.value) groupIndex.value -= 1
  }

  function next(): void {
    if (canNext.value) groupIndex.value += 1
  }

  watch(total, () => {
    groupIndex.value = 0
  })

  return {
    groupIndex,
    visibleBooks,
    placeholderCount,
    rangeLabel,
    showNavigation,
    canPrev,
    canNext,
    prev,
    next,
  }
}
