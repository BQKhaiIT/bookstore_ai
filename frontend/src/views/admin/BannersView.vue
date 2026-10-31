<script setup lang="ts">
import {
  createBanner,
  deleteBanner,
  fetchBanners,
  updateBanner,
} from '@/api/admin'
import { ApiError, firstFieldError } from '@/api/errors'
import AdminPagination from '@/components/admin/AdminPagination.vue'
import BannerImageUploader from '@/components/admin/BannerImageUploader.vue'
import { useConfirm } from '@/composables/useConfirm'
import { usePaginatedList } from '@/composables/usePaginatedList'
import { useToastStore } from '@/stores/toast'
import type { AdminBanner, BannerType } from '@/types/admin'
import { computed, reactive, ref } from 'vue'

const toast = useToastStore()
const { confirm } = useConfirm()
const typeFilter = ref('')
const modalVisible = ref(false)
const editing = ref<AdminBanner | null>(null)
const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})

const form = reactive({
  type: 'hero' as BannerType,
  title: '',
  description: '',
  cta_text: '',
  link_url: '',
  position: 'home_hero',
  sort_order: 0,
  is_active: false,
  starts_at: '',
  ends_at: '',
})

const { items, meta, loading, error, setQuery, load } = usePaginatedList<AdminBanner>(fetchBanners)

function defaultPosition(type: BannerType): string {
  return type === 'hero' ? 'home_hero' : 'home_editorial'
}

function openCreate(): void {
  editing.value = null
  form.type = 'hero'
  form.title = ''
  form.description = ''
  form.cta_text = ''
  form.link_url = '/books'
  form.position = defaultPosition('hero')
  form.sort_order = 0
  form.is_active = false
  form.starts_at = ''
  form.ends_at = ''
  fieldErrors.value = {}
  modalVisible.value = true
}

function openEdit(row: AdminBanner): void {
  editing.value = row
  form.type = row.type
  form.title = row.title
  form.description = row.description ?? ''
  form.cta_text = row.cta_text ?? ''
  form.link_url = row.link_url ?? ''
  form.position = row.position
  form.sort_order = row.sort_order
  form.is_active = row.is_active
  form.starts_at = row.starts_at ? row.starts_at.slice(0, 16) : ''
  form.ends_at = row.ends_at ? row.ends_at.slice(0, 16) : ''
  fieldErrors.value = {}
  modalVisible.value = true
}

function err(field: string): string | undefined {
  return firstFieldError(fieldErrors.value, field)
}

function onTypeChange(): void {
  if (!editing.value) {
    form.position = defaultPosition(form.type)
  }
}

const positionHint = computed(() =>
  form.type === 'hero'
    ? 'Hero — khối đầu trang chủ (ảnh bên phải tiêu đề). Vị trí: home_hero.'
    : 'Banner — ảnh minh họa mục “Biên tập viên gợi ý”. Vị trí: home_editorial.',
)

const positionMismatch = computed(() => {
  const expected = defaultPosition(form.type)
  return form.position.trim() !== '' && form.position.trim() !== expected
})

function payloadFromForm(): Record<string, unknown> {
  return {
    type: form.type,
    title: form.title,
    description: form.description || null,
    cta_text: form.cta_text || null,
    link_url: form.link_url || null,
    position: form.position,
    sort_order: form.sort_order,
    is_active: form.is_active,
    starts_at: form.starts_at || null,
    ends_at: form.ends_at || null,
  }
}

async function save(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  try {
    if (editing.value) {
      const updated = await updateBanner(editing.value.id, payloadFromForm())
      editing.value = updated
      toast.success('Đã cập nhật banner.')
    } else {
      const created = await createBanner(payloadFromForm())
      editing.value = created
      toast.success('Đã tạo banner — thêm ảnh bên dưới.')
    }
    await load()
  } catch (e) {
    if (e instanceof ApiError) {
      fieldErrors.value = e.errors
      toast.error(e.message)
    }
  } finally {
    saving.value = false
  }
}

function onBannerImagesUpdated(banner: AdminBanner): void {
  editing.value = banner
  void load()
}

async function onDelete(row: AdminBanner): Promise<void> {
  const ok = await confirm({ message: `Xóa "${row.title}"? Hành động không hoàn tác.` })
  if (!ok) return
  try {
    await deleteBanner(row.id)
    toast.success('Đã xóa banner.')
    if (editing.value?.id === row.id) modalVisible.value = false
    await load()
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Không thể xóa.')
  }
}

function applyFilters(): void {
  setQuery({ page: 1, type: typeFilter.value || undefined })
}

function typeLabel(type: BannerType): string {
  return type === 'hero' ? 'Hero' : 'Banner'
}
</script>

<template>
  <CCard class="admin-page">
    <CCardHeader class="d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong>Hero &amp; Banner</strong>
      <CButton color="primary" @click="openCreate"><CIcon icon="cil-plus" class="me-1" /> Thêm</CButton>
    </CCardHeader>
    <CCardBody>
      <div class="admin-filters mb-3 d-flex flex-wrap gap-2 align-items-center">
        <CFormSelect v-model="typeFilter" style="max-width: 10rem" @change="applyFilters">
          <option value="">Tất cả loại</option>
          <option value="hero">Hero</option>
          <option value="banner">Banner</option>
        </CFormSelect>
        <CButton color="secondary" variant="outline" @click="applyFilters">Lọc</CButton>
      </div>
      <CAlert v-if="error" color="danger">{{ error }}</CAlert>
      <div v-if="loading" class="admin-state"><CSpinner color="primary" /></div>
      <CTable v-else hover responsive align="middle">
        <CTableHead>
          <CTableRow>
            <CTableHeaderCell>Ảnh</CTableHeaderCell>
            <CTableHeaderCell>Tiêu đề</CTableHeaderCell>
            <CTableHeaderCell>Loại</CTableHeaderCell>
            <CTableHeaderCell>Vị trí</CTableHeaderCell>
            <CTableHeaderCell>TT</CTableHeaderCell>
            <CTableHeaderCell>Thứ tự</CTableHeaderCell>
            <CTableHeaderCell />
          </CTableRow>
        </CTableHead>
        <CTableBody>
          <CTableRow v-for="row in items" :key="row.id">
            <CTableDataCell>
              <img
                v-if="row.image_url"
                :src="row.image_url"
                alt=""
                class="rounded border"
                style="width: 56px; height: 40px; object-fit: cover"
              />
              <span v-else class="text-body-secondary small">—</span>
            </CTableDataCell>
            <CTableDataCell>{{ row.title }}</CTableDataCell>
            <CTableDataCell>{{ typeLabel(row.type) }}</CTableDataCell>
            <CTableDataCell><code>{{ row.position }}</code></CTableDataCell>
            <CTableDataCell>
              <CBadge :color="row.is_active ? 'success' : 'secondary'">{{ row.is_active ? 'Bật' : 'Tắt' }}</CBadge>
            </CTableDataCell>
            <CTableDataCell>{{ row.sort_order }}</CTableDataCell>
            <CTableDataCell class="text-end">
              <CButton size="sm" color="secondary" variant="ghost" @click="openEdit(row)">Sửa</CButton>
              <CButton size="sm" color="danger" variant="ghost" @click="onDelete(row)">Xóa</CButton>
            </CTableDataCell>
          </CTableRow>
        </CTableBody>
      </CTable>
      <p v-if="!loading && !items.length" class="text-body-secondary mb-0">Chưa có banner nào.</p>
      <AdminPagination v-if="meta" class="mt-3" :meta="meta" @page="(p) => setQuery({ page: p })" />
    </CCardBody>
  </CCard>

  <CModal :visible="modalVisible" size="lg" @close="modalVisible = false">
    <CModalHeader>
      <CModalTitle>{{ editing ? 'Sửa banner' : 'Thêm banner' }}</CModalTitle>
    </CModalHeader>
    <CModalBody>
      <CRow class="g-3">
        <CCol cols="12">
          <CAlert color="light" class="mb-0 py-2 small">
            Chỉ còn 2 vị trí trên trang chủ:
            <strong>Hero</strong> + <code>home_hero</code> (đầu trang);
            <strong>Banner</strong> + <code>home_editorial</code> (ảnh trái “Biên tập viên gợi ý”).
            Vị trí <code>home_promo</code> không còn hiển thị — đổi hoặc xóa bản ghi cũ.
          </CAlert>
        </CCol>
        <CCol md="6">
          <CFormLabel>Loại</CFormLabel>
          <CFormSelect v-model="form.type" :disabled="!!editing" @change="onTypeChange">
            <option value="hero">Hero</option>
            <option value="banner">Banner</option>
          </CFormSelect>
          <CFormText>{{ positionHint }}</CFormText>
        </CCol>
        <CCol md="6">
          <CFormLabel>Vị trí</CFormLabel>
          <CFormInput v-model="form.position" :invalid="!!err('position') || positionMismatch" />
          <CFormFeedback invalid>{{ err('position') }}</CFormFeedback>
          <CFormText v-if="positionMismatch" class="text-warning">
            Vị trí không khớp loại — đổi thành {{ defaultPosition(form.type) }} hoặc đổi loại cho đúng.
          </CFormText>
        </CCol>
        <CCol cols="12">
          <CFormLabel>Tiêu đề</CFormLabel>
          <CFormInput v-model="form.title" :invalid="!!err('title')" />
          <CFormFeedback invalid>{{ err('title') }}</CFormFeedback>
        </CCol>
        <CCol cols="12">
          <CFormLabel>Mô tả</CFormLabel>
          <CFormTextarea v-model="form.description" rows="3" />
        </CCol>
        <CCol md="6">
          <CFormLabel>CTA</CFormLabel>
          <CFormInput v-model="form.cta_text" />
        </CCol>
        <CCol md="6">
          <CFormLabel>Liên kết</CFormLabel>
          <CFormInput
            v-model="form.link_url"
            placeholder="/books — URL trang khi bấm banner (không dán link ảnh Cloudinary)"
            :invalid="!!err('link_url')"
          />
          <CFormFeedback invalid>{{ err('link_url') }}</CFormFeedback>
        </CCol>
        <CCol md="4">
          <CFormLabel>Thứ tự</CFormLabel>
          <CFormInput v-model.number="form.sort_order" type="number" min="0" />
        </CCol>
        <CCol md="4">
          <CFormLabel>Bắt đầu</CFormLabel>
          <CFormInput v-model="form.starts_at" type="datetime-local" />
        </CCol>
        <CCol md="4">
          <CFormLabel>Kết thúc</CFormLabel>
          <CFormInput v-model="form.ends_at" type="datetime-local" />
        </CCol>
        <CCol cols="12">
          <CFormCheck v-model="form.is_active" label="Hiển thị (active)" />
        </CCol>
      </CRow>

      <div v-if="editing" class="mt-4 border-top pt-4">
        <p class="fw-semibold mb-3">Ảnh &amp; preview</p>
        <CRow class="g-3">
          <CCol md="6">
            <BannerImageUploader
              :banner-id="editing.id"
              variant="desktop"
              label="Desktop"
              :image-url="editing.image_url"
              @updated="onBannerImagesUpdated"
            />
          </CCol>
          <CCol md="6">
            <BannerImageUploader
              :banner-id="editing.id"
              variant="mobile"
              label="Mobile"
              :image-url="editing.mobile_image_url"
              @updated="onBannerImagesUpdated"
            />
          </CCol>
        </CRow>
      </div>
      <CAlert v-else color="info" class="mt-3 mb-0">Lưu banner trước, sau đó upload ảnh desktop/mobile.</CAlert>
    </CModalBody>
    <CModalFooter>
      <CButton color="secondary" @click="modalVisible = false">Đóng</CButton>
      <CButton color="primary" :disabled="saving" @click="save">{{ saving ? 'Đang lưu…' : 'Lưu' }}</CButton>
    </CModalFooter>
  </CModal>
</template>
