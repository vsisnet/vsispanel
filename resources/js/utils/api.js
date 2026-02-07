import axios from 'axios'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import router from '@/router'

const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true,
  withXSRFToken: true,
})

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const authStore = useAuthStore()

    // Attach token if available
    if (authStore.token) {
      config.headers.Authorization = `Bearer ${authStore.token}`
    }

    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor
api.interceptors.response.use(
  (response) => {
    return response
  },
  async (error) => {
    const authStore = useAuthStore()
    const appStore = useAppStore()

    if (!error.response) {
      // Network error
      appStore.showToast({
        type: 'error',
        message: 'Network error. Please check your connection.',
      })
      return Promise.reject(error)
    }

    const { status, data } = error.response

    switch (status) {
      case 401:
        // Unauthorized - clear auth and redirect to login
        authStore.clearAuth()
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
        appStore.showToast({
          type: 'error',
          message: 'Session expired. Please login again.',
        })
        break

      case 403:
        // Forbidden
        appStore.showToast({
          type: 'error',
          message: data?.error?.message || 'You do not have permission to perform this action.',
        })
        break

      case 404:
        // Not found
        appStore.showToast({
          type: 'error',
          message: data?.error?.message || 'Resource not found.',
        })
        break

      case 422:
        // Validation error - let the component handle it
        break

      case 429:
        // Too many requests
        appStore.showToast({
          type: 'warning',
          message: 'Too many requests. Please slow down.',
        })
        break

      case 500:
        // Server error
        appStore.showToast({
          type: 'error',
          message: 'An unexpected error occurred. Please try again later.',
        })
        break

      default:
        appStore.showToast({
          type: 'error',
          message: data?.error?.message || 'An error occurred.',
        })
    }

    return Promise.reject(error)
  }
)

// CSRF token handling
export async function initCsrf() {
  await axios.get('/sanctum/csrf-cookie')
}

export default api
