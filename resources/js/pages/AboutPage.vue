<template>
  <div>
    <!-- Breadcrumb -->
    <VBreadcrumb class="mb-4" />

    <!-- Page Header -->
    <div class="mb-6 flex items-center gap-3">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('about.title') }}
      </h1>
      <VBadge variant="primary" rounded>
        v{{ panelVersion }}
      </VBadge>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <VLoadingSkeleton class="h-64" />
      <VLoadingSkeleton class="h-64" />
      <VLoadingSkeleton class="h-48" />
    </div>

    <!-- Content -->
    <template v-else>
      <!-- System Info Card -->
      <VCard :title="$t('about.systemInfo')" class="mb-6">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <div
            v-for="item in systemInfoItems"
            :key="item.label"
            class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
          >
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              {{ item.label }}
            </p>
            <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white truncate" :title="item.value">
              {{ item.value }}
            </p>
          </div>
        </div>
      </VCard>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- License Card -->
        <VCard :title="$t('about.license')">
          <div class="space-y-4">
            <div class="flex items-center gap-2">
              <ScaleIcon class="w-5 h-5 text-gray-500 dark:text-gray-400" />
              <span class="text-sm font-semibold text-gray-900 dark:text-white">
                GNU General Public License v3.0
              </span>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
              {{ $t('about.licenseDescription') }}
            </p>
            <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-2">
              <li class="flex items-start gap-2">
                <CheckCircleIcon class="w-4 h-4 text-green-500 mt-0.5 shrink-0" />
                <span>{{ $t('about.licensePermission1') }}</span>
              </li>
              <li class="flex items-start gap-2">
                <CheckCircleIcon class="w-4 h-4 text-green-500 mt-0.5 shrink-0" />
                <span>{{ $t('about.licensePermission2') }}</span>
              </li>
              <li class="flex items-start gap-2">
                <CheckCircleIcon class="w-4 h-4 text-green-500 mt-0.5 shrink-0" />
                <span>{{ $t('about.licensePermission3') }}</span>
              </li>
            </ul>
            <a
              href="https://www.gnu.org/licenses/gpl-3.0.html"
              target="_blank"
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
            >
              {{ $t('about.viewFullLicense') }}
              <ArrowTopRightOnSquareIcon class="w-4 h-4" />
            </a>
          </div>
        </VCard>

        <!-- Credits Card -->
        <VCard :title="$t('about.credits')">
          <div class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-300">
              {{ $t('about.builtWith') }}
            </p>
            <div class="grid grid-cols-2 gap-3">
              <div
                v-for="tech in technologies"
                :key="tech.name"
                class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg"
              >
                <div :class="['w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold', tech.color]">
                  {{ tech.abbr }}
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-white">
                    {{ tech.name }}
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ tech.description }}
                  </p>
                </div>
              </div>
            </div>
            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
              <p class="text-sm text-gray-500 dark:text-gray-400 text-center">
                {{ $t('about.openSource') }}
              </p>
            </div>
          </div>
        </VCard>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VBreadcrumb from '@/components/ui/VBreadcrumb.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import {
  ScaleIcon,
  CheckCircleIcon,
  ArrowTopRightOnSquareIcon,
} from '@heroicons/vue/24/outline'

const { t } = useI18n()

const loading = ref(true)
const systemInfo = ref(null)
const panelVersion = ref('1.0.0')

const systemInfoItems = computed(() => {
  const sys = systemInfo.value?.system
  if (!sys) return []

  return [
    { label: t('about.panelVersion'), value: panelVersion.value },
    { label: t('about.phpVersion'), value: sys.php_version || '-' },
    { label: t('about.mysqlVersion'), value: sys.mysql_version || '-' },
    { label: t('about.redisVersion'), value: sys.redis_version || '-' },
    { label: t('about.nginxVersion'), value: sys.nginx_version || '-' },
    { label: t('about.os'), value: sys.os || '-' },
    { label: t('about.hostname'), value: sys.hostname || '-' },
    { label: t('about.uptime'), value: sys.uptime || '-' },
  ]
})

const technologies = [
  {
    name: 'Laravel',
    abbr: 'La',
    description: 'PHP Framework',
    color: 'bg-red-500',
  },
  {
    name: 'Vue.js',
    abbr: 'Vu',
    description: 'JavaScript Framework',
    color: 'bg-emerald-500',
  },
  {
    name: 'Tailwind CSS',
    abbr: 'Tw',
    description: 'CSS Framework',
    color: 'bg-sky-500',
  },
  {
    name: 'Redis',
    abbr: 'Re',
    description: 'Cache & Queue',
    color: 'bg-red-600',
  },
]

async function fetchSystemInfo() {
  loading.value = true
  try {
    const { data } = await api.get('/dashboard/system-info')
    if (data.success !== false) {
      systemInfo.value = data.data || data
      if (data.data?.panel_version) {
        panelVersion.value = data.data.panel_version
      }
    }
  } catch (error) {
    console.error('Failed to fetch system info:', error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchSystemInfo()
})
</script>
