<template>
  <div class="space-y-6">
    <!-- Page Header -->
    <div>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('migration.title') }}</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('migration.description') }}</p>
    </div>

    <!-- Migration Wizard -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <!-- Steps indicator -->
      <div class="border-b border-gray-200 dark:border-gray-700 px-6 py-4">
        <nav class="flex space-x-4" aria-label="Steps">
          <button
            v-for="(stepInfo, idx) in steps"
            :key="idx"
            :class="[
              'flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors',
              step === idx
                ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300'
                : step > idx
                  ? 'text-green-600 dark:text-green-400'
                  : 'text-gray-500 dark:text-gray-400'
            ]"
            :disabled="idx > step"
            @click="idx < step && (step = idx)"
          >
            <span class="mr-2 flex h-6 w-6 items-center justify-center rounded-full text-xs"
              :class="step > idx ? 'bg-green-500 text-white' : step === idx ? 'bg-primary-600 text-white' : 'bg-gray-300 dark:bg-gray-600 text-gray-600 dark:text-gray-300'"
            >
              <svg v-if="step > idx" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
              <span v-else>{{ idx + 1 }}</span>
            </span>
            {{ $t(stepInfo.label) }}
          </button>
        </nav>
      </div>

      <div class="p-6">
        <!-- Step 0: Select Source -->
        <div v-if="step === 0">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('migration.selectSource') }}</h3>
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <button
              v-for="source in sourceTypes"
              :key="source.value"
              :class="[
                'flex flex-col items-center p-4 rounded-lg border-2 transition-all',
                form.source_type === source.value
                  ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                  : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
              ]"
              @click="form.source_type = source.value"
            >
              <div class="text-3xl mb-2">{{ source.icon }}</div>
              <span class="text-sm font-medium text-gray-900 dark:text-white">{{ source.label }}</span>
            </button>
          </div>
          <div class="mt-6 flex justify-end">
            <button @click="step = 1" :disabled="!form.source_type" class="btn-primary">
              {{ $t('common.next') }} →
            </button>
          </div>
        </div>

        <!-- Step 1: Connection -->
        <div v-if="step === 1">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('migration.connectionDetails') }}</h3>
          <div class="max-w-lg space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('migration.hostname') }}</label>
              <input v-model="form.host" type="text" class="input mt-1" placeholder="192.168.1.100 or server.example.com" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('migration.port') }}</label>
              <input v-model.number="form.port" type="number" class="input mt-1" placeholder="22" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('migration.username') }}</label>
              <input v-model="form.username" type="text" class="input mt-1" placeholder="root" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('migration.authMethod') }}</label>
              <select v-model="form.auth_method" class="input mt-1">
                <option value="password">{{ $t('migration.password') }}</option>
                <option value="key">{{ $t('migration.privateKey') }}</option>
                <option v-if="showApiKey" value="api_key">API Key</option>
              </select>
            </div>
            <div v-if="form.auth_method === 'password'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('migration.password') }}</label>
              <input v-model="form.password" type="password" class="input mt-1" />
            </div>
            <div v-if="form.auth_method === 'key'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('migration.privateKey') }}</label>
              <textarea v-model="form.private_key" class="input mt-1" rows="4" placeholder="-----BEGIN RSA PRIVATE KEY-----"></textarea>
            </div>
            <div v-if="form.auth_method === 'api_key'">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">API Key</label>
              <input v-model="form.api_key" type="text" class="input mt-1" />
            </div>
          </div>
          <div class="mt-6 flex justify-between">
            <button @click="step = 0" class="btn-secondary">← {{ $t('common.back') }}</button>
            <button @click="testAndDiscover" :disabled="!canTestConnection || testing" class="btn-primary">
              <span v-if="testing" class="flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                {{ $t('migration.testing') }}
              </span>
              <span v-else>{{ $t('migration.testAndDiscover') }} →</span>
            </button>
          </div>
        </div>

        <!-- Step 2: Test & Discover -->
        <div v-if="step === 2">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('migration.discoveredResources') }}</h3>

          <!-- Connection status -->
          <div :class="['p-3 rounded-lg mb-4', connectionResult?.success ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300']">
            {{ connectionResult?.message }}
          </div>

          <div v-if="discoveredData" class="space-y-4">
            <!-- Server type detected -->
            <div v-if="discoveredData.server_type" class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('migration.detectedServerType') }}: <strong class="text-gray-900 dark:text-white">{{ discoveredData.server_type }}</strong>
            </div>

            <!-- Domains -->
            <div v-if="discoveredData.domains?.length">
              <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ $t('migration.domains') }} ({{ discoveredData.domains.length }})</h4>
              <div class="space-y-1 max-h-60 overflow-y-auto">
                <label v-for="(domain, i) in discoveredData.domains" :key="i" class="flex items-center p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700">
                  <input type="checkbox" v-model="selectedItems.domains" :value="domain" class="mr-3 rounded" />
                  <span class="text-sm text-gray-900 dark:text-white">{{ domain.name }}</span>
                  <span class="ml-2 text-xs text-gray-500">{{ domain.path }}</span>
                </label>
              </div>
            </div>

            <!-- Databases -->
            <div v-if="discoveredData.databases?.length">
              <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ $t('migration.databases') }} ({{ discoveredData.databases.length }})</h4>
              <div class="space-y-1 max-h-40 overflow-y-auto">
                <label v-for="(db, i) in discoveredData.databases" :key="i" class="flex items-center p-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700">
                  <input type="checkbox" v-model="selectedItems.databases" :value="db" class="mr-3 rounded" />
                  <span class="text-sm text-gray-900 dark:text-white">{{ db.name }}</span>
                </label>
              </div>
            </div>

            <!-- Cron jobs -->
            <div v-if="discoveredData.crons?.length">
              <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ $t('migration.cronJobs') }} ({{ discoveredData.crons.length }})</h4>
              <label class="flex items-center p-2">
                <input type="checkbox" v-model="selectedItems.crons" class="mr-3 rounded" />
                <span class="text-sm text-gray-900 dark:text-white">{{ $t('migration.migrateCrons') }}</span>
              </label>
            </div>

            <!-- Options -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-4 space-y-2">
              <h4 class="font-medium text-gray-900 dark:text-white mb-2">{{ $t('migration.options') }}</h4>
              <label class="flex items-center p-2">
                <input type="checkbox" v-model="selectedItems.files" class="mr-3 rounded" />
                <span class="text-sm text-gray-900 dark:text-white">{{ $t('migration.migrateFiles') }}</span>
              </label>
              <label class="flex items-center p-2">
                <input type="checkbox" v-model="selectedItems.ssl" class="mr-3 rounded" />
                <span class="text-sm text-gray-900 dark:text-white">{{ $t('migration.issueSsl') }}</span>
              </label>
            </div>
          </div>

          <div class="mt-6 flex justify-between">
            <button @click="step = 1" class="btn-secondary">← {{ $t('common.back') }}</button>
            <button @click="step = 3" :disabled="!hasSelectedItems" class="btn-primary">
              {{ $t('migration.reviewStart') }} →
            </button>
          </div>
        </div>

        <!-- Step 3: Review & Start -->
        <div v-if="step === 3">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('migration.reviewSummary') }}</h3>

          <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 space-y-3">
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">{{ $t('migration.sourceType') }}:</span>
              <span class="text-gray-900 dark:text-white font-medium">{{ form.source_type }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">{{ $t('migration.sourceHost') }}:</span>
              <span class="text-gray-900 dark:text-white font-medium">{{ form.host }}:{{ form.port }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">{{ $t('migration.domains') }}:</span>
              <span class="text-gray-900 dark:text-white font-medium">{{ selectedItems.domains.length }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">{{ $t('migration.databases') }}:</span>
              <span class="text-gray-900 dark:text-white font-medium">{{ selectedItems.databases.length }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">{{ $t('migration.migrateFiles') }}:</span>
              <span class="text-gray-900 dark:text-white font-medium">{{ selectedItems.files ? '✓' : '✗' }}</span>
            </div>
            <div class="flex justify-between text-sm">
              <span class="text-gray-500 dark:text-gray-400">{{ $t('migration.issueSsl') }}:</span>
              <span class="text-gray-900 dark:text-white font-medium">{{ selectedItems.ssl ? '✓' : '✗' }}</span>
            </div>
          </div>

          <div class="mt-6 flex justify-between">
            <button @click="step = 2" class="btn-secondary">← {{ $t('common.back') }}</button>
            <button @click="startMigration" :disabled="starting" class="btn-primary bg-green-600 hover:bg-green-700">
              <span v-if="starting" class="flex items-center">
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                {{ $t('migration.starting') }}
              </span>
              <span v-else>🚀 {{ $t('migration.startMigration') }}</span>
            </button>
          </div>
        </div>

        <!-- Step 4: Progress -->
        <div v-if="step === 4">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('migration.migrationProgress') }}</h3>

          <div v-if="activeJob" class="space-y-4">
            <!-- Progress bar -->
            <div>
              <div class="flex justify-between text-sm mb-1">
                <span class="text-gray-500 dark:text-gray-400">{{ $t('migration.progress') }}</span>
                <span class="text-gray-900 dark:text-white font-medium">{{ activeJob.progress }}%</span>
              </div>
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                <div
                  class="h-3 rounded-full transition-all duration-500"
                  :class="activeJob.status === 'failed' ? 'bg-red-500' : activeJob.status === 'completed' ? 'bg-green-500' : 'bg-primary-600'"
                  :style="{ width: activeJob.progress + '%' }"
                ></div>
              </div>
            </div>

            <!-- Status badge -->
            <div class="flex items-center space-x-2">
              <span :class="statusBadgeClass(activeJob.status)" class="px-2 py-1 text-xs rounded-full font-medium">
                {{ $t('migration.statuses.' + activeJob.status) }}
              </span>
            </div>

            <!-- Log output -->
            <div class="bg-gray-900 rounded-lg p-4 max-h-80 overflow-y-auto font-mono text-xs text-green-400">
              <pre class="whitespace-pre-wrap">{{ activeJob.log || $t('migration.waitingForLogs') }}</pre>
            </div>

            <!-- Actions -->
            <div class="flex space-x-3">
              <button v-if="activeJob.status === 'running'" @click="cancelMigration" class="btn-secondary text-red-600">
                {{ $t('migration.cancel') }}
              </button>
              <button v-if="['completed', 'failed', 'cancelled'].includes(activeJob.status)" @click="resetWizard" class="btn-primary">
                {{ $t('migration.newMigration') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Migration History -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
      <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('migration.history') }}</h2>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('migration.sourceType') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('migration.sourceHost') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('common.status') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('migration.progress') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('common.created') }}</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $t('common.actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="job in jobs" :key="job.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ job.source_type }}</td>
              <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ job.source_host }}</td>
              <td class="px-6 py-4">
                <span :class="statusBadgeClass(job.status)" class="px-2 py-1 text-xs rounded-full font-medium">
                  {{ $t('migration.statuses.' + job.status) }}
                </span>
              </td>
              <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">{{ job.progress }}%</td>
              <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(job.created_at) }}</td>
              <td class="px-6 py-4 text-sm space-x-2">
                <button @click="viewJob(job)" class="text-primary-600 hover:text-primary-800">{{ $t('common.view') }}</button>
                <button v-if="!['running', 'pending'].includes(job.status)" @click="deleteJob(job)" class="text-red-600 hover:text-red-800">{{ $t('common.delete') }}</button>
              </td>
            </tr>
            <tr v-if="!jobs.length">
              <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ $t('migration.noJobs') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'

const { t } = useI18n()

const step = ref(0)
const testing = ref(false)
const starting = ref(false)
const connectionResult = ref(null)
const discoveredData = ref(null)
const activeJob = ref(null)
const jobs = ref([])
let pollInterval = null

const form = reactive({
  source_type: '',
  host: '',
  port: 22,
  username: 'root',
  auth_method: 'password',
  password: '',
  private_key: '',
  api_key: '',
})

const selectedItems = reactive({
  domains: [],
  databases: [],
  files: true,
  emails: false,
  crons: false,
  ssl: true,
})

const sourceTypes = [
  { value: 'plesk', label: 'Plesk', icon: '🟦' },
  { value: 'cpanel', label: 'cPanel', icon: '🟧' },
  { value: 'aapanel', label: 'aaPanel', icon: '🟩' },
  { value: 'directadmin', label: 'DirectAdmin', icon: '🟪' },
  { value: 'ssh', label: 'SSH', icon: '⬛' },
]

const steps = [
  { label: 'migration.stepSource' },
  { label: 'migration.stepConnection' },
  { label: 'migration.stepDiscover' },
  { label: 'migration.stepReview' },
  { label: 'migration.stepProgress' },
]

const showApiKey = computed(() => ['plesk'].includes(form.source_type))

const canTestConnection = computed(() => {
  return form.host && (form.password || form.private_key || form.api_key)
})

const hasSelectedItems = computed(() => {
  return selectedItems.domains.length > 0 || selectedItems.databases.length > 0
})

function statusBadgeClass(status) {
  return {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    running: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    completed: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
  }[status] || ''
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleString()
}

async function testAndDiscover() {
  testing.value = true
  connectionResult.value = null
  discoveredData.value = null

  const credentials = {
    source_type: form.source_type,
    host: form.host,
    port: form.port,
    username: form.username,
    password: form.auth_method === 'password' ? form.password : null,
    private_key: form.auth_method === 'key' ? form.private_key : null,
    api_key: form.auth_method === 'api_key' ? form.api_key : null,
  }

  try {
    // Test connection
    const testRes = await api.post('/migrations/test-connection', credentials)
    connectionResult.value = testRes.data.data

    if (connectionResult.value?.success) {
      // Discover resources
      const discoverRes = await api.post('/migrations/discover', credentials)
      discoveredData.value = discoverRes.data.data
      step.value = 2
    } else {
      step.value = 2
    }
  } catch (err) {
    connectionResult.value = {
      success: false,
      message: err.response?.data?.message || err.message || 'Connection failed'
    }
    step.value = 2
  } finally {
    testing.value = false
  }
}

async function startMigration() {
  starting.value = true
  try {
    const res = await api.post('/migrations', {
      source_type: form.source_type,
      source_host: form.host,
      source_port: form.port,
      credentials: {
        host: form.host,
        port: form.port,
        username: form.username,
        password: form.auth_method === 'password' ? form.password : null,
        private_key: form.auth_method === 'key' ? form.private_key : null,
        api_key: form.auth_method === 'api_key' ? form.api_key : null,
      },
      items: {
        domains: selectedItems.domains,
        databases: selectedItems.databases,
        files: selectedItems.files,
        emails: selectedItems.emails,
        crons: selectedItems.crons,
        ssl: selectedItems.ssl,
      },
      discovered_data: discoveredData.value,
    })

    activeJob.value = res.data.data
    step.value = 4
    startPolling(activeJob.value.id)
  } catch (err) {
    alert(err.response?.data?.message || 'Failed to start migration')
  } finally {
    starting.value = false
  }
}

async function cancelMigration() {
  if (!activeJob.value) return
  try {
    await api.post(`/migrations/${activeJob.value.id}/cancel`)
    await refreshActiveJob()
  } catch (err) {
    console.error(err)
  }
}

async function deleteJob(job) {
  if (!confirm(t('migration.confirmDelete'))) return
  try {
    await api.delete(`/migrations/${job.id}`)
    await loadJobs()
  } catch (err) {
    console.error(err)
  }
}

function viewJob(job) {
  activeJob.value = job
  step.value = 4
  if (['running', 'pending'].includes(job.status)) {
    startPolling(job.id)
  }
}

async function refreshActiveJob() {
  if (!activeJob.value) return
  try {
    const res = await api.get(`/migrations/${activeJob.value.id}`)
    activeJob.value = res.data.data
    if (!['running', 'pending'].includes(activeJob.value.status)) {
      stopPolling()
      await loadJobs()
    }
  } catch (err) {
    console.error(err)
  }
}

function startPolling(jobId) {
  stopPolling()
  pollInterval = setInterval(refreshActiveJob, 3000)
}

function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

function resetWizard() {
  step.value = 0
  activeJob.value = null
  form.source_type = ''
  form.host = ''
  form.port = 22
  form.username = 'root'
  form.auth_method = 'password'
  form.password = ''
  form.private_key = ''
  form.api_key = ''
  selectedItems.domains = []
  selectedItems.databases = []
  selectedItems.files = true
  selectedItems.emails = false
  selectedItems.crons = false
  selectedItems.ssl = true
  connectionResult.value = null
  discoveredData.value = null
}

async function loadJobs() {
  try {
    const res = await api.get('/migrations')
    jobs.value = res.data.data?.data || res.data.data || []
  } catch (err) {
    console.error(err)
  }
}

onMounted(() => {
  loadJobs()
})

onUnmounted(() => {
  stopPolling()
})
</script>
