import axios from 'axios'
import type { ApiErrorBody } from '@/types/user'

export class ApiError extends Error {
  status: number
  code?: string
  errors: Record<string, string[]>

  constructor(status: number, body: ApiErrorBody) {
    super(body.message ?? 'Đã xảy ra lỗi.')
    this.status = status
    this.code = body.code
    this.errors = body.errors ?? {}
  }
}

export function parseApiError(error: unknown): ApiError {
  if (axios.isAxiosError(error) && error.response) {
    const body = (error.response.data ?? {}) as ApiErrorBody
    const message =
      typeof body.message === 'string' && body.message
        ? body.message
        : error.response.status >= 500
          ? 'Máy chủ gặp lỗi (thường do gửi email). Thử đăng nhập hoặc gửi lại email xác minh.'
          : 'Không thể kết nối máy chủ.'
    return new ApiError(error.response.status, { ...body, message })
  }

  return new ApiError(0, {
    message:
      'Không thể kết nối máy chủ. Kiểm tra Laravel đang chạy (php artisan serve) và VITE_API_BASE_URL.',
  })
}

export function firstFieldError(errors: Record<string, string[]>, field: string): string | undefined {
  return errors[field]?.[0]
}
