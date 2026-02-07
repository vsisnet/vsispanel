<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('task.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('task.description') }}</p>
      </div>
      <div class="flex items-center space-x-3">
        <button
          @click="fetchTasks"
          class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
        >
          <svg class="w-4 h-4 mr-2" :class="{ 'animate-spin': isLoading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          {{ $t('common.refresh') }}
        </button>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('task.total') }}</div>
        <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.total }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm font-medium text-blue-500">{{ $t('task.running') }}</div>
        <div class="mt-1 text-2xl font-semibold text-blue-600">{{ stats.running }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm font-medium text-yellow-500">{{ $t('task.pending') }}</div>
        <div class="mt-1 text-2xl font-semibold text-yellow-600">{{ stats.pending }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm font-medium text-green-500">{{ $t('task.completedToday') }}</div>
        <div class="mt-1 text-2xl font-semibold text-green-600">{{ stats.completed_today }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm font-medium text-red-500">{{ $t('task.failedToday') }}</div>
        <div class="mt-1 text-2xl font-semibold text-red-600">{{ stats.failed_today }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="text-sm font-medium text-purple-500">{{ $t('task.active') }}</div>
        <div class="mt-1 text-2xl font-semibold text-purple-600">{{ stats.active }}</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
      <div class="flex flex-wrap gap-4">
        <div class="flex-1 min-w-[200px]">
          <input
            v-model="filters.search"
            type="text"
            :placeholder="$t('task.searchPlaceholder')"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            @input="debouncedSearch"
          />
        </div>
        <div class="w-40">
          <select
            v-model="filters.status"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            @change="fetchTasks"
          >
            <option value="">{{ $t('task.allStatuses') }}</option>
            <option value="pending">{{ $t('task.statusPending') }}</option>
            <option value="running">{{ $t('task.statusRunning') }}</option>
            <option value="completed">{{ $t('task.statusCompleted') }}</option>
            <option value="failed">{{ $t('task.statusFailed') }}</option>
            <option value="cancelled">{{ $t('task.statusCancelled') }}</option>
          </select>
        </div>
        <div class="w-48">
          <select
            v-model="filters.type"
            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            @change="fetchTasks"
          >
            <option value="">{{ $t('task.allTypes') }}</option>
            <option v-for="(label, key) in taskTypes" :key="key" :value="key">{{ label }}</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Active Tasks Section -->
    <div v-if="activeTasks.length > 0" class="bg-blue-50 dark:bg-blue-900/20 rounded-lg shadow p-4">
      <h2 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-4">
        <svg class="w-5 h-5 inline mr-2 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
        </svg>
        {{ $t('task.activeTasks') }} ({{ activeTasks.length }})
      </h2>
      <div class="space-y-3">
        <div
          v-for="task in activeTasks"
          :key="task.id"
          class="bg-white dark:bg-gray-800 rounded-lg p-4 cursor-pointer hover:shadow-md transition-shadow"
          @click="showTaskDetail(task)"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
              <div class="flex-shrink-0">
                <svg v-if="task.status === 'running'" class="w-6 h-6 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
                </svg>
              </div>
              <div>
                <div class="font-medium text-gray-900 dark:text-white">{{ task.name }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ task.type_label }}</div>
              </div>
            </div>
            <div class="flex items-center space-x-4">
              <div class="w-32">
                <div class="flex justify-between text-sm mb-1">
                  <span class="text-gray-500 dark:text-gray-400">{{ task.progress }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                  <div
                    class="h-2 rounded-full transition-all duration-300"
                    :class="task.status === 'running' ? 'bg-blue-500' : 'bg-yellow-500'"
                    :style="{ width: task.progress + '%' }"
                  ></div>
                </div>
              </div>
              <button
                v-if="task.can_cancel"
                @click.stop="cancelTask(task)"
                class="px-3 py-1 text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
              >
                {{ $t('task.cancel') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tasks Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t('task.allTasks') }}</h2>
        <div v-if="selectedTasks.length > 0" class="flex items-center space-x-2">
          <span class="text-sm text-gray-500 dark:text-gray-400">{{ selectedTasks.length }} {{ $t('task.selected') }}</span>
          <button
            @click="bulkDelete"
            class="px-3 py-1 text-sm text-red-600 hover:text-red-800 dark:text-red-400"
          >
            {{ $t('common.delete') }}
          </button>
        </div>
      </div>

      <div v-if="isLoading" class="p-8 text-center">
        <svg class="animate-spin h-8 w-8 mx-auto text-primary-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <p class="mt-2 text-gray-500 dark:text-gray-400">{{ $t('common.loading') }}</p>
      </div>

      <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th class="w-10 px-4 py-3">
              <input
                type="checkbox"
                v-model="selectAll"
                @change="toggleSelectAll"
                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
              />
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $t('task.taskName') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $t('task.type') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $t('task.status') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $t('task.progress') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $t('task.duration') }}</th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $t('task.createdAt') }}</th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr
            v-for="task in tasks"
            :key="task.id"
            class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
            @click="showTaskDetail(task)"
          >
            <td class="px-4 py-3" @click.stop>
              <input
                type="checkbox"
                v-model="selectedTasks"
                :value="task.id"
                :disabled="task.is_active"
                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 disabled:opacity-50"
              />
            </td>
            <td class="px-4 py-3">
              <div class="font-medium text-gray-900 dark:text-white">{{ task.name }}</div>
              <div v-if="task.error_message" class="text-xs text-red-500 truncate max-w-xs">{{ task.error_message }}</div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ task.type_label }}</td>
            <td class="px-4 py-3">
              <span
                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="getStatusClass(task.status)"
              >
                <svg v-if="task.status === 'running'" class="w-3 h-3 mr-1 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                {{ task.status_label }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="w-20">
                <div class="w-full bg-gray-200 rounded-full h-1.5 dark:bg-gray-700">
                  <div
                    class="h-1.5 rounded-full"
                    :class="getProgressClass(task.status)"
                    :style="{ width: task.progress + '%' }"
                  ></div>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ task.progress }}%</span>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ task.duration_formatted }}</td>
            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ formatDate(task.created_at) }}</td>
            <td class="px-4 py-3 text-right" @click.stop>
              <div class="flex justify-end space-x-2">
                <button
                  v-if="task.status === 'failed'"
                  @click="retryTask(task)"
                  class="text-blue-600 hover:text-blue-800 dark:text-blue-400"
                  :title="$t('task.retry')"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                </button>
                <button
                  v-if="task.can_cancel"
                  @click="cancelTask(task)"
                  class="text-yellow-600 hover:text-yellow-800 dark:text-yellow-400"
                  :title="$t('task.cancel')"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </button>
                <button
                  v-if="!task.is_active"
                  @click="deleteTask(task)"
                  class="text-red-600 hover:text-red-800 dark:text-red-400"
                  :title="$t('common.delete')"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>
          <tr v-if="tasks.length === 0">
            <td colspan="8" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
              {{ $t('task.noTasks') }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="meta.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <div class="text-sm text-gray-500 dark:text-gray-400">
          {{ $t('common.showing') }} {{ (meta.current_page - 1) * meta.per_page + 1 }} {{ $t('common.to') }} {{ Math.min(meta.current_page * meta.per_page, meta.total) }} {{ $t('common.of') }} {{ meta.total }}
        </div>
        <div class="flex space-x-2">
          <button
            @click="changePage(meta.current_page - 1)"
            :disabled="meta.current_page === 1"
            class="px-3 py-1 border rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-600"
          >
            {{ $t('common.previous') }}
          </button>
          <button
            @click="changePage(meta.current_page + 1)"
            :disabled="meta.current_page === meta.last_page"
            class="px-3 py-1 border rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed dark:border-gray-600"
          >
            {{ $t('common.next') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Task Detail Modal -->
    <div v-if="selectedTask" class="fixed inset-0 z-50 overflow-y-auto" @click.self="selectedTask = null">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-hidden">
          <!-- Modal Header -->
          <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div>
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedTask.name }}</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ selectedTask.type_label }}</p>
            </div>
            <button @click="selectedTask = null" class="text-gray-400 hover:text-gray-500">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="px-6 py-4 overflow-y-auto max-h-[60vh]">
            <!-- Status and Progress -->
            <div class="mb-4">
              <div class="flex items-center justify-between mb-2">
                <span
                  class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                  :class="getStatusClass(selectedTask.status)"
                >
                  <svg v-if="selectedTask.status === 'running'" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                  </svg>
                  {{ selectedTask.status_label }}
                </span>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ selectedTask.progress }}%</span>
              </div>
              <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                <div
                  class="h-2 rounded-full transition-all duration-300"
                  :class="getProgressClass(selectedTask.status)"
                  :style="{ width: selectedTask.progress + '%' }"
                ></div>
              </div>
            </div>

            <!-- Task Info -->
            <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
              <div>
                <span class="text-gray-500 dark:text-gray-400">{{ $t('task.createdAt') }}:</span>
                <span class="ml-2 text-gray-900 dark:text-white">{{ formatDate(selectedTask.created_at) }}</span>
              </div>
              <div>
                <span class="text-gray-500 dark:text-gray-400">{{ $t('task.duration') }}:</span>
                <span class="ml-2 text-gray-900 dark:text-white">{{ selectedTask.duration_formatted }}</span>
              </div>
              <div v-if="selectedTask.started_at">
                <span class="text-gray-500 dark:text-gray-400">{{ $t('task.startedAt') }}:</span>
                <span class="ml-2 text-gray-900 dark:text-white">{{ formatDate(selectedTask.started_at) }}</span>
              </div>
              <div v-if="selectedTask.completed_at">
                <span class="text-gray-500 dark:text-gray-400">{{ $t('task.completedAt') }}:</span>
                <span class="ml-2 text-gray-900 dark:text-white">{{ formatDate(selectedTask.completed_at) }}</span>
              </div>
            </div>

            <!-- Error Message -->
            <div v-if="selectedTask.error_message" class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
              <h4 class="text-sm font-medium text-red-800 dark:text-red-200 mb-1">{{ $t('task.errorMessage') }}</h4>
              <p class="text-sm text-red-700 dark:text-red-300">{{ selectedTask.error_message }}</p>
            </div>

            <!-- Output -->
            <div v-if="selectedTask.output" class="mb-4">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('task.output') }}</h4>
              <pre class="p-3 bg-gray-900 text-gray-100 rounded-lg text-xs overflow-x-auto max-h-64 overflow-y-auto font-mono">{{ selectedTask.output }}</pre>
            </div>
          </div>

          <!-- Modal Footer -->
          <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end space-x-3">
            <button
              v-if="selectedTask.status === 'failed'"
              @click="retryTask(selectedTask); selectedTask = null"
              class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
            >
              {{ $t('task.retry') }}
            </button>
            <button
              v-if="selectedTask.can_cancel"
              @click="cancelTask(selectedTask); selectedTask = null"
              class="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700"
            >
              {{ $t('task.cancel') }}
            </button>
            <button
              @click="selectedTask = null"
              class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
              {{ $t('common.close') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import { useAppStore } from '@/stores/app'

// Simple debounce function
const debounce = (fn, delay) => {
  let timeoutId
  return (...args) => {
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => fn(...args), delay)
  }
}

const { t } = useI18n()
const appStore = useAppStore()

// State
const isLoading = ref(false)
const tasks = ref([])
const activeTasks = ref([])
const stats = ref({
  total: 0,
  active: 0,
  pending: 0,
  running: 0,
  completed_today: 0,
  failed_today: 0,
})
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 20,
  total: 0,
})
const filters = ref({
  search: '',
  status: '',
  type: '',
})
const selectedTasks = ref([])
const selectAll = ref(false)
const selectedTask = ref(null)
const taskTypes = ref({})
const pollingInterval = ref(null)

// Computed
const debouncedSearch = debounce(() => {
  fetchTasks()
}, 300)

// Methods
const fetchTasks = async (page = 1) => {
  isLoading.value = true
  try {
    const params = { page, per_page: meta.value.per_page }
    if (filters.value.search) params.search = filters.value.search
    if (filters.value.status) params.status = filters.value.status
    if (filters.value.type) params.type = filters.value.type

    const response = await api.get('/tasks', { params })
    if (response.data.success) {
      tasks.value = response.data.data
      meta.value = response.data.meta
    }
  } catch (error) {
    console.error('Failed to fetch tasks:', error)
  } finally {
    isLoading.value = false
  }
}

const fetchActiveTasks = async () => {
  try {
    const response = await api.get('/tasks/active')
    if (response.data.success) {
      activeTasks.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch active tasks:', error)
  }
}

const fetchStats = async () => {
  try {
    const response = await api.get('/tasks/stats')
    if (response.data.success) {
      stats.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
}

const fetchTaskTypes = async () => {
  try {
    const response = await api.get('/tasks/types')
    if (response.data.success) {
      taskTypes.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch task types:', error)
  }
}

const cancelTask = async (task) => {
  try {
    const response = await api.post(`/tasks/${task.id}/cancel`)
    if (response.data.success) {
      appStore.showToast({ type: 'success', message: t('task.cancelSuccess') })
      await fetchTasks(meta.value.current_page)
      await fetchActiveTasks()
      await fetchStats()
    }
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  }
}

const retryTask = async (task) => {
  try {
    const response = await api.post(`/tasks/${task.id}/retry`)
    if (response.data.success) {
      appStore.showToast({ type: 'success', message: t('task.retrySuccess') })
      await fetchTasks(meta.value.current_page)
      await fetchActiveTasks()
      await fetchStats()
    }
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  }
}

const deleteTask = async (task) => {
  if (!confirm(t('task.confirmDelete'))) return

  try {
    const response = await api.delete(`/tasks/${task.id}`)
    if (response.data.success) {
      appStore.showToast({ type: 'success', message: t('task.deleteSuccess') })
      await fetchTasks(meta.value.current_page)
      await fetchStats()
    }
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  }
}

const bulkDelete = async () => {
  if (!confirm(t('task.confirmBulkDelete', { count: selectedTasks.value.length }))) return

  try {
    const response = await api.post('/tasks/bulk-delete', { ids: selectedTasks.value })
    if (response.data.success) {
      appStore.showToast({ type: 'success', message: t('task.bulkDeleteSuccess', { count: response.data.data.deleted_count }) })
      selectedTasks.value = []
      await fetchTasks(meta.value.current_page)
      await fetchStats()
    }
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  }
}

const showTaskDetail = (task) => {
  selectedTask.value = task
}

const toggleSelectAll = () => {
  if (selectAll.value) {
    selectedTasks.value = tasks.value.filter(t => !t.is_active).map(t => t.id)
  } else {
    selectedTasks.value = []
  }
}

const changePage = (page) => {
  if (page >= 1 && page <= meta.value.last_page) {
    fetchTasks(page)
  }
}

const getStatusClass = (status) => {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-200',
    running: 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200',
    completed: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
    cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
  }
  return classes[status] || classes.pending
}

const getProgressClass = (status) => {
  const classes = {
    pending: 'bg-yellow-500',
    running: 'bg-blue-500',
    completed: 'bg-green-500',
    failed: 'bg-red-500',
    cancelled: 'bg-gray-500',
  }
  return classes[status] || classes.pending
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

// Polling for active tasks
const startPolling = () => {
  pollingInterval.value = setInterval(() => {
    fetchActiveTasks()
    fetchStats()
  }, 5000)
}

const stopPolling = () => {
  if (pollingInterval.value) {
    clearInterval(pollingInterval.value)
    pollingInterval.value = null
  }
}

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchTasks(),
    fetchActiveTasks(),
    fetchStats(),
    fetchTaskTypes(),
  ])
  startPolling()
})

onUnmounted(() => {
  stopPolling()
})
</script>
