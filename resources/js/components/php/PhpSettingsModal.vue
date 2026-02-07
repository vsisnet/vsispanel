<template>
  <VModal
    :show="show"
    :title="$t('php.settingsTitle')"
    size="lg"
    @close="handleClose"
  >
    <div v-if="loading" class="flex justify-center py-8">
      <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
    </div>

    <form v-else @submit.prevent="handleSubmit">
      <div class="space-y-6">
        <!-- PHP Version Selection -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ $t('php.version') }}
          </label>
          <select
            v-model="form.php_version"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option v-for="version in availableVersions" :key="version.version" :value="version.version" :disabled="!version.installed">
              PHP {{ version.version }}
              <template v-if="!version.installed">({{ $t('php.notInstalled') }})</template>
              <template v-else-if="version.running">({{ $t('php.running') }})</template>
            </option>
          </select>
          <p v-if="form.php_version !== originalVersion" class="mt-1 text-sm text-amber-600 dark:text-amber-400">
            {{ $t('php.versionChangeWarning') }}
          </p>
        </div>

        <hr class="border-gray-200 dark:border-gray-700" />

        <!-- Memory Limit -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ $t('php.memoryLimit') }}
          </label>
          <div class="flex items-center space-x-3">
            <input
              type="range"
              v-model="memoryLimitMB"
              min="64"
              max="1024"
              step="64"
              class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
            />
            <span class="w-20 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ form.memory_limit }}
            </span>
          </div>
        </div>

        <!-- Upload Max Filesize -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ $t('php.uploadMaxFilesize') }}
          </label>
          <div class="flex items-center space-x-3">
            <input
              type="range"
              v-model="uploadMaxFilesizeMB"
              min="2"
              max="256"
              step="2"
              class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
            />
            <span class="w-20 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ form.upload_max_filesize }}
            </span>
          </div>
        </div>

        <!-- Post Max Size -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ $t('php.postMaxSize') }}
          </label>
          <div class="flex items-center space-x-3">
            <input
              type="range"
              v-model="postMaxSizeMB"
              min="8"
              max="256"
              step="8"
              class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
            />
            <span class="w-20 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ form.post_max_size }}
            </span>
          </div>
        </div>

        <!-- Max Execution Time -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ $t('php.maxExecutionTime') }}
          </label>
          <div class="flex items-center space-x-3">
            <input
              type="range"
              v-model.number="form.max_execution_time"
              min="30"
              max="600"
              step="30"
              class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
            />
            <span class="w-24 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ form.max_execution_time }}s
            </span>
          </div>
        </div>

        <!-- Max Input Time -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ $t('php.maxInputTime') }}
          </label>
          <div class="flex items-center space-x-3">
            <input
              type="range"
              v-model.number="form.max_input_time"
              min="60"
              max="600"
              step="30"
              class="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
            />
            <span class="w-24 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
              {{ form.max_input_time }}s
            </span>
          </div>
        </div>

        <!-- Display Errors -->
        <div class="flex items-center justify-between">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ $t('php.displayErrors') }}
          </label>
          <button
            type="button"
            @click="form.display_errors = form.display_errors === 'on' ? 'off' : 'on'"
            :class="[
              form.display_errors === 'on' ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700',
              'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2'
            ]"
          >
            <span
              :class="[
                form.display_errors === 'on' ? 'translate-x-5' : 'translate-x-0',
                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
              ]"
            />
          </button>
        </div>
        <p class="text-xs text-amber-600 dark:text-amber-400">
          {{ $t('php.displayErrorsWarning') }}
        </p>
      </div>

      <!-- Actions -->
      <div class="mt-8 flex justify-end space-x-3">
        <VButton
          type="button"
          variant="secondary"
          @click="handleClose"
        >
          {{ $t('common.cancel') }}
        </VButton>
        <VButton
          type="submit"
          variant="primary"
          :loading="isSubmitting"
        >
          {{ $t('common.save') }}
        </VButton>
      </div>
    </form>
  </VModal>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import VModal from '@/components/ui/VModal.vue'
import VButton from '@/components/ui/VButton.vue'
import api from '@/services/api'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  domain: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['update:show', 'updated'])

const { t } = useI18n()

const loading = ref(true)
const isSubmitting = ref(false)
const availableVersions = ref([])
const originalVersion = ref('')

const form = reactive({
  php_version: '8.3',
  memory_limit: '256M',
  upload_max_filesize: '64M',
  post_max_size: '64M',
  max_execution_time: 30,
  max_input_time: 60,
  display_errors: 'off'
})

// Computed values for slider-to-string conversion
const memoryLimitMB = computed({
  get: () => parseInt(form.memory_limit),
  set: (val) => { form.memory_limit = `${val}M` }
})

const uploadMaxFilesizeMB = computed({
  get: () => parseInt(form.upload_max_filesize),
  set: (val) => { form.upload_max_filesize = `${val}M` }
})

const postMaxSizeMB = computed({
  get: () => parseInt(form.post_max_size),
  set: (val) => { form.post_max_size = `${val}M` }
})

async function loadData() {
  if (!props.domain) return

  loading.value = true

  try {
    // Load available versions
    const versionsResponse = await api.get('/php/versions')
    availableVersions.value = versionsResponse.data.data.versions

    // Load current settings
    const settingsResponse = await api.get(`/domains/${props.domain.id}/php-settings`)
    const data = settingsResponse.data.data

    form.php_version = data.php_version
    originalVersion.value = data.php_version

    // Apply current settings or defaults
    const settings = { ...data.defaults, ...data.settings }
    form.memory_limit = settings.memory_limit || '256M'
    form.upload_max_filesize = settings.upload_max_filesize || '64M'
    form.post_max_size = settings.post_max_size || '64M'
    form.max_execution_time = parseInt(settings.max_execution_time) || 30
    form.max_input_time = parseInt(settings.max_input_time) || 60
    form.display_errors = settings.display_errors || 'off'
  } catch (error) {
    console.error('Failed to load PHP settings:', error)
  } finally {
    loading.value = false
  }
}

function handleClose() {
  emit('update:show', false)
}

async function handleSubmit() {
  if (!props.domain) return

  isSubmitting.value = true

  try {
    // Update PHP version if changed
    if (form.php_version !== originalVersion.value) {
      await api.put(`/domains/${props.domain.id}/php-version`, {
        php_version: form.php_version
      })
    }

    // Update PHP settings
    await api.put(`/domains/${props.domain.id}/php-settings`, {
      memory_limit: form.memory_limit,
      upload_max_filesize: form.upload_max_filesize,
      post_max_size: form.post_max_size,
      max_execution_time: form.max_execution_time,
      max_input_time: form.max_input_time,
      display_errors: form.display_errors
    })

    emit('updated', { ...form })
    handleClose()
  } catch (error) {
    console.error('Failed to save PHP settings:', error)
  } finally {
    isSubmitting.value = false
  }
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    loadData()
  }
})
</script>
