<script setup lang="ts">
import { uploadBannerImage } from '@/api/admin'
import { ApiError } from '@/api/errors'
import CloudinaryImagePickerModal from '@/components/admin/CloudinaryImagePickerModal.vue'
import { cilImage } from '@coreui/icons'
import { useToastStore } from '@/stores/toast'
import type { AdminBanner, CloudinaryImage } from '@/types/admin'
import { computed, ref } from 'vue'

const props = defineProps<{
  bannerId: number
  variant: 'desktop' | 'mobile'
  imageUrl?: string | null
  label: string
}>()

const emit = defineEmits<{ updated: [banner: AdminBanner] }>()

const toast = useToastStore()
const uploading = ref(false)
const pickerOpen = ref(false)
const previewUrl = ref<string | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)

const displayUrl = computed(() => previewUrl.value ?? props.imageUrl ?? null)

function resetPreview(): void {
  if (previewUrl.value) {
    URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = null
  }
}

async function uploadFile(file: File): Promise<void> {
  resetPreview()
  previewUrl.value = URL.createObjectURL(file)
  uploading.value = true
  try {
    const banner = await uploadBannerImage(props.bannerId, props.variant, file)
    resetPreview()
    emit('updated', banner)
    toast.success(`Đã cập nhật ảnh ${props.label}.`)
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Upload thất bại.')
  } finally {
    uploading.value = false
  }
}

async function onPickFromCloudinary(image: CloudinaryImage): Promise<void> {
  uploading.value = true
  try {
    const banner = await uploadBannerImage(props.bannerId, props.variant, undefined, image.public_id)
    resetPreview()
    emit('updated', banner)
    toast.success(`Đã gán ảnh ${props.label}.`)
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Không gán được ảnh.')
  } finally {
    uploading.value = false
  }
}

function onFileSelected(event: Event): void {
  const file = (event.target as HTMLInputElement).files?.[0]
  ;(event.target as HTMLInputElement).value = ''
  if (file) void uploadFile(file)
}
</script>

<template>
  <div class="border rounded p-3 bg-light">
    <p class="small fw-semibold mb-2">{{ label }}</p>
    <div
      class="ratio ratio-16x9 mb-2 bg-white border rounded overflow-hidden banner-uploader-preview"
      style="max-width: 280px"
    >
      <img v-if="displayUrl" :src="displayUrl" alt="" class="banner-uploader-preview__media" />
      <div v-else class="banner-uploader-preview__empty">
        <CIcon :icon="cilImage" size="xl" class="text-secondary opacity-50" />
        <span class="small text-secondary">Chưa có ảnh</span>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <CButton size="sm" color="primary" :disabled="uploading" @click="fileInput?.click()">
        {{ uploading ? 'Đang tải…' : 'Upload' }}
      </CButton>
      <CButton size="sm" color="secondary" variant="outline" :disabled="uploading" @click="pickerOpen = true">
        Cloudinary
      </CButton>
    </div>
    <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="d-none" @change="onFileSelected" />
    <CloudinaryImagePickerModal v-model:visible="pickerOpen" @select="onPickFromCloudinary" />
  </div>
</template>

<style scoped>
.banner-uploader-preview__media,
.banner-uploader-preview__empty {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.banner-uploader-preview__media {
  object-fit: contain;
}

.banner-uploader-preview__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  background-color: var(--cui-secondary-bg, #e9ecef);
}
</style>
