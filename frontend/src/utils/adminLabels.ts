import type { BookStatus, OrderStatus, PaymentStatus, ReviewStatus } from '@/types/admin'
import type { UserRole, UserStatus } from '@/types/user'

export const bookStatusLabel: Record<BookStatus, string> = {
  draft: 'Nháp',
  published: 'Đang bán',
  archived: 'Lưu trữ',
}

export const orderStatusLabel: Record<OrderStatus, string> = {
  pending: 'Chờ xử lý',
  confirmed: 'Đã xác nhận',
  packing: 'Đóng gói',
  shipping: 'Đang giao',
  delivered: 'Đã giao',
  cancelled: 'Đã hủy',
}

export const paymentStatusLabel: Record<PaymentStatus, string> = {
  pending: 'Chờ thanh toán',
  paid: 'Đã thanh toán',
  failed: 'Thất bại',
  refunded: 'Hoàn tiền',
}

export const reviewStatusLabel: Record<ReviewStatus, string> = {
  pending: 'Chờ duyệt',
  approved: 'Đã duyệt',
  rejected: 'Từ chối',
}

export const userStatusLabel: Record<UserStatus, string> = {
  active: 'Hoạt động',
  blocked: 'Đã khóa',
}

export const userRoleLabel: Record<UserRole, string> = {
  customer: 'Khách hàng',
  admin: 'Quản trị',
  super_admin: 'Super Admin',
}

/** Allowed order transitions (mirror backend OrderStatusService). */
export const orderTransitions: Record<OrderStatus, OrderStatus[]> = {
  pending: ['confirmed', 'cancelled'],
  confirmed: ['packing', 'cancelled'],
  packing: ['shipping', 'cancelled'],
  shipping: ['delivered', 'cancelled'],
  delivered: [],
  cancelled: [],
}

export function statusBadgeColor(
  kind: 'book' | 'order' | 'payment' | 'review' | 'user',
  value: string,
): string {
  if (kind === 'book') {
    if (value === 'published') return 'success'
    if (value === 'draft') return 'secondary'
    return 'dark'
  }
  if (kind === 'order') {
    if (value === 'delivered') return 'success'
    if (value === 'cancelled') return 'danger'
    if (value === 'pending') return 'warning'
    return 'info'
  }
  if (kind === 'payment') {
    if (value === 'paid') return 'success'
    if (value === 'pending') return 'warning'
    if (value === 'failed') return 'danger'
    return 'secondary'
  }
  if (kind === 'review') {
    if (value === 'approved') return 'success'
    if (value === 'rejected') return 'danger'
    return 'warning'
  }
  if (value === 'active') return 'success'
  return 'danger'
}

export const adminPermissionLabel: Record<string, string> = {
  dashboard: 'Tổng quan',
  books: 'Sách',
  categories: 'Danh mục',
  authors: 'Tác giả',
  publishers: 'Nhà xuất bản',
  orders: 'Đơn hàng',
  payments: 'Thanh toán',
  reviews: 'Đánh giá',
  users: 'Người dùng',
}

export function permissionLabel(key: string): string {
  return adminPermissionLabel[key] ?? key
}
