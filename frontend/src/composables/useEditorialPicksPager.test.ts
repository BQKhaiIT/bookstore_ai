import { describe, expect, it } from 'vitest'
import {
  editorialPlaceholderCount,
  editorialRangeLabel,
  editorialVisibleSlice,
} from '@/composables/useEditorialPicksPager'
import type { BookSummary } from '@/types/customer'

function book(id: number): BookSummary {
  return {
    id,
    isbn: null,
    title: `Book ${id}`,
    slug: `book-${id}`,
    description: null,
    price: 100_000,
    stock_quantity: 1,
    cover_url: null,
    status: 'published',
  }
}

describe('useEditorialPicksPager helpers', () => {
  it('labels range for partial last group', () => {
    expect(editorialRangeLabel(0, 12)).toBe('1–3 / 12')
    expect(editorialRangeLabel(1, 12)).toBe('4–6 / 12')
    expect(editorialRangeLabel(2, 7)).toBe('7–7 / 7')
  })

  it('slices visible books by group', () => {
    const items = [1, 2, 3, 4, 5, 6, 7].map(book)
    expect(editorialVisibleSlice(items, 0)).toHaveLength(3)
    expect(editorialVisibleSlice(items, 2)).toHaveLength(1)
  })

  it('pads placeholder count for incomplete groups', () => {
    expect(editorialPlaceholderCount(1)).toBe(2)
    expect(editorialPlaceholderCount(3)).toBe(0)
  })
})
