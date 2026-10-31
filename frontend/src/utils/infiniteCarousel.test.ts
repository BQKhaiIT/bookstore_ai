import { describe, expect, it } from 'vitest'
import {
  INFINITE_CAROUSEL_SPEED_PX_PER_SEC,
  carouselCycleDurationSec,
  nextAutoScrollLeft,
  normalizeLoopOffset,
  shouldUseInfiniteCarousel,
} from '@/utils/infiniteCarousel'

describe('infiniteCarousel', () => {
  it('enables infinite mode from 5 items', () => {
    expect(shouldUseInfiniteCarousel(4)).toBe(false)
    expect(shouldUseInfiniteCarousel(5)).toBe(true)
    expect(shouldUseInfiniteCarousel(20)).toBe(true)
  })

  it('normalizes loop offset', () => {
    expect(normalizeLoopOffset(0, 100)).toBe(0)
    expect(normalizeLoopOffset(150, 100)).toBe(50)
    expect(normalizeLoopOffset(-10, 100)).toBe(90)
  })

  it('advances auto scroll and wraps', () => {
    const step = nextAutoScrollLeft(0, 1000, 200, INFINITE_CAROUSEL_SPEED_PX_PER_SEC)
    expect(step).toBe(36)
    expect(nextAutoScrollLeft(190, 1000, 200)).toBe(26)
  })

  it('computes css cycle duration', () => {
    expect(carouselCycleDurationSec(360, 36)).toBe(10)
  })
})
