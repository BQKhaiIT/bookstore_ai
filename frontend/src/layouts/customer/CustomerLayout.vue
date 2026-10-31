<script setup lang="ts">
import CustomerConfirmDialog from '@/components/customer/CustomerConfirmDialog.vue'
import CustomerFooter from '@/components/customer/CustomerFooter.vue'
import CustomerHeader from '@/components/customer/CustomerHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useWishlistStore } from '@/stores/wishlist'
import '@/assets/styles/customer-tailwind.css'
import { RouterView } from 'vue-router'
import { watch } from 'vue'

const auth = useAuthStore()
const wishlist = useWishlistStore()

watch(
  () => auth.isAuthenticated,
  (ok) => {
    if (ok) void wishlist.load()
    else wishlist.clearLocal()
  },
  { immediate: true },
)
</script>

<template>
  <div class="customer-site flex min-h-screen flex-col">
    <CustomerHeader />
    <main class="flex-1">
      <RouterView />
    </main>
    <CustomerFooter />
    <CustomerConfirmDialog />
  </div>
</template>
