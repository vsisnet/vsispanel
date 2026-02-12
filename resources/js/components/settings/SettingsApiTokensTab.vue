<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
          {{ $t('apiTokens.title') }}
        </h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          {{ $t('apiTokens.description') }}
        </p>
      </div>
      <VButton variant="primary" @click="showCreateModal = true">
        <PlusIcon class="w-4 h-4 mr-2" />
        {{ $t('apiTokens.create') }}
      </VButton>
    </div>

    <!-- Token List -->
    <VCard>
      <div v-if="loading" class="py-8 text-center text-gray-500 dark:text-gray-400">
        <VLoadingSkeleton class="h-32" />
      </div>

      <div v-else-if="tokens.length === 0" class="py-8 text-center text-gray-500 dark:text-gray-400">
        <KeyIcon class="w-12 h-12 mx-auto mb-3 text-gray-300 dark:text-gray-600" />
        <p>{{ $t('apiTokens.noTokens') }}</p>
      </div>

      <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead>
          <tr>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              {{ $t('apiTokens.name') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              {{ $t('apiTokens.permissions') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              {{ $t('apiTokens.lastUsed') }}
            </th>
            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              {{ $t('apiTokens.created') }}
            </th>
            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
              {{ $t('common.actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
          <tr v-for="token in tokens" :key="token.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
            <td class="px-4 py-3">
              <div class="text-sm font-medium text-gray-900 dark:text-white">{{ token.name }}</div>
              <div class="text-xs text-gray-400 dark:text-gray-500 font-mono">{{ token.token_preview }}</div>
            </td>
            <td class="px-4 py-3">
              <div class="flex flex-wrap gap-1">
                <span
                  v-if="token.abilities.includes('*')"
                  class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300"
                >
                  {{ $t('apiTokens.allPermissions') }}
                </span>
                <template v-else>
                  <span
                    v-for="ability in token.abilities.slice(0, 3)"
                    :key="ability"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                  >
                    {{ ability }}
                  </span>
                  <span
                    v-if="token.abilities.length > 3"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                  >
                    +{{ token.abilities.length - 3 }}
                  </span>
                </template>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
              {{ token.last_used_at ? formatDate(token.last_used_at) : $t('apiTokens.never') }}
            </td>
            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
              {{ formatDate(token.created_at) }}
            </td>
            <td class="px-4 py-3 text-right">
              <VButton variant="danger" size="sm" @click="confirmDelete(token)">
                <TrashIcon class="w-4 h-4" />
              </VButton>
            </td>
          </tr>
        </tbody>
      </table>
    </VCard>

    <!-- Create Token Modal -->
    <VModal v-model="showCreateModal" :title="$t('apiTokens.create')" max-width="lg">
      <div class="space-y-4">
        <!-- Token Name -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('apiTokens.name') }}
          </label>
          <VInput v-model="form.name" :placeholder="$t('apiTokens.namePlaceholder')" />
        </div>

        <!-- Expiry -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('apiTokens.expiry') }}
          </label>
          <VInput v-model="form.expires_at" type="date" />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('apiTokens.noExpiry') }}</p>
        </div>

        <!-- Permissions -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            {{ $t('apiTokens.selectPermissions') }}
          </label>

          <!-- Select All -->
          <label class="flex items-center mb-3 p-2 rounded bg-gray-50 dark:bg-gray-800">
            <input type="checkbox" v-model="selectAll" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500" />
            <span class="ml-2 text-sm font-medium text-gray-900 dark:text-white">{{ $t('apiTokens.allPermissions') }}</span>
          </label>

          <!-- Permission Groups -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-64 overflow-y-auto">
            <div v-for="group in permissionGroups" :key="group.name" class="space-y-1">
              <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ group.label }}</p>
              <label
                v-for="perm in group.permissions"
                :key="perm"
                class="flex items-center"
              >
                <input
                  type="checkbox"
                  :value="perm"
                  v-model="form.abilities"
                  :disabled="selectAll"
                  class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500"
                />
                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ perm }}</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <template #footer>
        <VButton variant="secondary" @click="showCreateModal = false">{{ $t('common.cancel') }}</VButton>
        <VButton variant="primary" :loading="creating" @click="createToken" :disabled="!form.name || (!selectAll && form.abilities.length === 0)">
          {{ $t('apiTokens.create') }}
        </VButton>
      </template>
    </VModal>

    <!-- New Token Display Modal -->
    <VModal v-model="showTokenModal" :title="$t('apiTokens.newToken')" :closeable="true">
      <div class="space-y-4">
        <div class="p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
          <p class="text-sm text-yellow-800 dark:text-yellow-200">
            ⚠️ {{ $t('apiTokens.copyWarning') }}
          </p>
        </div>

        <div class="relative">
          <code class="block w-full p-3 bg-gray-100 dark:bg-gray-800 rounded-lg text-sm font-mono text-gray-900 dark:text-gray-100 break-all select-all">
            {{ newToken }}
          </code>
          <button
            @click="copyToken"
            class="absolute top-2 right-2 p-1.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 bg-white dark:bg-gray-700 rounded shadow-sm"
          >
            <ClipboardDocumentIcon class="w-4 h-4" />
          </button>
        </div>
      </div>

      <template #footer>
        <VButton variant="primary" @click="showTokenModal = false">{{ $t('common.done') }}</VButton>
      </template>
    </VModal>

    <!-- Delete Confirmation -->
    <VModal v-model="showDeleteModal" :title="$t('apiTokens.delete')" max-width="sm">
      <p class="text-sm text-gray-700 dark:text-gray-300">
        {{ $t('apiTokens.deleteConfirm', { name: tokenToDelete?.name }) }}
      </p>
      <template #footer>
        <VButton variant="secondary" @click="showDeleteModal = false">{{ $t('common.cancel') }}</VButton>
        <VButton variant="danger" :loading="deleting" @click="deleteToken">{{ $t('apiTokens.delete') }}</VButton>
      </template>
    </VModal>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VInput from '@/components/ui/VInput.vue'
import VModal from '@/components/ui/VModal.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import { PlusIcon, TrashIcon, KeyIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline'
import { useAppStore } from '@/stores/app'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const appStore = useAppStore()

const tokens = ref([])
const loading = ref(true)
const creating = ref(false)
const deleting = ref(false)

const showCreateModal = ref(false)
const showTokenModal = ref(false)
const showDeleteModal = ref(false)
const newToken = ref('')
const tokenToDelete = ref(null)

const selectAll = ref(false)

const form = ref({
  name: '',
  abilities: [],
  expires_at: '',
})

const permissionGroups = [
  { name: 'domains', label: 'Websites', permissions: ['domains.view', 'domains.create', 'domains.edit', 'domains.delete'] },
  { name: 'databases', label: 'Databases', permissions: ['databases.view', 'databases.create', 'databases.edit', 'databases.delete'] },
  { name: 'mail', label: 'Email', permissions: ['mail.view', 'mail.create', 'mail.edit', 'mail.delete'] },
  { name: 'dns', label: 'DNS', permissions: ['dns.view', 'dns.create', 'dns.edit', 'dns.delete'] },
  { name: 'ssl', label: 'SSL', permissions: ['ssl.view', 'ssl.create', 'ssl.edit', 'ssl.delete'] },
  { name: 'files', label: 'Files', permissions: ['files.view', 'files.create', 'files.edit', 'files.delete'] },
  { name: 'ftp', label: 'FTP', permissions: ['ftp.view', 'ftp.create', 'ftp.edit', 'ftp.delete'] },
  { name: 'backup', label: 'Backup', permissions: ['backup.view', 'backup.create', 'backup.edit', 'backup.delete'] },
  { name: 'cron', label: 'Cron', permissions: ['cron.view', 'cron.create', 'cron.edit', 'cron.delete'] },
  { name: 'firewall', label: 'Firewall', permissions: ['firewall.view', 'firewall.create', 'firewall.edit', 'firewall.delete'] },
]

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
}

async function fetchTokens() {
  try {
    const { data } = await api.get('/auth/api-tokens')
    if (data.success) {
      tokens.value = data.data
    }
  } catch (err) {
    console.error('Failed to fetch tokens:', err)
  } finally {
    loading.value = false
  }
}

async function createToken() {
  creating.value = true
  try {
    const payload = {
      name: form.value.name,
      abilities: selectAll.value ? ['*'] : form.value.abilities,
    }
    if (form.value.expires_at) {
      payload.expires_at = form.value.expires_at
    }

    const { data } = await api.post('/auth/api-tokens', payload)
    if (data.success) {
      newToken.value = data.data.token
      showCreateModal.value = false
      showTokenModal.value = true
      form.value = { name: '', abilities: [], expires_at: '' }
      selectAll.value = false
      await fetchTokens()
    }
  } catch (err) {
    console.error('Failed to create token:', err)
  } finally {
    creating.value = false
  }
}

function confirmDelete(token) {
  tokenToDelete.value = token
  showDeleteModal.value = true
}

async function deleteToken() {
  if (!tokenToDelete.value) return
  deleting.value = true
  try {
    const { data } = await api.delete(`/auth/api-tokens/${tokenToDelete.value.id}`)
    if (data.success) {
      showDeleteModal.value = false
      tokenToDelete.value = null
      appStore.showToast({ type: 'success', message: t('apiTokens.deleted') })
      await fetchTokens()
    }
  } catch (err) {
    console.error('Failed to delete token:', err)
  } finally {
    deleting.value = false
  }
}

function copyToken() {
  navigator.clipboard.writeText(newToken.value)
  appStore.showToast({ type: 'success', message: t('apiTokens.copied') })
}

onMounted(fetchTokens)
</script>
