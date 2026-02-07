import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { initCsrf } from '@/utils/api'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const token = ref(localStorage.getItem('vsispanel_token') || null)
  const isLoading = ref(false)
  const requires2FA = ref(false)
  const tempToken = ref(null)

  // Getters
  const isAuthenticated = computed(() => !!token.value && !!user.value)
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isReseller = computed(() => user.value?.role === 'reseller')
  const userName = computed(() => user.value?.name || '')
  const userEmail = computed(() => user.value?.email || '')
  const userRole = computed(() => user.value?.role || '')

  // Actions
  async function login(email, password) {
    isLoading.value = true
    requires2FA.value = false

    try {
      await initCsrf()
      const response = await api.post('/auth/login', { email, password })
      const data = response.data.data

      if (data.requires_2fa) {
        requires2FA.value = true
        tempToken.value = data.temp_token
        return { requires2FA: true }
      }

      setAuth(data.token, data.user)
      return { success: true }
    } catch (error) {
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function verify2FA(code) {
    isLoading.value = true

    try {
      const response = await api.post('/auth/login/2fa', {
        temp_token: tempToken.value,
        code: code
      })

      const data = response.data.data
      setAuth(data.token, data.user)
      requires2FA.value = false
      tempToken.value = null

      return { success: true }
    } catch (error) {
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    isLoading.value = true

    try {
      await api.post('/auth/logout')
    } catch (error) {
      // Ignore errors during logout
    } finally {
      clearAuth()
      isLoading.value = false
    }
  }

  async function fetchUser() {
    if (!token.value) return null

    isLoading.value = true

    try {
      const response = await api.get('/auth/me')
      user.value = response.data.data
      return user.value
    } catch (error) {
      clearAuth()
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function updateProfile(data) {
    isLoading.value = true

    try {
      const response = await api.put('/auth/profile', data)
      user.value = response.data.data
      return user.value
    } catch (error) {
      throw error
    } finally {
      isLoading.value = false
    }
  }

  async function updatePassword(currentPassword, newPassword, confirmPassword) {
    isLoading.value = true

    try {
      await api.put('/auth/password', {
        current_password: currentPassword,
        password: newPassword,
        password_confirmation: confirmPassword
      })
      return { success: true }
    } catch (error) {
      throw error
    } finally {
      isLoading.value = false
    }
  }

  function setAuth(newToken, newUser) {
    token.value = newToken
    user.value = newUser
    localStorage.setItem('vsispanel_token', newToken)
  }

  function clearAuth() {
    token.value = null
    user.value = null
    requires2FA.value = false
    tempToken.value = null
    localStorage.removeItem('vsispanel_token')
  }

  function hasPermission(permission) {
    if (!user.value) return false
    if (user.value.role === 'admin') return true
    return user.value.permissions?.includes(permission) || false
  }

  function hasRole(role) {
    return user.value?.role === role
  }

  return {
    // State
    user,
    token,
    isLoading,
    requires2FA,
    tempToken,

    // Getters
    isAuthenticated,
    isAdmin,
    isReseller,
    userName,
    userEmail,
    userRole,

    // Actions
    login,
    verify2FA,
    logout,
    fetchUser,
    updateProfile,
    updatePassword,
    setAuth,
    clearAuth,
    hasPermission,
    hasRole
  }
})
