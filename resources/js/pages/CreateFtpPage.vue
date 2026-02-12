<template>
  <div>
    <VBreadcrumb :items="[{ label: $t('nav.ftp'), to: '/ftp' }, { label: $t('ftp.createAccount') }]" />

    <div class="max-w-2xl mx-auto mt-6">
      <VCard :title="$t('ftp.createAccount')">
        <div v-if="loadingDomains" class="py-8 text-center">
          <VLoadingSkeleton class="h-32" />
        </div>
        <form v-else @submit.prevent="saveAccount">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('ftp.domain') }} *</label>
              <select v-model="formData.domain_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500" required>
                <option value="">{{ $t('ftp.selectDomain') }}</option>
                <option v-for="domain in domains" :key="domain.id" :value="domain.id">{{ domain.name }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('ftp.username') }} *</label>
              <VInput v-model="formData.username" required pattern="[a-zA-Z][a-zA-Z0-9_]{2,31}" />
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('ftp.usernameHint') }}</p>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('ftp.password') }} *</label>
              <div class="flex gap-2">
                <div class="relative flex-1">
                  <VInput v-model="formData.password" :type="showPassword ? 'text' : 'password'" required minlength="8" />
                  <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" @click="showPassword = !showPassword">
                    <EyeIcon v-if="!showPassword" class="w-5 h-5" />
                    <EyeSlashIcon v-else class="w-5 h-5" />
                  </button>
                </div>
                <VButton type="button" variant="secondary" @click="generatePassword">{{ $t('common.generate') }}</VButton>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('ftp.homeDirectory') }}</label>
              <VInput v-model="formData.home_directory" :placeholder="$t('ftp.homeDirectoryPlaceholder')" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('ftp.quotaMb') }}</label>
              <VInput v-model.number="formData.quota_mb" type="number" min="0" :placeholder="$t('ftp.quotaPlaceholder')" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('ftp.maxConnections') }}</label>
              <VInput v-model.number="formData.max_connections" type="number" min="1" max="100" />
            </div>
          </div>

          <!-- Permissions -->
          <div class="mb-4">
            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-2">{{ $t('ftp.permissions') }}</h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="formData.allow_upload" class="rounded text-primary-600" />
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('ftp.allowUpload') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="formData.allow_download" class="rounded text-primary-600" />
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('ftp.allowDownload') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="formData.allow_mkdir" class="rounded text-primary-600" />
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('ftp.allowMkdir') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="formData.allow_delete" class="rounded text-primary-600" />
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('ftp.allowDelete') }}</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" v-model="formData.allow_rename" class="rounded text-primary-600" />
                <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('ftp.allowRename') }}</span>
              </label>
            </div>
          </div>

          <!-- Description -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('ftp.description') }}</label>
            <textarea v-model="formData.description" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500" rows="2"></textarea>
          </div>

          <!-- Expiration -->
          <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('ftp.expiresAt') }}</label>
            <input v-model="formData.expires_at" type="date" class="w-full sm:w-48 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500" :min="minExpiryDate" />
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <router-link to="/ftp">
              <VButton type="button" variant="secondary">{{ $t('common.cancel') }}</VButton>
            </router-link>
            <VButton type="submit" variant="primary" :loading="saving">{{ $t('common.create') }}</VButton>
          </div>
        </form>
      </VCard>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import { useAppStore } from '@/stores/app'
import VCard from '@/components/ui/VCard.vue'
import VInput from '@/components/ui/VInput.vue'
import VButton from '@/components/ui/VButton.vue'
import VBreadcrumb from '@/components/ui/VBreadcrumb.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const { t } = useI18n()
const appStore = useAppStore()

const domains = ref([])
const loadingDomains = ref(true)
const saving = ref(false)
const showPassword = ref(false)

const formData = reactive({
  domain_id: '',
  username: '',
  password: '',
  home_directory: '',
  quota_mb: null,
  max_connections: 2,
  allow_upload: true,
  allow_download: true,
  allow_mkdir: true,
  allow_delete: true,
  allow_rename: true,
  description: '',
  expires_at: null
})

const minExpiryDate = computed(() => {
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  return tomorrow.toISOString().split('T')[0]
})

function generatePassword() {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'
  let pw = ''
  for (let i = 0; i < 16; i++) pw += chars.charAt(Math.floor(Math.random() * chars.length))
  formData.password = pw
}

async function fetchDomains() {
  try {
    const response = await api.get('/domains')
    domains.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch domains:', err)
  } finally {
    loadingDomains.value = false
  }
}

async function saveAccount() {
  saving.value = true
  try {
    await api.post('/ftp/accounts', formData)
    appStore.showToast({ type: 'success', message: t('ftp.createSuccess') })
    router.push('/ftp')
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  } finally {
    saving.value = false
  }
}

onMounted(fetchDomains)
</script>
