<script setup lang="ts">
import {
  createBook,
  fetchAuthor,
  fetchBook,
  fetchBookFormOptions,
  fetchCategory,
  fetchPublisher,
  updateBook,
} from '@/api/admin'
import { ApiError, firstFieldError } from '@/api/errors'
import BookCoverUploader from '@/components/admin/BookCoverUploader.vue'
import BookImagePlaceholder from '@/components/admin/BookImagePlaceholder.vue'
import SearchableSelect from '@/components/admin/SearchableSelect.vue'
import { useToastStore } from '@/stores/toast'
import type { AdminBook, Author, Category, Publisher } from '@/types/admin'
import { bookStatusLabel } from '@/utils/adminLabels'
import { fetchMissingByIds, mergeById, normalizeSelectId } from '@/utils/selectOptions'
import { computed, reactive, ref, watch } from 'vue'
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
const coverUrl = ref<string | null>(null)
const missingAuthorIds = ref<string[]>([])
const missingCategoryIds = ref<string[]>([])
const missingPublisherId = ref<string | null>(null)
const resolvingSelections = ref(false)

const form = reactive({
  title: '',
  isbn: '',
  description: '',
  price: 0,
  stock_quantity: 0,
  status: 'draft',
  publisher_id: '',
  author_ids: [] as string[],
  category_ids: [] as string[],
})

const publisherOptions = computed(() =>
  publishers.value.map((p) => ({ value: normalizeSelectId(p.id), label: p.name })),
)
const authorOptions = computed(() =>
  authors.value.map((a) => ({ value: normalizeSelectId(a.id), label: a.name })),
)
const categoryOptions = computed(() =>
  categories.value.map((c) => ({ value: normalizeSelectId(c.id), label: c.name })),
)

function idsFromBook(
  explicit: number[] | undefined,
  related: { id: number }[] | undefined,
): string[] {
  const fromRelated = related?.map((item) => normalizeSelectId(item.id)) ?? []
  const fromExplicit = explicit?.map((id) => normalizeSelectId(id)) ?? []
  return [...new Set([...fromExplicit, ...fromRelated])]
}

async function resolveSelectedRelations(
  authorIds: string[],
  categoryIds: string[],
  publisherId: string,
  book: AdminBook,
): Promise<void> {
  resolvingSelections.value = true
  missingAuthorIds.value = []
  missingCategoryIds.value = []
  missingPublisherId.value = null

  authors.value = mergeById(authors.value, book.authors ?? [])
  categories.value = mergeById(categories.value, book.categories ?? [])
  if (book.publisher) {
    publishers.value = mergeById(publishers.value, [book.publisher])
  }

  const [authorsResolved, categoriesResolved, publisherResolved] = await Promise.all([
    fetchMissingByIds(authors.value, authorIds, fetchAuthor),
    fetchMissingByIds(categories.value, categoryIds, fetchCategory),
    publisherId
      ? fetchMissingByIds(publishers.value, [publisherId], fetchPublisher)
      : Promise.resolve({ list: publishers.value, missingIds: [] as string[] }),
  ])

  authors.value = authorsResolved.list
  categories.value = categoriesResolved.list
  publishers.value = publisherResolved.list
  missingAuthorIds.value = authorsResolved.missingIds
  missingCategoryIds.value = categoriesResolved.missingIds
  missingPublisherId.value = publisherResolved.missingIds[0] ?? null
  resolvingSelections.value = false
}

async function loadPage(): Promise<void> {
  loading.value = true
  missingAuthorIds.value = []
  missingCategoryIds.value = []
  missingPublisherId.value = null
  resolvingSelections.value = false
  try {
    const opts = await fetchBookFormOptions()
    publishers.value = opts.publishers
    authors.value = opts.authors
    categories.value = opts.categories
    if (isEdit.value) {
      const book = await fetchBook(bookId.value)
      const authorIds = idsFromBook(book.author_ids, book.authors)
      const categoryIds = idsFromBook(book.category_ids, book.categories)
      const publisherId =
        book.publisher_id != null
          ? normalizeSelectId(book.publisher_id)
          : book.publisher
            ? normalizeSelectId(book.publisher.id)
            : ''

      await resolveSelectedRelations(authorIds, categoryIds, publisherId, book)

      form.title = book.title
      form.isbn = book.isbn ?? ''
      form.description = book.description ?? ''
      form.price = book.price
      form.stock_quantity = book.stock_quantity
      form.status = book.status
      form.publisher_id = publisherId
      form.author_ids = authorIds
      form.category_ids = categoryIds
      coverUrl.value = book.cover_url
    } else {
      coverUrl.value = null
      form.publisher_id = ''
      form.author_ids = []
      form.category_ids = []
    }
  } catch (e) {
    toast.error(e instanceof ApiError ? e.message : 'Không tải được dữ liệu.')
    router.push({ name: 'admin-books' })
  } finally {
    loading.value = false
  }
}

watch(
  () => [route.name, route.params.id] as const,
  () => {
    void loadPage()
  },
  { immediate: true },
)

function err(field: string): string | undefined {
  return firstFieldError(fieldErrors.value, field)
}

function onCoverUpdated(book: AdminBook): void {
  coverUrl.value = book.cover_url
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
    author_ids: form.author_ids.map(Number),
    category_ids: form.category_ids.map(Number),
  }
  try {
    if (isEdit.value) {
      await updateBook(bookId.value, payload)
      toast.success('Đã cập nhật sách.')
      router.push({ name: 'admin-books' })
    } else {
      const created = await createBook(payload)
      toast.success('Đã tạo sách. Bạn có thể upload ảnh bìa bên dưới.')
      router.replace({ name: 'admin-book-edit', params: { id: created.id } })
    }
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
  <CCard v-if="loading" class="admin-page book-form-page">
    <CCardBody class="admin-state"><CSpinner color="primary" /></CCardBody>
  </CCard>
  <CCard v-else class="admin-page book-form-page">
    <CCardHeader class="book-form-page__header">
      <div class="d-flex align-items-center gap-3 flex-wrap">
        <BookImagePlaceholder :src="coverUrl" width="48px" height="64px" />
        <div>
          <strong class="d-block">{{ isEdit ? 'Sửa sách' : 'Thêm sách' }}</strong>
          <small v-if="isEdit && form.title" class="text-body-secondary">{{ form.title }}</small>
        </div>
      </div>
    </CCardHeader>
    <CCardBody class="book-form-page__body">
      <CForm @submit.prevent="submit">
        <section class="book-form-section">
          <h6 class="book-form-section__title">Thông tin cơ bản</h6>
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
            <CCol cols="12">
              <CFormLabel for="description">Mô tả</CFormLabel>
              <CFormTextarea id="description" v-model="form.description" rows="4" />
            </CCol>
          </CRow>
        </section>

        <section class="book-form-section">
          <h6 class="book-form-section__title">Giá &amp; tồn kho</h6>
          <CRow class="g-3">
            <CCol sm="6" md="4">
              <CFormLabel for="price">Giá (VND) *</CFormLabel>
              <CFormInput id="price" v-model.number="form.price" type="number" min="0" required />
            </CCol>
            <CCol sm="6" md="4">
              <CFormLabel for="stock">Tồn kho</CFormLabel>
              <CFormInput id="stock" v-model.number="form.stock_quantity" type="number" min="0" />
            </CCol>
            <CCol sm="12" md="4">
              <CFormLabel for="status">Trạng thái</CFormLabel>
              <CFormSelect id="status" v-model="form.status">
                <option v-for="(label, key) in bookStatusLabel" :key="key" :value="key">{{ label }}</option>
              </CFormSelect>
            </CCol>
          </CRow>
        </section>

        <section class="book-form-section">
          <h6 class="book-form-section__title">Phân loại</h6>
          <CRow class="g-3">
            <CCol md="4">
              <CFormLabel for="publisher">Nhà xuất bản</CFormLabel>
              <SearchableSelect
                v-model="form.publisher_id"
                input-id="publisher"
                :options="publisherOptions"
                placeholder="Tìm nhà xuất bản..."
                :loading="loading || resolvingSelections"
                :resolving-labels="resolvingSelections"
                :missing-ids="missingPublisherId ? [missingPublisherId] : []"
                :invalid="!!err('publisher_id')"
                :error-text="err('publisher_id')"
              />
            </CCol>
            <CCol md="4">
              <CFormLabel for="authors">Tác giả</CFormLabel>
              <SearchableSelect
                v-model="form.author_ids"
                input-id="authors"
                multiple
                :options="authorOptions"
                placeholder="Tìm tác giả..."
                :loading="loading || resolvingSelections"
                :resolving-labels="resolvingSelections"
                :missing-ids="missingAuthorIds"
                :invalid="!!err('author_ids')"
                :error-text="err('author_ids')"
              />
            </CCol>
            <CCol md="4">
              <CFormLabel for="categories">Danh mục</CFormLabel>
              <SearchableSelect
                v-model="form.category_ids"
                input-id="categories"
                multiple
                :options="categoryOptions"
                placeholder="Tìm danh mục..."
                :loading="loading || resolvingSelections"
                :resolving-labels="resolvingSelections"
                :missing-ids="missingCategoryIds"
                :invalid="!!err('category_ids')"
                :error-text="err('category_ids')"
              />
            </CCol>
          </CRow>
        </section>

        <section class="book-form-section">
          <h6 class="book-form-section__title">Ảnh bìa</h6>
          <CCol v-if="isEdit" cols="12" class="px-0">
            <div class="book-form-cover">
              <BookCoverUploader :book-id="bookId" :cover-url="coverUrl" @updated="onCoverUpdated" />
            </div>
          </CCol>
          <CAlert v-else color="info" class="mb-0">
            Lưu sách trước, sau đó bạn có thể upload hoặc chọn ảnh bìa từ Cloudinary.
          </CAlert>
        </section>

        <div class="book-form-actions">
          <CButton color="primary" type="submit" :disabled="saving">
            <CSpinner v-if="saving" size="sm" class="me-2" aria-hidden="true" />
            {{ saving ? 'Đang lưu...' : 'Lưu' }}
          </CButton>
          <CButton
            color="secondary"
            variant="outline"
            type="button"
            :disabled="saving"
            @click="router.push({ name: 'admin-books' })"
          >
            Hủy
          </CButton>
        </div>
      </CForm>
    </CCardBody>
  </CCard>
</template>

<style scoped>
.book-form-page__body {
  overflow: visible;
}

.book-form-section + .book-form-section {
  margin-top: 1.75rem;
}

.book-form-section__title {
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--cui-secondary-color);
  border-bottom: 1px solid var(--cui-border-color-translucent, rgba(0, 0, 0, 0.08));
}

.book-form-cover {
  max-width: 22rem;
}

.book-form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  margin-top: 2rem;
  padding-top: 1rem;
  border-top: 1px solid var(--cui-border-color-translucent, rgba(0, 0, 0, 0.08));
  position: sticky;
  bottom: 0;
  z-index: 2;
  background: var(--cui-body-bg, #fff);
  padding-bottom: 0.25rem;
}

@media (max-width: 767.98px) {
  .book-form-cover {
    max-width: 100%;
  }
}
</style>
