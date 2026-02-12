<template>
  <div>
    <VBreadcrumb :items="[{ label: $t('nav.email'), to: '/email' }, { label: $t('email.createAccount') }]" />

    <div class="max-w-xl mx-auto mt-6">
      <VCard :title="$t('email.createAccount')">
        <div v-if="loadingDomains" class="py-8 text-center">
          <VLoadingSkeleton class="h-32" />
        </div>
        <div v-else-if="domains.length === 0" class="py-8 text-center text-gray-500 dark:text-gray-400">
          <p>{{ $t('email.noDomains') }}</p>
          <router-link to="/websites/create">
            <VButton variant="primary" class="mt-4">{{ $t('websites.addDomain') }}</VButton>
          </router-link>
        </div>
        <form v-else @submit.prevent="createAccount">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('email.domain') }}
              </label>
              <select v-model="selectedDomainId" class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500" required>
                <option value="">{{ $t('email.selectDomain') }}</option>
                <option v-for="domain in domains" :key="domain.id" :value="domain.id">{{ domain.name }}</option>
              </select>
            </div>

            <div v-if="!mailDomainReady && selectedDomainId" class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
              <p class="text-sm text-yellow-800 dark:text-yellow-200">{{ $t('email.setupMailFirst') }}</p>
              <VButton variant="primary" size="sm" class="mt-2" :loading="settingUp" @click="setupMailDomain">
                {{ $t('email.setupMail') }}
              </VButton>
            </div>

            <template v-if="mailDomainReady">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('email.username') }}
                </label>
                <div class="flex items-center">
                  <VInput v-model="form.username" :placeholder="$t('email.usernamePlaceholder')" class="flex-1 rounded-r-none" required />
                  <span class="px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-lg text-gray-500 dark:text-gray-400">
                    @{{ selectedDomain?.name }}
                  </span>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('email.password') }}
                </label>
                <div class="flex gap-2">
                  <VInput v-model="form.password" type="password" class="flex-1" required minlength="8" />
                  <VButton type="button" variant="secondary" @click="generatePassword">
                    {{ $t('email.generatePassword') }}
                  </VButton>
                </div>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('email.quotaMb') }}
                </label>
                <VInput v-model.number="form.quota_mb" type="number" min="1" max="102400" />
              </div>
            </template>
          </div>

          <div class="mt-6 flex justify-end gap-3">
            <router-link to="/email">
              <VButton type="button" variant="secondary">{{ $t('common.cancel') }}</VButton>
            </router-link>
            <VButton type="submit" variant="primary" :loading="creating" :disabled="!mailDomainReady">
              {{ $t('common.create') }}
            </VButton>
          </div>
        </form>
      </VCard>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import { useAppStore } from '@/stores/app'
import VCard from '@/components/ui/VCard.vue'
import VInput from '@/components/ui/VInput.vue'
import VButton from '@/components/ui/VButton.vue'
import VBreadcrumb from '@/components/ui/VBreadcrumb.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'

const router = useRouter()
const { t } = useI18n()
const appStore = useAppStore()

const domains = ref([])
const loadingDomains = ref(true)
const creating = ref(false)
const settingUp = ref(false)
const selectedDomainId = ref('')
const selectedMailDomain = ref(null)

const selectedDomain = computed(() => domains.value.find(d => d.id === selectedDomainId.value))
const mailDomainReady = computed(() => !!selectedMailDomain.value)

const form = ref({ username: '', password: '', quota_mb: 1024 })

function generatePassword() {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%'
  let pw = ''
  for (let i = 0; i < 16; i++) pw += chars.charAt(Math.floor(Math.random() * chars.length))
  form.value.password = pw
}

async function fetchDomains() {
  try {
    const response = await api.get('/domains')
    domains.value = response.data.data
    if (domains.value.length > 0) selectedDomainId.value = domains.value[0].id
  } catch (err) {
    console.error('Failed to fetch domains:', err)
  } finally {
    loadingDomains.value = false
  }
}

async function fetchMailDomain() {
  if (!selectedDomainId.value) { selectedMailDomain.value = null; return }
  try {
    const response = await api.get('/mail/domains', { params: { domain_id: selectedDomainId.value } })
    const mailDomains = response.data.data
    selectedMailDomain.value = mailDomains.find(md => md.domain_id === selectedDomainId.value) || null
  } catch (err) {
    selectedMailDomain.value = null
  }
}

async function setupMailDomain() {
  settingUp.value = true
  try {
    await api.post('/mail/domains', { domain_id: selectedDomainId.value })
    await fetchMailDomain()
    appStore.showToast({ type: 'success', message: t('email.setupSuccess') })
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('email.setupError') })
  } finally {
    settingUp.value = false
  }
}

watch(selectedDomainId, () => fetchMailDomain())

async function createAccount() {
  creating.value = true
  try {
    await api.post('/mail/accounts', {
      mail_domain_id: selectedMailDomain.value.id,
      username: form.value.username,
      password: form.value.password,
      quota_mb: form.value.quota_mb
    })
    appStore.showToast({ type: 'success', message: t('email.createSuccess') })
    router.push('/email')
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('email.createError') })
  } finally {
    creating.value = false
  }
}

onMounted(fetchDomains)
</script>
