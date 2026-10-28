<script setup lang="ts">
import type { PaginationMeta } from '@/types/admin'
import { computed } from 'vue'

const props = defineProps<{
  meta: PaginationMeta | null
}>()

const emit = defineEmits<{
  page: [page: number]
}>()

const pages = computed(() => {
  const meta = props.meta
  if (!meta || meta.last_page <= 1) {
    return []
  }
  const size = 5
  let start = Math.max(1, meta.current_page - 2)
  let end = Math.min(meta.last_page, start + size - 1)
  start = Math.max(1, end - size + 1)
  return Array.from({ length: end - start + 1 }, (_, index) => start + index)
})

const firstPage = computed(() => pages.value[0] ?? 1)
const lastShown = computed(() => pages.value[pages.value.length - 1] ?? 1)

function go(page: number): void {
  const meta = props.meta
  if (!meta || page < 1 || page > meta.last_page || page === meta.current_page) {
    return
  }
  emit('page', page)
}
</script>

<template>
  <div
    v-if="meta && meta.last_page > 1"
    class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2"
  >
    <small class="text-body-secondary">
      Hiển thị {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} / {{ meta.total }} mục
    </small>
    <CPagination aria-label="Phân trang">
      <CPaginationItem :disabled="meta.current_page <= 1" aria-label="Trang trước" @click="go(meta.current_page - 1)">
        ‹
      </CPaginationItem>
      <CPaginationItem v-if="firstPage > 1" @click="go(1)">1</CPaginationItem>
      <CPaginationItem v-if="firstPage > 2" disabled>…</CPaginationItem>
      <CPaginationItem v-for="p in pages" :key="p" :active="p === meta.current_page" @click="go(p)">
        {{ p }}
      </CPaginationItem>
      <CPaginationItem v-if="lastShown < meta.last_page - 1" disabled>…</CPaginationItem>
      <CPaginationItem v-if="lastShown < meta.last_page" @click="go(meta.last_page)">
        {{ meta.last_page }}
      </CPaginationItem>
      <CPaginationItem
        :disabled="meta.current_page >= meta.last_page"
        aria-label="Trang sau"
        @click="go(meta.current_page + 1)"
      >
        ›
      </CPaginationItem>
    </CPagination>
  </div>
</template>
