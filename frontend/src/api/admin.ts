import { api, ensureCsrfCookie } from '@/api/client'
import type {
  AdminBook,
  AdminOrder,
  AdminPayment,
  AdminReview,
  Author,
  Category,
  CloudinaryImage,
  CloudinaryImageListMeta,
  DashboardStats,
  PaginatedResponse,
  Publisher,
} from '@/types/admin'
import type { User } from '@/types/user'
import { getMockAdminApi } from '@/mock/admin'

const prefix = '/api/v1/admin'
const useMock = import.meta.env.DEV && import.meta.env.VITE_USE_MOCK === 'true'

function delegate<T>(real: () => Promise<T>, mock: () => Promise<T>): Promise<T> {
  return useMock ? mock() : real()
}

export type ListParams = Record<string, string | number | undefined>

function buildQuery(params: ListParams): string {
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== '') {
      search.set(key, String(value))
    }
  }
  const qs = search.toString()
  return qs ? `?${qs}` : ''
}

async function getPaginated<T>(path: string, params: ListParams = {}): Promise<PaginatedResponse<T>> {
  const { data } = await api.get<PaginatedResponse<T>>(`${prefix}${path}${buildQuery(params)}`)
  return data
}

export async function fetchDashboard(): Promise<DashboardStats> {
  return delegate(
    async () => {
      const { data } = await api.get<{ data: DashboardStats }>(`${prefix}/dashboard`)
      return data.data
    },
    () => getMockAdminApi().fetchDashboard(),
  )
}

export async function fetchBooks(params: ListParams = {}): Promise<PaginatedResponse<AdminBook>> {
  return delegate(
    () => getPaginated<AdminBook>('/books', params),
    () => getMockAdminApi().fetchBooks(params),
  )
}

export async function fetchBook(id: number): Promise<AdminBook> {
  return delegate(
    async () => {
      const { data } = await api.get<{ data: AdminBook }>(`${prefix}/books/${id}`)
      return data.data
    },
    () => getMockAdminApi().fetchBook(id),
  )
}

export async function createBook(payload: Record<string, unknown>): Promise<AdminBook> {
  return delegate(
    async () => {
      const { data } = await api.post<{ data: AdminBook }>(`${prefix}/books`, payload)
      return data.data
    },
    () => getMockAdminApi().createBook(payload),
  )
}

export async function updateBook(id: number, payload: Record<string, unknown>): Promise<AdminBook> {
  return delegate(
    async () => {
      const { data } = await api.patch<{ data: AdminBook }>(`${prefix}/books/${id}`, payload)
      return data.data
    },
    () => getMockAdminApi().updateBook(id, payload),
  )
}

export async function deleteBook(id: number): Promise<void> {
  return delegate(
    async () => {
      await api.delete(`${prefix}/books/${id}`)
    },
    () => getMockAdminApi().deleteBook(id),
  )
}

export async function uploadBookCover(bookId: number, file: File): Promise<AdminBook> {
  await ensureCsrfCookie()
  const formData = new FormData()
  formData.append('cover', file)
  const { data } = await api.post<{ data: AdminBook }>(`${prefix}/books/${bookId}/cover`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function assignBookCoverFromCloudinary(bookId: number, publicId: string): Promise<AdminBook> {
  const { data } = await api.put<{ data: AdminBook }>(`${prefix}/books/${bookId}/cover`, {
    public_id: publicId,
  })
  return data.data
}

export async function removeBookCover(bookId: number): Promise<AdminBook> {
  const { data } = await api.delete<{ data: AdminBook }>(`${prefix}/books/${bookId}/cover`)
  return data.data
}

export async function fetchCloudinaryImages(
  params: ListParams = {},
): Promise<{ data: CloudinaryImage[]; meta: CloudinaryImageListMeta }> {
  const { data } = await api.get<{ data: CloudinaryImage[]; meta: CloudinaryImageListMeta }>(
    `${prefix}/cloudinary/images${buildQuery(params)}`,
  )
  return data
}

export async function fetchCategories(params: ListParams = {}): Promise<PaginatedResponse<Category>> {
  return delegate(
    () => getPaginated<Category>('/categories', params),
    () => getMockAdminApi().fetchCategories(params),
  )
}

export async function createCategory(payload: { name: string; slug?: string }): Promise<Category> {
  return delegate(
    async () => {
      const { data } = await api.post<{ data: Category }>(`${prefix}/categories`, payload)
      return data.data
    },
    () => getMockAdminApi().createCategory(payload),
  )
}

export async function updateCategory(id: number, payload: { name?: string; slug?: string }): Promise<Category> {
  return delegate(
    async () => {
      const { data } = await api.patch<{ data: Category }>(`${prefix}/categories/${id}`, payload)
      return data.data
    },
    () => getMockAdminApi().updateCategory(id, payload),
  )
}

export async function deleteCategory(id: number): Promise<void> {
  return delegate(
    async () => {
      await api.delete(`${prefix}/categories/${id}`)
    },
    () => getMockAdminApi().deleteCategory(id),
  )
}

export async function fetchAuthors(params: ListParams = {}): Promise<PaginatedResponse<Author>> {
  return delegate(
    () => getPaginated<Author>('/authors', params),
    () => getMockAdminApi().fetchAuthors(params),
  )
}

export async function createAuthor(payload: { name: string; biography?: string }): Promise<Author> {
  return delegate(
    async () => {
      const { data } = await api.post<{ data: Author }>(`${prefix}/authors`, payload)
      return data.data
    },
    () => getMockAdminApi().createAuthor(payload),
  )
}

export async function updateAuthor(id: number, payload: { name?: string; biography?: string }): Promise<Author> {
  return delegate(
    async () => {
      const { data } = await api.patch<{ data: Author }>(`${prefix}/authors/${id}`, payload)
      return data.data
    },
    () => getMockAdminApi().updateAuthor(id, payload),
  )
}

export async function deleteAuthor(id: number): Promise<void> {
  return delegate(
    async () => {
      await api.delete(`${prefix}/authors/${id}`)
    },
    () => getMockAdminApi().deleteAuthor(id),
  )
}

export async function fetchPublishers(params: ListParams = {}): Promise<PaginatedResponse<Publisher>> {
  return delegate(
    () => getPaginated<Publisher>('/publishers', params),
    () => getMockAdminApi().fetchPublishers(params),
  )
}

export async function createPublisher(payload: { name: string }): Promise<Publisher> {
  return delegate(
    async () => {
      const { data } = await api.post<{ data: Publisher }>(`${prefix}/publishers`, payload)
      return data.data
    },
    () => getMockAdminApi().createPublisher(payload),
  )
}

export async function updatePublisher(id: number, payload: { name: string }): Promise<Publisher> {
  return delegate(
    async () => {
      const { data } = await api.patch<{ data: Publisher }>(`${prefix}/publishers/${id}`, payload)
      return data.data
    },
    () => getMockAdminApi().updatePublisher(id, payload),
  )
}

export async function deletePublisher(id: number): Promise<void> {
  return delegate(
    async () => {
      await api.delete(`${prefix}/publishers/${id}`)
    },
    () => getMockAdminApi().deletePublisher(id),
  )
}

export async function fetchOrders(params: ListParams = {}): Promise<PaginatedResponse<AdminOrder>> {
  return delegate(
    () => getPaginated<AdminOrder>('/orders', params),
    () => getMockAdminApi().fetchOrders(params),
  )
}

export async function fetchOrder(id: number): Promise<AdminOrder> {
  return delegate(
    async () => {
      const { data } = await api.get<{ data: AdminOrder }>(`${prefix}/orders/${id}`)
      return data.data
    },
    () => getMockAdminApi().fetchOrder(id),
  )
}

export async function updateOrderStatus(id: number, status: string): Promise<AdminOrder> {
  return delegate(
    async () => {
      const { data } = await api.patch<{ data: AdminOrder }>(`${prefix}/orders/${id}/status`, { status })
      return data.data
    },
    () => getMockAdminApi().updateOrderStatus(id, status),
  )
}

export async function fetchPayments(params: ListParams = {}): Promise<PaginatedResponse<AdminPayment>> {
  return delegate(
    () => getPaginated<AdminPayment>('/payments', params),
    () => getMockAdminApi().fetchPayments(params),
  )
}

export async function confirmCodPayment(id: number): Promise<AdminPayment> {
  return delegate(
    async () => {
      const { data } = await api.post<{ data: AdminPayment }>(`${prefix}/payments/${id}/confirm-cod`)
      return data.data
    },
    () => getMockAdminApi().confirmCodPayment(id),
  )
}

export async function fetchUsers(params: ListParams = {}): Promise<PaginatedResponse<User>> {
  return delegate(
    () => getPaginated<User>('/users', params),
    () => getMockAdminApi().fetchUsers(params),
  )
}

export async function updateUserStatus(id: number, status: 'active' | 'blocked'): Promise<User> {
  return delegate(
    async () => {
      const { data } = await api.patch<{ data: User }>(`${prefix}/users/${id}/status`, { status })
      return data.data
    },
    () => getMockAdminApi().updateUserStatus(id, status),
  )
}

export async function fetchReviews(params: ListParams = {}): Promise<PaginatedResponse<AdminReview>> {
  return delegate(
    () => getPaginated<AdminReview>('/reviews', params),
    () => getMockAdminApi().fetchReviews(params),
  )
}

export async function updateReviewStatus(id: number, status: 'approved' | 'rejected'): Promise<AdminReview> {
  return delegate(
    async () => {
      const { data } = await api.patch<{ data: AdminReview }>(`${prefix}/reviews/${id}`, { status })
      return data.data
    },
    () => getMockAdminApi().updateReviewStatus(id, status),
  )
}

/** Load all options for book form (unpaginated lists). */
export async function fetchBookFormOptions(): Promise<{
  publishers: Publisher[]
  authors: Author[]
  categories: Category[]
}> {
  const [publishers, authors, categories] = await Promise.all([
    fetchPublishers({ per_page: 100 }),
    fetchAuthors({ per_page: 100 }),
    fetchCategories({ per_page: 100 }),
  ])
  return {
    publishers: publishers.data,
    authors: authors.data,
    categories: categories.data,
  }
}

export interface AdminInvitationDto {
  id: number
  email: string
  name: string
  role: string
  permissions: string[]
  expires_at: string
}

export interface StaffOverview {
  staff: User[]
  pending_invitations: AdminInvitationDto[]
  available_permissions: string[]
}

export async function fetchStaffOverview(): Promise<StaffOverview> {
  const { data } = await api.get<{ data: StaffOverview }>(`${prefix}/staff`)
  return data.data
}

export async function inviteStaffAdmin(payload: {
  name: string
  email: string
  permissions: string[]
}): Promise<void> {
  await api.post(`${prefix}/staff/invitations`, payload)
}

export async function updateStaffPermissions(userId: number, permissions: string[]): Promise<void> {
  await api.patch(`${prefix}/staff/users/${userId}/permissions`, { permissions })
}

export async function updateStaffStatus(userId: number, status: 'active' | 'blocked'): Promise<void> {
  await api.patch(`${prefix}/staff/users/${userId}/status`, { status })
}

export async function cancelStaffInvitation(id: number): Promise<void> {
  await api.delete(`${prefix}/staff/invitations/${id}`)
}
