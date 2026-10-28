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
    return new ApiError(error.response.status, body)
  }

  return new ApiError(0, { message: 'Không thể kết nối máy chủ.' })
}

export function firstFieldError(errors: Record<string, string[]>, field: string): string | undefined {
  return errors[field]?.[0]
}
