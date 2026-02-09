<template>
  <div class="p-6">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        {{ $t('backup.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ $t('backup.description') }}
      </p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0 bg-primary-100 dark:bg-primary-900 rounded-lg p-3">
            <svg class="w-6 h-6 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('backup.totalBackups') }}</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.total_backups }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0 bg-green-100 dark:bg-green-900 rounded-lg p-3">
            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('backup.completedBackups') }}</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.completed_backups }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900 rounded-lg p-3">
            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('backup.totalSize') }}</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.total_size_formatted }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        <div class="flex items-center">
          <div class="flex-shrink-0 bg-purple-100 dark:bg-purple-900 rounded-lg p-3">
            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <div class="ml-4">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('backup.activeConfigs') }}</p>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ stats.active_configs }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Backup Tasks -->
    <div v-if="activeBackupTasks.length > 0" class="mb-6 bg-blue-50 dark:bg-blue-900/20 rounded-lg shadow p-4">
      <h2 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-4 flex items-center">
        <svg class="w-5 h-5 mr-2 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" />
        </svg>
        {{ $t('backup.activeTasks') }} ({{ activeBackupTasks.length }})
      </h2>
      <div class="space-y-3">
        <div
          v-for="task in activeBackupTasks"
          :key="task.id"
          class="bg-white dark:bg-gray-800 rounded-lg p-4"
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
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ task.description }}</div>
              </div>
            </div>
            <div class="flex items-center space-x-4">
              <div class="w-48">
                <div class="flex justify-between text-sm mb-1">
                  <span class="text-gray-500 dark:text-gray-400">{{ task.progress }}%</span>
                  <span v-if="task.duration_formatted" class="text-gray-400 dark:text-gray-500">{{ task.duration_formatted }}</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                  <div
                    class="h-2 rounded-full transition-all duration-300"
                    :class="task.status === 'running' ? 'bg-blue-500' : 'bg-yellow-500'"
                    :style="{ width: task.progress + '%' }"
                  ></div>
                </div>
              </div>
              <router-link
                :to="`/tasks?id=${task.id}`"
                class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400"
              >
                {{ $t('common.details') }}
              </router-link>
            </div>
          </div>
          <!-- Latest output preview -->
          <div v-if="task.output" class="mt-3 text-xs font-mono bg-gray-100 dark:bg-gray-900 p-2 rounded max-h-20 overflow-y-auto">
            <pre class="whitespace-pre-wrap text-gray-600 dark:text-gray-400">{{ getLastOutputLines(task.output, 3) }}</pre>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="mb-6">
      <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
          <button
            @click="activeTab = 'backups'"
            :class="[
              activeTab === 'backups'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
            ]"
          >
            {{ $t('backup.backups') }}
          </button>
          <button
            @click="activeTab = 'configs'"
            :class="[
              activeTab === 'configs'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
            ]"
          >
            {{ $t('backup.configs') }}
          </button>
          <button
            @click="activeTab = 'remotes'"
            :class="[
              activeTab === 'remotes'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
            ]"
          >
            {{ $t('backup.storageRemotes') }}
          </button>
        </nav>
      </div>
    </div>

    <!-- Backups Tab -->
    <div v-if="activeTab === 'backups'">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <div class="flex items-center space-x-4">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.backups') }}</h3>
            <!-- Delete Selected button (local storage only) -->
            <button
              v-if="!isViewingRemoteStorage() && selectedBackupIds.length > 0"
              @click="confirmBatchDelete"
              class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
            >
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
              {{ $t('backup.deleteSelected', { count: selectedBackupIds.length }) }}
            </button>
          </div>
          <!-- Storage selector for remote backups -->
          <div v-if="storageRemotes.length > 0" class="flex items-center space-x-2">
            <select
              v-model="selectedStorageSource"
              @change="onStorageSourceChange"
              class="block w-48 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
            >
              <option value="local">{{ $t('backup.localStorage') }}</option>
              <option v-for="remote in storageRemotes" :key="remote.id" :value="remote.id">
                {{ remote.display_name }}
              </option>
            </select>
          </div>
        </div>

        <!-- Loading state for remote backups -->
        <div v-if="isLoadingRemoteBackups" class="text-center py-12">
          <svg class="animate-spin mx-auto h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.loadingRemoteBackups') }}</p>
        </div>

        <div v-else-if="backups.length === 0" class="text-center py-12">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.noBackups') }}</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.noBackupsDesc') }}</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <!-- Checkbox column for batch selection (local storage only) -->
                <th v-if="!isViewingRemoteStorage()" class="px-4 py-3 w-10">
                  <input
                    type="checkbox"
                    :checked="allBackupsSelected"
                    :indeterminate="someBackupsSelected && !allBackupsSelected"
                    @change="toggleSelectAllBackups"
                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                  />
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ $t('backup.backupName') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ $t('backup.backupType') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ $t('backup.status') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ $t('backup.size') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ $t('backup.completedAt') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ $t('backup.storage') }}</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="backup in backups" :key="backup.id" :class="{ 'bg-primary-50 dark:bg-primary-900/20': selectedBackupIds.includes(backup.id) }">
                <!-- Checkbox column for batch selection (local storage only) -->
                <td v-if="!isViewingRemoteStorage()" class="px-4 py-4 w-10">
                  <input
                    type="checkbox"
                    :checked="selectedBackupIds.includes(backup.id)"
                    @change="toggleBackupSelection(backup.id)"
                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                  />
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  <div>
                    {{ backup.display_name || backup.backup_config?.name || '-' }}
                    <div v-if="backup.archive_name" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                      <span class="font-mono">{{ backup.datetime || backup.archive_name }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ getBackupTypeLabel(backup.type) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getStatusClass(backup.status)">
                    {{ getStatusLabel(backup.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ backup.size_formatted || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ backup.completed_at ? formatDate(backup.completed_at) : (backup.modified_at ? formatDate(backup.modified_at) : '-') }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm">
                  <div class="flex flex-wrap gap-1">
                    <!-- For remote viewing: show single remote badge -->
                    <template v-if="isViewingRemoteStorage() && backup.storage_remote">
                      <span
                        :class="getStorageBadgeClass(backup.storage_remote.type)"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                      >
                        <svg v-if="['ftp', 'sftp'].includes(backup.storage_remote.type)" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                        </svg>
                        <svg v-else-if="['drive', 'onedrive', 'dropbox'].includes(backup.storage_remote.type)" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                        </svg>
                        <svg v-else class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        {{ backup.storage_remote.display_name }}
                      </span>
                      <!-- Show indicator if also exists locally -->
                      <span
                        v-if="backup.has_local_record"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"
                        :title="$t('backup.alsoOnLocal')"
                      >
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                        Local
                      </span>
                    </template>
                    <!-- For local viewing: show all storage locations -->
                    <template v-else>
                      <!-- Local Storage badge if configured -->
                      <span
                        v-if="hasLocalStorage(backup)"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"
                      >
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                        Local
                      </span>
                      <!-- Synced remotes badges -->
                      <span
                        v-for="remote in getSyncedRemotesInfo(backup)"
                        :key="remote.id"
                        :class="getStorageBadgeClass(remote.type)"
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                      >
                        <!-- FTP/SFTP icon -->
                        <svg v-if="['ftp', 'sftp'].includes(remote.type)" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                        </svg>
                        <!-- Cloud icon for Drive/OneDrive/Dropbox -->
                        <svg v-else-if="['drive', 'onedrive', 'dropbox'].includes(remote.type)" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                        </svg>
                        <!-- S3/B2 icon -->
                        <svg v-else-if="['s3', 'b2'].includes(remote.type)" class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <!-- WebDAV icon -->
                        <svg v-else class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                        {{ remote.display_name }}
                      </span>
                    </template>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <!-- For local backups -->
                  <template v-if="!isViewingRemoteStorage()">
                    <router-link
                      v-if="backup.status === 'completed'"
                      :to="`/backup/${backup.id}/restore`"
                      class="text-primary-600 hover:text-primary-900 dark:hover:text-primary-400 mr-3"
                    >
                      {{ $t('backup.restore') }}
                    </router-link>
                    <button
                      v-if="backup.status === 'completed'"
                      @click="browseBackup(backup)"
                      class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300 mr-3"
                    >
                      {{ $t('backup.browse') }}
                    </button>
                    <button
                      @click="confirmDeleteBackup(backup)"
                      class="text-red-600 hover:text-red-900"
                    >
                      {{ $t('common.delete') }}
                    </button>
                  </template>
                  <!-- For remote backups -->
                  <template v-else>
                    <router-link
                      v-if="backup.has_local_record && backup.local_backup_id"
                      :to="`/backup/${backup.local_backup_id}/restore`"
                      class="text-primary-600 hover:text-primary-900 dark:hover:text-primary-400 mr-3"
                    >
                      {{ $t('backup.restore') }}
                    </router-link>
                    <span
                      v-else
                      class="text-gray-400 dark:text-gray-500 mr-3 cursor-not-allowed"
                      :title="$t('backup.syncToLocalFirst')"
                    >
                      {{ $t('backup.restore') }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-2">
                      {{ backup.file_count || 0 }} {{ $t('backup.files') }}
                    </span>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Configurations Tab -->
    <div v-if="activeTab === 'configs'">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.configs') }}</h3>
          <router-link
            to="/backup/config/new"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
          >
            <svg class="w-5 h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            {{ $t('backup.addConfig') }}
          </router-link>
        </div>

        <div v-if="configs.length === 0" class="text-center py-12">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.noConfigs') }}</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.noConfigsDesc') }}</p>
        </div>

        <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
          <div v-for="config in configs" :key="config.id" class="p-4">
            <div class="flex items-center justify-between">
              <div class="flex-1 min-w-0">
                <div class="flex items-center">
                  <span class="font-medium text-gray-900 dark:text-white">{{ config.name }}</span>
                  <span
                    :class="[
                      config.is_active
                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                      'ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium'
                    ]"
                  >
                    {{ config.is_active ? $t('common.active') : $t('common.inactive') }}
                  </span>
                </div>
                <div class="mt-1 flex items-center text-sm text-gray-500 dark:text-gray-400 space-x-4">
                  <span>{{ getBackupTypeLabel(config.type) }}</span>
                  <span>{{ getDestinationsLabels(config) }}</span>
                  <span v-if="config.schedule">{{ getScheduleLabel(config.schedule) }}</span>
                </div>
                <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                  <span v-if="config.last_run_at">{{ $t('backup.lastRun') }}: {{ formatDate(config.last_run_at) }}</span>
                  <span v-else>{{ $t('backup.lastRun') }}: {{ $t('backup.neverRun') }}</span>
                  <span class="mx-2">|</span>
                  <span v-if="config.next_run_at">{{ $t('backup.nextRun') }}: {{ formatDate(config.next_run_at) }}</span>
                  <span v-else>{{ $t('backup.nextRun') }}: {{ $t('backup.notScheduled') }}</span>
                </div>
              </div>
              <div class="flex items-center space-x-2">
                <button
                  @click="runBackupNow(config)"
                  class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                  :disabled="isCreatingBackup"
                >
                  <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  {{ $t('backup.runNow') }}
                </button>
                <button
                  @click="toggleConfig(config)"
                  class="p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                  :title="config.is_active ? $t('common.inactive') : $t('common.active')"
                >
                  <svg v-if="config.is_active" class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  <svg v-else class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                </button>
                <router-link
                  :to="`/backup/config/${config.id}/edit`"
                  class="p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </router-link>
                <button
                  @click="confirmDeleteConfig(config)"
                  class="p-2 text-red-400 hover:text-red-500"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Storage Remotes Tab -->
    <div v-if="activeTab === 'remotes'">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.storageRemotes') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.storageRemotesDesc') }}</p>
          </div>
          <button
            @click="openRemoteModal()"
            :disabled="!rcloneStatus.installed"
            class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg class="w-5 h-5 mr-2 -ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            {{ $t('backup.addRemote') }}
          </button>
        </div>

        <!-- Rclone Not Installed Warning -->
        <div v-if="!rcloneStatus.installed" class="p-6 text-center">
          <svg class="mx-auto h-12 w-12 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.rcloneNotInstalled') }}</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.rcloneNotInstalledDesc') }}</p>
          <button
            @click="installRclone"
            :disabled="isInstallingRclone"
            class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50"
          >
            <svg v-if="isInstallingRclone" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isInstallingRclone ? $t('security.installing') : $t('backup.installRclone') }}
          </button>
        </div>

        <!-- Remotes List -->
        <div v-else-if="storageRemotes.length === 0" class="text-center py-12">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.noRemotes') }}</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.noRemotesDesc') }}</p>
        </div>

        <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
          <div v-for="remote in storageRemotes" :key="remote.id" class="p-4">
            <div class="flex items-center justify-between">
              <div class="flex-1 min-w-0">
                <div class="flex items-center">
                  <span class="font-medium text-gray-900 dark:text-white">{{ remote.display_name }}</span>
                  <span
                    :class="[
                      remote.is_active
                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                        : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                      'ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium'
                    ]"
                  >
                    {{ remote.type_label }}
                  </span>
                  <span v-if="remote.last_test_result === true" class="ml-2 text-green-500">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                  </span>
                  <span v-else-if="remote.last_test_result === false" class="ml-2 text-red-500">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                  </span>
                </div>
                <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                  {{ $t('backup.remoteName') }}: {{ remote.name }}
                </div>
              </div>
              <div class="flex items-center space-x-2">
                <button
                  @click="testRemoteConnection(remote)"
                  :disabled="isTestingConnection"
                  class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600 disabled:opacity-50"
                >
                  {{ $t('backup.testConnection') }}
                </button>
                <button
                  @click="openRemoteModal(remote)"
                  class="p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
                <button
                  @click="confirmDeleteRemote(remote)"
                  class="p-2 text-red-400 hover:text-red-500"
                >
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Remote Modal -->
    <Teleport to="body">
      <div v-if="showRemoteModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showRemoteModal = false"></div>

          <div class="relative bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form @submit.prevent="saveRemote">
              <div class="px-4 pt-5 pb-4 sm:p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                  {{ editingRemote ? $t('backup.editRemote') : $t('backup.addRemote') }}
                </h3>

                <div class="space-y-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.remoteName') }}</label>
                    <input
                      v-model="remoteForm.name"
                      type="text"
                      :placeholder="$t('backup.remoteNameHint')"
                      :disabled="!!editingRemote"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm disabled:opacity-50"
                      required
                    />
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.remoteDisplayName') }}</label>
                    <input
                      v-model="remoteForm.display_name"
                      type="text"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                      required
                    />
                  </div>

                  <div v-if="!editingRemote">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.remoteType') }}</label>
                    <select
                      v-model="remoteForm.type"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                      required
                    >
                      <option value="ftp">FTP</option>
                      <option value="sftp">SFTP</option>
                      <option value="drive">Google Drive</option>
                      <option value="onedrive">OneDrive</option>
                      <option value="dropbox">Dropbox</option>
                      <option value="s3">Amazon S3</option>
                      <option value="b2">Backblaze B2</option>
                      <option value="webdav">WebDAV</option>
                    </select>
                  </div>

                  <!-- FTP/SFTP Config -->
                  <div v-if="['ftp', 'sftp'].includes(remoteForm.type)" class="space-y-3">
                    <div class="grid grid-cols-3 gap-3">
                      <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.host') }}</label>
                        <input v-model="remoteForm.config.host" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm" required />
                      </div>
                      <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.port') }}</label>
                        <input v-model.number="remoteForm.config.port" type="number" :placeholder="remoteForm.type === 'sftp' ? '22' : '21'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm" />
                      </div>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.user') }}</label>
                      <input v-model="remoteForm.config.user" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm" required />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.pass') }}</label>
                      <input v-model="remoteForm.config.pass" type="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm" :required="!editingRemote" />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.remotePath') }}</label>
                      <input v-model="remoteForm.config.path" type="text" :placeholder="$t('backup.remotePathPlaceholder')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm" />
                    </div>
                  </div>

                  <!-- S3 Config -->
                  <div v-if="remoteForm.type === 's3'" class="space-y-3">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Access Key ID</label>
                      <input v-model="remoteForm.config.access_key_id" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm" required />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Secret Access Key</label>
                      <input v-model="remoteForm.config.secret_access_key" type="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm" :required="!editingRemote" />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Region</label>
                      <input v-model="remoteForm.config.region" type="text" placeholder="us-east-1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm" />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.remotePath') }}</label>
                      <input v-model="remoteForm.config.path" type="text" :placeholder="$t('backup.remotePathPlaceholder')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm" />
                    </div>
                  </div>

                  <!-- OAuth for Drive/OneDrive/Dropbox -->
                  <div v-if="['drive', 'onedrive', 'dropbox'].includes(remoteForm.type)" class="space-y-4">
                    <div class="p-4 bg-blue-50 dark:bg-blue-900 rounded-md">
                      <p class="text-sm text-blue-800 dark:text-blue-200">
                        {{ $t('backup.oauthDescription') }}
                      </p>
                    </div>
                    <!-- Backup folder path for cloud storage -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.cloudBackupFolder') }}</label>
                      <input
                        v-model="remoteForm.config.path"
                        type="text"
                        :placeholder="$t('backup.cloudBackupFolderPlaceholder')"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                      />
                      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('backup.cloudBackupFolderHint') }}</p>
                    </div>
                    <button
                      type="button"
                      @click="initiateOAuth"
                      :disabled="isInitiatingOAuth || !remoteForm.name || !remoteForm.display_name"
                      class="w-full inline-flex justify-center items-center px-4 py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      <svg v-if="isInitiatingOAuth" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                      </svg>
                      {{ getOAuthButtonLabel(remoteForm.type) }}
                    </button>
                  </div>
                </div>
              </div>

              <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button
                  type="submit"
                  :disabled="isSavingRemote"
                  class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                >
                  {{ isSavingRemote ? $t('common.loading') : $t('common.save') }}
                </button>
                <button
                  type="button"
                  @click="showRemoteModal = false"
                  class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500"
                >
                  {{ $t('common.cancel') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Browse Files Modal -->
    <Teleport to="body">
      <div v-if="showBrowseModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showBrowseModal = false"></div>

          <div class="relative bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-4xl sm:w-full max-h-[80vh] flex flex-col">
            <div class="px-4 pt-5 pb-4 sm:p-6 border-b border-gray-200 dark:border-gray-700">
              <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.browseFiles') }}</h3>
                <button @click="showBrowseModal = false" class="text-gray-400 hover:text-gray-500">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              <!-- Breadcrumb -->
              <div class="mt-3 flex items-center text-sm text-gray-500 dark:text-gray-400">
                <button @click="browseToPath('/')" class="hover:text-primary-600">{{ $t('backup.root') }}</button>
                <template v-for="(segment, index) in browsePathSegments" :key="index">
                  <svg class="w-4 h-4 mx-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                  </svg>
                  <button @click="browseToPath(getPathUpTo(index))" class="hover:text-primary-600">{{ segment }}</button>
                </template>
              </div>
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-6">
              <div v-if="isBrowsingFiles" class="flex items-center justify-center py-12">
                <svg class="animate-spin h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>
              <div v-else-if="browseFiles.length === 0" class="text-center py-12 text-gray-500 dark:text-gray-400">
                {{ $t('backup.noFilesFound') }}
              </div>
              <div v-else class="space-y-1">
                <div
                  v-for="file in browseFiles"
                  :key="file.path"
                  @click="file.type === 'dir' ? browseToPath(file.path) : null"
                  :class="[
                    file.type === 'dir' ? 'cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700' : '',
                    selectedBrowseFiles.includes(file.path) ? 'bg-primary-50 dark:bg-primary-900' : '',
                    'flex items-center p-2 rounded-md'
                  ]"
                >
                  <input
                    v-if="file.type !== 'dir'"
                    type="checkbox"
                    :value="file.path"
                    v-model="selectedBrowseFiles"
                    class="mr-3 h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                    @click.stop
                  />
                  <svg v-if="file.type === 'dir'" class="w-5 h-5 text-yellow-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" />
                  </svg>
                  <svg v-else class="w-5 h-5 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                  </svg>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm text-gray-900 dark:text-white truncate">{{ file.name }}</p>
                    <p v-if="file.type !== 'dir'" class="text-xs text-gray-500 dark:text-gray-400">{{ formatFileSize(file.size) }}</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 flex justify-between items-center">
              <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ selectedBrowseFiles.length }} {{ $t('backup.filesSelected') }}
              </span>
              <div class="flex space-x-3">
                <button
                  type="button"
                  @click="restoreSelectedFiles"
                  :disabled="selectedBrowseFiles.length === 0"
                  class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:text-sm disabled:opacity-50"
                >
                  {{ $t('backup.restoreSelected') }}
                </button>
                <button
                  type="button"
                  @click="showBrowseModal = false"
                  class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:text-sm dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500"
                >
                  {{ $t('common.close') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Restore Modal -->
    <Teleport to="body">
      <div v-if="showRestoreModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showRestoreModal = false"></div>

          <div class="relative bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-3xl sm:w-full max-h-[90vh] flex flex-col">
            <form @submit.prevent="restoreBackup" class="flex flex-col h-full">
              <div class="px-4 pt-5 pb-4 sm:p-6 overflow-y-auto flex-1">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('backup.restoreBackup') }}</h3>

                <!-- Backup Info -->
                <div v-if="selectedBackup" class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                  <div class="flex items-center justify-between">
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-white">{{ selectedBackup.backup_config?.name }}</p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(selectedBackup.completed_at) }}</p>
                    </div>
                    <span class="text-sm text-gray-600 dark:text-gray-300">{{ selectedBackup.size_formatted }}</span>
                  </div>
                </div>

                <div class="space-y-6">
                  <!-- Restore Mode -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ $t('backup.restoreMode') }}</label>
                    <div class="grid grid-cols-2 gap-4">
                      <label
                        :class="[
                          restoreForm.mode === 'full'
                            ? 'border-primary-500 ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900'
                            : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                          'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none'
                        ]"
                      >
                        <input type="radio" v-model="restoreForm.mode" value="full" class="sr-only" />
                        <div class="flex flex-col">
                          <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.restoreFull') }}</span>
                          <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('backup.restoreFullDesc') }}</span>
                        </div>
                      </label>
                      <label
                        :class="[
                          restoreForm.mode === 'selective'
                            ? 'border-primary-500 ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900'
                            : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                          'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none'
                        ]"
                      >
                        <input type="radio" v-model="restoreForm.mode" value="selective" class="sr-only" />
                        <div class="flex flex-col">
                          <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.restoreSelective') }}</span>
                          <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('backup.restoreSelectiveDesc') }}</span>
                        </div>
                      </label>
                    </div>
                  </div>

                  <!-- Selective Options - Object Type Selector -->
                  <div v-if="restoreForm.mode === 'selective'" class="space-y-4">
                    <!-- Object Type -->
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('backup.objectType') }}</label>
                      <select
                        v-model="restoreForm.objectType"
                        @change="loadBackupObjects"
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                      >
                        <option value="websites">{{ $t('backup.websites') }}</option>
                        <option value="databases">{{ $t('backup.databases') }}</option>
                        <option value="emails">{{ $t('backup.emails') }}</option>
                        <option value="config">{{ $t('backup.config') }}</option>
                      </select>
                    </div>

                    <!-- Dual-pane selector -->
                    <div class="grid grid-cols-2 gap-4">
                      <!-- Available Objects -->
                      <div class="border border-gray-300 dark:border-gray-600 rounded-lg">
                        <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border-b border-gray-300 dark:border-gray-600 rounded-t-lg">
                          <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.available') }}</span>
                            <input
                              v-model="availableSearch"
                              type="text"
                              :placeholder="$t('common.search')"
                              class="w-32 text-xs rounded border-gray-300 dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                            />
                          </div>
                        </div>
                        <div class="h-48 overflow-y-auto p-2">
                          <div v-if="isLoadingObjects" class="flex items-center justify-center h-full">
                            <svg class="animate-spin h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24">
                              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                          </div>
                          <div v-else-if="filteredAvailableObjects.length === 0" class="text-center text-sm text-gray-500 dark:text-gray-400 py-4">
                            {{ $t('backup.noObjectsFound') }}
                          </div>
                          <div v-else class="space-y-1">
                            <label
                              v-for="obj in filteredAvailableObjects"
                              :key="obj.path"
                              class="flex items-center p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                            >
                              <input
                                type="checkbox"
                                :value="obj.path"
                                v-model="restoreForm.selectedObjects"
                                class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                              />
                              <span class="ml-2 text-sm text-gray-900 dark:text-white truncate">{{ obj.name }}</span>
                            </label>
                          </div>
                        </div>
                        <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border-t border-gray-300 dark:border-gray-600 rounded-b-lg">
                          <button
                            type="button"
                            @click="selectAllAvailable"
                            class="text-xs text-primary-600 hover:text-primary-800"
                          >
                            {{ $t('backup.selectAll') }}
                          </button>
                        </div>
                      </div>

                      <!-- Selected Objects -->
                      <div class="border border-gray-300 dark:border-gray-600 rounded-lg">
                        <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border-b border-gray-300 dark:border-gray-600 rounded-t-lg">
                          <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.selected') }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ restoreForm.selectedObjects.length }} {{ $t('backup.itemsSelected') }}</span>
                          </div>
                        </div>
                        <div class="h-48 overflow-y-auto p-2">
                          <div v-if="restoreForm.selectedObjects.length === 0" class="text-center text-sm text-gray-500 dark:text-gray-400 py-4">
                            {{ $t('backup.noObjectsSelected') }}
                          </div>
                          <div v-else class="space-y-1">
                            <div
                              v-for="path in restoreForm.selectedObjects"
                              :key="path"
                              class="flex items-center justify-between p-2 rounded bg-primary-50 dark:bg-primary-900"
                            >
                              <span class="text-sm text-gray-900 dark:text-white truncate">{{ getObjectName(path) }}</span>
                              <button
                                type="button"
                                @click="removeSelectedObject(path)"
                                class="text-red-500 hover:text-red-700"
                              >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                              </button>
                            </div>
                          </div>
                        </div>
                        <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border-t border-gray-300 dark:border-gray-600 rounded-b-lg">
                          <button
                            type="button"
                            @click="clearAllSelected"
                            class="text-xs text-red-600 hover:text-red-800"
                          >
                            {{ $t('backup.clearAll') }}
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Target Path with Presets -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('backup.targetPath') }}</label>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                      <label
                        :class="[
                          restoreForm.target_path === '/'
                            ? 'border-primary-500 ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900'
                            : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                          'relative flex cursor-pointer rounded-lg border p-3 focus:outline-none'
                        ]"
                      >
                        <input type="radio" v-model="restoreForm.target_path" value="/" class="sr-only" />
                        <div class="flex items-center">
                          <svg class="w-5 h-5 text-orange-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                          </svg>
                          <div>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.restoreOriginal') }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">/</span>
                          </div>
                        </div>
                      </label>
                      <label
                        :class="[
                          restoreForm.target_path === '/tmp/restore'
                            ? 'border-primary-500 ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900'
                            : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                          'relative flex cursor-pointer rounded-lg border p-3 focus:outline-none'
                        ]"
                      >
                        <input type="radio" v-model="restoreForm.target_path" value="/tmp/restore" class="sr-only" />
                        <div class="flex items-center">
                          <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                          </svg>
                          <div>
                            <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.restoreSafe') }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">/tmp/restore</span>
                          </div>
                        </div>
                      </label>
                    </div>
                    <div class="flex items-center">
                      <input
                        v-model="restoreForm.target_path"
                        type="text"
                        :placeholder="$t('backup.targetPathPlaceholder')"
                        class="flex-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
                        required
                      />
                    </div>
                  </div>

                  <!-- Warning based on target path -->
                  <div v-if="restoreForm.target_path === '/'" class="p-4 bg-red-50 dark:bg-red-900 rounded-lg">
                    <div class="flex">
                      <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                      </svg>
                      <div class="ml-3">
                        <p class="text-sm text-red-700 dark:text-red-200 font-medium">{{ $t('backup.restoreWarningOverwrite') }}</p>
                      </div>
                    </div>
                  </div>
                  <div v-else class="p-4 bg-blue-50 dark:bg-blue-900 rounded-lg">
                    <div class="flex">
                      <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                      </svg>
                      <div class="ml-3">
                        <p class="text-sm text-blue-700 dark:text-blue-200">{{ $t('backup.restoreInfoSafe') }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse border-t border-gray-200 dark:border-gray-600">
                <button
                  type="submit"
                  :disabled="isRestoring || (restoreForm.mode === 'selective' && restoreForm.selectedObjects.length === 0)"
                  class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                >
                  {{ isRestoring ? $t('common.loading') : $t('backup.startRestore') }}
                </button>
                <button
                  type="button"
                  @click="showRestoreModal = false"
                  class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500"
                >
                  {{ $t('common.cancel') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirmation Modal -->
    <Teleport to="body">
      <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showDeleteModal = false"></div>

          <div class="relative bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <div class="px-4 pt-5 pb-4 sm:p-6">
              <div class="sm:flex sm:items-start">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                  <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                  <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    {{ deleteTarget?.type === 'config' ? $t('backup.deleteConfig') : deleteTarget?.type === 'remote' ? $t('backup.deleteRemote') : $t('backup.deleteBackup') }}
                  </h3>
                  <div class="mt-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      {{ deleteTarget?.type === 'config' ? $t('backup.deleteConfigConfirm') : deleteTarget?.type === 'remote' ? $t('confirmDialog.deleteMessage') : $t('backup.deleteBackupConfirm') }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="button"
                @click="executeDelete"
                :disabled="isDeleting"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
              >
                {{ isDeleting ? $t('common.loading') : $t('common.delete') }}
              </button>
              <button
                type="button"
                @click="showDeleteModal = false"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500"
              >
                {{ $t('common.cancel') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Batch Delete Confirmation Modal -->
      <div v-if="showBatchDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
          <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="showBatchDeleteModal = false"></div>

          <div class="relative bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <div class="px-4 pt-5 pb-4 sm:p-6">
              <div class="sm:flex sm:items-start">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10">
                  <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                  <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    {{ $t('backup.batchDeleteTitle', { count: selectedBackupIds.length }) }}
                  </h3>
                  <div class="mt-2">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                      {{ $t('backup.batchDeleteConfirm', { count: selectedBackupIds.length }) }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
              <button
                type="button"
                @click="executeBatchDelete"
                :disabled="isDeletingBatch"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
              >
                {{ isDeletingBatch ? $t('common.loading') : $t('common.delete') }}
              </button>
              <button
                type="button"
                @click="showBatchDeleteModal = false"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:w-auto sm:text-sm dark:bg-gray-600 dark:text-gray-200 dark:border-gray-500 dark:hover:bg-gray-500"
              >
                {{ $t('common.cancel') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import api from '@/utils/api'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const appStore = useAppStore()

const showToast = (message, type = 'info') => {
  appStore.showToast({ type, message })
}

const activeTab = ref('backups')
const configs = ref([])
const backups = ref([])
const selectedBackupIds = ref([])

// Computed properties for batch selection
const allBackupsSelected = computed(() => {
  return backups.value.length > 0 && selectedBackupIds.value.length === backups.value.length
})

const someBackupsSelected = computed(() => {
  return selectedBackupIds.value.length > 0 && selectedBackupIds.value.length < backups.value.length
})

const stats = ref({
  total_backups: 0,
  completed_backups: 0,
  failed_backups: 0,
  running_backups: 0,
  total_size: 0,
  total_size_formatted: '0 B',
  configs_count: 0,
  active_configs: 0
})

const showRestoreModal = ref(false)
const showDeleteModal = ref(false)
const showBrowseModal = ref(false)
const selectedBackup = ref(null)
const deleteTarget = ref(null)

const isRestoring = ref(false)
const isDeleting = ref(false)
const isCreatingBackup = ref(false)
const isBrowsingFiles = ref(false)

// Browse files state
const browsePath = ref('/')
const browseFiles = ref([])
const selectedBrowseFiles = ref([])

// Storage Remotes state
const storageRemotes = ref([])
const rcloneStatus = ref({ installed: false, version: null })
const showRemoteModal = ref(false)
const editingRemote = ref(null)
const isSavingRemote = ref(false)
const isInstallingRclone = ref(false)
const isTestingConnection = ref(false)

// Remote backups viewing state
const selectedStorageSource = ref('local')
const isLoadingRemoteBackups = ref(false)

// Active backup tasks state
const activeBackupTasks = ref([])
const taskPollingInterval = ref(null)

const defaultRemoteForm = {
  name: '',
  display_name: '',
  type: 'ftp',
  config: {
    host: '',
    port: null,
    user: '',
    pass: '',
    path: '/backups'
  }
}
const remoteForm = ref({ ...defaultRemoteForm })
const isInitiatingOAuth = ref(false)
const oauthWindow = ref(null)

const restoreForm = ref({
  target_path: '',
  include_paths: [],
  mode: 'full', // 'full' or 'selective'
  items: [] // ['files', 'databases', 'emails', 'config']
})

const fetchConfigs = async () => {
  try {
    const response = await api.get('/backup-configs')
    if (response.data.success) {
      configs.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch configs:', error)
  }
}

const fetchBackups = async (storageRemoteId = null) => {
  try {
    // Clear selection when refreshing
    selectedBackupIds.value = []

    const params = {}
    if (storageRemoteId && storageRemoteId !== 'local') {
      params.storage_remote_id = storageRemoteId
    }
    const response = await api.get('/backups', { params })
    if (response.data.success) {
      backups.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch backups:', error)
  }
}

const onStorageSourceChange = async () => {
  isLoadingRemoteBackups.value = true
  try {
    if (selectedStorageSource.value === 'local') {
      // Fetch local backups from database
      await fetchBackups()
    } else {
      // Fetch backups from remote storage
      await fetchRemoteBackups(selectedStorageSource.value)
    }
  } finally {
    isLoadingRemoteBackups.value = false
  }
}

// Fetch backups from remote storage
const fetchRemoteBackups = async (remoteId) => {
  try {
    const response = await api.get(`/storage-remotes/${remoteId}/backups`)
    if (response.data.success) {
      backups.value = response.data.data || []
    }
  } catch (error) {
    console.error('Failed to fetch remote backups:', error)
    showToast(error.response?.data?.error?.message || t('backup.fetchRemoteBackupsFailed'), 'error')
    backups.value = []
  }
}

const fetchStats = async () => {
  try {
    const response = await api.get('/backups/stats')
    if (response.data.success) {
      stats.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
}

const toggleConfig = async (config) => {
  try {
    const response = await api.post(`/backup-configs/${config.id}/toggle`)
    if (response.data.success) {
      showToast(response.data.message, 'success')
      fetchConfigs()
      fetchStats()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  }
}

const runBackupNow = async (config) => {
  isCreatingBackup.value = true
  try {
    const response = await api.post('/backups', {
      backup_config_id: config.id
    })
    if (response.data.success) {
      showToast(t('backup.backupStarted'), 'success')
      fetchBackups()
      fetchStats()

      // Start task polling if task_id is returned
      if (response.data.task_id) {
        fetchActiveBackupTasks()
        startTaskPolling()
      }
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isCreatingBackup.value = false
  }
}

// Fetch active backup tasks
const fetchActiveBackupTasks = async () => {
  try {
    const response = await api.get('/tasks', {
      params: {
        status: 'running,pending',
        type: 'backup.create,backup.restore'
      }
    })
    if (response.data.success) {
      activeBackupTasks.value = response.data.data || []

      // Stop polling if no active tasks
      if (activeBackupTasks.value.length === 0) {
        stopTaskPolling()
        fetchBackups()
        fetchStats()
      }
    }
  } catch (error) {
    console.error('Failed to fetch active backup tasks:', error)
  }
}

// Start polling for active tasks
const startTaskPolling = () => {
  if (taskPollingInterval.value) return

  taskPollingInterval.value = setInterval(() => {
    fetchActiveBackupTasks()
  }, 3000) // Poll every 3 seconds
}

// Stop polling
const stopTaskPolling = () => {
  if (taskPollingInterval.value) {
    clearInterval(taskPollingInterval.value)
    taskPollingInterval.value = null
  }
}

// Get last N lines from task output
const getLastOutputLines = (output, n = 3) => {
  if (!output) return ''
  const lines = output.trim().split('\n')
  return lines.slice(-n).join('\n')
}

const confirmDeleteConfig = (config) => {
  deleteTarget.value = { type: 'config', item: config }
  showDeleteModal.value = true
}

const confirmDeleteBackup = (backup) => {
  deleteTarget.value = { type: 'backup', item: backup }
  showDeleteModal.value = true
}

const executeDelete = async () => {
  isDeleting.value = true
  try {
    let endpoint, successMessage
    switch (deleteTarget.value.type) {
      case 'config':
        endpoint = `/backup-configs/${deleteTarget.value.item.id}`
        successMessage = t('backup.configDeleted')
        break
      case 'backup':
        endpoint = `/backups/${deleteTarget.value.item.id}`
        successMessage = t('backup.backupDeleted')
        break
      case 'remote':
        endpoint = `/storage-remotes/${deleteTarget.value.item.id}`
        successMessage = t('backup.remoteDeleted')
        break
    }

    const response = await api.delete(endpoint)
    if (response.data.success) {
      showToast(successMessage, 'success')
    } else {
      showToast(response.data.error?.message || t('common.error'), 'error')
    }
    showDeleteModal.value = false
    if (deleteTarget.value.type === 'config') {
      fetchConfigs()
    } else if (deleteTarget.value.type === 'backup') {
      fetchBackups()
    } else if (deleteTarget.value.type === 'remote') {
      fetchStorageRemotes()
    }
    fetchStats()
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
    showDeleteModal.value = false
  } finally {
    isDeleting.value = false
  }
}

// Batch selection methods
const toggleBackupSelection = (backupId) => {
  const index = selectedBackupIds.value.indexOf(backupId)
  if (index === -1) {
    selectedBackupIds.value.push(backupId)
  } else {
    selectedBackupIds.value.splice(index, 1)
  }
}

const toggleSelectAllBackups = () => {
  if (allBackupsSelected.value) {
    selectedBackupIds.value = []
  } else {
    selectedBackupIds.value = backups.value.map(b => b.id)
  }
}

const showBatchDeleteModal = ref(false)
const isDeletingBatch = ref(false)

const confirmBatchDelete = () => {
  if (selectedBackupIds.value.length === 0) return
  showBatchDeleteModal.value = true
}

const executeBatchDelete = async () => {
  if (selectedBackupIds.value.length === 0) return

  isDeletingBatch.value = true
  try {
    const response = await api.delete('/backups/batch', {
      data: { ids: selectedBackupIds.value }
    })
    if (response.data.success) {
      const deleted = response.data.deleted || selectedBackupIds.value.length
      showToast(t('backup.batchDeleted', { count: deleted }), 'success')
      if (response.data.failed > 0) {
        showToast(`${response.data.failed} backup(s) failed to delete`, 'warning')
      }
    } else {
      showToast(response.data.error?.message || t('common.error'), 'error')
    }
    selectedBackupIds.value = []
    showBatchDeleteModal.value = false
    fetchBackups()
    fetchStats()
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
    showBatchDeleteModal.value = false
  } finally {
    isDeletingBatch.value = false
  }
}

const openRestoreModal = (backup) => {
  selectedBackup.value = backup
  restoreForm.value = {
    target_path: '/tmp/restore',
    include_paths: [],
    mode: 'full',
    items: []
  }
  showRestoreModal.value = true
}

const restoreBackup = async () => {
  isRestoring.value = true
  try {
    // Build include paths based on mode
    let includePaths = []
    if (restoreForm.value.mode === 'selective') {
      const itemPaths = {
        files: ['/home', '/var/www'],
        databases: ['/var/lib/mysql'],
        emails: ['/var/mail', '/var/vmail'],
        config: ['/etc/nginx', '/etc/apache2', '/etc/postfix', '/etc/dovecot', '/etc/vsispanel']
      }
      restoreForm.value.items.forEach(item => {
        if (itemPaths[item]) {
          includePaths.push(...itemPaths[item])
        }
      })
    }

    const response = await api.post(`/backups/${selectedBackup.value.id}/restore`, {
      target_path: restoreForm.value.target_path,
      include_paths: includePaths
    })
    if (response.data.success) {
      showToast(t('backup.restoreStarted'), 'success')
      showRestoreModal.value = false
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isRestoring.value = false
  }
}

const browseBackup = async (backup) => {
  selectedBackup.value = backup
  browsePath.value = '/'
  browseFiles.value = []
  selectedBrowseFiles.value = []
  showBrowseModal.value = true
  await fetchBrowseFiles()
}

const fetchBrowseFiles = async () => {
  isBrowsingFiles.value = true
  try {
    const response = await api.get(`/backups/${selectedBackup.value.id}/browse`, {
      params: { path: browsePath.value }
    })
    if (response.data.success) {
      browseFiles.value = response.data.data || []
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isBrowsingFiles.value = false
  }
}

const browseToPath = async (path) => {
  browsePath.value = path
  await fetchBrowseFiles()
}

const browsePathSegments = computed(() => {
  if (browsePath.value === '/') return []
  return browsePath.value.split('/').filter(s => s)
})

const getPathUpTo = (index) => {
  const segments = browsePath.value.split('/').filter(s => s)
  return '/' + segments.slice(0, index + 1).join('/')
}

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  const factor = Math.floor(Math.log(bytes) / Math.log(1024))
  return (bytes / Math.pow(1024, factor)).toFixed(2) + ' ' + units[factor]
}

const restoreSelectedFiles = async () => {
  if (selectedBrowseFiles.value.length === 0) return

  isRestoring.value = true
  try {
    const response = await api.post(`/backups/${selectedBackup.value.id}/restore`, {
      target_path: '/tmp/restore',
      include_paths: selectedBrowseFiles.value
    })
    if (response.data.success) {
      showToast(t('backup.restoreStarted'), 'success')
      showBrowseModal.value = false
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isRestoring.value = false
  }
}

const getBackupTypeLabel = (type) => {
  const labels = {
    full: t('backup.typeFull'),
    files: t('backup.typeFiles'),
    databases: t('backup.typeDatabases'),
    emails: t('backup.typeEmails'),
    config: t('backup.typeConfig')
  }
  return labels[type] || type
}

const getDestinationLabel = (type) => {
  const labels = {
    local: t('backup.destinationLocal'),
    s3: t('backup.destinationS3'),
    ftp: t('backup.destinationFtp'),
    b2: t('backup.destinationB2'),
    rclone: t('backup.destinationRclone')
  }
  return labels[type] || type
}

const getDestinationsLabels = (config) => {
  const destinations = config.destinations || []
  if (destinations.length === 0) {
    // Fallback to destination_type for backward compatibility
    return getDestinationLabel(config.destination_type)
  }

  const labels = []
  for (const dest of destinations) {
    if (dest === 'local') {
      labels.push(t('backup.destinationLocal'))
    } else if (dest.startsWith('remote:')) {
      const remoteId = dest.replace('remote:', '')
      // Find the remote name from storageRemotes
      const remote = storageRemotes.value.find(r => r.id === remoteId)
      if (remote) {
        labels.push(remote.display_name)
      } else {
        labels.push(t('backup.destinationRclone'))
      }
    }
  }

  return labels.length > 0 ? labels.join(', ') : getDestinationLabel(config.destination_type)
}

const getScheduleLabel = (schedule) => {
  const labels = {
    hourly: t('backup.scheduleHourly'),
    daily: t('backup.scheduleDaily'),
    weekly: t('backup.scheduleWeekly'),
    monthly: t('backup.scheduleMonthly')
  }
  return labels[schedule] || schedule
}

const getStatusLabel = (status) => {
  const labels = {
    pending: t('backup.statusPending'),
    running: t('backup.statusRunning'),
    completed: t('backup.statusCompleted'),
    failed: t('backup.statusFailed'),
    remote: t('backup.statusRemote')
  }
  return labels[status] || status
}

const getStatusClass = (status) => {
  const classes = {
    pending: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    running: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    completed: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    failed: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    remote: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300'
  }
  return classes[status] || ''
}

// Check if viewing remote storage
const isViewingRemoteStorage = () => {
  return selectedStorageSource.value !== 'local'
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

// Storage display helpers
const hasLocalStorage = (backup) => {
  const config = backup.backup_config
  if (!config) return true // Default to local
  const destinations = config.destinations || ['local']
  return destinations.includes('local')
}

const getSyncedRemotesInfo = (backup) => {
  if (!backup.synced_remotes_info || backup.synced_remotes_info.length === 0) {
    // Fallback to storage_remote if no synced_remotes_info
    if (backup.storage_remote) {
      return [backup.storage_remote]
    }
    return []
  }
  return backup.synced_remotes_info
}

const getStorageBadgeClass = (type) => {
  const classes = {
    ftp: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    sftp: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    drive: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    onedrive: 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-300',
    dropbox: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
    s3: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    b2: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    webdav: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300'
  }
  return classes[type] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
}

// Storage Remotes methods
const fetchRcloneStatus = async () => {
  try {
    const response = await api.get('/storage-remotes/rclone-status')
    if (response.data.success) {
      rcloneStatus.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch rclone status:', error)
  }
}

const fetchStorageRemotes = async () => {
  try {
    const response = await api.get('/storage-remotes')
    if (response.data.success) {
      storageRemotes.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch storage remotes:', error)
  }
}

const installRclone = async () => {
  isInstallingRclone.value = true
  try {
    const response = await api.post('/storage-remotes/install-rclone')
    if (response.data.success) {
      showToast(t('backup.rcloneInstalled'), 'success')
      await fetchRcloneStatus()
    } else {
      showToast(response.data.error?.message || t('backup.rcloneInstallFailed'), 'error')
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('backup.rcloneInstallFailed'), 'error')
  } finally {
    isInstallingRclone.value = false
  }
}

const openRemoteModal = (remote = null) => {
  editingRemote.value = remote
  if (remote) {
    remoteForm.value = {
      name: remote.name,
      display_name: remote.display_name,
      type: remote.type,
      config: { ...defaultRemoteForm.config }
    }
  } else {
    remoteForm.value = JSON.parse(JSON.stringify(defaultRemoteForm))
  }
  showRemoteModal.value = true
}

const saveRemote = async () => {
  isSavingRemote.value = true
  try {
    let response
    if (editingRemote.value) {
      response = await api.put(`/storage-remotes/${editingRemote.value.id}`, {
        display_name: remoteForm.value.display_name,
        config: remoteForm.value.config
      })
    } else {
      response = await api.post('/storage-remotes', remoteForm.value)
    }

    if (response.data.success) {
      showToast(editingRemote.value ? t('backup.remoteUpdated') : t('backup.remoteCreated'), 'success')
      showRemoteModal.value = false
      fetchStorageRemotes()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isSavingRemote.value = false
  }
}

const testRemoteConnection = async (remote) => {
  isTestingConnection.value = true
  try {
    const response = await api.post(`/storage-remotes/${remote.id}/test`)
    if (response.data.success) {
      showToast(t('backup.connectionSuccessful'), 'success')
    } else {
      showToast(response.data.message || t('backup.connectionFailed'), 'error')
    }
    fetchStorageRemotes()
  } catch (error) {
    showToast(error.response?.data?.message || t('backup.connectionFailed'), 'error')
  } finally {
    isTestingConnection.value = false
  }
}

const confirmDeleteRemote = (remote) => {
  deleteTarget.value = { type: 'remote', item: remote }
  showDeleteModal.value = true
}

// OAuth methods
const getOAuthButtonLabel = (type) => {
  const labels = {
    drive: t('backup.connectGoogleDrive'),
    onedrive: t('backup.connectOneDrive'),
    dropbox: t('backup.connectDropbox')
  }
  return labels[type] || t('backup.connectCloud')
}

const initiateOAuth = async () => {
  if (!remoteForm.value.name || !remoteForm.value.display_name) {
    showToast(t('backup.oauthNameRequired'), 'error')
    return
  }

  isInitiatingOAuth.value = true
  try {
    // Map form type to OAuth provider
    const providerMap = {
      drive: 'google',
      onedrive: 'onedrive',
      dropbox: 'dropbox'
    }
    const provider = providerMap[remoteForm.value.type] || remoteForm.value.type

    const response = await api.post(`/storage-remotes/oauth/${provider}/authorize`, {
      remote_name: remoteForm.value.name,
      display_name: remoteForm.value.display_name
    })

    if (response.data.success) {
      const authUrl = response.data.data.auth_url

      // Open OAuth in a popup window
      const width = 600
      const height = 700
      const left = (window.screen.width - width) / 2
      const top = (window.screen.height - height) / 2

      oauthWindow.value = window.open(
        authUrl,
        'oauth_popup',
        `width=${width},height=${height},left=${left},top=${top},scrollbars=yes`
      )

      // Close the modal since OAuth flow started
      showRemoteModal.value = false
      showToast(t('backup.oauthWindowOpened'), 'info')
    }
  } catch (error) {
    const errorMessage = error.response?.data?.error?.message || t('backup.oauthInitFailed')
    showToast(errorMessage, 'error')
  } finally {
    isInitiatingOAuth.value = false
  }
}

// Listen for OAuth result from popup window
const handleOAuthMessage = (event) => {
  if (event.origin !== window.location.origin) return
  if (event.data?.type !== 'oauth_callback') return

  if (event.data.success) {
    showToast(t('backup.oauthSuccess'), 'success')
    fetchStorageRemotes()
    activeTab.value = 'remotes'
  } else if (event.data.error) {
    const errorMessages = {
      invalid_state: t('backup.oauthErrorInvalidState'),
      token_exchange_failed: t('backup.oauthErrorTokenExchange'),
      token_decrypt_failed: t('backup.oauthErrorTokenExchange'),
      user_denied: t('backup.oauthErrorUserDenied')
    }
    showToast(errorMessages[event.data.error] || event.data.error, 'error')
  }

  oauthWindow.value = null
}

// Handle OAuth callback from URL parameters
const handleOAuthCallback = () => {
  const oauthSuccess = route.query.oauth_success
  const oauthError = route.query.oauth_error
  const remoteId = route.query.remote_id
  const tab = route.query.tab

  // If we're inside the OAuth popup window, notify parent and close
  if ((oauthSuccess || oauthError) && window.opener && !window.opener.closed) {
    window.opener.postMessage({
      type: 'oauth_callback',
      success: oauthSuccess === 'true',
      error: oauthError || null,
      remoteId: remoteId || null
    }, window.location.origin)
    window.close()
    return
  }

  // Set active tab if specified
  if (tab) {
    activeTab.value = tab
  }

  if (oauthSuccess === 'true') {
    showToast(t('backup.oauthSuccess'), 'success')
    fetchStorageRemotes()
    // Clean URL
    router.replace({ path: '/backup', query: { tab: 'remotes' } })
  } else if (oauthError) {
    const errorMessages = {
      invalid_state: t('backup.oauthErrorInvalidState'),
      token_exchange_failed: t('backup.oauthErrorTokenExchange'),
      token_decrypt_failed: t('backup.oauthErrorTokenExchange'),
      user_denied: t('backup.oauthErrorUserDenied')
    }
    showToast(errorMessages[oauthError] || oauthError, 'error')
    // Clean URL
    router.replace({ path: '/backup', query: { tab: 'remotes' } })
  }
}

onMounted(() => {
  fetchConfigs()
  fetchBackups()
  fetchStats()
  fetchRcloneStatus()
  fetchStorageRemotes()
  fetchActiveBackupTasks()

  // Start polling if there are active tasks
  fetchActiveBackupTasks().then(() => {
    if (activeBackupTasks.value.length > 0) {
      startTaskPolling()
    }
  })

  // Listen for OAuth popup messages
  window.addEventListener('message', handleOAuthMessage)

  // Handle OAuth callback if present (for popup or direct navigation)
  handleOAuthCallback()
})

onUnmounted(() => {
  stopTaskPolling()
  window.removeEventListener('message', handleOAuthMessage)
})
</script>
