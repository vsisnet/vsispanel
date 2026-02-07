<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('monitoring.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $t('monitoring.description') }}
      </p>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
      <nav class="-mb-px flex space-x-6">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="switchTab(tab.id)"
          :class="[
            'flex items-center gap-2 py-3 px-1 border-b-2 text-sm font-medium transition-colors',
            activeTab === tab.id
              ? 'border-primary-500 text-primary-600 dark:text-primary-400'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
          ]"
        >
          <component :is="tab.icon" class="w-4 h-4" />
          {{ $t(tab.label) }}
        </button>
      </nav>
    </div>

    <!-- Dashboard Tab -->
    <div v-if="activeTab === 'dashboard'">
      <!-- Uptime & Quick Stats -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <VCard>
          <div class="text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $t('monitoring.uptime') }}</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ currentMetrics?.uptime?.formatted || '--' }}</p>
          </div>
        </VCard>
        <VCard>
          <div class="text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $t('monitoring.processes') }}</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ currentMetrics?.processes?.total || 0 }}</p>
            <p class="text-xs text-red-500" v-if="currentMetrics?.processes?.zombie > 0">{{ currentMetrics.processes.zombie }} zombie</p>
          </div>
        </VCard>
        <VCard>
          <div class="text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $t('monitoring.loadAvg') }}</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ currentMetrics?.load?.[0] || '--' }}</p>
            <p class="text-xs text-gray-400">{{ currentMetrics?.load?.[1] || '--' }} / {{ currentMetrics?.load?.[2] || '--' }}</p>
          </div>
        </VCard>
        <VCard>
          <div class="text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $t('monitoring.cores') }}</p>
            <p class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ currentMetrics?.cpu?.cores || '--' }}</p>
          </div>
        </VCard>
      </div>

      <!-- CPU & Memory Gauges -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- CPU Gauge -->
        <VCard>
          <div class="text-center">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">{{ $t('monitoring.cpu') }}</p>
            <div class="relative w-32 h-32 mx-auto">
              <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" class="stroke-gray-200 dark:stroke-gray-700" />
                <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" stroke-linecap="round"
                  :stroke-dasharray="326.7"
                  :stroke-dashoffset="326.7 - (326.7 * (currentMetrics?.cpu?.percentage || 0)) / 100"
                  :class="getGaugeColor(currentMetrics?.cpu?.percentage || 0)" />
              </svg>
              <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ currentMetrics?.cpu?.percentage || 0 }}%</span>
              </div>
            </div>
          </div>
        </VCard>

        <!-- Memory Gauge -->
        <VCard>
          <div class="text-center">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">{{ $t('monitoring.memory') }}</p>
            <div class="relative w-32 h-32 mx-auto">
              <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 120 120">
                <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" class="stroke-gray-200 dark:stroke-gray-700" />
                <circle cx="60" cy="60" r="52" fill="none" stroke-width="10" stroke-linecap="round"
                  :stroke-dasharray="326.7"
                  :stroke-dashoffset="326.7 - (326.7 * memoryPercentage) / 100"
                  :class="getGaugeColor(memoryPercentage)" />
              </svg>
              <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-2xl font-bold text-gray-900 dark:text-white">{{ memoryPercentage }}%</span>
              </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">{{ formatBytes(currentMetrics?.memory?.used_bytes) }} / {{ formatBytes(currentMetrics?.memory?.total_bytes) }}</p>
          </div>
        </VCard>

        <!-- Disk Usage -->
        <VCard class="lg:col-span-2">
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">{{ $t('monitoring.disk') }}</p>
          <div v-for="disk in (currentMetrics?.disk || [])" :key="disk.mount" class="mb-3 last:mb-0">
            <div class="flex justify-between text-sm mb-1">
              <span class="text-gray-700 dark:text-gray-300">{{ disk.mount }}</span>
              <span class="text-gray-500">{{ disk.percentage }}%</span>
            </div>
            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
              <div
                class="h-3 rounded-full transition-all"
                :class="getBarColor(disk.percentage)"
                :style="{ width: disk.percentage + '%' }"
              ></div>
            </div>
            <div class="flex justify-between text-xs text-gray-400 mt-1">
              <span>{{ formatBytes(disk.used_bytes) }} {{ $t('monitoring.used') }}</span>
              <span>{{ formatBytes(disk.total_bytes) }} {{ $t('monitoring.total') }}</span>
            </div>
          </div>
        </VCard>
      </div>

      <!-- Charts -->
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t('monitoring.charts') }}</h2>
        <div class="flex items-center gap-2">
          <select v-model="chartPeriod" @change="loadHistory" class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300">
            <option value="1h">1h</option>
            <option value="6h">6h</option>
            <option value="24h">24h</option>
            <option value="7d">7d</option>
            <option value="30d">30d</option>
          </select>
          <button @click="refreshAll" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            <ArrowPathIcon class="w-4 h-4" :class="{ 'animate-spin': loading }" />
          </button>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <VCard>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ $t('monitoring.cpuUsage') }}</p>
          <apexchart v-if="historyData.length" type="area" height="250" :options="cpuChartOptions" :series="cpuSeries" />
          <div v-else class="h-[250px] flex items-center justify-center text-gray-400">{{ $t('monitoring.noData') }}</div>
        </VCard>

        <VCard>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ $t('monitoring.memoryUsage') }}</p>
          <apexchart v-if="historyData.length" type="area" height="250" :options="memoryChartOptions" :series="memorySeries" />
          <div v-else class="h-[250px] flex items-center justify-center text-gray-400">{{ $t('monitoring.noData') }}</div>
        </VCard>

        <VCard>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ $t('monitoring.networkIO') }}</p>
          <apexchart v-if="historyData.length" type="line" height="250" :options="networkChartOptions" :series="networkSeries" />
          <div v-else class="h-[250px] flex items-center justify-center text-gray-400">{{ $t('monitoring.noData') }}</div>
        </VCard>

        <VCard>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">{{ $t('monitoring.loadAverage') }}</p>
          <apexchart v-if="historyData.length" type="line" height="250" :options="loadChartOptions" :series="loadSeries" />
          <div v-else class="h-[250px] flex items-center justify-center text-gray-400">{{ $t('monitoring.noData') }}</div>
        </VCard>
      </div>
    </div>

    <!-- Processes Tab -->
    <div v-if="activeTab === 'processes'">
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
          <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t('monitoring.sortBy') }}:</span>
          <select v-model="processSortBy" @change="loadProcesses" class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300">
            <option value="cpu">CPU %</option>
            <option value="memory">Memory %</option>
          </select>
        </div>
        <VButton size="sm" variant="secondary" @click="loadProcesses" :loading="processesLoading">
          <ArrowPathIcon class="w-4 h-4 mr-1" /> {{ $t('monitoring.refresh') }}
        </VButton>
      </div>

      <VCard>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">PID</th>
                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">{{ $t('monitoring.user') }}</th>
                <th class="text-right py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">CPU %</th>
                <th class="text-right py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">MEM %</th>
                <th class="text-left py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">{{ $t('monitoring.command') }}</th>
                <th class="text-right py-2 px-3 text-gray-500 dark:text-gray-400 font-medium">{{ $t('monitoring.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="proc in processes" :key="proc.pid" class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="py-2 px-3 font-mono text-gray-900 dark:text-white">{{ proc.pid }}</td>
                <td class="py-2 px-3 text-gray-600 dark:text-gray-300">{{ proc.user }}</td>
                <td class="py-2 px-3 text-right">
                  <span :class="proc.cpu > 50 ? 'text-red-600' : proc.cpu > 20 ? 'text-yellow-600' : 'text-gray-900 dark:text-white'">
                    {{ proc.cpu }}%
                  </span>
                </td>
                <td class="py-2 px-3 text-right">
                  <span :class="proc.memory > 50 ? 'text-red-600' : proc.memory > 20 ? 'text-yellow-600' : 'text-gray-900 dark:text-white'">
                    {{ proc.memory }}%
                  </span>
                </td>
                <td class="py-2 px-3 text-gray-600 dark:text-gray-300 max-w-xs truncate font-mono text-xs">{{ proc.command }}</td>
                <td class="py-2 px-3 text-right">
                  <button @click="killProcess(proc.pid)" class="text-red-500 hover:text-red-700 text-xs font-medium" :title="$t('monitoring.killProcess')">
                    {{ $t('monitoring.kill') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </VCard>
    </div>

    <!-- Confirm Dialog -->
    <VConfirmDialog
      v-model="showConfirmDialog"
      :title="confirmTitle"
      :message="confirmMessage"
      :confirmLabel="$t('common.confirm')"
      :cancelLabel="$t('common.cancel')"
      variant="danger"
      @confirm="onConfirm"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import VueApexCharts from 'vue3-apexcharts'
import {
  ChartBarIcon,
  CommandLineIcon,
  ArrowPathIcon,
} from '@heroicons/vue/24/outline'

const apexchart = VueApexCharts

const { t } = useI18n()
const appStore = useAppStore()

const tabs = [
  { id: 'dashboard', label: 'monitoring.dashboard', icon: ChartBarIcon },
  { id: 'processes', label: 'monitoring.processesTab', icon: CommandLineIcon },
]
const activeTab = ref('dashboard')

const loading = ref(false)
const currentMetrics = ref(null)
const historyData = ref([])
const chartPeriod = ref('24h')
const processes = ref([])
const processesLoading = ref(false)
const processSortBy = ref('cpu')
const showConfirmDialog = ref(false)
const confirmTitle = ref('')
const confirmMessage = ref('')
let pendingConfirmAction = null

let refreshInterval = null

const memoryPercentage = computed(() => {
  const m = currentMetrics.value?.memory
  if (!m || !m.total_bytes) return 0
  return Math.round((m.used_bytes / m.total_bytes) * 100 * 10) / 10
})

const isDark = computed(() => appStore.darkMode)

const chartTheme = computed(() => ({
  borderColor: isDark.value ? '#374151' : '#e5e7eb',
  labelColor: isDark.value ? '#9ca3af' : '#6b7280',
  tooltipTheme: isDark.value ? 'dark' : 'light',
}))

const cpuChartOptions = computed(() => ({
  chart: { toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  colors: ['#3b82f6'],
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
  grid: { borderColor: chartTheme.value.borderColor, strokeDashArray: 3 },
  xaxis: { type: 'datetime', labels: { style: { colors: chartTheme.value.labelColor, fontSize: '11px' } } },
  yaxis: { max: 100, min: 0, labels: { style: { colors: chartTheme.value.labelColor, fontSize: '11px' } } },
  tooltip: { theme: chartTheme.value.tooltipTheme },
}))

const cpuSeries = computed(() => [{
  name: 'CPU %',
  data: historyData.value.map(m => [new Date(m.recorded_at).getTime(), m.cpu_usage]),
}])

const memoryChartOptions = computed(() => ({
  chart: { toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  colors: ['#8b5cf6'],
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
  grid: { borderColor: chartTheme.value.borderColor, strokeDashArray: 3 },
  xaxis: { type: 'datetime', labels: { style: { colors: chartTheme.value.labelColor, fontSize: '11px' } } },
  yaxis: { labels: { style: { colors: chartTheme.value.labelColor, fontSize: '11px' }, formatter: (v) => formatBytes(v) } },
  tooltip: { theme: chartTheme.value.tooltipTheme },
}))

const memorySeries = computed(() => [{
  name: t('monitoring.memory'),
  data: historyData.value.map(m => [new Date(m.recorded_at).getTime(), m.memory_used]),
}])

const networkChartOptions = computed(() => ({
  chart: { toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  colors: ['#10b981', '#f59e0b'],
  grid: { borderColor: chartTheme.value.borderColor, strokeDashArray: 3 },
  xaxis: { type: 'datetime', labels: { style: { colors: chartTheme.value.labelColor, fontSize: '11px' } } },
  yaxis: { labels: { style: { colors: chartTheme.value.labelColor, fontSize: '11px' }, formatter: (v) => formatBytes(v) } },
  tooltip: { theme: chartTheme.value.tooltipTheme },
  legend: { labels: { colors: isDark.value ? '#d1d5db' : '#374151' } },
}))

const networkSeries = computed(() => [
  { name: 'In', data: historyData.value.map(m => [new Date(m.recorded_at).getTime(), m.network_in]) },
  { name: 'Out', data: historyData.value.map(m => [new Date(m.recorded_at).getTime(), m.network_out]) },
])

const loadChartOptions = computed(() => ({
  chart: { toolbar: { show: false }, zoom: { enabled: false }, background: 'transparent' },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  colors: ['#ef4444'],
  grid: { borderColor: chartTheme.value.borderColor, strokeDashArray: 3 },
  xaxis: { type: 'datetime', labels: { style: { colors: chartTheme.value.labelColor, fontSize: '11px' } } },
  yaxis: { labels: { style: { colors: chartTheme.value.labelColor, fontSize: '11px' } } },
  tooltip: { theme: chartTheme.value.tooltipTheme },
}))

const loadSeries = computed(() => [{
  name: 'Load 1m',
  data: historyData.value.map(m => [new Date(m.recorded_at).getTime(), m.load_1m]),
}])

function getGaugeColor(pct) {
  if (pct >= 90) return 'stroke-red-500'
  if (pct >= 70) return 'stroke-yellow-500'
  return 'stroke-green-500'
}

function getBarColor(pct) {
  if (pct >= 90) return 'bg-red-500'
  if (pct >= 70) return 'bg-yellow-500'
  return 'bg-green-500'
}

function formatBytes(bytes) {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let i = 0; let val = bytes
  while (val >= 1024 && i < units.length - 1) { val /= 1024; i++ }
  return `${val.toFixed(i > 1 ? 1 : 0)} ${units[i]}`
}

async function loadCurrentMetrics() {
  try {
    const { data } = await api.get('/monitoring/current')
    if (data.success) currentMetrics.value = data.data
  } catch (e) { /* interceptor */ }
}

async function loadHistory() {
  try {
    const { data } = await api.get('/monitoring/history', { params: { period: chartPeriod.value } })
    if (data.success) historyData.value = data.data.metrics
  } catch (e) { /* interceptor */ }
}

async function loadProcesses() {
  processesLoading.value = true
  try {
    const { data } = await api.get('/monitoring/processes', { params: { sort: processSortBy.value } })
    if (data.success) processes.value = data.data
  } catch (e) { /* interceptor */ }
  processesLoading.value = false
}

async function refreshAll() {
  loading.value = true
  await Promise.all([loadCurrentMetrics(), loadHistory()])
  loading.value = false
}

function switchTab(tabId) {
  activeTab.value = tabId
  if (tabId === 'processes') loadProcesses()
}

function killProcess(pid) {
  confirmTitle.value = t('monitoring.killProcess')
  confirmMessage.value = t('monitoring.killProcessConfirm', { pid })
  pendingConfirmAction = async () => {
    try {
      const { data } = await api.post(`/monitoring/processes/${pid}/kill`)
      appStore.showToast({ type: data.success ? 'success' : 'error', message: data.message })
      await loadProcesses()
    } catch (e) { /* interceptor */ }
  }
  showConfirmDialog.value = true
}

function onConfirm() {
  if (pendingConfirmAction) {
    pendingConfirmAction()
    pendingConfirmAction = null
  }
}

onMounted(async () => {
  await Promise.all([loadCurrentMetrics(), loadHistory()])
  refreshInterval = setInterval(async () => {
    await loadCurrentMetrics()
  }, 30000)
})

onUnmounted(() => {
  if (refreshInterval) clearInterval(refreshInterval)
})
</script>
