<template>
  <div>
    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
          {{ $t('websites.title') }}
        </h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ $t('websites.description') }}
        </p>
      </div>
      <VButton
        variant="primary"
        :icon="PlusIcon"
        @click="showCreateModal = true"
      >
        {{ $t('websites.addDomain') }}
      </VButton>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <div class="flex flex-col sm:flex-row gap-4">
        <!-- Search -->
        <div class="flex-1">
          <VInput
            v-model="searchQuery"
            :placeholder="$t('websites.searchPlaceholder')"
            :icon="MagnifyingGlassIcon"
            @input="debouncedSearch"
          />
        </div>

        <!-- Status Filter -->
        <div class="w-full sm:w-40">
          <select
            v-model="statusFilter"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="handleFilterChange"
          >
            <option :value="null">{{ $t('websites.allStatuses') }}</option>
            <option value="active">{{ $t('websites.statusActive') }}</option>
            <option value="suspended">{{ $t('websites.statusSuspended') }}</option>
            <option value="pending">{{ $t('websites.statusPending') }}</option>
          </select>
        </div>

        <!-- PHP Version Filter -->
        <div class="w-full sm:w-36">
          <select
            v-model="phpFilter"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="handleFilterChange"
          >
            <option :value="null">{{ $t('websites.allPhp') }}</option>
            <option v-for="version in phpVersions" :key="version" :value="version">
              PHP {{ version }}
            </option>
          </select>
        </div>

        <!-- Refresh -->
        <VButton
          variant="secondary"
          :icon="ArrowPathIcon"
          :loading="domainsStore.loading"
          @click="refreshData"
        >
          {{ $t('common.refresh') }}
        </VButton>
      </div>

      <!-- Bulk Actions -->
      <div v-if="selectedDomains.length > 0" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center gap-4">
        <span class="text-sm text-gray-600 dark:text-gray-400">
          {{ $t('websites.selectedCount', { count: selectedDomains.length }) }}
        </span>
        <VButton
          variant="secondary"
          size="sm"
          @click="bulkSuspend"
        >
          {{ $t('websites.suspendSelected') }}
        </VButton>
        <VButton
          variant="danger"
          size="sm"
          @click="confirmBulkDelete"
        >
          {{ $t('websites.deleteSelected') }}
        </VButton>
        <VButton
          variant="ghost"
          size="sm"
          @click="clearSelection"
        >
          {{ $t('common.cancel') }}
        </VButton>
      </div>
    </VCard>

    <!-- Domains List -->
    <VCard :padding="false">
      <template v-if="domainsStore.loading && domainsStore.isEmpty">
        <VLoadingSkeleton class="h-64" />
      </template>

      <template v-else-if="domainsStore.isEmpty">
        <VEmptyState
          :title="$t('websites.noDomains')"
          :description="$t('websites.noDomainsDesc')"
          icon="GlobeAltIcon"
        >
          <VButton
            variant="primary"
            :icon="PlusIcon"
            @click="showCreateModal = true"
          >
            {{ $t('websites.addFirstDomain') }}
          </VButton>
        </VEmptyState>
      </template>

      <template v-else>
        <!-- Desktop Table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-4 py-3 w-10">
                  <input
                    type="checkbox"
                    :checked="isAllSelected"
                    :indeterminate="isPartiallySelected"
                    class="w-4 h-4 text-primary-600 rounded border-gray-300 focus:ring-primary-500"
                    @change="toggleSelectAll"
                  >
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('websites.domain') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('websites.status') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  PHP
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  SSL
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('websites.diskUsage') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('websites.createdAt') }}
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('common.actions') }}
                </th>
              </tr>
            </thead>
            <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
              <tr
                v-for="domain in domainsStore.domains"
                :key="domain.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                :class="{ 'bg-primary-50 dark:bg-primary-900/20': isSelected(domain.id) }"
              >
                <td class="px-4 py-4">
                  <input
                    type="checkbox"
                    :checked="isSelected(domain.id)"
                    class="w-4 h-4 text-primary-600 rounded border-gray-300 focus:ring-primary-500"
                    @change="toggleSelect(domain.id)"
                  >
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <GlobeAltIcon class="w-5 h-5 text-gray-400 mr-3" />
                    <div>
                      <router-link
                        :to="{ name: 'domain-detail', params: { id: domain.id } }"
                        class="text-sm font-medium text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-300 hover:underline"
                      >
                        {{ domain.name }}
                      </router-link>
                      <div v-if="domain.is_main" class="text-xs text-primary-600 dark:text-primary-400">
                        {{ $t('websites.mainDomain') }}
                      </div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <VBadge :variant="getStatusVariant(domain.status)">
                    {{ $t(`websites.status${capitalize(domain.status)}`) }}
                  </VBadge>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <VBadge variant="info" size="sm">
                    PHP {{ domain.php_version }}
                  </VBadge>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <LockClosedIcon
                      v-if="domain.ssl_enabled"
                      :class="getSslIconClass(domain)"
                    />
                    <LockOpenIcon
                      v-else
                      class="w-5 h-5 text-gray-400"
                    />
                    <span
                      v-if="domain.ssl_expires_in_days !== null"
                      class="ml-2 text-xs"
                      :class="getSslTextClass(domain)"
                    >
                      {{ domain.ssl_expires_in_days }}d
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ domain.disk_used_formatted || '0 B' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(domain.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <!-- Actions Dropdown -->
                  <div class="relative inline-block">
                    <VButton
                      :ref="el => setDropdownRef(domain.id, el)"
                      variant="ghost"
                      size="sm"
                      :icon="EllipsisVerticalIcon"
                      @click.stop="toggleDropdown(domain.id)"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile Cards -->
        <div class="md:hidden divide-y divide-gray-200 dark:divide-gray-700">
          <div
            v-for="domain in domainsStore.domains"
            :key="domain.id"
            class="p-4"
            :class="{ 'bg-primary-50 dark:bg-primary-900/20': isSelected(domain.id) }"
          >
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center">
                <input
                  type="checkbox"
                  :checked="isSelected(domain.id)"
                  class="w-4 h-4 text-primary-600 rounded border-gray-300 focus:ring-primary-500 mr-3"
                  @change="toggleSelect(domain.id)"
                >
                <GlobeAltIcon class="w-5 h-5 text-gray-400 mr-2" />
                <router-link
                  :to="{ name: 'domain-detail', params: { id: domain.id } }"
                  class="font-medium text-primary-600 dark:text-primary-400 hover:underline"
                >
                  {{ domain.name }}
                </router-link>
              </div>
              <VBadge :variant="getStatusVariant(domain.status)" size="sm">
                {{ $t(`websites.status${capitalize(domain.status)}`) }}
              </VBadge>
            </div>
            <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400 mb-2">
              <VBadge variant="info" size="sm">PHP {{ domain.php_version }}</VBadge>
              <span class="flex items-center">
                <LockClosedIcon v-if="domain.ssl_enabled" :class="['w-4 h-4 mr-1', getSslIconClass(domain)]" />
                <LockOpenIcon v-else class="w-4 h-4 text-gray-400 mr-1" />
                {{ domain.ssl_enabled ? 'SSL' : 'No SSL' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-xs text-gray-400 mb-3">
              <span>{{ domain.disk_used_formatted || '0 B' }}</span>
              <span>{{ formatDate(domain.created_at) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <a
                :href="'https://' + domain.name"
                target="_blank"
                class="text-sm text-primary-600 dark:text-primary-400 hover:underline flex items-center"
              >
                <ArrowTopRightOnSquareIcon class="w-4 h-4 mr-1" />
                {{ $t('websites.visitSite') }}
              </a>
              <div class="flex items-center space-x-2">
                <VButton variant="secondary" size="sm" @click="viewDomain(domain)">
                  {{ $t('common.view') }}
                </VButton>
                <VButton
                  variant="ghost"
                  size="sm"
                  :icon="EllipsisVerticalIcon"
                  @click="toggleDropdown(domain.id)"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Pagination -->
        <div
          v-if="domainsStore.pagination.lastPage > 1"
          class="px-6 py-4 border-t border-gray-200 dark:border-gray-700"
        >
          <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('common.showing') }}
              <span class="font-medium">{{ paginationStart }}</span>
              {{ $t('common.to') }}
              <span class="font-medium">{{ paginationEnd }}</span>
              {{ $t('common.of') }}
              <span class="font-medium">{{ domainsStore.pagination.total }}</span>
            </p>
            <div class="flex space-x-2">
              <VButton
                variant="secondary"
                size="sm"
                :disabled="domainsStore.pagination.currentPage === 1"
                @click="goToPage(domainsStore.pagination.currentPage - 1)"
              >
                {{ $t('common.previous') }}
              </VButton>
              <VButton
                variant="secondary"
                size="sm"
                :disabled="domainsStore.pagination.currentPage === domainsStore.pagination.lastPage"
                @click="goToPage(domainsStore.pagination.currentPage + 1)"
              >
                {{ $t('common.next') }}
              </VButton>
            </div>
          </div>
        </div>
      </template>
    </VCard>

    <!-- Add Website Wizard -->
    <AddWebsiteWizard
      v-model:show="showCreateModal"
      @created="handleDomainCreated"
    />

    <!-- Edit Domain Modal -->
    <EditDomainModal
      v-model:show="showEditModal"
      :domain="selectedDomain"
      @updated="handleDomainUpdated"
    />

    <!-- Delete Confirmation -->
    <VConfirmDialog
      v-model:show="showDeleteDialog"
      :title="$t('websites.deleteDomain')"
      :message="$t('websites.deleteConfirmMessage', { name: selectedDomain?.name })"
      :confirm-text="$t('common.delete')"
      :loading="isDeleting"
      variant="danger"
      @confirm="handleDelete"
    />

    <!-- Suspend Confirmation -->
    <VConfirmDialog
      v-model:show="showSuspendDialog"
      :title="$t('websites.suspendDomain')"
      :message="$t('websites.suspendConfirmMessage', { name: selectedDomain?.name })"
      :confirm-text="$t('domainDetail.suspend')"
      :loading="isSuspending"
      variant="warning"
      @confirm="handleSuspend"
    />

    <!-- Bulk Delete Confirmation -->
    <VConfirmDialog
      v-model:show="showBulkDeleteDialog"
      :title="$t('websites.bulkDeleteTitle')"
      :message="$t('websites.bulkDeleteMessage', { count: selectedDomains.length })"
      :confirm-text="$t('common.delete')"
      :loading="isDeleting"
      variant="danger"
      @confirm="handleBulkDelete"
    />

    <!-- Teleported Dropdown Menu -->
    <Teleport to="body">
      <div
        v-if="openDropdown && dropdownDomain"
        class="fixed z-[9999] w-56 rounded-lg shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5"
        :style="dropdownStyle"
        @click.stop
      >
        <div class="py-1">
          <!-- Visit Site -->
          <a
            :href="'https://' + dropdownDomain.name"
            target="_blank"
            class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <ArrowTopRightOnSquareIcon class="w-4 h-4 mr-3 text-gray-400" />
            {{ $t('websites.visitSite') }}
          </a>

          <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>

          <!-- File Manager -->
          <button
            @click="goToFileManager(dropdownDomain)"
            class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <FolderIcon class="w-4 h-4 mr-3 text-gray-400" />
            {{ $t('nav.fileManager') }}
          </button>

          <!-- Databases -->
          <button
            @click="goToDatabases(dropdownDomain)"
            class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <CircleStackIcon class="w-4 h-4 mr-3 text-gray-400" />
            {{ $t('nav.databases') }}
          </button>

          <!-- Email -->
          <button
            @click="goToEmail(dropdownDomain)"
            class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <EnvelopeIcon class="w-4 h-4 mr-3 text-gray-400" />
            {{ $t('nav.email') }}
          </button>

          <!-- DNS -->
          <button
            @click="goToDns(dropdownDomain)"
            class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <ServerStackIcon class="w-4 h-4 mr-3 text-gray-400" />
            {{ $t('nav.dns') }}
          </button>

          <!-- SSL -->
          <button
            @click="goToSsl(dropdownDomain)"
            class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <ShieldCheckIcon class="w-4 h-4 mr-3 text-gray-400" />
            {{ $t('nav.ssl') }}
          </button>

          <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>

          <!-- PHP Settings -->
          <button
            @click="goToPhpSettings(dropdownDomain)"
            class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <CodeBracketIcon class="w-4 h-4 mr-3 text-gray-400" />
            {{ $t('domainDetail.phpSettings') }}
          </button>

          <!-- Logs -->
          <button
            @click="goToLogs(dropdownDomain)"
            class="w-full flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <DocumentTextIcon class="w-4 h-4 mr-3 text-gray-400" />
            {{ $t('domainDetail.tabs.logs') }}
          </button>

          <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>

          <!-- Suspend/Unsuspend -->
          <button
            v-if="dropdownDomain.status === 'active'"
            @click="confirmSuspend(dropdownDomain)"
            class="w-full flex items-center px-4 py-2 text-sm text-orange-600 dark:text-orange-400 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <PauseCircleIcon class="w-4 h-4 mr-3" />
            {{ $t('domainDetail.suspend') }}
          </button>
          <button
            v-else-if="dropdownDomain.status === 'suspended'"
            @click="handleUnsuspend(dropdownDomain)"
            class="w-full flex items-center px-4 py-2 text-sm text-green-600 dark:text-green-400 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <PlayCircleIcon class="w-4 h-4 mr-3" />
            {{ $t('domainDetail.unsuspend') }}
          </button>

          <!-- Delete -->
          <button
            @click="confirmDelete(dropdownDomain)"
            class="w-full flex items-center px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            <TrashIcon class="w-4 h-4 mr-3" />
            {{ $t('common.delete') }}
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDomainsStore } from '@/stores/domains'
import { useAppStore } from '@/stores/app'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VInput from '@/components/ui/VInput.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import VEmptyState from '@/components/ui/VEmptyState.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import AddWebsiteWizard from '@/components/domain/AddWebsiteWizard.vue'
import EditDomainModal from '@/components/domain/EditDomainModal.vue'
import {
  GlobeAltIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  ArrowPathIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  LockClosedIcon,
  LockOpenIcon,
  EllipsisVerticalIcon,
  ArrowTopRightOnSquareIcon,
  FolderIcon,
  CircleStackIcon,
  EnvelopeIcon,
  ServerStackIcon,
  ShieldCheckIcon,
  CodeBracketIcon,
  DocumentTextIcon,
  PauseCircleIcon,
  PlayCircleIcon
} from '@heroicons/vue/24/outline'

const router = useRouter()
const { t } = useI18n()
const domainsStore = useDomainsStore()
const appStore = useAppStore()

// PHP versions
const phpVersions = ['7.4', '8.0', '8.1', '8.2', '8.3']

// State
const searchQuery = ref('')
const statusFilter = ref(null)
const phpFilter = ref(null)
const showCreateModal = ref(false)
const showEditModal = ref(false)
const showDeleteDialog = ref(false)
const showSuspendDialog = ref(false)
const showBulkDeleteDialog = ref(false)
const selectedDomain = ref(null)
const isDeleting = ref(false)
const isSuspending = ref(false)
const openDropdown = ref(null)
const selectedDomains = ref([])
const dropdownRefs = ref({})
const dropdownPosition = ref({ top: 0, left: 0 })

// Debounce timer
let searchTimeout = null

// Computed
const paginationStart = computed(() => {
  const { currentPage, perPage } = domainsStore.pagination
  return (currentPage - 1) * perPage + 1
})

const paginationEnd = computed(() => {
  const { currentPage, perPage, total } = domainsStore.pagination
  return Math.min(currentPage * perPage, total)
})

const isAllSelected = computed(() => {
  return domainsStore.domains.length > 0 && selectedDomains.value.length === domainsStore.domains.length
})

const isPartiallySelected = computed(() => {
  return selectedDomains.value.length > 0 && selectedDomains.value.length < domainsStore.domains.length
})

const dropdownDomain = computed(() => {
  if (!openDropdown.value) return null
  return domainsStore.domains.find(d => d.id === openDropdown.value)
})

const dropdownStyle = computed(() => {
  return {
    top: `${dropdownPosition.value.top}px`,
    left: `${dropdownPosition.value.left}px`
  }
})

// Methods
function capitalize(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''
}

function getStatusVariant(status) {
  switch (status) {
    case 'active': return 'success'
    case 'suspended': return 'danger'
    case 'pending': return 'warning'
    case 'disabled': return 'secondary'
    default: return 'secondary'
  }
}

function getSslIconClass(domain) {
  if (!domain.ssl_enabled) return 'w-5 h-5 text-gray-400'
  if (domain.ssl_expires_in_days !== null && domain.ssl_expires_in_days <= 7) return 'w-5 h-5 text-red-500'
  if (domain.ssl_expires_in_days !== null && domain.ssl_expires_in_days <= 30) return 'w-5 h-5 text-orange-500'
  return 'w-5 h-5 text-green-500'
}

function getSslTextClass(domain) {
  if (domain.ssl_expires_in_days <= 7) return 'text-red-500'
  if (domain.ssl_expires_in_days <= 30) return 'text-orange-500'
  return 'text-gray-500'
}

function formatDate(date) {
  if (!date) return '-'
  return new Date(date).toLocaleDateString()
}

function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    domainsStore.setFilter('search', searchQuery.value)
    domainsStore.fetchDomains()
  }, 300)
}

function handleFilterChange() {
  domainsStore.setFilter('status', statusFilter.value)
  domainsStore.setFilter('php_version', phpFilter.value)
  domainsStore.fetchDomains()
}

// Selection methods
function isSelected(id) {
  return selectedDomains.value.includes(id)
}

function toggleSelect(id) {
  const index = selectedDomains.value.indexOf(id)
  if (index > -1) {
    selectedDomains.value.splice(index, 1)
  } else {
    selectedDomains.value.push(id)
  }
}

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedDomains.value = []
  } else {
    selectedDomains.value = domainsStore.domains.map(d => d.id)
  }
}

function clearSelection() {
  selectedDomains.value = []
}

// Dropdown methods
function setDropdownRef(id, el) {
  if (el) {
    dropdownRefs.value[id] = el.$el || el
  }
}

function toggleDropdown(id) {
  if (openDropdown.value === id) {
    openDropdown.value = null
    return
  }

  openDropdown.value = id

  // Calculate position after next tick
  setTimeout(() => {
    const buttonEl = dropdownRefs.value[id]
    if (buttonEl) {
      const rect = buttonEl.getBoundingClientRect()
      const menuHeight = 400 // approximate menu height
      const menuWidth = 224 // w-56 = 14rem = 224px

      // Check if menu would go below viewport
      let top = rect.bottom + 8
      if (top + menuHeight > window.innerHeight) {
        top = rect.top - menuHeight - 8
      }

      // Check if menu would go past right edge
      let left = rect.right - menuWidth
      if (left < 8) {
        left = 8
      }

      dropdownPosition.value = { top, left }
    }
  }, 0)
}

function closeDropdown(event) {
  openDropdown.value = null
}

// Navigation methods
function goToFileManager(domain) {
  openDropdown.value = null
  router.push({ name: 'domain-detail', params: { id: domain.id }, query: { tab: 'files' } })
}

function goToDatabases(domain) {
  openDropdown.value = null
  router.push({ name: 'domain-detail', params: { id: domain.id }, query: { tab: 'databases' } })
}

function goToEmail(domain) {
  openDropdown.value = null
  router.push({ name: 'domain-detail', params: { id: domain.id }, query: { tab: 'email' } })
}

function goToDns(domain) {
  openDropdown.value = null
  router.push({ name: 'domain-detail', params: { id: domain.id }, query: { tab: 'dns' } })
}

function goToSsl(domain) {
  openDropdown.value = null
  router.push({ name: 'domain-detail', params: { id: domain.id }, query: { tab: 'ssl' } })
}

function goToPhpSettings(domain) {
  openDropdown.value = null
  router.push({ name: 'domain-detail', params: { id: domain.id }, query: { tab: 'settings' } })
}

function goToLogs(domain) {
  openDropdown.value = null
  router.push({ name: 'domain-detail', params: { id: domain.id }, query: { tab: 'logs' } })
}

// Suspend methods
function confirmSuspend(domain) {
  openDropdown.value = null
  selectedDomain.value = domain
  showSuspendDialog.value = true
}

async function handleSuspend() {
  if (!selectedDomain.value) return

  isSuspending.value = true
  try {
    await domainsStore.suspendDomain(selectedDomain.value.id)
    appStore.showToast({
      type: 'success',
      message: t('domainDetail.suspendSuccess')
    })
    showSuspendDialog.value = false
    selectedDomain.value = null
  } catch (error) {
    appStore.showToast({
      type: 'error',
      message: t('domainDetail.suspendError')
    })
  } finally {
    isSuspending.value = false
  }
}

async function handleUnsuspend(domain) {
  openDropdown.value = null
  try {
    await domainsStore.unsuspendDomain(domain.id)
    appStore.showToast({
      type: 'success',
      message: t('domainDetail.unsuspendSuccess')
    })
  } catch (error) {
    appStore.showToast({
      type: 'error',
      message: t('domainDetail.unsuspendError')
    })
  }
}

// Bulk actions
async function bulkSuspend() {
  if (selectedDomains.value.length === 0) return

  try {
    for (const id of selectedDomains.value) {
      await domainsStore.suspendDomain(id)
    }
    appStore.showToast({
      type: 'success',
      message: t('websites.bulkSuspendSuccess', { count: selectedDomains.value.length })
    })
    selectedDomains.value = []
  } catch (error) {
    appStore.showToast({
      type: 'error',
      message: t('websites.bulkSuspendError')
    })
  }
}

function confirmBulkDelete() {
  showBulkDeleteDialog.value = true
}

async function handleBulkDelete() {
  if (selectedDomains.value.length === 0) return

  isDeleting.value = true
  try {
    for (const id of selectedDomains.value) {
      await domainsStore.deleteDomain(id)
    }
    appStore.showToast({
      type: 'success',
      message: t('websites.bulkDeleteSuccess', { count: selectedDomains.value.length })
    })
    showBulkDeleteDialog.value = false
    selectedDomains.value = []
  } catch (error) {
    appStore.showToast({
      type: 'error',
      message: t('websites.bulkDeleteError')
    })
  } finally {
    isDeleting.value = false
  }
}

function goToPage(page) {
  domainsStore.setPage(page)
  domainsStore.fetchDomains()
}

async function refreshData() {
  try {
    await domainsStore.fetchDomains()
  } catch (error) {
    appStore.showToast({
      type: 'error',
      message: t('websites.refreshError')
    })
  }
}

function viewDomain(domain) {
  router.push({ name: 'domain-detail', params: { id: domain.id } })
}

function editDomain(domain) {
  selectedDomain.value = domain
  showEditModal.value = true
}

function confirmDelete(domain) {
  openDropdown.value = null
  selectedDomain.value = domain
  showDeleteDialog.value = true
}

async function handleDelete() {
  if (!selectedDomain.value) return

  isDeleting.value = true
  try {
    await domainsStore.deleteDomain(selectedDomain.value.id)
    appStore.showToast({
      type: 'success',
      message: t('websites.deleteSuccess')
    })
    showDeleteDialog.value = false
    selectedDomain.value = null
  } catch (error) {
    appStore.showToast({
      type: 'error',
      message: t('websites.deleteError')
    })
  } finally {
    isDeleting.value = false
  }
}

function handleDomainCreated(domain) {
  appStore.showToast({
    type: 'success',
    message: t('websites.createSuccess')
  })
  domainsStore.fetchDomains()
}

function handleDomainUpdated(domain) {
  appStore.showToast({
    type: 'success',
    message: t('websites.updateSuccess')
  })
}

// Lifecycle
onMounted(() => {
  domainsStore.fetchDomains()
  document.addEventListener('click', closeDropdown)
})

onUnmounted(() => {
  document.removeEventListener('click', closeDropdown)
})
</script>
