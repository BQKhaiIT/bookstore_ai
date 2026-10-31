<script setup lang="ts">
import {
  assignBookCoverFromCloudinary,
  removeBookCover,
  uploadBookCover,
} from '@/api/admin'
import { ApiError } from '@/api/errors'
import BookImagePlaceholder from '@/components/admin/BookImagePlaceholder.vue'
import CloudinaryImagePickerModal from '@/components/admin/CloudinaryImagePickerModal.vue'
import { useToastStore } from '@/stores/toast'
import type { AdminBook, CloudinaryImage } from '@/types/admin'
import { computed, ref } from 'vue'

const props = defineProps<{
  bookId: number
  coverUrl?: string | null
}>()

const emit = defineEmits<{
  updated: [book: AdminBook]
}>()

const toast = useToastStore()
const uploading = ref(false)
const removing = ref(false)
const pickerOpen = ref(false)
const previewUrl = ref<string | null>(null)
const dragActive = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const displayUrl = computed(() => previewUrl.value ?? props.coverUrl ?? null)

function resetPreview(): void {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
}

function openFilePicker(): void {
  fileInput.value?.click()
}

function onFileSelected(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  void uploadFile(file)
}

function onDrop(event: DragEvent): void {
  dragActive.value = false
  const file = event.dataTransfer?.files?.[0]
  if (!file) return
  void uploadFile(file)
}

async function uploadFile(file: File): Promise<void> {
  resetPreview()
  previewUrl.value = URL.createObjectURL(file)
  uploading.value = true
  try {
    const book = await uploadBookCover(props.bookId, file)
    resetPreview()
    emit('updated', book)
    toast.success('Đã cập nhật ảnh bìa.')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Upload ảnh bìa thất bại.')
  } finally {
    uploading.value = false
  }
}

async function onPickFromCloudinary(image: CloudinaryImage): Promise<void> {
  uploading.value = true
  try {
    const book = await assignBookCoverFromCloudinary(props.bookId, image.public_id)
    resetPreview()
    emit('updated', book)
    toast.success('Đã gán ảnh bìa từ Cloudinary.')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Không gán được ảnh bìa.')
  } finally {
    uploading.value = false
  }
}

async function onRemove(): Promise<void> {
  removing.value = true
  try {
    const book = await removeBookCover(props.bookId)
    resetPreview()
    emit('updated', book)
    toast.success('Đã gỡ ảnh bìa.')
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Không gỡ được ảnh bìa.')
  } finally {
    removing.value = false
  }
}
</script>

<template>
  <div class="book-cover-uploader">
    <div
      class="book-cover-uploader__dropzone"
      :class="{ 'book-cover-uploader__dropzone--active': dragActive }"
      @dragover.prevent="dragActive = true"
      @dragleave.prevent="dragActive = false"
      @drop.prevent="onDrop"
    >
      <BookImagePlaceholder :src="displayUrl" width="160px" height="220px" alt="Ảnh bìa sách" />
      <div class="book-cover-uploader__actions mt-3 d-flex flex-wrap gap-2">
        <CButton color="primary" size="sm" :disabled="uploading || removing" @click="openFilePicker">
          {{ uploading ? 'Đang upload...' : 'Chọn ảnh' }}
        </CButton>
        <CButton color="secondary" variant="outline" size="sm" :disabled="uploading || removing" @click="pickerOpen = true">
          Chọn từ Cloudinary
        </CButton>
        <CButton
          v-if="coverUrl || displayUrl"
          color="danger"
          variant="outline"
          size="sm"
          :disabled="uploading || removing"
          @click="onRemove"
        >
          {{ removing ? 'Đang gỡ...' : 'Gỡ ảnh' }}
        </CButton>
      </div>
      <small class="text-body-secondary d-block mt-2">JPG, PNG, WebP — tối đa 5MB. Kéo thả hoặc chọn file.</small>
      <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="d-none" @change="onFileSelected" />
    </div>
    <CloudinaryImagePickerModal v-model:visible="pickerOpen" @select="onPickFromCloudinary" />
  </div>
</template>

<style scoped>
.book-cover-uploader__dropzone {
  border: 1px dashed var(--cui-border-color, #b1b7c1);
  border-radius: 0.5rem;
  padding: 1rem;
  background: var(--cui-tertiary-bg, #f8f9fa);
}

.book-cover-uploader__dropzone--active {
  border-color: var(--cui-primary, #321fdb);
  background: rgba(50, 31, 219, 0.04);
}
</style>
