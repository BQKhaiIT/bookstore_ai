<script setup lang="ts">
import { adminBreadcrumb } from '@/admin/nav'
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

const route = useRoute()
const crumbs = computed(() => adminBreadcrumb(route.name))
</script>

<template>
  <CBreadcrumb class="admin-breadcrumb mb-0">
    <CBreadcrumbItem
      v-for="(item, index) in crumbs"
      :key="`${item.label}-${index}`"
      :active="index === crumbs.length - 1"
    >
      <RouterLink v-if="item.to && index !== crumbs.length - 1" :to="item.to">
        {{ item.label }}
      </RouterLink>
      <template v-else>{{ item.label }}</template>
    </CBreadcrumbItem>
  </CBreadcrumb>
</template>
