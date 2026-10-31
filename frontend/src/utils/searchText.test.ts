import { describe, expect, it } from 'vitest'
import { matchesSearch, normalizeSearchText } from './searchText'

describe('normalizeSearchText', () => {
  it('lowercases and strips Vietnamese diacritics', () => {
    expect(normalizeSearchText('Nguyễn Nhật Ánh')).toBe('nguyen nhat anh')
    expect(normalizeSearchText('Đặng')).toBe('dang')
  })
})

describe('matchesSearch', () => {
  it('matches with or without diacritics', () => {
    expect(matchesSearch('Văn học', 'van hoc')).toBe(true)
    expect(matchesSearch('Kinh tế', 'kinh te')).toBe(true)
    expect(matchesSearch('Sapiens', 'xyz')).toBe(false)
  })

  it('matches empty query to all', () => {
    expect(matchesSearch('Anything', '')).toBe(true)
  })
})
