<template>
  <div class="space-y-6">
    <!-- Let's Encrypt Email -->
    <VCard>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
        {{ $t('settings.ssl.title') }}
      </h3>
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
        {{ $t('settings.ssl.description') }}
      </p>

      <div class="max-w-md">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          {{ $t('settings.ssl.letsencryptEmail') }}
        </label>
        <input
          v-model="form.letsencrypt_email"
          type="email"
          :placeholder="$t('settings.ssl.letsencryptEmailPlaceholder')"
          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
        />
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          {{ $t('settings.ssl.letsencryptEmailHint') }}
        </p>
      </div>
    </VCard>

    <!-- Save Button -->
    <div class="flex justify-end">
      <VButton variant="primary" :loading="saving" @click="saveSettings">
        {{ $t('common.save') }}
      </VButton>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const appStore = useAppStore()

const form = ref({
  letsencrypt_email: '',
})
const saving = ref(false)

watch(() => props.settings, (settings) => {
  if (settings?.ssl) {
    form.value.letsencrypt_email = settings.ssl.letsencrypt_email || ''
  }
}, { immediate: true })

async function saveSettings() {
  saving.value = true
  try {
    const { data } = await api.put('/settings', {
      'ssl.letsencrypt_email': form.value.letsencrypt_email,
    })
    if (data.success) {
      appStore.showToast({ type: 'success', message: t('settings.saveSuccess') })
      emit('refresh')
    }
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('settings.saveError'),
    })
  } finally {
    saving.value = false
  }
}
</script>
