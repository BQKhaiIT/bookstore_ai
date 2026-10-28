import {
  cilBook,
  cilBuilding,
  cilCart,
  cilCreditCard,
  cilPeople,
  cilLockLocked,
  cilSpeedometer,
  cilStar,
  cilTags,
  cilUser,
} from '@coreui/icons'

export interface AdminNavItem {
  name: string
  to: string
  icon: typeof cilBook
  permission?: string
  superAdminOnly?: boolean
}

export interface AdminNavGroup {
  title: string
  items: AdminNavItem[]
}

export const adminNavGroups: AdminNavGroup[] = [
  {
    title: 'Tổng quan',
    items: [
      { name: 'Tổng quan', to: '/admin/dashboard', icon: cilSpeedometer, permission: 'dashboard' },
    ],
  },
  {
    title: 'Sản phẩm',
    items: [
      { name: 'Sách', to: '/admin/books', icon: cilBook, permission: 'books' },
      { name: 'Danh mục', to: '/admin/categories', icon: cilTags, permission: 'categories' },
      { name: 'Tác giả', to: '/admin/authors', icon: cilUser, permission: 'authors' },
      { name: 'Nhà xuất bản', to: '/admin/publishers', icon: cilBuilding, permission: 'publishers' },
    ],
  },
  {
    title: 'Bán hàng',
    items: [
      { name: 'Đơn hàng', to: '/admin/orders', icon: cilCart, permission: 'orders' },
      { name: 'Thanh toán', to: '/admin/payments', icon: cilCreditCard, permission: 'payments' },
    ],
  },
  {
    title: 'Cộng đồng',
    items: [
      { name: 'Người dùng', to: '/admin/users', icon: cilPeople, permission: 'users' },
      { name: 'Đánh giá', to: '/admin/reviews', icon: cilStar, permission: 'reviews' },
    ],
  },
  {
    title: 'Hệ thống',
    items: [
      {
        name: 'Quản trị viên',
        to: '/admin/staff',
        icon: cilLockLocked,
        superAdminOnly: true,
      },
    ],
  },
]

export const adminNav: AdminNavItem[] = adminNavGroups.flatMap((group) => group.items)

export const adminRouteTitles: Record<string, string> = {
  'admin-dashboard': 'Tổng quan',
  'admin-books': 'Quản lý sách',
  'admin-book-create': 'Thêm sách',
  'admin-book-edit': 'Sửa sách',
  'admin-categories': 'Danh mục',
  'admin-authors': 'Tác giả',
  'admin-publishers': 'Nhà xuất bản',
  'admin-orders': 'Đơn hàng',
  'admin-order-detail': 'Chi tiết đơn hàng',
  'admin-payments': 'Thanh toán',
  'admin-users': 'Người dùng',
  'admin-reviews': 'Đánh giá',
  'admin-staff': 'Quản trị viên',
}

const breadcrumbParents: Record<string, { label: string; to: string }> = {
  'admin-book-create': { label: 'Quản lý sách', to: '/admin/books' },
  'admin-book-edit': { label: 'Quản lý sách', to: '/admin/books' },
  'admin-order-detail': { label: 'Đơn hàng', to: '/admin/orders' },
}

export interface AdminCrumb {
  label: string
  to?: string
}

export function adminBreadcrumb(routeName: string | symbol | null | undefined): AdminCrumb[] {
  const name = typeof routeName === 'string' ? routeName : ''
  const current = adminRouteTitles[name] ?? 'Quản trị'
  if (name === 'admin-dashboard' || name === '') {
    return [{ label: 'Tổng quan' }]
  }
  const crumbs: AdminCrumb[] = [{ label: 'Tổng quan', to: '/admin/dashboard' }]
  const parent = breadcrumbParents[name]
  if (parent) {
    crumbs.push(parent)
  }
  crumbs.push({ label: current })
  return crumbs
}
