import { ApiError } from '@/api/errors'

export function authFormErrorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    if (error.status === 429) {
      return 'Quá nhiều lần thử. Vui lòng đợi vài phút rồi thử lại.'
    }
    if (error.status === 419) {
      return 'Phiên làm việc hết hạn. Vui lòng tải lại trang và thử lại.'
    }
    return error.message
  }
  return fallback
}
