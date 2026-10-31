export function normalizeSelectId(id: number | string): string {
  return String(id)
}

export function mergeById<T extends { id: number }>(base: T[], extras: readonly T[]): T[] {
  const map = new Map(base.map((item) => [normalizeSelectId(item.id), item]))
  for (const item of extras) {
    map.set(normalizeSelectId(item.id), item)
  }
  return Array.from(map.values())
}

export async function fetchMissingByIds<T extends { id: number }>(
  list: T[],
  ids: readonly (number | string)[],
  fetchOne: (id: number) => Promise<T>,
): Promise<{ list: T[]; missingIds: string[] }> {
  let merged = list
  const known = new Set(merged.map((item) => normalizeSelectId(item.id)))
  const missingIds: string[] = []

  await Promise.all(
    ids.map(async (rawId) => {
      const id = normalizeSelectId(rawId)
      if (!id || known.has(id)) return
      try {
        const entity = await fetchOne(Number(id))
        merged = mergeById(merged, [entity])
        known.add(normalizeSelectId(entity.id))
      } catch {
        missingIds.push(id)
      }
    }),
  )

  return { list: merged, missingIds }
}
