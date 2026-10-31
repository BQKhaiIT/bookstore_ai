import { api } from '@/api/client'
import type {
  AuthorRef,
  BookListParams,
  BookSummary,
  CategoryRef,
  PaginatedResponse,
  PublisherRef,
  Review,
  StorefrontBanner,
} from '@/types/customer'

function unwrapPaginated<T>(payload: {
  data: T[]
  meta?: PaginatedResponse<T>['meta']
  links?: PaginatedResponse<T>['links']
}): PaginatedResponse<T> {
  return {
    data: payload.data,
    meta: payload.meta ?? {
      current_page: 1,
      last_page: 1,
      per_page: payload.data.length,
      total: payload.data.length,
    },
    links: payload.links,
  }
}

export async function fetchStorefrontBanners(): Promise<StorefrontBanner[]> {
  const { data } = await api.get<{ data: StorefrontBanner[] }>('/api/v1/banners')
  return data.data
}

export async function fetchBooks(params: BookListParams = {}): Promise<PaginatedResponse<BookSummary>> {
  const { data } = await api.get<PaginatedResponse<BookSummary>>('/api/v1/books', { params })
  return unwrapPaginated(data)
}

export async function fetchBook(slug: string): Promise<BookSummary> {
  const { data } = await api.get<{ data: BookSummary }>(`/api/v1/books/${encodeURIComponent(slug)}`)
  return data.data
}

export async function fetchRelatedBooks(slug: string): Promise<BookSummary[]> {
  const { data } = await api.get<{ data: BookSummary[] }>(
    `/api/v1/books/${encodeURIComponent(slug)}/related`,
  )
  return data.data
}

export async function fetchCategories(search?: string): Promise<CategoryRef[]> {
  const { data } = await api.get<PaginatedResponse<CategoryRef>>('/api/v1/categories', {
    params: search ? { search } : undefined,
  })
  return data.data
}

export async function fetchAuthors(search?: string): Promise<AuthorRef[]> {
  const { data } = await api.get<PaginatedResponse<AuthorRef>>('/api/v1/authors', {
    params: search ? { search } : undefined,
  })
  return data.data
}

export async function fetchPublishers(search?: string): Promise<PublisherRef[]> {
  const { data } = await api.get<PaginatedResponse<PublisherRef>>('/api/v1/publishers', {
    params: search ? { search } : undefined,
  })
  return data.data
}

export async function fetchBookReviews(slug: string, page = 1): Promise<PaginatedResponse<Review>> {
  const { data } = await api.get<PaginatedResponse<Review>>(
    `/api/v1/books/${encodeURIComponent(slug)}/reviews`,
    { params: { page } },
  )
  return unwrapPaginated(data)
}
