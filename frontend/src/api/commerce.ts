import { api, ensureCsrfCookie } from '@/api/client'
import type {
  Cart,
  CheckoutPreview,
  OrderSummary,
  PaginatedResponse,
  PaymentSummary,
  Review,
  SePayCheckoutSession,
  UserAddress,
} from '@/types/customer'

async function withCsrf<T>(action: () => Promise<T>): Promise<T> {
  await ensureCsrfCookie()
  return action()
}

export async function fetchCart(): Promise<Cart> {
  const { data } = await api.get<{ data: Cart }>('/api/v1/cart')
  return data.data
}

export async function addCartItem(bookId: number, quantity = 1): Promise<Cart> {
  return withCsrf(async () => {
    const { data } = await api.post<{ data: Cart }>('/api/v1/cart/items', {
      book_id: bookId,
      quantity,
    })
    return data.data
  })
}

export async function updateCartItem(itemId: number, quantity: number): Promise<Cart> {
  return withCsrf(async () => {
    const { data } = await api.patch<{ data: Cart }>(`/api/v1/cart/items/${itemId}`, { quantity })
    return data.data
  })
}

export async function removeCartItem(itemId: number): Promise<Cart> {
  return withCsrf(async () => {
    const { data } = await api.delete<{ data: Cart }>(`/api/v1/cart/items/${itemId}`)
    return data.data
  })
}

export async function fetchWishlistBookIds(): Promise<number[]> {
  const { data } = await api.get<{ data: Array<{ id: number }> }>('/api/v1/wishlist')
  return data.data.map((b) => b.id)
}

export async function fetchWishlistBooks(): Promise<import('@/types/customer').BookSummary[]> {
  const { data } = await api.get<{ data: import('@/types/customer').BookSummary[] }>('/api/v1/wishlist')
  return data.data
}

export async function addWishlistItem(bookId: number): Promise<void> {
  await withCsrf(async () => {
    await api.post('/api/v1/wishlist', { book_id: bookId })
  })
}

export async function removeWishlistItem(bookId: number): Promise<void> {
  await withCsrf(async () => {
    await api.delete(`/api/v1/wishlist/${bookId}`)
  })
}

export async function fetchAddresses(): Promise<UserAddress[]> {
  const { data } = await api.get<{ data: UserAddress[] }>('/api/v1/addresses')
  return data.data
}

export async function createAddress(payload: Omit<UserAddress, 'id' | 'created_at' | 'updated_at'>): Promise<UserAddress> {
  return withCsrf(async () => {
    const { data } = await api.post<{ data: UserAddress }>('/api/v1/addresses', payload)
    return data.data
  })
}

export async function updateAddress(id: number, payload: Partial<UserAddress>): Promise<UserAddress> {
  return withCsrf(async () => {
    const { data } = await api.patch<{ data: UserAddress }>(`/api/v1/addresses/${id}`, payload)
    return data.data
  })
}

export async function deleteAddress(id: number): Promise<void> {
  await withCsrf(async () => {
    await api.delete(`/api/v1/addresses/${id}`)
  })
}

export async function fetchCheckoutPreview(addressId?: number): Promise<CheckoutPreview> {
  const { data } = await api.get<{ data: CheckoutPreview }>('/api/v1/checkout/preview', {
    params: addressId ? { address_id: addressId } : undefined,
  })
  return data.data
}

export async function placeOrder(
  addressId: number,
  idempotencyKey: string,
  customerNote?: string,
  paymentMethod = 'cod',
): Promise<OrderSummary> {
  return withCsrf(async () => {
    const { data } = await api.post<{ data: OrderSummary }>(
      '/api/v1/checkout',
      { address_id: addressId, customer_note: customerNote ?? null, payment_method: paymentMethod },
      { headers: { 'Idempotency-Key': idempotencyKey } },
    )
    return data.data
  })
}

export async function fetchOrderPayment(orderId: number): Promise<PaymentSummary> {
  const { data } = await api.get<{ data: PaymentSummary }>(`/api/v1/orders/${orderId}/payment`)
  return data.data
}

export async function createSePayPaymentSession(orderId: number): Promise<{
  checkout: SePayCheckoutSession
  payment: PaymentSummary
}> {
  return withCsrf(async () => {
    const { data } = await api.post<{
      data: { checkout: SePayCheckoutSession; payment: PaymentSummary }
    }>(`/api/v1/orders/${orderId}/payment-sessions`, {})
    return data.data
  })
}

export async function fetchOrders(
  page = 1,
  status?: string,
  perPage?: number,
): Promise<PaginatedResponse<OrderSummary>> {
  const { data } = await api.get<PaginatedResponse<OrderSummary>>('/api/v1/orders', {
    params: { page, status: status || undefined, per_page: perPage },
  })
  return data
}

export async function fetchOrder(id: number): Promise<OrderSummary> {
  const { data } = await api.get<{ data: OrderSummary }>(`/api/v1/orders/${id}`)
  return data.data
}

export async function cancelOrder(id: number): Promise<OrderSummary> {
  return withCsrf(async () => {
    const { data } = await api.post<{ data: OrderSummary }>(`/api/v1/orders/${id}/cancel`)
    return data.data
  })
}

export async function fetchMyReview(slug: string): Promise<Review | null> {
  const { data } = await api.get<{ data: Review | null }>(
    `/api/v1/books/${encodeURIComponent(slug)}/reviews/mine`,
  )
  return data.data
}

export async function submitReview(slug: string, rating: number, comment?: string): Promise<Review> {
  return withCsrf(async () => {
    const { data } = await api.post<{ data: Review }>(
      `/api/v1/books/${encodeURIComponent(slug)}/reviews`,
      { rating, comment: comment ?? null },
    )
    return data.data
  })
}
