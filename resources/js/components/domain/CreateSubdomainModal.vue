<template>
  <VModal :show="show" :title="$t('domainDetail.addSubdomain')" @close="$emit('update:show', false)">
    <form @submit.prevent="handleSubmit">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('domainDetail.subdomainName') }}
          </label>
          <div class="flex items-center">
            <VInput
              v-model="form.name"
              :placeholder="$t('domainDetail.subdomainPlaceholder')"
              class="flex-1"
              required
            />
            <span class="ml-2 text-gray-500 dark:text-gray-400">.{{ domain.name }}</span>
          </div>
          <p v-if="errors.name" class="mt-1 text-sm text-red-500">{{ errors.name }}</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('domainDetail.documentRoot') }}
          </label>
          <VInput
            v-model="form.document_root"
            :placeholder="$t('domainDetail.documentRootPlaceholder')"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ $t('domainDetail.documentRootHint') }}
          </p>
        </div>
      </div>

      <div class="mt-6 flex justify-end space-x-3">
        <VButton variant="secondary" type="button" @click="$emit('update:show', false)">
          {{ $t('common.cancel') }}
        </VButton>
        <VButton variant="primary" type="submit" :loading="loading">
          {{ $t('common.create') }}
        </VButton>
      </div>
    </form>
  </VModal>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
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
    required: true
  }
})

const emit = defineEmits(['update:show', 'created'])

const { t } = useI18n()

// State
const loading = ref(false)
const errors = ref({})
const form = ref({
  name: '',
  document_root: ''
})

// Watch show to reset form
watch(() => props.show, (newVal) => {
  if (newVal) {
    form.value = { name: '', document_root: '' }
    errors.value = {}
  }
})

// Methods
async function handleSubmit() {
  errors.value = {}
  loading.value = true

  try {
    await api.post(`/domains/${props.domain.id}/subdomains`, form.value)
    emit('update:show', false)
    emit('created')
  } catch (err) {
    if (err.response?.data?.error?.errors) {
      errors.value = err.response.data.error.errors
    }
  } finally {
    loading.value = false
  }
}
</script>
