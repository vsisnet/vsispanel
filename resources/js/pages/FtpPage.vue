<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('ftp.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $t('ftp.description') }}
      </p>
    </div>

    <!-- Service Status Card -->
    <VCard v-if="status" class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
          <div class="flex items-center gap-2">
            <span
              :class="[
                'w-3 h-3 rounded-full',
                status.running ? 'bg-green-500' : 'bg-red-500'
              ]"
            ></span>
            <span class="font-medium text-gray-900 dark:text-white">
              {{ status.running ? $t('common.running') : $t('common.stopped') }}
            </span>
          </div>
          <VBadge variant="secondary">{{ status.server_type }}</VBadge>
          <span class="text-gray-500 dark:text-gray-400">
            {{ status.users_count }} {{ $t('ftp.accounts') }}
          </span>
        </div>
        <div class="flex items-center gap-2">
          <VButton variant="secondary" size="sm" :loading="restarting" @click="restartService">
            {{ $t('ftp.restartService') }}
          </VButton>
          <VButton variant="secondary" size="sm" :loading="reloading" @click="reloadConfig">
            {{ $t('ftp.reloadConfig') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Statistics Cards -->
    <div v-if="statistics" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <VCard class="flex items-center gap-4">
        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
          <UsersIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
        </div>
        <div>
          <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ statistics.total_accounts }}</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('ftp.totalAccounts') }}</p>
        </div>
      </VCard>
      <VCard class="flex items-center gap-4">
        <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
          <CheckCircleIcon class="w-6 h-6 text-green-600 dark:text-green-400" />
        </div>
        <div>
          <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ statistics.active_accounts }}</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('ftp.activeAccounts') }}</p>
        </div>
      </VCard>
      <VCard class="flex items-center gap-4">
        <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg flex items-center justify-center">
          <PauseCircleIcon class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
        </div>
        <div>
          <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ statistics.suspended_accounts }}</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('ftp.suspendedAccounts') }}</p>
        </div>
      </VCard>
      <VCard class="flex items-center gap-4">
        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
          <ArrowUpIcon class="w-6 h-6 text-purple-600 dark:text-purple-400" />
        </div>
        <div>
          <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatBytes(statistics.total_uploaded) }}</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('ftp.totalUploaded') }}</p>
        </div>
      </VCard>
    </div>

    <!-- Filters -->
    <VCard class="mb-6">
      <div class="flex flex-col lg:flex-row lg:items-end gap-4">
        <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ftp.filterByDomain') }}
            </label>
            <select
              v-model="selectedDomain"
              @change="fetchAccounts"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            >
              <option value="">{{ $t('ftp.allDomains') }}</option>
              <option v-for="domain in domains" :key="domain.id" :value="domain.id">
                {{ domain.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ftp.filterByStatus') }}
            </label>
            <select
              v-model="selectedStatus"
              @change="fetchAccounts"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            >
              <option value="">{{ $t('ftp.allStatuses') }}</option>
              <option value="active">{{ $t('ftp.statusActive') }}</option>
              <option value="suspended">{{ $t('ftp.statusSuspended') }}</option>
              <option value="disabled">{{ $t('ftp.statusDisabled') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('common.search') }}
            </label>
            <VInput
              v-model="searchQuery"
              :placeholder="$t('ftp.searchPlaceholder')"
              @input="debouncedSearch"
            />
          </div>
        </div>
        <div class="flex items-center gap-2">
          <VButton variant="secondary" :icon="ArrowPathIcon" :loading="loading" @click="fetchAccounts">
            {{ $t('common.refresh') }}
          </VButton>
          <VButton variant="primary" :icon="PlusIcon" @click="openCreateModal">
            {{ $t('ftp.createAccount') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Bulk Actions Bar -->
    <div
      v-if="selectedAccounts.length > 0"
      class="mb-4 p-4 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg flex flex-wrap items-center justify-between gap-4"
    >
      <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
        {{ $t('ftp.selectedCount', { count: selectedAccounts.length }) }}
      </span>
      <div class="flex items-center gap-2">
        <VButton variant="secondary" size="sm" :loading="bulkProcessing" @click="bulkActivate">
          <PlayIcon class="w-4 h-4 mr-1" />
          {{ $t('ftp.activateSelected') }}
        </VButton>
        <VButton variant="secondary" size="sm" :loading="bulkProcessing" @click="bulkSuspend">
          <PauseIcon class="w-4 h-4 mr-1" />
          {{ $t('ftp.suspendSelected') }}
        </VButton>
        <VButton variant="danger" size="sm" :loading="bulkProcessing" @click="confirmBulkDelete">
          <TrashIcon class="w-4 h-4 mr-1" />
          {{ $t('ftp.deleteSelected') }}
        </VButton>
        <VButton variant="ghost" size="sm" @click="clearSelection">
          {{ $t('common.cancel') }}
        </VButton>
      </div>
    </div>

    <!-- FTP Accounts Table -->
    <VCard :padding="false">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-4 py-3 text-left w-10">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  :indeterminate="isPartiallySelected"
                  @change="toggleSelectAll"
                  class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                />
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('ftp.username') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('ftp.domain') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('ftp.homeDirectory') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('ftp.status') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('ftp.quota') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('ftp.lastLogin') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('common.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-if="loading">
              <td colspan="8" class="px-6 py-12 text-center">
                <VLoadingSkeleton class="h-24" />
              </td>
            </tr>
            <tr v-else-if="accounts.length === 0">
              <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                {{ $t('ftp.noAccounts') }}
              </td>
            </tr>
            <tr v-for="account in accounts" :key="account.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
              <td class="px-4 py-4">
                <input
                  type="checkbox"
                  :checked="selectedAccounts.includes(account.id)"
                  @change="toggleSelection(account.id)"
                  class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                />
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <UserIcon class="w-4 h-4 text-gray-400" />
                  <span class="font-medium text-gray-900 dark:text-white">{{ account.username }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400">
                {{ account.domain?.name || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="font-mono text-sm text-gray-500 dark:text-gray-400" :title="account.home_directory">
                  {{ truncatePath(account.home_directory) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <VBadge :variant="getStatusVariant(account.status)">
                  {{ $t(`ftp.status${capitalize(account.status)}`) }}
                </VBadge>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div v-if="account.quota_mb" class="w-24">
                  <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                    <span>{{ account.quota_usage?.used_mb || 0 }}MB</span>
                    <span>{{ account.quota_mb }}MB</span>
                  </div>
                  <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5">
                    <div
                      :class="[
                        'h-1.5 rounded-full transition-all',
                        getQuotaClass(account.quota_usage?.percent || 0)
                      ]"
                      :style="{ width: `${Math.min(account.quota_usage?.percent || 0, 100)}%` }"
                    ></div>
                  </div>
                </div>
                <span v-else class="text-sm text-gray-500 dark:text-gray-400">{{ $t('ftp.unlimited') }}</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ account.last_login_at ? formatDate(account.last_login_at) : $t('ftp.neverLogged') }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <div class="flex items-center justify-end gap-1">
                  <VButton variant="ghost" size="sm" :title="$t('common.edit')" @click="openEditModal(account)">
                    <PencilIcon class="w-4 h-4" />
                  </VButton>
                  <VButton variant="ghost" size="sm" :title="$t('ftp.changePassword')" @click="openPasswordModal(account)">
                    <KeyIcon class="w-4 h-4" />
                  </VButton>
                  <VButton
                    variant="ghost"
                    size="sm"
                    :title="account.status === 'active' ? $t('ftp.suspend') : $t('ftp.activate')"
                    @click="toggleStatus(account)"
                  >
                    <PauseIcon v-if="account.status === 'active'" class="w-4 h-4" />
                    <PlayIcon v-else class="w-4 h-4" />
                  </VButton>
                  <VButton variant="ghost" size="sm" class="text-red-500" :title="$t('common.delete')" @click="confirmDelete(account)">
                    <TrashIcon class="w-4 h-4" />
                  </VButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
        <p class="text-sm text-gray-500 dark:text-gray-400">
          {{ $t('common.page') }} {{ pagination.current_page }} / {{ pagination.last_page }}
        </p>
        <div class="flex gap-2">
          <VButton
            variant="secondary"
            size="sm"
            :disabled="pagination.current_page === 1"
            @click="changePage(pagination.current_page - 1)"
          >
            {{ $t('common.previous') }}
          </VButton>
          <VButton
            variant="secondary"
            size="sm"
            :disabled="pagination.current_page === pagination.last_page"
            @click="changePage(pagination.current_page + 1)"
          >
            {{ $t('common.next') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Create/Edit Modal -->
    <VModal v-model="showModal" :title="editingAccount ? $t('ftp.editAccount') : $t('ftp.createAccount')" size="lg">
      <form @submit.prevent="saveAccount">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ftp.domain') }} *
            </label>
            <select
              v-model="formData.domain_id"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              :disabled="editingAccount"
              required
            >
              <option value="">{{ $t('ftp.selectDomain') }}</option>
              <option v-for="domain in domains" :key="domain.id" :value="domain.id">
                {{ domain.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ftp.username') }} *
            </label>
            <VInput
              v-model="formData.username"
              :disabled="editingAccount"
              required
              pattern="[a-zA-Z][a-zA-Z0-9_]{2,31}"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('ftp.usernameHint') }}</p>
          </div>
          <div v-if="!editingAccount">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ftp.password') }} *
            </label>
            <div class="flex gap-2">
              <div class="relative flex-1">
                <VInput
                  v-model="formData.password"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  minlength="8"
                />
                <button
                  type="button"
                  class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  @click="showPassword = !showPassword"
                >
                  <EyeIcon v-if="!showPassword" class="w-5 h-5" />
                  <EyeSlashIcon v-else class="w-5 h-5" />
                </button>
              </div>
              <VButton type="button" variant="secondary" @click="generatePassword">
                {{ $t('common.generate') }}
              </VButton>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ftp.homeDirectory') }}
            </label>
            <div class="flex gap-2">
              <VInput v-model="formData.home_directory" :placeholder="$t('ftp.homeDirectoryPlaceholder')" class="flex-1" />
              <VButton
                type="button"
                variant="secondary"
                :disabled="!formData.domain_id"
                :title="!formData.domain_id ? $t('ftp.selectDomainFirst') : $t('ftp.browseFolder')"
                @click="openFolderBrowser"
              >
                {{ $t('common.browse') }}
              </VButton>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ftp.quotaMb') }}
            </label>
            <VInput v-model.number="formData.quota_mb" type="number" min="0" :placeholder="$t('ftp.quotaPlaceholder')" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ftp.maxConnections') }}
            </label>
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
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('ftp.description') }}
          </label>
          <textarea
            v-model="formData.description"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            rows="2"
          ></textarea>
        </div>

        <!-- Expiration -->
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('ftp.expiresAt') }}
          </label>
          <input
            v-model="formData.expires_at"
            type="date"
            class="w-full sm:w-48 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            :min="minExpiryDate"
          />
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
          <VButton type="button" variant="secondary" @click="closeModal">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="saving">
            {{ $t('common.save') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Change Password Modal -->
    <VModal v-model="showPasswordModal" :title="$t('ftp.changePassword')" size="sm">
      <form @submit.prevent="changePassword">
        <p class="text-gray-600 dark:text-gray-400 mb-4">{{ passwordAccount?.username }}</p>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ftp.newPassword') }} *
            </label>
            <VInput v-model="newPassword" type="password" required minlength="8" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ftp.confirmPassword') }} *
            </label>
            <VInput v-model="confirmPassword" type="password" required />
            <p v-if="passwordMismatch" class="mt-1 text-sm text-red-500">
              {{ $t('ftp.passwordMismatch') }}
            </p>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <VButton type="button" variant="secondary" @click="closePasswordModal">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="changingPassword" :disabled="passwordMismatch">
            {{ $t('ftp.changePassword') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Delete Confirmation -->
    <VConfirmDialog
      v-model="showDeleteModal"
      :title="$t('ftp.confirmDelete')"
      :message="$t('ftp.deleteWarning', { username: deletingAccount?.username })"
      :loading="deleting"
      type="danger"
      @confirm="deleteAccount"
    />

    <!-- Bulk Delete Confirmation -->
    <VConfirmDialog
      v-model="showBulkDeleteModal"
      :title="$t('ftp.bulkDeleteTitle')"
      :message="$t('ftp.bulkDeleteMessage', { count: selectedAccounts.length })"
      :loading="bulkProcessing"
      type="danger"
      @confirm="bulkDelete"
    />

    <!-- Folder Browser Modal -->
    <VModal v-model="showFolderBrowser" :title="$t('ftp.selectFolder')" size="lg">
      <div class="space-y-4">
        <!-- Current Path -->
        <div class="flex items-center gap-2 p-3 bg-gray-100 dark:bg-gray-800 rounded-lg">
          <FolderIcon class="w-5 h-5 text-gray-500" />
          <span class="font-mono text-sm text-gray-700 dark:text-gray-300 flex-1">
            {{ currentBrowsePath || '/' }}
          </span>
          <VButton
            v-if="currentBrowsePath"
            variant="ghost"
            size="sm"
            @click="navigateToParent"
          >
            <ArrowUpIcon class="w-4 h-4" />
            {{ $t('ftp.parentFolder') }}
          </VButton>
        </div>

        <!-- Folder List -->
        <div class="border border-gray-200 dark:border-gray-700 rounded-lg max-h-80 overflow-y-auto">
          <div v-if="loadingFolders" class="p-8 text-center">
            <VLoadingSkeleton class="h-32" />
          </div>
          <div v-else-if="folders.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
            {{ $t('ftp.noFolders') }}
          </div>
          <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
            <div
              v-for="folder in folders"
              :key="folder.name"
              class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors"
              @click="navigateToFolder(folder.name)"
              @dblclick="selectFolder(folder.path)"
            >
              <FolderIcon class="w-5 h-5 text-yellow-500" />
              <span class="flex-1 text-gray-900 dark:text-white">{{ folder.name }}</span>
              <VButton
                variant="ghost"
                size="sm"
                @click.stop="selectFolder(folder.path)"
              >
                {{ $t('common.select') }}
              </VButton>
            </div>
          </div>
        </div>

        <p class="text-sm text-gray-500 dark:text-gray-400">
          {{ $t('ftp.folderBrowserHint') }}
        </p>
      </div>

      <div class="flex justify-between gap-3 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
        <VButton type="button" variant="secondary" @click="selectFolder(currentBrowsePath || '/')">
          {{ $t('ftp.useCurrentFolder') }}
        </VButton>
        <VButton type="button" variant="secondary" @click="showFolderBrowser = false">
          {{ $t('common.cancel') }}
        </VButton>
      </div>
    </VModal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VInput from '@/components/ui/VInput.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VModal from '@/components/ui/VModal.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import {
  PlusIcon,
  ArrowPathIcon,
  UserIcon,
  UsersIcon,
  CheckCircleIcon,
  PauseCircleIcon,
  ArrowUpIcon,
  PencilIcon,
  KeyIcon,
  PauseIcon,
  PlayIcon,
  TrashIcon,
  EyeIcon,
  EyeSlashIcon,
  FolderIcon
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

// State
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)
const restarting = ref(false)
const reloading = ref(false)
const changingPassword = ref(false)

const accounts = ref([])
const domains = ref([])
const status = ref(null)
const statistics = ref(null)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})

// Filters
const selectedDomain = ref('')
const selectedStatus = ref('')
const searchQuery = ref('')

// Modals
const showModal = ref(false)
const showPasswordModal = ref(false)
const showDeleteModal = ref(false)
const showBulkDeleteModal = ref(false)
const showFolderBrowser = ref(false)
const editingAccount = ref(null)
const deletingAccount = ref(null)
const passwordAccount = ref(null)

// Selection
const selectedAccounts = ref([])
const bulkProcessing = ref(false)

// Password visibility
const showPassword = ref(false)

// Folder browser
const folders = ref([])
const currentBrowsePath = ref('')
const loadingFolders = ref(false)

// Form data
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

// Password change
const newPassword = ref('')
const confirmPassword = ref('')

const passwordMismatch = computed(() => {
  return confirmPassword.value && newPassword.value !== confirmPassword.value
})

const minExpiryDate = computed(() => {
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  return tomorrow.toISOString().split('T')[0]
})

// Selection computed
const isAllSelected = computed(() => {
  return accounts.value.length > 0 && selectedAccounts.value.length === accounts.value.length
})

const isPartiallySelected = computed(() => {
  return selectedAccounts.value.length > 0 && selectedAccounts.value.length < accounts.value.length
})

// Methods
const fetchAccounts = async () => {
  loading.value = true
  try {
    const params = {
      page: pagination.value.current_page,
      per_page: pagination.value.per_page
    }
    if (selectedDomain.value) params.domain_id = selectedDomain.value
    if (selectedStatus.value) params.status = selectedStatus.value
    if (searchQuery.value) params.search = searchQuery.value

    const response = await api.get('/ftp/accounts', { params })
    accounts.value = response.data.data
    if (response.data.meta) {
      pagination.value = {
        current_page: response.data.meta.current_page,
        last_page: response.data.meta.last_page,
        per_page: response.data.meta.per_page,
        total: response.data.meta.total
      }
    }
  } catch (error) {
    console.error('Failed to fetch FTP accounts:', error)
  } finally {
    loading.value = false
  }
}

const fetchDomains = async () => {
  try {
    const response = await api.get('/domains')
    domains.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch domains:', error)
  }
}

const fetchStatus = async () => {
  try {
    const response = await api.get('/ftp/status')
    status.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch FTP status:', error)
  }
}

const fetchStatistics = async () => {
  try {
    const response = await api.get('/ftp/statistics')
    statistics.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch FTP statistics:', error)
  }
}

// Simple debounce implementation
let searchTimeout = null
const debouncedSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    pagination.value.current_page = 1
    fetchAccounts()
  }, 300)
}

const changePage = (page) => {
  pagination.value.current_page = page
  fetchAccounts()
}

const openCreateModal = () => {
  editingAccount.value = null
  Object.assign(formData, {
    domain_id: selectedDomain.value || '',
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
  showModal.value = true
}

const openEditModal = (account) => {
  editingAccount.value = account
  Object.assign(formData, {
    domain_id: account.domain_id,
    username: account.username,
    home_directory: account.home_directory,
    quota_mb: account.quota_mb,
    max_connections: account.max_connections,
    allow_upload: account.permissions?.upload ?? true,
    allow_download: account.permissions?.download ?? true,
    allow_mkdir: account.permissions?.mkdir ?? true,
    allow_delete: account.permissions?.delete ?? true,
    allow_rename: account.permissions?.rename ?? true,
    description: account.description,
    expires_at: account.expires_at ? account.expires_at.split('T')[0] : null
  })
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  editingAccount.value = null
}

const saveAccount = async () => {
  saving.value = true
  try {
    if (editingAccount.value) {
      await api.put(`/ftp/accounts/${editingAccount.value.id}`, formData)
      appStore.showToast({ type: 'success', message: t('ftp.updateSuccess') })
    } else {
      await api.post('/ftp/accounts', formData)
      appStore.showToast({ type: 'success', message: t('ftp.createSuccess') })
    }
    closeModal()
    fetchAccounts()
    fetchStatistics()
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  } finally {
    saving.value = false
  }
}

const openPasswordModal = (account) => {
  passwordAccount.value = account
  newPassword.value = ''
  confirmPassword.value = ''
  showPasswordModal.value = true
}

const closePasswordModal = () => {
  showPasswordModal.value = false
  passwordAccount.value = null
}

const changePassword = async () => {
  if (passwordMismatch.value) return

  changingPassword.value = true
  try {
    await api.post(`/ftp/accounts/${passwordAccount.value.id}/change-password`, {
      password: newPassword.value
    })
    appStore.showToast({ type: 'success', message: t('ftp.passwordChanged') })
    closePasswordModal()
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  } finally {
    changingPassword.value = false
  }
}

const toggleStatus = async (account) => {
  try {
    await api.post(`/ftp/accounts/${account.id}/toggle-status`)
    appStore.showToast({ type: 'success', message: t('ftp.statusToggled') })
    fetchAccounts()
    fetchStatistics()
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  }
}

const confirmDelete = (account) => {
  deletingAccount.value = account
  showDeleteModal.value = true
}

const deleteAccount = async () => {
  deleting.value = true
  try {
    await api.delete(`/ftp/accounts/${deletingAccount.value.id}`)
    appStore.showToast({ type: 'success', message: t('ftp.deleteSuccess') })
    showDeleteModal.value = false
    deletingAccount.value = null
    fetchAccounts()
    fetchStatistics()
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  } finally {
    deleting.value = false
  }
}

// Selection methods
const toggleSelection = (accountId) => {
  const index = selectedAccounts.value.indexOf(accountId)
  if (index === -1) {
    selectedAccounts.value.push(accountId)
  } else {
    selectedAccounts.value.splice(index, 1)
  }
}

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedAccounts.value = []
  } else {
    selectedAccounts.value = accounts.value.map(a => a.id)
  }
}

const clearSelection = () => {
  selectedAccounts.value = []
}

// Bulk operations
const bulkActivate = async () => {
  if (selectedAccounts.value.length === 0) return

  bulkProcessing.value = true
  try {
    await api.post('/ftp/accounts/bulk-activate', {
      account_ids: selectedAccounts.value
    })
    appStore.showToast({ type: 'success', message: t('ftp.bulkActivateSuccess', { count: selectedAccounts.value.length }) })
    clearSelection()
    fetchAccounts()
    fetchStatistics()
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  } finally {
    bulkProcessing.value = false
  }
}

const bulkSuspend = async () => {
  if (selectedAccounts.value.length === 0) return

  bulkProcessing.value = true
  try {
    await api.post('/ftp/accounts/bulk-suspend', {
      account_ids: selectedAccounts.value
    })
    appStore.showToast({ type: 'success', message: t('ftp.bulkSuspendSuccess', { count: selectedAccounts.value.length }) })
    clearSelection()
    fetchAccounts()
    fetchStatistics()
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  } finally {
    bulkProcessing.value = false
  }
}

const confirmBulkDelete = () => {
  if (selectedAccounts.value.length === 0) return
  showBulkDeleteModal.value = true
}

const bulkDelete = async () => {
  bulkProcessing.value = true
  try {
    await api.post('/ftp/accounts/bulk-delete', {
      account_ids: selectedAccounts.value
    })
    appStore.showToast({ type: 'success', message: t('ftp.bulkDeleteSuccess', { count: selectedAccounts.value.length }) })
    showBulkDeleteModal.value = false
    clearSelection()
    fetchAccounts()
    fetchStatistics()
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  } finally {
    bulkProcessing.value = false
  }
}

const restartService = async () => {
  restarting.value = true
  try {
    await api.post('/ftp/restart')
    appStore.showToast({ type: 'success', message: t('ftp.restartSuccess') })
    fetchStatus()
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  } finally {
    restarting.value = false
  }
}

const reloadConfig = async () => {
  reloading.value = true
  try {
    await api.post('/ftp/reload')
    appStore.showToast({ type: 'success', message: t('ftp.reloadSuccess') })
    fetchStatus()
  } catch (error) {
    appStore.showToast({ type: 'error', message: error.response?.data?.error?.message || t('common.error') })
  } finally {
    reloading.value = false
  }
}

// Helpers
const getStatusVariant = (status) => {
  const variants = {
    active: 'success',
    suspended: 'warning',
    disabled: 'danger'
  }
  return variants[status] || 'secondary'
}

const getQuotaClass = (percent) => {
  if (percent >= 90) return 'bg-red-500'
  if (percent >= 70) return 'bg-yellow-500'
  return 'bg-green-500'
}

const capitalize = (str) => str ? str.charAt(0).toUpperCase() + str.slice(1) : ''

const truncatePath = (path) => {
  if (!path) return '-'
  if (path.length <= 30) return path
  return '...' + path.slice(-27)
}

const formatBytes = (bytes) => {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(1024))
  return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i]
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  return date.toLocaleDateString() + ' ' + date.toLocaleTimeString()
}

// Password generation
const generatePassword = () => {
  const length = 16
  const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'
  let password = ''
  const array = new Uint32Array(length)
  crypto.getRandomValues(array)
  for (let i = 0; i < length; i++) {
    password += charset[array[i] % charset.length]
  }
  formData.password = password
  showPassword.value = true // Show password after generation
}

// Folder browser functions
const openFolderBrowser = async () => {
  if (!formData.domain_id) return
  currentBrowsePath.value = ''
  showFolderBrowser.value = true
  await fetchFolders('')
}

const fetchFolders = async (path) => {
  loadingFolders.value = true
  folders.value = []
  try {
    const response = await api.get(`/domains/${formData.domain_id}/files`, {
      params: { path: path || '/' }
    })
    // Filter only directories
    const items = response.data.data || []
    folders.value = items
      .filter(item => item.type === 'directory')
      .map(item => ({
        name: item.name,
        path: path ? `${path}/${item.name}` : item.name
      }))
  } catch (error) {
    console.error('Failed to fetch folders:', error)
    appStore.showToast({ type: 'error', message: t('ftp.fetchFoldersError') })
  } finally {
    loadingFolders.value = false
  }
}

const navigateToFolder = async (folderName) => {
  const newPath = currentBrowsePath.value ? `${currentBrowsePath.value}/${folderName}` : folderName
  currentBrowsePath.value = newPath
  await fetchFolders(newPath)
}

const navigateToParent = async () => {
  if (!currentBrowsePath.value) return
  const parts = currentBrowsePath.value.split('/')
  parts.pop()
  currentBrowsePath.value = parts.join('/')
  await fetchFolders(currentBrowsePath.value)
}

const selectFolder = (path) => {
  formData.home_directory = path || ''
  showFolderBrowser.value = false
}

// Lifecycle
onMounted(() => {
  fetchDomains()
  fetchAccounts()
  fetchStatus()
  fetchStatistics()
})
</script>
