<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('marketplace.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('marketplace.description') }}</p>
      </div>
      <div class="flex items-center gap-3">
        <input v-model="searchQuery" type="text" :placeholder="$t('marketplace.search')"
          class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white w-64" />
        <select v-model="filterType"
          class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
          <option value="">{{ $t('marketplace.allTypes') }}</option>
          <option value="php">PHP</option>
          <option value="nodejs">Node.js</option>
          <option value="python">Python</option>
        </select>
      </div>
    </div>

    <!-- Apps Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <VCard v-for="app in filteredApps" :key="app.id" class="hover:shadow-md transition-shadow">
        <div class="p-5">
          <div class="flex items-start gap-4">
            <!-- Icon -->
            <div class="w-14 h-14 rounded-xl flex items-center justify-center flex-shrink-0"
              :class="iconBg(app.icon)">
              <span class="text-2xl font-bold text-white">{{ app.name.charAt(0) }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ app.name }}</h3>
                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                  v{{ app.version }}
                </span>
              </div>
              <span class="text-xs text-gray-400">{{ app.type.toUpperCase() }}</span>
            </div>
          </div>
          <p class="mt-3 text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{{ app.description }}</p>
          <div class="mt-4 flex items-center justify-between">
            <div class="flex items-center gap-2 text-xs text-gray-500">
              <span v-if="app.requirements?.php_version" class="bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 px-2 py-0.5 rounded">
                PHP {{ app.requirements.php_version }}+
              </span>
              <span v-if="app.requirements?.min_disk_mb" class="bg-gray-50 dark:bg-gray-700 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded">
                {{ app.requirements.min_disk_mb }} MB
              </span>
            </div>
            <VButton size="sm" @click="startInstall(app)">
              {{ $t('marketplace.install') }}
            </VButton>
          </div>
        </div>
      </VCard>
    </div>

    <div v-if="filteredApps.length === 0" class="text-center py-12 text-gray-400">
      {{ $t('marketplace.noApps') }}
    </div>

    <!-- Install Wizard Modal -->
    <VModal v-model="showWizard" :title="$t('marketplace.installApp', { name: selectedApp?.name || '' })" size="lg">
      <!-- Steps indicator -->
      <div class="flex items-center justify-center gap-2 mb-6">
        <div v-for="(step, i) in steps" :key="i"
          :class="['flex items-center gap-1.5 text-xs font-medium',
            wizardStep === i ? 'text-primary-600' : wizardStep > i ? 'text-green-600' : 'text-gray-400']">
          <span :class="['w-6 h-6 rounded-full flex items-center justify-center text-xs border-2',
            wizardStep === i ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' :
            wizardStep > i ? 'border-green-500 bg-green-50 dark:bg-green-900/20' :
            'border-gray-300 dark:border-gray-600']">
            <CheckIcon v-if="wizardStep > i" class="w-3.5 h-3.5" />
            <span v-else>{{ i + 1 }}</span>
          </span>
          <span class="hidden sm:inline">{{ step }}</span>
          <ChevronRightIcon v-if="i < steps.length - 1" class="w-4 h-4 text-gray-300 ml-1" />
        </div>
      </div>

      <!-- Step 1: Select Domain -->
      <div v-if="wizardStep === 0" class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $t('marketplace.selectDomainHint') }}</p>
        <select v-model="installForm.domain_id"
          class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
          <option value="">{{ $t('marketplace.selectDomain') }}</option>
          <option v-for="d in domains" :key="d.id" :value="d.id" :disabled="d.status !== 'active'">
            {{ d.name }} {{ d.status !== 'active' ? `(${d.status})` : '' }}
          </option>
        </select>
      </div>

      <!-- Step 2: Options (WordPress example) -->
      <div v-if="wizardStep === 1" class="space-y-4">
        <template v-if="selectedApp?.slug === 'wordpress'">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('marketplace.siteTitle') }}</label>
            <input v-model="installForm.options.site_title" type="text" placeholder="My Website"
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('marketplace.adminUser') }}</label>
              <input v-model="installForm.options.admin_username" type="text" placeholder="admin"
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('marketplace.adminPassword') }}</label>
              <input v-model="installForm.options.admin_password" type="password"
                class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('marketplace.adminEmail') }}</label>
            <input v-model="installForm.options.admin_email" type="email"
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
          </div>
        </template>
        <template v-else>
          <p class="text-sm text-gray-500">{{ $t('marketplace.noOptionsNeeded') }}</p>
        </template>
      </div>

      <!-- Step 3: Requirements Check -->
      <div v-if="wizardStep === 2" class="space-y-3">
        <div v-if="checkingReqs" class="text-center py-8">
          <ArrowPathIcon class="w-8 h-8 text-primary-500 animate-spin mx-auto mb-2" />
          <p class="text-sm text-gray-500">{{ $t('marketplace.checkingRequirements') }}</p>
        </div>
        <template v-else>
          <div v-for="check in reqChecks" :key="check.name"
            class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-gray-800">
            <div>
              <p class="text-sm font-medium text-gray-900 dark:text-white">{{ check.name }}</p>
              <p class="text-xs text-gray-500">{{ $t('marketplace.required') }}: {{ check.required }}</p>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-500">{{ check.current }}</span>
              <CheckCircleIcon v-if="check.passed" class="w-5 h-5 text-green-500" />
              <XCircleIcon v-else class="w-5 h-5 text-red-500" />
            </div>
          </div>
        </template>
      </div>

      <!-- Step 4: Progress -->
      <div v-if="wizardStep === 3" class="space-y-4">
        <div>
          <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ installStatus.current_step || $t('marketplace.preparing') }}</span>
            <span class="text-sm text-gray-500">{{ installStatus.progress }}%</span>
          </div>
          <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
            <div class="bg-primary-600 h-2.5 rounded-full transition-all duration-500"
              :style="{ width: installStatus.progress + '%' }"></div>
          </div>
        </div>
        <div class="bg-gray-900 rounded-lg p-3 max-h-48 overflow-y-auto font-mono text-xs text-green-400">
          <pre class="whitespace-pre-wrap">{{ installStatus.logs || $t('marketplace.waitingForLogs') }}</pre>
        </div>
        <div v-if="installStatus.status === 'completed'" class="bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 rounded-lg p-3 text-sm text-center">
          {{ $t('marketplace.installComplete') }}
        </div>
        <div v-if="installStatus.status === 'failed'" class="bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 rounded-lg p-3 text-sm text-center">
          {{ $t('marketplace.installFailed') }}
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between">
          <VButton v-if="wizardStep > 0 && wizardStep < 3" variant="secondary" @click="wizardStep--">
            {{ $t('marketplace.back') }}
          </VButton>
          <div v-else></div>
          <div class="flex gap-2">
            <VButton variant="secondary" @click="showWizard = false">
              {{ wizardStep === 3 && installStatus.status !== 'processing' ? $t('common.close') : $t('common.cancel') }}
            </VButton>
            <VButton v-if="wizardStep < 3"
              @click="nextStep"
              :disabled="!canProceed"
              :loading="checkingReqs || installing">
              {{ wizardStep === 2 ? $t('marketplace.startInstall') : $t('marketplace.next') }}
            </VButton>
          </div>
        </div>
      </template>
    </VModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VModal from '@/components/ui/VModal.vue'
import {
  CheckIcon,
  CheckCircleIcon,
  XCircleIcon,
  ChevronRightIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

const apps = ref([])
const domains = ref([])
const searchQuery = ref('')
const filterType = ref('')
const showWizard = ref(false)
const selectedApp = ref(null)
const wizardStep = ref(0)
const checkingReqs = ref(false)
const installing = ref(false)
const reqChecks = ref([])
const reqsPassed = ref(false)
const installationId = ref(null)
const installStatus = reactive({ progress: 0, current_step: '', logs: '', status: 'pending' })
let pollInterval = null

const installForm = reactive({
  domain_id: '',
  options: {},
})

const steps = computed(() => [
  t('marketplace.selectDomain'),
  t('marketplace.configure'),
  t('marketplace.requirements'),
  t('marketplace.progress'),
])

const filteredApps = computed(() => {
  return apps.value.filter(app => {
    const matchSearch = !searchQuery.value ||
      app.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      app.description?.toLowerCase().includes(searchQuery.value.toLowerCase())
    const matchType = !filterType.value || app.type === filterType.value
    return matchSearch && matchType
  })
})

const canProceed = computed(() => {
  if (wizardStep.value === 0) return !!installForm.domain_id
  if (wizardStep.value === 1) return true
  if (wizardStep.value === 2) return reqsPassed.value && !checkingReqs.value
  return false
})

function iconBg(icon) {
  const colors = {
    wordpress: 'bg-blue-600',
    laravel: 'bg-red-600',
    joomla: 'bg-orange-600',
    drupal: 'bg-blue-800',
    prestashop: 'bg-pink-600',
    nodejs: 'bg-green-600',
  }
  return colors[icon] || 'bg-gray-600'
}

async function loadApps() {
  try {
    const { data } = await api.get('/apps')
    if (data.success) apps.value = data.data
  } catch (e) { /* interceptor */ }
}

async function loadDomains() {
  try {
    const { data } = await api.get('/domains')
    if (data.success) domains.value = data.data
  } catch (e) { /* interceptor */ }
}

function startInstall(app) {
  selectedApp.value = app
  wizardStep.value = 0
  installForm.domain_id = ''
  installForm.options = {}
  reqChecks.value = []
  reqsPassed.value = false
  installationId.value = null
  Object.assign(installStatus, { progress: 0, current_step: '', logs: '', status: 'pending' })
  showWizard.value = true
}

async function nextStep() {
  if (wizardStep.value === 0) {
    wizardStep.value = 1
  } else if (wizardStep.value === 1) {
    wizardStep.value = 2
    await checkRequirements()
  } else if (wizardStep.value === 2) {
    await startInstallation()
  }
}

async function checkRequirements() {
  checkingReqs.value = true
  try {
    const { data } = await api.post(`/domains/${installForm.domain_id}/apps/check-requirements`, {
      app_id: selectedApp.value.id,
    })
    if (data.success) {
      reqChecks.value = data.data.checks
      reqsPassed.value = data.data.passed
    }
  } catch (e) {
    reqsPassed.value = false
  }
  checkingReqs.value = false
}

async function startInstallation() {
  installing.value = true
  try {
    const { data } = await api.post(`/domains/${installForm.domain_id}/apps/install`, {
      app_id: selectedApp.value.id,
      options: installForm.options,
    })
    if (data.success) {
      installationId.value = data.data.installation_id
      wizardStep.value = 3
      startPolling()
    }
  } catch (e) { /* interceptor */ }
  installing.value = false
}

function startPolling() {
  pollInterval = setInterval(async () => {
    try {
      const { data } = await api.get(`/domains/${installForm.domain_id}/apps/install-status`, {
        params: { installation_id: installationId.value },
      })
      if (data.success && data.data) {
        Object.assign(installStatus, {
          progress: data.data.progress || 0,
          current_step: data.data.current_step || '',
          logs: data.data.logs || '',
          status: data.data.status || 'processing',
        })

        if (data.data.status === 'completed' || data.data.status === 'failed') {
          clearInterval(pollInterval)
          pollInterval = null
        }
      }
    } catch (e) { /* ignore */ }
  }, 2000)
}

onMounted(async () => {
  await Promise.all([loadApps(), loadDomains()])
})
</script>
