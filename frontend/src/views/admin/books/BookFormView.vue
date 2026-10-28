<script setup lang="ts">
import { createBook, fetchBook, fetchBookFormOptions, updateBook } from '@/api/admin'
import { ApiError, firstFieldError } from '@/api/errors'
import { useToastStore } from '@/stores/toast'
import type { Author, Category, Publisher } from '@/types/admin'
import BookImagePlaceholder from '@/components/admin/BookImagePlaceholder.vue'
import { bookStatusLabel } from '@/utils/adminLabels'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const isEdit = computed(() => route.name === 'admin-book-edit')
const bookId = computed(() => Number(route.params.id))
const loading = ref(true)
const saving = ref(false)
const fieldErrors = ref<Record<string, string[]>>({})
const publishers = ref<Publisher[]>([])
const authors = ref<Author[]>([])
const categories = ref<Category[]>([])

const form = reactive({
  title: '',
  isbn: '',
  description: '',
  price: 0,
  stock_quantity: 0,
  status: 'draft' as string,
  publisher_id: '' as number | '',
  author_ids: [] as number[],
  category_ids: [] as number[],
})

onMounted(async () => {
  try {
    const opts = await fetchBookFormOptions()
    publishers.value = opts.publishers
    authors.value = opts.authors
    categories.value = opts.categories
    if (isEdit.value) {
      const book = await fetchBook(bookId.value)
      form.title = book.title
      form.isbn = book.isbn ?? ''
      form.description = book.description ?? ''
      form.price = book.price
      form.stock_quantity = book.stock_quantity
      form.status = book.status
      form.publisher_id = book.publisher_id ?? ''
      form.author_ids = book.author_ids ?? book.authors?.map((a) => a.id) ?? []
      form.category_ids = book.category_ids ?? book.categories?.map((c) => c.id) ?? []
    }
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Không tải được dữ liệu.')
    router.push({ name: 'admin-books' })
  } finally {
    loading.value = false
  }
})

function err(field: string): string | undefined {
  return firstFieldError(fieldErrors.value, field)
}

async function submit(): Promise<void> {
  saving.value = true
  fieldErrors.value = {}
  const payload = {
    title: form.title,
    isbn: form.isbn || null,
    description: form.description || null,
    price: Number(form.price),
    stock_quantity: Number(form.stock_quantity),
    status: form.status,
    publisher_id: form.publisher_id ? Number(form.publisher_id) : null,
    author_ids: form.author_ids,
    category_ids: form.category_ids,
  }
  try {
    if (isEdit.value) {
      await updateBook(bookId.value, payload)
      toast.success('Đã cập nhật sách.')
    } else {
      await createBook(payload)
      toast.success('Đã tạo sách.')
    }
    router.push({ name: 'admin-books' })
  } catch (e) {
    if (e instanceof ApiError) {
      fieldErrors.value = e.errors
      toast.error(e.message)
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <CCard v-if="loading" class="admin-page"><CCardBody class="admin-state"><CSpinner color="primary" /></CCardBody></CCard>
  <CCard v-else class="admin-page">
    <CCardHeader>
      <div class="d-flex align-items-center gap-3">
        <BookImagePlaceholder width="48px" height="64px" />
        <strong>{{ isEdit ? 'Sửa sách' : 'Thêm sách' }}</strong>
      </div>
    </CCardHeader>
    <CCardBody>
      <CForm @submit.prevent="submit">
        <CRow class="g-3">
          <CCol md="8">
            <CFormLabel for="title">Tên sách *</CFormLabel>
            <CFormInput id="title" v-model="form.title" required :invalid="!!err('title')" />
            <CFormFeedback invalid>{{ err('title') }}</CFormFeedback>
          </CCol>
          <CCol md="4">
            <CFormLabel for="isbn">ISBN</CFormLabel>
            <CFormInput id="isbn" v-model="form.isbn" :invalid="!!err('isbn')" />
            <CFormFeedback invalid>{{ err('isbn') }}</CFormFeedback>
          </CCol>
          <CCol md="12">
            <CFormLabel for="description">Mô tả</CFormLabel>
            <CFormTextarea id="description" v-model="form.description" rows="4" />
          </CCol>
          <CCol md="3">
            <CFormLabel for="price">Giá (VND) *</CFormLabel>
            <CFormInput id="price" v-model.number="form.price" type="number" min="0" required />
          </CCol>
          <CCol md="3">
            <CFormLabel for="stock">Tồn kho</CFormLabel>
            <CFormInput id="stock" v-model.number="form.stock_quantity" type="number" min="0" />
          </CCol>
          <CCol md="3">
            <CFormLabel for="status">Trạng thái</CFormLabel>
            <CFormSelect id="status" v-model="form.status">
              <option v-for="(label, key) in bookStatusLabel" :key="key" :value="key">{{ label }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="3">
            <CFormLabel for="publisher">Nhà xuất bản</CFormLabel>
            <CFormSelect id="publisher" v-model="form.publisher_id">
              <option value="">— Không chọn —</option>
              <option v-for="p in publishers" :key="p.id" :value="p.id">{{ p.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <CFormLabel>Tác giả</CFormLabel>
            <CFormSelect v-model="form.author_ids" multiple size="sm">
              <option v-for="a in authors" :key="a.id" :value="a.id">{{ a.name }}</option>
            </CFormSelect>
          </CCol>
          <CCol md="6">
            <CFormLabel>Danh mục</CFormLabel>
            <CFormSelect v-model="form.category_ids" multiple size="sm">
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </CFormSelect>
          </CCol>
        </CRow>
        <div class="d-flex gap-2 mt-4">
          <CButton color="primary" type="submit" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu' }}
          </CButton>
          <CButton color="secondary" variant="outline" @click="router.push({ name: 'admin-books' })">Hủy</CButton>
        </div>
      </CForm>
    </CCardBody>
  </CCard>
</template>
