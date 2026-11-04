# Docker — Bookstore

Stack mặc định chạy bằng một lệnh, không cần terminal riêng cho PHP, Vite, PostgreSQL hay ngrok.

```powershell
docker compose up -d --build
docker compose ps
docker compose logs -f
docker compose down
```

`docker compose down` dừng container và giữ named volume. Không dùng `down -v`, `volume prune` hoặc `migrate:fresh`.

## Dịch vụ

| Service | Địa chỉ trên máy | Ghi chú |
| --- | --- | --- |
| frontend | http://127.0.0.1:5173 | Nginx phục vụ bản build Vue. SPA fallback. |
| backend | http://127.0.0.1:8000 | Nginx + PHP 8.5-FPM. Không dùng `artisan serve`. |
| postgres | 127.0.0.1:5433 | Volume `postgres_data`. Không mở ra LAN. |
| queue | nội bộ | `queue:work` cho mail đặt lại mật khẩu. |
| ngrok | domain cố định | Trỏ tới `backend:80` trong mạng Docker. |
| pgAdmin | profile `tools` | Không khởi động mặc định. |

PostgreSQL trên Windows giữ nguyên ở cổng 5432 và là bản dự phòng tại thời điểm backup. Ứng dụng trong Docker dùng database trên cổng 5433. File `backend/.env` không bị sửa; container chỉ ghi đè `DB_HOST`, `DB_PORT`, `APP_DEBUG=false`, `LOG_LEVEL` và `TRUSTED_PROXIES`.

## Điều kiện

- Windows 11, Docker Desktop với backend WSL2.
- Bật Windows Features: Virtual Machine Platform và Windows Subsystem for Linux, rồi khởi động lại máy. Docker Desktop không tự bật các feature này và không tự restart Windows.
- Sau reboot, mở Docker Desktop và đợi trạng thái Engine running. Có thể bỏ qua đăng nhập tài khoản Docker.
- Nếu `docker` không nhận lệnh, mở terminal mới hoặc dùng `C:\Program Files\Docker\Docker\resources\bin\docker.exe`.
- RAM trống nên còn khoảng 4 GB trước khi build.

## Bí mật

`scripts/docker/sync-secrets.ps1` tạo các file gitignore:

- `docker/secrets/db_password` — mật khẩu PostgreSQL hiện tại.
- `docker/secrets/postgres.env` — tên database và user, không có mật khẩu.
- `docker/secrets/ngrok.env` — `NGROK_AUTHTOKEN`.

Script dừng nếu `SEPAY_ENV` khác `sandbox` hoặc `SEPAY_PUBLIC_BASE_URL` khác `https://squishy-showcase-lapped.ngrok-free.dev`. Không đổi secret SePay, không đổi `APP_KEY`.

## Backup và restore

```powershell
powershell -File scripts/docker/backup-windows-postgres.ps1
docker compose up -d postgres
powershell -File scripts/docker/restore-to-docker.ps1 -DumpFile backups\postgres\<file>.dump
powershell -File scripts/docker/compare-databases.ps1
```

Restore chỉ được nối vào `127.0.0.1:5433`. Script từ chối ghi đè nếu bảng `users` đã có trên Docker. Không xóa database Windows.

## ngrok và SePay

Container ngrok dùng image `ngrok/ngrok`, region `ap`, domain `squishy-showcase-lapped.ngrok-free.dev`, và forward tới `backend:80`.

Đường hầm chỉ tới Laravel, giống `ngrok http 8000` hiện tại. IPN và URL trả về SePay vẫn là:

`https://squishy-showcase-lapped.ngrok-free.dev/api/v1/payments/webhooks/sepay`

`/payment/sepay/return/{order}` nằm trên Laravel rồi redirect về `FRONTEND_URL`. Vì vậy frontend không được đưa lên cùng domain này: request trả về của SePay sẽ không còn vào Laravel. Frontend khách vẫn là `http://localhost:5173`, API vẫn là `http://localhost:8000`.

Chỉ một agent ngrok được giữ domain. Trước khi Compose khởi động service `ngrok`, tắt ngrok đang chạy trên Windows.

Không đánh dấu đơn đã thanh toán từ redirect. `APP_DEBUG` trong container là `false`.

## Chế độ dev của frontend

Bản Nginx cổng 5173 là mặc định. HMR riêng, không chiếm 5173:

```powershell
docker compose --profile dev up frontend-dev
```

Vite lắng nghe http://127.0.0.1:5174.

pgAdmin tùy chọn, sau khi tạo `docker/secrets/pgadmin.env` từ file example:

```powershell
docker compose --profile tools up -d pgadmin
```

pgAdmin trên Windows cũng kết nối được `127.0.0.1:5433` với user/password hiện tại và database `bookstore_ai`.

## Rollback

1. `docker compose down` (không thêm `-v` nếu muốn giữ bản Docker).
2. Tắt process ngrok của Docker bằng cách xuống service; domain được nhả.
3. Chạy lại Laravel, Vite và `ngrok http 8000` trên Windows.
4. `backend/.env` vẫn trỏ PostgreSQL Windows cổng 5432.
5. File backup trong `backups/postgres/` được giữ.

Dữ liệu phát sinh trên Docker sau thời điểm backup không có trên PostgreSQL Windows.
