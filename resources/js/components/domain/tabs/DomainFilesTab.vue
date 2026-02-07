<template>
  <VCard :padding="false">
    <div class="p-4 border-b border-gray-200 dark:border-gray-700">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $t('domainDetail.fileManager') }}
          </h3>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $t('domainDetail.documentRoot') }}: {{ domain.document_root }}
          </p>
        </div>
        <VButton
          variant="secondary"
          :icon="ArrowTopRightOnSquareIcon"
          @click="openFullFileManager"
        >
          {{ $t('domainDetail.openFullFileManager') }}
        </VButton>
      </div>
    </div>

    <!-- Embedded File Browser -->
    <div class="p-4">
      <!-- Breadcrumb -->
      <div class="flex items-center space-x-2 mb-4 text-sm">
        <button
          @click="navigateTo('')"
          class="text-primary-600 hover:text-primary-700 dark:text-primary-400"
        >
          <HomeIcon class="w-4 h-4" />
        </button>
        <span v-for="(part, index) in pathParts" :key="index" class="flex items-center">
          <ChevronRightIcon class="w-4 h-4 text-gray-400 mx-1" />
          <button
            @click="navigateTo(pathParts.slice(0, index + 1).join('/'))"
            class="text-primary-600 hover:text-primary-700 dark:text-primary-400"
          >
            {{ part }}
          </button>
        </span>
      </div>

      <!-- Loading State -->
      <VLoadingSkeleton v-if="loading" class="h-64" />

      <!-- File List -->
      <div v-else class="border rounded-lg dark:border-gray-700 overflow-hidden">
        <!-- Toolbar -->
        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 border-b dark:border-gray-700">
          <div class="flex items-center space-x-2">
            <VButton variant="secondary" size="sm" :icon="FolderPlusIcon" @click="showNewFolderModal = true">
              {{ $t('fileManager.newFolder') }}
            </VButton>
            <VButton variant="secondary" size="sm" :icon="DocumentPlusIcon" @click="showNewFileModal = true">
              {{ $t('fileManager.newFile') }}
            </VButton>
            <VButton variant="secondary" size="sm" :icon="ArrowUpTrayIcon" @click="showUploadModal = true">
              {{ $t('fileManager.upload') }}
            </VButton>
          </div>
          <VButton variant="ghost" size="sm" :icon="ArrowPathIcon" :loading="loading" @click="fetchFiles">
            {{ $t('common.refresh') }}
          </VButton>
        </div>

        <!-- Files Table -->
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                {{ $t('fileManager.name') }}
              </th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                {{ $t('fileManager.size') }}
              </th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                {{ $t('fileManager.modified') }}
              </th>
              <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
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
              <td class="px-4 py-3" colspan="4">
                <div class="flex items-center text-gray-500 dark:text-gray-400">
                  <ArrowUpIcon class="w-5 h-5 mr-3" />
                  <span>..</span>
                </div>
              </td>
            </tr>
            <!-- Files & Directories -->
            <tr
              v-for="item in files"
              :key="item.name"
              @click="handleItemClick(item)"
              class="hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
            >
              <td class="px-4 py-3">
                <div class="flex items-center">
                  <component
                    :is="getFileIcon(item)"
                    :class="[
                      'w-5 h-5 mr-3',
                      item.type === 'directory' ? 'text-yellow-500' : 'text-gray-400'
                    ]"
                  />
                  <span class="text-gray-900 dark:text-white">{{ item.name }}</span>
                </div>
              </td>
              <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                {{ item.type === 'directory' ? '-' : formatSize(item.size) }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                {{ formatDateTime(item.modified_at) }}
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end space-x-1" @click.stop>
                  <VButton
                    v-if="item.type === 'file'"
                    variant="ghost"
                    size="sm"
                    :icon="PencilIcon"
                    @click="editFile(item)"
                  />
                  <VButton
                    v-if="item.type === 'file'"
                    variant="ghost"
                    size="sm"
                    :icon="ArrowDownTrayIcon"
                    @click="downloadFile(item)"
                  />
                  <VButton
                    variant="ghost"
                    size="sm"
                    :icon="TrashIcon"
                    class="text-red-500"
                    @click="confirmDelete(item)"
                  />
                </div>
              </td>
            </tr>
            <!-- Empty State -->
            <tr v-if="!loading && files.length === 0">
              <td colspan="4" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                {{ $t('fileManager.emptyDirectory') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- New Folder Modal -->
    <VModal v-model:show="showNewFolderModal" :title="$t('fileManager.newFolder')">
      <form @submit.prevent="createFolder">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('fileManager.folderName') || 'Folder Name' }}
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
            {{ $t('fileManager.fileName') || 'File Name' }}
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
    <VModal v-model:show="showUploadModal" :title="$t('fileManager.upload')">
      <form @submit.prevent="uploadFiles">
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ $t('fileManager.selectFiles') || 'Select Files' }}
          </label>
          <div
            class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center cursor-pointer hover:border-primary-500 transition-colors"
            @click="$refs.fileInput.click()"
            @drop.prevent="handleDrop"
            @dragover.prevent
          >
            <ArrowUpTrayIcon class="w-10 h-10 mx-auto text-gray-400 mb-2" />
            <p class="text-gray-600 dark:text-gray-400">
              {{ $t('fileManager.dropFiles') || 'Click or drop files here' }}
            </p>
            <input
              ref="fileInput"
              type="file"
              multiple
              class="hidden"
              @change="handleFileSelect"
            />
          </div>
          <div v-if="selectedFiles.length > 0" class="mt-3 space-y-2">
            <div v-for="(file, index) in selectedFiles" :key="index" class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 px-3 py-2 rounded">
              <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ file.name }}</span>
              <button type="button" @click="removeFile(index)" class="text-red-500 hover:text-red-700">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
        <div class="flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showUploadModal = false; selectedFiles = []">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="uploading" :disabled="selectedFiles.length === 0">
            {{ $t('fileManager.upload') }}
          </VButton>
        </div>
      </form>
    </VModal>
  </VCard>
</template>

<script setup>
import { ref, computed, onMounted, markRaw } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VInput from '@/components/ui/VInput.vue'
import VModal from '@/components/ui/VModal.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import {
  HomeIcon,
  ChevronRightIcon,
  FolderIcon,
  FolderPlusIcon,
  DocumentIcon,
  DocumentPlusIcon,
  ArrowUpTrayIcon,
  ArrowDownTrayIcon,
  ArrowPathIcon,
  ArrowTopRightOnSquareIcon,
  ArrowUpIcon,
  PencilIcon,
  TrashIcon,
  PhotoIcon,
  CodeBracketIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  domain: {
    type: Object,
    required: true
  }
})

const router = useRouter()
const { t } = useI18n()
const appStore = useAppStore()

// State
const files = ref([])
const currentPath = ref('')
const loading = ref(false)
const showNewFolderModal = ref(false)
const showNewFileModal = ref(false)
const showUploadModal = ref(false)
const newFolderName = ref('')
const newFileName = ref('')
const creatingFolder = ref(false)
const creatingFile = ref(false)
const uploading = ref(false)
const selectedFiles = ref([])

// Computed
const pathParts = computed(() => {
  return currentPath.value ? currentPath.value.split('/').filter(Boolean) : []
})

const parentPath = computed(() => {
  const parts = pathParts.value.slice(0, -1)
  return parts.join('/')
})

// Methods
function getFileIcon(item) {
  if (item.type === 'directory') return markRaw(FolderIcon)
  const ext = item.extension?.toLowerCase()
  if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'].includes(ext)) return markRaw(PhotoIcon)
  if (['php', 'js', 'ts', 'vue', 'html', 'css', 'json'].includes(ext)) return markRaw(CodeBracketIcon)
  return markRaw(DocumentIcon)
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

function formatDateTime(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleString()
}

async function fetchFiles() {
  loading.value = true
  try {
    const response = await api.get(`/domains/${props.domain.id}/files`, {
      params: { path: currentPath.value }
    })
    // Backend returns { path, items, parent } - extract items array
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

function navigateTo(path) {
  currentPath.value = path
  fetchFiles()
}

function handleItemClick(item) {
  if (item.type === 'directory') {
    const newPath = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
    navigateTo(newPath)
  }
}

function editFile(item) {
  const path = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
  router.push({
    name: 'files',
    query: {
      domain: props.domain.id,
      path: path,
      edit: 'true'
    }
  })
}

async function downloadFile(item) {
  const path = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
  try {
    const response = await api.get(`/domains/${props.domain.id}/files/download`, {
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

async function confirmDelete(item) {
  if (!confirm(t('fileManager.deleteConfirm', { name: item.name }))) {
    return
  }
  const path = currentPath.value ? `${currentPath.value}/${item.name}` : item.name
  try {
    await api.delete(`/domains/${props.domain.id}/files`, {
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

function openFullFileManager() {
  router.push({ name: 'files', query: { domain: props.domain.id } })
}

async function createFolder() {
  creatingFolder.value = true
  const path = currentPath.value ? `${currentPath.value}/${newFolderName.value}` : newFolderName.value
  try {
    await api.post(`/domains/${props.domain.id}/files/directory`, { path })
    showNewFolderModal.value = false
    newFolderName.value = ''
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.folderCreated') || 'Folder created successfully'
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.createError') || 'Failed to create folder'
    })
  } finally {
    creatingFolder.value = false
  }
}

async function createFile() {
  creatingFile.value = true
  const path = currentPath.value ? `${currentPath.value}/${newFileName.value}` : newFileName.value
  try {
    await api.post(`/domains/${props.domain.id}/files/file`, { path, content: '' })
    showNewFileModal.value = false
    newFileName.value = ''
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.fileCreated') || 'File created successfully'
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.createError') || 'Failed to create file'
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

    await api.post(`/domains/${props.domain.id}/files/upload`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    showUploadModal.value = false
    selectedFiles.value = []
    fetchFiles()
    appStore.showToast({
      type: 'success',
      message: t('fileManager.uploadSuccess') || 'Files uploaded successfully'
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('fileManager.uploadError') || 'Failed to upload files'
    })
  } finally {
    uploading.value = false
  }
}

// Lifecycle
onMounted(() => {
  fetchFiles()
})
</script>
