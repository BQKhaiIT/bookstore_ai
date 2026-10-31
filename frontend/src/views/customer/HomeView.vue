<script setup lang="ts">
import { fetchBooks, fetchCategories, fetchStorefrontBanners } from '@/api/catalog'
import BookCard from '@/components/customer/BookCard.vue'
import InfiniteBookCarousel from '@/components/customer/InfiniteBookCarousel.vue'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import EditorialPicksSection from '@/components/customer/EditorialPicksSection.vue'
import SectionHeading from '@/components/customer/SectionHeading.vue'
import StorefrontBannerImage from '@/components/customer/StorefrontBannerImage.vue'
import StorefrontCtaLink from '@/components/customer/StorefrontCtaLink.vue'
import Button from '@/components/ui/button/Button.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { ApiError } from '@/api/errors'
import type { BookSummary, CategoryRef, StorefrontBanner } from '@/types/customer'
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'

const newest = ref<BookSummary[]>([])
/** Nguồn tuyển chọn editorial — sau này thay bằng Hybrid Recommendation API. */
const editorialBooks = ref<BookSummary[]>([])
const popular = ref<BookSummary[]>([])
const categories = ref<CategoryRef[]>([])
const storefrontBanners = ref<StorefrontBanner[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const heroBanner = computed(() => {
  const heroes = storefrontBanners.value.filter((b) => b.type === 'hero')
  return heroes.find((b) => b.position === 'home_hero') ?? heroes[0] ?? null
})

/** Ảnh minh họa cột trái mục "Biên tập viên gợi ý" — không dùng Hero đầu trang. */
const editorialBanner = computed(() =>
  storefrontBanners.value.find((b) => b.position === 'home_editorial' && b.image_url) ?? null,
)

const heroEyebrow = computed(() => 'Nhà sách trực tuyến')
const heroTitle = computed(
  () => heroBanner.value?.title ?? 'Chọn sách có chủ đích — đọc lâu dài, mua gọn gàng.',
)
const heroDescription = computed(
  () =>
    heroBanner.value?.description ??
    'Danh mục sách thật từ cửa hàng, giá và tồn kho đồng bộ với hệ thống. Tìm theo tên, ISBN hoặc thể loại.',
)
const heroPrimaryCta = computed(() => heroBanner.value?.cta_text ?? 'Khám phá sách')
const heroPrimaryLink = computed(() => heroBanner.value?.link_url ?? '/books')

onMounted(async () => {
  loading.value = true
  error.value = null
  try {
    const [n, p, c, banners] = await Promise.all([
      fetchBooks({ per_page: 20, sort: 'newest' }),
      fetchBooks({ per_page: 8, sort: 'popular' }),
      fetchCategories(),
      fetchStorefrontBanners().catch(() => [] as StorefrontBanner[]),
    ])
    newest.value = n.data.slice(0, 10)
    editorialBooks.value = n.data
    popular.value = p.data
    categories.value = c.slice(0, 6)
    storefrontBanners.value = banners
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không tải được dữ liệu trang chủ.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <section class="border-b border-border bg-card">
      <CustomerContainer class="grid items-center gap-10 py-10 lg:grid-cols-12 lg:gap-12 lg:py-16">
        <template v-if="loading">
          <div class="lg:col-span-7">
            <Skeleton class="h-4 w-36" />
            <Skeleton class="mt-4 h-14 w-full max-w-xl" />
            <Skeleton class="mt-4 h-14 w-full max-w-lg" />
            <Skeleton class="mt-5 h-20 w-full max-w-xl" />
            <div class="mt-8 flex flex-wrap gap-3">
              <Skeleton class="h-11 w-36 rounded-md" />
              <Skeleton class="h-11 w-28 rounded-md" />
            </div>
          </div>
          <div class="lg:col-span-5">
            <Skeleton class="lg:ml-auto aspect-[4/5] w-full max-w-md rounded-lg" />
          </div>
        </template>
        <template v-else>
          <div class="lg:col-span-7">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">{{ heroEyebrow }}</p>
            <h1 class="mt-4 font-display text-4xl font-semibold leading-[1.1] text-balance sm:text-5xl lg:text-[3.25rem]">
              {{ heroTitle }}
            </h1>
            <p class="mt-5 max-w-xl text-base leading-relaxed text-muted-foreground">
              {{ heroDescription }}
            </p>
            <div class="mt-8 flex flex-wrap items-center gap-3">
              <Button as-child size="lg" variant="accent">
                <StorefrontCtaLink :href="heroPrimaryLink">{{ heroPrimaryCta }}</StorefrontCtaLink>
              </Button>
              <Button as-child size="lg" variant="outline">
                <RouterLink :to="{ name: 'books', query: { sort: 'newest' } }">Sách mới</RouterLink>
              </Button>
            </div>
          </div>
          <div class="lg:col-span-5">
            <StorefrontBannerImage
              :banner="heroBanner"
              aspect="hero"
              priority
              class="lg:ml-auto aspect-[4/5] max-w-md w-full"
            />
          </div>
        </template>
      </CustomerContainer>
    </section>

    <EditorialPicksSection
      :books="editorialBooks"
      :loading="loading"
      :editorial-banner="editorialBanner"
    />

    <section v-if="categories.length" class="border-y border-border bg-secondary/50 py-14">
      <CustomerContainer>
        <SectionHeading
          title="Khám phá theo thể loại"
          description="Lọc danh mục theo chủ đề — đồng bộ với bộ lọc trang danh mục."
        />
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <li v-for="(cat, index) in categories" :key="cat.id">
            <RouterLink
              :to="{ name: 'books', query: { category_id: cat.id } }"
              class="group flex items-center justify-between rounded-lg border border-border bg-card px-5 py-4 transition-colors hover:border-foreground/20 hover:bg-background"
            >
              <span class="font-medium group-hover:text-accent">{{ cat.name }}</span>
              <span class="text-xs tabular-nums text-muted-foreground">{{ String(index + 1).padStart(2, '0') }}</span>
            </RouterLink>
          </li>
        </ul>
      </CustomerContainer>
    </section>

    <section class="py-14 lg:py-16">
      <CustomerContainer>
        <SectionHeading
          title="Mới cập nhật"
          :to="{ name: 'books', query: { sort: 'newest' } }"
          link-label="Xem tất cả"
        />
        <p v-if="error" class="text-sm text-destructive">{{ error }}</p>
        <div v-else-if="loading" class="flex gap-4 overflow-hidden">
          <Skeleton v-for="i in 5" :key="i" class="h-[280px] w-40 shrink-0" />
        </div>
        <div
          v-else-if="newest.length"
          class="-mx-4 flex gap-4 overflow-x-auto px-4 pb-2 snap-x snap-mandatory sm:mx-0 sm:px-0"
        >
          <div v-for="book in newest" :key="book.id" class="w-40 shrink-0 snap-start sm:w-44">
            <BookCard :book="book" variant="minimal" />
          </div>
        </div>
        <p v-else class="text-sm text-muted-foreground">Chưa có sách để hiển thị.</p>
      </CustomerContainer>
    </section>

    <section class="border-t border-border bg-card pb-16 pt-14 lg:pb-20">
      <CustomerContainer>
        <SectionHeading
          title="Được quan tâm"
          description="Xếp theo số đánh giá đã duyệt — dữ liệu từ hệ thống, không phải gợi ý AI."
          :to="{ name: 'books', query: { sort: 'popular' } }"
          link-label="Xem thêm"
        />
        <div v-if="loading" class="flex gap-4 overflow-hidden">
          <Skeleton v-for="i in 5" :key="i" class="h-[280px] w-40 shrink-0" />
        </div>
        <InfiniteBookCarousel
          v-else-if="popular.length"
          :books="popular"
          aria-label="Sách được quan tâm"
        />
        <p v-else class="text-sm text-muted-foreground">Chưa đủ dữ liệu phổ biến.</p>
      </CustomerContainer>
    </section>
  </div>
</template>
