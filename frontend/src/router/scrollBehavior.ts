import { CUSTOMER_HEADER_SCROLL_OFFSET, isAdminRoute, prefersReducedMotion } from '@/utils/customerScroll'
import type { RouterScrollBehavior } from 'vue-router'

export const customerScrollBehavior: RouterScrollBehavior = (to, from, savedPosition) => {
  if (isAdminRoute(to.path) || isAdminRoute(from.path)) {
    if (savedPosition) return savedPosition
    if (isAdminRoute(to.path)) return { top: 0, left: 0 }
    return false
  }

  if (savedPosition) {
    return savedPosition
  }

  if (to.name === 'books' && from.name === 'books') {
    return false
  }

  const behavior = prefersReducedMotion() ? 'auto' : 'auto'

  if (to.hash) {
    return {
      el: to.hash,
      top: CUSTOMER_HEADER_SCROLL_OFFSET,
      behavior,
    }
  }

  return { top: 0, left: 0, behavior }
}
