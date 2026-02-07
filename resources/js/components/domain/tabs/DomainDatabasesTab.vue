<template>
  <VCard>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
        {{ $t('domainDetail.databases') }}
      </h3>
      <div class="flex items-center space-x-2">
        <VButton variant="secondary" size="sm" @click="openPhpMyAdmin">
          phpMyAdmin
        </VButton>
        <VButton variant="primary" size="sm" :icon="PlusIcon" @click="openCreateModal">
          {{ $t('databases.create') }}
        </VButton>
      </div>
    </div>

    <VLoadingSkeleton v-if="loading" class="h-48" />

    <template v-else-if="databases.length > 0">
      <div class="space-y-3">
        <div
          v-for="db in databases"
          :key="db.id"
          class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-lg"
        >
          <div class="flex items-center">
            <CircleStackIcon class="w-8 h-8 text-purple-500 mr-4" />
            <div>
              <p class="font-medium text-gray-900 dark:text-white">{{ db.name }}</p>
              <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ db.size_formatted || formatSize(db.size_bytes) }} | {{ db.charset }}
              </p>
              <div v-if="db.users && db.users.length > 0" class="flex flex-wrap gap-1 mt-1">
                <VBadge v-for="user in db.users.slice(0, 3)" :key="user.id" variant="secondary" size="sm">
                  {{ user.original_username }}
                </VBadge>
                <VBadge v-if="db.users.length > 3" variant="secondary" size="sm">
                  +{{ db.users.length - 3 }}
                </VBadge>
              </div>
            </div>
          </div>
          <div class="flex items-center space-x-2">
            <VButton variant="ghost" size="sm" @click="openPhpMyAdminDb(db)" :title="'phpMyAdmin'">
              <span class="text-xs">phpMyAdmin</span>
            </VButton>
            <VButton
              variant="ghost"
              size="sm"
              :icon="UserPlusIcon"
              @click="openAddUserModal(db)"
              :title="$t('databases.addUser')"
            />
            <VButton
              variant="ghost"
              size="sm"
              :icon="ArrowUpTrayIcon"
              @click="openImportModal(db)"
              :title="$t('databases.import')"
            />
            <VButton
              variant="ghost"
              size="sm"
              :icon="ArrowDownTrayIcon"
              @click="backupDatabase(db)"
              :loading="backingUp === db.id"
              :title="$t('databases.backup')"
            />
            <VButton
              variant="ghost"
              size="sm"
              :icon="TrashIcon"
              class="text-red-500"
              @click="confirmDelete(db)"
              :title="$t('common.delete')"
            />
          </div>
        </div>
      </div>
    </template>

    <VEmptyState
      v-else
      :title="$t('databases.noDatabases')"
      :description="$t('databases.noDatabasesDesc')"
      icon="CircleStackIcon"
    >
      <VButton variant="primary" :icon="PlusIcon" @click="openCreateModal">
        {{ $t('databases.createFirst') }}
      </VButton>
    </VEmptyState>

    <!-- Create Database Modal -->
    <VModal v-model:show="showCreateModal" :title="$t('databases.createDatabase')">
      <form @submit.prevent="createDatabase">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.name') }}
            </label>
            <VInput v-model="createForm.name" :placeholder="$t('databases.namePlaceholder')" required />
            <p class="mt-1 text-xs text-gray-500">{{ $t('databases.nameHint') }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('databases.charset') }}
              </label>
              <select
                v-model="createForm.charset"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              >
                <option value="utf8mb4">utf8mb4 ({{ $t('common.recommended') }})</option>
                <option value="utf8">utf8</option>
                <option value="latin1">latin1</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('databases.collation') }}
              </label>
              <select
                v-model="createForm.collation"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              >
                <option value="utf8mb4_unicode_ci">utf8mb4_unicode_ci</option>
                <option value="utf8mb4_general_ci">utf8mb4_general_ci</option>
                <option value="utf8_unicode_ci">utf8_unicode_ci</option>
              </select>
            </div>
          </div>

          <!-- Create database user checkbox -->
          <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <label class="flex items-center">
              <input
                type="checkbox"
                v-model="createForm.create_user"
                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
              />
              <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                {{ $t('databases.createUserWithDatabase') }}
              </span>
            </label>
          </div>

          <!-- Database user fields -->
          <template v-if="createForm.create_user">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('databases.username') }}
              </label>
              <VInput v-model="createForm.username" :placeholder="createForm.name || 'dbuser'" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('databases.password') }}
              </label>
              <div class="flex gap-2">
                <VInput
                  v-model="createForm.password"
                  :type="showCreatePassword ? 'text' : 'password'"
                  class="flex-1"
                />
                <VButton type="button" variant="secondary" @click="showCreatePassword = !showCreatePassword">
                  <EyeIcon v-if="!showCreatePassword" class="w-5 h-5" />
                  <EyeSlashIcon v-else class="w-5 h-5" />
                </VButton>
                <VButton type="button" variant="secondary" @click="generatePassword">
                  {{ $t('common.generate') }}
                </VButton>
              </div>
            </div>
          </template>
        </div>
        <div class="mt-6 flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showCreateModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="creating">
            {{ $t('common.create') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Add User Modal -->
    <VModal v-model:show="showAddUserModal" :title="$t('databases.addUser')">
      <form @submit.prevent="grantAccess">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.database') }}
            </label>
            <VInput :model-value="selectedDatabase?.name" disabled />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.selectUser') }}
            </label>
            <select
              v-model="grantForm.database_user_id"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              required
            >
              <option value="">{{ $t('databases.selectUserPlaceholder') }}</option>
              <option v-for="user in databaseUsers" :key="user.id" :value="user.id">
                {{ user.username }}@{{ user.host }}
              </option>
            </select>
          </div>
        </div>
        <div class="mt-6 flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showAddUserModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="granting">
            {{ $t('databases.grantAccess') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Delete Confirmation -->
    <VConfirmDialog
      v-model="showDeleteConfirm"
      :title="$t('databases.deleteDatabase')"
      :message="$t('databases.deleteConfirm', { name: deletingDatabase?.name })"
      :loading="deleting"
      @confirm="deleteDatabase"
    />

    <!-- Import Modal -->
    <VModal v-model:show="showImportModal" :title="$t('databases.importSql')">
      <form @submit.prevent="importSql">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.database') }}
            </label>
            <VInput :model-value="selectedDatabase?.name" disabled />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              {{ $t('databases.selectFile') }}
            </label>
            <div
              class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center cursor-pointer hover:border-primary-500 transition-colors"
              @click="$refs.importFileInput.click()"
            >
              <ArrowUpTrayIcon class="w-10 h-10 mx-auto text-gray-400 mb-2" />
              <p class="text-gray-600 dark:text-gray-400">
                {{ importFile ? importFile.name : $t('databases.selectSqlFile') }}
              </p>
              <input
                ref="importFileInput"
                type="file"
                accept=".sql,.gz,.zip"
                class="hidden"
                @change="handleImportFileSelect"
              />
            </div>
          </div>
        </div>
        <div class="mt-6 flex justify-end space-x-3">
          <VButton variant="secondary" type="button" @click="showImportModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="primary" type="submit" :loading="importing" :disabled="!importFile">
            {{ $t('databases.import') }}
          </VButton>
        </div>
      </form>
    </VModal>
  </VCard>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VInput from '@/components/ui/VInput.vue'
import VModal from '@/components/ui/VModal.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VEmptyState from '@/components/ui/VEmptyState.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import {
  CircleStackIcon,
  PlusIcon,
  TrashIcon,
  UserPlusIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  EyeIcon,
  EyeSlashIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  domain: {
    type: Object,
    required: true
  }
})

const { t } = useI18n()
const appStore = useAppStore()

// State
const databases = ref([])
const databaseUsers = ref([])
const loading = ref(false)
const showCreateModal = ref(false)
const showAddUserModal = ref(false)
const showDeleteConfirm = ref(false)
const showImportModal = ref(false)
const creating = ref(false)
const granting = ref(false)
const deleting = ref(false)
const backingUp = ref(null)
const importing = ref(false)
const showCreatePassword = ref(false)
const importFile = ref(null)

// Form data
const createForm = ref({
  name: '',
  charset: 'utf8mb4',
  collation: 'utf8mb4_unicode_ci',
  create_user: true,
  username: '',
  password: ''
})

const grantForm = ref({
  database_user_id: ''
})

// Selected items
const selectedDatabase = ref(null)
const deletingDatabase = ref(null)

// Watch for name changes to auto-fill username
watch(() => createForm.value.name, (newName) => {
  if (createForm.value.create_user && !createForm.value.username) {
    createForm.value.username = newName
  }
})

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

function generateRandomPassword() {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'
  let password = ''
  for (let i = 0; i < 16; i++) {
    password += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  return password
}

function generatePassword() {
  createForm.value.password = generateRandomPassword()
}

async function fetchDatabases() {
  loading.value = true
  try {
    const response = await api.get('/databases', {
      params: { domain_id: props.domain.id }
    })
    databases.value = response.data.data || []
  } catch (err) {
    console.error('Failed to fetch databases:', err)
  } finally {
    loading.value = false
  }
}

async function fetchDatabaseUsers() {
  try {
    const response = await api.get('/database-users')
    databaseUsers.value = response.data.data || []
  } catch (err) {
    console.error('Failed to fetch database users:', err)
  }
}

function openCreateModal() {
  createForm.value = {
    name: '',
    charset: 'utf8mb4',
    collation: 'utf8mb4_unicode_ci',
    create_user: true,
    username: '',
    password: generateRandomPassword()
  }
  showCreatePassword.value = false
  showCreateModal.value = true
}

async function createDatabase() {
  creating.value = true
  try {
    await api.post('/databases', {
      ...createForm.value,
      domain_id: props.domain.id
    })

    showCreateModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('databases.createSuccess')
    })
    await Promise.all([fetchDatabases(), fetchDatabaseUsers()])
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.createError')
    })
  } finally {
    creating.value = false
  }
}

function openAddUserModal(database) {
  selectedDatabase.value = database
  grantForm.value = { database_user_id: '' }
  showAddUserModal.value = true
}

async function grantAccess() {
  if (!selectedDatabase.value || !grantForm.value.database_user_id) return
  granting.value = true
  try {
    await api.post(`/database-users/${grantForm.value.database_user_id}/grant`, {
      database_id: selectedDatabase.value.id
    })
    showAddUserModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('databases.accessGranted')
    })
    await fetchDatabases()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.grantError')
    })
  } finally {
    granting.value = false
  }
}

function getPhpMyAdminBaseUrl() {
  // phpMyAdmin runs on port 80, not on the panel port
  const hostname = window.location.hostname
  return `http://${hostname}/phpmyadmin`
}

function openPhpMyAdmin() {
  window.open(getPhpMyAdminBaseUrl(), '_blank')
}

async function openPhpMyAdminDb(db) {
  try {
    // Try to get SSO URL for auto-login
    const response = await api.get(`/databases/${db.id}/phpmyadmin-sso`)
    if (response.data.success && response.data.data.sso_url) {
      // Open phpMyAdmin with auto-login
      window.open(`${getPhpMyAdminBaseUrl()}${response.data.data.sso_url.replace('/phpmyadmin', '')}`, '_blank')
      return
    }
  } catch (err) {
    // SSO not available, show info message
    if (err.response?.data?.error?.code === 'NO_STORED_PASSWORD') {
      appStore.showToast({
        type: 'info',
        message: t('databases.ssoNotAvailable')
      })
    }
    console.log('SSO not available, falling back to regular phpMyAdmin')
  }

  // Fallback: open phpMyAdmin with database selected (user needs to login manually)
  window.open(`${getPhpMyAdminBaseUrl()}?db=${encodeURIComponent(db.name)}`, '_blank')
}

async function backupDatabase(database) {
  backingUp.value = database.id
  try {
    await api.post(`/databases/${database.id}/backup`)
    appStore.showToast({
      type: 'success',
      message: t('databases.backupSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.backupError')
    })
  } finally {
    backingUp.value = null
  }
}

function confirmDelete(db) {
  deletingDatabase.value = db
  showDeleteConfirm.value = true
}

function openImportModal(database) {
  selectedDatabase.value = database
  importFile.value = null
  showImportModal.value = true
}

function handleImportFileSelect(event) {
  importFile.value = event.target.files[0] || null
}

async function importSql() {
  if (!selectedDatabase.value || !importFile.value) return
  importing.value = true
  try {
    const formData = new FormData()
    formData.append('file', importFile.value)

    await api.post(`/databases/${selectedDatabase.value.id}/import`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
    showImportModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('databases.importSuccess')
    })
    await fetchDatabases()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.importError')
    })
  } finally {
    importing.value = false
  }
}

async function deleteDatabase() {
  if (!deletingDatabase.value) return
  deleting.value = true
  try {
    await api.delete(`/databases/${deletingDatabase.value.id}`)
    showDeleteConfirm.value = false
    appStore.showToast({
      type: 'success',
      message: t('databases.deleteSuccess')
    })
    await fetchDatabases()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.deleteError')
    })
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchDatabases(), fetchDatabaseUsers()])
})
</script>
