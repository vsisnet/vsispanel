<template>
  <AuthLayout>
    <!-- Login Form -->
    <form v-if="!showTwoFactor" @submit.prevent="handleLogin" class="space-y-6">
      <div>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white text-center mb-6">
          {{ $t('auth.login') }}
        </h2>
      </div>

      <VInput
        v-model="form.email"
        type="email"
        :label="$t('auth.email')"
        :placeholder="$t('auth.email')"
        :error="errors.email"
        required
        autofocus
      />

      <VInput
        v-model="form.password"
        type="password"
        :label="$t('auth.password')"
        :placeholder="$t('auth.password')"
        :error="errors.password"
        required
      />

      <div class="flex items-center justify-between">
        <label class="flex items-center">
          <input
            v-model="form.remember"
            type="checkbox"
            class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
          />
          <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
            {{ $t('auth.rememberMe') }}
          </span>
        </label>
      </div>

      <div v-if="errors.general" class="p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
        <p class="text-sm text-red-600 dark:text-red-400">{{ errors.general }}</p>
      </div>

      <VButton
        type="submit"
        variant="primary"
        class="w-full"
        :loading="isLoading"
      >
        {{ $t('auth.login') }}
      </VButton>
    </form>

    <!-- 2FA Form -->
    <form v-else @submit.prevent="handleVerify2FA" class="space-y-6">
      <div class="text-center">
        <div class="w-16 h-16 bg-primary-100 dark:bg-primary-900/30 rounded-full flex items-center justify-center mx-auto mb-4">
          <ShieldCheckIcon class="w-8 h-8 text-primary-600 dark:text-primary-400" />
        </div>
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
          {{ $t('auth.twoFactorTitle') }}
        </h2>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('auth.twoFactorDescription') }}
        </p>
      </div>

      <VInput
        v-model="twoFactorCode"
        type="text"
        :label="$t('auth.twoFactorCode')"
        placeholder="000000"
        :error="errors.code"
        maxlength="6"
        required
        autofocus
        class="text-center text-2xl tracking-widest"
      />

      <div v-if="errors.general" class="p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
        <p class="text-sm text-red-600 dark:text-red-400">{{ errors.general }}</p>
      </div>

      <div class="space-y-3">
        <VButton
          type="submit"
          variant="primary"
          class="w-full"
          :loading="isLoading"
        >
          {{ $t('common.confirm') }}
        </VButton>

        <VButton
          type="button"
          variant="ghost"
          class="w-full"
          @click="cancelTwoFactor"
        >
          {{ $t('common.back') }}
        </VButton>
      </div>
    </form>
  </AuthLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import AuthLayout from '@/layouts/AuthLayout.vue'
import VInput from '@/components/ui/VInput.vue'
import VButton from '@/components/ui/VButton.vue'
import { ShieldCheckIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const route = useRoute()
const { t } = useI18n()
const authStore = useAuthStore()
const appStore = useAppStore()

const form = reactive({
  email: '',
  password: '',
  remember: false
})

const errors = reactive({
  email: '',
  password: '',
  code: '',
  general: ''
})

const twoFactorCode = ref('')
const showTwoFactor = computed(() => authStore.requires2FA)
const isLoading = computed(() => authStore.isLoading)

function clearErrors() {
  errors.email = ''
  errors.password = ''
  errors.code = ''
  errors.general = ''
}

async function handleLogin() {
  clearErrors()

  try {
    const result = await authStore.login(form.email, form.password)

    if (result.requires2FA) {
      // Show 2FA form
      return
    }

    appStore.showToast({
      type: 'success',
      message: t('auth.loginSuccess')
    })

    // Redirect to intended page or dashboard
    const redirect = route.query.redirect || '/dashboard'
    router.push(redirect)
  } catch (error) {
    if (error.response?.status === 422) {
      const validationErrors = error.response.data.error?.errors || {}
      errors.email = validationErrors.email?.[0] || ''
      errors.password = validationErrors.password?.[0] || ''
    } else if (error.response?.status === 401) {
      errors.general = t('auth.loginFailed')
    } else {
      errors.general = error.response?.data?.error?.message || 'An error occurred'
    }
  }
}

async function handleVerify2FA() {
  clearErrors()

  try {
    await authStore.verify2FA(twoFactorCode.value)

    appStore.showToast({
      type: 'success',
      message: t('auth.loginSuccess')
    })

    const redirect = route.query.redirect || '/dashboard'
    router.push(redirect)
  } catch (error) {
    if (error.response?.status === 422) {
      const validationErrors = error.response.data.error?.errors || {}
      errors.code = validationErrors.code?.[0] || ''
    } else {
      errors.general = error.response?.data?.error?.message || 'Invalid code'
    }
  }
}

function cancelTwoFactor() {
  authStore.clearAuth()
  twoFactorCode.value = ''
  clearErrors()
}
</script>
