import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/utils/api'

export const useDashboardStore = defineStore('dashboard', () => {
  // State
  const stats = ref(null)
  const metrics = ref(null)
  const activity = ref([])
  const systemInfo = ref(null)
  const loading = ref({
    stats: false,
    metrics: false,
    activity: false,
    systemInfo: false,
  })
  const errors = ref({
    stats: null,
    metrics: null,
    activity: null,
    systemInfo: null,
  })
  const lastUpdated = ref(null)

  // Getters
  const cpuUsage = computed(() => metrics.value?.cpu ?? null)
  const memoryUsage = computed(() => metrics.value?.memory ?? null)
  const diskUsage = computed(() => metrics.value?.disk ?? [])

  const cpuPercentage = computed(() => cpuUsage.value?.percentage ?? 0)
  const memoryPercentage = computed(() => memoryUsage.value?.percentage ?? 0)

  const cpuHistory = computed(() => cpuUsage.value?.history ?? [])
  const memoryHistory = computed(() => memoryUsage.value?.history ?? [])

  const isLoading = computed(() =>
    loading.value.stats ||
    loading.value.metrics ||
    loading.value.activity ||
    loading.value.systemInfo
  )

  // Actions
  async function fetchStats() {
    loading.value.stats = true
    errors.value.stats = null
    try {
      const response = await api.get('/dashboard/stats')
      stats.value = response.data.data
      return stats.value
    } catch (error) {
      errors.value.stats = error.response?.data?.message || error.message
      throw error
    } finally {
      loading.value.stats = false
    }
  }

  async function fetchMetrics() {
    loading.value.metrics = true
    errors.value.metrics = null
    try {
      const response = await api.get('/dashboard/metrics')
      metrics.value = response.data.data
      lastUpdated.value = new Date()
      return metrics.value
    } catch (error) {
      errors.value.metrics = error.response?.data?.message || error.message
      throw error
    } finally {
      loading.value.metrics = false
    }
  }

  async function fetchActivity() {
    loading.value.activity = true
    errors.value.activity = null
    try {
      const response = await api.get('/dashboard/activity')
      activity.value = response.data.data
      return activity.value
    } catch (error) {
      errors.value.activity = error.response?.data?.message || error.message
      throw error
    } finally {
      loading.value.activity = false
    }
  }

  async function fetchSystemInfo() {
    loading.value.systemInfo = true
    errors.value.systemInfo = null
    try {
      const response = await api.get('/dashboard/system-info')
      systemInfo.value = response.data.data
      return systemInfo.value
    } catch (error) {
      errors.value.systemInfo = error.response?.data?.message || error.message
      throw error
    } finally {
      loading.value.systemInfo = false
    }
  }

  async function fetchRealtime() {
    try {
      const response = await api.get('/dashboard/realtime')
      const data = response.data.data

      // Update metrics with realtime data
      if (metrics.value) {
        if (metrics.value.cpu) {
          metrics.value.cpu.percentage = data.cpu_percentage
          metrics.value.cpu.load_1min = data.cpu_load
        }
        if (metrics.value.memory) {
          metrics.value.memory.percentage = data.memory_percentage
          metrics.value.memory.used = data.memory_used
        }
      }

      lastUpdated.value = new Date(data.timestamp)
      return data
    } catch (error) {
      console.error('Failed to fetch realtime metrics:', error)
      throw error
    }
  }

  async function fetchAll() {
    return Promise.all([
      fetchStats(),
      fetchMetrics(),
      fetchActivity(),
      fetchSystemInfo(),
    ])
  }

  function reset() {
    stats.value = null
    metrics.value = null
    activity.value = []
    systemInfo.value = null
    lastUpdated.value = null
    Object.keys(errors.value).forEach(key => {
      errors.value[key] = null
    })
  }

  return {
    // State
    stats,
    metrics,
    activity,
    systemInfo,
    loading,
    errors,
    lastUpdated,

    // Getters
    cpuUsage,
    memoryUsage,
    diskUsage,
    cpuPercentage,
    memoryPercentage,
    cpuHistory,
    memoryHistory,
    isLoading,

    // Actions
    fetchStats,
    fetchMetrics,
    fetchActivity,
    fetchSystemInfo,
    fetchRealtime,
    fetchAll,
    reset,
  }
})
