# Schema audit — `bookstore_ai`

Nguồn sự thật: PostgreSQL 18.6, database `bookstore_ai`, schema `public`, đọc catalog bằng `SELECT` (read-only) ngày 2026-11-04. Không có bản ghi và không có credential trong tài liệu này.

Đối chiếu: 12/12 file trong `backend/database/migrations/` đã ghi trong bảng `migrations`. Mọi `foreignId()->constrained()` trong migration có FK thật. `docs/database/schema.sql` là bản thiết kế tham chiếu và **không** khớp schema đang chạy.

## Số liệu

| Hạng mục | PostgreSQL | DBML / Mermaid |
|---|---:|---:|
| Bảng | 27 | 27 |
| Cột | 199 | 199 |
| Foreign key | 19 | 19 |
| CHECK | 1 (`users_role_check`) | ghi trên cột `users.role` |
| ON UPDATE | 19/19 `NO ACTION` | 19/19 `no action` |

UNIQUE constraint: 16. Thêm 1 unique index một phần: `uq_payments_one_paid_per_order` trên `payments(order_id) WHERE status = 'paid'`.

## Nhóm nghiệp vụ

| Nhóm | Bảng trong PostgreSQL |
|---|---|
| Auth / RBAC | `users`, `user_addresses`, `password_reset_tokens`, `personal_access_tokens`, `sessions`, `admin_invitations`, `admin_audit_logs` |
| Catalog | `publishers`, `authors`, `categories`, `books`, `book_authors`, `book_categories` |
| Cart | `carts`, `cart_items`, `wishlists` |
| Orders | `orders`, `order_items` |
| Payments | `payments` |
| Reviews | `reviews` |
| CMS | `banners` |
| Recommendation | không có bảng |
| Chatbot | không có bảng |
| Hạ tầng Laravel | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations` |

`wishlists` không phải dòng giỏ hàng; được xếp cạnh cart vì cùng lớp dữ liệu trước đơn. Tiền là `bigint` VND. Không có kiểu `ENUM` của PostgreSQL.

## Quan hệ được PostgreSQL enforce

- **1-1:** `carts.user_id` UNIQUE NOT NULL → `users.id`, ON DELETE CASCADE.
- **N-N:** `book_authors` (PK `book_id, author_id`) và `book_categories` (PK `book_id, category_id`). Cả bốn FK ON DELETE CASCADE.
- **1-N, ON DELETE CASCADE:** `user_addresses.user_id`, `cart_items.cart_id`, `reviews.user_id`, `wishlists.user_id`.
- **1-N, ON DELETE RESTRICT:** `orders.user_id`, `order_items.order_id`, `order_items.book_id`, `cart_items.book_id`, `wishlists.book_id`, `reviews.book_id`, `payments.order_id`.
- **1-N, ON DELETE SET NULL:** `books.publisher_id`, `admin_audit_logs.actor_id`, `admin_invitations.invited_by`.

## Cột trông giống quan hệ nhưng không phải FK

Các cột này không được vẽ thành `Ref` / quan hệ ERD.

- `sessions.user_id`: nullable, có index, migration chỉ gọi `index()` chứ không `constrained()`. Không có quan hệ Eloquent.
- `personal_access_tokens.tokenable_type` + `tokenable_id`: morph index của Laravel. Không có FK. `User` không khai báo `HasApiTokens`.
- `admin_audit_logs.target_type` + `target_id`: không có FK và không có quan hệ Eloquent tới bảng đích.

Mọi quan hệ trong `backend/app/Models/` đều có FK tương ứng: user addresses/cart/orders/wishlists; cart items; order items/payments; book publisher/authors/categories/reviews/order items; review user/book; admin invitation `invited_by`; audit `actor_id`.

## Lệch giữa các nguồn

Migration và catalog đang chạy khớp các điểm đã kiểm: RBAC (`admin_permissions`, `admin_invitations`, `admin_audit_logs`), Cloudinary (`books.cover_public_id`, `cover_asset_id`), commerce (địa chỉ, cart, wishlist), `banners`, cột SePay trên `payments` (`provider`, `environment`, `invoice_number`, `metadata`).

`docs/database/schema.sql` và `docs/database/erd.mmd` cũ mô tả một schema khác:

- Có trong SQL tham chiếu, không có trên database: `visitor_sessions`, `user_behaviors`, `recommendation_models`, `recommendation_results`, `conversations`, `messages`.
- Có trên database, không có trong SQL tham chiếu: `banners`, `admin_invitations`, `admin_audit_logs`, `password_reset_tokens`, `personal_access_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`, `users.admin_permissions`, `books.cover_public_id`, `books.cover_asset_id`.
- `users.phone` chỉ có trong SQL tham chiếu. `user_addresses.phone` thì có trên database.
- SQL tham chiếu dùng `timestamptz`, `jsonb` và nhiều `CHECK` (giá, tồn kho, rating, số lượng, tổng tiền, status). Database dùng `timestamp without time zone`, `json`, và chỉ một CHECK: `users.role IN ('customer', 'admin', 'super_admin')`. `users.status` không có CHECK.
- SQL tham chiếu có unique một phần “một địa chỉ mặc định” và unique email không phân biệt hoa thường. Database không có hai ràng buộc đó. `user_addresses.user_id` chỉ có index thường.
- `books.slug` trên database là `varchar(255)`, không phải `varchar(300)`. `users.name` là `varchar(255)`.

UNIQUE cho phép nhiều NULL: `books.isbn`, `payments.invoice_number`, và `orders (user_id, idempotency_key)` khi `idempotency_key` NULL.
