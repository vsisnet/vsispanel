<template>
  <div class="flex flex-col h-full bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-2 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
      <span class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ fileName }}</span>
      <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
        <XMarkIcon class="w-5 h-5" />
      </button>
    </div>

    <!-- Preview Content -->
    <div class="flex-1 min-h-0 flex items-center justify-center p-4 overflow-auto">
      <!-- Image -->
      <img v-if="isImage" :src="previewUrl" :alt="fileName"
        class="max-w-full max-h-full object-contain rounded shadow-lg" />

      <!-- Video -->
      <video v-else-if="isVideo" :src="previewUrl" controls
        class="max-w-full max-h-full rounded shadow-lg">
      </video>

      <!-- PDF -->
      <iframe v-else-if="isPdf" :src="previewUrl" class="w-full h-full border-0 rounded"></iframe>

      <!-- Unsupported -->
      <div v-else class="text-center text-gray-400">
        <DocumentIcon class="w-16 h-16 mx-auto mb-3" />
        <p>{{ $t('fileManager.previewNotSupported') }}</p>
      </div>
    </div>

    <!-- Info Bar -->
    <div v-if="fileInfo" class="px-4 py-2 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-4">
      <span v-if="fileInfo.width">{{ fileInfo.width }} x {{ fileInfo.height }}px</span>
      <span>{{ formatSize(fileInfo.size) }}</span>
      <span>{{ fileInfo.mime_type }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { XMarkIcon, DocumentIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  fileName: { type: String, default: '' },
  previewUrl: { type: String, default: '' },
  fileInfo: { type: Object, default: null },
})

defineEmits(['close'])

const isImage = computed(() => props.fileInfo?.is_image)
const isVideo = computed(() => props.fileInfo?.is_video)
const isPdf = computed(() => props.fileInfo?.is_pdf)

function formatSize(bytes) {
  if (!bytes) return ''
  const units = ['B', 'KB', 'MB', 'GB']
  let i = 0
  let size = bytes
  while (size >= 1024 && i < units.length - 1) { size /= 1024; i++ }
  return `${size.toFixed(i > 0 ? 1 : 0)} ${units[i]}`
}
</script>
