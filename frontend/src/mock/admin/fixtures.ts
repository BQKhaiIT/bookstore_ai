import type {
  AdminBook,
  AdminOrder,
  AdminPayment,
  AdminReview,
  Author,
  Category,
  DashboardStats,
  Publisher,
} from '@/types/admin'
import type { User } from '@/types/user'

const publishers: Publisher[] = [
  { id: 1, name: 'NXB Trẻ' },
  { id: 2, name: 'NXB Kim Đồng' },
  { id: 3, name: 'Alpha Books' },
]

const categories: Category[] = [
  { id: 1, name: 'Văn học', slug: 'van-hoc' },
  { id: 2, name: 'Kinh tế', slug: 'kinh-te' },
  { id: 3, name: 'Khoa học', slug: 'khoa-hoc' },
]

const authors: Author[] = [
  { id: 1, name: 'Nguyễn Nhật Ánh', biography: 'Nhà văn Việt Nam.' },
  { id: 2, name: 'Haruki Murakami', biography: 'Nhà văn Nhật Bản.' },
  { id: 3, name: 'Yuval Noah Harari', biography: 'Sử gia, tác giả Sapiens.' },
]

const books: AdminBook[] = [
  {
    id: 1,
    publisher_id: 1,
    publisher: publishers[0],
    isbn: '978-604-1',
    title: 'Mắt Biếc',
    slug: 'mat-biec',
    description: 'Tiểu thuyết tuổi học trò.',
    price: 89000,
    stock_quantity: 42,
    cover_url: null,
    status: 'published',
    authors: [authors[0]],
    categories: [categories[0]],
    author_ids: [1],
    category_ids: [1],
  },
  {
    id: 2,
    publisher_id: 3,
    publisher: publishers[2],
    isbn: '978-604-2',
    title: 'Sapiens',
    slug: 'sapiens',
    description: 'Lược sử loài người.',
    price: 199000,
    stock_quantity: 15,
    cover_url: null,
    status: 'published',
    authors: [authors[2]],
    categories: [categories[2]],
    author_ids: [3],
    category_ids: [3],
  },
]

const users: User[] = [
  {
    id: 10,
    name: 'Khách Active',
    email: 'customer@demo.test',
    role: 'customer',
    status: 'active',
    email_verified_at: new Date().toISOString(),
    is_email_verified: true,
  },
  {
    id: 11,
    name: 'Khách Blocked',
    email: 'blocked@demo.test',
    role: 'customer',
    status: 'blocked',
    email_verified_at: new Date().toISOString(),
    is_email_verified: true,
  },
]

const orders: AdminOrder[] = [
  {
    id: 1,
    order_code: 'ORD-DEMO001',
    status: 'pending',
    subtotal_amount: 288000,
    shipping_fee: 30000,
    total_amount: 318000,
    shipping_address_snapshot: {
      recipient_name: 'Khách Active',
      phone: '0901234567',
      address_line: '123 Demo',
      province: 'Hà Nội',
    },
    customer_note: null,
    cancelled_at: null,
    user: users[0],
    created_at: new Date().toISOString(),
  },
  {
    id: 2,
    order_code: 'ORD-DEMO002',
    status: 'delivered',
    subtotal_amount: 199000,
    shipping_fee: 30000,
    total_amount: 229000,
    shipping_address_snapshot: { recipient_name: 'Khách Active', phone: '0901234567' },
    customer_note: 'Giao giờ hành chính',
    cancelled_at: null,
    user: users[0],
    created_at: new Date(Date.now() - 86400000).toISOString(),
  },
]

const payments: AdminPayment[] = [
  { id: 1, order_id: 1, method: 'cod', amount: 318000, status: 'pending', transaction_reference: null, paid_at: null },
  { id: 2, order_id: 2, method: 'cod', amount: 229000, status: 'paid', transaction_reference: null, paid_at: new Date().toISOString() },
]

const reviews: AdminReview[] = [
  {
    id: 1,
    book_id: 1,
    rating: 5,
    comment: 'Rất hay!',
    status: 'pending',
    user: users[0],
    book: books[0],
    created_at: new Date().toISOString(),
  },
  {
    id: 2,
    book_id: 2,
    rating: 4,
    comment: 'Sách tốt.',
    status: 'approved',
    user: users[0],
    book: books[1],
    created_at: new Date().toISOString(),
  },
]

const dashboard: DashboardStats = {
  revenue: 229000,
  sandbox_revenue: 0,
  orders_count: 2,
  books_count: 2,
  customers_count: 2,
  pending_orders_count: 1,
}

export const fixtures = {
  publishers,
  categories,
  authors,
  books,
  users,
  orders,
  payments,
  reviews,
  dashboard,
}
