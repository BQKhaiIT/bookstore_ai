import { describe, expect, it } from 'vitest'
import { customerScrollBehavior } from '@/router/scrollBehavior'
import type { RouteLocationNormalized } from 'vue-router'

function loc(
  partial: Partial<RouteLocationNormalized> & Pick<RouteLocationNormalized, 'path'>,
): RouteLocationNormalized {
  return {
    name: partial.name,
    path: partial.path,
    hash: partial.hash ?? '',
    matched: [],
    meta: {},
    redirectedFrom: undefined,
    fullPath: partial.path,
    query: {},
    params: {},
  } as RouteLocationNormalized
}

describe('customerScrollBehavior', () => {
  it('restores saved position on customer back navigation', () => {
    const saved = { left: 0, top: 420 }
    const result = customerScrollBehavior(
      loc({ path: '/books', name: 'books' }),
      loc({ path: '/books/slug', name: 'book-detail' }),
      saved,
    )
    expect(result).toEqual(saved)
  })

  it('scrolls to top for catalog to detail', () => {
    const result = customerScrollBehavior(
      loc({ path: '/books/foo', name: 'book-detail' }),
      loc({ path: '/books', name: 'books' }),
      null,
    )
    expect(result).toEqual({ top: 0, left: 0, behavior: 'auto' })
  })

  it('defers scroll when paginating within catalog', () => {
    const result = customerScrollBehavior(
      loc({ path: '/books', name: 'books' }),
      loc({ path: '/books', name: 'books' }),
      null,
    )
    expect(result).toBe(false)
  })

  it('does not force customer scroll when leaving admin', () => {
    const result = customerScrollBehavior(
      loc({ path: '/books', name: 'books' }),
      loc({ path: '/admin/books', name: 'admin-books' }),
      null,
    )
    expect(result).toBe(false)
  })
})
