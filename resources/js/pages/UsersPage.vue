<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('users.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('users.description') }}</p>
      </div>
      <button
        @click="openCreateModal"
        class="inline-flex items-center px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium rounded-lg transition-colors"
      >
        <UserPlusIcon class="w-5 h-5 mr-2" />
        {{ $t('users.createUser') }}
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('users.totalUsers') }}</div>
        <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('users.activeUsers') }}</div>
        <div class="mt-1 text-2xl font-bold text-green-600">{{ stats.active }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('users.suspendedUsers') }}</div>
        <div class="mt-1 text-2xl font-bold text-red-600">{{ stats.suspended }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('users.admins') }}</div>
        <div class="mt-1 text-2xl font-bold text-purple-600">{{ stats.admins }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('users.resellers') }}</div>
        <div class="mt-1 text-2xl font-bold text-blue-600">{{ stats.resellers }}</div>
      </div>
      <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('users.regularUsers') }}</div>
        <div class="mt-1 text-2xl font-bold text-gray-600">{{ stats.users }}</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
      <div class="flex flex-wrap gap-4">
        <div class="flex-1 min-w-[200px]">
          <input
            v-model="filters.search"
            type="text"
            :placeholder="$t('users.searchPlaceholder')"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            @input="debouncedFetch"
          />
        </div>
        <select
          v-model="filters.role"
          class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
          @change="fetchUsers"
        >
          <option value="">{{ $t('users.allRoles') }}</option>
          <option value="admin">{{ $t('users.roleAdmin') }}</option>
          <option value="reseller">{{ $t('users.roleReseller') }}</option>
          <option value="user">{{ $t('users.roleUser') }}</option>
        </select>
        <select
          v-model="filters.status"
          class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
          @change="fetchUsers"
        >
          <option value="">{{ $t('users.allStatuses') }}</option>
          <option value="active">{{ $t('users.statusActive') }}</option>
          <option value="suspended">{{ $t('users.statusSuspended') }}</option>
        </select>
      </div>
    </div>

    <!-- Users Table -->
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('users.user') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('users.username') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('users.role') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('users.status') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('users.twoFactor') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('users.lastLogin') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('common.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="7" class="px-6 py-12 text-center">
                <div class="flex items-center justify-center">
                  <svg class="animate-spin h-8 w-8 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                </div>
              </td>
            </tr>
            <tr v-else-if="users.length === 0">
              <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                {{ $t('users.noUsers') }}
              </td>
            </tr>
            <tr v-for="user in users" :key="user.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <div class="flex-shrink-0 h-10 w-10">
                    <div class="h-10 w-10 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                      <span class="text-primary-600 dark:text-primary-400 font-medium text-sm">
                        {{ user.name.charAt(0).toUpperCase() }}
                      </span>
                    </div>
                  </div>
                  <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ user.name }}</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ user.email }}</div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                {{ user.username }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getRoleBadgeClass(user.role)" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ $t(`users.role${capitalize(user.role)}`) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="getStatusBadgeClass(user.status)" class="px-2 py-1 text-xs font-medium rounded-full">
                  {{ $t(`users.status${capitalize(user.status)}`) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="user.two_factor_enabled" class="text-green-600">
                  <ShieldCheckIcon class="w-5 h-5" />
                </span>
                <span v-else class="text-gray-400">
                  <ShieldExclamationIcon class="w-5 h-5" />
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                <template v-if="user.last_login_at">
                  <div>{{ formatDate(user.last_login_at) }}</div>
                  <div class="text-xs">{{ user.last_login_ip }}</div>
                </template>
                <span v-else>{{ $t('users.neverLoggedIn') }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex items-center justify-end space-x-2">
                  <button
                    @click="openEditModal(user)"
                    class="text-primary-600 hover:text-primary-900 dark:hover:text-primary-400"
                    :title="$t('common.edit')"
                  >
                    <PencilIcon class="w-5 h-5" />
                  </button>
                  <button
                    v-if="user.status === 'active'"
                    @click="confirmSuspend(user)"
                    class="text-orange-600 hover:text-orange-900 dark:hover:text-orange-400"
                    :title="$t('users.suspend')"
                  >
                    <NoSymbolIcon class="w-5 h-5" />
                  </button>
                  <button
                    v-else
                    @click="unsuspendUser(user)"
                    class="text-green-600 hover:text-green-900 dark:hover:text-green-400"
                    :title="$t('users.unsuspend')"
                  >
                    <CheckCircleIcon class="w-5 h-5" />
                  </button>
                  <button
                    @click="confirmDelete(user)"
                    class="text-red-600 hover:text-red-900 dark:hover:text-red-400"
                    :title="$t('common.delete')"
                  >
                    <TrashIcon class="w-5 h-5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="meta.total > 0" class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            @click="changePage(meta.current_page - 1)"
            :disabled="meta.current_page === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            {{ $t('common.previous') }}
          </button>
          <button
            @click="changePage(meta.current_page + 1)"
            :disabled="meta.current_page === meta.last_page"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50"
          >
            {{ $t('common.next') }}
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700 dark:text-gray-300">
              {{ $t('common.showing') }}
              <span class="font-medium">{{ (meta.current_page - 1) * meta.per_page + 1 }}</span>
              {{ $t('common.to') }}
              <span class="font-medium">{{ Math.min(meta.current_page * meta.per_page, meta.total) }}</span>
              {{ $t('common.of') }}
              <span class="font-medium">{{ meta.total }}</span>
              {{ $t('common.results') }}
            </p>
          </div>
          <div class="flex items-center space-x-4">
            <select
              v-model="filters.per_page"
              @change="fetchUsers"
              class="px-3 py-1 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm"
            >
              <option :value="10">10</option>
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
            </select>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
              <button
                @click="changePage(meta.current_page - 1)"
                :disabled="meta.current_page === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
              >
                <ChevronLeftIcon class="h-5 w-5" />
              </button>
              <button
                @click="changePage(meta.current_page + 1)"
                :disabled="meta.current_page === meta.last_page"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50"
              >
                <ChevronRightIcon class="h-5 w-5" />
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit User Modal -->
    <VModal :show="showUserModal" @close="closeUserModal" :title="editingUser ? $t('users.editUser') : $t('users.createUser')">
      <form @submit.prevent="saveUser" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('users.name') }} *
            </label>
            <input
              v-model="userForm.name"
              type="text"
              required
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('users.username') }} *
            </label>
            <input
              v-model="userForm.username"
              type="text"
              required
              pattern="^[a-zA-Z0-9_]+$"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            />
            <p class="mt-1 text-xs text-gray-500">{{ $t('users.usernameHint') }}</p>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('users.email') }} *
          </label>
          <input
            v-model="userForm.email"
            type="email"
            required
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('users.password') }} {{ editingUser ? '' : '*' }}
          </label>
          <div class="flex gap-2">
            <div class="relative flex-1">
              <input
                v-model="userForm.password"
                :type="showPassword ? 'text' : 'password'"
                :required="!editingUser"
                :placeholder="editingUser ? $t('users.leaveEmptyToKeep') : ''"
                class="w-full px-4 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              >
                <EyeIcon v-if="!showPassword" class="w-5 h-5" />
                <EyeSlashIcon v-else class="w-5 h-5" />
              </button>
            </div>
            <button
              type="button"
              @click="generatePassword"
              class="px-3 py-2 text-sm font-medium text-primary-600 bg-primary-50 dark:bg-primary-900/30 dark:text-primary-400 border border-primary-200 dark:border-primary-800 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900/50 whitespace-nowrap"
              :title="$t('users.generatePassword')"
            >
              <ArrowPathIcon class="w-5 h-5" />
            </button>
          </div>
          <p class="mt-1 text-xs text-gray-500">{{ $t('users.passwordHint') }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('users.role') }} *
            </label>
            <select
              v-model="userForm.role"
              required
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            >
              <option value="admin">{{ $t('users.roleAdmin') }}</option>
              <option value="reseller">{{ $t('users.roleReseller') }}</option>
              <option value="user">{{ $t('users.roleUser') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('users.status') }}
            </label>
            <select
              v-model="userForm.status"
              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            >
              <option value="active">{{ $t('users.statusActive') }}</option>
              <option value="suspended">{{ $t('users.statusSuspended') }}</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end space-x-3 pt-4">
          <button
            type="button"
            @click="closeUserModal"
            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            type="submit"
            :disabled="saving"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
          >
            {{ saving ? $t('common.saving') : $t('common.save') }}
          </button>
        </div>
      </form>
    </VModal>

    <!-- Delete Confirmation Modal -->
    <VConfirmDialog
      :show="showDeleteModal"
      :title="$t('users.deleteUser')"
      :message="$t('users.confirmDelete', { name: userToDelete?.name })"
      :confirm-text="$t('common.delete')"
      confirm-variant="danger"
      @confirm="deleteUser"
      @cancel="showDeleteModal = false"
    />

    <!-- Suspend Confirmation Modal -->
    <VConfirmDialog
      :show="showSuspendModal"
      :title="$t('users.suspendUser')"
      :message="$t('users.confirmSuspend', { name: userToSuspend?.name })"
      :confirm-text="$t('users.suspend')"
      confirm-variant="warning"
      @confirm="suspendUser"
      @cancel="showSuspendModal = false"
    />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VModal from '@/components/ui/VModal.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import {
  UserPlusIcon,
  PencilIcon,
  TrashIcon,
  NoSymbolIcon,
  CheckCircleIcon,
  ShieldCheckIcon,
  ShieldExclamationIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  EyeIcon,
  EyeSlashIcon,
  ArrowPathIcon
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

const showToast = (message, type = 'info') => {
  appStore.showToast({ type, message })
}

// State
const loading = ref(false)
const saving = ref(false)
const users = ref([])
const stats = ref({
  total: 0,
  active: 0,
  suspended: 0,
  admins: 0,
  resellers: 0,
  users: 0
})
const meta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})
const filters = reactive({
  search: '',
  role: '',
  status: '',
  per_page: 15
})

// Password
const showPassword = ref(false)

// Modals
const showUserModal = ref(false)
const showDeleteModal = ref(false)
const showSuspendModal = ref(false)
const editingUser = ref(null)
const userToDelete = ref(null)
const userToSuspend = ref(null)

// Form
const userForm = reactive({
  name: '',
  email: '',
  username: '',
  password: '',
  role: 'user',
  status: 'active'
})

// Debounce for search
let searchTimeout = null
const debouncedFetch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(fetchUsers, 300)
}

// Fetch users
async function fetchUsers() {
  loading.value = true
  try {
    const params = {
      page: meta.value.current_page,
      per_page: filters.per_page,
      ...(filters.search && { search: filters.search }),
      ...(filters.role && { role: filters.role }),
      ...(filters.status && { status: filters.status })
    }
    const response = await api.get('/users', { params })
    users.value = response.data.data
    meta.value = response.data.meta
  } catch (error) {
    showToast(t('users.fetchError'), 'error')
  } finally {
    loading.value = false
  }
}

// Fetch stats
async function fetchStats() {
  try {
    const response = await api.get('/users/stats')
    stats.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch stats:', error)
  }
}

// Change page
function changePage(page) {
  if (page >= 1 && page <= meta.value.last_page) {
    meta.value.current_page = page
    fetchUsers()
  }
}

// Modal functions
function openCreateModal() {
  editingUser.value = null
  resetForm()
  showUserModal.value = true
}

function openEditModal(user) {
  editingUser.value = user
  userForm.name = user.name
  userForm.email = user.email
  userForm.username = user.username
  userForm.password = ''
  userForm.role = user.role
  userForm.status = user.status
  showUserModal.value = true
}

function closeUserModal() {
  showUserModal.value = false
  editingUser.value = null
  resetForm()
}

function resetForm() {
  userForm.name = ''
  userForm.email = ''
  userForm.username = ''
  userForm.password = ''
  userForm.role = 'user'
  userForm.status = 'active'
  showPassword.value = false
}

function generatePassword() {
  const length = 16
  const upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
  const lower = 'abcdefghijklmnopqrstuvwxyz'
  const digits = '0123456789'
  const symbols = '!@#$%^&*_+-='
  const all = upper + lower + digits + symbols

  // Ensure at least one of each type
  let password = ''
  password += upper[Math.floor(Math.random() * upper.length)]
  password += lower[Math.floor(Math.random() * lower.length)]
  password += digits[Math.floor(Math.random() * digits.length)]
  password += symbols[Math.floor(Math.random() * symbols.length)]

  for (let i = password.length; i < length; i++) {
    password += all[Math.floor(Math.random() * all.length)]
  }

  // Shuffle
  userForm.password = password.split('').sort(() => Math.random() - 0.5).join('')
  showPassword.value = true
}

// Save user
async function saveUser() {
  saving.value = true
  try {
    const data = { ...userForm }
    if (editingUser.value && !data.password) {
      delete data.password
    }

    if (editingUser.value) {
      await api.put(`/users/${editingUser.value.id}`, data)
      showToast(t('users.userUpdated'), 'success')
    } else {
      await api.post('/users', data)
      showToast(t('users.userCreated'), 'success')
    }
    closeUserModal()
    fetchUsers()
    fetchStats()
  } catch (error) {
    const message = error.response?.data?.error?.message || error.response?.data?.message || t('users.saveError')
    showToast(message, 'error')
  } finally {
    saving.value = false
  }
}

// Delete user
function confirmDelete(user) {
  userToDelete.value = user
  showDeleteModal.value = true
}

async function deleteUser() {
  try {
    await api.delete(`/users/${userToDelete.value.id}`)
    showToast(t('users.userDeleted'), 'success')
    showDeleteModal.value = false
    userToDelete.value = null
    fetchUsers()
    fetchStats()
  } catch (error) {
    const message = error.response?.data?.error?.message || t('users.deleteError')
    showToast(message, 'error')
  }
}

// Suspend user
function confirmSuspend(user) {
  userToSuspend.value = user
  showSuspendModal.value = true
}

async function suspendUser() {
  try {
    await api.post(`/users/${userToSuspend.value.id}/suspend`)
    showToast(t('users.userSuspended'), 'success')
    showSuspendModal.value = false
    userToSuspend.value = null
    fetchUsers()
    fetchStats()
  } catch (error) {
    const message = error.response?.data?.error?.message || t('users.suspendError')
    showToast(message, 'error')
  }
}

// Unsuspend user
async function unsuspendUser(user) {
  try {
    await api.post(`/users/${user.id}/unsuspend`)
    showToast(t('users.userUnsuspended'), 'success')
    fetchUsers()
    fetchStats()
  } catch (error) {
    const message = error.response?.data?.error?.message || t('users.unsuspendError')
    showToast(message, 'error')
  }
}

// Helpers
function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
}

function getRoleBadgeClass(role) {
  const classes = {
    admin: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
    reseller: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    user: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
  }
  return classes[role] || classes.user
}

function getStatusBadgeClass(status) {
  const classes = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    suspended: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
  }
  return classes[status] || classes.active
}

// Initialize
onMounted(() => {
  fetchUsers()
  fetchStats()
})
</script>
