<template>
  <div>
    <VBreadcrumb :items="[{ label: $t('nav.databases'), to: '/databases' }, { label: $t('databases.createDatabase') }]" />

    <div class="max-w-xl mx-auto mt-6">
      <VCard :title="$t('databases.createDatabase')">
        <form @submit.prevent="createDatabase">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('databases.name') }}
              </label>
              <VInput v-model="form.name" placeholder="mydb" required />
              <p class="mt-1 text-xs text-gray-500">{{ $t('databases.nameHint') }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('databases.charset') }}
                </label>
                <select v-model="form.charset" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500">
                  <option value="utf8mb4">utf8mb4 ({{ $t('common.recommended') }})</option>
                  <option value="utf8">utf8</option>
                  <option value="latin1">latin1</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('databases.collation') }}
                </label>
                <select v-model="form.collation" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500">
                  <option value="utf8mb4_unicode_ci">utf8mb4_unicode_ci</option>
                  <option value="utf8mb4_general_ci">utf8mb4_general_ci</option>
                  <option value="utf8_unicode_ci">utf8_unicode_ci</option>
                </select>
              </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
              <label class="flex items-center">
                <input type="checkbox" v-model="form.create_user" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('databases.createUserWithDatabase') }}</span>
              </label>
            </div>

            <template v-if="form.create_user">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('databases.username') }}
                </label>
                <VInput v-model="form.username" :placeholder="form.name || 'dbuser'" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('databases.password') }}
                </label>
                <div class="flex gap-2">
                  <VInput v-model="form.password" :type="showPassword ? 'text' : 'password'" class="flex-1" />
                  <VButton type="button" variant="secondary" @click="showPassword = !showPassword">
                    <EyeIcon v-if="!showPassword" class="w-5 h-5" />
                    <EyeSlashIcon v-else class="w-5 h-5" />
                  </VButton>
                  <VButton type="button" variant="secondary" @click="generatePassword">
                    {{ $t('common.generate') }}
                  </VButton>
                </div>
              </div>
            </template>
          </div>

          <div class="mt-6 flex justify-end gap-3">
            <router-link to="/databases">
              <VButton type="button" variant="secondary">{{ $t('common.cancel') }}</VButton>
            </router-link>
            <VButton type="submit" variant="primary" :loading="creating">
              {{ $t('common.create') }}
            </VButton>
          </div>
        </form>
      </VCard>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import { useAppStore } from '@/stores/app'
import VCard from '@/components/ui/VCard.vue'
import VInput from '@/components/ui/VInput.vue'
import VButton from '@/components/ui/VButton.vue'
import VBreadcrumb from '@/components/ui/VBreadcrumb.vue'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const { t } = useI18n()
const appStore = useAppStore()

const creating = ref(false)
const showPassword = ref(false)

const form = ref({
  name: '',
  charset: 'utf8mb4',
  collation: 'utf8mb4_unicode_ci',
  create_user: true,
  username: '',
  password: generateRandomPassword()
})

function generateRandomPassword() {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'
  let password = ''
  for (let i = 0; i < 16; i++) password += chars.charAt(Math.floor(Math.random() * chars.length))
  return password
}

function generatePassword() {
  form.value.password = generateRandomPassword()
}

async function createDatabase() {
  creating.value = true
  try {
    await api.post('/databases', form.value)
    appStore.showToast({ type: 'success', message: t('databases.createSuccess') })
    router.push('/databases')
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('databases.createError') })
  } finally {
    creating.value = false
  }
}
</script>
