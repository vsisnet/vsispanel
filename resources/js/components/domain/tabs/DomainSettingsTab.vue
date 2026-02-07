<template>
  <div class="space-y-6">
    <!-- PHP Settings -->
    <VCard>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        {{ $t('domainDetail.phpSettings') }}
      </h3>

      <div class="space-y-4">
        <!-- PHP Version -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('php.version') }}
          </label>
          <div class="flex items-center space-x-4">
            <select
              v-model="phpVersion"
              class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            >
              <option v-for="version in phpVersions" :key="version" :value="version">
                PHP {{ version }}
              </option>
            </select>
            <VButton
              variant="primary"
              :loading="changingVersion"
              :disabled="phpVersion === domain.php_version"
              @click="changePhpVersion"
            >
              {{ $t('common.save') }}
            </VButton>
          </div>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $t('php.versionChangeWarning') }}
          </p>
        </div>

        <!-- PHP Settings Form -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('php.memoryLimit') }}
            </label>
            <div class="relative">
              <input
                v-model="phpSettings.memory_limit"
                type="text"
                placeholder="256M"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              />
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('php.memoryLimitHint') }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('php.uploadMaxFilesize') }}
            </label>
            <div class="relative">
              <input
                v-model="phpSettings.upload_max_filesize"
                type="text"
                placeholder="128M"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              />
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('php.uploadMaxFilesizeHint') }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('php.postMaxSize') }}
            </label>
            <div class="relative">
              <input
                v-model="phpSettings.post_max_size"
                type="text"
                placeholder="128M"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              />
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('php.postMaxSizeHint') }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('php.maxExecutionTime') }}
            </label>
            <div class="relative">
              <input
                v-model="phpSettings.max_execution_time"
                type="number"
                min="0"
                placeholder="300"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              />
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('php.maxExecutionTimeHint') }}</p>
          </div>

          <div class="flex items-center">
            <label class="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                v-model="phpSettings.display_errors"
                class="sr-only peer"
              >
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
              <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ $t('php.displayErrors') }}
              </span>
            </label>
          </div>
        </div>

        <div class="flex justify-end pt-4">
          <VButton variant="primary" :loading="savingPhpSettings" @click="savePhpSettings">
            {{ $t('php.saveSettings') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Nginx Configuration (Read Only) -->
    <VCard>
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ $t('domainDetail.nginxConfig') }}
        </h3>
        <VButton variant="secondary" size="sm" :icon="ArrowPathIcon" :loading="loadingNginx" @click="fetchNginxConfig">
          {{ $t('common.refresh') }}
        </VButton>
      </div>

      <VLoadingSkeleton v-if="loadingNginx" class="h-48" />
      <pre
        v-else
        class="p-4 bg-gray-900 rounded-lg overflow-auto text-sm font-mono text-gray-300 max-h-96"
      >{{ nginxConfig || $t('domainDetail.noNginxConfig') }}</pre>
    </VCard>

    <!-- Danger Zone -->
    <VCard class="border-red-200 dark:border-red-800">
      <h3 class="text-lg font-semibold text-red-600 dark:text-red-400 mb-4">
        {{ $t('domainDetail.dangerZone') }}
      </h3>
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-medium text-gray-900 dark:text-white">
              {{ $t('domainDetail.deleteDomain') }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('domainDetail.deleteWarning') }}
            </p>
          </div>
          <VButton variant="danger" @click="confirmDelete">
            {{ $t('common.delete') }}
          </VButton>
        </div>
      </div>
    </VCard>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import { useDomainsStore } from '@/stores/domains'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  domain: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['refresh'])

const router = useRouter()
const { t } = useI18n()
const appStore = useAppStore()
const domainsStore = useDomainsStore()

// State
const phpVersion = ref(props.domain.php_version)
const phpVersions = ref([])
const changingVersion = ref(false)
const phpSettings = ref({
  memory_limit: '256M',
  upload_max_filesize: '32M',
  post_max_size: '32M',
  max_execution_time: '60',
  display_errors: false
})
const savingPhpSettings = ref(false)
const nginxConfig = ref('')
const loadingNginx = ref(false)

// Methods
async function fetchPhpVersions() {
  try {
    const response = await api.get('/php/versions')
    const versions = response.data.data?.versions || []
    // Only show versions that have FPM installed and running
    phpVersions.value = versions
      .filter(v => v.installed)
      .map(v => v.version)
  } catch (err) {
    // Fallback to current version if API fails
    phpVersions.value = [props.domain.php_version]
    console.error('Failed to fetch PHP versions:', err)
  }
}

async function changePhpVersion() {
  changingVersion.value = true
  try {
    await api.put(`/domains/${props.domain.id}/php-version`, {
      php_version: phpVersion.value
    })
    appStore.showToast({
      type: 'success',
      message: t('php.versionChanged')
    })
    emit('refresh')
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('php.versionChangeError')
    })
  } finally {
    changingVersion.value = false
  }
}

async function fetchPhpSettings() {
  try {
    const response = await api.get(`/domains/${props.domain.id}/php-settings`)
    if (response.data.data) {
      const responseData = response.data.data
      // Get settings from response (API returns { domain, php_version, settings, defaults })
      const settings = responseData.settings || {}

      // Convert 'on'/'off' string to boolean for checkbox
      if (settings.display_errors !== undefined) {
        settings.display_errors = settings.display_errors === 'on' || settings.display_errors === '1' || settings.display_errors === true
      }

      // Merge with defaults if settings are empty
      const defaults = responseData.defaults || {}
      phpSettings.value = { ...phpSettings.value, ...defaults, ...settings }
    }
  } catch (err) {
    console.error('Failed to fetch PHP settings:', err)
  }
}

async function savePhpSettings() {
  savingPhpSettings.value = true
  try {
    // Convert boolean display_errors to string 'on'/'off' for backend
    const settings = {
      ...phpSettings.value,
      display_errors: phpSettings.value.display_errors ? 'on' : 'off'
    }
    await api.put(`/domains/${props.domain.id}/php-settings`, settings)
    appStore.showToast({
      type: 'success',
      message: t('php.settingsSaved')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('php.settingsError')
    })
  } finally {
    savingPhpSettings.value = false
  }
}

async function fetchNginxConfig() {
  loadingNginx.value = true
  try {
    const response = await api.get(`/domains/${props.domain.id}/nginx-config`)
    nginxConfig.value = response.data.data?.config || ''
  } catch (err) {
    console.error('Failed to fetch Nginx config:', err)
    nginxConfig.value = ''
  } finally {
    loadingNginx.value = false
  }
}

async function confirmDelete() {
  if (!confirm(t('websites.deleteConfirmMessage', { name: props.domain.name }))) {
    return
  }
  try {
    await domainsStore.deleteDomain(props.domain.id)
    appStore.showToast({
      type: 'success',
      message: t('websites.deleteSuccess')
    })
    router.push({ name: 'websites' })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('websites.deleteError')
    })
  }
}

onMounted(() => {
  fetchPhpVersions()
  fetchPhpSettings()
  fetchNginxConfig()
})
</script>
