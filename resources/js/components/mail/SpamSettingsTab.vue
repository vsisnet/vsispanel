<template>
  <div class="space-y-6">
    <!-- Status Card -->
    <VCard>
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ $t('email.spam.status') }}
        </h3>
        <VBadge :variant="status.running ? 'success' : 'danger'">
          {{ status.running ? $t('email.spam.running') : $t('email.spam.stopped') }}
        </VBadge>
      </div>
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ statistics.scanned }}</div>
          <div class="text-sm text-gray-500 dark:text-gray-400">{{ $t('email.spam.scanned') }}</div>
        </div>
        <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <div class="text-2xl font-bold text-red-600">{{ statistics.spam_count }}</div>
          <div class="text-sm text-gray-500 dark:text-gray-400">{{ $t('email.spam.spamCount') }}</div>
        </div>
        <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <div class="text-2xl font-bold text-green-600">{{ statistics.ham_count }}</div>
          <div class="text-sm text-gray-500 dark:text-gray-400">{{ $t('email.spam.hamCount') }}</div>
        </div>
        <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
          <div class="text-2xl font-bold text-blue-600">{{ statistics.learned }}</div>
          <div class="text-sm text-gray-500 dark:text-gray-400">{{ $t('email.spam.learned') }}</div>
        </div>
      </div>
    </VCard>

    <!-- Score Thresholds -->
    <VCard>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        {{ $t('email.spam.scoreThresholds') }}
      </h3>
      <form @submit.prevent="saveScores" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('email.spam.rejectScore') }}
            </label>
            <VInput v-model.number="scores.reject" type="number" min="1" max="100" step="0.1" />
            <p class="mt-1 text-xs text-gray-500">{{ $t('email.spam.rejectScoreDesc') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('email.spam.addHeaderScore') }}
            </label>
            <VInput v-model.number="scores.add_header" type="number" min="1" max="100" step="0.1" />
            <p class="mt-1 text-xs text-gray-500">{{ $t('email.spam.addHeaderScoreDesc') }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('email.spam.greylistScore') }}
            </label>
            <VInput v-model.number="scores.greylist" type="number" min="0" max="100" step="0.1" />
            <p class="mt-1 text-xs text-gray-500">{{ $t('email.spam.greylistScoreDesc') }}</p>
          </div>
        </div>
        <div class="flex justify-end">
          <VButton type="submit" variant="primary" :loading="savingScores">
            {{ $t('common.save') }}
          </VButton>
        </div>
      </form>
    </VCard>

    <!-- Whitelist & Blacklist -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Whitelist -->
      <VCard>
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ $t('email.spam.whitelist') }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('email.spam.whitelistDesc') }}
            </p>
          </div>
          <VButton variant="secondary" size="sm" :icon="PlusIcon" @click="showWhitelistModal = true">
            {{ $t('email.spam.addEntry') }}
          </VButton>
        </div>
        <div class="space-y-2">
          <div v-if="whitelist.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
            {{ $t('email.spam.noEntries') }}
          </div>
          <div
            v-for="item in whitelist"
            :key="item.entry"
            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
          >
            <div class="flex items-center space-x-3">
              <CheckCircleIcon class="w-5 h-5 text-green-500" />
              <span class="font-medium text-gray-900 dark:text-white">{{ item.entry }}</span>
              <VBadge variant="secondary" size="sm">{{ item.type }}</VBadge>
            </div>
            <VButton variant="danger" size="sm" @click="removeFromWhitelist(item.entry)">
              <TrashIcon class="w-4 h-4" />
            </VButton>
          </div>
        </div>
      </VCard>

      <!-- Blacklist -->
      <VCard>
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ $t('email.spam.blacklist') }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('email.spam.blacklistDesc') }}
            </p>
          </div>
          <VButton variant="secondary" size="sm" :icon="PlusIcon" @click="showBlacklistModal = true">
            {{ $t('email.spam.addEntry') }}
          </VButton>
        </div>
        <div class="space-y-2">
          <div v-if="blacklist.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
            {{ $t('email.spam.noEntries') }}
          </div>
          <div
            v-for="item in blacklist"
            :key="item.entry"
            class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg"
          >
            <div class="flex items-center space-x-3">
              <XCircleIcon class="w-5 h-5 text-red-500" />
              <span class="font-medium text-gray-900 dark:text-white">{{ item.entry }}</span>
              <VBadge variant="secondary" size="sm">{{ item.type }}</VBadge>
            </div>
            <VButton variant="danger" size="sm" @click="removeFromBlacklist(item.entry)">
              <TrashIcon class="w-4 h-4" />
            </VButton>
          </div>
        </div>
      </VCard>
    </div>

    <!-- Add to Whitelist Modal -->
    <VModal v-model="showWhitelistModal" :title="$t('email.spam.whitelist')">
      <form @submit.prevent="addToWhitelist">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('email.spam.entry') }}
            </label>
            <VInput
              v-model="newWhitelistEntry"
              :placeholder="$t('email.spam.entryPlaceholder')"
              required
            />
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showWhitelistModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="addingWhitelist">
            {{ $t('common.save') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Add to Blacklist Modal -->
    <VModal v-model="showBlacklistModal" :title="$t('email.spam.blacklist')">
      <form @submit.prevent="addToBlacklist">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('email.spam.entry') }}
            </label>
            <VInput
              v-model="newBlacklistEntry"
              :placeholder="$t('email.spam.entryPlaceholder')"
              required
            />
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showBlacklistModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="addingBlacklist">
            {{ $t('common.save') }}
          </VButton>
        </div>
      </form>
    </VModal>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VInput from '@/components/ui/VInput.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VModal from '@/components/ui/VModal.vue'
import {
  PlusIcon,
  TrashIcon,
  CheckCircleIcon,
  XCircleIcon
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

// State
const loading = ref(false)
const status = ref({ running: false, version: '' })
const statistics = ref({
  scanned: 0,
  spam_count: 0,
  ham_count: 0,
  learned: 0
})
const scores = ref({
  reject: 15,
  add_header: 6,
  greylist: 4
})
const whitelist = ref([])
const blacklist = ref([])

// Modal states
const showWhitelistModal = ref(false)
const showBlacklistModal = ref(false)
const newWhitelistEntry = ref('')
const newBlacklistEntry = ref('')

// Loading states
const savingScores = ref(false)
const addingWhitelist = ref(false)
const addingBlacklist = ref(false)

// Methods
async function fetchSettings() {
  loading.value = true
  try {
    const response = await api.get('/mail/spam/settings')
    const data = response.data.data
    status.value = data.status
    statistics.value = data.statistics
    scores.value = data.scores
  } catch (err) {
    console.error('Failed to fetch spam settings:', err)
  } finally {
    loading.value = false
  }
}

async function fetchWhitelist() {
  try {
    const response = await api.get('/mail/spam/whitelist')
    whitelist.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch whitelist:', err)
  }
}

async function fetchBlacklist() {
  try {
    const response = await api.get('/mail/spam/blacklist')
    blacklist.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch blacklist:', err)
  }
}

async function saveScores() {
  savingScores.value = true
  try {
    await api.put('/mail/spam/settings', scores.value)
    appStore.showToast({ type: 'success', message: t('email.spam.settingsSaved') })
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('email.spam.settingsError') })
  } finally {
    savingScores.value = false
  }
}

async function addToWhitelist() {
  addingWhitelist.value = true
  try {
    await api.post('/mail/spam/whitelist', { entry: newWhitelistEntry.value })
    appStore.showToast({ type: 'success', message: t('email.spam.addedToWhitelist') })
    showWhitelistModal.value = false
    newWhitelistEntry.value = ''
    await fetchWhitelist()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  } finally {
    addingWhitelist.value = false
  }
}

async function removeFromWhitelist(entry) {
  try {
    await api.delete(`/mail/spam/whitelist/${encodeURIComponent(entry)}`)
    appStore.showToast({ type: 'success', message: t('email.spam.removedFromWhitelist') })
    await fetchWhitelist()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  }
}

async function addToBlacklist() {
  addingBlacklist.value = true
  try {
    await api.post('/mail/spam/blacklist', { entry: newBlacklistEntry.value })
    appStore.showToast({ type: 'success', message: t('email.spam.addedToBlacklist') })
    showBlacklistModal.value = false
    newBlacklistEntry.value = ''
    await fetchBlacklist()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  } finally {
    addingBlacklist.value = false
  }
}

async function removeFromBlacklist(entry) {
  try {
    await api.delete(`/mail/spam/blacklist/${encodeURIComponent(entry)}`)
    appStore.showToast({ type: 'success', message: t('email.spam.removedFromBlacklist') })
    await fetchBlacklist()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  }
}

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchSettings(),
    fetchWhitelist(),
    fetchBlacklist()
  ])
})
</script>
