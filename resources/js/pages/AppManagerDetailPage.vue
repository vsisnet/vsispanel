<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <router-link :to="{ name: 'app-manager' }" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
          <ArrowLeftIcon class="w-5 h-5" />
        </router-link>
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
            <div :class="['w-10 h-10 rounded-lg flex items-center justify-center text-white text-sm font-bold', iconBg]">
              {{ app?.name?.substring(0, 2).toUpperCase() || '--' }}
            </div>
            {{ app?.name || '...' }}
          </h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $t('appManager.detail.manageVersions', { name: app?.name || '' }) }}
          </p>
        </div>
      </div>
      <div class="flex gap-2">
        <VButton variant="secondary" size="sm" @click="loadDetail" :loading="loading">
          <ArrowPathIcon class="w-4 h-4 mr-1" /> {{ $t('common.refresh') }}
        </VButton>
      </div>
    </div>

    <!-- Active Version Info -->
    <VCard v-if="app" class="mb-6 p-4">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('appManager.detail.activeVersion') }}</p>
          <p class="text-xl font-bold text-gray-900 dark:text-white">
            {{ app.name }} {{ app.active_version || '--' }}
          </p>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-center">
            <p class="text-xs text-gray-400">{{ $t('appManager.detail.installed') }}</p>
            <p class="text-lg font-semibold text-primary-600">{{ (app.installed_versions || []).length }}</p>
          </div>
          <div class="text-center">
            <p class="text-xs text-gray-400">{{ $t('appManager.detail.available') }}</p>
            <p class="text-lg font-semibold text-gray-500">{{ availableVersions.length }}</p>
          </div>
        </div>
      </div>
    </VCard>

    <!-- Version List -->
    <div class="space-y-3">
      <VCard v-for="ver in versions" :key="ver.version" class="p-4">
        <div class="flex items-center justify-between">
          <!-- Left: Version info -->
          <div class="flex items-center gap-4 flex-1 min-w-0">
            <div class="flex items-center gap-3">
              <div :class="['w-3 h-3 rounded-full flex-shrink-0',
                !ver.installed ? 'bg-gray-300 dark:bg-gray-600' :
                !ver.service_name ? 'bg-green-500' :
                ver.is_running ? 'bg-green-500' : 'bg-red-400']">
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <p class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ app?.name }} {{ ver.version }}
                  </p>
                  <span v-if="ver.is_default"
                    class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                    {{ $t('appManager.default') }}
                  </span>
                  <span :class="['px-1.5 py-0.5 text-[10px] font-medium rounded',
                    ver.installed
                      ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                      : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400']">
                    {{ ver.installed ? $t('appManager.statuses.installed') : $t('appManager.statuses.not_installed') }}
                  </span>
                </div>
                <p v-if="ver.installed && ver.service_name" class="text-xs text-gray-400 mt-0.5">
                  {{ ver.service_name }}
                  <span v-if="ver.is_running" class="text-green-500 ml-1">{{ $t('common.running') }}</span>
                  <span v-else class="text-red-400 ml-1">{{ $t('common.stopped') }}</span>
                  <span v-if="ver.is_enabled" class="text-gray-400 ml-1">({{ $t('appManager.autoStart') }})</span>
                </p>
                <p v-else-if="ver.installed" class="text-xs text-green-500 mt-0.5">{{ $t('appManager.statuses.installed') }}</p>
              </div>
            </div>
          </div>

          <!-- Right: Actions -->
          <div class="flex items-center gap-1 ml-4">
            <template v-if="ver.installed">
              <!-- Start -->
              <button v-if="ver.service_name && !ver.is_running" @click="versionAction(ver, 'start')"
                :disabled="actionLoading === ver.version"
                class="p-2 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded-lg transition-colors"
                :title="$t('appManager.start')">
                <PlayIcon class="w-4 h-4" />
              </button>
              <!-- Stop (hidden for active/default version) -->
              <button v-if="ver.service_name && ver.is_running && !ver.is_default" @click="versionAction(ver, 'stop')"
                :disabled="actionLoading === ver.version"
                class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                :title="$t('appManager.stop')">
                <StopIcon class="w-4 h-4" />
              </button>
              <!-- Restart -->
              <button v-if="ver.service_name" @click="versionAction(ver, 'restart')"
                :disabled="actionLoading === ver.version"
                class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors"
                :title="$t('appManager.restart')">
                <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': actionLoading === ver.version }" />
              </button>
              <!-- Enable/Disable (disable not allowed for active/default version) -->
              <button v-if="ver.service_name && (!ver.is_default || !ver.is_enabled)"
                @click="versionAction(ver, ver.is_enabled ? 'disable' : 'enable')"
                :disabled="actionLoading === ver.version"
                class="p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                :title="ver.is_enabled ? $t('appManager.detail.disable') : $t('appManager.detail.enable')">
                <BoltIcon v-if="ver.is_enabled" class="w-4 h-4 text-yellow-500" />
                <BoltSlashIcon v-else class="w-4 h-4" />
              </button>

              <div v-if="ver.service_name" class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

              <!-- Set Default -->
              <button v-if="!ver.is_default" @click="setDefault(ver)"
                :disabled="actionLoading === ver.version"
                class="px-2 py-1 text-xs text-purple-600 hover:bg-purple-50 dark:hover:bg-purple-900/20 rounded-lg transition-colors"
                :title="$t('appManager.setDefault')">
                <StarIcon class="w-4 h-4" />
              </button>
              <!-- Extensions (PHP only) -->
              <button v-if="slug === 'php'" @click="loadExtensions(ver.version)"
                class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg transition-colors"
                :title="$t('appManager.extensions')">
                <PuzzlePieceIcon class="w-4 h-4" />
              </button>
              <!-- Config -->
              <button v-if="ver.config_files?.length" @click="openConfig(ver)"
                class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg transition-colors"
                :title="$t('appManager.config')">
                <Cog6ToothIcon class="w-4 h-4" />
              </button>
              <!-- Logs -->
              <button v-if="ver.service_name" @click="openLogs(ver)"
                class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg transition-colors"
                :title="$t('appManager.logs')">
                <DocumentTextIcon class="w-4 h-4" />
              </button>

              <div class="w-px h-6 bg-gray-200 dark:bg-gray-700 mx-1"></div>

              <!-- Uninstall (blocked for active/default version) -->
              <button v-if="!ver.is_default" @click="confirmUninstall(ver)"
                :disabled="actionLoading === ver.version"
                class="p-2 text-gray-400 hover:text-red-500 rounded-lg transition-colors"
                :title="$t('appManager.uninstall')">
                <TrashIcon class="w-4 h-4" />
              </button>
              <span v-else class="p-2 text-gray-300 dark:text-gray-600 cursor-not-allowed"
                :title="$t('appManager.cannotUninstallActive')">
                <TrashIcon class="w-4 h-4" />
              </span>
            </template>

            <!-- Install button for not-installed versions -->
            <template v-else>
              <VButton size="sm" @click="installVersion(ver)"
                :loading="actionLoading === ver.version">
                {{ $t('appManager.install') }}
              </VButton>
            </template>
          </div>
        </div>
      </VCard>
    </div>

    <div v-if="!versions.length && !loading" class="text-center py-12 text-gray-400">
      {{ $t('appManager.noVersions') }}
    </div>

    <!-- Task Progress Modal -->
    <VModal v-model="showTaskModal" :title="taskTitle" size="lg">
      <div class="space-y-4">
        <!-- Progress bar -->
        <div>
          <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('appManager.detail.progress') }}</span>
            <span class="text-sm text-gray-500">{{ taskProgress }}%</span>
          </div>
          <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
            <div :class="['h-2.5 rounded-full transition-all duration-500',
              taskStatus === 'failed' ? 'bg-red-500' :
              taskStatus === 'completed' ? 'bg-green-500' : 'bg-primary-600']"
              :style="{ width: taskProgress + '%' }">
            </div>
          </div>
        </div>

        <!-- Status -->
        <div class="flex items-center gap-2">
          <div v-if="taskStatus === 'running' || taskStatus === 'pending'" class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></div>
          <div v-else-if="taskStatus === 'completed'" class="w-2 h-2 bg-green-500 rounded-full"></div>
          <div v-else-if="taskStatus === 'failed'" class="w-2 h-2 bg-red-500 rounded-full"></div>
          <span :class="['text-sm font-medium',
            taskStatus === 'failed' ? 'text-red-500' :
            taskStatus === 'completed' ? 'text-green-500' : 'text-blue-500']">
            {{ taskStatus === 'running' ? $t('appManager.detail.installing') :
               taskStatus === 'pending' ? $t('appManager.detail.queued') :
               taskStatus === 'completed' ? $t('appManager.detail.completed') :
               $t('appManager.detail.failed') }}
          </span>
        </div>

        <!-- Output log -->
        <div ref="taskOutputRef"
          class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs font-mono overflow-auto max-h-[350px] whitespace-pre-wrap min-h-[200px]">{{ taskOutput || $t('appManager.detail.waitingForOutput') }}</div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <VButton variant="secondary" @click="closeTaskModal">
            {{ $t('common.close') }}
          </VButton>
        </div>
      </template>
    </VModal>

    <!-- Extensions Modal -->
    <VModal v-model="showExtModal" :title="$t('appManager.extensions') + ` - ${app?.name || ''} ${extVersion}`" size="xl">
      <div class="space-y-4">
        <!-- Search & Filter -->
        <div class="flex items-center gap-3">
          <div class="relative flex-1">
            <MagnifyingGlassIcon class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input v-model="extSearch" type="text" :placeholder="$t('appManager.detail.searchExtensions')"
              class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-primary-500 focus:border-primary-500" />
          </div>
          <div class="flex gap-1">
            <button @click="extFilter = 'all'"
              :class="['px-3 py-2 text-xs rounded-lg border transition-colors', extFilter === 'all' ? 'bg-primary-100 border-primary-500 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'border-gray-300 dark:border-gray-600 text-gray-500']">
              {{ $t('appManager.detail.extAll') }}
            </button>
            <button @click="extFilter = 'installed'"
              :class="['px-3 py-2 text-xs rounded-lg border transition-colors', extFilter === 'installed' ? 'bg-green-100 border-green-500 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'border-gray-300 dark:border-gray-600 text-gray-500']">
              {{ $t('appManager.detail.extInstalled') }}
            </button>
            <button @click="extFilter = 'available'"
              :class="['px-3 py-2 text-xs rounded-lg border transition-colors', extFilter === 'available' ? 'bg-blue-100 border-blue-500 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' : 'border-gray-300 dark:border-gray-600 text-gray-500']">
              {{ $t('appManager.detail.extAvailable') }}
            </button>
          </div>
        </div>

        <!-- Stats -->
        <div v-if="!extLoading" class="flex gap-4 text-xs text-gray-500 dark:text-gray-400">
          <span>{{ $t('appManager.detail.extTotal') }}: {{ allExtensions.length }}</span>
          <span class="text-green-500">{{ $t('appManager.detail.extInstalled') }}: {{ allExtensions.filter(e => e.installed).length }}</span>
          <span v-if="extSelected.length > 0" class="text-primary-500">{{ $t('appManager.detail.extSelected') }}: {{ extSelected.length }}</span>
        </div>

        <!-- Loading -->
        <div v-if="extLoading" class="text-center py-12 text-gray-400">
          <ArrowPathIcon class="w-6 h-6 animate-spin mx-auto mb-2" />
          {{ $t('common.loading') }}
        </div>

        <!-- Extension List -->
        <div v-else-if="filteredExtensions.length" class="max-h-[400px] overflow-y-auto space-y-1">
          <div v-for="ext in filteredExtensions" :key="ext.package"
            :class="['flex items-center justify-between px-3 py-2 rounded-lg transition-colors',
              extSelected.includes(ext.package) ? 'bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800' : 'hover:bg-gray-50 dark:hover:bg-gray-800/50']">
            <div class="flex items-center gap-3 min-w-0 flex-1">
              <input type="checkbox" :value="ext.package" v-model="extSelected"
                :disabled="ext.installed && extSelectedAction === 'install' || !ext.installed && extSelectedAction === 'uninstall'"
                class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500" />
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <span class="text-sm font-medium text-gray-900 dark:text-white">{{ ext.name }}</span>
                  <span v-if="ext.installed"
                    class="px-1.5 py-0.5 text-[10px] rounded bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                    {{ $t('appManager.statuses.installed') }}
                  </span>
                </div>
                <p v-if="ext.description" class="text-xs text-gray-400 truncate">{{ ext.description }}</p>
              </div>
            </div>
            <div class="flex items-center gap-1 ml-2 flex-shrink-0">
              <button v-if="!ext.installed" @click="quickInstallExt(ext)"
                :disabled="extActionLoading"
                class="px-2 py-1 text-xs text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded transition-colors"
                :title="$t('appManager.install')">
                {{ $t('appManager.install') }}
              </button>
              <button v-else @click="quickUninstallExt(ext)"
                :disabled="extActionLoading"
                class="px-2 py-1 text-xs text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
                :title="$t('appManager.uninstall')">
                {{ $t('appManager.uninstall') }}
              </button>
            </div>
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-400">{{ $t('appManager.detail.noExtensions') }}</div>
      </div>
      <template #footer>
        <div class="flex items-center justify-between w-full">
          <div class="flex gap-2">
            <VButton v-if="extSelectedInstallable.length > 0" size="sm" @click="batchInstallExt"
              :disabled="extActionLoading">
              {{ $t('appManager.detail.extInstallSelected') }} ({{ extSelectedInstallable.length }})
            </VButton>
            <VButton v-if="extSelectedUninstallable.length > 0" variant="danger" size="sm" @click="batchUninstallExt"
              :disabled="extActionLoading">
              {{ $t('appManager.detail.extUninstallSelected') }} ({{ extSelectedUninstallable.length }})
            </VButton>
          </div>
          <VButton variant="secondary" @click="showExtModal = false">{{ $t('common.close') }}</VButton>
        </div>
      </template>
    </VModal>

    <!-- Config Editor Modal -->
    <VModal v-model="showConfigModal" :title="$t('appManager.editConfig') + ` - ${app?.name || ''} ${configVersion}`" size="xl">
      <div class="space-y-4">
        <div class="flex gap-2 mb-3">
          <button v-for="key in configKeys" :key="key" @click="loadConfigFile(key)"
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
    <VModal v-model="showLogsModal" :title="$t('appManager.serviceLogs') + ` - ${app?.name || ''} ${logsVersion}`" size="xl">
      <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs font-mono overflow-auto max-h-[500px] whitespace-pre-wrap">{{ logsContent || $t('appManager.noLogs') }}</pre>
      <template #footer>
        <div class="flex justify-between w-full">
          <VButton variant="secondary" size="sm" @click="loadLogsForVersion(logsVersion)">
            <ArrowPathIcon class="w-4 h-4 mr-1" /> {{ $t('common.refresh') }}
          </VButton>
          <VButton variant="secondary" @click="showLogsModal = false">{{ $t('common.close') }}</VButton>
        </div>
      </template>
    </VModal>

    <!-- Confirm Dialog -->
    <VConfirmDialog v-model="showConfirm" :title="$t('appManager.uninstall')" :message="confirmMsg" @confirm="doUninstall" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VModal from '@/components/ui/VModal.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import {
  ArrowLeftIcon,
  ArrowPathIcon,
  PlayIcon,
  StopIcon,
  Cog6ToothIcon,
  DocumentTextIcon,
  TrashIcon,
  StarIcon,
  PuzzlePieceIcon,
  BoltIcon,
  BoltSlashIcon,
  MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const { t } = useI18n()
const appStore = useAppStore()

const slug = computed(() => route.params.slug)
const loading = ref(false)
const app = ref(null)
const versions = ref([])
const availableVersions = ref([])
const actionLoading = ref(null)

// Task progress
const showTaskModal = ref(false)
const taskTitle = ref('')
const taskId = ref(null)
const taskStatus = ref('pending')
const taskProgress = ref(0)
const taskOutput = ref('')
const taskOutputRef = ref(null)
let taskPollTimer = null
let taskOutputOffset = 0
let taskPollErrors = 0

// Extensions
const showExtModal = ref(false)
const extVersion = ref('')
const allExtensions = ref([])
const extLoading = ref(false)
const extSearch = ref('')
const extFilter = ref('all')
const extSelected = ref([])
const extSelectedAction = ref(null)
const extActionLoading = ref(false)

const filteredExtensions = computed(() => {
  let list = allExtensions.value
  if (extFilter.value === 'installed') list = list.filter(e => e.installed)
  else if (extFilter.value === 'available') list = list.filter(e => !e.installed)
  if (extSearch.value) {
    const q = extSearch.value.toLowerCase()
    list = list.filter(e => e.name.toLowerCase().includes(q) || (e.description || '').toLowerCase().includes(q))
  }
  return list
})

const extSelectedInstallable = computed(() => {
  return extSelected.value.filter(pkg => {
    const ext = allExtensions.value.find(e => e.package === pkg)
    return ext && !ext.installed
  })
})

const extSelectedUninstallable = computed(() => {
  return extSelected.value.filter(pkg => {
    const ext = allExtensions.value.find(e => e.package === pkg)
    return ext && ext.installed
  })
})

// Config
const showConfigModal = ref(false)
const configVersion = ref('')
const configKeys = ref([])
const activeConfigKey = ref('')
const configContent = ref('')
const configLoading = ref(false)
const configSaving = ref(false)

// Logs
const showLogsModal = ref(false)
const logsVersion = ref('')
const logsContent = ref('')

// Confirm
const showConfirm = ref(false)
const confirmMsg = ref('')
let uninstallVersion = null

const iconBg = computed(() => {
  const c = app.value?.category
  return {
    runtime: 'bg-purple-600',
    webserver: 'bg-blue-600',
    database: 'bg-yellow-600',
  }[c] || 'bg-gray-600'
})

async function loadDetail() {
  loading.value = true
  try {
    const { data } = await api.get(`/app-manager/${slug.value}`)
    if (data.success) {
      app.value = data.data.app
      versions.value = data.data.versions || []
      availableVersions.value = data.data.available_versions || []
    }
  } catch (e) { /* interceptor */ }
  loading.value = false
}

async function versionAction(ver, action) {
  actionLoading.value = ver.version
  try {
    const { data } = await api.post(`/app-manager/${slug.value}/${action}`, { version: ver.version })
    if (data.success) {
      appStore.showToast({ type: 'success', message: data.message })
    } else {
      appStore.showToast({ type: 'error', message: data.message })
    }
    await loadDetail()
  } catch (e) { /* interceptor */ }
  actionLoading.value = null
}

async function installVersion(ver) {
  actionLoading.value = ver.version
  try {
    const { data } = await api.post(`/app-manager/${slug.value}/install`, { version: ver.version })
    if (data.success && data.data?.task_id) {
      // Open task progress modal
      startTaskPolling(data.data.task_id, `${t('appManager.install')} ${app.value?.name || ''} ${ver.version}`)
    } else if (!data.success) {
      appStore.showToast({ type: 'error', message: data.message })
    }
  } catch (e) { /* interceptor */ }
  actionLoading.value = null
}

async function setDefault(ver) {
  actionLoading.value = ver.version
  try {
    const { data } = await api.post(`/app-manager/${slug.value}/set-default`, { version: ver.version })
    if (data.success) {
      appStore.showToast({ type: 'success', message: data.message })
    }
    await loadDetail()
  } catch (e) { /* interceptor */ }
  actionLoading.value = null
}

function confirmUninstall(ver) {
  uninstallVersion = ver.version
  confirmMsg.value = t('appManager.confirmUninstall', { name: `${app.value?.name || ''} ${ver.version}` })
  showConfirm.value = true
}

async function doUninstall() {
  if (!uninstallVersion) return
  const ver = uninstallVersion
  uninstallVersion = null
  try {
    const { data } = await api.post(`/app-manager/${slug.value}/uninstall`, { version: ver })
    if (data.success && data.data?.task_id) {
      startTaskPolling(data.data.task_id, `${t('appManager.uninstall')} ${app.value?.name || ''} ${ver}`)
    } else if (!data.success) {
      appStore.showToast({ type: 'error', message: data.message })
    }
  } catch (e) { /* interceptor */ }
}

// ──────────────────────────────────────────────
// Task progress polling
// ──────────────────────────────────────────────
function startTaskPolling(id, title) {
  taskId.value = id
  taskTitle.value = title
  taskStatus.value = 'pending'
  taskProgress.value = 0
  taskOutput.value = ''
  taskOutputOffset = 0
  taskPollErrors = 0
  showTaskModal.value = true

  // Start polling with small delay to ensure task is committed
  setTimeout(() => {
    pollTaskOutput()
    taskPollTimer = setInterval(pollTaskOutput, 2000)
  }, 500)
}

async function pollTaskOutput() {
  if (!taskId.value) return
  try {
    const { data } = await api.get(`/tasks/${taskId.value}/output`, {
      params: { offset: taskOutputOffset }
    })
    if (data.success && data.data) {
      taskPollErrors = 0

      // Always update offset from response
      if (data.data.output && data.data.output.length > 0) {
        taskOutput.value += data.data.output

        // Auto-scroll to bottom
        await nextTick()
        if (taskOutputRef.value) {
          taskOutputRef.value.scrollTop = taskOutputRef.value.scrollHeight
        }
      }
      taskOutputOffset = data.data.offset ?? taskOutputOffset

      taskProgress.value = data.data.progress ?? 0
      taskStatus.value = data.data.status ?? taskStatus.value

      // Stop polling if task is done
      if (['completed', 'failed', 'cancelled'].includes(data.data.status)) {
        stopTaskPolling()
        // Refresh detail after task completes
        await loadDetail()
      }
    }
  } catch (e) {
    taskPollErrors++
    // Only stop polling after multiple consecutive failures
    if (taskPollErrors >= 10) {
      stopTaskPolling()
    }
  }
}

function stopTaskPolling() {
  if (taskPollTimer) {
    clearInterval(taskPollTimer)
    taskPollTimer = null
  }
}

function closeTaskModal() {
  showTaskModal.value = false
  stopTaskPolling()
  taskId.value = null
}

// Extensions
async function loadExtensions(version) {
  extVersion.value = version
  allExtensions.value = []
  extLoading.value = true
  extSearch.value = ''
  extFilter.value = 'all'
  extSelected.value = []
  extSelectedAction.value = null
  showExtModal.value = true
  try {
    const { data } = await api.get(`/app-manager/${slug.value}/available-extensions`, { params: { version } })
    if (data.success) allExtensions.value = data.data
  } catch (e) { /* interceptor */ }
  extLoading.value = false
}

async function quickInstallExt(ext) {
  await manageExtensions('install', [ext.package])
}

async function quickUninstallExt(ext) {
  await manageExtensions('uninstall', [ext.package])
}

async function batchInstallExt() {
  await manageExtensions('install', extSelectedInstallable.value)
}

async function batchUninstallExt() {
  await manageExtensions('uninstall', extSelectedUninstallable.value)
}

async function manageExtensions(action, packages) {
  if (!packages.length) return
  extActionLoading.value = true
  try {
    const { data } = await api.post(`/app-manager/${slug.value}/extensions/${action}`, {
      version: extVersion.value,
      packages,
    })
    if (data.success && data.data?.task_id) {
      showExtModal.value = false
      const extNames = packages.map(p => p.replace(`php${extVersion.value}-`, ''))
      const title = `${action === 'install' ? t('appManager.install') : t('appManager.uninstall')} PHP ${extVersion.value} extensions: ${extNames.join(', ')}`
      startTaskPolling(data.data.task_id, title)
    } else if (!data.success) {
      appStore.showToast({ type: 'error', message: data.message })
    }
  } catch (e) { /* interceptor */ }
  extActionLoading.value = false
  extSelected.value = []
}

function openConfig(ver) {
  configVersion.value = ver.version
  configKeys.value = ver.config_files || []
  configContent.value = ''
  activeConfigKey.value = ''
  showConfigModal.value = true
  if (configKeys.value.length > 0) {
    loadConfigFile(configKeys.value[0])
  }
}

async function loadConfigFile(key) {
  activeConfigKey.value = key
  configLoading.value = true
  try {
    const { data } = await api.get(`/app-manager/${slug.value}/config/${key}`, { params: { version: configVersion.value } })
    if (data.success) configContent.value = data.data.content
  } catch (e) { /* interceptor */ }
  configLoading.value = false
}

async function saveConfig() {
  configSaving.value = true
  try {
    const { data } = await api.put(`/app-manager/${slug.value}/config/${activeConfigKey.value}`, {
      content: configContent.value,
      version: configVersion.value,
    })
    if (data.success) {
      appStore.showToast({ type: 'success', message: data.message })
    } else {
      appStore.showToast({ type: 'error', message: data.message })
    }
  } catch (e) { /* interceptor */ }
  configSaving.value = false
}

function openLogs(ver) {
  logsVersion.value = ver.version
  logsContent.value = ''
  showLogsModal.value = true
  loadLogsForVersion(ver.version)
}

async function loadLogsForVersion(version) {
  try {
    const { data } = await api.get(`/app-manager/${slug.value}/logs`, { params: { lines: 100, version } })
    if (data.success) logsContent.value = data.data.logs
  } catch (e) { /* interceptor */ }
}

onMounted(() => {
  loadDetail()
})

onBeforeUnmount(() => {
  stopTaskPolling()
})
</script>
