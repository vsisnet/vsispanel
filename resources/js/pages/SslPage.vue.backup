<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('nav.ssl') }}
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Manage SSL/TLS certificates for your domains.
      </p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <VCard>
        <div class="flex items-center">
          <div class="p-3 rounded-lg bg-green-100 dark:bg-green-900 mr-4">
            <ShieldCheckIcon class="w-6 h-6 text-green-600 dark:text-green-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Active</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ activeCerts }}</p>
          </div>
        </div>
      </VCard>
      <VCard>
        <div class="flex items-center">
          <div class="p-3 rounded-lg bg-yellow-100 dark:bg-yellow-900 mr-4">
            <ExclamationTriangleIcon class="w-6 h-6 text-yellow-600 dark:text-yellow-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Expiring Soon</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ expiringSoonCerts }}</p>
          </div>
        </div>
      </VCard>
      <VCard>
        <div class="flex items-center">
          <div class="p-3 rounded-lg bg-blue-100 dark:bg-blue-900 mr-4">
            <LockClosedIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Let's Encrypt</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ letsEncryptCerts }}</p>
          </div>
        </div>
      </VCard>
      <VCard>
        <div class="flex items-center">
          <div class="p-3 rounded-lg bg-purple-100 dark:bg-purple-900 mr-4">
            <KeyIcon class="w-6 h-6 text-purple-600 dark:text-purple-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">Custom</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ customCerts }}</p>
          </div>
        </div>
      </VCard>
    </div>

    <!-- Actions Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div class="flex items-center gap-4">
        <VInput
          v-model="search"
          placeholder="Search certificates..."
          class="w-full sm:w-64"
        />
        <select
          v-model="statusFilter"
          class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
        >
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="pending">Pending</option>
          <option value="expired">Expired</option>
          <option value="failed">Failed</option>
          <option value="revoked">Revoked</option>
        </select>
      </div>
      <VButton variant="primary" :icon="PlusIcon" @click="showIssueModal = true">
        Issue Certificate
      </VButton>
    </div>

    <!-- Loading State -->
    <VLoadingSkeleton v-if="loading" class="h-96" />

    <!-- Empty State -->
    <VCard v-else-if="certificates.length === 0" class="text-center py-12">
      <LockOpenIcon class="w-16 h-16 mx-auto text-gray-400 mb-4" />
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
        No SSL Certificates
      </h2>
      <p class="text-gray-500 dark:text-gray-400 mb-4">
        Issue a Let's Encrypt certificate or upload a custom one to secure your domains.
      </p>
      <VButton variant="primary" @click="showIssueModal = true">
        Issue Your First Certificate
      </VButton>
    </VCard>

    <!-- Certificates Table -->
    <VCard v-else>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead>
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Domain
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Type
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Status
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Issuer
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Expires
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Auto-Renew
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="cert in filteredCertificates" :key="cert.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="flex items-center">
                  <LockClosedIcon v-if="cert.status === 'active'" class="w-5 h-5 text-green-500 mr-2" />
                  <LockOpenIcon v-else class="w-5 h-5 text-gray-400 mr-2" />
                  <span class="font-medium text-gray-900 dark:text-white">{{ cert.domain?.name || '-' }}</span>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <VBadge :variant="cert.type === 'lets_encrypt' ? 'success' : 'primary'" size="sm">
                  {{ cert.type === 'lets_encrypt' ? "Let's Encrypt" : 'Custom' }}
                </VBadge>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <VBadge :variant="getStatusVariant(cert.status)" size="sm">
                  {{ capitalize(cert.status) }}
                </VBadge>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                {{ cert.issuer || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="cert.expires_at" :class="getExpiryClass(cert.expires_at)" class="text-sm">
                  {{ formatDate(cert.expires_at) }}
                  <span class="text-xs ml-1">({{ daysUntil(cert.expires_at) }}d)</span>
                </span>
                <span v-else class="text-sm text-gray-400">-</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <button
                  v-if="cert.type === 'lets_encrypt'"
                  @click="toggleAutoRenew(cert)"
                  :class="[
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors',
                    cert.auto_renew ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'
                  ]"
                >
                  <span
                    :class="[
                      'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition',
                      cert.auto_renew ? 'translate-x-5' : 'translate-x-0'
                    ]"
                  ></span>
                </button>
                <span v-else class="text-sm text-gray-400">N/A</span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <div class="flex items-center justify-end space-x-2">
                  <VButton
                    v-if="cert.type === 'lets_encrypt' && cert.status === 'active'"
                    variant="secondary"
                    size="sm"
                    title="Renew"
                    :loading="renewingId === cert.id"
                    @click="renewCert(cert)"
                  >
                    <ArrowPathIcon class="w-4 h-4" />
                  </VButton>
                  <VButton
                    variant="danger"
                    size="sm"
                    title="Delete"
                    @click="confirmDelete(cert)"
                  >
                    <TrashIcon class="w-4 h-4" />
                  </VButton>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </VCard>

    <!-- Issue Certificate Modal -->
    <VModal v-model="showIssueModal" title="Issue SSL Certificate" size="lg">
      <div class="space-y-6">
        <!-- Domain Selection -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Domain
          </label>
          <select
            v-model="issueForm.domain_id"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            required
          >
            <option value="">Select a domain...</option>
            <option v-for="domain in domains" :key="domain.id" :value="domain.id">
              {{ domain.name }}
            </option>
          </select>
        </div>

        <!-- Certificate Type Tabs -->
        <div class="border-b border-gray-200 dark:border-gray-700">
          <nav class="-mb-px flex space-x-8">
            <button
              @click="issueForm.type = 'lets_encrypt'"
              :class="[
                'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                issueForm.type === 'lets_encrypt'
                  ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              <ShieldCheckIcon class="w-5 h-5 inline-block mr-1" />
              Let's Encrypt (Free)
            </button>
            <button
              @click="issueForm.type = 'custom'"
              :class="[
                'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                issueForm.type === 'custom'
                  ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
              ]"
            >
              <KeyIcon class="w-5 h-5 inline-block mr-1" />
              Custom Certificate
            </button>
          </nav>
        </div>

        <!-- Let's Encrypt -->
        <div v-if="issueForm.type === 'lets_encrypt'" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
          <p class="text-sm text-green-800 dark:text-green-200">
            <strong>Automatic SSL:</strong> A free certificate will be issued via Let's Encrypt using certbot.
            Make sure your domain's DNS points to this server before issuing.
          </p>
        </div>

        <!-- Custom Certificate -->
        <template v-if="issueForm.type === 'custom'">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Certificate (PEM)</label>
            <textarea
              v-model="issueForm.certificate"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:ring-2 focus:ring-primary-500"
              placeholder="-----BEGIN CERTIFICATE-----"
            ></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Private Key (PEM)</label>
            <textarea
              v-model="issueForm.private_key"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:ring-2 focus:ring-primary-500"
              placeholder="-----BEGIN PRIVATE KEY-----"
            ></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CA Bundle (optional)</label>
            <textarea
              v-model="issueForm.ca_bundle"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:ring-2 focus:ring-primary-500"
              placeholder="-----BEGIN CERTIFICATE-----"
            ></textarea>
          </div>
        </template>
      </div>
      <div class="mt-6 flex justify-end gap-3">
        <VButton variant="secondary" @click="showIssueModal = false">Cancel</VButton>
        <VButton
          variant="primary"
          :loading="issuing"
          :disabled="!issueForm.domain_id"
          @click="issueCertificate"
        >
          {{ issueForm.type === 'lets_encrypt' ? 'Issue Certificate' : 'Upload Certificate' }}
        </VButton>
      </div>
    </VModal>

    <!-- Delete Confirmation -->
    <VConfirmDialog
      v-model="showDeleteConfirm"
      title="Delete Certificate"
      :message="`Are you sure you want to revoke and delete the SSL certificate for ${certToDelete?.domain?.name}? This will disable HTTPS for the domain.`"
      :loading="deleting"
      @confirm="deleteCert"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
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
  LockClosedIcon,
  LockOpenIcon,
  ShieldCheckIcon,
  KeyIcon,
  PlusIcon,
  TrashIcon,
  ArrowPathIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

// State
const loading = ref(false)
const certificates = ref([])
const domains = ref([])
const search = ref('')
const statusFilter = ref('')

// Modals
const showIssueModal = ref(false)
const showDeleteConfirm = ref(false)

// Actions
const issuing = ref(false)
const deleting = ref(false)
const renewingId = ref(null)
const certToDelete = ref(null)

// Form
const issueForm = ref({
  domain_id: '',
  type: 'lets_encrypt',
  certificate: '',
  private_key: '',
  ca_bundle: ''
})

// Computed
const activeCerts = computed(() => certificates.value.filter(c => c.status === 'active').length)
const expiringSoonCerts = computed(() => certificates.value.filter(c => {
  if (!c.expires_at || c.status !== 'active') return false
  return daysUntil(c.expires_at) <= 30 && daysUntil(c.expires_at) > 0
}).length)
const letsEncryptCerts = computed(() => certificates.value.filter(c => c.type === 'lets_encrypt').length)
const customCerts = computed(() => certificates.value.filter(c => c.type === 'custom').length)

const filteredCertificates = computed(() => {
  let result = certificates.value
  if (statusFilter.value) {
    result = result.filter(c => c.status === statusFilter.value)
  }
  if (search.value) {
    const s = search.value.toLowerCase()
    result = result.filter(c => c.domain?.name?.toLowerCase().includes(s) || c.issuer?.toLowerCase().includes(s))
  }
  return result
})

// Methods
function capitalize(str) {
  return str ? str.charAt(0).toUpperCase() + str.slice(1) : ''
}

function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString()
}

function daysUntil(dateStr) {
  if (!dateStr) return 0
  return Math.floor((new Date(dateStr) - new Date()) / (1000 * 60 * 60 * 24))
}

function getStatusVariant(status) {
  switch (status) {
    case 'active': return 'success'
    case 'pending': return 'warning'
    case 'expired': return 'danger'
    case 'failed': return 'danger'
    case 'revoked': return 'secondary'
    default: return 'secondary'
  }
}

function getExpiryClass(dateStr) {
  const days = daysUntil(dateStr)
  if (days <= 7) return 'text-red-600 dark:text-red-400 font-semibold'
  if (days <= 30) return 'text-orange-600 dark:text-orange-400'
  return 'text-green-600 dark:text-green-400'
}

async function fetchCertificates() {
  loading.value = true
  try {
    const response = await api.get('/ssl')
    certificates.value = response.data.data || []
  } catch (err) {
    console.error('Failed to fetch certificates:', err)
  } finally {
    loading.value = false
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

async function issueCertificate() {
  if (!issueForm.value.domain_id) return
  issuing.value = true
  try {
    if (issueForm.value.type === 'lets_encrypt') {
      await api.post(`/ssl/domains/${issueForm.value.domain_id}/letsencrypt`)
    } else {
      await api.post(`/ssl/domains/${issueForm.value.domain_id}/custom`, {
        certificate: issueForm.value.certificate,
        private_key: issueForm.value.private_key,
        ca_bundle: issueForm.value.ca_bundle || undefined
      })
    }
    appStore.showToast({ type: 'success', message: 'Certificate issued successfully!' })
    showIssueModal.value = false
    issueForm.value = { domain_id: '', type: 'lets_encrypt', certificate: '', private_key: '', ca_bundle: '' }
    await fetchCertificates()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || 'Failed to issue certificate.' })
  } finally {
    issuing.value = false
  }
}

async function renewCert(cert) {
  renewingId.value = cert.id
  try {
    await api.post(`/ssl/${cert.id}/renew`)
    appStore.showToast({ type: 'success', message: 'Certificate renewed successfully!' })
    await fetchCertificates()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || 'Renewal failed.' })
  } finally {
    renewingId.value = null
  }
}

async function toggleAutoRenew(cert) {
  try {
    await api.post(`/ssl/${cert.id}/toggle-auto-renew`)
    cert.auto_renew = !cert.auto_renew
  } catch (err) {
    appStore.showToast({ type: 'error', message: 'Failed to toggle auto-renew.' })
  }
}

function confirmDelete(cert) {
  certToDelete.value = cert
  showDeleteConfirm.value = true
}

async function deleteCert() {
  deleting.value = true
  try {
    await api.delete(`/ssl/${certToDelete.value.id}`)
    appStore.showToast({ type: 'success', message: 'Certificate deleted.' })
    showDeleteConfirm.value = false
    await fetchCertificates()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || 'Delete failed.' })
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchCertificates(), fetchDomains()])
})
</script>
