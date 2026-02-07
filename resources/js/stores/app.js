import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import { setLocale, getLocale } from '@/i18n'

export const useAppStore = defineStore('app', () => {
  // State
  const sidebarCollapsed = ref(localStorage.getItem('vsispanel_sidebar_collapsed') === 'true')
  const darkMode = ref(localStorage.getItem('vsispanel_dark_mode') === 'true')
  const locale = ref(getLocale())
  const toasts = ref([])
  const notifications = ref([])
  const isLoadingGlobal = ref(false)

  // Toast counter for unique IDs
  let toastId = 0

  // Getters
  const notificationCount = computed(() => notifications.value.filter(n => !n.read).length)

  // Watch dark mode changes
  watch(darkMode, (value) => {
    localStorage.setItem('vsispanel_dark_mode', value.toString())
    updateDarkModeClass(value)
  }, { immediate: true })

  // Watch sidebar collapsed changes
  watch(sidebarCollapsed, (value) => {
    localStorage.setItem('vsispanel_sidebar_collapsed', value.toString())
  })

  // Actions
  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function setSidebarCollapsed(value) {
    sidebarCollapsed.value = value
  }

  function toggleDarkMode() {
    darkMode.value = !darkMode.value
  }

  function setDarkMode(value) {
    darkMode.value = value
  }

  function updateDarkModeClass(isDark) {
    if (isDark) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
  }

  function changeLocale(newLocale) {
    locale.value = newLocale
    setLocale(newLocale)
  }

  function showToast({ type = 'info', message, duration = 5000 }) {
    const id = ++toastId

    toasts.value.push({
      id,
      type,
      message,
      createdAt: Date.now()
    })

    if (duration > 0) {
      setTimeout(() => {
        removeToast(id)
      }, duration)
    }

    return id
  }

  function removeToast(id) {
    const index = toasts.value.findIndex(t => t.id === id)
    if (index !== -1) {
      toasts.value.splice(index, 1)
    }
  }

  function clearToasts() {
    toasts.value = []
  }

  function addNotification(notification) {
    notifications.value.unshift({
      id: Date.now(),
      read: false,
      createdAt: new Date().toISOString(),
      ...notification
    })
  }

  function markNotificationAsRead(id) {
    const notification = notifications.value.find(n => n.id === id)
    if (notification) {
      notification.read = true
    }
  }

  function markAllNotificationsAsRead() {
    notifications.value.forEach(n => {
      n.read = true
    })
  }

  function clearNotifications() {
    notifications.value = []
  }

  function setGlobalLoading(value) {
    isLoadingGlobal.value = value
  }

  return {
    // State
    sidebarCollapsed,
    darkMode,
    locale,
    toasts,
    notifications,
    isLoadingGlobal,

    // Getters
    notificationCount,

    // Actions
    toggleSidebar,
    setSidebarCollapsed,
    toggleDarkMode,
    setDarkMode,
    changeLocale,
    showToast,
    removeToast,
    clearToasts,
    addNotification,
    markNotificationAsRead,
    markAllNotificationsAsRead,
    clearNotifications,
    setGlobalLoading
  }
})
