<template>
  <div class="space-y-6">
    <!-- Log Type Selector -->
    <div class="flex items-center space-x-4">
      <button
        v-for="logType in logTypes"
        :key="logType.id"
        @click="activeLogType = logType.id"
        :class="[
          'px-4 py-2 rounded-lg font-medium transition-colors',
          activeLogType === logType.id
            ? 'bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300'
            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700'
        ]"
      >
        <component :is="logType.icon" class="w-5 h-5 inline-block mr-2" />
        {{ $t(`logs.${logType.id}`) }}
      </button>
    </div>

    <!-- Log Viewer -->
    <VCard :padding="false">
      <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
        <div class="flex items-center space-x-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $t(`logs.${activeLogType}`) }}
          </h3>
          <div class="flex items-center space-x-2">
            <label class="text-sm text-gray-500 dark:text-gray-400">{{ $t('logs.lines') }}:</label>
            <select
              v-model="linesCount"
              @change="fetchLogs"
              class="px-2 py-1 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm"
            >
              <option :value="50">50</option>
              <option :value="100">100</option>
              <option :value="200">200</option>
              <option :value="500">500</option>
            </select>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <VButton
            variant="secondary"
            size="sm"
            :icon="ArrowPathIcon"
            :loading="loading"
            @click="fetchLogs"
          >
            {{ $t('common.refresh') }}
          </VButton>
          <VButton
            variant="secondary"
            size="sm"
            :icon="ArrowDownTrayIcon"
            @click="downloadLog"
          >
            {{ $t('logs.download') }}
          </VButton>
        </div>
      </div>

      <!-- Log Content -->
      <div class="relative">
        <VLoadingSkeleton v-if="loading" class="h-96" />
        <div
          v-else
          ref="logContainer"
          class="p-4 h-96 overflow-auto bg-gray-900 font-mono text-sm text-gray-300 whitespace-pre-wrap"
        >
          <template v-if="logs.length > 0">
            <div v-for="(line, index) in logs" :key="index" class="hover:bg-gray-800 py-0.5">
              <span class="text-gray-500 select-none mr-4">{{ index + 1 }}</span>
              <span :class="getLogLineClass(line)">{{ line }}</span>
            </div>
          </template>
          <template v-else>
            <div class="text-gray-500 text-center py-8">
              {{ $t('logs.noLogs') }}
            </div>
          </template>
        </div>
      </div>
    </VCard>

    <!-- Log Path Info -->
    <VCard>
      <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        {{ $t('logs.logPath') }}
      </h4>
      <code class="block p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-sm font-mono text-gray-600 dark:text-gray-400">
        {{ activeLogType === 'access' ? accessLogPath : errorLogPath }}
      </code>
    </VCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, markRaw } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import {
  ArrowPathIcon,
  ArrowDownTrayIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  domain: {
    type: Object,
    required: true
  }
})

const { t } = useI18n()
const appStore = useAppStore()

// State
const activeLogType = ref('access')
const linesCount = ref(100)
const logs = ref([])
const loading = ref(false)
const logContainer = ref(null)

// Log types
const logTypes = [
  { id: 'access', icon: markRaw(DocumentTextIcon) },
  { id: 'error', icon: markRaw(ExclamationTriangleIcon) }
]

// Computed
const accessLogPath = computed(() => {
  return `/home/${props.domain.user?.username || 'user'}/domains/${props.domain.name}/logs/access.log`
})

const errorLogPath = computed(() => {
  return `/home/${props.domain.user?.username || 'user'}/domains/${props.domain.name}/logs/error.log`
})

// Methods
function getLogLineClass(line) {
  if (line.includes('error') || line.includes('Error') || line.includes('ERROR')) {
    return 'text-red-400'
  }
  if (line.includes('warn') || line.includes('Warn') || line.includes('WARN')) {
    return 'text-yellow-400'
  }
  if (line.includes('200') || line.includes('success')) {
    return 'text-green-400'
  }
  if (line.includes('404') || line.includes('403')) {
    return 'text-orange-400'
  }
  if (line.includes('500') || line.includes('502') || line.includes('503')) {
    return 'text-red-400'
  }
  return 'text-gray-300'
}

async function fetchLogs() {
  loading.value = true
  try {
    const response = await api.get(`/domains/${props.domain.id}/logs`, {
      params: {
        type: activeLogType.value,
        lines: linesCount.value
      }
    })
    const content = response.data.data?.content || ''
    logs.value = content.split('\n').filter(line => line.trim())

    // Scroll to bottom
    await nextTick()
    if (logContainer.value) {
      logContainer.value.scrollTop = logContainer.value.scrollHeight
    }
  } catch (err) {
    console.error('Failed to fetch logs:', err)
    logs.value = []
  } finally {
    loading.value = false
  }
}

function downloadLog() {
  const content = logs.value.join('\n')
  const blob = new Blob([content], { type: 'text/plain' })
  const url = window.URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', `${props.domain.name}_${activeLogType.value}.log`)
  document.body.appendChild(link)
  link.click()
  link.remove()
  window.URL.revokeObjectURL(url)
}

onMounted(() => {
  fetchLogs()
})
</script>
