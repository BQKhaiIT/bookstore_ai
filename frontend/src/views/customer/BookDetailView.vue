<script setup lang="ts">
import { fetchBook, fetchBookReviews, fetchRelatedBooks } from '@/api/catalog'
import { fetchMyReview, submitReview } from '@/api/commerce'
import BookCard from '@/components/customer/BookCard.vue'
import BookProductGallery from '@/components/customer/BookProductGallery.vue'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import BookReviewsSection from '@/components/customer/BookReviewsSection.vue'
import SectionHeading from '@/components/customer/SectionHeading.vue'
import PriceDisplay from '@/components/customer/PriceDisplay.vue'
import Badge from '@/components/ui/badge/Badge.vue'
import Button from '@/components/ui/button/Button.vue'
import Input from '@/components/ui/input/Input.vue'
import Separator from '@/components/ui/separator/Separator.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { ApiError } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import type { BookSummary, Review } from '@/types/customer'
import { Heart, Minus, Plus, ShoppingBag, Zap } from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const cart = useCartStore()
const wishlist = useWishlistStore()

const book = ref<BookSummary | null>(null)
const related = ref<BookSummary[]>([])
const reviews = ref<Review[]>([])
const myReview = ref<Review | null>(null)
const qty = ref(1)
const loading = ref(true)
const cartBusy = ref(false)
const reviewBusy = ref(false)
const wishlistBusy = ref(false)
const actionMsg = ref<string | null>(null)
const actionErr = ref<string | null>(null)
const reviewRating = ref(5)
const reviewComment = ref('')
const descriptionExpanded = ref(false)

const slug = computed(() => String(route.params.slug))
const inStock = computed(() => (book.value?.stock_quantity ?? 0) > 0)
const maxQty = computed(() => Math.max(1, book.value?.stock_quantity ?? 1))

const specRows = computed(() => {
  if (!book.value) return []
  const b = book.value
  const rows: Array<{ label: string; value: string; to?: { name: string; query: Record<string, number> } }> = []
  if (b.isbn) rows.push({ label: 'ISBN', value: b.isbn })
  if (b.authors?.length) rows.push({ label: 'Tác giả', value: b.authors.map((a) => a.name).join(', ') })
  if (b.publisher?.name) rows.push({ label: 'Nhà xuất bản', value: b.publisher.name })
  if (b.categories?.length) {
    rows.push({
      label: 'Danh mục',
      value: b.categories.map((c) => c.name).join(', '),
    })
  }
  rows.push({
    label: 'Tình trạng',
    value: inStock.value ? `Còn hàng (${b.stock_quantity} cuốn)` : 'Hết hàng',
  })
  if (b.reviews_count != null && b.reviews_count > 0 && b.average_rating != null) {
    rows.push({
      label: 'Đánh giá',
      value: `${b.average_rating.toFixed(1)} / 5 (${b.reviews_count} đánh giá đã duyệt)`,
    })
  }
  return rows
})

const descriptionLong = computed(() => (book.value?.description?.length ?? 0) > 420)

async function load(): Promise<void> {
  loading.value = true
  actionErr.value = null
  descriptionExpanded.value = false
  try {
    book.value = await fetchBook(slug.value)
    qty.value = 1
    const [rel, rev] = await Promise.all([
      fetchRelatedBooks(slug.value),
      fetchBookReviews(slug.value),
    ])
    related.value = rel
    reviews.value = rev.data
    if (auth.isAuthenticated) {
      myReview.value = await fetchMyReview(slug.value)
      if (myReview.value) {
        reviewRating.value = myReview.value.rating
        reviewComment.value = myReview.value.comment ?? ''
      }
    } else {
      myReview.value = null
    }
  } catch (e) {
    actionErr.value = e instanceof ApiError ? e.message : 'Không tải được sách.'
    book.value = null
  } finally {
    loading.value = false
  }
}

function clampQty(): void {
  if (!book.value) return
  const n = Number(qty.value)
  if (!Number.isFinite(n) || n < 1) qty.value = 1
  else if (n > maxQty.value) qty.value = maxQty.value
  else qty.value = Math.floor(n)
}

function stepQty(delta: number): void {
  qty.value = Math.min(maxQty.value, Math.max(1, qty.value + delta))
}

async function addToCart(redirectToCart = false): Promise<void> {
  if (cartBusy.value) return
  if (!auth.isAuthenticated) {
    void router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  if (!book.value || !inStock.value) return
  clampQty()
  actionMsg.value = null
  actionErr.value = null
  cartBusy.value = true
  try {
    await cart.add(book.value.id, qty.value)
    actionMsg.value = redirectToCart ? 'Đã thêm — chuyển tới giỏ hàng…' : 'Đã thêm vào giỏ hàng.'
    if (redirectToCart) void router.push({ name: 'cart' })
  } catch (e) {
    actionErr.value = e instanceof ApiError ? e.message : 'Không thêm được vào giỏ.'
  } finally {
    cartBusy.value = false
  }
}

async function toggleWishlist(): Promise<void> {
  if (wishlistBusy.value) return
  if (!auth.isAuthenticated) {
    void router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  if (!book.value) return
  wishlistBusy.value = true
  try {
    await wishlist.toggle(book.value.id)
  } finally {
    wishlistBusy.value = false
  }
}

async function sendReview(): Promise<void> {
  if (reviewBusy.value) return
  if (!auth.isAuthenticated) {
    void router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  reviewBusy.value = true
  actionErr.value = null
  try {
    myReview.value = await submitReview(slug.value, reviewRating.value, reviewComment.value)
    actionMsg.value = 'Đánh giá đã gửi — chờ duyệt.'
  } catch (e) {
    actionErr.value = e instanceof ApiError ? e.message : 'Không gửi được đánh giá.'
  } finally {
    reviewBusy.value = false
  }
}

watch(slug, () => void load(), { immediate: true })
watch(qty, () => clampQty())
</script>

<template>
  <div class="pb-16 lg:pb-20">
    <CustomerContainer class="py-6 lg:py-10">
      <nav class="mb-6 text-sm text-muted-foreground" aria-label="Breadcrumb">
        <RouterLink to="/" class="hover:text-foreground">Trang chủ</RouterLink>
        <span class="mx-2" aria-hidden="true">/</span>
        <RouterLink to="/books" class="hover:text-foreground">Danh mục</RouterLink>
        <span class="mx-2" aria-hidden="true">/</span>
        <span class="text-foreground">{{ book?.title ?? '…' }}</span>
      </nav>

      <div v-if="loading" class="grid gap-10 lg:grid-cols-12 lg:gap-12">
        <Skeleton class="mx-auto aspect-[2/3] w-full max-w-md lg:col-span-5" />
        <div class="space-y-4 lg:col-span-7">
          <Skeleton class="h-10 w-3/4" />
          <Skeleton class="h-5 w-1/2" />
          <Skeleton class="h-8 w-40" />
          <Skeleton class="h-24 w-full max-w-sm" />
        </div>
      </div>

      <p v-else-if="actionErr && !book" class="text-destructive">{{ actionErr }}</p>

      <template v-else-if="book">
        <div class="grid gap-10 lg:grid-cols-12 lg:gap-12 xl:gap-16">
          <BookProductGallery
            class="lg:col-span-5"
            :title="book.title"
            :cover-url="book.cover_url"
          />

          <div class="lg:col-span-7">
            <div v-if="book.categories?.length" class="mb-3 flex flex-wrap gap-2">
              <RouterLink
                v-for="cat in book.categories"
                :key="cat.id"
                :to="{ name: 'books', query: { category_id: cat.id } }"
                class="rounded-full border border-border bg-secondary/60 px-3 py-0.5 text-xs font-medium text-secondary-foreground hover:bg-secondary"
              >
                {{ cat.name }}
              </RouterLink>
            </div>

            <h1 class="font-display text-3xl font-semibold leading-tight text-balance sm:text-4xl">
              {{ book.title }}
            </h1>

            <p class="mt-3 text-base text-muted-foreground">
              {{ book.authors?.map((a) => a.name).join(', ') || '—' }}
              <span v-if="book.publisher"> · {{ book.publisher.name }}</span>
            </p>

            <div class="mt-5 flex flex-wrap items-baseline gap-x-4 gap-y-2">
              <PriceDisplay :amount="book.price" size="lg" class="text-2xl sm:text-3xl text-accent" />
              <Badge v-if="!inStock" variant="outline">Hết hàng</Badge>
              <span v-else class="text-sm font-medium text-foreground">Còn {{ book.stock_quantity }} cuốn</span>
            </div>

            <p class="mt-4 text-sm leading-relaxed text-muted-foreground">
              Thanh toán khi nhận hàng (COD). Phí vận chuyển được tính tại bước thanh toán nếu có cấu hình.
            </p>

            <div class="mt-6 flex flex-wrap items-center gap-3">
              <span class="text-sm font-medium">Số lượng</span>
              <div class="flex items-center rounded-md border border-input bg-card">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="h-10 w-10 shrink-0 rounded-none"
                  :disabled="!inStock || qty <= 1 || cartBusy"
                  aria-label="Giảm số lượng"
                  @click="stepQty(-1)"
                >
                  <Minus class="size-4" />
                </Button>
                <Input
                  id="qty"
                  v-model="qty"
                  type="number"
                  min="1"
                  :max="book.stock_quantity"
                  :disabled="!inStock || cartBusy"
                  class="h-10 w-16 border-0 text-center shadow-none focus-visible:ring-0"
                  aria-label="Số lượng"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  class="h-10 w-10 shrink-0 rounded-none"
                  :disabled="!inStock || qty >= maxQty || cartBusy"
                  aria-label="Tăng số lượng"
                  @click="stepQty(1)"
                >
                  <Plus class="size-4" />
                </Button>
              </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap">
              <Button
                variant="accent"
                size="lg"
                class="min-w-[11rem]"
                :disabled="!inStock || cartBusy"
                @click="addToCart(false)"
              >
                <ShoppingBag class="size-5 shrink-0" aria-hidden="true" />
                {{ cartBusy ? 'Đang xử lý…' : 'Thêm vào giỏ' }}
              </Button>
              <Button
                variant="default"
                size="lg"
                class="min-w-[11rem]"
                :disabled="!inStock || cartBusy"
                @click="addToCart(true)"
              >
                <Zap class="size-5 shrink-0" aria-hidden="true" />
                Mua ngay
              </Button>
              <Button
                variant="outline"
                size="lg"
                :disabled="wishlistBusy"
                @click="toggleWishlist"
              >
                <Heart
                  class="size-5 shrink-0"
                  :class="wishlist.has(book.id) ? 'fill-accent text-accent' : ''"
                  aria-hidden="true"
                />
                {{ wishlist.has(book.id) ? 'Đã yêu thích' : 'Yêu thích' }}
              </Button>
            </div>

            <p v-if="actionMsg" class="mt-3 text-sm text-accent" role="status">{{ actionMsg }}</p>
            <p v-if="actionErr" class="mt-3 text-sm text-destructive" role="alert">{{ actionErr }}</p>

            <div v-if="specRows.length" class="mt-8 overflow-hidden rounded-lg border border-border">
              <table class="w-full text-sm">
                <caption class="sr-only">Thông tin sản phẩm</caption>
                <tbody>
                  <tr
                    v-for="(row, i) in specRows"
                    :key="row.label"
                    class="border-b border-border last:border-0"
                    :class="i % 2 === 0 ? 'bg-card' : 'bg-secondary/30'"
                  >
                    <th scope="row" class="w-[38%] px-4 py-3 text-left font-medium text-muted-foreground">
                      {{ row.label }}
                    </th>
                    <td class="px-4 py-3 text-foreground">{{ row.value }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <Separator class="my-12 lg:my-14" />

        <section id="description" class="scroll-mt-24">
          <h2 class="font-display text-2xl font-semibold">Mô tả sách</h2>
          <div v-if="book.description" class="mt-4">
            <p
              class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground"
              :class="!descriptionExpanded && descriptionLong ? 'line-clamp-6' : ''"
            >
              {{ book.description }}
            </p>
            <Button
              v-if="descriptionLong"
              variant="link"
              class="mt-2 h-auto p-0 text-accent"
              @click="descriptionExpanded = !descriptionExpanded"
            >
              {{ descriptionExpanded ? 'Thu gọn' : 'Xem thêm' }}
            </Button>
          </div>
          <p v-else class="mt-3 text-sm text-muted-foreground">Chưa có mô tả cho cuốn sách này.</p>
        </section>

        <section v-if="specRows.length" class="mt-12">
          <h2 class="font-display text-2xl font-semibold">Thông tin chi tiết</h2>
          <dl class="mt-4 grid gap-3 sm:grid-cols-2">
            <div
              v-for="row in specRows"
              :key="`detail-${row.label}`"
              class="rounded-lg border border-border bg-card px-4 py-3"
            >
              <dt class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ row.label }}</dt>
              <dd class="mt-1 text-sm font-medium">{{ row.value }}</dd>
            </div>
          </dl>
        </section>

        <BookReviewsSection
          class="mt-14"
          :book="book"
          :reviews="reviews"
          :authenticated="auth.isAuthenticated"
          :login-redirect="route.fullPath"
          :my-review="myReview"
          :review-busy="reviewBusy"
          v-model:review-rating="reviewRating"
          v-model:review-comment="reviewComment"
          @submit="sendReview"
        />

        <section v-if="related.length" class="mt-14">
          <SectionHeading title="Sách liên quan" />
          <div class="mt-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            <BookCard v-for="b in related" :key="b.id" :book="b" />
          </div>
        </section>
      </template>
    </CustomerContainer>
  </div>
</template>
