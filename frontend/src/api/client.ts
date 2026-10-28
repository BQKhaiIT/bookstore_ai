import axios from 'axios'
import { parseApiError } from '@/api/errors'
import { notifyUnauthorized } from '@/api/sessionEvents'

const baseURL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000'

export const api = axios.create({
  baseURL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
})

let csrfReady = false

export async function ensureCsrfCookie(): Promise<void> {
  if (csrfReady) {
    return
  }

  await api.get('/sanctum/csrf-cookie')
  csrfReady = true
}

export function resetCsrfCookie(): void {
  csrfReady = false
}

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (axios.isAxiosError(error) && error.response?.status === 419) {
      resetCsrfCookie()
      await ensureCsrfCookie()

      if (error.config && !('_retriedCsrf' in error.config)) {
        Object.assign(error.config, { _retriedCsrf: true })
        return api.request(error.config)
      }
    }

    if (axios.isAxiosError(error) && error.response?.status === 401) {
      const url = error.config?.url ?? ''
      if (!url.includes('/api/v1/me')) {
        notifyUnauthorized()
      }
    }

    return Promise.reject(parseApiError(error))
  },
)
