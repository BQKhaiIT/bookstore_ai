export const INFINITE_CAROUSEL_MIN_ITEMS = 5

/** Pixels per second — slow, smooth editorial pace. */
export const INFINITE_CAROUSEL_SPEED_PX_PER_SEC = 36

export function shouldUseInfiniteCarousel(itemCount: number): boolean {
  return itemCount >= INFINITE_CAROUSEL_MIN_ITEMS
}

/** Normalize scroll offset into `[0, setWidth)`. */
export function normalizeLoopOffset(scrollLeft: number, setWidth: number): number {
  if (setWidth <= 0) return 0
  let o = scrollLeft % setWidth
  if (o < 0) o += setWidth
  return o
}

export function nextAutoScrollLeft(
  scrollLeft: number,
  deltaMs: number,
  setWidth: number,
  speedPxPerSec = INFINITE_CAROUSEL_SPEED_PX_PER_SEC,
): number {
  if (setWidth <= 0 || deltaMs <= 0) return scrollLeft
  let next = scrollLeft + (speedPxPerSec * deltaMs) / 1000
  if (next >= setWidth) next -= setWidth
  return next
}

/** One loop cycle duration (seconds) for CSS animation over `cycleWidthPx`. */
export function carouselCycleDurationSec(
  cycleWidthPx: number,
  speedPxPerSec = INFINITE_CAROUSEL_SPEED_PX_PER_SEC,
): number {
  if (cycleWidthPx <= 0 || speedPxPerSec <= 0) return 0
  return cycleWidthPx / speedPxPerSec
}
