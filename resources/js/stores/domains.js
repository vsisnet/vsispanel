import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/utils/api'

export const useDomainsStore = defineStore('domains', () => {
  // State
  const domains = ref([])
  const currentDomain = ref(null)
  const loading = ref(false)
  const pagination = ref({
    currentPage: 1,
    lastPage: 1,
    perPage: 15,
    total: 0
  })
  const filters = ref({
    status: null,
    search: '',
    php_version: null
  })

  // Getters
  const activeDomains = computed(() =>
    domains.value.filter(d => d.status === 'active')
  )

  const suspendedDomains = computed(() =>
    domains.value.filter(d => d.status === 'suspended')
  )

  const isEmpty = computed(() => domains.value.length === 0)

  // Actions
  async function fetchDomains(params = {}) {
    loading.value = true
    try {
      const response = await api.get('/domains', {
        params: {
          page: pagination.value.currentPage,
          per_page: pagination.value.perPage,
          status: filters.value.status,
          search: filters.value.search,
          php_version: filters.value.php_version,
          ...params
        }
      })

      domains.value = response.data.data
      pagination.value = {
        currentPage: response.data.meta.current_page,
        lastPage: response.data.meta.last_page,
        perPage: response.data.meta.per_page,
        total: response.data.meta.total
      }

      return response.data
    } catch (error) {
      console.error('Failed to fetch domains:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  async function fetchDomain(id) {
    loading.value = true
    try {
      const response = await api.get(`/domains/${id}`)
      currentDomain.value = response.data.data
      return response.data.data
    } catch (error) {
      console.error('Failed to fetch domain:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  async function createDomain(data) {
    loading.value = true
    try {
      const response = await api.post('/domains', data)
      domains.value.unshift(response.data.data)
      return response.data.data
    } catch (error) {
      console.error('Failed to create domain:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  async function updateDomain(id, data) {
    loading.value = true
    try {
      const response = await api.put(`/domains/${id}`, data)
      const index = domains.value.findIndex(d => d.id === id)
      if (index !== -1) {
        domains.value[index] = response.data.data
      }
      if (currentDomain.value?.id === id) {
        currentDomain.value = response.data.data
      }
      return response.data.data
    } catch (error) {
      console.error('Failed to update domain:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  async function deleteDomain(id) {
    loading.value = true
    try {
      await api.delete(`/domains/${id}`)
      domains.value = domains.value.filter(d => d.id !== id)
      if (currentDomain.value?.id === id) {
        currentDomain.value = null
      }
    } catch (error) {
      console.error('Failed to delete domain:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  async function suspendDomain(id, reason = '') {
    loading.value = true
    try {
      const response = await api.post(`/domains/${id}/suspend`, { reason })
      const index = domains.value.findIndex(d => d.id === id)
      if (index !== -1) {
        domains.value[index] = response.data.data
      }
      if (currentDomain.value?.id === id) {
        currentDomain.value = response.data.data
      }
      return response.data.data
    } catch (error) {
      console.error('Failed to suspend domain:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  async function unsuspendDomain(id) {
    loading.value = true
    try {
      const response = await api.post(`/domains/${id}/unsuspend`)
      const index = domains.value.findIndex(d => d.id === id)
      if (index !== -1) {
        domains.value[index] = response.data.data
      }
      if (currentDomain.value?.id === id) {
        currentDomain.value = response.data.data
      }
      return response.data.data
    } catch (error) {
      console.error('Failed to unsuspend domain:', error)
      throw error
    } finally {
      loading.value = false
    }
  }

  async function getDiskUsage(id) {
    try {
      const response = await api.get(`/domains/${id}/disk-usage`)
      return response.data.data
    } catch (error) {
      console.error('Failed to get disk usage:', error)
      throw error
    }
  }

  // Subdomain actions
  async function fetchSubdomains(domainId) {
    try {
      const response = await api.get(`/domains/${domainId}/subdomains`)
      return response.data.data
    } catch (error) {
      console.error('Failed to fetch subdomains:', error)
      throw error
    }
  }

  async function createSubdomain(domainId, data) {
    try {
      const response = await api.post(`/domains/${domainId}/subdomains`, data)
      return response.data.data
    } catch (error) {
      console.error('Failed to create subdomain:', error)
      throw error
    }
  }

  async function deleteSubdomain(domainId, subdomainId) {
    try {
      await api.delete(`/domains/${domainId}/subdomains/${subdomainId}`)
    } catch (error) {
      console.error('Failed to delete subdomain:', error)
      throw error
    }
  }

  function setFilter(key, value) {
    filters.value[key] = value
    pagination.value.currentPage = 1
  }

  function setPage(page) {
    pagination.value.currentPage = page
  }

  function reset() {
    domains.value = []
    currentDomain.value = null
    loading.value = false
    pagination.value = {
      currentPage: 1,
      lastPage: 1,
      perPage: 15,
      total: 0
    }
    filters.value = {
      status: null,
      search: '',
      php_version: null
    }
  }

  return {
    // State
    domains,
    currentDomain,
    loading,
    pagination,
    filters,
    // Getters
    activeDomains,
    suspendedDomains,
    isEmpty,
    // Actions
    fetchDomains,
    fetchDomain,
    createDomain,
    updateDomain,
    deleteDomain,
    suspendDomain,
    unsuspendDomain,
    getDiskUsage,
    fetchSubdomains,
    createSubdomain,
    deleteSubdomain,
    setFilter,
    setPage,
    reset
  }
})
