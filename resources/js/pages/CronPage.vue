<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('cron.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('cron.description') }}</p>
      </div>
      <VButton @click="openForm()">
        <PlusIcon class="w-4 h-4 mr-1" /> {{ $t('cron.addJob') }}
      </VButton>
    </div>

    <!-- Jobs Table -->
    <VCard>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200 dark:border-gray-700">
              <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('cron.description') }}</th>
              <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('cron.schedule') }}</th>
              <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('cron.command') }}</th>
              <th class="text-center py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('cron.status') }}</th>
              <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('cron.lastRun') }}</th>
              <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('cron.nextRun') }}</th>
              <th class="text-center py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('cron.active') }}</th>
              <th class="text-right py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('cron.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="job in jobs" :key="job.id" class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
              <td class="py-3 px-4 text-gray-900 dark:text-white">{{ job.description || '--' }}</td>
              <td class="py-3 px-4">
                <span class="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-2 py-1 rounded font-mono">
                  {{ job.schedule }}
                </span>
                <p class="text-xs text-gray-400 mt-0.5">{{ job.schedule_human }}</p>
              </td>
              <td class="py-3 px-4 max-w-[200px]">
                <code class="text-xs text-gray-600 dark:text-gray-300 truncate block">{{ job.command }}</code>
              </td>
              <td class="py-3 px-4 text-center">
                <span v-if="job.last_run_status === 'success'" class="inline-flex items-center gap-1 text-xs text-green-600">
                  <CheckCircleIcon class="w-4 h-4" /> {{ $t('cron.success') }}
                </span>
                <span v-else-if="job.last_run_status === 'failed'" class="inline-flex items-center gap-1 text-xs text-red-600">
                  <XCircleIcon class="w-4 h-4" /> {{ $t('cron.failed') }}
                </span>
                <span v-else-if="job.last_run_status === 'running'" class="inline-flex items-center gap-1 text-xs text-yellow-600">
                  <ArrowPathIcon class="w-4 h-4 animate-spin" /> {{ $t('cron.running') }}
                </span>
                <span v-else class="text-xs text-gray-400">--</span>
              </td>
              <td class="py-3 px-4 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(job.last_run_at) }}</td>
              <td class="py-3 px-4 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(job.next_run_at) }}</td>
              <td class="py-3 px-4 text-center">
                <button @click="toggleJob(job)">
                  <div :class="['w-10 h-6 rounded-full p-0.5 transition-colors', job.is_active ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600']">
                    <div :class="['w-5 h-5 bg-white rounded-full shadow-sm transition-transform', job.is_active ? 'translate-x-4' : 'translate-x-0']"></div>
                  </div>
                </button>
              </td>
              <td class="py-3 px-4 text-right">
                <div class="flex items-center justify-end gap-1">
                  <button @click="runJobNow(job)" :disabled="runningJob === job.id"
                    class="p-1.5 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20 rounded" :title="$t('cron.runNow')">
                    <PlayIcon class="w-4 h-4" :class="{ 'animate-pulse': runningJob === job.id }" />
                  </button>
                  <button @click="viewOutput(job)" class="p-1.5 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded" :title="$t('cron.viewOutput')">
                    <DocumentTextIcon class="w-4 h-4" />
                  </button>
                  <button @click="openForm(job)" class="p-1.5 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded">
                    <PencilSquareIcon class="w-4 h-4" />
                  </button>
                  <button @click="deleteJob(job)" class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded">
                    <TrashIcon class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="jobs.length === 0">
              <td colspan="8" class="text-center py-8 text-gray-400">{{ $t('cron.noJobs') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </VCard>

    <!-- Create/Edit Modal -->
    <VModal v-model="showForm" :title="editing ? $t('cron.editJob') : $t('cron.addJob')" size="lg">
      <form @submit.prevent="saveJob" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('cron.command') }} *</label>
          <input v-model="form.command" type="text" required placeholder="/usr/bin/php /path/to/script.php"
            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-mono text-sm" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('cron.schedule') }} *</label>

          <div class="flex flex-wrap items-center gap-3">
            <!-- Schedule Type Dropdown -->
            <select
              v-model="selectedPreset"
              @change="onPresetChange"
              class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
            >
              <option v-for="preset in presets" :key="preset.value" :value="preset.value">{{ preset.label }}</option>
              <option value="custom">{{ $t('cron.custom') }}</option>
            </select>

            <!-- Time Picker (for daily, weekly, monthly, renew_ssl) -->
            <input
              v-if="['daily', 'weekly', 'monthly', 'renew_ssl'].includes(selectedPreset)"
              v-model="scheduleTime"
              type="time"
              @change="updateCronExpression"
              class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
            />

            <!-- Minute picker (for hourly) -->
            <template v-if="selectedPreset === 'hourly'">
              <span class="text-sm text-gray-600 dark:text-gray-400">{{ $t('cron.atMinute') }}</span>
              <input
                v-model.number="scheduleMinute"
                type="number"
                min="0"
                max="59"
                @change="updateCronExpression"
                class="w-20 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
              />
            </template>

            <!-- Day of Week (for weekly) -->
            <select
              v-if="selectedPreset === 'weekly'"
              v-model="scheduleDayOfWeek"
              @change="updateCronExpression"
              class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
            >
              <option v-for="(day, i) in daysOfWeek" :key="i" :value="i.toString()">{{ day }}</option>
            </select>

            <!-- Day of Month (for monthly) -->
            <template v-if="selectedPreset === 'monthly'">
              <span class="text-sm text-gray-600 dark:text-gray-400">{{ $t('cron.onDay') }}</span>
              <select
                v-model="scheduleDayOfMonth"
                @change="updateCronExpression"
                class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
              >
                <option v-for="day in 28" :key="day" :value="day.toString()">{{ day }}</option>
              </select>
            </template>

            <!-- Schedule Summary -->
            <span v-if="scheduleSummary && selectedPreset !== 'custom'" class="text-sm text-gray-500 dark:text-gray-400 flex items-center">
              <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              {{ scheduleSummary }}
            </span>
          </div>

          <!-- Custom Cron Expression -->
          <div v-if="selectedPreset === 'custom'" class="mt-3 p-4 bg-blue-50 dark:bg-gray-700 rounded-lg">
            <label class="block text-sm font-medium text-primary-600 dark:text-primary-400 mb-1">{{ $t('cron.cronExpression') }}</label>
            <input v-model="form.schedule" type="text" required placeholder="* * * * *"
              @input="validateSchedule"
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-mono text-sm" />
            <p class="mt-2 text-xs text-amber-600 dark:text-amber-400">
              {{ $t('cron.scheduleFormat') }}
            </p>
          </div>

          <!-- Next runs preview -->
          <div v-if="nextRuns.length > 0" class="mt-2 bg-gray-50 dark:bg-gray-800 rounded p-2">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">{{ $t('cron.nextRuns') }}:</p>
            <ul class="text-xs text-gray-600 dark:text-gray-300 space-y-0.5">
              <li v-for="(run, i) in nextRuns" :key="i">{{ run }}</li>
            </ul>
          </div>
          <p v-if="scheduleError" class="text-xs text-red-500 mt-1">{{ scheduleError }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('cron.jobDescription') }}</label>
          <input v-model="form.description" type="text" placeholder="Database backup"
            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('cron.outputHandling') }}</label>
          <select v-model="form.output_handling" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
            <option value="discard">{{ $t('cron.discard') }}</option>
            <option value="email">{{ $t('cron.emailOutput') }}</option>
            <option value="log">{{ $t('cron.logToFile') }}</option>
          </select>
        </div>

        <div v-if="form.output_handling === 'email'">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('cron.outputEmail') }}</label>
          <input v-model="form.output_email" type="email"
            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
        </div>

        <div v-if="form.output_handling === 'log'">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('cron.logPath') }}</label>
          <input v-model="form.log_path" type="text" placeholder="/var/log/my-cron.log"
            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-mono text-sm" />
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <VButton variant="secondary" @click="showForm = false">{{ $t('common.cancel') }}</VButton>
          <VButton @click="saveJob" :loading="saving">{{ $t('common.save') }}</VButton>
        </div>
      </template>
    </VModal>

    <!-- Output Modal -->
    <VModal v-model="showOutput" :title="$t('cron.jobOutput')" size="lg">
      <div v-if="outputData.run_at" class="mb-3 flex items-center gap-3 text-sm">
        <span class="text-gray-500">{{ formatDate(outputData.run_at) }}</span>
        <span :class="outputData.status === 'success' ? 'text-green-600' : 'text-red-600'" class="font-medium">
          {{ outputData.status }}
        </span>
      </div>
      <pre class="bg-gray-900 text-green-400 p-4 rounded-lg text-xs font-mono overflow-auto max-h-[400px] whitespace-pre-wrap">{{ outputData.output || $t('cron.noOutput') }}</pre>
      <template #footer>
        <VButton variant="secondary" @click="showOutput = false">{{ $t('common.close') }}</VButton>
      </template>
    </VModal>

    <!-- Confirm Dialog -->
    <VConfirmDialog
      v-model="showConfirm"
      :title="$t('cron.deleteJob')"
      :message="$t('cron.deleteJobConfirm')"
      variant="danger"
      @confirm="confirmDelete"
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
import VModal from '@/components/ui/VModal.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import {
  PlusIcon,
  PlayIcon,
  DocumentTextIcon,
  PencilSquareIcon,
  TrashIcon,
  ArrowPathIcon,
  CheckCircleIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

const jobs = ref([])
const showForm = ref(false)
const editing = ref(null)
const saving = ref(false)
const showOutput = ref(false)
const outputData = ref({ output: '', status: null, run_at: null })
const showConfirm = ref(false)
const deletingJob = ref(null)
const runningJob = ref(null)
const nextRuns = ref([])
const scheduleError = ref('')
const selectedPreset = ref('hourly')
const scheduleTime = ref('02:00')
const scheduleMinute = ref(0)
const scheduleDayOfWeek = ref('0')
const scheduleDayOfMonth = ref('1')
let validateTimer = null

const form = ref(getDefaultForm())

const presets = computed(() => [
  { label: t('cron.presetEveryMinute'), value: 'every_minute' },
  { label: t('cron.presetEvery5Min'), value: 'every_5min' },
  { label: t('cron.presetEvery15Min'), value: 'every_15min' },
  { label: t('cron.presetEvery30Min'), value: 'every_30min' },
  { label: t('cron.presetHourly'), value: 'hourly' },
  { label: t('cron.presetDaily'), value: 'daily' },
  { label: t('cron.presetWeekly'), value: 'weekly' },
  { label: t('cron.presetMonthly'), value: 'monthly' },
  { label: t('cron.presetRenewSsl'), value: 'renew_ssl' },
])

const daysOfWeek = computed(() => [
  t('cron.sunday'), t('cron.monday'), t('cron.tuesday'), t('cron.wednesday'),
  t('cron.thursday'), t('cron.friday'), t('cron.saturday'),
])

const scheduleSummary = computed(() => {
  switch (selectedPreset.value) {
    case 'every_minute': return t('cron.summaryEveryMinute')
    case 'every_5min': return t('cron.summaryEvery5Min')
    case 'every_15min': return t('cron.summaryEvery15Min')
    case 'every_30min': return t('cron.summaryEvery30Min')
    case 'hourly': return t('cron.summaryHourly', { minute: scheduleMinute.value })
    case 'daily': return t('cron.summaryDaily', { time: scheduleTime.value })
    case 'weekly': return t('cron.summaryWeekly', { day: daysOfWeek.value[parseInt(scheduleDayOfWeek.value)], time: scheduleTime.value })
    case 'monthly': return t('cron.summaryMonthly', { day: scheduleDayOfMonth.value, time: scheduleTime.value })
    case 'renew_ssl': return t('cron.summaryRenewSsl', { time: scheduleTime.value })
    default: return ''
  }
})

function getDefaultForm() {
  return { command: '', schedule: '0 * * * *', description: '', output_handling: 'discard', output_email: '', log_path: '' }
}

function updateCronExpression() {
  const timeParts = (scheduleTime.value || '00:00').split(':')
  const hour = parseInt(timeParts[0]) || 0
  const minute = parseInt(timeParts[1]) || 0

  switch (selectedPreset.value) {
    case 'every_minute': form.value.schedule = '* * * * *'; break
    case 'every_5min': form.value.schedule = '*/5 * * * *'; break
    case 'every_15min': form.value.schedule = '*/15 * * * *'; break
    case 'every_30min': form.value.schedule = '*/30 * * * *'; break
    case 'hourly': form.value.schedule = `${scheduleMinute.value} * * * *`; break
    case 'daily': form.value.schedule = `${minute} ${hour} * * *`; break
    case 'weekly': form.value.schedule = `${minute} ${hour} * * ${scheduleDayOfWeek.value}`; break
    case 'monthly': form.value.schedule = `${minute} ${hour} ${scheduleDayOfMonth.value} * *`; break
    case 'renew_ssl': form.value.schedule = `${minute} ${hour} * * *`; break
  }
  validateSchedule()
}

function parseCronToPreset(cron) {
  const parts = (cron || '').trim().split(/\s+/)
  if (parts.length !== 5) return { type: 'custom' }

  const [min, hour, dom, mon, dow] = parts
  const isNum = s => /^\d+$/.test(s)
  const pad = s => String(s).padStart(2, '0')

  if (min === '*' && hour === '*' && dom === '*' && mon === '*' && dow === '*') return { type: 'every_minute' }
  if (min === '*/5' && hour === '*' && dom === '*' && mon === '*' && dow === '*') return { type: 'every_5min' }
  if (min === '*/15' && hour === '*' && dom === '*' && mon === '*' && dow === '*') return { type: 'every_15min' }
  if (min === '*/30' && hour === '*' && dom === '*' && mon === '*' && dow === '*') return { type: 'every_30min' }

  if (isNum(min) && hour === '*' && dom === '*' && mon === '*' && dow === '*') {
    return { type: 'hourly', minute: parseInt(min) }
  }
  if (isNum(min) && isNum(hour) && dom === '*' && mon === '*' && dow === '*') {
    return { type: 'daily', time: `${pad(hour)}:${pad(min)}` }
  }
  if (isNum(min) && isNum(hour) && dom === '*' && mon === '*' && isNum(dow)) {
    return { type: 'weekly', time: `${pad(hour)}:${pad(min)}`, dayOfWeek: dow }
  }
  if (isNum(min) && isNum(hour) && isNum(dom) && mon === '*' && dow === '*') {
    return { type: 'monthly', time: `${pad(hour)}:${pad(min)}`, dayOfMonth: dom }
  }

  return { type: 'custom' }
}

function onPresetChange() {
  if (selectedPreset.value === 'renew_ssl') {
    scheduleTime.value = '03:00'
    if (!form.value.command) form.value.command = '/usr/bin/certbot renew --quiet --deploy-hook "systemctl reload nginx"'
    if (!form.value.description) form.value.description = t('cron.presetRenewSslDesc')
  }
  if (selectedPreset.value !== 'custom') {
    updateCronExpression()
  } else {
    validateSchedule()
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '--'
  return new Date(dateStr).toLocaleString()
}

async function loadJobs() {
  try {
    const { data } = await api.get('/cron-jobs')
    if (data.success) jobs.value = data.data
  } catch (e) { /* interceptor */ }
}

function openForm(job = null) {
  editing.value = job
  form.value = job ? { ...job } : getDefaultForm()

  if (job) {
    const parsed = parseCronToPreset(form.value.schedule)
    selectedPreset.value = parsed.type
    if (parsed.time) scheduleTime.value = parsed.time
    if (parsed.minute !== undefined) scheduleMinute.value = parsed.minute
    if (parsed.dayOfWeek) scheduleDayOfWeek.value = parsed.dayOfWeek
    if (parsed.dayOfMonth) scheduleDayOfMonth.value = parsed.dayOfMonth
  } else {
    selectedPreset.value = 'hourly'
    scheduleTime.value = '02:00'
    scheduleMinute.value = 0
    scheduleDayOfWeek.value = '0'
    scheduleDayOfMonth.value = '1'
  }

  nextRuns.value = []
  scheduleError.value = ''
  showForm.value = true
  updateCronExpression()
}

async function validateSchedule() {
  clearTimeout(validateTimer)
  validateTimer = setTimeout(async () => {
    if (!form.value.schedule) return
    try {
      const { data } = await api.post('/cron-jobs/validate', { expression: form.value.schedule })
      if (data.success) {
        if (data.data.valid) {
          nextRuns.value = data.data.next_runs
          scheduleError.value = ''
        } else {
          nextRuns.value = []
          scheduleError.value = t('cron.invalidExpression')
        }
      }
    } catch (e) {
      scheduleError.value = t('cron.invalidExpression')
    }
  }, 500)
}

async function saveJob() {
  saving.value = true
  try {
    if (editing.value) {
      await api.put(`/cron-jobs/${editing.value.id}`, form.value)
    } else {
      await api.post('/cron-jobs', form.value)
    }
    appStore.showToast({ type: 'success', message: t('common.saved') })
    showForm.value = false
    await loadJobs()
  } catch (e) { /* interceptor */ }
  saving.value = false
}

async function toggleJob(job) {
  try {
    await api.post(`/cron-jobs/${job.id}/toggle`)
    await loadJobs()
  } catch (e) { /* interceptor */ }
}

async function runJobNow(job) {
  runningJob.value = job.id
  try {
    const { data } = await api.post(`/cron-jobs/${job.id}/run-now`)
    if (data.success) {
      outputData.value = { output: data.data.output, status: data.data.status, run_at: new Date().toISOString() }
      showOutput.value = true
    }
    await loadJobs()
  } catch (e) { /* interceptor */ }
  runningJob.value = null
}

async function viewOutput(job) {
  try {
    const { data } = await api.get(`/cron-jobs/${job.id}/output`)
    if (data.success) {
      outputData.value = data.data
      showOutput.value = true
    }
  } catch (e) { /* interceptor */ }
}

function deleteJob(job) {
  deletingJob.value = job
  showConfirm.value = true
}

async function confirmDelete() {
  if (!deletingJob.value) return
  try {
    await api.delete(`/cron-jobs/${deletingJob.value.id}`)
    appStore.showToast({ type: 'success', message: t('common.deleted') })
    await loadJobs()
  } catch (e) { /* interceptor */ }
  deletingJob.value = null
}

onMounted(loadJobs)
</script>
