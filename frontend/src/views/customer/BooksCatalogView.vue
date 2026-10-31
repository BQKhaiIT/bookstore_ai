<script setup lang="ts">
import { fetchAuthors, fetchBooks, fetchCategories, fetchPublishers } from '@/api/catalog'
import BookCard from '@/components/customer/BookCard.vue'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import Button from '@/components/ui/button/Button.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { cn } from '@/lib/utils'
import { ApiError } from '@/api/errors'
import type { AuthorRef, BookListParams, BookSort, BookSummary, CategoryRef, PublisherRef } from '@/types/customer'
import { SlidersHorizontal } from '@lucide/vue'
import { isCatalogPageOnlyChange, scrollElementIntoView } from '@/utils/customerScroll'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

const books = ref<BookSummary[]>([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const loading = ref(false)
const error = ref<string | null>(null)
const filtersOpen = ref(false)
const catalogTopRef = ref<HTMLElement | null>(null)

const categories = ref<CategoryRef[]>([])
const authors = ref<AuthorRef[]>([])
const publishers = ref<PublisherRef[]>([])

const params = computed<BookListParams>(() => ({
  page: Number(route.query.page) || 1,
  search: typeof route.query.search === 'string' ? route.query.search : undefined,
  category_id: route.query.category_id ? Number(route.query.category_id) : undefined,
  author_id: route.query.author_id ? Number(route.query.author_id) : undefined,
  publisher_id: route.query.publisher_id ? Number(route.query.publisher_id) : undefined,
  min_price: route.query.min_price ? Number(route.query.min_price) : undefined,
  max_price: route.query.max_price ? Number(route.query.max_price) : undefined,
  in_stock: route.query.in_stock === '1',
  sort: (route.query.sort as BookSort) || 'newest',
  per_page: 12,
}))

let loadSeq = 0

async function loadBooks(): Promise<void> {
  const seq = ++loadSeq
  loading.value = true
  error.value = null
  try {
    const res = await fetchBooks(params.value)
    if (seq !== loadSeq) return
    books.value = res.data
    meta.value = res.meta
  } catch (e) {
    if (seq !== loadSeq) return
    error.value = e instanceof ApiError ? e.message : 'Không tải được danh sách sách.'
  } finally {
    if (seq === loadSeq) loading.value = false
  }
}

function patchQuery(patch: Record<string, string | number | undefined | null>): void {
  const q: Record<string, string | undefined> = {}
  for (const [k, v] of Object.entries({ ...route.query, ...patch })) {
    if (v === undefined || v === null || v === '') continue
    q[k] = String(v)
  }
  void router.push({ query: q })
}

function clearFilters(): void {
  void router.push({ query: route.query.search ? { search: String(route.query.search) } : {} })
}

const selectClass =
  'flex h-10 w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring'

onMounted(async () => {
  try {
    ;[categories.value, authors.value, publishers.value] = await Promise.all([
      fetchCategories(),
      fetchAuthors(),
      fetchPublishers(),
    ])
  } catch {
    /* optional */
  }
})

watch(
  params,
  async (next, prev) => {
    const pageOnlyChange = prev !== undefined && isCatalogPageOnlyChange(prev, next)
    const targetPage = next.page
    await loadBooks()
    if (!pageOnlyChange || route.name !== 'books') return
    if ((Number(route.query.page) || 1) !== targetPage) return
    await nextTick()
    scrollElementIntoView(catalogTopRef.value)
  },
  { immediate: true, deep: true },
)
</script>

<template>
  <CustomerContainer class="py-8 lg:py-10">
    <div
      ref="catalogTopRef"
      class="scroll-mt-24 mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
      <div>
        <h1 class="font-display text-3xl font-semibold">Danh mục sách</h1>
        <p class="mt-1 text-sm text-muted-foreground">{{ meta.total }} kết quả</p>
      </div>
      <Button variant="outline" size="sm" class="lg:hidden" @click="filtersOpen = !filtersOpen">
        <SlidersHorizontal class="size-4" />
        Bộ lọc
      </Button>
    </div>

    <div class="grid gap-8 lg:grid-cols-[260px_1fr]">
      <aside
        :class="
          cn(
            'flex flex-col gap-4 rounded-lg border border-border bg-card p-4 lg:sticky lg:top-24 lg:self-start',
            !filtersOpen && 'hidden lg:flex',
          )
        "
      >
        <label class="text-sm font-medium">Sắp xếp</label>
        <select :class="selectClass" :value="params.sort" @change="patchQuery({ sort: ($event.target as HTMLSelectElement).value, page: 1 })">
          <option value="newest">Mới nhất</option>
          <option value="popular">Phổ biến</option>
          <option value="price_asc">Giá tăng dần</option>
          <option value="price_desc">Giá giảm dần</option>
        </select>

        <label class="text-sm font-medium">Danh mục</label>
        <select :class="selectClass" :value="params.category_id ?? ''" @change="patchQuery({ category_id: ($event.target as HTMLSelectElement).value || undefined, page: 1 })">
          <option value="">Tất cả</option>
          <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>

        <label class="text-sm font-medium">Tác giả</label>
        <select :class="selectClass" :value="params.author_id ?? ''" @change="patchQuery({ author_id: ($event.target as HTMLSelectElement).value || undefined, page: 1 })">
          <option value="">Tất cả</option>
          <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
        </select>

        <label class="text-sm font-medium">NXB</label>
        <select :class="selectClass" :value="params.publisher_id ?? ''" @change="patchQuery({ publisher_id: ($event.target as HTMLSelectElement).value || undefined, page: 1 })">
          <option value="">Tất cả</option>
          <option v-for="p in publishers" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>

        <label class="text-sm font-medium">Khoảng giá (VND)</label>
        <div class="grid grid-cols-2 gap-2">
          <input :class="selectClass" type="number" min="0" placeholder="Từ" :value="params.min_price ?? ''" @change="patchQuery({ min_price: ($event.target as HTMLInputElement).value || undefined, page: 1 })" />
          <input :class="selectClass" type="number" min="0" placeholder="Đến" :value="params.max_price ?? ''" @change="patchQuery({ max_price: ($event.target as HTMLInputElement).value || undefined, page: 1 })" />
        </div>

        <label class="flex items-center gap-2 text-sm">
          <input type="checkbox" :checked="params.in_stock" @change="patchQuery({ in_stock: ($event.target as HTMLInputElement).checked ? '1' : undefined, page: 1 })" />
          Chỉ còn hàng
        </label>

        <Button variant="ghost" size="sm" class="justify-start px-0" @click="clearFilters">Xóa bộ lọc</Button>
      </aside>

      <section>
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <div v-else-if="loading" class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
          <Skeleton v-for="i in 8" :key="i" class="aspect-[2/3] w-full" />
        </div>
        <p v-else-if="!books.length" class="text-muted-foreground">Không tìm thấy sách phù hợp.</p>
        <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
          <BookCard v-for="book in books" :key="book.id" :book="book" />
        </div>

        <nav v-if="meta.last_page > 1" class="mt-10 flex items-center justify-center gap-4" aria-label="Phân trang">
          <Button variant="outline" :disabled="meta.current_page <= 1" @click="patchQuery({ page: meta.current_page - 1 })">Trước</Button>
          <span class="text-sm text-muted-foreground">Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
          <Button variant="outline" :disabled="meta.current_page >= meta.last_page" @click="patchQuery({ page: meta.current_page + 1 })">Sau</Button>
        </nav>
      </section>
    </div>
  </CustomerContainer>
</template>
