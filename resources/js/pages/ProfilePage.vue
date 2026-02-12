<template>
  <div>
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('profile.title') }}</h1>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
      <nav class="flex space-x-8">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          :class="[
            'py-3 px-1 border-b-2 text-sm font-medium transition-colors',
            activeTab === tab.id
              ? 'border-primary-500 text-primary-600 dark:text-primary-400'
              : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
          ]"
        >
          <component :is="tab.icon" class="w-4 h-4 inline mr-2" />
          {{ $t(tab.label) }}
        </button>
      </nav>
    </div>

    <!-- Profile Info Tab -->
    <VCard v-if="activeTab === 'info'" :title="$t('profile.personalInfo')">
      <form @submit.prevent="updateProfile" class="space-y-4 max-w-lg">
        <VInput v-model="profileForm.name" :label="$t('profile.name')" required />
        <VInput v-model="profileForm.email" type="email" :label="$t('profile.email')" required />
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('profile.language') }}</label>
          <select
            v-model="profileForm.locale"
            class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="en">English</option>
            <option value="vi">Tiếng Việt</option>
          </select>
        </div>
        <VInput v-model="profileForm.timezone" :label="$t('profile.timezone')" placeholder="Asia/Ho_Chi_Minh" />

        <div v-if="profileMsg" :class="['p-3 rounded-lg text-sm', profileMsg.type === 'success' ? 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400']">
          {{ profileMsg.text }}
        </div>

        <VButton type="submit" variant="primary" :loading="profileLoading">
          {{ $t('common.save') }}
        </VButton>
      </form>
    </VCard>

    <!-- Change Password Tab -->
    <VCard v-if="activeTab === 'password'" :title="$t('profile.changePassword')">
      <form @submit.prevent="updatePassword" class="space-y-4 max-w-lg">
        <VInput v-model="passwordForm.current_password" type="password" :label="$t('profile.currentPassword')" required />
        <VInput v-model="passwordForm.new_password" type="password" :label="$t('profile.newPassword')" required />
        <VInput v-model="passwordForm.new_password_confirmation" type="password" :label="$t('profile.confirmPassword')" required />

        <div v-if="passwordMsg" :class="['p-3 rounded-lg text-sm', passwordMsg.type === 'success' ? 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400']">
          {{ passwordMsg.text }}
        </div>

        <VButton type="submit" variant="primary" :loading="passwordLoading">
          {{ $t('profile.changePassword') }}
        </VButton>
      </form>
    </VCard>

    <!-- 2FA Tab -->
    <VCard v-if="activeTab === '2fa'" :title="$t('profile.twoFactor')">
      <div class="max-w-lg space-y-6">
        <!-- 2FA Enabled -->
        <div v-if="is2FAEnabled" class="space-y-4">
          <div class="flex items-center space-x-3 p-4 bg-green-50 dark:bg-green-900/30 rounded-lg">
            <ShieldCheckIcon class="w-6 h-6 text-green-600 dark:text-green-400" />
            <span class="text-sm text-green-700 dark:text-green-300">{{ $t('profile.twoFactorEnabled') }}</span>
          </div>

          <form @submit.prevent="disable2FA" class="space-y-4">
            <VInput v-model="disablePassword" type="password" :label="$t('profile.currentPassword')" required />
            <VButton type="submit" variant="danger" :loading="tfaLoading">
              {{ $t('profile.disable2fa') }}
            </VButton>
          </form>
        </div>

        <!-- 2FA Setup -->
        <div v-else class="space-y-4">
          <div v-if="!qrData" class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $t('profile.twoFactorDesc') }}</p>
            <VButton @click="enable2FA" variant="primary" :loading="tfaLoading">
              {{ $t('profile.enable2fa') }}
            </VButton>
          </div>

          <div v-else class="space-y-4">
            <p class="text-sm text-gray-600 dark:text-gray-400">{{ $t('profile.scanQrCode') }}</p>
            <div class="flex justify-center p-4 bg-white rounded-lg">
              <div v-html="atob(qrData.qr_code_svg)" class="w-48 h-48" />
            </div>
            <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $t('profile.manualCode') }}</p>
              <code class="text-sm font-mono text-gray-900 dark:text-white">{{ qrData.secret }}</code>
            </div>
            <form @submit.prevent="confirm2FA" class="space-y-4">
              <VInput v-model="confirmCode" :label="$t('auth.twoFactorCode')" placeholder="000000" maxlength="6" required />
              <VButton type="submit" variant="primary" :loading="tfaLoading">
                {{ $t('common.confirm') }}
              </VButton>
            </form>
          </div>
        </div>

        <div v-if="tfaMsg" :class="['p-3 rounded-lg text-sm', tfaMsg.type === 'success' ? 'bg-green-50 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-red-50 dark:bg-red-900/30 text-red-600 dark:text-red-400']">
          {{ tfaMsg.text }}
        </div>
      </div>
    </VCard>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/auth'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VInput from '@/components/ui/VInput.vue'
import VButton from '@/components/ui/VButton.vue'
import { UserIcon, KeyIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'

const { t } = useI18n()
const authStore = useAuthStore()
const appStore = useAppStore()

const activeTab = ref('info')
const tabs = [
  { id: 'info', label: 'profile.personalInfo', icon: UserIcon },
  { id: 'password', label: 'profile.changePassword', icon: KeyIcon },
  { id: '2fa', label: 'profile.twoFactor', icon: ShieldCheckIcon },
]

// Profile
const profileForm = reactive({ name: '', email: '', locale: 'en', timezone: '' })
const profileLoading = ref(false)
const profileMsg = ref(null)

onMounted(() => {
  const u = authStore.user
  if (u) {
    profileForm.name = u.name || ''
    profileForm.email = u.email || ''
    profileForm.locale = u.locale || 'en'
    profileForm.timezone = u.timezone || ''
  }
})

async function updateProfile() {
  profileLoading.value = true
  profileMsg.value = null
  try {
    await api.put('/auth/profile', profileForm)
    await authStore.fetchUser()
    profileMsg.value = { type: 'success', text: t('profile.profileUpdated') }
  } catch (e) {
    profileMsg.value = { type: 'error', text: e.response?.data?.error?.message || t('errors.generic') }
  } finally {
    profileLoading.value = false
  }
}

// Password
const passwordForm = reactive({ current_password: '', new_password: '', new_password_confirmation: '' })
const passwordLoading = ref(false)
const passwordMsg = ref(null)

async function updatePassword() {
  passwordLoading.value = true
  passwordMsg.value = null
  try {
    await api.put('/auth/password', passwordForm)
    passwordForm.current_password = ''
    passwordForm.new_password = ''
    passwordForm.new_password_confirmation = ''
    passwordMsg.value = { type: 'success', text: t('profile.passwordUpdated') }
  } catch (e) {
    passwordMsg.value = { type: 'error', text: e.response?.data?.error?.message || t('errors.generic') }
  } finally {
    passwordLoading.value = false
  }
}

// 2FA
const is2FAEnabled = computed(() => !!authStore.user?.two_factor_confirmed_at)
const qrData = ref(null)
const confirmCode = ref('')
const disablePassword = ref('')
const tfaLoading = ref(false)
const tfaMsg = ref(null)

async function enable2FA() {
  tfaLoading.value = true
  tfaMsg.value = null
  try {
    const res = await api.post('/auth/2fa/enable')
    qrData.value = res.data.data
  } catch (e) {
    tfaMsg.value = { type: 'error', text: e.response?.data?.error?.message || t('errors.generic') }
  } finally {
    tfaLoading.value = false
  }
}

async function confirm2FA() {
  tfaLoading.value = true
  tfaMsg.value = null
  try {
    await api.post('/auth/2fa/confirm', { code: confirmCode.value })
    await authStore.fetchUser()
    qrData.value = null
    confirmCode.value = ''
    tfaMsg.value = { type: 'success', text: t('profile.twoFactorEnabledSuccess') }
  } catch (e) {
    tfaMsg.value = { type: 'error', text: e.response?.data?.error?.message || t('errors.generic') }
  } finally {
    tfaLoading.value = false
  }
}

async function disable2FA() {
  tfaLoading.value = true
  tfaMsg.value = null
  try {
    await api.post('/auth/2fa/disable', { password: disablePassword.value })
    await authStore.fetchUser()
    disablePassword.value = ''
    tfaMsg.value = { type: 'success', text: t('profile.twoFactorDisabledSuccess') }
  } catch (e) {
    tfaMsg.value = { type: 'error', text: e.response?.data?.error?.message || t('errors.generic') }
  } finally {
    tfaLoading.value = false
  }
}
</script>
