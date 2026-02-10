<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('appManager.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('appManager.description') }}</p>
      </div>
      <div class="flex gap-2">
        <div class="relative">
          <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
          <input v-model="searchQuery" type="text" :placeholder="$t('common.search')"
            class="pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white w-56" />
        </div>
        <VButton variant="secondary" @click="scanSystem" :loading="scanning">
          <ArrowPathIcon class="w-4 h-4 mr-1" /> {{ $t('appManager.scan') }}
        </VButton>
      </div>
    </div>

    <!-- Category Filter -->
    <div class="flex flex-wrap gap-2 mb-6">
      <button v-for="cat in categoryFilters" :key="cat.value" @click="filterCategory = cat.value"
        :class="['px-3 py-1.5 text-xs rounded-full border transition-colors',
          filterCategory === cat.value
            ? 'bg-primary-100 border-primary-500 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
            : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gray-400']">
        {{ cat.label }}
      </button>
    </div>

    <!-- Apps by Category -->
    <div v-for="group in filteredGroups" :key="group.category" class="mb-8">
      <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 uppercase tracking-wider">
        {{ group.label }}
      </h2>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <VCard v-for="app in group.apps" :key="app.slug" class="p-4">
          <div class="flex items-start justify-between">
            <div class="flex items-start gap-3 flex-1 min-w-0">
              <div :class="['w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 text-white text-sm font-bold', appIconBg(app)]">
                {{ app.name.substring(0, 2).toUpperCase() }}
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <router-link v-if="app.type === 'multi_version'"
                    :to="{ name: 'app-manager-detail', params: { slug: app.slug } }"
                    class="text-sm font-semibold text-primary-600 dark:text-primary-400 hover:underline truncate">
                    {{ app.name }}
                  </router-link>
                  <p v-else class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ app.name }}</p>
                  <span :class="['px-1.5 py-0.5 text-[10px] font-medium rounded', statusClass(app.status)]">
                    {{ $t('appManager.statuses.' + app.status) }}
                  </span>
                  <span v-if="app.is_critical"
                    class="px-1.5 py-0.5 text-[10px] font-medium rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                    :title="$t('appManager.criticalService')">
                    {{ $t('appManager.critical') }}
                  </span>
                </div>
                <p class="text-xs text-gray-400 mt-0.5">{{ app.slug }}</p>
                <!-- Version info for multi-version -->
                <div v-if="app.type === 'multi_version' && app.installed_versions?.length" class="mt-1.5 flex flex-wrap gap-1">
                  <router-link v-for="v in app.installed_versions" :key="v"
                    :to="{ name: 'app-manager-detail', params: { slug: app.slug } }"
                    :class="['px-1.5 py-0.5 text-[10px] rounded border cursor-pointer',
                      v === app.active_version
                        ? 'bg-primary-100 border-primary-500 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                        : 'border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-gray-400']">
                    {{ v }}{{ v === app.active_version ? ' *' : '' }}
                  </router-link>
                </div>
                <!-- Single version info -->
                <p v-else-if="app.installed_version" class="text-xs text-gray-500 mt-0.5">
                  v{{ app.installed_version }}
                </p>
                <!-- Running indicator (only for apps with a service, not runtimes) -->
                <div v-if="app.status === 'installed' && app.service_name" class="flex items-center gap-1.5 mt-1.5">
                  <div :class="['w-2 h-2 rounded-full', app.is_running ? 'bg-green-500' : 'bg-red-400']"></div>
                  <span :class="['text-xs', app.is_running ? 'text-green-600 dark:text-green-400' : 'text-red-500']">
                    {{ app.is_running ? $t('common.running') : $t('common.stopped') }}
                  </span>
                  <span v-if="app.is_enabled" class="text-xs text-gray-400 ml-1">({{ $t('appManager.autoStart') }})</span>
                </div>
                <div v-else-if="app.status === 'installed' && !app.service_name" class="flex items-center gap-1.5 mt-1.5">
                  <div class="w-2 h-2 rounded-full bg-green-500"></div>
                  <span class="text-xs text-green-600 dark:text-green-400">{{ $t('appManager.statuses.installed') }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100 dark:border-gray-700">
            <div class="flex items-center gap-1">
              <!-- Multi-version: link to detail page instead of start/stop -->
              <template v-if="app.type === 'multi_version'">
                <router-link :to="{ name: 'app-manager-detail', params: { slug: app.slug } }"
                  class="px-3 py-1.5 text-xs font-medium text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-lg flex items-center gap-1">
                  <Square3Stack3DIcon class="w-4 h-4" />
                  {{ $t('appManager.detail.manage') }}
                </router-link>
              </template>
              <!-- Single-version: start/stop/restart -->
              <template v-else-if="app.status === 'installed'">
                <button v-if="!app.is_running" @click="appAction(app, 'start')"
                  :disabled="actionLoading === app.slug"
                  class="p-1.5 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded" :title="$t('appManager.start')">
                  <PlayIcon class="w-4 h-4" />
                </button>
                <button v-if="app.is_running && !app.is_critical" @click="appAction(app, 'stop')"
                  :disabled="actionLoading === app.slug"
                  class="p-1.5 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded" :title="$t('appManager.stop')">
                  <StopIcon class="w-4 h-4" />
                </button>
                <button @click="appAction(app, 'restart')"
                  :disabled="actionLoading === app.slug"
                  class="p-1.5 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded" :title="$t('appManager.restart')">
                  <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': actionLoading === app.slug }" />
                </button>
              </template>
            </div>
            <div class="flex items-center gap-1">
              <button v-if="app.status === 'installed'" @click="openConfigEditor(app)"
                class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded" :title="$t('appManager.config')">
                <Cog6ToothIcon class="w-4 h-4" />
              </button>
              <button v-if="app.status === 'installed'" @click="openLogs(app)"
                class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded" :title="$t('appManager.logs')">
                <DocumentTextIcon class="w-4 h-4" />
              </button>
              <button v-if="app.status === 'not_installed'" @click="installApp(app)"
                class="px-2.5 py-1 text-xs font-medium text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded"
                :disabled="actionLoading === app.slug">
                {{ $t('appManager.install') }}
              </button>
              <button v-if="app.status === 'installed' && !app.is_critical"
                @click="confirmUninstall(app)"
                class="p-1.5 text-gray-400 hover:text-red-500 rounded" :title="$t('appManager.uninstall')">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </VCard>
      </div>
    </div>

    <div v-if="filteredGroups.length === 0" class="text-center py-12 text-gray-400">
      {{ $t('appManager.noApps') }}
    </div>

    <!-- Version Manager Modal -->
    <VModal v-model="showVersionModal" :title="$t('appManager.manageVersions') + ' - ' + (selectedApp?.name || '')" size="lg">
      <div v-if="selectedApp" class="space-y-4">
        <!-- Installed Versions -->
        <div>
          <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('appManager.installedVersions') }}</h3>
          <div class="space-y-2">
            <div v-for="v in selectedApp.installed_versions || []" :key="v"
              class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
              <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedApp.name }} {{ v }}</span>
                <span v-if="v === selectedApp.active_version"
                  class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                  {{ $t('appManager.default') }}
                </span>
              </div>
              <div class="flex items-center gap-2">
                <VButton v-if="v !== selectedApp.active_version" size="sm" variant="secondary"
                  @click="setDefault(selectedApp, v)" :loading="versionLoading === v">
                  {{ $t('appManager.setDefault') }}
                </VButton>
                <button @click="uninstallVersion(selectedApp, v)"
                  :disabled="versionLoading === v"
                  class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
            <div v-if="!selectedApp.installed_versions?.length" class="text-sm text-gray-400 text-center py-4">
              {{ $t('appManager.noVersions') }}
            </div>
          </div>
        </div>

        <!-- Available Versions -->
        <div v-if="availableVersions.length">
          <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('appManager.availableVersions') }}</h3>
          <div class="flex flex-wrap gap-2">
            <button v-for="v in availableVersions" :key="v"
              @click="installVersion(selectedApp, v)"
              :disabled="versionLoading === v"
              class="px-3 py-1.5 text-sm border border-dashed border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 rounded-lg hover:border-primary-500 hover:text-primary-600 transition-colors">
              {{ v }}
              <span class="text-xs ml-1">{{ $t('appManager.install') }}</span>
            </button>
          </div>
        </div>

        <!-- Extensions (PHP) -->
        <div v-if="selectedApp.slug === 'php' && extensions.length">
          <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('appManager.extensions') }}</h3>
          <div class="flex flex-wrap gap-1.5">
            <span v-for="ext in extensions" :key="ext"
              class="px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded">
              {{ ext }}
            </span>
          </div>
        </div>
      </div>
      <template #footer>
        <VButton variant="secondary" @click="showVersionModal = false">{{ $t('common.close') }}</VButton>
      </template>
    </VModal>

    <!-- Config Editor Modal -->
    <VModal v-model="showConfigModal" :title="$t('appManager.editConfig') + ' - ' + (selectedApp?.name || '')" size="xl">
      <div v-if="selectedApp" class="space-y-4">
        <div class="flex gap-2 mb-3">
          <button v-for="key in configKeys" :key="key" @click="loadConfigFile(selectedApp, key)"
            :class="['px-3 py-1.5 text-xs rounded-lg border transition-colors',
              activeConfigKey === key
                ? 'bg-primary-100 border-primary-500 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400']">
            {{ key }}
          </button>
        </div>
        <div v-if="configLoading" class="text-center py-8 text-gray-400">{{ $t('common.loading') }}</div>
        <textarea v-else v-model="configContent"
          class="w-full h-[400px] font-mono text-xs border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100"
          spellcheck="false"></textarea>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <VButton variant="secondary" @click="showConfigModal = false">{{ $t('common.cancel') }}</VButton>
          <VButton @click="saveConfig" :loading="configSaving">{{ $t('common.save') }}</VButton>
        </div>
      </template>
    </VModal>

    <!-- Logs Modal -->
    <VModal v-model="showLogsModal" :title="$t('appManager.serviceLogs') + ' - ' + (selectedApp?.name || '')" size="xl">
      <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs font-mono overflow-auto max-h-[500px] whitespace-pre-wrap">{{ logsContent || $t('appManager.noLogs') }}</pre>
      <template #footer>
        <div class="flex justify-between w-full">
          <VButton variant="secondary" size="sm" @click="loadLogs(selectedApp)">
            <ArrowPathIcon class="w-4 h-4 mr-1" /> {{ $t('common.refresh') }}
          </VButton>
          <VButton variant="secondary" @click="showLogsModal = false">{{ $t('common.close') }}</VButton>
        </div>
      </template>
    </VModal>

    <!-- Confirm Dialog -->
    <VConfirmDialog v-model="showConfirm" :title="$t('appManager.uninstall')" :message="confirmMessage" @confirm="doUninstall" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VModal from '@/components/ui/VModal.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import {
  ArrowPathIcon,
  PlayIcon,
  StopIcon,
  Cog6ToothIcon,
  DocumentTextIcon,
  TrashIcon,
  MagnifyingGlassIcon,
  Square3Stack3DIcon,
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

const appGroups = ref([])
const searchQuery = ref('')
const filterCategory = ref('')
const scanning = ref(false)
const actionLoading = ref(null)

// Version manager
const showVersionModal = ref(false)
const selectedApp = ref(null)
const availableVersions = ref([])
const extensions = ref([])
const versionLoading = ref(null)

// Config editor
const showConfigModal = ref(false)
const configKeys = ref([])
const activeConfigKey = ref('')
const configContent = ref('')
const configLoading = ref(false)
const configSaving = ref(false)

// Logs
const showLogsModal = ref(false)
const logsContent = ref('')

// Confirm
const showConfirm = ref(false)
const confirmMessage = ref('')
let uninstallTarget = null

const categoryFilters = computed(() => {
  return [
    { value: '', label: t('appManager.allCategories') },
    ...appGroups.value.map(g => ({ value: g.key, label: g.label })),
  ]
})

const filteredGroups = computed(() => {
  const q = searchQuery.value.toLowerCase()
  const groups = []
  for (const group of appGroups.value) {
    if (filterCategory.value && group.key !== filterCategory.value) continue
    let filtered = group.apps
    if (q) {
      filtered = group.apps.filter(a => a.name.toLowerCase().includes(q) || a.slug.toLowerCase().includes(q))
    }
    if (filtered.length > 0) {
      groups.push({ category: group.key, label: group.label, apps: filtered })
    }
  }
  return groups
})

function appIconBg(app) {
  const colors = {
    webserver: 'bg-blue-600',
    runtime: 'bg-purple-600',
    database: 'bg-yellow-600',
    cache: 'bg-red-600',
    mail: 'bg-green-600',
    dns: 'bg-indigo-600',
    security: 'bg-orange-600',
    panel: 'bg-gray-600',
  }
  return colors[app.category] || 'bg-gray-600'
}

function statusClass(status) {
  return {
    installed: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    not_installed: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
    installing: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    uninstalling: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    error: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  }[status] || 'bg-gray-100 text-gray-500'
}

async function loadApps() {
  try {
    const { data } = await api.get('/app-manager')
    if (data.success) appGroups.value = data.data
  } catch (e) { /* interceptor */ }
}

async function scanSystem() {
  scanning.value = true
  try {
    const { data } = await api.post('/app-manager/scan')
    if (data.success) appGroups.value = data.data
    appStore.showToast({ type: 'success', message: data.message || t('appManager.scanComplete') })
  } catch (e) { /* interceptor */ }
  scanning.value = false
}

async function appAction(app, action) {
  actionLoading.value = app.slug
  try {
    const { data } = await api.post(`/app-manager/${app.slug}/${action}`)
    if (data.success) {
      appStore.showToast({ type: 'success', message: data.message || `${action} successful` })
    } else {
      appStore.showToast({ type: 'error', message: data.message || `${action} failed` })
    }
    await loadApps()
  } catch (e) { /* interceptor */ }
  actionLoading.value = null
}

async function installApp(app, version = null) {
  actionLoading.value = app.slug
  try {
    const { data } = await api.post(`/app-manager/${app.slug}/install`, { version })
    if (data.success) {
      appStore.showToast({ type: 'success', message: data.message || t('appManager.installSuccess') })
    } else {
      appStore.showToast({ type: 'error', message: data.message || t('appManager.installFailed') })
    }
    await loadApps()
  } catch (e) { /* interceptor */ }
  actionLoading.value = null
}

function confirmUninstall(app) {
  uninstallTarget = { app, version: null }
  confirmMessage.value = t('appManager.confirmUninstall', { name: app.name })
  showConfirm.value = true
}

async function doUninstall() {
  if (!uninstallTarget) return
  const { app, version } = uninstallTarget
  actionLoading.value = app.slug
  try {
    const { data } = await api.post(`/app-manager/${app.slug}/uninstall`, { version })
    if (data.success) {
      appStore.showToast({ type: 'success', message: data.message || t('appManager.uninstallSuccess') })
    }
    await loadApps()
    if (showVersionModal.value) {
      await refreshAppDetail(app.slug)
    }
  } catch (e) { /* interceptor */ }
  actionLoading.value = null
  uninstallTarget = null
}

async function openVersionManager(app) {
  selectedApp.value = app
  extensions.value = []
  showVersionModal.value = true
  await refreshAppDetail(app.slug)
}

async function refreshAppDetail(slug) {
  try {
    const { data } = await api.get(`/app-manager/${slug}`)
    if (data.success) {
      selectedApp.value = data.data.app
      availableVersions.value = (data.data.available_versions || []).filter(
        v => !(selectedApp.value.installed_versions || []).includes(v)
      )
      if (slug === 'php' && selectedApp.value.active_version) {
        loadExtensions(slug, selectedApp.value.active_version)
      }
    }
  } catch (e) { /* interceptor */ }
}

async function loadExtensions(slug, version) {
  try {
    const { data } = await api.get(`/app-manager/${slug}/extensions`, { params: { version } })
    if (data.success) extensions.value = data.data
  } catch (e) { /* interceptor */ }
}

async function installVersion(app, version) {
  versionLoading.value = version
  await installApp(app, version)
  await refreshAppDetail(app.slug)
  versionLoading.value = null
}

async function uninstallVersion(app, version) {
  uninstallTarget = { app, version }
  confirmMessage.value = t('appManager.confirmUninstall', { name: `${app.name} ${version}` })
  showConfirm.value = true
}

async function setDefault(app, version) {
  versionLoading.value = version
  try {
    const { data } = await api.post(`/app-manager/${app.slug}/set-default`, { version })
    if (data.success) {
      appStore.showToast({ type: 'success', message: data.message })
    }
    await refreshAppDetail(app.slug)
    await loadApps()
  } catch (e) { /* interceptor */ }
  versionLoading.value = null
}

async function openConfigEditor(app) {
  selectedApp.value = app
  configContent.value = ''
  configKeys.value = []
  activeConfigKey.value = ''
  showConfigModal.value = true

  try {
    const { data } = await api.get(`/app-manager/${app.slug}`)
    if (data.success) {
      configKeys.value = data.data.config_files || []
      if (configKeys.value.length > 0) {
        await loadConfigFile(app, configKeys.value[0])
      }
    }
  } catch (e) { /* interceptor */ }
}

async function loadConfigFile(app, key) {
  activeConfigKey.value = key
  configLoading.value = true
  try {
    const { data } = await api.get(`/app-manager/${app.slug}/config/${key}`)
    if (data.success) configContent.value = data.data.content
  } catch (e) { /* interceptor */ }
  configLoading.value = false
}

async function saveConfig() {
  configSaving.value = true
  try {
    const { data } = await api.put(`/app-manager/${selectedApp.value.slug}/config/${activeConfigKey.value}`, {
      content: configContent.value,
    })
    if (data.success) {
      appStore.showToast({ type: 'success', message: data.message || t('common.success') })
    } else {
      appStore.showToast({ type: 'error', message: data.message || t('common.error') })
    }
  } catch (e) { /* interceptor */ }
  configSaving.value = false
}

async function openLogs(app) {
  selectedApp.value = app
  logsContent.value = ''
  showLogsModal.value = true
  await loadLogs(app)
}

async function loadLogs(app) {
  try {
    const { data } = await api.get(`/app-manager/${app.slug}/logs`, { params: { lines: 100 } })
    if (data.success) logsContent.value = data.data.logs
  } catch (e) { /* interceptor */ }
}

onMounted(() => {
  loadApps()
})
</script>
