<script setup lang="ts">
import StarRating from '@/components/customer/StarRating.vue'
import Button from '@/components/ui/button/Button.vue'
import type { BookSummary, Review } from '@/types/customer'
import { formatDate } from '@/utils/format'
import { cn } from '@/lib/utils'
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps<{
  book: BookSummary
  reviews: Review[]
  authenticated: boolean
  loginRedirect: string
  myReview: Review | null
  reviewBusy?: boolean
}>()

const reviewRating = defineModel<number>('reviewRating', { required: true })
const reviewComment = defineModel<string>('reviewComment', { required: true })

const emit = defineEmits<{ submit: [] }>()

type ReviewSort = 'newest' | 'rating'

const sort = ref<ReviewSort>('newest')

const reviewCount = computed(() => props.book.reviews_count ?? props.reviews.length)

const averageScore = computed(() => {
  if (props.book.average_rating != null && reviewCount.value > 0) {
    return props.book.average_rating
  }
  if (!props.reviews.length) return 0
  const sum = props.reviews.reduce((acc, r) => acc + r.rating, 0)
  return sum / props.reviews.length
})

const averageLabel = computed(() => {
  if (reviewCount.value === 0) return '0/5'
  const n = averageScore.value
  const text = Number.isInteger(n) ? String(n) : n.toFixed(1)
  return `${text}/5`
})

const distribution = computed(() => {
  const counts = [0, 0, 0, 0, 0]
  for (const r of props.reviews) {
    const idx = Math.min(5, Math.max(1, Math.round(r.rating))) - 1
    counts[idx] += 1
  }
  return [5, 4, 3, 2, 1].map((stars) => {
    const count = counts[stars - 1]
    return {
      stars,
      count,
      percent: props.reviews.length ? Math.round((count / props.reviews.length) * 100) : 0,
    }
  })
})

const sortedReviews = computed(() => {
  const list = [...props.reviews]
  if (sort.value === 'rating') {
    return list.sort((a, b) => b.rating - a.rating || Date.parse(b.created_at) - Date.parse(a.created_at))
  }
  return list.sort((a, b) => Date.parse(b.created_at) - Date.parse(a.created_at))
})

const tabs: Array<{ id: ReviewSort; label: string }> = [
  { id: 'newest', label: 'Mới nhất' },
  { id: 'rating', label: 'Điểm cao nhất' },
]
</script>

<template>
  <section id="reviews" class="scroll-mt-24">
    <div class="overflow-hidden rounded-lg border border-border bg-card">
      <h2 class="border-b border-border px-4 py-4 font-display text-xl font-semibold sm:px-6 sm:text-2xl">
        Đánh giá sản phẩm
      </h2>

      <div class="grid gap-6 border-b border-border px-4 py-6 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)_minmax(0,1fr)] lg:items-start">
        <div class="text-center lg:text-left">
          <p class="font-display text-4xl font-semibold tabular-nums text-foreground">{{ averageLabel }}</p>
          <StarRating :rating="averageScore" readonly size="lg" class="mt-2 justify-center lg:justify-start" />
          <p class="mt-2 text-sm text-muted-foreground">({{ reviewCount }} đánh giá)</p>
        </div>

        <div class="space-y-2">
          <div v-for="row in distribution" :key="row.stars" class="flex items-center gap-2 text-sm">
            <span class="w-12 shrink-0 text-muted-foreground">{{ row.stars }} sao</span>
            <div class="h-2 min-w-0 flex-1 overflow-hidden rounded-full bg-muted">
              <div
                class="h-full rounded-full bg-amber-400/80 transition-[width] duration-300"
                :style="{ width: `${row.percent}%` }"
              />
            </div>
            <span class="w-10 shrink-0 text-right tabular-nums text-muted-foreground">{{ row.percent }}%</span>
          </div>
        </div>

        <div class="rounded-lg border border-border bg-secondary/30 px-4 py-3 text-sm leading-relaxed text-muted-foreground lg:text-left">
          <template v-if="authenticated">
            <p class="font-medium text-foreground">Viết đánh giá</p>
            <p class="mt-1">Chia sẻ trải nghiệm của bạn sau khi đọc. Đánh giá sẽ được kiểm duyệt trước khi hiển thị.</p>
          </template>
          <template v-else>
            Chỉ thành viên mới có thể viết nhận xét. Vui lòng
            <RouterLink :to="{ name: 'login', query: { redirect: loginRedirect } }" class="font-medium text-accent hover:underline">
              đăng nhập
            </RouterLink>
            hoặc
            <RouterLink :to="{ name: 'register', query: { redirect: loginRedirect } }" class="font-medium text-accent hover:underline">
              đăng ký
            </RouterLink>
            .
          </template>
        </div>
      </div>

      <div class="flex gap-6 border-b border-border px-4 sm:px-6" role="tablist" aria-label="Sắp xếp đánh giá">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          role="tab"
          :aria-selected="sort === tab.id"
          :class="
            cn(
              'border-b-2 py-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
              sort === tab.id
                ? 'border-accent text-accent'
                : 'border-transparent text-muted-foreground hover:text-foreground',
            )
          "
          @click="sort = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <div class="px-4 sm:px-6">
        <p v-if="!sortedReviews.length" class="py-8 text-center text-sm text-muted-foreground">
          Chưa có đánh giá được duyệt.
        </p>
        <ul v-else role="list">
          <li
            v-for="r in sortedReviews"
            :key="r.id"
            class="flex flex-col gap-3 border-b border-border py-5 last:border-0 sm:flex-row sm:gap-6"
          >
            <div class="shrink-0 sm:w-36">
              <p class="font-semibold text-foreground">{{ r.user?.name ?? 'Khách' }}</p>
              <p class="mt-1 text-xs text-muted-foreground">{{ formatDate(r.created_at) }}</p>
            </div>
            <div class="min-w-0 flex-1">
              <StarRating :rating="r.rating" readonly size="sm" />
              <p v-if="r.comment" class="mt-2 text-sm leading-relaxed text-foreground">{{ r.comment }}</p>
              <p v-else class="mt-2 text-sm italic text-muted-foreground">Không có nội dung nhận xét.</p>
            </div>
          </li>
        </ul>
      </div>

      <div
        v-if="authenticated"
        class="border-t border-border bg-secondary/20 px-4 py-6 sm:px-6"
      >
        <h3 class="font-display text-lg font-semibold">Đánh giá của bạn</h3>
        <p v-if="myReview" class="mt-1 text-xs text-muted-foreground">
          Trạng thái: {{ myReview.status === 'approved' ? 'Đã duyệt' : myReview.status }}
        </p>
        <div class="mt-4 max-w-xl space-y-4">
          <div>
            <p class="mb-2 text-sm font-medium">Chọn số sao</p>
            <StarRating v-model="reviewRating" size="lg" />
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium" for="review-comment">Nhận xét</label>
            <textarea
              id="review-comment"
              v-model="reviewComment"
              rows="4"
              placeholder="Chia sẻ cảm nhận về nội dung, chất lượng bản in…"
              class="flex w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            />
          </div>
          <Button variant="accent" :disabled="reviewBusy" @click="emit('submit')">
            {{ reviewBusy ? 'Đang gửi…' : 'Gửi đánh giá' }}
          </Button>
        </div>
      </div>
    </div>
  </section>
</template>
