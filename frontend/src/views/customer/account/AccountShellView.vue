<script setup lang="ts">
import CustomerContainer from '@/components/customer/CustomerContainer.vue'
import { cn } from '@/lib/utils'
import { RouterLink, RouterView, useRoute } from 'vue-router'

const route = useRoute()
const links = [
  { to: '/account/profile', label: 'Hồ sơ' },
  { to: '/account/addresses', label: 'Địa chỉ' },
  { to: '/account/security', label: 'Bảo mật' },
]
</script>

<template>
  <CustomerContainer class="py-8 lg:py-10">
    <h1 class="font-display text-3xl font-semibold">Tài khoản</h1>
    <nav class="mt-6 flex flex-wrap gap-2">
      <RouterLink
        v-for="link in links"
        :key="link.to"
        :to="link.to"
        :class="
          cn(
            'rounded-md border px-3 py-2 text-sm font-medium transition-colors',
            route.path.startsWith(link.to)
              ? 'border-primary bg-primary text-primary-foreground'
              : 'border-border bg-card text-muted-foreground hover:text-foreground',
          )
        "
      >
        {{ link.label }}
      </RouterLink>
    </nav>
    <div class="mt-8"><RouterView /></div>
  </CustomerContainer>
</template>
