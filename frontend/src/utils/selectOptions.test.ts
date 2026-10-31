import { describe, expect, it, vi } from 'vitest'
import { fetchMissingByIds, mergeById, normalizeSelectId } from './selectOptions'

describe('normalizeSelectId', () => {
  it('stringifies numbers', () => {
    expect(normalizeSelectId(122)).toBe('122')
    expect(normalizeSelectId('122')).toBe('122')
  })
})

describe('mergeById', () => {
  it('merges without duplicates and prefers later entries', () => {
    const merged = mergeById(
      [{ id: 1, name: 'A' }],
      [
        { id: 2, name: 'B' },
        { id: 1, name: 'A updated' },
      ],
    )
    expect(merged).toHaveLength(2)
    expect(merged.find((x) => x.id === 1)?.name).toBe('A updated')
  })
})

describe('fetchMissingByIds', () => {
  it('fetches only unknown ids', async () => {
    const fetchOne = vi.fn(async (id: number) => ({ id, name: `Author ${id}` }))
    const result = await fetchMissingByIds([{ id: 1, name: 'One' }], ['1', '5'], fetchOne)
    expect(fetchOne).toHaveBeenCalledTimes(1)
    expect(fetchOne).toHaveBeenCalledWith(5)
    expect(result.list.some((a) => a.id === 5)).toBe(true)
    expect(result.missingIds).toEqual([])
  })

  it('records missing ids when fetch fails', async () => {
    const fetchOne = vi.fn(async (id: number) => {
      if (id === 99) throw new Error('404')
      return { id, name: 'Ok' }
    })
    const result = await fetchMissingByIds([], ['99'], fetchOne)
    expect(result.missingIds).toEqual(['99'])
  })
})
