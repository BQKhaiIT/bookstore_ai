<script setup lang="ts">
import { fetchCloudinaryImages } from '@/api/admin'
import { ApiError } from '@/api/errors'
import type { CloudinaryImage } from '@/types/admin'
import { onBeforeUnmount, ref, watch } from 'vue'

const visible = defineModel<boolean>('visible', { default: false })

const emit = defineEmits<{
  select: [image: CloudinaryImage]
}>()

const loading = ref(false)
const error = ref<string | null>(null)
const images = ref<CloudinaryImage[]>([])
const nextCursor = ref<string | null>(null)
const hasMore = ref(false)
const selectedId = ref<string | null>(null)
const searchInput = ref('')
const activeSearch = ref('')
const loadGeneration = ref(0)

let debounceTimer: ReturnType<typeof setTimeout> | null = null

function imageLabel(image: CloudinaryImage): string {
  return image.display_name || image.public_id
}

async function load(options: { cursor?: string; append?: boolean; search?: string } = {}): Promise<void> {
  const requestId = ++loadGeneration.value
  const search = options.search ?? activeSearch.value
  loading.value = true
  error.value = null

  try {
    const result = await fetchCloudinaryImages({
      per_page: 24,
      cursor: options.cursor || undefined,
      search: search || undefined,
    })

    if (requestId !== loadGeneration.value) {
      return
    }

    activeSearch.value = search
    if (options.append && options.cursor) {
      images.value = [...images.value, ...result.data]
    } else {
      images.value = result.data
    }
    nextCursor.value = result.meta.next_cursor
    hasMore.value = result.meta.has_more
  } catch (e) {
    if (requestId !== loadGeneration.value) {
      return
    }
    error.value = e instanceof ApiError ? e.message : 'Không tải được ảnh Cloudinary.'
  } finally {
    if (requestId === loadGeneration.value) {
      loading.value = false
    }
  }
}

function scheduleSearch(): void {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
  debounceTimer = setTimeout(() => {
    selectedId.value = null
    void load({ search: searchInput.value.trim() })
  }, 400)
}

watch(visible, (open) => {
  if (open) {
    selectedId.value = null
    searchInput.value = ''
    activeSearch.value = ''
    void load({ search: '' })
  }
})

watch(searchInput, () => {
  if (!visible.value) {
    return
  }
  scheduleSearch()
})

onBeforeUnmount(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer)
  }
  loadGeneration.value++
})

function confirmSelect(): void {
  const image = images.value.find((item) => item.public_id === selectedId.value)
  if (!image) return
  emit('select', image)
  visible.value = false
}
</script>

<template>
  <CModal alignment="center" size="xl" :visible="visible" @close="visible = false">
    <CModalHeader>
      <CModalTitle>Chọn từ Cloudinary</CModalTitle>
    </CModalHeader>
    <CModalBody>
      <CInputGroup class="mb-3">
        <CInputGroupText><CIcon icon="cil-magnifying-glass" /></CInputGroupText>
        <CFormInput
          v-model="searchInput"
          type="search"
          placeholder="Tìm ảnh theo tên file..."
          autocomplete="off"
        />
      </CInputGroup>

      <CAlert v-if="error" color="danger">{{ error }}</CAlert>

      <div v-if="loading && !images.length" class="admin-state py-4">
        <CSpinner color="primary" />
      </div>
      <div v-else-if="images.length" class="cloudinary-grid">
        <button
          v-for="image in images"
          :key="image.public_id"
          type="button"
          class="cloudinary-grid__item"
          :class="{ 'cloudinary-grid__item--selected': selectedId === image.public_id }"
          :title="imageLabel(image)"
          @click="selectedId = image.public_id"
        >
          <img :src="image.secure_url" :alt="imageLabel(image)" loading="lazy" />
          <span class="cloudinary-grid__caption">{{ imageLabel(image) }}</span>
        </button>
      </div>
      <div v-else-if="!loading && !error" class="text-center text-body-secondary py-4">
        {{ activeSearch ? 'Không tìm thấy ảnh phù hợp.' : 'Không có ảnh nào.' }}
      </div>

      <div v-if="loading && images.length" class="text-center py-2">
        <CSpinner size="sm" color="primary" />
      </div>
    </CModalBody>
    <CModalFooter>
      <CButton
        v-if="hasMore && nextCursor"
        color="secondary"
        variant="outline"
        :disabled="loading"
        @click="load({ cursor: nextCursor ?? undefined, append: true, search: activeSearch })"
      >
        {{ loading ? 'Đang tải...' : 'Tải thêm' }}
      </CButton>
      <CButton color="secondary" variant="outline" @click="visible = false">Hủy</CButton>
      <CButton color="primary" :disabled="!selectedId" @click="confirmSelect">Chọn ảnh</CButton>
    </CModalFooter>
  </CModal>
</template>

<style scoped>
.cloudinary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 0.75rem;
}

.cloudinary-grid__item {
  border: 2px solid transparent;
  border-radius: 0.375rem;
  padding: 0;
  background: transparent;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  text-align: left;
}

.cloudinary-grid__item img {
  width: 100%;
  aspect-ratio: 3 / 4;
  object-fit: cover;
  display: block;
}

.cloudinary-grid__caption {
  font-size: 0.75rem;
  line-height: 1.2;
  padding: 0.35rem 0.25rem 0;
  color: var(--cui-body-color-secondary, #6c757d);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.cloudinary-grid__item--selected {
  border-color: var(--cui-primary, #321fdb);
}

.cloudinary-grid__item--selected .cloudinary-grid__caption {
  color: var(--cui-primary, #321fdb);
}
</style>
