import { ApiError } from '@/api/errors'
import type { PaginationMeta } from '@/types/admin'
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

type Fetcher<T> = (params: Record<string, string | number | undefined>) => Promise<{ data: T[]; meta: PaginationMeta }>

export function usePaginatedList<T>(fetcher: Fetcher<T>, defaultParams: Record<string, string | number> = {}) {
  const route = useRoute()
  const router = useRouter()
  const items = ref<T[]>([]) as { value: T[] }
  const meta = ref<PaginationMeta | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  function queryParams(): Record<string, string | number | undefined> {
    const q = route.query
    return {
      ...defaultParams,
      page: q.page ? Number(q.page) : 1,
      per_page: q.per_page ? Number(q.per_page) : defaultParams.per_page ?? 15,
      search: typeof q.search === 'string' ? q.search : undefined,
      status: typeof q.status === 'string' ? q.status : undefined,
      role: typeof q.role === 'string' ? q.role : undefined,
      publisher_id: q.publisher_id ? Number(q.publisher_id) : undefined,
      category_id: q.category_id ? Number(q.category_id) : undefined,
    }
  }

  async function load(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const result = await fetcher(queryParams())
      items.value = result.data
      meta.value = result.meta
    } catch (e) {
      items.value = []
      meta.value = null
      error.value = e instanceof ApiError ? e.message : 'Không tải được dữ liệu.'
    } finally {
      loading.value = false
    }
  }

  function setQuery(patch: Record<string, string | number | undefined>): void {
    const next: Record<string, string> = {}
    const merged = { ...queryParams(), ...patch }
    for (const [key, value] of Object.entries(merged)) {
      if (value !== undefined && value !== '') {
        next[key] = String(value)
      }
    }
    router.push({ query: next })
  }

  watch(() => route.query, load, { deep: true })
  onMounted(load)

  return { items, meta, loading, error, load, setQuery, queryParams }
}
