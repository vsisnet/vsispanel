<template>
  <div class="space-y-6">
    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Document Root -->
      <VCard>
        <div class="flex items-center">
          <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-lg mr-4">
            <FolderIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('domainDetail.documentRoot') }}
            </p>
            <p class="text-sm font-mono text-gray-900 dark:text-white truncate max-w-[180px]" :title="domain.document_root">
              {{ domain.document_root || '-' }}
            </p>
          </div>
        </div>
      </VCard>

      <!-- PHP Version -->
      <VCard>
        <div class="flex items-center">
          <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-lg mr-4">
            <CodeBracketIcon class="w-6 h-6 text-purple-600 dark:text-purple-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('domainDetail.phpVersion') }}
            </p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">
              PHP {{ domain.php_version }}
            </p>
          </div>
        </div>
      </VCard>

      <!-- SSL Status -->
      <VCard>
        <div class="flex items-center">
          <div :class="[
            'p-3 rounded-lg mr-4',
            domain.ssl_enabled
              ? 'bg-green-100 dark:bg-green-900'
              : 'bg-gray-100 dark:bg-gray-700'
          ]">
            <LockClosedIcon v-if="domain.ssl_enabled" class="w-6 h-6 text-green-600 dark:text-green-400" />
            <LockOpenIcon v-else class="w-6 h-6 text-gray-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('domainDetail.sslStatus') }}
            </p>
            <p class="text-lg font-semibold" :class="domain.ssl_enabled ? 'text-green-600 dark:text-green-400' : 'text-gray-500'">
              {{ domain.ssl_enabled ? $t('domainDetail.sslActive') : $t('domainDetail.sslInactive') }}
            </p>
          </div>
        </div>
      </VCard>

      <!-- Disk Usage -->
      <VCard>
        <div class="flex items-center">
          <div class="p-3 bg-orange-100 dark:bg-orange-900 rounded-lg mr-4">
            <ChartPieIcon class="w-6 h-6 text-orange-600 dark:text-orange-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('domainDetail.diskUsage') }}
            </p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ domain.disk_used_formatted || '0 B' }}
            </p>
          </div>
        </div>
      </VCard>
    </div>

    <!-- Domain Info -->
    <VCard>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        {{ $t('domainDetail.domainInfo') }}
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-3">
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('domainDetail.domainName') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ domain.name }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('domainDetail.webServer') }}</span>
            <span class="font-medium text-gray-900 dark:text-white uppercase">{{ domain.web_server_type || 'nginx' }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('domainDetail.isMainDomain') }}</span>
            <VBadge :variant="domain.is_main ? 'success' : 'secondary'" size="sm">
              {{ domain.is_main ? $t('common.yes') : $t('common.no') }}
            </VBadge>
          </div>
        </div>
        <div class="space-y-3">
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('domainDetail.createdAt') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ formatDateTime(domain.created_at) }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('domainDetail.updatedAt') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ formatDateTime(domain.updated_at) }}</span>
          </div>
          <div v-if="domain.ssl_expires_at" class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('domainDetail.sslExpires') }}</span>
            <span class="font-medium" :class="sslExpiryClass">{{ formatDateTime(domain.ssl_expires_at) }}</span>
          </div>
        </div>
      </div>
    </VCard>

    <!-- Subdomains -->
    <VCard>
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ $t('domainDetail.subdomains') }}
        </h3>
        <VButton variant="secondary" size="sm" :icon="PlusIcon" @click="showSubdomainModal = true">
          {{ $t('domainDetail.addSubdomain') }}
        </VButton>
      </div>

      <template v-if="subdomains.length > 0">
        <div class="space-y-2">
          <div
            v-for="subdomain in subdomains"
            :key="subdomain.id"
            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
          >
            <div class="flex items-center">
              <GlobeAltIcon class="w-5 h-5 text-gray-400 mr-3" />
              <span class="font-medium text-gray-900 dark:text-white">
                {{ subdomain.name }}.{{ domain.name }}
              </span>
            </div>
            <div class="flex items-center space-x-2">
              <VBadge :variant="getStatusVariant(subdomain.status)" size="sm">
                {{ subdomain.status }}
              </VBadge>
              <VButton
                variant="ghost"
                size="sm"
                :icon="TrashIcon"
                class="text-red-500"
                @click="confirmDeleteSubdomain(subdomain)"
              />
            </div>
          </div>
        </div>
      </template>
      <template v-else>
        <p class="text-gray-500 dark:text-gray-400 text-center py-4">
          {{ $t('domainDetail.noSubdomains') }}
        </p>
      </template>
    </VCard>

    <!-- Quick Actions -->
    <VCard>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        {{ $t('domainDetail.quickActions') }}
      </h3>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <button
          @click="$emit('changeTab', 'files')"
          class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
        >
          <FolderIcon class="w-8 h-8 text-blue-500 mb-2" />
          <span class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $t('domainDetail.fileManager') }}
          </span>
        </button>
        <button
          @click="$emit('changeTab', 'databases')"
          class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
        >
          <CircleStackIcon class="w-8 h-8 text-purple-500 mb-2" />
          <span class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $t('domainDetail.databases') }}
          </span>
        </button>
        <button
          @click="$emit('changeTab', 'ssl')"
          class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
        >
          <LockClosedIcon class="w-8 h-8 text-green-500 mb-2" />
          <span class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $t('domainDetail.sslCertificate') }}
          </span>
        </button>
        <button
          @click="$emit('changeTab', 'settings')"
          class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
        >
          <Cog6ToothIcon class="w-8 h-8 text-gray-500 mb-2" />
          <span class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $t('domainDetail.settings') }}
          </span>
        </button>
      </div>
    </VCard>

    <!-- Create Subdomain Modal -->
    <CreateSubdomainModal
      v-model:show="showSubdomainModal"
      :domain="domain"
      @created="handleSubdomainCreated"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VBadge from '@/components/ui/VBadge.vue'
import CreateSubdomainModal from '@/components/domain/CreateSubdomainModal.vue'
import {
  FolderIcon,
  CodeBracketIcon,
  LockClosedIcon,
  LockOpenIcon,
  ChartPieIcon,
  GlobeAltIcon,
  CircleStackIcon,
  Cog6ToothIcon,
  PlusIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  domain: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['refresh', 'changeTab'])

const { t } = useI18n()
const appStore = useAppStore()

// State
const subdomains = ref([])
const loadingSubdomains = ref(false)
const showSubdomainModal = ref(false)

// Computed
const sslExpiryClass = computed(() => {
  if (!props.domain.ssl_expires_at) return 'text-gray-500'
  const days = Math.floor((new Date(props.domain.ssl_expires_at) - new Date()) / (1000 * 60 * 60 * 24))
  if (days <= 7) return 'text-red-600 dark:text-red-400'
  if (days <= 30) return 'text-orange-600 dark:text-orange-400'
  return 'text-green-600 dark:text-green-400'
})

// Methods
function formatDateTime(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString()
}

function getStatusVariant(status) {
  switch (status) {
    case 'active': return 'success'
    case 'suspended': return 'danger'
    case 'pending': return 'warning'
    default: return 'secondary'
  }
}

async function fetchSubdomains() {
  loadingSubdomains.value = true
  try {
    const response = await api.get(`/domains/${props.domain.id}/subdomains`)
    subdomains.value = response.data.data || []
  } catch (err) {
    console.error('Failed to fetch subdomains:', err)
  } finally {
    loadingSubdomains.value = false
  }
}

function handleSubdomainCreated() {
  fetchSubdomains()
  appStore.showToast({
    type: 'success',
    message: t('domainDetail.subdomainCreated')
  })
}

async function confirmDeleteSubdomain(subdomain) {
  if (!confirm(t('domainDetail.deleteSubdomainConfirm', { name: subdomain.name }))) {
    return
  }
  try {
    await api.delete(`/domains/${props.domain.id}/subdomains/${subdomain.id}`)
    fetchSubdomains()
    appStore.showToast({
      type: 'success',
      message: t('domainDetail.subdomainDeleted')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('domainDetail.subdomainDeleteError')
    })
  }
}

// Lifecycle
onMounted(() => {
  fetchSubdomains()
})
</script>
