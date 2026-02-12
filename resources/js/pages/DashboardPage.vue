<template>
  <div>
    <!-- Welcome Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('dashboard.welcome') }}, {{ authStore.userName }}!
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ formattedDate }}
      </p>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
      <VCard v-for="stat in statCards" :key="stat.name" :padding="false">
        <template v-if="dashboardStore.loading.stats">
          <VLoadingSkeleton class="h-24" />
        </template>
        <template v-else>
          <div class="p-6">
            <div class="flex items-center">
              <div :class="['p-3 rounded-lg', stat.bgColor]">
                <component :is="stat.icon" :class="['w-6 h-6', stat.iconColor]" />
              </div>
              <div class="ml-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                  {{ $t(stat.label) }}
                </p>
                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                  {{ stat.value }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ stat.subtitle }}
                </p>
              </div>
            </div>
          </div>
        </template>
      </VCard>
    </div>

    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- CPU Usage Chart -->
      <VCard :title="$t('dashboard.cpuUsage')">
        <template #headerRight>
          <VBadge :variant="cpuBadgeVariant">
            {{ dashboardStore.cpuPercentage }}%
          </VBadge>
        </template>
        <template v-if="dashboardStore.loading.metrics">
          <VLoadingSkeleton class="h-64" />
        </template>
        <template v-else>
          <div class="h-64">
            <apexchart
              type="area"
              height="100%"
              :options="cpuChartOptions"
              :series="cpuChartSeries"
            />
          </div>
          <div class="mt-4 grid grid-cols-3 gap-4 text-center">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('dashboard.load1min') }}</p>
              <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ dashboardStore.cpuUsage?.load_1min ?? '-' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('dashboard.load5min') }}</p>
              <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ dashboardStore.cpuUsage?.load_5min ?? '-' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('dashboard.cores') }}</p>
              <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ dashboardStore.cpuUsage?.cores ?? '-' }}
              </p>
            </div>
          </div>
        </template>
      </VCard>

      <!-- Memory Usage Chart -->
      <VCard :title="$t('dashboard.memoryUsage')">
        <template #headerRight>
          <VBadge :variant="memoryBadgeVariant">
            {{ dashboardStore.memoryPercentage }}%
          </VBadge>
        </template>
        <template v-if="dashboardStore.loading.metrics">
          <VLoadingSkeleton class="h-64" />
        </template>
        <template v-else>
          <div class="h-64">
            <apexchart
              type="area"
              height="100%"
              :options="memoryChartOptions"
              :series="memoryChartSeries"
            />
          </div>
          <div class="mt-4 grid grid-cols-3 gap-4 text-center">
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('dashboard.used') }}</p>
              <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ dashboardStore.memoryUsage?.used ?? '-' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('dashboard.total') }}</p>
              <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ dashboardStore.memoryUsage?.total ?? '-' }}
              </p>
            </div>
            <div>
              <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('dashboard.swap') }}</p>
              <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ dashboardStore.memoryUsage?.swap_percentage ?? 0 }}%
              </p>
            </div>
          </div>
        </template>
      </VCard>
    </div>

    <!-- Disk Usage -->
    <VCard :title="$t('dashboard.diskUsage')" class="mb-6">
      <template v-if="dashboardStore.loading.metrics">
        <VLoadingSkeleton class="h-24" />
      </template>
      <template v-else>
        <div class="space-y-4">
          <div
            v-for="(disk, index) in dashboardStore.diskUsage"
            :key="index"
            class="space-y-2"
          >
            <div class="flex justify-between text-sm">
              <span class="text-gray-700 dark:text-gray-300">{{ disk.mount }}</span>
              <span class="text-gray-500 dark:text-gray-400">
                {{ disk.used }} / {{ disk.total }}
              </span>
            </div>
            <div class="w-full h-3 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
              <div
                :class="getDiskBarClass(disk.percentage)"
                :style="{ width: `${disk.percentage}%` }"
                class="h-full rounded-full transition-all duration-300"
              />
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 text-right">
              {{ disk.percentage }}% {{ $t('dashboard.used') }}
            </p>
          </div>
        </div>
      </template>
    </VCard>

    <!-- Activity & Quick Actions -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Recent Activity -->
      <VCard :title="$t('dashboard.recentActivity')">
        <template v-if="dashboardStore.loading.activity">
          <VLoadingSkeleton class="h-48" />
        </template>
        <template v-else-if="dashboardStore.activity.length === 0">
          <VEmptyState
            :title="$t('dashboard.noActivity')"
            :description="$t('dashboard.noActivityDesc')"
            icon="ClockIcon"
          />
        </template>
        <template v-else>
          <div class="space-y-4 max-h-80 overflow-y-auto">
            <div
              v-for="act in dashboardStore.activity"
              :key="act.id"
              class="flex items-start space-x-3"
            >
              <div :class="['p-2 rounded-lg', getActivityColor(act.event)]">
                <component :is="getActivityIcon(act.event)" class="w-4 h-4" :class="getActivityIconColor(act.event)" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-900 dark:text-white">
                  {{ act.description }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                  {{ act.time_ago }}
                  <span v-if="act.causer"> - {{ act.causer.name }}</span>
                </p>
              </div>
            </div>
          </div>
        </template>
      </VCard>

      <!-- Quick Actions -->
      <VCard :title="$t('dashboard.quickActions')">
        <div class="grid grid-cols-2 gap-4">
          <VButton
            v-for="action in quickActions"
            :key="action.name"
            variant="secondary"
            :icon="action.icon"
            class="justify-start"
            @click="handleQuickAction(action.route)"
          >
            {{ $t(action.label) }}
          </VButton>
        </div>
      </VCard>
    </div>

    <!-- System Info & Services -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- System Info -->
      <VCard :title="$t('dashboard.systemInfo')">
        <template v-if="dashboardStore.loading.systemInfo">
          <VLoadingSkeleton class="h-32" />
        </template>
        <template v-else>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div
              v-for="info in systemInfoItems"
              :key="info.label"
              class="text-center p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
            >
              <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ info.label }}
              </p>
              <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white truncate" :title="info.value">
                {{ info.value }}
              </p>
            </div>
          </div>
        </template>
      </VCard>

      <!-- Services Status -->
      <VCard :title="$t('dashboard.services')">
        <template v-if="dashboardStore.loading.systemInfo">
          <VLoadingSkeleton class="h-32" />
        </template>
        <template v-else-if="!services.length">
          <VEmptyState
            :title="$t('dashboard.noServices')"
            :description="$t('dashboard.noServicesDesc')"
            icon="ServerIcon"
          />
        </template>
        <template v-else>
          <div class="grid grid-cols-2 gap-4">
            <div
              v-for="service in services"
              :key="service.name"
              class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
            >
              <span class="text-sm font-medium text-gray-900 dark:text-white">
                {{ service.name }}
              </span>
              <VBadge :variant="service.active ? 'success' : 'danger'">
                {{ service.active ? $t('dashboard.running') : $t('dashboard.stopped') }}
              </VBadge>
            </div>
          </div>
        </template>
      </VCard>
    </div>

    <!-- Last Updated -->
    <div class="text-center text-xs text-gray-400 dark:text-gray-500">
      <span v-if="dashboardStore.lastUpdated">
        {{ $t('dashboard.lastUpdated') }}: {{ formatTime(dashboardStore.lastUpdated) }}
      </span>
      <button
        class="ml-2 text-primary-600 hover:text-primary-700 dark:text-primary-400"
        @click="refreshData"
      >
        <ArrowPathIcon class="w-4 h-4 inline" :class="{ 'animate-spin': isRefreshing }" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import VueApexCharts from 'vue3-apexcharts'
import { useAuthStore } from '@/stores/auth'
import { useDashboardStore } from '@/stores/dashboard'
import { useAppStore } from '@/stores/app'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import VEmptyState from '@/components/ui/VEmptyState.vue'
import {
  GlobeAltIcon,
  CircleStackIcon,
  EnvelopeIcon,
  ServerIcon,
  PlusIcon,
  CloudArrowUpIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  ArrowPathIcon,
  XCircleIcon,
  PencilIcon,
  TrashIcon,
  UserIcon,
  ClockIcon
} from '@heroicons/vue/24/outline'

const apexchart = VueApexCharts

const router = useRouter()
const { t, locale } = useI18n()
const authStore = useAuthStore()
const dashboardStore = useDashboardStore()
const appStore = useAppStore()

const isRefreshing = ref(false)
let refreshInterval = null

const formattedDate = computed(() => {
  return new Date().toLocaleDateString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
})

// Stats cards
const statCards = computed(() => {
  const stats = dashboardStore.stats
  return [
    {
      name: 'websites',
      label: 'dashboard.websites',
      value: stats?.websites?.count ?? 0,
      subtitle: t('dashboard.active'),
      icon: GlobeAltIcon,
      bgColor: 'bg-blue-100 dark:bg-blue-900/30',
      iconColor: 'text-blue-600 dark:text-blue-400'
    },
    {
      name: 'databases',
      label: 'dashboard.databases',
      value: stats?.databases?.count ?? 0,
      subtitle: t('dashboard.running'),
      icon: CircleStackIcon,
      bgColor: 'bg-green-100 dark:bg-green-900/30',
      iconColor: 'text-green-600 dark:text-green-400'
    },
    {
      name: 'email',
      label: 'dashboard.emailAccounts',
      value: stats?.email_accounts?.count ?? 0,
      subtitle: t('dashboard.active'),
      icon: EnvelopeIcon,
      bgColor: 'bg-purple-100 dark:bg-purple-900/30',
      iconColor: 'text-purple-600 dark:text-purple-400'
    },
    {
      name: 'ftp',
      label: 'dashboard.ftpAccounts',
      value: stats?.ftp_accounts?.count ?? 0,
      subtitle: t('dashboard.active'),
      icon: ServerIcon,
      bgColor: 'bg-orange-100 dark:bg-orange-900/30',
      iconColor: 'text-orange-600 dark:text-orange-400'
    }
  ]
})

// Chart options
const isDark = computed(() => appStore.darkMode)

const baseChartOptions = computed(() => ({
  chart: {
    toolbar: { show: false },
    zoom: { enabled: false },
    background: 'transparent',
    animations: {
      enabled: true,
      easing: 'easeinout',
      speed: 800,
    }
  },
  dataLabels: { enabled: false },
  stroke: {
    curve: 'smooth',
    width: 2,
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.1,
      stops: [0, 100]
    }
  },
  grid: {
    borderColor: isDark.value ? '#374151' : '#e5e7eb',
    strokeDashArray: 3,
  },
  xaxis: {
    labels: {
      style: {
        colors: isDark.value ? '#9ca3af' : '#6b7280',
        fontSize: '11px',
      }
    },
    axisBorder: { show: false },
    axisTicks: { show: false },
  },
  yaxis: {
    min: 0,
    max: 100,
    labels: {
      style: {
        colors: isDark.value ? '#9ca3af' : '#6b7280',
        fontSize: '11px',
      },
      formatter: (val) => `${val}%`
    }
  },
  tooltip: {
    theme: isDark.value ? 'dark' : 'light',
    y: {
      formatter: (val) => `${val}%`
    }
  }
}))

const cpuChartOptions = computed(() => ({
  ...baseChartOptions.value,
  colors: ['#1A5276'],
  xaxis: {
    ...baseChartOptions.value.xaxis,
    categories: dashboardStore.cpuHistory.map(h => h.time),
  }
}))

const cpuChartSeries = computed(() => [{
  name: t('dashboard.cpuUsage'),
  data: dashboardStore.cpuHistory.map(h => h.value)
}])

const memoryChartOptions = computed(() => ({
  ...baseChartOptions.value,
  colors: ['#2ECC71'],
  xaxis: {
    ...baseChartOptions.value.xaxis,
    categories: dashboardStore.memoryHistory.map(h => h.time),
  }
}))

const memoryChartSeries = computed(() => [{
  name: t('dashboard.memoryUsage'),
  data: dashboardStore.memoryHistory.map(h => h.value)
}])

// Badge variants based on usage
const cpuBadgeVariant = computed(() => {
  const pct = dashboardStore.cpuPercentage
  if (pct >= 90) return 'danger'
  if (pct >= 70) return 'warning'
  return 'success'
})

const memoryBadgeVariant = computed(() => {
  const pct = dashboardStore.memoryPercentage
  if (pct >= 90) return 'danger'
  if (pct >= 70) return 'warning'
  return 'success'
})

// Disk bar class
function getDiskBarClass(percentage) {
  if (percentage >= 90) return 'bg-red-500'
  if (percentage >= 70) return 'bg-yellow-500'
  return 'bg-green-500'
}

// Activity helpers
function getActivityColor(event) {
  switch (event) {
    case 'created': return 'bg-green-100 dark:bg-green-900/30'
    case 'updated': return 'bg-blue-100 dark:bg-blue-900/30'
    case 'deleted': return 'bg-red-100 dark:bg-red-900/30'
    default: return 'bg-gray-100 dark:bg-gray-700/30'
  }
}

function getActivityIconColor(event) {
  switch (event) {
    case 'created': return 'text-green-600 dark:text-green-400'
    case 'updated': return 'text-blue-600 dark:text-blue-400'
    case 'deleted': return 'text-red-600 dark:text-red-400'
    default: return 'text-gray-600 dark:text-gray-400'
  }
}

function getActivityIcon(event) {
  switch (event) {
    case 'created': return CheckCircleIcon
    case 'updated': return PencilIcon
    case 'deleted': return TrashIcon
    default: return ClockIcon
  }
}

// Quick actions
const quickActions = computed(() => {
  const actions = [
    { name: 'addWebsite', label: 'dashboard.addWebsite', icon: PlusIcon, route: '/websites' },
    { name: 'createDatabase', label: 'dashboard.createDatabase', icon: CircleStackIcon, route: '/databases' },
    { name: 'addEmail', label: 'dashboard.addEmail', icon: EnvelopeIcon, route: '/email' },
    { name: 'fileManager', label: 'dashboard.fileManager', icon: ServerIcon, route: '/files' },
    { name: 'createBackup', label: 'dashboard.createBackup', icon: CloudArrowUpIcon, route: '/backup' },
    { name: 'sslCertificate', label: 'dashboard.sslCertificate', icon: CheckCircleIcon, route: '/ssl' },
  ]
  if (authStore.isAdmin) {
    actions.push({ name: 'migration', label: 'dashboard.migration', icon: ArrowPathIcon, route: '/migration' })
  }
  return actions
})

function handleQuickAction(route) {
  router.push(route)
}

// System info
const systemInfoItems = computed(() => {
  const sys = dashboardStore.systemInfo?.system
  if (!sys) return []
  return [
    { label: 'OS', value: sys.os || '-' },
    { label: 'Hostname', value: sys.hostname || '-' },
    { label: 'Uptime', value: sys.uptime || '-' },
    { label: 'PHP', value: sys.php_version || '-' },
    { label: 'MySQL', value: sys.mysql_version || '-' },
    { label: 'Nginx', value: sys.nginx_version || '-' }
  ]
})

const services = computed(() => {
  const svc = dashboardStore.systemInfo?.services
  if (!svc) return []
  return Object.values(svc)
})

// Format time
function formatTime(date) {
  return new Date(date).toLocaleTimeString(locale.value === 'vi' ? 'vi-VN' : 'en-US', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit'
  })
}

// Refresh data
async function refreshData() {
  isRefreshing.value = true
  try {
    await dashboardStore.fetchAll()
  } catch (error) {
    appStore.showToast({
      type: 'error',
      message: t('dashboard.refreshError')
    })
  } finally {
    isRefreshing.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await dashboardStore.fetchAll()

  // Auto-refresh every 60 seconds
  refreshInterval = setInterval(async () => {
    try {
      await dashboardStore.fetchRealtime()
    } catch (error) {
      // Silent fail for realtime updates
    }
  }, 60000)
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
  }
})
</script>
