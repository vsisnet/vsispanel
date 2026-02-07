<template>
  <VModal
    :show="show"
    :title="$t('websites.editDomain')"
    size="md"
    @close="handleClose"
  >
    <form @submit.prevent="handleSubmit">
      <div class="space-y-4">
        <!-- Domain Name (read-only) -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('websites.domainName') }}
          </label>
          <VInput
            :model-value="domain?.name"
            disabled
          />
        </div>

        <!-- PHP Version -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('websites.phpVersion') }}
          </label>
          <select
            v-model="form.php_version"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="8.3">PHP 8.3</option>
            <option value="8.2">PHP 8.2</option>
            <option value="8.1">PHP 8.1</option>
          </select>
        </div>

        <!-- Web Server Type -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('websites.webServer') }}
          </label>
          <select
            v-model="form.web_server_type"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="nginx">Nginx</option>
            <option value="apache">Apache</option>
          </select>
        </div>

        <!-- Is Main Domain -->
        <div class="flex items-center">
          <input
            v-model="form.is_main"
            type="checkbox"
            class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
          />
          <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">
            {{ $t('websites.setAsMain') }}
          </label>
        </div>
      </div>

      <!-- Actions -->
      <div class="mt-6 flex justify-end space-x-3">
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
import { ref, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDomainsStore } from '@/stores/domains'
import VModal from '@/components/ui/VModal.vue'
import VInput from '@/components/ui/VInput.vue'
import VButton from '@/components/ui/VButton.vue'

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
const domainsStore = useDomainsStore()

const isSubmitting = ref(false)
const errors = ref({})

const form = reactive({
  php_version: '8.3',
  web_server_type: 'nginx',
  is_main: false
})

function populateForm() {
  if (props.domain) {
    form.php_version = props.domain.php_version || '8.3'
    form.web_server_type = props.domain.web_server_type || 'nginx'
    form.is_main = props.domain.is_main || false
  }
}

function handleClose() {
  errors.value = {}
  emit('update:show', false)
}

async function handleSubmit() {
  if (!props.domain) return

  errors.value = {}
  isSubmitting.value = true

  try {
    const updatedDomain = await domainsStore.updateDomain(props.domain.id, {
      php_version: form.php_version,
      web_server_type: form.web_server_type,
      is_main: form.is_main
    })

    emit('updated', updatedDomain)
    handleClose()
  } catch (error) {
    if (error.response?.status === 422) {
      const responseErrors = error.response.data.error?.errors || error.response.data.errors || {}
      Object.keys(responseErrors).forEach(key => {
        errors.value[key] = Array.isArray(responseErrors[key])
          ? responseErrors[key][0]
          : responseErrors[key]
      })
    }
  } finally {
    isSubmitting.value = false
  }
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    populateForm()
  }
})

watch(() => props.domain, () => {
  if (props.show) {
    populateForm()
  }
}, { deep: true })
</script>
