import type { User } from '@/types/user'

export type BookStatus = 'draft' | 'published' | 'archived'
export type OrderStatus =
  | 'pending'
  | 'confirmed'
  | 'packing'
  | 'shipping'
  | 'delivered'
  | 'cancelled'
export type PaymentStatus = 'pending' | 'paid' | 'failed' | 'refunded'
export type PaymentMethod = 'cod' | 'bank_transfer'
export type ReviewStatus = 'pending' | 'approved' | 'rejected'

export interface DashboardStats {
  revenue: number
  orders_count: number
  books_count: number
  customers_count: number
  pending_orders_count: number
}

export interface Publisher {
  id: number
  name: string
  created_at?: string | null
  updated_at?: string | null
}

export interface Category {
  id: number
  name: string
  slug: string
  created_at?: string | null
  updated_at?: string | null
}

export interface Author {
  id: number
  name: string
  biography?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminBook {
  id: number
  publisher_id: number | null
  publisher?: { id: number; name: string } | null
  isbn: string | null
  title: string
  slug: string
  description: string | null
  price: number
  stock_quantity: number
  cover_url: string | null
  status: BookStatus
  author_ids?: number[]
  category_ids?: number[]
  authors?: Author[]
  categories?: Category[]
  created_at?: string | null
  updated_at?: string | null
}

export interface OrderItem {
  id: number
  book_id: number
  book_title: string
  quantity: number
  unit_price: number
  subtotal: number
}

export interface ShippingAddressSnapshot {
  recipient_name?: string
  phone?: string
  province?: string
  district?: string
  ward?: string
  address_line?: string
}

export interface AdminOrder {
  id: number
  order_code: string
  status: OrderStatus
  subtotal_amount: number
  shipping_fee: number
  total_amount: number
  shipping_address_snapshot: ShippingAddressSnapshot | null
  customer_note: string | null
  cancelled_at: string | null
  user?: User
  items?: OrderItem[]
  payment?: AdminPayment
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminPayment {
  id: number
  order_id: number
  method: PaymentMethod | string
  amount: number
  status: PaymentStatus
  transaction_reference: string | null
  paid_at: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface AdminReview {
  id: number
  book_id: number
  rating: number
  comment: string | null
  status: ReviewStatus
  user?: User
  book?: AdminBook
  created_at?: string | null
  updated_at?: string | null
}

export interface PaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
  links?: Record<string, string | null>
}
