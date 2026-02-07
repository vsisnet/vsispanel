<template>
  <VCard>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
        {{ $t('domainDetail.emailAccounts') }}
      </h3>
      <VButton
        v-if="mailDomain"
        variant="primary"
        size="sm"
        :icon="PlusIcon"
        @click="openCreateModal"
      >
        {{ $t('email.createAccount') }}
      </VButton>
    </div>

    <VLoadingSkeleton v-if="loading" class="h-48" />

    <!-- Mail Domain Not Enabled -->
    <div v-else-if="!mailDomain" class="text-center py-8">
      <EnvelopeIcon class="w-16 h-16 mx-auto text-gray-400 mb-4" />
      <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
        {{ $t('email.mailNotEnabled') }}
      </h4>
      <p class="text-gray-500 dark:text-gray-400 mb-4">
        {{ $t('email.enableMailFirst') }}
      </p>
      <VButton variant="primary" :loading="enablingMail" @click="enableMail">
        {{ $t('email.enableMail') }}
      </VButton>
    </div>

    <!-- Email Accounts List -->
    <template v-else-if="emailAccounts.length > 0">
      <div class="space-y-3">
        <div
          v-for="account in emailAccounts"
          :key="account.id"
          class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg"
        >
          <div class="flex items-center">
            <EnvelopeIcon class="w-8 h-8 text-blue-500 mr-4" />
            <div>
              <p class="font-medium text-gray-900 dark:text-white">
                {{ account.email }}
              </p>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ formatSize(account.used_quota_bytes) }} / {{ formatSize(account.quota_mb * 1024 * 1024) }}
              </p>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <VBadge :variant="account.status === 'active' ? 'success' : 'secondary'" size="sm">
              {{ account.status === 'active' ? $t('common.active') : $t('common.inactive') }}
            </VBadge>
            <VButton variant="ghost" size="sm" :icon="KeyIcon" @click="openPasswordModal(account)" :title="$t('email.changePassword')" />
            <VButton variant="ghost" size="sm" :icon="PencilIcon" @click="openEditModal(account)" :title="$t('common.edit')" />
            <VButton
              variant="ghost"
              size="sm"
              :icon="TrashIcon"
              class="text-red-500"
              @click="confirmDelete(account)"
              :title="$t('common.delete')"
            />
          </div>
        </div>
      </div>
    </template>

    <VEmptyState
      v-else
      :title="$t('email.noAccounts')"
      :description="$t('email.noAccountsDesc')"
      icon="EnvelopeIcon"
    >
      <VButton variant="primary" :icon="PlusIcon" @click="openCreateModal">
        {{ $t('email.createFirst') }}
      </VButton>
    </VEmptyState>
  </VCard>

  <!-- Create Account Modal -->
  <VModal v-model="showCreateModal" :title="$t('email.createAccount')">
    <form @submit.prevent="createAccount">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('email.username') }}
          </label>
          <div class="flex">
            <VInput
              v-model="createForm.username"
              :placeholder="$t('email.usernamePlaceholder')"
              class="flex-1 rounded-r-none"
              required
            />
            <span class="inline-flex items-center px-3 border border-l-0 border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-r-lg">
              @{{ domain.name }}
            </span>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('email.password') }}
          </label>
          <VInput
            v-model="createForm.password"
            type="password"
            :placeholder="$t('email.passwordPlaceholder')"
            required
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('email.quota') }} (MB)
          </label>
          <VInput
            v-model.number="createForm.quota_mb"
            type="number"
            min="1"
            max="10240"
            placeholder="1024"
          />
        </div>
      </div>
      <div class="mt-6 flex justify-end gap-3">
        <VButton type="button" variant="secondary" @click="showCreateModal = false">
          {{ $t('common.cancel') }}
        </VButton>
        <VButton type="submit" variant="primary" :loading="creating">
          {{ $t('common.create') }}
        </VButton>
      </div>
    </form>
  </VModal>

  <!-- Edit Account Modal -->
  <VModal v-model="showEditModal" :title="$t('email.editAccount')">
    <form @submit.prevent="updateAccount">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('email.email') }}
          </label>
          <VInput :model-value="editingAccount?.email" disabled />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('email.quota') }} (MB)
          </label>
          <VInput
            v-model.number="editForm.quota_mb"
            type="number"
            min="1"
            max="10240"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('email.status') }}
          </label>
          <select
            v-model="editForm.status"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
          >
            <option value="active">{{ $t('common.active') }}</option>
            <option value="suspended">{{ $t('common.suspended') }}</option>
          </select>
        </div>
      </div>
      <div class="mt-6 flex justify-end gap-3">
        <VButton type="button" variant="secondary" @click="showEditModal = false">
          {{ $t('common.cancel') }}
        </VButton>
        <VButton type="submit" variant="primary" :loading="updating">
          {{ $t('common.save') }}
        </VButton>
      </div>
    </form>
  </VModal>

  <!-- Change Password Modal -->
  <VModal v-model="showPasswordModal" :title="$t('email.changePassword')">
    <form @submit.prevent="changePassword">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('email.email') }}
          </label>
          <VInput :model-value="editingAccount?.email" disabled />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('email.newPassword') }}
          </label>
          <VInput
            v-model="passwordForm.password"
            type="password"
            :placeholder="$t('email.newPasswordPlaceholder')"
            required
          />
        </div>
      </div>
      <div class="mt-6 flex justify-end gap-3">
        <VButton type="button" variant="secondary" @click="showPasswordModal = false">
          {{ $t('common.cancel') }}
        </VButton>
        <VButton type="submit" variant="primary" :loading="changingPassword">
          {{ $t('common.save') }}
        </VButton>
      </div>
    </form>
  </VModal>

  <!-- Delete Confirmation -->
  <VConfirmDialog
    v-model="showDeleteConfirm"
    :title="$t('email.deleteAccount')"
    :message="$t('email.deleteConfirm', { email: deletingAccount?.email })"
    :loading="deleting"
    @confirm="deleteAccount"
  />
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
import VEmptyState from '@/components/ui/VEmptyState.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import {
  EnvelopeIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  KeyIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  domain: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const appStore = useAppStore()

// State
const emailAccounts = ref([])
const mailDomain = ref(null)
const loading = ref(true)
const enablingMail = ref(false)

// Modal states
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showPasswordModal = ref(false)
const showDeleteConfirm = ref(false)

// Form states
const creating = ref(false)
const updating = ref(false)
const changingPassword = ref(false)
const deleting = ref(false)

// Form data
const createForm = ref({
  username: '',
  password: '',
  quota_mb: 1024
})

const editForm = ref({
  quota_mb: 1024,
  status: 'active'
})

const passwordForm = ref({
  password: ''
})

const editingAccount = ref(null)
const deletingAccount = ref(null)

// Methods
function formatSize(bytes) {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  return `${size.toFixed(1)} ${units[unitIndex]}`
}

async function fetchMailDomain() {
  try {
    const response = await api.get('/mail/domains', {
      params: { domain_id: props.domain.id }
    })
    const domains = response.data.data || []
    mailDomain.value = domains.find(d => d.domain_id === props.domain.id) || null
  } catch (err) {
    console.error('Failed to fetch mail domain:', err)
    mailDomain.value = null
  }
}

async function fetchEmailAccounts() {
  if (!mailDomain.value) {
    emailAccounts.value = []
    return
  }

  try {
    const response = await api.get('/mail/accounts', {
      params: { mail_domain_id: mailDomain.value.id }
    })
    emailAccounts.value = response.data.data || []
  } catch (err) {
    console.error('Failed to fetch email accounts:', err)
    emailAccounts.value = []
  }
}

async function enableMail() {
  enablingMail.value = true
  try {
    const response = await api.post('/mail/domains', {
      domain_id: props.domain.id
    })
    mailDomain.value = response.data.data
    appStore.showToast({
      type: 'success',
      message: t('email.mailEnabled')
    })
    await fetchEmailAccounts()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('email.enableError')
    })
  } finally {
    enablingMail.value = false
  }
}

function openCreateModal() {
  createForm.value = {
    username: '',
    password: '',
    quota_mb: 1024
  }
  showCreateModal.value = true
}

async function createAccount() {
  creating.value = true
  try {
    await api.post('/mail/accounts', {
      mail_domain_id: mailDomain.value.id,
      username: createForm.value.username,
      password: createForm.value.password,
      quota_mb: createForm.value.quota_mb
    })
    showCreateModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('email.accountCreated')
    })
    await fetchEmailAccounts()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('email.createError')
    })
  } finally {
    creating.value = false
  }
}

function openEditModal(account) {
  editingAccount.value = account
  editForm.value = {
    quota_mb: account.quota_mb,
    status: account.status
  }
  showEditModal.value = true
}

async function updateAccount() {
  if (!editingAccount.value) return
  updating.value = true
  try {
    await api.put(`/mail/accounts/${editingAccount.value.id}`, editForm.value)
    showEditModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('email.accountUpdated')
    })
    await fetchEmailAccounts()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('email.updateError')
    })
  } finally {
    updating.value = false
  }
}

function openPasswordModal(account) {
  editingAccount.value = account
  passwordForm.value = { password: '' }
  showPasswordModal.value = true
}

async function changePassword() {
  if (!editingAccount.value) return
  changingPassword.value = true
  try {
    await api.put(`/mail/accounts/${editingAccount.value.id}/password`, passwordForm.value)
    showPasswordModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('email.passwordChanged')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('email.passwordError')
    })
  } finally {
    changingPassword.value = false
  }
}

function confirmDelete(account) {
  deletingAccount.value = account
  showDeleteConfirm.value = true
}

async function deleteAccount() {
  if (!deletingAccount.value) return
  deleting.value = true
  try {
    await api.delete(`/mail/accounts/${deletingAccount.value.id}`)
    showDeleteConfirm.value = false
    appStore.showToast({
      type: 'success',
      message: t('email.accountDeleted')
    })
    await fetchEmailAccounts()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('email.deleteError')
    })
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  loading.value = true
  await fetchMailDomain()
  await fetchEmailAccounts()
  loading.value = false
})
</script>
