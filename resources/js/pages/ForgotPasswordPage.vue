<template>
  <AuthLayout>
    <div class="space-y-6">
      <div class="text-center">
        <div class="w-16 h-16 bg-primary-100 dark:bg-primary-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
          <EnvelopeIcon class="w-8 h-8 text-primary-600 dark:text-primary-400" />
        </div>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
          {{ $t('auth.forgotPassword') }}
        </h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('auth.forgotPasswordDesc') }}
        </p>
      </div>

      <div v-if="sent" class="p-4 bg-green-50 dark:bg-green-900/30 rounded-lg">
        <p class="text-sm text-green-700 dark:text-green-300">
          {{ $t('auth.resetLinkSent') }}
        </p>
      </div>

      <form v-else @submit.prevent="handleSubmit" class="space-y-4">
        <VInput
          v-model="email"
          type="email"
          :label="$t('auth.email')"
          :placeholder="$t('auth.email')"
          :error="error"
          required
          autofocus
        />

        <VButton type="submit" variant="primary" class="w-full" :loading="loading">
          {{ $t('auth.sendResetLink') }}
        </VButton>
      </form>

      <div class="text-center">
        <router-link
          to="/login"
          class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
        >
          {{ $t('auth.backToLogin') }}
        </router-link>
      </div>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import AuthLayout from '@/layouts/AuthLayout.vue'
import VInput from '@/components/ui/VInput.vue'
import VButton from '@/components/ui/VButton.vue'
import { EnvelopeIcon } from '@heroicons/vue/24/outline'

const { t } = useI18n()
const email = ref('')
const error = ref('')
const loading = ref(false)
const sent = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await api.post('/auth/forgot-password', { email: email.value })
    sent.value = true
  } catch (e) {
    error.value = e.response?.data?.error?.message || t('errors.generic')
  } finally {
    loading.value = false
  }
}
</script>
