<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('email.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $t('email.description') }}
      </p>
    </div>

    <!-- Domain Selector -->
    <VCard class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('websites.domain') }}
          </label>
          <select
            v-model="selectedDomainId"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            @change="onDomainChange"
          >
            <option value="">{{ $t('common.noData') }}</option>
            <option v-for="domain in domains" :key="domain.id" :value="domain.id">
              {{ domain.name }}
            </option>
          </select>
        </div>
        <div v-if="selectedDomain && !selectedMailDomain" class="sm:self-end">
          <VButton variant="primary" :loading="enabling" @click="enableMail">
            {{ $t('email.enableMail') }}
          </VButton>
        </div>
        <div v-else-if="selectedMailDomain" class="sm:self-end flex items-center gap-2">
          <VBadge variant="success">
            {{ $t('email.mailEnabled') }}
          </VBadge>
        </div>
      </div>
    </VCard>

    <!-- Loading State -->
    <VLoadingSkeleton v-if="loading" class="h-96" />

    <!-- No Domain Selected -->
    <VCard v-else-if="!selectedDomainId" class="text-center py-12">
      <EnvelopeIcon class="w-16 h-16 mx-auto text-gray-400 mb-4" />
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
        {{ $t('email.noAccounts') }}
      </h2>
      <p class="text-gray-500 dark:text-gray-400">
        {{ $t('websites.domain') }}
      </p>
    </VCard>

    <!-- Mail Not Enabled -->
    <VCard v-else-if="selectedDomain && !selectedMailDomain" class="text-center py-12">
      <EnvelopeIcon class="w-16 h-16 mx-auto text-gray-400 mb-4" />
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
        {{ $t('email.noAccounts') }}
      </h2>
      <p class="text-gray-500 dark:text-gray-400 mb-4">
        {{ $t('email.noAccountsDesc') }}
      </p>
      <VButton variant="primary" :loading="enabling" @click="enableMail">
        {{ $t('email.enableMail') }}
      </VButton>
    </VCard>

    <!-- Mail Content -->
    <template v-else-if="selectedMailDomain">
      <!-- Tabs Navigation -->
      <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="-mb-px flex space-x-8 overflow-x-auto">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === tab.id
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'
            ]"
          >
            <component :is="tab.icon" class="w-5 h-5 inline-block mr-2" />
            {{ $t(`email.${tab.id}`) }}
          </button>
        </nav>
      </div>

      <!-- Accounts Tab -->
      <div v-if="activeTab === 'accounts'">
        <!-- Actions Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
          <VInput
            v-model="search"
            :placeholder="$t('email.searchAccounts')"
            class="w-full sm:w-64"
          />
          <VButton variant="primary" :icon="PlusIcon" @click="showCreateModal = true">
            {{ $t('email.createAccount') }}
          </VButton>
        </div>

        <!-- Accounts Table -->
        <VCard>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead>
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ $t('email.account') }}
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ $t('email.quota') }}
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ $t('email.status') }}
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ $t('email.lastLogin') }}
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ $t('common.actions') }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-if="accounts.length === 0">
                  <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                    {{ $t('email.noAccounts') }}
                  </td>
                </tr>
                <tr v-for="account in filteredAccounts" :key="account.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <EnvelopeIcon class="w-5 h-5 text-gray-400 mr-3" />
                      <span class="font-medium text-gray-900 dark:text-white">{{ account.email }}</span>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="w-32">
                      <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                        <span>{{ account.quota_used_mb }} MB</span>
                        <span>{{ account.quota_mb }} MB</span>
                      </div>
                      <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                        <div
                          class="h-2 rounded-full transition-all"
                          :class="account.quota_percent > 90 ? 'bg-red-500' : account.quota_percent > 70 ? 'bg-yellow-500' : 'bg-green-500'"
                          :style="{ width: `${Math.min(account.quota_percent, 100)}%` }"
                        ></div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <VBadge :variant="getStatusVariant(account.status)">
                      {{ $t(`email.status${capitalize(account.status)}`) }}
                    </VBadge>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    {{ account.last_login_at ? formatDate(account.last_login_at) : $t('email.neverLoggedIn') }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <div class="flex items-center justify-end space-x-2">
                      <VButton
                        v-if="account.status === 'active'"
                        variant="primary"
                        size="sm"
                        :title="$t('email.openWebmail')"
                        :loading="openingWebmail === account.id"
                        @click="openWebmail(account)"
                      >
                        <GlobeAltIcon class="w-4 h-4" />
                      </VButton>
                      <VButton variant="secondary" size="sm" :title="$t('email.changePassword')" @click="openPasswordModal(account)">
                        <KeyIcon class="w-4 h-4" />
                      </VButton>
                      <VButton
                        v-if="account.status === 'active'"
                        variant="warning"
                        size="sm"
                        :title="$t('email.suspend')"
                        @click="suspendAccount(account)"
                      >
                        <PauseIcon class="w-4 h-4" />
                      </VButton>
                      <VButton
                        v-else
                        variant="success"
                        size="sm"
                        :title="$t('email.unsuspend')"
                        @click="unsuspendAccount(account)"
                      >
                        <PlayIcon class="w-4 h-4" />
                      </VButton>
                      <VButton variant="danger" size="sm" :title="$t('common.delete')" @click="confirmDeleteAccount(account)">
                        <TrashIcon class="w-4 h-4" />
                      </VButton>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </VCard>
      </div>

      <!-- Aliases Tab -->
      <div v-else-if="activeTab === 'aliases'">
        <div class="flex justify-end mb-6">
          <VButton variant="primary" :icon="PlusIcon" @click="showAliasModal = true">
            {{ $t('email.createAlias') }}
          </VButton>
        </div>

        <VCard>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead>
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ $t('email.aliasSource') }}
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ $t('email.aliasDestination') }}
                  </th>
                  <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ $t('common.actions') }}
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-if="aliases.length === 0">
                  <td colspan="3" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                    {{ $t('common.noData') }}
                  </td>
                </tr>
                <tr v-for="alias in aliases" :key="alias.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                  <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-white">
                    {{ alias.source_address }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-gray-500 dark:text-gray-400">
                    {{ alias.destination_address }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-right">
                    <VButton variant="danger" size="sm" @click="deleteAlias(alias)">
                      <TrashIcon class="w-4 h-4" />
                    </VButton>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </VCard>
      </div>

      <!-- Forwards Tab -->
      <div v-else-if="activeTab === 'forwards'">
        <VCard class="text-center py-12">
          <ArrowPathIcon class="w-16 h-16 mx-auto text-gray-400 mb-4" />
          <p class="text-gray-500 dark:text-gray-400">
            {{ $t('email.forwarding') }}
          </p>
        </VCard>
      </div>

      <!-- Spam Settings Tab -->
      <div v-else-if="activeTab === 'spamSettings'">
        <SpamSettingsTab />
      </div>

      <!-- Mail Configuration Card -->
      <VCard class="mt-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $t('email.mailConfig') }}
          </h3>
          <VButton variant="secondary" size="sm" @click="showConfigModal = true">
            <Cog6ToothIcon class="w-4 h-4 mr-2" />
            {{ $t('email.clientSettings') }}
          </VButton>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $t('email.imapServer') }}</div>
            <div class="flex items-center justify-between">
              <div class="font-medium text-gray-900 dark:text-white">mail.{{ selectedDomain?.name }}</div>
              <button @click="copyToClipboard(`mail.${selectedDomain?.name}`)" class="text-gray-400 hover:text-gray-600">
                <ClipboardDocumentIcon class="w-4 h-4" />
              </button>
            </div>
            <div class="text-xs text-gray-500">Port: 993 (SSL/TLS)</div>
          </div>
          <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $t('email.popServer') }}</div>
            <div class="flex items-center justify-between">
              <div class="font-medium text-gray-900 dark:text-white">mail.{{ selectedDomain?.name }}</div>
              <button @click="copyToClipboard(`mail.${selectedDomain?.name}`)" class="text-gray-400 hover:text-gray-600">
                <ClipboardDocumentIcon class="w-4 h-4" />
              </button>
            </div>
            <div class="text-xs text-gray-500">Port: 995 (SSL/TLS)</div>
          </div>
          <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $t('email.smtpServer') }}</div>
            <div class="flex items-center justify-between">
              <div class="font-medium text-gray-900 dark:text-white">mail.{{ selectedDomain?.name }}</div>
              <button @click="copyToClipboard(`mail.${selectedDomain?.name}`)" class="text-gray-400 hover:text-gray-600">
                <ClipboardDocumentIcon class="w-4 h-4" />
              </button>
            </div>
            <div class="text-xs text-gray-500">Port: 587 (STARTTLS)</div>
          </div>
          <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <div class="text-sm text-blue-600 dark:text-blue-400">{{ $t('email.webmail') }}</div>
            <div class="font-medium text-blue-700 dark:text-blue-300">Roundcube</div>
            <a
              :href="webmailUrl"
              target="_blank"
              class="text-xs text-blue-500 hover:underline"
            >
              {{ $t('email.openWebmailLink') }}
            </a>
          </div>
        </div>
      </VCard>
    </template>

    <!-- Create Account Modal -->
    <VModal v-model="showCreateModal" :title="$t('email.createAccount')">
      <form @submit.prevent="createAccount">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('email.username') }}
            </label>
            <div class="flex items-center">
              <VInput
                v-model="newAccount.username"
                :placeholder="$t('email.usernamePlaceholder')"
                class="flex-1 rounded-r-none"
                required
              />
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
              <VInput
                v-model="newAccount.password"
                type="password"
                class="flex-1"
                required
                minlength="8"
              />
              <VButton type="button" variant="secondary" @click="generatePassword">
                {{ $t('email.generatePassword') }}
              </VButton>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('email.quotaMb') }}
            </label>
            <VInput
              v-model.number="newAccount.quota_mb"
              type="number"
              min="1"
              max="102400"
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

    <!-- Change Password Modal -->
    <VModal v-model="showPasswordModal" :title="$t('email.changePassword')">
      <form @submit.prevent="changePassword">
        <div class="space-y-4">
          <p class="text-gray-600 dark:text-gray-400">
            {{ selectedAccount?.email }}
          </p>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('email.password') }}
            </label>
            <div class="flex gap-2">
              <VInput
                v-model="newPassword"
                type="password"
                class="flex-1"
                required
                minlength="8"
              />
              <VButton type="button" variant="secondary" @click="generatePasswordForChange">
                {{ $t('email.generatePassword') }}
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

    <!-- Create Alias Modal -->
    <VModal v-model="showAliasModal" :title="$t('email.createAlias')">
      <form @submit.prevent="createAlias">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('email.aliasSource') }}
            </label>
            <div class="flex items-center">
              <VInput
                v-model="newAlias.source"
                placeholder="sales"
                class="flex-1 rounded-r-none"
                required
              />
              <span class="px-3 py-2 bg-gray-100 dark:bg-gray-700 border border-l-0 border-gray-300 dark:border-gray-600 rounded-r-lg text-gray-500 dark:text-gray-400">
                @{{ selectedDomain?.name }}
              </span>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('email.aliasDestination') }}
            </label>
            <VInput
              v-model="newAlias.destination"
              type="email"
              placeholder="user@example.com"
              required
            />
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showAliasModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="creatingAlias">
            {{ $t('common.create') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Delete Confirmation Modal -->
    <VConfirmDialog
      v-model="showDeleteConfirm"
      :title="$t('confirmDialog.deleteTitle')"
      :message="$t('email.deleteAccountConfirm', { email: accountToDelete?.email })"
      :loading="deleting"
      @confirm="deleteAccount"
    />

    <!-- Mail Client Configuration Modal -->
    <VModal v-model="showConfigModal" :title="$t('email.clientConfigTitle')" size="lg">
      <div class="space-y-6">
        <!-- Instructions -->
        <p class="text-sm text-gray-600 dark:text-gray-400">
          {{ $t('email.clientConfigDescription') }}
        </p>

        <!-- IMAP Settings -->
        <div class="border dark:border-gray-700 rounded-lg p-4">
          <h4 class="font-medium text-gray-900 dark:text-white mb-3 flex items-center">
            <EnvelopeIcon class="w-5 h-5 mr-2 text-blue-500" />
            {{ $t('email.imapSettings') }}
          </h4>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.server') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">mail.{{ selectedDomain?.name }}</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.port') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">993</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.security') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">SSL/TLS</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.authentication') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">{{ $t('email.normalPassword') }}</span>
            </div>
          </div>
        </div>

        <!-- POP3 Settings -->
        <div class="border dark:border-gray-700 rounded-lg p-4">
          <h4 class="font-medium text-gray-900 dark:text-white mb-3 flex items-center">
            <EnvelopeIcon class="w-5 h-5 mr-2 text-green-500" />
            {{ $t('email.pop3Settings') }}
          </h4>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.server') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">mail.{{ selectedDomain?.name }}</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.port') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">995</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.security') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">SSL/TLS</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.authentication') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">{{ $t('email.normalPassword') }}</span>
            </div>
          </div>
        </div>

        <!-- SMTP Settings -->
        <div class="border dark:border-gray-700 rounded-lg p-4">
          <h4 class="font-medium text-gray-900 dark:text-white mb-3 flex items-center">
            <EnvelopeIcon class="w-5 h-5 mr-2 text-purple-500" />
            {{ $t('email.smtpSettings') }}
          </h4>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.server') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">mail.{{ selectedDomain?.name }}</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.port') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">587</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.security') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">STARTTLS</span>
            </div>
            <div>
              <span class="text-gray-500 dark:text-gray-400">{{ $t('email.authentication') }}:</span>
              <span class="ml-2 font-mono text-gray-900 dark:text-white">{{ $t('email.required') }}</span>
            </div>
          </div>
        </div>

        <!-- Username Note -->
        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
          <p class="text-sm text-yellow-800 dark:text-yellow-200">
            <strong>{{ $t('email.usernameNote') }}:</strong> {{ $t('email.usernameNoteDesc') }}
          </p>
        </div>
      </div>

      <div class="mt-6 flex justify-end">
        <VButton variant="secondary" @click="showConfigModal = false">
          {{ $t('common.close') }}
        </VButton>
      </div>
    </VModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, markRaw } from 'vue'
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
import SpamSettingsTab from '@/components/mail/SpamSettingsTab.vue'
import {
  EnvelopeIcon,
  PlusIcon,
  KeyIcon,
  TrashIcon,
  PauseIcon,
  PlayIcon,
  ArrowPathIcon,
  ShieldExclamationIcon,
  UserGroupIcon,
  ArrowsRightLeftIcon,
  GlobeAltIcon,
  ClipboardDocumentIcon,
  Cog6ToothIcon
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

// State
const loading = ref(false)
const domains = ref([])
const selectedDomainId = ref('')
const selectedMailDomain = ref(null)
const accounts = ref([])
const aliases = ref([])
const activeTab = ref('accounts')
const search = ref('')
const enabling = ref(false)

// Modals
const showCreateModal = ref(false)
const showPasswordModal = ref(false)
const showAliasModal = ref(false)
const showDeleteConfirm = ref(false)
const showConfigModal = ref(false)

// Webmail
const openingWebmail = ref(null)
const webmailConfig = ref(null)

// Form states
const creating = ref(false)
const changingPassword = ref(false)
const creatingAlias = ref(false)
const deleting = ref(false)

const newAccount = ref({ username: '', password: '', quota_mb: 1024 })
const newPassword = ref('')
const newAlias = ref({ source: '', destination: '' })
const selectedAccount = ref(null)
const accountToDelete = ref(null)

// Tabs configuration
const tabs = [
  { id: 'accounts', icon: markRaw(EnvelopeIcon) },
  { id: 'aliases', icon: markRaw(UserGroupIcon) },
  { id: 'forwards', icon: markRaw(ArrowsRightLeftIcon) },
  { id: 'spamSettings', icon: markRaw(ShieldExclamationIcon) }
]

// Computed
const selectedDomain = computed(() => domains.value.find(d => d.id === selectedDomainId.value))

const filteredAccounts = computed(() => {
  if (!search.value) return accounts.value
  return accounts.value.filter(a =>
    a.email.toLowerCase().includes(search.value.toLowerCase())
  )
})

const webmailUrl = computed(() => {
  return webmailConfig.value?.url || '/webmail'
})

// Methods
function capitalize(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''
}

function getStatusVariant(status) {
  switch (status) {
    case 'active': return 'success'
    case 'suspended': return 'danger'
    case 'disabled': return 'secondary'
    default: return 'secondary'
  }
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString()
}

function generatePassword() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%'
  let password = ''
  for (let i = 0; i < 16; i++) {
    password += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  newAccount.value.password = password
}

function generatePasswordForChange() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%'
  let password = ''
  for (let i = 0; i < 16; i++) {
    password += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  newPassword.value = password
}

async function fetchDomains() {
  try {
    const response = await api.get('/domains')
    domains.value = response.data.data
    if (domains.value.length > 0 && !selectedDomainId.value) {
      selectedDomainId.value = domains.value[0].id
    }
  } catch (err) {
    console.error('Failed to fetch domains:', err)
  }
}

async function fetchMailDomain() {
  if (!selectedDomainId.value) {
    selectedMailDomain.value = null
    accounts.value = []
    aliases.value = []
    return
  }

  loading.value = true
  try {
    const response = await api.get('/mail/domains', {
      params: { domain_id: selectedDomainId.value }
    })
    const mailDomains = response.data.data
    selectedMailDomain.value = mailDomains.find(md => md.domain_id === selectedDomainId.value) || null

    if (selectedMailDomain.value) {
      await fetchAccounts()
      await fetchAliases()
    }
  } catch (err) {
    console.error('Failed to fetch mail domain:', err)
    selectedMailDomain.value = null
  } finally {
    loading.value = false
  }
}

async function fetchAccounts() {
  if (!selectedMailDomain.value) return
  try {
    const response = await api.get('/mail/accounts', {
      params: { mail_domain_id: selectedMailDomain.value.id }
    })
    accounts.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch accounts:', err)
  }
}

async function fetchAliases() {
  if (!selectedMailDomain.value) return
  try {
    const response = await api.get('/mail/aliases', {
      params: { mail_domain_id: selectedMailDomain.value.id }
    })
    aliases.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch aliases:', err)
  }
}

async function enableMail() {
  enabling.value = true
  try {
    await api.post('/mail/domains', {
      domain_id: selectedDomainId.value,
      enable_dkim: true
    })
    appStore.showToast({ type: 'success', message: t('email.mailEnabled') })
    await fetchMailDomain()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  } finally {
    enabling.value = false
  }
}

async function createAccount() {
  creating.value = true
  try {
    await api.post('/mail/accounts', {
      mail_domain_id: selectedMailDomain.value.id,
      username: newAccount.value.username,
      password: newAccount.value.password,
      quota_mb: newAccount.value.quota_mb
    })
    appStore.showToast({ type: 'success', message: t('email.createSuccess') })
    showCreateModal.value = false
    newAccount.value = { username: '', password: '', quota_mb: 1024 }
    await fetchAccounts()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('email.createError') })
  } finally {
    creating.value = false
  }
}

function openPasswordModal(account) {
  selectedAccount.value = account
  newPassword.value = ''
  showPasswordModal.value = true
}

async function changePassword() {
  changingPassword.value = true
  try {
    await api.put(`/mail/accounts/${selectedAccount.value.id}/password`, {
      password: newPassword.value
    })
    appStore.showToast({ type: 'success', message: t('email.passwordChanged') })
    showPasswordModal.value = false
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('email.passwordChangeError') })
  } finally {
    changingPassword.value = false
  }
}

async function suspendAccount(account) {
  try {
    await api.put(`/mail/accounts/${account.id}`, { status: 'suspended' })
    appStore.showToast({ type: 'success', message: t('email.suspendSuccess') })
    await fetchAccounts()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  }
}

async function unsuspendAccount(account) {
  try {
    await api.put(`/mail/accounts/${account.id}`, { status: 'active' })
    appStore.showToast({ type: 'success', message: t('email.unsuspendSuccess') })
    await fetchAccounts()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  }
}

function confirmDeleteAccount(account) {
  accountToDelete.value = account
  showDeleteConfirm.value = true
}

async function deleteAccount() {
  deleting.value = true
  try {
    await api.delete(`/mail/accounts/${accountToDelete.value.id}`)
    appStore.showToast({ type: 'success', message: t('email.deleteSuccess') })
    showDeleteConfirm.value = false
    await fetchAccounts()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('email.deleteError') })
  } finally {
    deleting.value = false
  }
}

async function createAlias() {
  creatingAlias.value = true
  try {
    await api.post('/mail/aliases', {
      mail_domain_id: selectedMailDomain.value.id,
      source: newAlias.value.source,
      destination: newAlias.value.destination
    })
    appStore.showToast({ type: 'success', message: t('email.aliasCreated') })
    showAliasModal.value = false
    newAlias.value = { source: '', destination: '' }
    await fetchAliases()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  } finally {
    creatingAlias.value = false
  }
}

async function deleteAlias(alias) {
  try {
    await api.delete(`/mail/aliases/${alias.id}`)
    appStore.showToast({ type: 'success', message: t('email.aliasDeleted') })
    await fetchAliases()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  }
}

function onDomainChange() {
  fetchMailDomain()
}

async function openWebmail(account) {
  openingWebmail.value = account.id
  try {
    const response = await api.get(`/mail/accounts/${account.id}/webmail-url`)
    if (response.data.success && response.data.data.url) {
      window.open(response.data.data.url, '_blank')
    }
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('email.webmailError')
    })
  } finally {
    openingWebmail.value = null
  }
}

async function fetchWebmailConfig() {
  try {
    const response = await api.get('/mail/webmail/config')
    webmailConfig.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch webmail config:', err)
  }
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => {
    appStore.showToast({ type: 'success', message: t('common.copied') })
  }).catch(() => {
    appStore.showToast({ type: 'error', message: t('common.copyFailed') })
  })
}

// Watch for domain changes
watch(selectedDomainId, () => {
  if (selectedDomainId.value) {
    fetchMailDomain()
  }
})

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchDomains(),
    fetchWebmailConfig()
  ])
  if (selectedDomainId.value) {
    await fetchMailDomain()
  }
})
</script>
