import { describe, expect, it } from 'vitest'
import { isAdminRoute, isCatalogPageOnlyChange } from '@/utils/customerScroll'
import type { BookListParams } from '@/types/customer'

const base: BookListParams = {
  page: 1,
  sort: 'newest',
  per_page: 12,
  in_stock: false,
}

describe('customerScroll', () => {
  it('detects admin paths', () => {
    expect(isAdminRoute('/admin')).toBe(true)
    expect(isAdminRoute('/admin/books')).toBe(true)
    expect(isAdminRoute('/books')).toBe(false)
  })

  it('page-only change when only page differs', () => {
    expect(isCatalogPageOnlyChange({ ...base, page: 1 }, { ...base, page: 2 })).toBe(true)
  })

  it('not page-only when sort changes', () => {
    expect(
      isCatalogPageOnlyChange({ ...base, page: 1, sort: 'newest' }, { ...base, page: 1, sort: 'popular' }),
    ).toBe(false)
  })

  it('not page-only when page unchanged', () => {
    expect(isCatalogPageOnlyChange({ ...base, page: 2 }, { ...base, page: 2 })).toBe(false)
  })
})
