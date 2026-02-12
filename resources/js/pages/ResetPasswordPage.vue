<template>
  <AuthLayout>
    <div class="space-y-6">
      <div class="text-center">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
          {{ $t('auth.resetPassword') }}
        </h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('auth.resetPasswordDesc') }}
        </p>
      </div>

      <div v-if="success" class="p-4 bg-green-50 dark:bg-green-900/30 rounded-lg text-center">
        <p class="text-sm text-green-700 dark:text-green-300 mb-3">
          {{ $t('auth.passwordResetSuccess') }}
        </p>
        <router-link to="/login" class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400">
          {{ $t('auth.backToLogin') }}
        </router-link>
      </div>

      <form v-else @submit.prevent="handleReset" class="space-y-4">
        <VInput
          v-model="form.email"
          type="email"
          :label="$t('auth.email')"
          :error="errors.email"
          required
        />
        <VInput
          v-model="form.password"
          type="password"
          :label="$t('auth.newPassword')"
          :error="errors.password"
          required
        />
        <VInput
          v-model="form.password_confirmation"
          type="password"
          :label="$t('auth.confirmPassword')"
          required
        />

        <div v-if="errors.general" class="p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
          <p class="text-sm text-red-600 dark:text-red-400">{{ errors.general }}</p>
        </div>

        <VButton type="submit" variant="primary" class="w-full" :loading="loading">
          {{ $t('auth.resetPassword') }}
        </VButton>
      </form>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import AuthLayout from '@/layouts/AuthLayout.vue'
import VInput from '@/components/ui/VInput.vue'
import VButton from '@/components/ui/VButton.vue'

const route = useRoute()
const { t } = useI18n()
const loading = ref(false)
const success = ref(false)
const form = reactive({
  email: route.query.email || '',
  password: '',
  password_confirmation: '',
})
const errors = reactive({ email: '', password: '', general: '' })

async function handleReset() {
  errors.email = ''
  errors.password = ''
  errors.general = ''
  loading.value = true
  try {
    await api.post('/auth/reset-password', {
      ...form,
      token: route.params.token,
    })
    success.value = true
  } catch (e) {
    const data = e.response?.data
    if (e.response?.status === 422 && data?.error?.errors) {
      errors.email = data.error.errors.email?.[0] || ''
      errors.password = data.error.errors.password?.[0] || ''
    } else {
      errors.general = data?.error?.message || t('errors.generic')
    }
  } finally {
    loading.value = false
  }
}
</script>
