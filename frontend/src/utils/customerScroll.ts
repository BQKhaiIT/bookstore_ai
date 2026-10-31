import type { BookListParams } from '@/types/customer'

/** Sticky customer header (`top-0` + content); matches Tailwind `scroll-mt-24`. */
export const CUSTOMER_HEADER_SCROLL_OFFSET = 96

export function isAdminRoute(path: string): boolean {
  return path.startsWith('/admin')
}

export function prefersReducedMotion(): boolean {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return false
  }
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

export function scrollElementIntoView(el: HTMLElement | null | undefined): void {
  if (!el) return
  el.scrollIntoView({
    block: 'start',
    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
  })
}

export function isCatalogPageOnlyChange(prev: BookListParams, next: BookListParams): boolean {
  if (prev.page === next.page) return false

  return (
    prev.search === next.search &&
    prev.category_id === next.category_id &&
    prev.author_id === next.author_id &&
    prev.publisher_id === next.publisher_id &&
    prev.min_price === next.min_price &&
    prev.max_price === next.max_price &&
    prev.in_stock === next.in_stock &&
    prev.sort === next.sort &&
    prev.per_page === next.per_page
  )
}
