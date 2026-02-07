<template>
  <div class="p-6">
    <!-- Header with Breadcrumb -->
    <div class="mb-6">
      <nav class="flex mb-2" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
          <li class="inline-flex items-center">
            <router-link to="/backup" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
              </svg>
              {{ $t('backup.title') }}
            </router-link>
          </li>
          <li>
            <div class="flex items-center">
              <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
              </svg>
              <span class="ml-1 text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.restore') }}</span>
            </div>
          </li>
        </ol>
      </nav>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        {{ $t('backup.restoreBackup') }}
      </h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ $t('backup.restoreDescription') }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex items-center justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <!-- Backup Not Found -->
    <div v-else-if="!backup" class="text-center py-12">
      <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.notFound') }}</h3>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.notFoundDesc') }}</p>
      <router-link to="/backup" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700">
        {{ $t('backup.backToList') }}
      </router-link>
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Side - Backup Info & Options -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Remote Sync Warning -->
        <div v-if="backup.needs_remote_sync" class="bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800 rounded-lg shadow p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ $t('backup.remoteRestoreTitle') }}</h3>
              <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                <p>{{ $t('backup.remoteRestoreDesc') }}</p>
                <ul v-if="backup.synced_remotes_info && backup.synced_remotes_info.length > 0" class="mt-2 list-disc list-inside">
                  <li v-for="remote in backup.synced_remotes_info" :key="remote.id">
                    {{ remote.display_name }} ({{ remote.type }})
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Backup Information Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.backupInfo') }}</h3>
          </div>
          <div class="px-4 py-5 sm:p-6">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('backup.configName') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ backup.backup_config?.name || '-' }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('backup.backupType') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ getBackupTypeLabel(backup.type) }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('backup.completedAt') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ formatDate(backup.completed_at) }}</dd>
              </div>
              <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('backup.size') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ backup.size_formatted || '-' }}</dd>
              </div>
              <div v-if="backup.metadata?.files_count">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('backup.filesCount') }}</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ backup.metadata.files_count }}</dd>
              </div>
              <div v-if="backup.snapshot_id">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('backup.snapshotId') }}</dt>
                <dd class="mt-1 text-sm font-mono text-gray-900 dark:text-white">{{ backup.snapshot_id.substring(0, 12) }}</dd>
              </div>
            </dl>
          </div>
        </div>

        <!-- Restore Mode Selection -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.restoreMode') }}</h3>
          </div>
          <div class="px-4 py-5 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <label
                :class="[
                  restoreForm.mode === 'full'
                    ? 'border-primary-500 ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900'
                    : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                  'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none transition-all'
                ]"
              >
                <input type="radio" v-model="restoreForm.mode" value="full" class="sr-only" />
                <div class="flex items-start">
                  <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.restoreFull') }}</span>
                    <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('backup.restoreFullDesc') }}</span>
                  </div>
                </div>
              </label>
              <label
                :class="[
                  restoreForm.mode === 'selective'
                    ? 'border-primary-500 ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900'
                    : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                  'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none transition-all'
                ]"
              >
                <input type="radio" v-model="restoreForm.mode" value="selective" class="sr-only" />
                <div class="flex items-start">
                  <div class="flex-shrink-0">
                    <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.restoreSelective') }}</span>
                    <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('backup.restoreSelectiveDesc') }}</span>
                  </div>
                </div>
              </label>
            </div>
          </div>
        </div>

        <!-- Selective Restore Options -->
        <div v-if="restoreForm.mode === 'selective'" class="bg-white dark:bg-gray-800 rounded-lg shadow">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.selectObjects') }}</h3>
          </div>
          <div class="px-4 py-5 sm:p-6">
            <!-- Object Type Tabs -->
            <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
              <nav class="-mb-px flex space-x-8">
                <button
                  v-for="objType in objectTypes"
                  :key="objType.value"
                  @click="restoreForm.objectType = objType.value; loadBackupObjects()"
                  :class="[
                    restoreForm.objectType === objType.value
                      ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
                    'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center'
                  ]"
                >
                  <component :is="objType.icon" class="w-5 h-5 mr-2" />
                  {{ objType.label }}
                  <span
                    v-if="getSelectedCountForType(objType.value) > 0"
                    class="ml-2 bg-primary-100 text-primary-600 dark:bg-primary-900 dark:text-primary-400 px-2 py-0.5 rounded-full text-xs"
                  >
                    {{ getSelectedCountForType(objType.value) }}
                  </span>
                </button>
              </nav>
            </div>

            <!-- Folder Browser (for folders tab) -->
            <div v-if="restoreForm.objectType === 'folders'" class="grid grid-cols-2 gap-4">
              <!-- Folder Browser -->
              <div class="border border-gray-300 dark:border-gray-600 rounded-lg">
                <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border-b border-gray-300 dark:border-gray-600 rounded-t-lg">
                  <div class="flex items-center text-sm text-gray-500 dark:text-gray-400">
                    <button @click="browseFolderPath('/')" class="hover:text-primary-600">{{ $t('backup.root') }}</button>
                    <template v-for="(segment, index) in folderPathSegments" :key="index">
                      <svg class="w-4 h-4 mx-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                      </svg>
                      <button @click="browseFolderPath(getFolderPathUpTo(index))" class="hover:text-primary-600 truncate max-w-[100px]">{{ segment }}</button>
                    </template>
                  </div>
                </div>
                <div class="h-64 overflow-y-auto p-2">
                  <div v-if="isBrowsingFolders" class="flex items-center justify-center h-full">
                    <svg class="animate-spin h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                  </div>
                  <div v-else-if="folderItems.length === 0" class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">
                    {{ $t('backup.noFilesFound') }}
                  </div>
                  <div v-else class="space-y-1">
                    <div
                      v-for="item in folderItems"
                      :key="item.path"
                      class="flex items-center p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
                    >
                      <input
                        type="checkbox"
                        :value="item.path"
                        v-model="restoreForm.selectedObjects"
                        class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded mr-3"
                        @click.stop
                      />
                      <div
                        @click="item.type === 'dir' ? browseFolderPath(item.path) : null"
                        :class="[item.type === 'dir' ? 'cursor-pointer' : '', 'flex items-center flex-1 min-w-0']"
                      >
                        <svg v-if="item.type === 'dir'" class="w-5 h-5 text-yellow-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M2 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" clip-rule="evenodd" />
                        </svg>
                        <svg v-else class="w-5 h-5 text-gray-400 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                          <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd" />
                        </svg>
                        <div class="flex-1 min-w-0">
                          <span class="text-sm text-gray-900 dark:text-white block truncate">{{ item.name }}</span>
                          <span v-if="item.size" class="text-xs text-gray-500 dark:text-gray-400">{{ formatFileSize(item.size) }}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border-t border-gray-300 dark:border-gray-600 rounded-b-lg flex justify-between">
                  <button
                    type="button"
                    @click="selectAllFolderItems"
                    class="text-xs text-primary-600 hover:text-primary-800"
                  >
                    {{ $t('backup.selectAll') }}
                  </button>
                  <span class="text-xs text-gray-500 dark:text-gray-400">{{ folderItems.length }} {{ $t('backup.items') }}</span>
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
                <div class="h-64 overflow-y-auto p-2">
                  <div v-if="restoreForm.selectedObjects.length === 0" class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">
                    {{ $t('backup.noObjectsSelected') }}
                  </div>
                  <div v-else class="space-y-1">
                    <div
                      v-for="path in restoreForm.selectedObjects"
                      :key="path"
                      class="flex items-center justify-between p-2 rounded bg-primary-50 dark:bg-primary-900"
                    >
                      <div class="flex-1 min-w-0">
                        <span class="text-sm text-gray-900 dark:text-white truncate block">{{ getObjectName(path) }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ path }}</span>
                      </div>
                      <button
                        type="button"
                        @click="removeSelectedObject(path)"
                        class="ml-2 text-red-500 hover:text-red-700"
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

            <!-- Dual-pane Object Selector (for other tabs) -->
            <div v-else class="grid grid-cols-2 gap-4">
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
                <div class="h-64 overflow-y-auto p-2">
                  <div v-if="isLoadingObjects" class="flex items-center justify-center h-full">
                    <svg class="animate-spin h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                  </div>
                  <div v-else-if="filteredAvailableObjects.length === 0" class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">
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
                      <div class="ml-3 flex-1 min-w-0">
                        <span class="text-sm text-gray-900 dark:text-white block truncate">{{ obj.name }}</span>
                        <span v-if="obj.description" class="text-xs text-gray-500 dark:text-gray-400 block">{{ obj.description }}</span>
                        <span v-else-if="obj.size" class="text-xs text-gray-500 dark:text-gray-400">{{ formatFileSize(obj.size) }}</span>
                      </div>
                    </label>
                  </div>
                </div>
                <div class="px-3 py-2 bg-gray-50 dark:bg-gray-700 border-t border-gray-300 dark:border-gray-600 rounded-b-lg flex justify-between">
                  <button
                    type="button"
                    @click="selectAllAvailable"
                    class="text-xs text-primary-600 hover:text-primary-800"
                  >
                    {{ $t('backup.selectAll') }}
                  </button>
                  <span class="text-xs text-gray-500 dark:text-gray-400">{{ availableObjects.length }} {{ $t('backup.items') }}</span>
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
                <div class="h-64 overflow-y-auto p-2">
                  <div v-if="restoreForm.selectedObjects.length === 0" class="text-center text-sm text-gray-500 dark:text-gray-400 py-8">
                    {{ $t('backup.noObjectsSelected') }}
                  </div>
                  <div v-else class="space-y-1">
                    <div
                      v-for="path in restoreForm.selectedObjects"
                      :key="path"
                      class="flex items-center justify-between p-2 rounded bg-primary-50 dark:bg-primary-900"
                    >
                      <div class="flex-1 min-w-0">
                        <span class="text-sm text-gray-900 dark:text-white truncate block">{{ getObjectName(path) }}</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ getObjectType(path) }}</span>
                      </div>
                      <button
                        type="button"
                        @click="removeSelectedObject(path)"
                        class="ml-2 text-red-500 hover:text-red-700"
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
        </div>

        <!-- Target Path Selection -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.targetPath') }}</h3>
          </div>
          <div class="px-4 py-5 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
              <label
                :class="[
                  restoreForm.target_path === '/'
                    ? 'border-orange-500 ring-2 ring-orange-500 bg-orange-50 dark:bg-orange-900'
                    : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                  'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none transition-all'
                ]"
              >
                <input type="radio" v-model="restoreForm.target_path" value="/" class="sr-only" />
                <div class="flex items-center w-full">
                  <div class="flex-shrink-0 bg-orange-100 dark:bg-orange-800 rounded-lg p-2">
                    <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                  </div>
                  <div class="ml-3 flex-1">
                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.restoreOriginal') }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $t('backup.restoreOriginalDesc') }}</span>
                    <code class="mt-1 block text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">/</code>
                  </div>
                </div>
              </label>
              <label
                :class="[
                  restoreForm.target_path === '/tmp/restore'
                    ? 'border-green-500 ring-2 ring-green-500 bg-green-50 dark:bg-green-900'
                    : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                  'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none transition-all'
                ]"
              >
                <input type="radio" v-model="restoreForm.target_path" value="/tmp/restore" class="sr-only" />
                <div class="flex items-center w-full">
                  <div class="flex-shrink-0 bg-green-100 dark:bg-green-800 rounded-lg p-2">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                  </div>
                  <div class="ml-3 flex-1">
                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.restoreSafe') }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $t('backup.restoreSafeDesc') }}</span>
                    <code class="mt-1 block text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">/tmp/restore</code>
                  </div>
                </div>
              </label>
            </div>

            <!-- Custom Path Input -->
            <div class="mt-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('backup.customPath') }}</label>
              <input
                v-model="restoreForm.target_path"
                type="text"
                :placeholder="$t('backup.targetPathPlaceholder')"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
              />
            </div>

            <!-- Warning Messages -->
            <div v-if="restoreForm.target_path === '/'" class="mt-4 p-4 bg-red-50 dark:bg-red-900/50 rounded-lg border border-red-200 dark:border-red-800">
              <div class="flex">
                <svg class="h-5 w-5 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-red-800 dark:text-red-200">{{ $t('backup.warningOverwrite') }}</h3>
                  <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $t('backup.restoreWarningOverwrite') }}</p>
                </div>
              </div>
            </div>

            <!-- Database Restore Info -->
            <div v-if="hasDatabaseSelected" class="mt-4 p-4 bg-green-50 dark:bg-green-900/50 rounded-lg border border-green-200 dark:border-green-800">
              <div class="flex">
                <svg class="h-5 w-5 text-green-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-green-800 dark:text-green-200">{{ $t('backup.dbRestoreNoDowntime') }}</h3>
                  <p class="mt-1 text-sm text-green-700 dark:text-green-300">{{ $t('backup.dbRestoreNoDowntimeDesc') }}</p>
                </div>
              </div>
            </div>

            <div v-else class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/50 rounded-lg border border-blue-200 dark:border-blue-800">
              <div class="flex">
                <svg class="h-5 w-5 text-blue-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                  <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ $t('backup.infoSafe') }}</h3>
                  <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">{{ $t('backup.restoreInfoSafe') }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Side - Summary & Actions -->
      <div class="space-y-6">
        <!-- Restore Summary -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow sticky top-6">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.restoreSummary') }}</h3>
          </div>
          <div class="px-4 py-5 sm:p-6">
            <dl class="space-y-4">
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.backupDate') }}</dt>
                <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(backup.completed_at) }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.restoreMode') }}</dt>
                <dd class="text-sm font-medium text-gray-900 dark:text-white">
                  {{ restoreForm.mode === 'full' ? $t('backup.restoreFull') : $t('backup.restoreSelective') }}
                </dd>
              </div>
              <div v-if="restoreForm.mode === 'selective'" class="flex justify-between">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.selectedItems') }}</dt>
                <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ restoreForm.selectedObjects.length }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.targetPath') }}</dt>
                <dd class="text-sm font-mono font-medium text-gray-900 dark:text-white">{{ restoreForm.target_path }}</dd>
              </div>
            </dl>

            <div class="mt-6 space-y-3">
              <!-- Restore Status Progress -->
              <div v-if="restoreStatus" class="p-4 rounded-lg" :class="{
                'bg-blue-50 dark:bg-blue-900/50 border border-blue-200 dark:border-blue-800': restoreStatus.status === 'running' || restoreStatus.status === 'pending',
                'bg-green-50 dark:bg-green-900/50 border border-green-200 dark:border-green-800': restoreStatus.status === 'completed',
                'bg-red-50 dark:bg-red-900/50 border border-red-200 dark:border-red-800': restoreStatus.status === 'failed'
              }">
                <div class="flex items-center">
                  <!-- Pending/Running -->
                  <svg v-if="restoreStatus.status === 'running' || restoreStatus.status === 'pending'" class="animate-spin h-5 w-5 text-blue-600 dark:text-blue-400 mr-3" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <!-- Completed -->
                  <svg v-else-if="restoreStatus.status === 'completed'" class="h-5 w-5 text-green-600 dark:text-green-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                  <!-- Failed -->
                  <svg v-else-if="restoreStatus.status === 'failed'" class="h-5 w-5 text-red-600 dark:text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                  </svg>
                  <div class="flex-1">
                    <p class="text-sm font-medium" :class="{
                      'text-blue-800 dark:text-blue-200': restoreStatus.status === 'running' || restoreStatus.status === 'pending',
                      'text-green-800 dark:text-green-200': restoreStatus.status === 'completed',
                      'text-red-800 dark:text-red-200': restoreStatus.status === 'failed'
                    }">
                      {{ restoreStatus.status === 'pending' ? $t('backup.restorePending') :
                         restoreStatus.status === 'running' ? $t('backup.restoreRunning') :
                         restoreStatus.status === 'completed' ? $t('backup.restoreCompleted', { files: restoreStatus.files_restored, size: restoreStatus.bytes_restored_formatted }) :
                         $t('backup.restoreFailed') }}
                    </p>
                    <!-- Special handling for OLD_BACKUP_FORMAT error -->
                    <template v-if="restoreStatus.status === 'failed' && restoreStatus.error_message">
                      <div v-if="restoreStatus.error_message.includes('OLD_BACKUP_FORMAT') || restoreStatus.error_message.includes('old format')" class="mt-2">
                        <p class="text-sm font-medium text-amber-800 dark:text-amber-200">{{ $t('backup.oldBackupFormatTitle') }}</p>
                        <p class="text-xs text-amber-700 dark:text-amber-300 mt-1">{{ $t('backup.oldBackupFormatDesc') }}</p>
                        <router-link to="/backup/create" class="inline-flex items-center mt-2 px-3 py-1.5 text-xs font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-md">
                          <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                          </svg>
                          {{ $t('backup.oldBackupFormatSolution') }}
                        </router-link>
                      </div>
                      <p v-else class="text-xs text-red-600 dark:text-red-400 mt-1">
                        {{ restoreStatus.error_message }}
                      </p>
                    </template>
                    <p v-if="restoreStatus.status === 'completed'" class="text-xs text-green-600 dark:text-green-400 mt-1">
                      {{ $t('backup.restoreTargetPath') }}: {{ restoreStatus.target_path }}
                    </p>
                  </div>
                </div>
              </div>

              <button
                @click="startRestore"
                :disabled="isRestoring || (restoreForm.mode === 'selective' && restoreForm.selectedObjects.length === 0)"
                class="w-full inline-flex justify-center items-center px-4 py-3 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <svg v-if="isRestoring" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <svg v-else class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                {{ isRestoring ? $t('backup.restoring') : $t('backup.startRestore') }}
              </button>
              <router-link
                to="/backup"
                :class="isRestoring ? 'pointer-events-none opacity-50' : ''"
                class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
              >
                {{ $t('common.cancel') }}
              </router-link>
            </div>
          </div>
        </div>

        <!-- Browse Files Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.browseFiles') }}</h3>
          </div>
          <div class="px-4 py-5 sm:p-6">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ $t('backup.browseFilesDesc') }}</p>
            <button
              @click="openBrowseModal"
              class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
            >
              <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
              </svg>
              {{ $t('backup.browseBackup') }}
            </button>
          </div>
        </div>
      </div>
    </div>

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
                  @click="addSelectedFilesToRestore"
                  :disabled="selectedBrowseFiles.length === 0"
                  class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:text-sm disabled:opacity-50"
                >
                  {{ $t('backup.addToRestore') }}
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, h } from 'vue'
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

// State
const isLoading = ref(true)
const backup = ref(null)
const isRestoring = ref(false)
const isLoadingObjects = ref(false)

// Browse files state
const showBrowseModal = ref(false)
const isBrowsingFiles = ref(false)
const browsePath = ref('/')
const browseFiles = ref([])
const selectedBrowseFiles = ref([])

// Folder browser state (for folders tab)
const isBrowsingFolders = ref(false)
const folderPath = ref('/')
const folderItems = ref([])

// Available objects state
const availableObjects = ref([])
const availableSearch = ref('')

// Object types with icons
const objectTypes = computed(() => [
  {
    value: 'websites',
    label: t('backup.websites'),
    icon: {
      render() {
        return h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9' })
        ])
      }
    }
  },
  {
    value: 'databases',
    label: t('backup.databases'),
    icon: {
      render() {
        return h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4' })
        ])
      }
    }
  },
  {
    value: 'emails',
    label: t('backup.emails'),
    icon: {
      render() {
        return h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' })
        ])
      }
    }
  },
  {
    value: 'config',
    label: t('backup.config'),
    icon: {
      render() {
        return h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }),
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z' })
        ])
      }
    }
  },
  {
    value: 'folders',
    label: t('backup.folders'),
    icon: {
      render() {
        return h('svg', { class: 'w-5 h-5', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
          h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z' })
        ])
      }
    }
  }
])

// Restore form
const restoreForm = ref({
  mode: 'full',
  target_path: '/',
  objectType: 'websites',
  selectedObjects: []
})

// Computed
const browsePathSegments = computed(() => {
  if (browsePath.value === '/') return []
  return browsePath.value.split('/').filter(s => s)
})

const folderPathSegments = computed(() => {
  if (folderPath.value === '/') return []
  return folderPath.value.split('/').filter(s => s)
})

const filteredAvailableObjects = computed(() => {
  if (!availableSearch.value) return availableObjects.value
  const search = availableSearch.value.toLowerCase()
  return availableObjects.value.filter(obj => obj.name.toLowerCase().includes(search))
})

const hasDatabaseSelected = computed(() => {
  // Check if any database path is selected (full mode or selective with database)
  if (restoreForm.value.mode === 'full' && (backup.value?.type === 'full' || backup.value?.type === 'databases')) {
    return true
  }
  // Check selective mode
  return restoreForm.value.selectedObjects.some(path => path.includes('/var/lib/mysql/'))
})

// Methods
const fetchBackup = async () => {
  isLoading.value = true
  try {
    const response = await api.get(`/backups/${route.params.id}`)
    if (response.data.success) {
      backup.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch backup:', error)
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isLoading.value = false
  }
}

const loadBackupObjects = async () => {
  // For folders tab, browse the backup instead
  if (restoreForm.value.objectType === 'folders') {
    await browseFolderPath('/')
    return
  }

  isLoadingObjects.value = true
  availableObjects.value = []

  try {
    const response = await api.get(`/backups/${route.params.id}/objects`, {
      params: { type: restoreForm.value.objectType }
    })
    if (response.data.success) {
      availableObjects.value = response.data.data || []
    }
  } catch (error) {
    console.error('Failed to load backup objects:', error)
    showToast(t('backup.noObjectsFound'), 'warning')
  } finally {
    isLoadingObjects.value = false
  }
}

// Folder browser functions
const browseFolderPath = async (path) => {
  isBrowsingFolders.value = true
  folderPath.value = path
  folderItems.value = []

  try {
    const response = await api.get(`/backups/${route.params.id}/browse`, {
      params: { path }
    })
    if (response.data.success) {
      folderItems.value = response.data.data || []
    }
  } catch (error) {
    console.error('Failed to browse folder:', error)
    showToast(t('backup.noFilesFound'), 'warning')
  } finally {
    isBrowsingFolders.value = false
  }
}

const getFolderPathUpTo = (index) => {
  const segments = folderPath.value.split('/').filter(s => s)
  return '/' + segments.slice(0, index + 1).join('/')
}

const selectAllFolderItems = () => {
  const paths = folderItems.value.map(item => item.path)
  restoreForm.value.selectedObjects = [...new Set([...restoreForm.value.selectedObjects, ...paths])]
}

const selectAllAvailable = () => {
  const paths = filteredAvailableObjects.value.map(obj => obj.path)
  restoreForm.value.selectedObjects = [...new Set([...restoreForm.value.selectedObjects, ...paths])]
}

const clearAllSelected = () => {
  restoreForm.value.selectedObjects = []
}

const removeSelectedObject = (path) => {
  restoreForm.value.selectedObjects = restoreForm.value.selectedObjects.filter(p => p !== path)
}

const getObjectName = (path) => {
  // Try to find the object in availableObjects to get its name
  const obj = availableObjects.value.find(o => o.path === path)
  if (obj && obj.name) {
    return obj.name
  }
  return path.split('/').pop() || path
}

const getObjectType = (path) => {
  if (path.includes('/var/www') || path.includes('/home')) return t('backup.websites')
  if (path.includes('/var/lib/mysql')) return t('backup.databases')
  if (path.includes('/var/mail') || path.includes('/var/vmail')) return t('backup.emails')
  return t('backup.config')
}

const getSelectedCountForType = (type) => {
  const typePathMap = {
    websites: ['/var/www', '/home'],
    databases: ['/var/lib/mysql'],
    emails: ['/var/mail', '/var/vmail'],
    config: ['/etc/nginx', '/etc/apache2', '/etc/postfix', '/etc/vsispanel']
  }

  const paths = typePathMap[type] || []
  return restoreForm.value.selectedObjects.filter(p =>
    paths.some(typePath => p.startsWith(typePath))
  ).length
}

const openBrowseModal = async () => {
  browsePath.value = '/'
  browseFiles.value = []
  selectedBrowseFiles.value = []
  showBrowseModal.value = true
  await fetchBrowseFiles()
}

const fetchBrowseFiles = async () => {
  isBrowsingFiles.value = true
  try {
    const response = await api.get(`/backups/${route.params.id}/browse`, {
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

const getPathUpTo = (index) => {
  const segments = browsePath.value.split('/').filter(s => s)
  return '/' + segments.slice(0, index + 1).join('/')
}

const addSelectedFilesToRestore = () => {
  restoreForm.value.selectedObjects = [...new Set([...restoreForm.value.selectedObjects, ...selectedBrowseFiles.value])]
  restoreForm.value.mode = 'selective'
  showBrowseModal.value = false
  showToast(t('backup.filesAddedToRestore'), 'success')
}

// Restore operation tracking
const restoreOperationId = ref(null)
const restoreStatus = ref(null)
const restorePollingInterval = ref(null)

const startRestore = async () => {
  isRestoring.value = true
  restoreOperationId.value = null
  restoreStatus.value = null

  try {
    let includePaths = []
    if (restoreForm.value.mode === 'selective') {
      includePaths = restoreForm.value.selectedObjects
    }

    const response = await api.post(`/backups/${route.params.id}/restore`, {
      target_path: restoreForm.value.target_path,
      include_paths: includePaths
    })

    if (response.data.success) {
      restoreOperationId.value = response.data.data?.restore_operation_id
      showToast(t('backup.restoreStarted'), 'success')

      // Start polling for status if we have an operation ID
      if (restoreOperationId.value) {
        startRestorePolling()
      } else {
        // No operation ID, redirect immediately
        router.push('/backup')
      }
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
    isRestoring.value = false
  }
}

const startRestorePolling = () => {
  // Poll every 2 seconds
  restorePollingInterval.value = setInterval(async () => {
    try {
      const response = await api.get(`/restore-operations/${restoreOperationId.value}`)
      if (response.data.success) {
        restoreStatus.value = response.data.data

        // Check if restore is done
        if (restoreStatus.value.status === 'completed') {
          stopRestorePolling()
          const filesRestored = restoreStatus.value.files_restored || 0
          const bytesFormatted = restoreStatus.value.bytes_restored_formatted || '0 B'
          showToast(t('backup.restoreCompleted', { files: filesRestored, size: bytesFormatted }), 'success')
          isRestoring.value = false
          // Navigate after showing success message
          setTimeout(() => router.push('/backup'), 2000)
        } else if (restoreStatus.value.status === 'failed') {
          stopRestorePolling()
          showToast(restoreStatus.value.error_message || t('backup.restoreFailed'), 'error')
          isRestoring.value = false
        }
      }
    } catch (error) {
      console.error('Failed to poll restore status:', error)
    }
  }, 2000)
}

const stopRestorePolling = () => {
  if (restorePollingInterval.value) {
    clearInterval(restorePollingInterval.value)
    restorePollingInterval.value = null
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

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  const factor = Math.floor(Math.log(bytes) / Math.log(1024))
  return (bytes / Math.pow(1024, factor)).toFixed(2) + ' ' + units[factor]
}

onMounted(async () => {
  await fetchBackup()
  if (backup.value) {
    await loadBackupObjects()
  }
})

onUnmounted(() => {
  stopRestorePolling()
})
</script>
