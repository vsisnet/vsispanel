<template>
  <div>
    <!-- Loading State -->
    <VLoadingSkeleton v-if="loading" class="h-96" />

    <!-- Error State -->
    <VCard v-else-if="error" class="text-center py-12">
      <ExclamationCircleIcon class="w-16 h-16 mx-auto text-red-500 mb-4" />
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
        {{ $t('errors.notFound') }}
      </h2>
      <p class="text-gray-500 dark:text-gray-400 mb-4">
        {{ $t('domainDetail.domainNotFound') }}
      </p>
      <VButton variant="primary" @click="router.push({ name: 'websites' })">
        {{ $t('common.back') }}
      </VButton>
    </VCard>

    <!-- Domain Content -->
    <template v-else-if="domain">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div class="flex items-center space-x-4">
            <button
              @click="router.push({ name: 'websites' })"
              class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
            >
              <ArrowLeftIcon class="w-5 h-5" />
            </button>
            <div>
              <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ domain.name }}
                </h1>
                <VBadge :variant="getStatusVariant(domain.status)">
                  {{ $t(`websites.status${capitalize(domain.status)}`) }}
                </VBadge>
              </div>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $t('domainDetail.createdAt') }}: {{ formatDate(domain.created_at) }}
              </p>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <VButton
              variant="secondary"
              :icon="ArrowTopRightOnSquareIcon"
              @click="visitSite"
            >
              {{ $t('domainDetail.visitSite') }}
            </VButton>
            <VButton
              v-if="domain.status === 'active'"
              variant="warning"
              :icon="PauseIcon"
              :loading="suspending"
              @click="handleSuspend"
            >
              {{ $t('domainDetail.suspend') }}
            </VButton>
            <VButton
              v-else-if="domain.status === 'suspended'"
              variant="success"
              :icon="PlayIcon"
              :loading="unsuspending"
              @click="handleUnsuspend"
            >
              {{ $t('domainDetail.unsuspend') }}
            </VButton>
          </div>
        </div>
      </div>

      <!-- Tabs Navigation -->
      <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="-mb-px flex space-x-8 overflow-x-auto">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="switchTab(tab.id)"
            :class="[
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === tab.id
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'
            ]"
          >
            <component :is="tab.icon" class="w-5 h-5 inline-block mr-2" />
            {{ $t(`domainDetail.tabs.${tab.id}`) }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <KeepAlive>
        <component :is="activeTabComponent" :domain="domain" @refresh="fetchDomain" />
      </KeepAlive>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, markRaw } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import DomainOverviewTab from '@/components/domain/tabs/DomainOverviewTab.vue'
import DomainFilesTab from '@/components/domain/tabs/DomainFilesTab.vue'
import DomainDatabasesTab from '@/components/domain/tabs/DomainDatabasesTab.vue'
import DomainEmailTab from '@/components/domain/tabs/DomainEmailTab.vue'
import DomainSslTab from '@/components/domain/tabs/DomainSslTab.vue'
import DomainDnsTab from '@/components/domain/tabs/DomainDnsTab.vue'
import DomainLogsTab from '@/components/domain/tabs/DomainLogsTab.vue'
import DomainSettingsTab from '@/components/domain/tabs/DomainSettingsTab.vue'
import {
  ArrowLeftIcon,
  ArrowTopRightOnSquareIcon,
  PauseIcon,
  PlayIcon,
  ExclamationCircleIcon,
  HomeIcon,
  FolderIcon,
  CircleStackIcon,
  EnvelopeIcon,
  LockClosedIcon,
  GlobeAltIcon,
  DocumentTextIcon,
  Cog6ToothIcon
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const appStore = useAppStore()

// Valid tab ids
const validTabs = ['overview', 'files', 'databases', 'email', 'ssl', 'dns', 'logs', 'settings']

// Get initial tab from query param or default to 'overview'
function getInitialTab() {
  const tabFromQuery = route.query.tab
  if (tabFromQuery && validTabs.includes(tabFromQuery)) {
    return tabFromQuery
  }
  return 'overview'
}

// State
const domain = ref(null)
const loading = ref(true)
const error = ref(false)
const activeTab = ref(getInitialTab())
const suspending = ref(false)
const unsuspending = ref(false)

// Watch for route query changes to update active tab
watch(() => route.query.tab, (newTab) => {
  if (newTab && validTabs.includes(newTab)) {
    activeTab.value = newTab
  }
})

// Tabs configuration
const tabs = [
  { id: 'overview', icon: markRaw(HomeIcon) },
  { id: 'files', icon: markRaw(FolderIcon) },
  { id: 'databases', icon: markRaw(CircleStackIcon) },
  { id: 'email', icon: markRaw(EnvelopeIcon) },
  { id: 'ssl', icon: markRaw(LockClosedIcon) },
  { id: 'dns', icon: markRaw(GlobeAltIcon) },
  { id: 'logs', icon: markRaw(DocumentTextIcon) },
  { id: 'settings', icon: markRaw(Cog6ToothIcon) }
]

// Tab components mapping
const tabComponents = {
  overview: markRaw(DomainOverviewTab),
  files: markRaw(DomainFilesTab),
  databases: markRaw(DomainDatabasesTab),
  email: markRaw(DomainEmailTab),
  ssl: markRaw(DomainSslTab),
  dns: markRaw(DomainDnsTab),
  logs: markRaw(DomainLogsTab),
  settings: markRaw(DomainSettingsTab)
}

// Computed
const activeTabComponent = computed(() => tabComponents[activeTab.value])

// Methods
function capitalize(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''
}

function getStatusVariant(status) {
  switch (status) {
    case 'active': return 'success'
    case 'suspended': return 'danger'
    case 'pending': return 'warning'
    case 'disabled': return 'secondary'
    default: return 'secondary'
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString()
}

function switchTab(tabId) {
  activeTab.value = tabId
  // Update URL query parameter without navigation
  router.replace({
    query: { ...route.query, tab: tabId === 'overview' ? undefined : tabId }
  })
}

function visitSite() {
  const protocol = domain.value.ssl_enabled ? 'https' : 'http'
  window.open(`${protocol}://${domain.value.name}`, '_blank')
}

async function fetchDomain() {
  loading.value = true
  error.value = false
  try {
    const response = await api.get(`/domains/${route.params.id}`)
    domain.value = response.data.data
  } catch (err) {
    error.value = true
    console.error('Failed to fetch domain:', err)
  } finally {
    loading.value = false
  }
}

async function handleSuspend() {
  suspending.value = true
  try {
    await api.post(`/domains/${domain.value.id}/suspend`)
    domain.value.status = 'suspended'
    appStore.showToast({
      type: 'success',
      message: t('domainDetail.suspendSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('domainDetail.suspendError')
    })
  } finally {
    suspending.value = false
  }
}

async function handleUnsuspend() {
  unsuspending.value = true
  try {
    await api.post(`/domains/${domain.value.id}/unsuspend`)
    domain.value.status = 'active'
    appStore.showToast({
      type: 'success',
      message: t('domainDetail.unsuspendSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('domainDetail.unsuspendError')
    })
  } finally {
    unsuspending.value = false
  }
}

// Lifecycle
onMounted(() => {
  fetchDomain()
})
</script>
