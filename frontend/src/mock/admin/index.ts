import type {
  AdminBook,
  AdminOrder,
  AdminPayment,
  AdminReview,
  Author,
  Category,
  DashboardStats,
  PaginatedResponse,
  Publisher,
} from '@/types/admin'
import type { User } from '@/types/user'
import { fixtures } from '@/mock/admin/fixtures'

type ListParams = Record<string, string | number | undefined>

function paginate<T>(items: T[], params: ListParams): PaginatedResponse<T> {
  const page = Number(params.page ?? 1)
  const perPage = Number(params.per_page ?? 15)
  const search = String(params.search ?? '').toLowerCase()
  let filtered = items
  if (search) {
    filtered = items.filter((item) => JSON.stringify(item).toLowerCase().includes(search))
  }
  const total = filtered.length
  const lastPage = Math.max(1, Math.ceil(total / perPage))
  const current = Math.min(Math.max(page, 1), lastPage)
  const start = (current - 1) * perPage
  const slice = filtered.slice(start, start + perPage)
  return {
    data: slice,
    meta: {
      current_page: current,
      from: total ? start + 1 : null,
      last_page: lastPage,
      per_page: perPage,
      to: total ? start + slice.length : null,
      total,
    },
  }
}

let mockState = structuredClone(fixtures)

export function getMockAdminApi() {
  return {
    async fetchDashboard(): Promise<DashboardStats> {
      return mockState.dashboard
    },
    async fetchBooks(params: ListParams): Promise<PaginatedResponse<AdminBook>> {
      return paginate(mockState.books, params)
    },
    async fetchBook(id: number): Promise<AdminBook> {
      const book = mockState.books.find((b) => b.id === id)
      if (!book) throw new Error('Not found')
      return book
    },
    async createBook(payload: Record<string, unknown>): Promise<AdminBook> {
      const id = Math.max(0, ...mockState.books.map((b) => b.id)) + 1
      const book = { ...payload, id, slug: `book-${id}` } as AdminBook
      mockState.books.unshift(book)
      return book
    },
    async updateBook(id: number, payload: Record<string, unknown>): Promise<AdminBook> {
      const idx = mockState.books.findIndex((b) => b.id === id)
      mockState.books[idx] = { ...mockState.books[idx], ...payload } as AdminBook
      return mockState.books[idx]
    },
    async deleteBook(id: number): Promise<void> {
      mockState.books = mockState.books.filter((b) => b.id !== id)
    },
    async fetchCategories(params: ListParams): Promise<PaginatedResponse<Category>> {
      return paginate(mockState.categories, params)
    },
    async createCategory(payload: { name: string }): Promise<Category> {
      const id = Math.max(0, ...mockState.categories.map((c) => c.id)) + 1
      const cat = { id, name: payload.name, slug: payload.name.toLowerCase() }
      mockState.categories.push(cat)
      return cat
    },
    async updateCategory(id: number, payload: { name?: string }): Promise<Category> {
      const cat = mockState.categories.find((c) => c.id === id)!
      if (payload.name) cat.name = payload.name
      return cat
    },
    async deleteCategory(id: number): Promise<void> {
      mockState.categories = mockState.categories.filter((c) => c.id !== id)
    },
    async fetchAuthors(params: ListParams): Promise<PaginatedResponse<Author>> {
      return paginate(mockState.authors, params)
    },
    async createAuthor(payload: { name: string; biography?: string }): Promise<Author> {
      const id = Math.max(0, ...mockState.authors.map((a) => a.id)) + 1
      const author = { id, ...payload }
      mockState.authors.push(author)
      return author
    },
    async updateAuthor(id: number, payload: { name?: string; biography?: string }): Promise<Author> {
      const author = mockState.authors.find((a) => a.id === id)!
      Object.assign(author, payload)
      return author
    },
    async deleteAuthor(id: number): Promise<void> {
      mockState.authors = mockState.authors.filter((a) => a.id !== id)
    },
    async fetchPublishers(params: ListParams): Promise<PaginatedResponse<Publisher>> {
      return paginate(mockState.publishers, params)
    },
    async createPublisher(payload: { name: string }): Promise<Publisher> {
      const id = Math.max(0, ...mockState.publishers.map((p) => p.id)) + 1
      const pub = { id, name: payload.name }
      mockState.publishers.push(pub)
      return pub
    },
    async updatePublisher(id: number, payload: { name: string }): Promise<Publisher> {
      const pub = mockState.publishers.find((p) => p.id === id)!
      pub.name = payload.name
      return pub
    },
    async deletePublisher(id: number): Promise<void> {
      mockState.publishers = mockState.publishers.filter((p) => p.id !== id)
    },
    async fetchOrders(params: ListParams): Promise<PaginatedResponse<AdminOrder>> {
      let items = mockState.orders
      if (params.status) {
        items = items.filter((o) => o.status === params.status)
      }
      return paginate(items, params)
    },
    async fetchOrder(id: number): Promise<AdminOrder> {
      return mockState.orders.find((o) => o.id === id)!
    },
    async updateOrderStatus(id: number, status: string): Promise<AdminOrder> {
      const order = mockState.orders.find((o) => o.id === id)!
      order.status = status as AdminOrder['status']
      return order
    },
    async fetchPayments(params: ListParams): Promise<PaginatedResponse<AdminPayment>> {
      let items = mockState.payments
      if (params.status) {
        items = items.filter((p) => p.status === params.status)
      }
      return paginate(items, params)
    },
    async confirmCodPayment(id: number): Promise<AdminPayment> {
      const payment = mockState.payments.find((p) => p.id === id)!
      payment.status = 'paid'
      payment.paid_at = new Date().toISOString()
      return payment
    },
    async fetchUsers(params: ListParams): Promise<PaginatedResponse<User>> {
      let items = mockState.users
      if (params.status) items = items.filter((u) => u.status === params.status)
      if (params.role) items = items.filter((u) => u.role === params.role)
      return paginate(items, params)
    },
    async updateUserStatus(id: number, status: 'active' | 'blocked'): Promise<User> {
      const user = mockState.users.find((u) => u.id === id)!
      user.status = status
      return user
    },
    async fetchReviews(params: ListParams): Promise<PaginatedResponse<AdminReview>> {
      let items = mockState.reviews
      if (params.status) items = items.filter((r) => r.status === params.status)
      return paginate(items, params)
    },
    async updateReviewStatus(id: number, status: 'approved' | 'rejected'): Promise<AdminReview> {
      const review = mockState.reviews.find((r) => r.id === id)!
      review.status = status
      return review
    },
  }
}
