<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('nav.fileManager') }}
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $t('fileManager.description') }}
      </p>
    </div>

    <!-- Domain Selector -->
    <VCard v-if="!selectedDomain" class="mb-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ $t('fileManager.selectDomain') }}
        </h2>
        <div class="flex items-center gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-lg">
          <button
            @click="domainViewMode = 'grid'"
            :class="[
              'p-2 rounded-md transition-colors',
              domainViewMode === 'grid' ? 'bg-white dark:bg-gray-700 shadow text-primary-600' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
            ]"
            :title="$t('fileManager.gridView')"
          >
            <Squares2X2Icon class="w-4 h-4" />
          </button>
          <button
            @click="domainViewMode = 'list'"
            :class="[
              'p-2 rounded-md transition-colors',
              domainViewMode === 'list' ? 'bg-white dark:bg-gray-700 shadow text-primary-600' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'
            ]"
            :title="$t('fileManager.listView')"
          >
            <Bars3Icon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Grid View -->
      <div v-if="domainViewMode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div
          v-for="domain in domains"
          :key="domain.id"
          @click="selectDomain(domain)"
          class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/20 transition-all hover:shadow-md"
        >
          <div class="flex flex-col items-center text-center">
            <div class="w-14 h-14 bg-primary-100 dark:bg-primary-900/30 rounded-xl flex items-center justify-center mb-3">
              <GlobeAltIcon class="w-7 h-7 text-primary-500" />
            </div>
            <p class="font-medium text-gray-900 dark:text-white mb-1 truncate w-full">{{ domain.name }}</p>
            <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
              <CircleStackIcon class="w-4 h-4" />
              <span>{{ formatDiskUsage(domain.disk_usage) }}</span>
            </div>
            <!-- Disk Usage Progress -->
            <div v-if="domain.disk_quota" class="w-full mt-2">
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                <div
                  :class="[
                    'h-1.5 rounded-full transition-all',
                    getDiskUsageClass(domain.disk_usage, domain.disk_quota)
                  ]"
                  :style="{ width: `${Math.min(getDiskUsagePercent(domain.disk_usage, domain.disk_quota), 100)}%` }"
                ></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- List View -->
      <div v-else class="border border-gray-200 dark:border-gray-700 rounded-lg divide-y divide-gray-200 dark:divide-gray-700">
        <div
          v-for="domain in domains"
          :key="domain.id"
          @click="selectDomain(domain)"
          class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
        >
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center flex-shrink-0">
              <GlobeAltIcon class="w-5 h-5 text-primary-500" />
            </div>
            <div>
              <p class="font-medium text-gray-900 dark:text-white">{{ domain.name }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                <VBadge :variant="domain.status === 'active' ? 'success' : 'warning'" size="sm">
                  {{ domain.status }}
                </VBadge>
              </p>
            </div>
          </div>
          <div class="flex items-center gap-6">
            <!-- Disk Usage -->
            <div class="text-right">
              <p class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDiskUsage(domain.disk_usage) }}</p>
              <p v-if="domain.disk_quota" class="text-xs text-gray-500 dark:text-gray-400">
                / {{ formatDiskUsage(domain.disk_quota) }}
              </p>
              <p v-else class="text-xs text-gray-500 dark:text-gray-400">{{ $t('ftp.unlimited') }}</p>
            </div>
            <!-- Progress Bar -->
            <div v-if="domain.disk_quota" class="w-24">
              <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                  :class="[
                    'h-2 rounded-full transition-all',
                    getDiskUsageClass(domain.disk_usage, domain.disk_quota)
                  ]"
                  :style="{ width: `${Math.min(getDiskUsagePercent(domain.disk_usage, domain.disk_quota), 100)}%` }"
                ></div>
              </div>
            </div>
            <ChevronRightIcon class="w-5 h-5 text-gray-400" />
          </div>
        </div>
      </div>

      <div v-if="loadingDomains" class="py-8 text-center">
        <VLoadingSkeleton class="h-24" />
      </div>
      <div v-if="!loadingDomains && domains.length === 0" class="py-8 text-center text-gray-500 dark:text-gray-400">
        {{ $t('fileManager.noDomains') }}
      </div>
    </VCard>

    <!-- File Browser -->
    <template v-else>
      <!-- Breadcrumb & Domain Info -->
      <VCard class="mb-4">
        <div class="flex items-center justify-between">
          <div class="flex items-center space-x-4">
            <button
              @click="selectedDomain = null"
              class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800"
            >
              <ArrowLeftIcon class="w-5 h-5" />
            </button>
            <div>
              <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ selectedDomain.name }}</h2>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ selectedDomain.document_root }}</p>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <VBadge variant="info">{{ diskUsage }}</VBadge>
          </div>
        </div>
      </VCard>

      <!-- Main Toolbar -->
      <VCard class="mb-4" :padding="false">
        <div class="flex flex-wrap items-center gap-2 p-3 border-b border-gray-200 dark:border-gray-700">
          <VButton variant="secondary" size="sm" :icon="ArrowUpTrayIcon" @click="showUploadModal = true">
            {{ $t('fileManager.upload') }}
          </VButton>

          <div class="relative" ref="newDropdownRef">
            <VButton variant="secondary" size="sm" :icon="PlusIcon" @click="showNewDropdown = !showNewDropdown">
              {{ $t('fileManager.new') }}
              <ChevronDownIcon class="w-4 h-4 ml-1" />
            </VButton>
            <div v-if="showNewDropdown" class="absolute left-0 mt-1 w-40 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50">
              <button @click="showNewFolderModal = true; showNewDropdown = false" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center">
                <FolderPlusIcon class="w-4 h-4 mr-2" />
                {{ $t('fileManager.newFolder') }}
              </button>
              <button @click="showNewFileModal = true; showNewDropdown = false" class="w-full px-4 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center">
                <DocumentPlusIcon class="w-4 h-4 mr-2" />
                {{ $t('fileManager.newFile') }}
              </button>
            </div>
          </div>

          <VButton variant="secondary" size="sm" :icon="MagnifyingGlassIcon" @click="showSearchModal = true">
            {{ $t('fileManager.searchContent') }}
          </VButton>

          <VButton variant="secondary" size="sm" :icon="CloudArrowDownIcon" @click="showRemoteDownloadModal = true">
            {{ $t('fileManager.remoteDownload') }}
          </VButton>

          <div class="border-l border-gray-300 dark:border-gray-600 h-6 mx-1"></div>

          <VButton variant="ghost" size="sm" :icon="ArrowPathIcon" :loading="loading" @click="fetchFiles">
            {{ $t('common.refresh') }}
          </VButton>
        </div>

        <!-- Bulk Actions (shown when items selected) -->
        <div v-if="selectedItems.length > 0" class="flex flex-wrap items-center gap-2 p-3 bg-primary-50 dark:bg-primary-900/20 border-b border-gray-200 dark:border-gray-700">
          <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
            {{ selectedItems.length }} {{ $t('fileManager.itemsSelected') }}
          </span>
          <div class="border-l border-primary-300 dark:border-primary-600 h-6 mx-1"></div>
          <VButton variant="secondary" size="sm" :icon="ArrowDownTrayIcon" @click="downloadSelected">
            {{ $t('fileManager.download') }}
          </VButton>
          <VButton variant="secondary" size="sm" :icon="ArchiveBoxIcon" @click="showCompressModal = true">
            {{ $t('fileManager.compress') }}
          </VButton>
          <VButton variant="secondary" size="sm" :icon="DocumentDuplicateIcon" @click="showCopyModal = true">
            {{ $t('fileManager.copy') }}
          </VButton>
          <VButton variant="secondary" size="sm" :icon="ArrowRightIcon" @click="showMoveModal = true">
            {{ $t('fileManager.move') }}
          </VButton>
          <VButton variant="secondary" size="sm" :icon="LockClosedIcon" @click="showBulkPermissionsModal = true">
            {{ $t('fileManager.permissions') }}
          </VButton>
          <VButton variant="danger" size="sm" :icon="TrashIcon" @click="deleteSelected">
            {{ $t('common.delete') }}
          </VButton>
          <VButton variant="ghost" size="sm" @click="clearSelection">
            {{ $t('common.cancel') }}
          </VButton>
        </div>

        <!-- Path Breadcrumb -->
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800/50">
          <div class="flex items-center space-x-2 text-sm">
            <button
              @click="navigateTo('')"
              class="text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline"
            >
              <HomeIcon class="w-4 h-4" />
            </button>
            <span v-for="(part, index) in pathParts" :key="index" class="flex items-center">
              <ChevronRightIcon class="w-4 h-4 text-gray-400 mx-1" />
              <button
                @click="navigateTo(pathParts.slice(0, index + 1).join('/'))"
                class="text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline"
              >
                {{ part }}
              </button>
            </span>
          </div>
          <div class="flex items-center space-x-2">
            <VInput
              v-model="searchQuery"
              :placeholder="$t('fileManager.filterFiles')"
              size="sm"
              class="w-48"
              @keyup.enter="handleSearch"
            />
          </div>
        </div>
      </VCard>

      <!-- File List -->
      <VCard :padding="false">
        <!-- Loading State -->
        <VLoadingSkeleton v-if="loading" class="h-64 m-4" />

        <!-- Files Table -->
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-4 py-3 text-left w-10">
                  <input
                    type="checkbox"
                    :checked="allSelected"
                    :indeterminate="someSelected && !allSelected"
                    @change="toggleSelectAll"
                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                  {{ $t('fileManager.name') }}
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-24">
                  {{ $t('fileManager.size') }}
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-24">
                  {{ $t('fileManager.permissions') }}
                </th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-40">
                  {{ $t('fileManager.modified') }}
                </th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-48">
                  {{ $t('common.actions') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <!-- Parent Directory -->
              <tr
                v-if="currentPath"
                @click="navigateTo(parentPath)"
                class="hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
              >
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3" colspan="5">
                  <div class="flex items-center text-gray-500 dark:text-gray-400">
                    <ArrowUpIcon class="w-5 h-5 mr-3" />
                    <span>..</span>
                  </div>
                </td>
              </tr>
              <!-- Files & Directories -->
              <tr
                v-for="item in filteredFiles"
                :key="item.name"
                :class="[
                  'hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer',
                  isSelected(item) ? 'bg-primary-50 dark:bg-primary-900/20' : ''
                ]"
              >
                <td class="px-4 py-3" @click.stop>
                  <input
                    type="checkbox"
                    :checked="isSelected(item)"
                    @change="toggleSelect(item)"
                    class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                </td>
                <td class="px-4 py-3" @click="handleItemClick(item)">
                  <div class="flex items-center">
                    <component
                      :is="getFileIcon(item)"
                      :class="[
                        'w-5 h-5 mr-3 flex-shrink-0',
                        item.type === 'directory' ? 'text-yellow-500' : 'text-gray-400'
                      ]"
                    />
                    <span class="text-gray-900 dark:text-white truncate">{{ item.name }}</span>
                    <VBadge v-if="isArchive(item)" variant="info" class="ml-2 text-xs">{{ getArchiveLabel(item) }}</VBadge>
                  </div>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                  {{ item.type === 'directory' ? '-' : formatSize(item.size) }}
                </td>
                <td class="px-4 py-3">
                  <button
                    @click.stop="showPermissions(item)"
                    class="text-sm text-gray-500 dark:text-gray-400 font-mono hover:text-primary-600 dark:hover:text-primary-400"
                  >
                    {{ item.permissions }}
                  </button>
                </td>
                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDateTime(item.modified_at) }}
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end space-x-1" @click.stop>
                    <!-- Edit (for editable files) -->
                    <VButton
                      v-if="item.type === 'file' && item.is_editable"
                      variant="ghost"
                      size="sm"
                      :icon="PencilIcon"
                      @click="editFile(item)"
                      :title="$t('fileManager.edit')"
                    />
                    <!-- Extract (for archives) -->
                    <VButton
                      v-if="isArchive(item)"
                      variant="ghost"
                      size="sm"
                      :icon="ArchiveBoxArrowDownIcon"
                      @click="extractFile(item)"
                      :title="$t('fileManager.extract')"
                    />
                    <!-- Download (for files) -->
                    <VButton
                      v-if="item.type === 'file'"
                      variant="ghost"
                      size="sm"
                      :icon="ArrowDownTrayIcon"
                      @click="downloadFile(item)"
                      :title="$t('fileManager.download')"
                    />
                    <!-- Compress -->
                    <VButton
                      variant="ghost"
                      size="sm"
                      :icon="ArchiveBoxIcon"
                      @click="compressSingle(item)"
                      :title="$t('fileManager.compress')"
                    />
                    <!-- Rename -->
                    <VButton
                      variant="ghost"
                      size="sm"
                      :icon="PencilSquareIcon"
                      @click="showRenameModal(item)"
                      :title="$t('fileManager.rename')"
                    />
                    <!-- Permissions -->
                    <VButton
                      variant="ghost"
                      size="sm"
                      :icon="LockClosedIcon"
                      @click="showPermissions(item)"
                      :title="$t('fileManager.permissions')"
                    />
                    <!-- Delete -->
                    <VButton
                      variant="ghost"
                      size="sm"
                      :icon="TrashIcon"
                      class="text-red-500 hover:text-red-700"
                      @click="confirmDelete(item)"
                      :title="$t('common.delete')"
                    />
                  </div>
                </td>
              </tr>
              <!-- Empty State -->
              <tr v-if="!loading && filteredFiles.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                  {{ $t('fileManager.emptyDirectory') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </VCard>
    </template>

    <!-- New Folder Modal -->
    <VModal v-model:show="showNewFolderModal" :title="$t('fileManager.newFolder')">
      <form @submit.prevent="createFolder">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('fileManager.folderName') }}
          </label>
          <VInput v-model="newFolderName" placeholder="new_folder" required autofocus />
        </div>
        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showNewFolderModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="creatingFolder">
            {{ $t('common.create') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- New File Modal -->
    <VModal v-model:show="showNewFileModal" :title="$t('fileManager.newFile')">
      <form @submit.prevent="createFile">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('fileManager.fileName') }}
          </label>
          <VInput v-model="newFileName" placeholder="new_file.txt" required autofocus />
        </div>
        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showNewFileModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="creatingFile">
            {{ $t('common.create') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Upload Modal -->
    <VModal v-model:show="showUploadModal" :title="$t('fileManager.upload')" size="lg">
      <form @submit.prevent="uploadFiles">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ $t('fileManager.selectFiles') }}
          </label>
          <div
            class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center cursor-pointer hover:border-primary-500 transition-colors"
            @click="$refs.fileInput.click()"
            @drop.prevent="handleDrop"
            @dragover.prevent
          >
            <ArrowUpTrayIcon class="w-10 h-10 mx-auto text-gray-400 mb-2" />
            <p class="text-gray-600 dark:text-gray-400">
              {{ $t('fileManager.dropFiles') }}
            </p>
            <input
              ref="fileInput"
              type="file"
              multiple
              class="hidden"
              @change="handleFileSelect"
            />
          </div>
          <div v-if="selectedFiles.length > 0" class="mt-3 space-y-2 max-h-48 overflow-y-auto">
            <div v-for="(file, index) in selectedFiles" :key="index" class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 px-3 py-2 rounded">
              <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ file.name }}</span>
              <button type="button" @click="removeFile(index)" class="text-red-500 hover:text-red-700">
                <XMarkIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showUploadModal = false; selectedFiles = []">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="uploading" :disabled="selectedFiles.length === 0">
            {{ $t('fileManager.upload') }} ({{ selectedFiles.length }})
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Edit File Modal -->
    <VModal v-model:show="showEditModal" :title="editingFile?.name || $t('fileManager.editFile')" size="full">
      <div class="h-full">
        <CodeEditor
          ref="codeEditorRef"
          :domain-id="selectedDomain?.id"
          :dark-mode="isDarkMode"
          @save="handleEditorSave"
          @close="showEditModal = false"
        />
      </div>
      <template #footer>
        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showEditModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" :loading="savingFile" @click="saveFile">
            {{ $t('common.save') }}
          </VButton>
        </div>
      </template>
    </VModal>

    <!-- Image Preview Modal -->
    <VModal v-model:show="showPreviewModal" :title="previewFile?.name || $t('fileManager.preview')" size="lg">
      <div class="h-[60vh]">
        <ImagePreview
          :file-name="previewFile?.name || ''"
          :preview-url="previewUrl"
          :file-info="previewInfo"
          @close="showPreviewModal = false"
        />
      </div>
    </VModal>

    <!-- Rename Modal -->
    <VModal v-model:show="showRenameModalFlag" :title="$t('fileManager.rename')">
      <form @submit.prevent="handleRename">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('fileManager.newName') }}
          </label>
          <VInput v-model="renameNewName" required autofocus />
        </div>
        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showRenameModalFlag = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="renaming">
            {{ $t('fileManager.rename') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Permissions Modal -->
    <VModal v-model:show="showPermissionsModal" :title="$t('fileManager.changePermissions')">
      <form @submit.prevent="handleChangePermissions">
        <div class="mb-4">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ $t('fileManager.permissionsFor') }}: <strong>{{ permissionsItem?.name }}</strong>
          </p>

          <!-- Permission Grid -->
          <div class="space-y-4">
            <div class="grid grid-cols-4 gap-4 text-sm font-medium text-gray-700 dark:text-gray-300">
              <div></div>
              <div class="text-center">{{ $t('fileManager.read') }}</div>
              <div class="text-center">{{ $t('fileManager.write') }}</div>
              <div class="text-center">{{ $t('fileManager.execute') }}</div>
            </div>

            <!-- Owner -->
            <div class="grid grid-cols-4 gap-4 items-center">
              <div class="text-sm text-gray-700 dark:text-gray-300">{{ $t('fileManager.owner') }}</div>
              <div class="text-center">
                <input type="checkbox" v-model="permOwner.r" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              </div>
              <div class="text-center">
                <input type="checkbox" v-model="permOwner.w" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              </div>
              <div class="text-center">
                <input type="checkbox" v-model="permOwner.x" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              </div>
            </div>

            <!-- Group -->
            <div class="grid grid-cols-4 gap-4 items-center">
              <div class="text-sm text-gray-700 dark:text-gray-300">{{ $t('fileManager.group') }}</div>
              <div class="text-center">
                <input type="checkbox" v-model="permGroup.r" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              </div>
              <div class="text-center">
                <input type="checkbox" v-model="permGroup.w" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              </div>
              <div class="text-center">
                <input type="checkbox" v-model="permGroup.x" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              </div>
            </div>

            <!-- Others -->
            <div class="grid grid-cols-4 gap-4 items-center">
              <div class="text-sm text-gray-700 dark:text-gray-300">{{ $t('fileManager.others') }}</div>
              <div class="text-center">
                <input type="checkbox" v-model="permOthers.r" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              </div>
              <div class="text-center">
                <input type="checkbox" v-model="permOthers.w" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              </div>
              <div class="text-center">
                <input type="checkbox" v-model="permOthers.x" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              </div>
            </div>
          </div>

          <!-- Numeric Display -->
          <div class="mt-4 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('fileManager.numericPermissions') }}
            </label>
            <div class="flex items-center space-x-2">
              <VInput v-model="numericPermissions" class="w-24 font-mono" maxlength="4" />
              <span class="text-sm text-gray-500 dark:text-gray-400">
                ({{ permissionsString }})
              </span>
            </div>
          </div>

          <!-- Apply Recursively -->
          <div v-if="permissionsItem?.type === 'directory'" class="mt-4">
            <label class="flex items-center space-x-2">
              <input type="checkbox" v-model="applyRecursively" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
              <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('fileManager.applyRecursively') }}</span>
            </label>
          </div>
        </div>

        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showPermissionsModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="changingPermissions">
            {{ $t('common.save') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Compress Modal -->
    <VModal v-model:show="showCompressModal" :title="$t('fileManager.compress')">
      <form @submit.prevent="handleCompress">
        <div class="mb-4">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ $t('fileManager.compressingItems', { count: itemsToCompress.length }) }}
          </p>
          <ul class="text-sm text-gray-700 dark:text-gray-300 mb-4 max-h-32 overflow-y-auto">
            <li v-for="item in itemsToCompress" :key="item.name" class="flex items-center py-1">
              <component :is="getFileIcon(item)" class="w-4 h-4 mr-2" />
              {{ item.name }}
            </li>
          </ul>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('fileManager.archiveName') }}
          </label>
          <VInput v-model="archiveName" placeholder="archive.zip" required />
        </div>
        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showCompressModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="compressing">
            {{ $t('fileManager.compress') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Extract Modal -->
    <VModal v-model:show="showExtractModal" :title="$t('fileManager.extract')">
      <form @submit.prevent="handleExtract">
        <div class="mb-4">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ $t('fileManager.extractingFile') }}: <strong>{{ extractingFile?.name }}</strong>
          </p>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('fileManager.extractTo') }}
          </label>
          <VInput v-model="extractDestination" :placeholder="$t('fileManager.currentDirectory')" />
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {{ $t('fileManager.leaveEmptyForCurrent') }}
          </p>
        </div>
        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showExtractModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="extracting">
            {{ $t('fileManager.extract') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Search Content Modal -->
    <VModal v-model:show="showSearchModal" :title="$t('fileManager.searchFileContent')" size="lg">
      <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          {{ $t('fileManager.searchQuery') }}
        </label>
        <VInput v-model="contentSearchQuery" :placeholder="$t('fileManager.enterSearchTerm')" autofocus @keyup.enter="searchContent" />
      </div>

      <div v-if="searchResults.length > 0" class="mb-4">
        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          {{ $t('fileManager.searchResults') }} ({{ searchResults.length }})
        </h4>
        <div class="max-h-64 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg">
          <div
            v-for="result in searchResults"
            :key="result.path"
            @click="navigateToSearchResult(result)"
            class="p-3 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer border-b border-gray-200 dark:border-gray-700 last:border-0"
          >
            <div class="flex items-center">
              <component :is="getFileIcon(result)" class="w-4 h-4 mr-2 text-gray-400" />
              <span class="text-sm text-gray-900 dark:text-white">{{ result.path }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="flex justify-end space-x-3">
        <VButton variant="secondary" type="button" @click="showSearchModal = false">
          {{ $t('common.close') }}
        </VButton>
        <VButton variant="primary" :loading="searching" @click="searchContent">
          {{ $t('common.search') }}
        </VButton>
      </div>
    </VModal>

    <!-- Bulk Permissions Modal -->
    <VModal v-model:show="showBulkPermissionsModal" :title="$t('fileManager.changePermissions')">
      <form @submit.prevent="handleBulkChangePermissions">
        <div class="mb-4">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ $t('fileManager.changingPermissionsFor', { count: selectedItems.length }) }}
          </p>

          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('fileManager.numericPermissions') }}
          </label>
          <VInput v-model="bulkPermissions" placeholder="755" class="w-24 font-mono" maxlength="4" />
        </div>

        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showBulkPermissionsModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="changingPermissions">
            {{ $t('common.save') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Copy Modal -->
    <VModal v-model:show="showCopyModal" :title="$t('fileManager.copy')">
      <form @submit.prevent="handleCopy">
        <div class="mb-4">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ $t('fileManager.copyingItems', { count: selectedItems.length }) }}
          </p>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('fileManager.destinationPath') }}
          </label>
          <VInput v-model="copyDestination" :placeholder="currentPath || '/'" required />
        </div>
        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showCopyModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="copying">
            {{ $t('fileManager.copy') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Move Modal -->
    <VModal v-model:show="showMoveModal" :title="$t('fileManager.move')">
      <form @submit.prevent="handleMove">
        <div class="mb-4">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ $t('fileManager.movingItems', { count: selectedItems.length }) }}
          </p>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('fileManager.destinationPath') }}
          </label>
          <VInput v-model="moveDestination" :placeholder="currentPath || '/'" required />
        </div>
        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showMoveModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="moving">
            {{ $t('fileManager.move') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Remote Download Modal -->
    <VModal v-model:show="showRemoteDownloadModal" :title="$t('fileManager.remoteDownload')">
      <form @submit.prevent="handleRemoteDownload">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('fileManager.urlAddress') }}
            </label>
            <VInput
              v-model="remoteUrl"
              type="url"
              placeholder="https://example.com/file.zip"
              required
              autofocus
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              {{ $t('fileManager.urlHint') }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('fileManager.downloadTo') }}
            </label>
            <div class="flex space-x-2">
              <VInput
                v-model="remoteDownloadPath"
                :placeholder="currentPath || '/'"
                readonly
                class="flex-1"
              />
              <VButton variant="secondary" type="button" @click="openFolderBrowser">
                {{ $t('fileManager.browse') }}
              </VButton>
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              {{ $t('fileManager.downloadToHint') }}
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('fileManager.saveAsFilename') }}
            </label>
            <VInput
              v-model="remoteFilename"
              :placeholder="$t('fileManager.autoDetect')"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              {{ $t('fileManager.filenameHint') }}
            </p>
          </div>
        </div>

        <div class="flex justify-end space-x-3 mt-6">
          <VButton variant="secondary" type="button" @click="showRemoteDownloadModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="remoteDownloading" :disabled="!remoteUrl">
            {{ $t('fileManager.download') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Folder Browser Modal -->
    <VModal v-model:show="showFolderBrowserModal" :title="$t('fileManager.selectFolder')" size="lg">
      <div class="mb-4">
        <!-- Current Path -->
        <div class="flex items-center space-x-2 text-sm mb-4 p-2 bg-gray-100 dark:bg-gray-800 rounded-lg">
          <button
            @click="browsePath = ''"
            class="text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline"
          >
            <HomeIcon class="w-4 h-4" />
          </button>
          <span v-for="(part, index) in browsePathParts" :key="index" class="flex items-center">
            <ChevronRightIcon class="w-4 h-4 text-gray-400 mx-1" />
            <button
              @click="browsePath = browsePathParts.slice(0, index + 1).join('/')"
              class="text-primary-600 hover:text-primary-700 dark:text-primary-400 hover:underline"
            >
              {{ part }}
            </button>
          </span>
        </div>

        <!-- Folder List -->
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg max-h-64 overflow-y-auto">
          <VLoadingSkeleton v-if="loadingBrowseFolders" class="h-32 m-2" />
          <div v-else>
            <!-- Parent Directory -->
            <div
              v-if="browsePath"
              @click="navigateBrowseTo(browseParentPath)"
              class="flex items-center px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer border-b border-gray-200 dark:border-gray-700"
            >
              <ArrowUpIcon class="w-5 h-5 mr-3 text-gray-400" />
              <span class="text-gray-500 dark:text-gray-400">..</span>
            </div>
            <!-- Folders -->
            <div
              v-for="folder in browseFolders"
              :key="folder.name"
              @click="navigateBrowseTo(folder.path)"
              :class="[
                'flex items-center px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer border-b border-gray-200 dark:border-gray-700 last:border-0',
                selectedBrowseFolder === folder.path ? 'bg-primary-50 dark:bg-primary-900/20' : ''
              ]"
            >
              <FolderIcon class="w-5 h-5 mr-3 text-yellow-500" />
              <span class="text-gray-900 dark:text-white">{{ folder.name }}</span>
            </div>
            <!-- Empty State -->
            <div v-if="browseFolders.length === 0 && !browsePath" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
              {{ $t('fileManager.noFolders') }}
            </div>
          </div>
        </div>

        <!-- Selected Path Display -->
        <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <span class="text-sm text-gray-600 dark:text-gray-400">{{ $t('fileManager.selectedPath') }}:</span>
          <span class="text-sm font-medium text-gray-900 dark:text-white ml-2">
            /{{ browsePath || '' }}
          </span>
        </div>
      </div>

      <div class="flex justify-end space-x-3">
        <VButton variant="secondary" @click="showFolderBrowserModal = false">
          {{ $t('common.cancel') }}
        </VButton>
        <VButton variant="primary" @click="selectBrowseFolder">
          {{ $t('fileManager.selectThisFolder') }}
        </VButton>
      </div>
    </VModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, markRaw, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VInput from '@/components/ui/VInput.vue'
import VModal from '@/components/ui/VModal.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import CodeEditor from '@/components/filemanager/CodeEditor.vue'
import ImagePreview from '@/components/filemanager/ImagePreview.vue'
import {
  GlobeAltIcon,
  HomeIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  FolderIcon,
  FolderPlusIcon,
  DocumentIcon,
  DocumentPlusIcon,
  DocumentDuplicateIcon,
  ArrowUpTrayIcon,
  CloudArrowDownIcon,
  ArrowDownTrayIcon,
  ArrowPathIcon,
  ArrowLeftIcon,
  ArrowUpIcon,
  ArrowRightIcon,
  PencilIcon,
  PencilSquareIcon,
  TrashIcon,
  PhotoIcon,
  CodeBracketIcon,
  Squares2X2Icon,
  Bars3Icon,
  CircleStackIcon,
  ArchiveBoxIcon,
  ArchiveBoxArrowDownIcon,
  LockClosedIcon,
  MagnifyingGlassIcon,
  PlusIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const appStore = useAppStore()

// State
const domains = ref([])
const loadingDomains = ref(false)
const selectedDomain = ref(null)
const domainViewMode = ref('grid') // 'grid' or 'list'
const files = ref([])
const currentPath = ref('')
const loading = ref(false)
const searchQuery = ref('')
const diskUsage = ref('0 B')

// Selection state
const selectedItems = ref([])

// Dropdown state
const showNewDropdown = ref(false)
const newDropdownRef = ref(null)

// Modal states
const showNewFolderModal = ref(false)
const showNewFileModal = ref(false)
const showUploadModal = ref(false)
const showEditModal = ref(false)
const showRenameModalFlag = ref(false)
const showPermissionsModal = ref(false)
const showCompressModal = ref(false)
const showExtractModal = ref(false)
const showSearchModal = ref(false)
const showRemoteDownloadModal = ref(false)
const showBulkPermissionsModal = ref(false)
const showCopyModal = ref(false)
const showMoveModal = ref(false)
const showFolderBrowserModal = ref(false)

// Folder browser state
const browsePath = ref('')
const browseFolders = ref([])
const loadingBrowseFolders = ref(false)
const selectedBrowseFolder = ref('')

// Form states
const newFolderName = ref('')
const newFileName = ref('')
const selectedFiles = ref([])
const editingFile = ref(null)
const fileContent = ref('')
const codeEditorRef = ref(null)
const showPreviewModal = ref(false)
const previewFile = ref(null)
const previewUrl = ref('')
const previewInfo = ref(null)
const isDarkMode = ref(document.documentElement.classList.contains('dark'))

// Watch for dark mode changes
const darkModeObserver = new MutationObserver(() => {
  isDarkMode.value = document.documentElement.classList.contains('dark')
})
darkModeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
const renamingItem = ref(null)
const renameNewName = ref('')

// Permissions state
const permissionsItem = ref(null)
const permOwner = ref({ r: false, w: false, x: false })
const permGroup = ref({ r: false, w: false, x: false })
const permOthers = ref({ r: false, w: false, x: false })
const applyRecursively = ref(false)
const bulkPermissions = ref('755')

// Compress/Extract state
const itemsToCompress = ref([])
const archiveName = ref('')
const extractingFile = ref(null)
const extractDestination = ref('')

// Search state
const contentSearchQuery = ref('')
const searchResults = ref([])

// Copy/Move state
const copyDestination = ref('')
const moveDestination = ref('')

// Remote download state
const remoteUrl = ref('')
const remoteDownloadPath = ref('')
const remoteFilename = ref('')

// Loading states
const creatingFolder = ref(false)
const creatingFile = ref(false)
const uploading = ref(false)
const savingFile = ref(false)
const renaming = ref(false)
const changingPermissions = ref(false)
const compressing = ref(false)
const extracting = ref(false)
const searching = ref(false)
const copying = ref(false)
const moving = ref(false)
const remoteDownloading = ref(false)

// Computed
const pathParts = computed(() => {
  return currentPath.value ? currentPath.value.split('/').filter(Boolean) : []
})

const parentPath = computed(() => {
  const parts = pathParts.value.slice(0, -1)
  return parts.join('/')
})

const filteredFiles = computed(() => {
  if (!searchQuery.value) return files.value
  const query = searchQuery.value.toLowerCase()
  return files.value.filter(f => f.name.toLowerCase().includes(query))
})

const allSelected = computed(() => {
  return filteredFiles.value.length > 0 && selectedItems.value.length === filteredFiles.value.length
})

const someSelected = computed(() => {
  return selectedItems.value.length > 0
})

const numericPermissions = computed({
  get() {
    const owner = (permOwner.value.r ? 4 : 0) + (permOwner.value.w ? 2 : 0) + (permOwner.value.x ? 1 : 0)
    const group = (permGroup.value.r ? 4 : 0) + (permGroup.value.w ? 2 : 0) + (permGroup.value.x ? 1 : 0)
    const others = (permOthers.value.r ? 4 : 0) + (permOthers.value.w ? 2 : 0) + (permOthers.value.x ? 1 : 0)
    return `${owner}${group}${others}`
  },
  set(val) {
    if (val.length >= 3) {
      const digits = val.slice(-3).split('').map(Number)
      permOwner.value = { r: digits[0] >= 4, w: digits[0] % 4 >= 2, x: digits[0] % 2 === 1 }
      permGroup.value = { r: digits[1] >= 4, w: digits[1] % 4 >= 2, x: digits[1] % 2 === 1 }
      permOthers.value = { r: digits[2] >= 4, w: digits[2] % 4 >= 2, x: digits[2] % 2 === 1 }
    }
  }
})

const permissionsString = computed(() => {
  const r = (p) => (p.r ? 'r' : '-') + (p.w ? 'w' : '-') + (p.x ? 'x' : '-')
  return r(permOwner.value) + r(permGroup.value) + r(permOthers.value)
})

const browsePathParts = computed(() => {
  return browsePath.value ? browsePath.value.split('/').filter(Boolean) : []
})

const browseParentPath = computed(() => {
  const parts = browsePathParts.value.slice(0, -1)
  return parts.join('/')
})

// Methods
function getFileIcon(item) {
  if (item.type === 'directory') return markRaw(FolderIcon)
  const ext = item.extension?.toLowerCase()
  if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'].includes(ext)) return markRaw(PhotoIcon)
  if (['php', 'js', 'ts', 'vue', 'html', 'css', 'json', 'xml', 'yaml', 'yml'].includes(ext)) return markRaw(CodeBracketIcon)
  if (['zip', 'gz', 'tar', 'rar', '7z', 'tgz', 'bz2', 'tbz2', 'tbz'].includes(ext)) return markRaw(ArchiveBoxIcon)
  return markRaw(DocumentIcon)
}

function isArchive(item) {
  if (item.type !== 'file') return false
  const ext = item.extension?.toLowerCase()
  const name = item.name?.toLowerCase() || ''
  // Check for compound extensions first
  if (name.endsWith('.tar.gz') || name.endsWith('.tar.bz2')) {
    return true
  }
  return ['zip', 'gz', 'tar', 'rar', '7z', 'tgz', 'bz2', 'tbz2', 'tbz'].includes(ext)
}

function getArchiveLabel(item) {
  const name = item.name?.toLowerCase() || ''
  const ext = item.extension?.toLowerCase()
  if (name.endsWith('.tar.gz') || ext === 'tgz') return 'TAR.GZ'
  if (name.endsWith('.tar.bz2') || ext === 'tbz2' || ext === 'tbz') return 'TAR.BZ2'
  if (ext === 'gz') return 'GZ'
  if (ext === 'tar') return 'TAR'
  if (ext === 'bz2') return 'BZ2'
  return ext?.toUpperCase() || 'ZIP'
}

function formatSize(bytes) {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  return `${size.toFixed(1)} ${units[unitIndex]}`
}

function formatDiskUsage(bytes) {
  if (!bytes) return '0 MB'
  const mb = bytes / (1024 * 1024)
  if (mb < 1024) {
    return `${mb.toFixed(1)} MB`
  }
  const gb = mb / 1024
  return `${gb.toFixed(2)} GB`
}

function getDiskUsagePercent(used, total) {
  if (!total || !used) return 0
  return (used / total) * 100
}

function getDiskUsageClass(used, total) {
  const percent = getDiskUsagePercent(used, total)
  if (percent >= 90) return 'bg-red-500'
  if (percent >= 70) return 'bg-yellow-500'
  return 'bg-green-500'
}

function formatDateTime(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString()
}

// Selection methods
function isSelected(item) {
  return selectedItems.value.some(i => i.name === item.name && i.path === item.path)
}

function toggleSelect(item) {
  const index = selectedItems.value.findIndex(i => i.name === item.name && i.path === item.path)
  if (index >= 0) {
    selectedItems.value.splice(index, 1)
  } else {
    selectedItems.value.push(item)
  }
}

function toggleSelectAll() {
  if (allSelected.value) {
    selectedItems.value = []
  } else {
    selectedItems.value = [...filteredFiles.value]
  }
}

function clearSelection() {
  selectedItems.value = []
}

// API methods
async function fetchDomains() {
  loadingDomains.value = true
  try {
    const response = await api.get('/domains')
    domains.value = response.data.data || []
  } catch (err) {
    console.error('Failed to fetch domains:', err)
  } finally {
    loadingDomains.value = false
  }
}

function selectDomain(domain) {
  selectedDomain.value = domain
  currentPath.value = ''
  selectedItems.value = []
  fetchFiles()
  fetchDiskUsage()
}

async function fetchFiles() {
  if (!selectedDomain.value) return
  loading.value = true
  selectedItems.value = []
  try {
    const response = await api.get(`/domains/${selectedDomain.value.id}/files`, {
      params: { path: currentPath.value }
    })
    files.value = response.data.data?.items || []
  } catch (err) {
    console.error('Failed to fetch files:', err)
    appStore.showToast({
      type: 'error',
      message: t('fileManager.loadError')
    })
  } finally {
    loading.value = false
  }
}

async function fetchDiskUsage() {
  if (!selectedDomain.value) return
  try {
    const response = await api.get(`/domains/${selectedDomain.value.id}/files/disk-usage`)
    diskUsage.value = response.data.data?.formatted || '0 B'
  } catch (err) {
    console.error('Failed to fetch disk usage:', err)
  }
}

function navigateTo(path) {
  currentPath.value = path
  selectedItems.value = []
  fetchFiles()
}

function handleItemClick(item) {
  if (item.type === 'directory') {
    const newPath = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
    navigateTo(newPath)
  }
}

async function editFile(item) {
  const path = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
  const ext = (item.extension || item.name.split('.').pop() || '').toLowerCase()
  const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp']
  const videoExts = ['mp4', 'webm', 'ogg']
  const pdfExts = ['pdf']

  // Preview mode for images/videos/PDFs
  if (imageExts.includes(ext) || videoExts.includes(ext) || pdfExts.includes(ext)) {
    try {
      const { data } = await api.get(`/domains/${selectedDomain.value.id}/files/preview`, { params: { path } })
      previewFile.value = item
      previewInfo.value = data.data
      previewUrl.value = `/api/v1/domains/${selectedDomain.value.id}/files/download?path=${encodeURIComponent(path)}`
      showPreviewModal.value = true
    } catch (err) {
      appStore.showToast({ type: 'error', message: t('fileManager.readError') })
    }
    return
  }

  // Code editor mode for text files
  try {
    const response = await api.get(`/domains/${selectedDomain.value.id}/files/content`, {
      params: { path }
    })
    const content = response.data.data?.content || ''
    const syntaxMap = {
      php: 'php', phtml: 'php',
      html: 'html', htm: 'html',
      css: 'css', scss: 'css', less: 'css',
      js: 'javascript', jsx: 'javascript', ts: 'javascript', tsx: 'javascript', mjs: 'javascript',
      json: 'json',
      xml: 'xml', svg: 'xml',
      md: 'markdown', markdown: 'markdown',
      sql: 'sql',
    }
    const syntax = syntaxMap[ext] || 'text'
    editingFile.value = item
    fileContent.value = content
    showEditModal.value = true

    // Open in CodeEditor after modal is visible
    setTimeout(() => {
      if (codeEditorRef.value) {
        codeEditorRef.value.openFile(path, item.name, content, syntax, item.size || 0)
      }
    }, 100)
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.readError')
    })
  }
}

async function handleEditorSave({ path, content, callback }) {
  savingFile.value = true
  try {
    await api.put(`/domains/${selectedDomain.value.id}/files/content`, { path, content })
    appStore.showToast({ type: 'success', message: t('fileManager.fileSaved') })
    callback(true)
    fetchFiles()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('fileManager.saveError') })
    callback(false)
  }
  savingFile.value = false
}

async function saveFile() {
  // If CodeEditor is active, delegate to it
  if (codeEditorRef.value && codeEditorRef.value.activeTabPath) {
    const tab = codeEditorRef.value.tabs.find(t => t.path === codeEditorRef.value.activeTabPath)
    if (tab) {
      await handleEditorSave({ path: tab.path, content: tab.content, callback: (ok) => { if (ok) tab.modified = false } })
    }
    return
  }
  if (!editingFile.value) return
  savingFile.value = true
  const path = currentPath.value ? `${currentPath.value}/${editingFile.value.name}` : editingFile.value.name
  try {
    await api.put(`/domains/${selectedDomain.value.id}/files/content`, {
      path,
      content: fileContent.value
    })
    showEditModal.value = false
    editingFile.value = null
    fileContent.value = ''
    appStore.showToast({
      type: 'success',
      message: t('fileManager.fileSaved')
    })
    fetchFiles()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.saveError')
    })
  } finally {
    savingFile.value = false
  }
}

async function downloadFile(item) {
  const path = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
  try {
    const response = await api.get(`/domains/${selectedDomain.value.id}/files/download`, {
      params: { path },
      responseType: 'blob'
    })
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', item.name)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('fileManager.downloadError')
    })
  }
}

async function downloadSelected() {
  for (const item of selectedItems.value) {
    if (item.type === 'file') {
      await downloadFile(item)
    }
  }
}

async function confirmDelete(item) {
  if (!confirm(t('fileManager.deleteConfirm', { name: item.name }))) {
    return
  }
  const path = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
  try {
    await api.delete(`/domains/${selectedDomain.value.id}/files`, {
      data: { paths: [path] }
    })
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.deleteSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('fileManager.deleteError')
    })
  }
}

async function deleteSelected() {
  if (!confirm(t('fileManager.deleteMultipleConfirm', { count: selectedItems.value.length }))) {
    return
  }
  const paths = selectedItems.value.map(item =>
    currentPath.value ? `${currentPath.value}/${item.name}` : item.name
  )
  try {
    await api.delete(`/domains/${selectedDomain.value.id}/files`, {
      data: { paths }
    })
    selectedItems.value = []
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.deleteSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('fileManager.deleteError')
    })
  }
}

async function createFolder() {
  creatingFolder.value = true
  const path = currentPath.value ? `${currentPath.value}/${newFolderName.value}` : newFolderName.value
  try {
    await api.post(`/domains/${selectedDomain.value.id}/files/directory`, { path })
    showNewFolderModal.value = false
    newFolderName.value = ''
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.folderCreated')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.createError')
    })
  } finally {
    creatingFolder.value = false
  }
}

async function createFile() {
  creatingFile.value = true
  const path = currentPath.value ? `${currentPath.value}/${newFileName.value}` : newFileName.value
  try {
    await api.post(`/domains/${selectedDomain.value.id}/files/file`, { path, content: '' })
    showNewFileModal.value = false
    newFileName.value = ''
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.fileCreated')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.createError')
    })
  } finally {
    creatingFile.value = false
  }
}

function handleFileSelect(event) {
  selectedFiles.value = [...selectedFiles.value, ...Array.from(event.target.files)]
}

function handleDrop(event) {
  selectedFiles.value = [...selectedFiles.value, ...Array.from(event.dataTransfer.files)]
}

function removeFile(index) {
  selectedFiles.value.splice(index, 1)
}

async function uploadFiles() {
  uploading.value = true
  try {
    const formData = new FormData()
    formData.append('path', currentPath.value || '/')
    selectedFiles.value.forEach((file, index) => {
      formData.append(`files[${index}]`, file)
    })

    await api.post(`/domains/${selectedDomain.value.id}/files/upload`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    showUploadModal.value = false
    selectedFiles.value = []
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.uploadSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.uploadError')
    })
  } finally {
    uploading.value = false
  }
}

function showRenameModal(item) {
  renamingItem.value = item
  renameNewName.value = item.name
  showRenameModalFlag.value = true
}

async function handleRename() {
  if (!renamingItem.value) return
  renaming.value = true
  const path = currentPath.value ? `${currentPath.value}/${renamingItem.value.name}` : renamingItem.value.name
  try {
    await api.post(`/domains/${selectedDomain.value.id}/files/rename`, {
      path,
      new_name: renameNewName.value
    })
    showRenameModalFlag.value = false
    renamingItem.value = null
    renameNewName.value = ''
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.renameSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.renameError')
    })
  } finally {
    renaming.value = false
  }
}

// Permissions
function showPermissions(item) {
  permissionsItem.value = item
  const perms = item.permissions || '0755'
  const numeric = perms.slice(-3)
  numericPermissions.value = numeric
  applyRecursively.value = false
  showPermissionsModal.value = true
}

async function handleChangePermissions() {
  if (!permissionsItem.value) return
  changingPermissions.value = true
  const path = currentPath.value ? `${currentPath.value}/${permissionsItem.value.name}` : permissionsItem.value.name
  try {
    await api.post(`/domains/${selectedDomain.value.id}/files/permissions`, {
      path,
      permissions: numericPermissions.value
    })
    showPermissionsModal.value = false
    permissionsItem.value = null
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.permissionsChanged')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.permissionsError')
    })
  } finally {
    changingPermissions.value = false
  }
}

async function handleBulkChangePermissions() {
  changingPermissions.value = true
  try {
    for (const item of selectedItems.value) {
      const path = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
      await api.post(`/domains/${selectedDomain.value.id}/files/permissions`, {
        path,
        permissions: bulkPermissions.value
      })
    }
    showBulkPermissionsModal.value = false
    selectedItems.value = []
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.permissionsChanged')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.permissionsError')
    })
  } finally {
    changingPermissions.value = false
  }
}

// Compress
function compressSingle(item) {
  itemsToCompress.value = [item]
  archiveName.value = item.name + '.zip'
  showCompressModal.value = true
}

function openCompressModal() {
  itemsToCompress.value = [...selectedItems.value]
  archiveName.value = 'archive.zip'
  showCompressModal.value = true
}

// Watch for showCompressModal to update items
watch(showCompressModal, (val) => {
  if (val && selectedItems.value.length > 0 && itemsToCompress.value.length === 0) {
    itemsToCompress.value = [...selectedItems.value]
  }
})

async function handleCompress() {
  compressing.value = true
  const paths = itemsToCompress.value.map(item =>
    currentPath.value ? `${currentPath.value}/${item.name}` : item.name
  )
  try {
    await api.post(`/domains/${selectedDomain.value.id}/files/compress`, {
      paths,
      archive_name: archiveName.value
    })
    showCompressModal.value = false
    itemsToCompress.value = []
    archiveName.value = ''
    selectedItems.value = []
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.compressSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.compressError')
    })
  } finally {
    compressing.value = false
  }
}

// Extract
function extractFile(item) {
  extractingFile.value = item
  extractDestination.value = ''
  showExtractModal.value = true
}

async function handleExtract() {
  if (!extractingFile.value) return
  extracting.value = true
  const path = currentPath.value ? `${currentPath.value}/${extractingFile.value.name}` : extractingFile.value.name
  try {
    await api.post(`/domains/${selectedDomain.value.id}/files/extract`, {
      path,
      destination: extractDestination.value || null
    })
    showExtractModal.value = false
    extractingFile.value = null
    extractDestination.value = ''
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.extractSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.extractError')
    })
  } finally {
    extracting.value = false
  }
}

// Search
async function searchContent() {
  if (!contentSearchQuery.value || contentSearchQuery.value.length < 2) return
  searching.value = true
  try {
    const response = await api.get(`/domains/${selectedDomain.value.id}/files/search`, {
      params: { query: contentSearchQuery.value, path: currentPath.value }
    })
    searchResults.value = response.data.data?.results || []
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('fileManager.searchError')
    })
  } finally {
    searching.value = false
  }
}

function navigateToSearchResult(result) {
  const parts = result.path.split('/')
  if (result.type === 'directory') {
    navigateTo(result.path)
  } else {
    const dirPath = parts.slice(0, -1).join('/')
    navigateTo(dirPath)
  }
  showSearchModal.value = false
  searchResults.value = []
  contentSearchQuery.value = ''
}

function handleSearch() {
  // This is for the filter input, just triggers reactivity
}

// Copy/Move
async function handleCopy() {
  copying.value = true
  try {
    for (const item of selectedItems.value) {
      const source = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
      const dest = copyDestination.value ? `${copyDestination.value}/${item.name}` : item.name
      await api.post(`/domains/${selectedDomain.value.id}/files/copy`, {
        source,
        destination: dest
      })
    }
    showCopyModal.value = false
    copyDestination.value = ''
    selectedItems.value = []
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.copySuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.copyError')
    })
  } finally {
    copying.value = false
  }
}

async function handleMove() {
  moving.value = true
  try {
    for (const item of selectedItems.value) {
      const source = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
      const dest = moveDestination.value ? `${moveDestination.value}/${item.name}` : item.name
      await api.post(`/domains/${selectedDomain.value.id}/files/move`, {
        source,
        destination: dest
      })
    }
    showMoveModal.value = false
    moveDestination.value = ''
    selectedItems.value = []
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.moveSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.moveError')
    })
  } finally {
    moving.value = false
  }
}

// Remote Download
async function handleRemoteDownload() {
  if (!remoteUrl.value) return
  remoteDownloading.value = true
  try {
    await api.post(`/domains/${selectedDomain.value.id}/files/remote-download`, {
      url: remoteUrl.value,
      path: remoteDownloadPath.value || currentPath.value || '',
      filename: remoteFilename.value || null
    })
    showRemoteDownloadModal.value = false
    remoteUrl.value = ''
    remoteFilename.value = ''
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.remoteDownloadSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.remoteDownloadError')
    })
  } finally {
    remoteDownloading.value = false
  }
}

// Watch for remote download modal to set default path
watch(showRemoteDownloadModal, (val) => {
  if (val) {
    remoteDownloadPath.value = currentPath.value || ''
  }
})

// Folder Browser
function openFolderBrowser() {
  browsePath.value = remoteDownloadPath.value || currentPath.value || ''
  selectedBrowseFolder.value = browsePath.value
  fetchBrowseFolders()
  showFolderBrowserModal.value = true
}

async function fetchBrowseFolders() {
  if (!selectedDomain.value) return
  loadingBrowseFolders.value = true
  try {
    const response = await api.get(`/domains/${selectedDomain.value.id}/files`, {
      params: { path: browsePath.value }
    })
    const items = response.data.data?.items || []
    // Filter only directories
    browseFolders.value = items.filter(item => item.type === 'directory')
  } catch (err) {
    console.error('Failed to fetch folders:', err)
    browseFolders.value = []
  } finally {
    loadingBrowseFolders.value = false
  }
}

function navigateBrowseTo(path) {
  browsePath.value = path
  selectedBrowseFolder.value = path
  fetchBrowseFolders()
}

function selectBrowseFolder() {
  remoteDownloadPath.value = browsePath.value
  showFolderBrowserModal.value = false
}

// Close dropdown on outside click
function handleClickOutside(event) {
  if (newDropdownRef.value && !newDropdownRef.value.contains(event.target)) {
    showNewDropdown.value = false
  }
}

// Check for domain query parameter
watch(() => route.query.domain, async (domainId) => {
  if (domainId && domains.value.length > 0) {
    const domain = domains.value.find(d => d.id === domainId)
    if (domain) {
      selectDomain(domain)
    }
  }
}, { immediate: true })

// Lifecycle
onMounted(async () => {
  document.addEventListener('click', handleClickOutside)
  await fetchDomains()
  if (route.query.domain) {
    const domain = domains.value.find(d => d.id === route.query.domain)
    if (domain) {
      selectDomain(domain)
    }
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
  darkModeObserver.disconnect()
})
</script>
