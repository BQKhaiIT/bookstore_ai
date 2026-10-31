<script setup lang="ts">
import { fetchWishlistBooks, removeWishlistItem } from '@/api/commerce'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import WishlistEditorialHero from '@/components/customer/WishlistEditorialHero.vue'
import WishlistItemCard from '@/components/customer/WishlistItemCard.vue'
import Button from '@/components/ui/button/Button.vue'
import Skeleton from '@/components/ui/skeleton/Skeleton.vue'
import { ApiError } from '@/api/errors'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useWishlistStore } from '@/stores/wishlist'
import type { BookSummary } from '@/types/customer'
import { BookOpen } from '@lucide/vue'
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
const cart = useCartStore()
const wishlist = useWishlistStore()

const books = ref<BookSummary[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const itemBusyId = ref<number | null>(null)
const cartMsg = ref<string | null>(null)

onMounted(async () => {
  if (!auth.isAuthenticated) {
    void router.replace({ name: 'login', query: { redirect: '/wishlist' } })
    return
  }
  try {
    books.value = await fetchWishlistBooks()
    await wishlist.load()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không tải được wishlist.'
  } finally {
    loading.value = false
  }
})

async function removeBook(book: BookSummary): Promise<void> {
  if (itemBusyId.value != null) return
  itemBusyId.value = book.id
  try {
    if (wishlist.has(book.id)) {
      await wishlist.toggle(book.id)
    } else {
      await removeWishlistItem(book.id)
    }
    books.value = books.value.filter((b) => b.id !== book.id)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không bỏ được yêu thích.'
  } finally {
    itemBusyId.value = null
  }
}

async function addBookToCart(book: BookSummary): Promise<void> {
  if (itemBusyId.value != null || book.stock_quantity < 1) return
  itemBusyId.value = book.id
  cartMsg.value = null
  error.value = null
  try {
    await cart.add(book.id, 1)
    cartMsg.value = `Đã thêm «${book.title}» vào giỏ.`
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Không thêm được vào giỏ.'
  } finally {
    itemBusyId.value = null
  }
}
</script>

<template>
  <div class="pb-14 lg:pb-16">
    <WishlistEditorialHero :books="books" :total="books.length" />

    <CustomerContainer class="py-8 lg:py-10">
      <p v-if="error" class="mb-4 text-sm text-destructive" role="alert">{{ error }}</p>
      <p v-if="cartMsg" class="mb-4 text-sm text-accent" role="status">{{ cartMsg }}</p>

      <div v-if="loading" class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        <Skeleton v-for="i in 6" :key="i" class="aspect-[2/3] w-full rounded-lg" />
      </div>

      <div
        v-else-if="!books.length"
        class="mx-auto flex max-w-md flex-col items-center gap-5 py-10 text-center"
      >
        <div
          class="flex size-16 items-center justify-center rounded-full border border-border bg-secondary/50 text-muted-foreground"
          aria-hidden="true"
        >
          <BookOpen class="size-8" />
        </div>
        <div>
          <h2 class="font-display text-xl font-semibold">Danh sách trống</h2>
          <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
            Lưu sách từ trang chi tiết để xem lại và đặt hàng nhanh hơn.
          </p>
        </div>
        <Button as-child variant="accent" size="lg">
          <RouterLink to="/books">Khám phá sách</RouterLink>
        </Button>
      </div>

      <div v-else>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 class="font-display text-xl font-semibold sm:text-2xl">Tất cả yêu thích</h2>
            <p class="mt-1 text-sm text-muted-foreground">{{ books.length }} cuốn trong danh sách</p>
          </div>
          <Button as-child variant="outline" size="sm">
            <RouterLink to="/books">Tiếp tục mua sắm</RouterLink>
          </Button>
        </div>

        <ul
          class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
          role="list"
        >
          <li
            v-for="(book, index) in books"
            :key="book.id"
            :style="{ animationDelay: `${Math.min(index, 8) * 0.04}s` }"
          >
            <WishlistItemCard
              :book="book"
              :busy="itemBusyId === book.id"
              @add-to-cart="addBookToCart(book)"
              @remove="removeBook(book)"
            />
          </li>
        </ul>
      </div>
    </CustomerContainer>
  </div>
</template>
