<template>
  <div class="space-y-6">
    <!-- SSL Status Card -->
    <VCard>
      <div class="flex items-center justify-between">
        <div class="flex items-center">
          <div :class="[
            'p-4 rounded-lg mr-4',
            certificate ? 'bg-green-100 dark:bg-green-900' : 'bg-gray-100 dark:bg-gray-700'
          ]">
            <LockClosedIcon v-if="certificate" class="w-8 h-8 text-green-600 dark:text-green-400" />
            <LockOpenIcon v-else class="w-8 h-8 text-gray-400" />
          </div>
          <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ certificate ? $t('ssl.certificateActive') : $t('ssl.noSSL') }}
            </h3>
            <p v-if="certificate" class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('ssl.expiresIn') }}: {{ daysUntilExpiry }} {{ $t('ssl.days') }}
            </p>
            <p v-else class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('ssl.noSSLDesc') }}
            </p>
          </div>
        </div>
        <div class="flex items-center space-x-2">
          <VButton
            v-if="!certificate"
            variant="primary"
            :icon="ShieldCheckIcon"
            :loading="issuing"
            @click="issueLetsEncrypt"
          >
            {{ $t('ssl.issueLetsEncrypt') }}
          </VButton>
          <VButton
            v-if="certificate"
            variant="secondary"
            :icon="ArrowPathIcon"
            :loading="renewing"
            @click="renewCertificate"
          >
            {{ $t('ssl.renew') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Certificate Details -->
    <VCard v-if="certificate">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        {{ $t('ssl.certificateDetails') }}
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-3">
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('ssl.type') }}</span>
            <VBadge :variant="certificate.type === 'lets_encrypt' ? 'success' : 'primary'" size="sm">
              {{ certificate.type === 'lets_encrypt' ? "Let's Encrypt" : $t('ssl.custom') }}
            </VBadge>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('ssl.status') }}</span>
            <VBadge :variant="getStatusVariant(certificate.status)" size="sm">
              {{ certificate.status }}
            </VBadge>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('ssl.issuer') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ certificate.issuer || '-' }}</span>
          </div>
        </div>
        <div class="space-y-3">
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('ssl.issuedAt') }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ formatDate(certificate.issued_at) }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('ssl.expiresAt') }}</span>
            <span class="font-medium" :class="expiryClass">{{ formatDate(certificate.expires_at) }}</span>
          </div>
          <div class="flex justify-between py-2 border-b border-gray-100 dark:border-gray-700">
            <span class="text-gray-500 dark:text-gray-400">{{ $t('ssl.autoRenew') }}</span>
            <label class="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                :checked="certificate.auto_renew"
                class="sr-only peer"
                @change="toggleAutoRenew"
              >
              <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 dark:peer-focus:ring-primary-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary-600"></div>
            </label>
          </div>
        </div>
      </div>

      <!-- Delete Certificate -->
      <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
        <VButton
          variant="danger"
          :icon="TrashIcon"
          :loading="deleting"
          @click="confirmDelete"
        >
          {{ $t('ssl.deleteCertificate') }}
        </VButton>
      </div>
    </VCard>

    <!-- Upload Custom Certificate -->
    <VCard v-if="!certificate">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        {{ $t('ssl.uploadCustom') }}
      </h3>
      <form @submit.prevent="uploadCustomCert">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ssl.certificate') }}
            </label>
            <textarea
              v-model="customCert.certificate"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:ring-2 focus:ring-primary-500"
              :placeholder="$t('ssl.certificatePlaceholder')"
            ></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ssl.privateKey') }}
            </label>
            <textarea
              v-model="customCert.private_key"
              rows="4"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:ring-2 focus:ring-primary-500"
              :placeholder="$t('ssl.privateKeyPlaceholder')"
            ></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('ssl.caBundle') }} ({{ $t('common.optional') }})
            </label>
            <textarea
              v-model="customCert.ca_bundle"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white font-mono text-sm focus:ring-2 focus:ring-primary-500"
              :placeholder="$t('ssl.caBundlePlaceholder')"
            ></textarea>
          </div>
        </div>
        <div class="mt-6">
          <VButton variant="primary" type="submit" :loading="uploading">
            {{ $t('ssl.uploadCertificate') }}
          </VButton>
        </div>
      </form>
    </VCard>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VBadge from '@/components/ui/VBadge.vue'
import {
  LockClosedIcon,
  LockOpenIcon,
  ShieldCheckIcon,
  ArrowPathIcon,
  TrashIcon
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
const certificate = ref(null)
const loading = ref(false)
const issuing = ref(false)
const renewing = ref(false)
const deleting = ref(false)
const uploading = ref(false)
const customCert = ref({
  certificate: '',
  private_key: '',
  ca_bundle: ''
})

// Computed
const daysUntilExpiry = computed(() => {
  if (!certificate.value?.expires_at) return 0
  return Math.floor((new Date(certificate.value.expires_at) - new Date()) / (1000 * 60 * 60 * 24))
})

const expiryClass = computed(() => {
  if (daysUntilExpiry.value <= 7) return 'text-red-600 dark:text-red-400'
  if (daysUntilExpiry.value <= 30) return 'text-orange-600 dark:text-orange-400'
  return 'text-green-600 dark:text-green-400'
})

// Methods
function formatDate(dateStr) {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString()
}

function getStatusVariant(status) {
  switch (status) {
    case 'active': return 'success'
    case 'expired': return 'danger'
    case 'pending': return 'warning'
    case 'revoked': return 'secondary'
    default: return 'secondary'
  }
}

async function fetchCertificate() {
  loading.value = true
  try {
    const response = await api.get('/ssl', {
      params: { domain_id: props.domain.id }
    })
    const certs = response.data.data || []
    certificate.value = certs.length > 0 ? certs[0] : null
  } catch (err) {
    console.error('Failed to fetch certificate:', err)
  } finally {
    loading.value = false
  }
}

async function issueLetsEncrypt() {
  issuing.value = true
  try {
    const response = await api.post(`/ssl/domains/${props.domain.id}/letsencrypt`)
    certificate.value = response.data.data
    appStore.showToast({
      type: 'success',
      message: t('ssl.issueSuccess')
    })
    emit('refresh')
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('ssl.issueError')
    })
  } finally {
    issuing.value = false
  }
}

async function renewCertificate() {
  if (!certificate.value) return
  renewing.value = true
  try {
    await api.post(`/ssl/${certificate.value.id}/renew`)
    fetchCertificate()
    appStore.showToast({
      type: 'success',
      message: t('ssl.renewSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('ssl.renewError')
    })
  } finally {
    renewing.value = false
  }
}

async function toggleAutoRenew() {
  if (!certificate.value) return
  try {
    await api.post(`/ssl/${certificate.value.id}/toggle-auto-renew`)
    certificate.value.auto_renew = !certificate.value.auto_renew
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('ssl.updateError')
    })
  }
}

async function confirmDelete() {
  if (!confirm(t('ssl.deleteConfirm'))) return
  deleting.value = true
  try {
    await api.delete(`/ssl/${certificate.value.id}`)
    certificate.value = null
    appStore.showToast({
      type: 'success',
      message: t('ssl.deleteSuccess')
    })
    emit('refresh')
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: t('ssl.deleteError')
    })
  } finally {
    deleting.value = false
  }
}

async function uploadCustomCert() {
  uploading.value = true
  try {
    const response = await api.post(`/ssl/domains/${props.domain.id}/custom`, customCert.value)
    certificate.value = response.data.data
    customCert.value = { certificate: '', private_key: '', ca_bundle: '' }
    appStore.showToast({
      type: 'success',
      message: t('ssl.uploadSuccess')
    })
    emit('refresh')
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('ssl.uploadError')
    })
  } finally {
    uploading.value = false
  }
}

onMounted(() => {
  fetchCertificate()
})
</script>
