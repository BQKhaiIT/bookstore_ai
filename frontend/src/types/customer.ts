export interface PublisherRef {
  id: number
  name: string
}

export interface AuthorRef {
  id: number
  name: string
}

export interface CategoryRef {
  id: number
  name: string
  slug: string
}

export interface StorefrontBanner {
  id: number
  type: 'hero' | 'banner'
  title: string
  description: string | null
  image_url: string | null
  mobile_image_url: string | null
  cta_text: string | null
  link_url: string | null
  position: string
  sort_order: number
}

export interface BookSummary {
  id: number
  isbn: string | null
  title: string
  slug: string
  description: string | null
  price: number
  stock_quantity: number
  cover_url: string | null
  status: string
  publisher?: PublisherRef | null
  authors?: AuthorRef[]
  categories?: CategoryRef[]
  average_rating?: number
  reviews_count?: number
  created_at?: string
}

export interface PaginatedMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginatedMeta
  links?: Record<string, string | null>
}

export interface CartItem {
  id: number
  book_id: number
  quantity: number
  unit_price: number
  subtotal: number
  book: BookSummary
}

export interface Cart {
  id: number
  items: CartItem[]
  item_count: number
  subtotal_amount: number
}

export interface UserAddress {
  id: number
  recipient_name: string
  phone: string
  province: string
  district: string
  ward: string
  address_line: string
  is_default: boolean
  created_at?: string
  updated_at?: string
}

export interface CheckoutPreview {
  subtotal_amount: number
  shipping_fee: number
  total_amount: number
  items: Array<{
    book_id: number
    book_title: string
    quantity: number
    unit_price: number
    subtotal: number
  }>
  shipping_address: UserAddress | null
  payment_methods: Array<{ code: string; label: string }>
}

export interface OrderItem {
  id: number
  book_id: number
  book_title: string
  quantity: number
  unit_price: number
  subtotal: number
}

export interface PaymentSummary {
  id: number
  method: string
  provider?: string | null
  environment?: string | null
  invoice_number?: string | null
  amount: number
  status: string
  transaction_reference?: string | null
  paid_at?: string | null
}

export interface SePayCheckoutSession {
  action_url: string
  method: string
  fields: Record<string, string>
  field_order: string[]
}

export interface OrderSummary {
  id: number
  order_code: string
  status: string
  subtotal_amount: number
  shipping_fee: number
  total_amount: number
  shipping_address_snapshot: Record<string, string>
  customer_note: string | null
  cancelled_at: string | null
  items?: OrderItem[]
  payment?: PaymentSummary
  created_at: string
  updated_at?: string
}

export interface Review {
  id: number
  book_id: number
  rating: number
  comment: string | null
  status: string
  user?: { id: number; name: string }
  created_at: string
  updated_at?: string
}

export type BookSort = 'newest' | 'popular' | 'price_asc' | 'price_desc'

export interface BookListParams {
  page?: number
  per_page?: number
  search?: string
  category_id?: number
  author_id?: number
  publisher_id?: number
  min_price?: number
  max_price?: number
  in_stock?: boolean
  sort?: BookSort
}
