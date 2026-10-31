<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue'
import { buttonVariants } from '@/components/ui/button/index'
import Input from '@/components/ui/input/Input.vue'
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import { useDebouncedRef } from '@/composables/useDebouncedRef'
import { cn } from '@/lib/utils'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
  DialogTrigger,
} from 'reka-ui'
import { Heart, Menu, Search, ShoppingBag, User, X } from '@lucide/vue'
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'

const auth = useAuthStore()
const cart = useCartStore()
const router = useRouter()
const route = useRoute()

const searchInput = ref(typeof route.query.search === 'string' ? route.query.search : '')
const debouncedSearch = useDebouncedRef(searchInput, 350)
const mobileOpen = ref(false)

const cartBadge = computed(() => cart.itemCount)

watch(
  () => auth.isAuthenticated,
  (ok) => {
    if (ok) void cart.load()
    else cart.clearLocal()
  },
  { immediate: true },
)

watch(debouncedSearch, (q) => {
  if (route.name === 'books') {
    void router.replace({ query: { ...route.query, search: q || undefined, page: undefined } })
  }
})

function submitSearch(): void {
  mobileOpen.value = false
  void router.push({ name: 'books', query: { search: searchInput.value.trim() || undefined } })
}

async function logout(): Promise<void> {
  mobileOpen.value = false
  await auth.logout()
  cart.clearLocal()
  void router.push({ name: 'home' })
}

function closeMobile(): void {
  mobileOpen.value = false
}

const navLinks = computed(() => [
  { to: '/books', label: 'Danh mục sách' },
  ...(auth.isAuthenticated
    ? [
        { to: '/wishlist', label: 'Yêu thích' },
        { to: '/orders', label: 'Đơn hàng' },
        { to: '/account', label: 'Tài khoản' },
      ]
    : []),
])
</script>

<template>
  <header class="sticky top-0 z-50 border-b border-border/80 bg-background/95 backdrop-blur-sm">
    <CustomerContainer class="py-3">
      <div class="flex items-center gap-3 lg:gap-6">
        <RouterLink to="/" class="font-display text-xl font-bold tracking-tight shrink-0 no-underline hover:text-foreground">
          BookStore
        </RouterLink>

        <form class="hidden flex-1 items-center gap-2 md:flex max-w-xl" @submit.prevent="submitSearch">
          <div class="relative flex-1">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              v-model="searchInput"
              type="search"
              placeholder="Tìm theo tên sách, ISBN…"
              class="pl-9"
              aria-label="Tìm sách"
            />
          </div>
          <Button type="submit" variant="default" size="sm">Tìm</Button>
        </form>

        <nav class="hidden items-center gap-1 lg:flex">
          <RouterLink
            v-for="link in navLinks"
            :key="link.to"
            :to="link.to"
            class="rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground"
          >
            {{ link.label }}
          </RouterLink>
        </nav>

        <div class="ml-auto flex items-center gap-1 sm:gap-2">
          <Button v-if="auth.isAuthenticated" as-child variant="ghost" size="icon" aria-label="Yêu thích">
            <RouterLink to="/wishlist"><Heart class="size-5" /></RouterLink>
          </Button>
          <Button as-child variant="ghost" size="icon" aria-label="Giỏ hàng" class="relative">
            <RouterLink to="/cart">
              <ShoppingBag class="size-5" />
              <span
                v-if="cartBadge > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold text-accent-foreground"
              >
                {{ cartBadge }}
              </span>
            </RouterLink>
          </Button>

          <template v-if="auth.isAuthenticated">
            <Button as-child variant="ghost" size="icon" class="hidden sm:inline-flex" aria-label="Tài khoản">
              <RouterLink to="/account"><User class="size-5" /></RouterLink>
            </Button>
            <Button variant="outline" size="sm" class="hidden md:inline-flex" @click="logout">Đăng xuất</Button>
          </template>
          <template v-else>
            <RouterLink
              to="/login"
              class="hidden rounded-md px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground sm:inline-flex"
            >
              Đăng nhập
            </RouterLink>
            <RouterLink
              to="/register"
              :class="cn(buttonVariants({ variant: 'default', size: 'sm' }))"
            >
              Đăng ký
            </RouterLink>
          </template>

          <DialogRoot v-model:open="mobileOpen">
            <DialogTrigger as-child>
              <Button variant="outline" size="icon" class="lg:hidden" aria-label="Mở menu">
                <Menu class="size-5" />
              </Button>
            </DialogTrigger>
            <DialogPortal>
              <DialogOverlay class="fixed inset-0 z-50 bg-black/40 data-[state=open]:animate-in data-[state=closed]:animate-out" />
              <DialogContent
                :class="
                  cn(
                    'fixed inset-y-0 right-0 z-50 flex w-[min(100vw-2rem,320px)] flex-col border-l border-border bg-card p-6 shadow-lg outline-none',
                  )
                "
              >
                <div class="mb-6 flex items-center justify-between">
                  <DialogTitle class="font-display text-lg font-semibold">Menu</DialogTitle>
                  <DialogClose as-child>
                    <Button variant="ghost" size="icon" aria-label="Đóng">
                      <X class="size-5" />
                    </Button>
                  </DialogClose>
                </div>

                <form class="mb-6 flex flex-col gap-2 md:hidden" @submit.prevent="submitSearch">
                  <Input v-model="searchInput" type="search" placeholder="Tìm sách…" aria-label="Tìm sách" />
                  <Button type="submit" class="w-full">Tìm kiếm</Button>
                </form>

                <nav class="flex flex-col gap-1">
                  <RouterLink
                    v-for="link in navLinks"
                    :key="link.to"
                    :to="link.to"
                    class="rounded-md px-3 py-2.5 text-sm font-medium hover:bg-muted"
                    @click="closeMobile"
                  >
                    {{ link.label }}
                  </RouterLink>
                  <RouterLink to="/cart" class="rounded-md px-3 py-2.5 text-sm font-medium hover:bg-muted" @click="closeMobile">
                    Giỏ hàng
                  </RouterLink>
                </nav>

                <div class="mt-auto flex flex-col gap-2 border-t border-border pt-4">
                  <template v-if="auth.isAuthenticated">
                    <Button variant="outline" @click="logout">Đăng xuất</Button>
                  </template>
                  <template v-else>
                    <Button as-child variant="outline"><RouterLink to="/login" @click="closeMobile">Đăng nhập</RouterLink></Button>
                    <Button as-child><RouterLink to="/register" @click="closeMobile">Đăng ký</RouterLink></Button>
                  </template>
                </div>
              </DialogContent>
            </DialogPortal>
          </DialogRoot>
        </div>
      </div>
    </CustomerContainer>
  </header>
</template>
