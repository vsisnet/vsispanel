<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('nav.databases') }}
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $t('databases.description') }}
      </p>
    </div>

    <!-- Quick Actions Bar -->
    <VCard class="mb-6">
      <div class="flex flex-wrap items-center gap-2">
        <VButton variant="primary" :icon="PlusIcon" @click="openCreateModal">
          {{ $t('databases.createDatabase') }}
        </VButton>
        <VButton variant="secondary" :icon="KeyIcon" @click="openRootPasswordModal">
          {{ $t('databases.rootPassword') }}
        </VButton>
        <VButton
          variant="secondary"
          :icon="ServerStackIcon"
          @click="openPhpMyAdmin"
          :title="$t('databases.openPhpMyAdmin')"
        >
          phpMyAdmin
        </VButton>
      </div>
    </VCard>

    <!-- Search and Filter Bar -->
    <VCard class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <VInput
          v-model="search"
          :placeholder="$t('databases.searchPlaceholder')"
          class="w-64"
          @keyup.enter="fetchDatabases"
        />
        <select
          v-model="filterDomain"
          class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
          @change="fetchDatabases"
        >
          <option value="">{{ $t('databases.allDomains') }}</option>
          <option v-for="domain in domains" :key="domain.id" :value="domain.id">
            {{ domain.name }}
          </option>
        </select>
      </div>

      <!-- Bulk Actions -->
      <div v-if="selectedDatabases.length > 0" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex items-center gap-4">
        <span class="text-sm text-gray-600 dark:text-gray-400">
          {{ $t('databases.selectedCount', { count: selectedDatabases.length }) }}
        </span>
        <VButton
          variant="secondary"
          size="sm"
          :icon="ArrowDownTrayIcon"
          :loading="bulkBackingUp"
          @click="bulkBackup"
        >
          {{ $t('databases.backupSelected') }}
        </VButton>
        <VButton
          variant="danger"
          size="sm"
          :icon="TrashIcon"
          @click="confirmBulkDelete"
        >
          {{ $t('databases.deleteSelected') }}
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

    <!-- Loading State -->
    <VLoadingSkeleton v-if="loading" class="h-96" />

    <!-- Empty State -->
    <VCard v-else-if="databases.length === 0" class="text-center py-12">
      <CircleStackIcon class="w-16 h-16 mx-auto text-gray-400 mb-4" />
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
        {{ $t('databases.noDatabases') }}
      </h2>
      <p class="text-gray-500 dark:text-gray-400 mb-4">
        {{ $t('databases.noDatabasesDesc') }}
      </p>
      <VButton variant="primary" :icon="PlusIcon" @click="openCreateModal">
        {{ $t('databases.createFirst') }}
      </VButton>
    </VCard>

    <!-- Databases List -->
    <VCard v-else :padding="false">
      <div class="overflow-x-auto">
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
                {{ $t('databases.name') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('databases.users') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('databases.size') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('databases.backup') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('databases.status') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('common.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr
              v-for="database in databases"
              :key="database.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-800"
              :class="{ 'bg-primary-50 dark:bg-primary-900/20': isSelected(database.id) }"
            >
              <td class="px-4 py-4">
                <input
                  type="checkbox"
                  :checked="isSelected(database.id)"
                  class="w-4 h-4 text-primary-600 rounded border-gray-300 focus:ring-primary-500"
                  @change="toggleSelect(database.id)"
                >
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <CircleStackIcon class="w-5 h-5 text-primary-500 mr-3" />
                  <div>
                    <p class="font-medium text-gray-900 dark:text-white">{{ database.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ database.charset }} / {{ database.collation }}</p>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex flex-wrap gap-1">
                  <VBadge
                    v-for="user in (database.users || []).slice(0, 2)"
                    :key="user.id"
                    variant="secondary"
                    size="sm"
                  >
                    {{ user.original_username }}
                  </VBadge>
                  <VBadge v-if="(database.users || []).length > 2" variant="secondary" size="sm">
                    +{{ (database.users || []).length - 2 }}
                  </VBadge>
                  <span v-if="!(database.users || []).length" class="text-sm text-gray-400">-</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ database.size_formatted || '0 B' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center space-x-2">
                  <span class="text-sm text-orange-500">{{ $t('databases.noBackup') }}</span>
                  <button
                    @click="openImportModal(database)"
                    class="text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400"
                  >
                    {{ $t('databases.import') }}
                  </button>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <VBadge :variant="database.status === 'active' ? 'success' : 'warning'">
                  {{ database.status === 'active' ? $t('common.active') : $t('common.suspended') }}
                </VBadge>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <div class="flex items-center justify-end space-x-1">
                  <VButton
                    variant="ghost"
                    size="sm"
                    @click="openPhpMyAdminDb(database)"
                    :title="'phpMyAdmin'"
                    class="text-primary-600 hover:text-primary-800"
                  >
                    <span class="text-xs">phpMyAdmin</span>
                  </VButton>
                  <VButton
                    variant="ghost"
                    size="sm"
                    :icon="UserPlusIcon"
                    @click="openAddUserModal(database)"
                    :title="$t('databases.addUser')"
                  />
                  <VButton
                    variant="ghost"
                    size="sm"
                    :icon="ArrowDownTrayIcon"
                    @click="backupDatabase(database)"
                    :loading="backingUp === database.id"
                    :title="$t('databases.backup')"
                  />
                  <VButton
                    variant="ghost"
                    size="sm"
                    :icon="TrashIcon"
                    @click="confirmDelete(database)"
                    :title="$t('common.delete')"
                    class="text-red-600 hover:text-red-800"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </VCard>

    <!-- Database Users Section -->
    <div class="mt-8">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">
          {{ $t('databases.databaseUsers') }}
        </h2>
        <VButton variant="primary" size="sm" :icon="PlusIcon" @click="openCreateUserModal">
          {{ $t('databases.createUser') }}
        </VButton>
      </div>

      <VLoadingSkeleton v-if="loadingUsers" class="h-48" />

      <VCard v-else-if="databaseUsers.length === 0" class="text-center py-8">
        <UserIcon class="w-12 h-12 mx-auto text-gray-400 mb-3" />
        <p class="text-gray-500 dark:text-gray-400">{{ $t('databases.noUsers') }}</p>
      </VCard>

      <VCard v-else :padding="false">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                  {{ $t('databases.username') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                  {{ $t('databases.host') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                  {{ $t('databases.accessTo') }}
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                  {{ $t('common.actions') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="dbUser in databaseUsers" :key="dbUser.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <UserIcon class="w-5 h-5 text-blue-500 mr-3" />
                    <span class="font-medium text-gray-900 dark:text-white">{{ dbUser.username }}</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 font-mono">
                  {{ dbUser.host }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex flex-wrap gap-1">
                    <VBadge v-for="db in (dbUser.databases || [])" :key="db.id" variant="secondary" size="sm">
                      {{ db.name }}
                    </VBadge>
                    <span v-if="!dbUser.databases?.length" class="text-sm text-gray-400">-</span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="flex items-center justify-end space-x-2">
                    <VButton
                      variant="secondary"
                      size="sm"
                      :icon="KeyIcon"
                      @click="openChangePasswordModal(dbUser)"
                      :title="$t('databases.changePassword')"
                    />
                    <VButton
                      variant="danger"
                      size="sm"
                      :icon="TrashIcon"
                      @click="confirmDeleteUser(dbUser)"
                      :title="$t('common.delete')"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </VCard>
    </div>

    <!-- Create Database Modal -->
    <VModal v-model="showCreateModal" :title="$t('databases.createDatabase')">
      <form @submit.prevent="createDatabase">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.name') }}
            </label>
            <VInput
              v-model="createForm.name"
              placeholder="mydb"
              required
            />
            <p class="mt-1 text-xs text-gray-500">{{ $t('databases.nameHint') }}</p>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('databases.charset') }}
              </label>
              <select
                v-model="createForm.charset"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
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
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              >
                <option value="utf8mb4_unicode_ci">utf8mb4_unicode_ci</option>
                <option value="utf8mb4_general_ci">utf8mb4_general_ci</option>
                <option value="utf8_unicode_ci">utf8_unicode_ci</option>
              </select>
            </div>
          </div>

          <!-- Create User Checkbox -->
          <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <label class="flex items-center">
              <input
                type="checkbox"
                v-model="createForm.create_user"
                class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
              />
              <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('databases.createUserWithDatabase') }}</span>
            </label>
          </div>

          <!-- User Fields (shown when create_user is checked) -->
          <template v-if="createForm.create_user">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('databases.username') }}
              </label>
              <VInput
                v-model="createForm.username"
                :placeholder="createForm.name || 'dbuser'"
              />
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

    <!-- Root Password Modal -->
    <VModal v-model="showRootPasswordModal" :title="$t('databases.rootPassword')">
      <form @submit.prevent="changeRootPassword">
        <div class="space-y-4">
          <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
            <p class="text-sm text-yellow-800 dark:text-yellow-200">
              {{ $t('databases.rootPasswordWarning') }}
            </p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.newRootPassword') }}
            </label>
            <div class="flex gap-2">
              <VInput
                v-model="rootPasswordForm.password"
                :type="showRootPassword ? 'text' : 'password'"
                required
                class="flex-1"
              />
              <VButton type="button" variant="secondary" @click="showRootPassword = !showRootPassword">
                <EyeIcon v-if="!showRootPassword" class="w-5 h-5" />
                <EyeSlashIcon v-else class="w-5 h-5" />
              </VButton>
              <VButton type="button" variant="secondary" @click="generateRootPassword">
                {{ $t('common.generate') }}
              </VButton>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.confirmPassword') }}
            </label>
            <VInput
              v-model="rootPasswordForm.password_confirmation"
              type="password"
              required
            />
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showRootPasswordModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="danger" :loading="changingRootPassword">
            {{ $t('databases.changeRootPassword') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Create Database User Modal -->
    <VModal v-model="showCreateUserModal" :title="$t('databases.createUser')">
      <form @submit.prevent="createUser">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.username') }}
            </label>
            <VInput
              v-model="createUserForm.username"
              placeholder="dbuser"
              required
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.password') }}
            </label>
            <div class="flex gap-2">
              <VInput
                v-model="createUserForm.password"
                :type="showUserPassword ? 'text' : 'password'"
                required
                class="flex-1"
              />
              <VButton type="button" variant="secondary" @click="showUserPassword = !showUserPassword">
                <EyeIcon v-if="!showUserPassword" class="w-5 h-5" />
                <EyeSlashIcon v-else class="w-5 h-5" />
              </VButton>
              <VButton type="button" variant="secondary" @click="generateUserPassword">
                {{ $t('common.generate') }}
              </VButton>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.host') }}
            </label>
            <VInput
              v-model="createUserForm.host"
              placeholder="localhost"
            />
            <p class="mt-1 text-xs text-gray-500">{{ $t('databases.hostHint') }}</p>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showCreateUserModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="creatingUser">
            {{ $t('common.create') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Add User to Database Modal -->
    <VModal v-model="showAddUserModal" :title="$t('databases.addUser')">
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
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              required
            >
              <option value="">{{ $t('databases.selectUserPlaceholder') }}</option>
              <option v-for="user in databaseUsers" :key="user.id" :value="user.id">
                {{ user.username }}@{{ user.host }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.privileges') }}
            </label>
            <div class="grid grid-cols-2 gap-2">
              <label v-for="priv in availablePrivileges" :key="priv" class="flex items-center">
                <input
                  type="checkbox"
                  v-model="grantForm.privileges"
                  :value="priv"
                  class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                />
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ priv }}</span>
              </label>
            </div>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showAddUserModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="granting">
            {{ $t('databases.grantAccess') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Change Password Modal -->
    <VModal v-model="showPasswordModal" :title="$t('databases.changePassword')">
      <form @submit.prevent="changeUserPassword">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.username') }}
            </label>
            <VInput :model-value="selectedUser?.username" disabled />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('databases.newPassword') }}
            </label>
            <div class="flex gap-2">
              <VInput
                v-model="passwordForm.password"
                :type="showChangePassword ? 'text' : 'password'"
                required
                class="flex-1"
              />
              <VButton type="button" variant="secondary" @click="showChangePassword = !showChangePassword">
                <EyeIcon v-if="!showChangePassword" class="w-5 h-5" />
                <EyeSlashIcon v-else class="w-5 h-5" />
              </VButton>
              <VButton type="button" variant="secondary" @click="generateChangePassword">
                {{ $t('common.generate') }}
              </VButton>
            </div>
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
    <!-- Import Modal -->
    <VModal v-model="showImportModal" :title="$t('databases.importSql')">
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
                accept=".sql,.gz"
                class="hidden"
                @change="handleImportFileSelect"
              />
            </div>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showImportModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="importing" :disabled="!importFile">
            {{ $t('databases.import') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Delete Confirmations -->
    <VConfirmDialog
      v-model="showDeleteConfirm"
      :title="$t('databases.deleteDatabase')"
      :message="$t('databases.deleteConfirm', { name: deletingDatabase?.name })"
      :loading="deleting"
      @confirm="deleteDatabase"
    />

    <VConfirmDialog
      v-model="showDeleteUserConfirm"
      :title="$t('databases.deleteUser')"
      :message="$t('databases.deleteUserConfirm', { username: deletingUser?.username })"
      :loading="deletingUserLoading"
      @confirm="deleteUser"
    />

    <VConfirmDialog
      v-model="showBulkDeleteConfirm"
      :title="$t('databases.bulkDeleteTitle')"
      :message="$t('databases.bulkDeleteConfirm', { count: selectedDatabases.length })"
      :loading="bulkDeleting"
      type="danger"
      @confirm="handleBulkDelete"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
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
  CircleStackIcon,
  PlusIcon,
  TrashIcon,
  KeyIcon,
  UserIcon,
  UserPlusIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  ArrowPathIcon,
  ServerStackIcon,
  EyeIcon,
  EyeSlashIcon
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

// State
const databases = ref([])
const databaseUsers = ref([])
const domains = ref([])
const loading = ref(true)
const loadingUsers = ref(true)
const search = ref('')
const filterDomain = ref('')
const syncing = ref(false)

// Bulk selection state
const selectedDatabases = ref([])
const bulkBackingUp = ref(false)
const showBulkDeleteConfirm = ref(false)
const bulkDeleting = ref(false)

// Computed for bulk selection
const isAllSelected = computed(() => {
  return databases.value.length > 0 && selectedDatabases.value.length === databases.value.length
})

const isPartiallySelected = computed(() => {
  return selectedDatabases.value.length > 0 && selectedDatabases.value.length < databases.value.length
})

// Modal states
const showCreateModal = ref(false)
const showCreateUserModal = ref(false)
const showAddUserModal = ref(false)
const showPasswordModal = ref(false)
const showImportModal = ref(false)
const showDeleteConfirm = ref(false)
const showDeleteUserConfirm = ref(false)
const showRootPasswordModal = ref(false)

// Password visibility
const showCreatePassword = ref(false)
const showUserPassword = ref(false)
const showChangePassword = ref(false)
const showRootPassword = ref(false)

// Form states
const creating = ref(false)
const creatingUser = ref(false)
const granting = ref(false)
const changingPassword = ref(false)
const changingRootPassword = ref(false)
const deleting = ref(false)
const deletingUserLoading = ref(false)
const backingUp = ref(null)
const importing = ref(false)

// Form data
const createForm = ref({
  name: '',
  domain_id: '',
  charset: 'utf8mb4',
  collation: 'utf8mb4_unicode_ci',
  create_user: true,
  username: '',
  password: ''
})

const createUserForm = ref({
  username: '',
  password: '',
  host: 'localhost'
})

const grantForm = ref({
  database_user_id: '',
  privileges: ['SELECT', 'INSERT', 'UPDATE', 'DELETE']
})

const passwordForm = ref({
  password: ''
})

const rootPasswordForm = ref({
  password: '',
  password_confirmation: ''
})

// Selected items
const selectedDatabase = ref(null)
const selectedUser = ref(null)
const deletingDatabase = ref(null)
const deletingUser = ref(null)
const importFile = ref(null)

// Available privileges
const availablePrivileges = ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'DROP', 'INDEX', 'ALTER']

// Watch for name changes to auto-fill username
watch(() => createForm.value.name, (newName) => {
  if (createForm.value.create_user && !createForm.value.username) {
    createForm.value.username = newName
  }
})

// Methods
function generatePassword() {
  createForm.value.password = generateRandomPassword()
}

function generateUserPassword() {
  createUserForm.value.password = generateRandomPassword()
}

function generateChangePassword() {
  passwordForm.value.password = generateRandomPassword()
}

function generateRootPassword() {
  rootPasswordForm.value.password = generateRandomPassword()
}

function generateRandomPassword() {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'
  let password = ''
  for (let i = 0; i < 16; i++) {
    password += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  return password
}

function formatBytes(bytes) {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

async function fetchDatabases() {
  loading.value = true
  try {
    const params = {}
    if (filterDomain.value) params.domain_id = filterDomain.value
    if (search.value) params.search = search.value

    const response = await api.get('/databases', { params })
    databases.value = response.data.data || []
  } catch (err) {
    console.error('Failed to fetch databases:', err)
  } finally {
    loading.value = false
  }
}

async function fetchDatabaseUsers() {
  loadingUsers.value = true
  try {
    const response = await api.get('/database-users')
    databaseUsers.value = response.data.data || []
  } catch (err) {
    console.error('Failed to fetch database users:', err)
  } finally {
    loadingUsers.value = false
  }
}

async function fetchDomains() {
  try {
    const response = await api.get('/domains')
    domains.value = response.data.data || []
  } catch (err) {
    console.error('Failed to fetch domains:', err)
  }
}

function openCreateModal() {
  createForm.value = {
    name: '',
    domain_id: '',
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
    await api.post('/databases', createForm.value)
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

function openRootPasswordModal() {
  rootPasswordForm.value = {
    password: '',
    password_confirmation: ''
  }
  showRootPassword.value = false
  showRootPasswordModal.value = true
}

async function changeRootPassword() {
  if (rootPasswordForm.value.password !== rootPasswordForm.value.password_confirmation) {
    appStore.showToast({
      type: 'error',
      message: t('databases.passwordMismatch')
    })
    return
  }

  changingRootPassword.value = true
  try {
    await api.post('/databases/root-password', {
      password: rootPasswordForm.value.password
    })
    showRootPasswordModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('databases.rootPasswordChanged')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.rootPasswordError')
    })
  } finally {
    changingRootPassword.value = false
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

async function openPhpMyAdminDb(database) {
  try {
    // Try to get SSO URL for auto-login
    const response = await api.get(`/databases/${database.id}/phpmyadmin-sso`)
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
  window.open(`${getPhpMyAdminBaseUrl()}?db=${encodeURIComponent(database.name)}`, '_blank')
}

async function syncDatabasesFromServer() {
  syncing.value = true
  try {
    // First get server databases
    const serverDbsResponse = await api.get('/databases/server-databases')
    const serverDatabases = serverDbsResponse.data.data || []

    // Filter databases not yet in panel
    const databasesToSync = serverDatabases
      .filter(db => !db.exists_in_panel)
      .map(db => db.name)

    if (databasesToSync.length === 0) {
      appStore.showToast({
        type: 'info',
        message: t('databases.noServerDatabases')
      })
      return
    }

    // Sync them
    const response = await api.post('/databases/sync-from-server', {
      databases: databasesToSync
    })
    appStore.showToast({
      type: 'success',
      message: t('databases.syncSuccess', { count: response.data.data?.synced?.length || 0 })
    })
    await fetchDatabases()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.syncError')
    })
  } finally {
    syncing.value = false
  }
}

function openCreateUserModal() {
  createUserForm.value = {
    username: '',
    password: generateRandomPassword(),
    host: 'localhost'
  }
  showUserPassword.value = false
  showCreateUserModal.value = true
}

async function createUser() {
  creatingUser.value = true
  try {
    await api.post('/database-users', createUserForm.value)
    showCreateUserModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('databases.userCreated')
    })
    await fetchDatabaseUsers()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.userCreateError')
    })
  } finally {
    creatingUser.value = false
  }
}

function openAddUserModal(database) {
  selectedDatabase.value = database
  grantForm.value = {
    database_user_id: '',
    privileges: ['SELECT', 'INSERT', 'UPDATE', 'DELETE']
  }
  showAddUserModal.value = true
}

async function grantAccess() {
  if (!selectedDatabase.value || !grantForm.value.database_user_id) return
  granting.value = true
  try {
    await api.post(`/database-users/${grantForm.value.database_user_id}/grant`, {
      database_id: selectedDatabase.value.id,
      privileges: grantForm.value.privileges
    })
    showAddUserModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('databases.accessGranted')
    })
    await Promise.all([fetchDatabases(), fetchDatabaseUsers()])
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.grantError')
    })
  } finally {
    granting.value = false
  }
}

function openChangePasswordModal(user) {
  selectedUser.value = user
  passwordForm.value = { password: '' }
  showChangePassword.value = false
  showPasswordModal.value = true
}

async function changeUserPassword() {
  if (!selectedUser.value) return
  changingPassword.value = true
  try {
    await api.put(`/database-users/${selectedUser.value.id}/password`, passwordForm.value)
    showPasswordModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('databases.passwordChanged')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.passwordError')
    })
  } finally {
    changingPassword.value = false
  }
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

function confirmDelete(database) {
  deletingDatabase.value = database
  showDeleteConfirm.value = true
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

function confirmDeleteUser(user) {
  deletingUser.value = user
  showDeleteUserConfirm.value = true
}

async function deleteUser() {
  if (!deletingUser.value) return
  deletingUserLoading.value = true
  try {
    await api.delete(`/database-users/${deletingUser.value.id}`)
    showDeleteUserConfirm.value = false
    appStore.showToast({
      type: 'success',
      message: t('databases.userDeleted')
    })
    await fetchDatabaseUsers()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('databases.userDeleteError')
    })
  } finally {
    deletingUserLoading.value = false
  }
}

// Bulk operation functions
function isSelected(id) {
  return selectedDatabases.value.includes(id)
}

function toggleSelect(id) {
  const index = selectedDatabases.value.indexOf(id)
  if (index === -1) {
    selectedDatabases.value.push(id)
  } else {
    selectedDatabases.value.splice(index, 1)
  }
}

function toggleSelectAll() {
  if (isAllSelected.value) {
    selectedDatabases.value = []
  } else {
    selectedDatabases.value = databases.value.map(db => db.id)
  }
}

function clearSelection() {
  selectedDatabases.value = []
}

async function bulkBackup() {
  if (selectedDatabases.value.length === 0) return
  bulkBackingUp.value = true
  try {
    const results = await Promise.allSettled(
      selectedDatabases.value.map(id => api.post(`/databases/${id}/backup`))
    )
    const successCount = results.filter(r => r.status === 'fulfilled').length
    const failCount = results.filter(r => r.status === 'rejected').length

    if (failCount === 0) {
      appStore.showToast({
        type: 'success',
        message: t('databases.bulkBackupSuccess', { count: successCount })
      })
    } else {
      appStore.showToast({
        type: 'warning',
        message: t('databases.bulkBackupPartial', { success: successCount, fail: failCount })
      })
    }
    clearSelection()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('databases.bulkBackupError')
    })
  } finally {
    bulkBackingUp.value = false
  }
}

function confirmBulkDelete() {
  if (selectedDatabases.value.length === 0) return
  showBulkDeleteConfirm.value = true
}

async function handleBulkDelete() {
  if (selectedDatabases.value.length === 0) return
  bulkDeleting.value = true
  try {
    const results = await Promise.allSettled(
      selectedDatabases.value.map(id => api.delete(`/databases/${id}`))
    )
    const successCount = results.filter(r => r.status === 'fulfilled').length
    const failCount = results.filter(r => r.status === 'rejected').length

    showBulkDeleteConfirm.value = false

    if (failCount === 0) {
      appStore.showToast({
        type: 'success',
        message: t('databases.bulkDeleteSuccess', { count: successCount })
      })
    } else {
      appStore.showToast({
        type: 'warning',
        message: t('databases.bulkDeletePartial', { success: successCount, fail: failCount })
      })
    }
    clearSelection()
    await fetchDatabases()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('databases.bulkDeleteError')
    })
  } finally {
    bulkDeleting.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchDatabases(),
    fetchDatabaseUsers(),
    fetchDomains()
  ])
})
</script>
