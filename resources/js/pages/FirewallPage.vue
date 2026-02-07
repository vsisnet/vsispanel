<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('firewall.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $t('firewall.description') }}
      </p>
    </div>

    <!-- Warning Banner when Firewall Disabled -->
    <div v-if="!firewallEnabled" class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
      <div class="flex items-center">
        <ExclamationTriangleIcon class="w-5 h-5 text-red-500 mr-3" />
        <div>
          <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
            {{ $t('firewall.disabledWarning') }}
          </h3>
          <p class="text-sm text-red-600 dark:text-red-300 mt-1">
            {{ $t('firewall.disabledWarningDesc') }}
          </p>
        </div>
      </div>
    </div>

    <!-- Status and Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <!-- Firewall Status Card -->
      <VCard>
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <div :class="[
              'p-3 rounded-lg mr-4',
              firewallEnabled ? 'bg-green-100 dark:bg-green-900/30' : 'bg-red-100 dark:bg-red-900/30'
            ]">
              <ShieldCheckIcon v-if="firewallEnabled" class="w-6 h-6 text-green-600 dark:text-green-400" />
              <ShieldExclamationIcon v-else class="w-6 h-6 text-red-600 dark:text-red-400" />
            </div>
            <div>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('firewall.status') }}</p>
              <p :class="[
                'text-lg font-semibold',
                firewallEnabled ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
              ]">
                {{ firewallEnabled ? $t('firewall.enabled') : $t('firewall.disabled') }}
              </p>
            </div>
          </div>
          <VButton
            :variant="firewallEnabled ? 'danger' : 'success'"
            size="sm"
            :loading="togglingFirewall"
            @click="toggleFirewall"
          >
            {{ firewallEnabled ? $t('firewall.disable') : $t('firewall.enable') }}
          </VButton>
        </div>
      </VCard>

      <!-- Default Incoming Policy -->
      <VCard>
        <div class="flex items-center">
          <div class="p-3 rounded-lg mr-4 bg-blue-100 dark:bg-blue-900/30">
            <ArrowDownIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('firewall.defaultIncoming') }}</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white uppercase">
              {{ policies.incoming || 'deny' }}
            </p>
          </div>
        </div>
      </VCard>

      <!-- Default Outgoing Policy -->
      <VCard>
        <div class="flex items-center">
          <div class="p-3 rounded-lg mr-4 bg-purple-100 dark:bg-purple-900/30">
            <ArrowUpIcon class="w-6 h-6 text-purple-600 dark:text-purple-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('firewall.defaultOutgoing') }}</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white uppercase">
              {{ policies.outgoing || 'allow' }}
            </p>
          </div>
        </div>
      </VCard>

      <!-- Total Rules -->
      <VCard>
        <div class="flex items-center">
          <div class="p-3 rounded-lg mr-4 bg-orange-100 dark:bg-orange-900/30">
            <ListBulletIcon class="w-6 h-6 text-orange-600 dark:text-orange-400" />
          </div>
          <div>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('firewall.totalRules') }}</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ rules.length }}
            </p>
          </div>
        </div>
      </VCard>
    </div>

    <!-- Quick Actions -->
    <VCard class="mb-6">
      <div class="flex flex-wrap items-center gap-2">
        <VButton variant="primary" :icon="PlusIcon" @click="openAddModal">
          {{ $t('firewall.addRule') }}
        </VButton>
        <VButton variant="secondary" @click="openQuickAction('allowPort')">
          {{ $t('firewall.quickAllowPort') }}
        </VButton>
        <VButton variant="secondary" @click="openQuickAction('blockIp')">
          {{ $t('firewall.quickBlockIp') }}
        </VButton>
        <VButton variant="secondary" @click="openQuickAction('allowIp')">
          {{ $t('firewall.quickAllowIp') }}
        </VButton>
        <div class="flex-1"></div>
        <VButton
          variant="danger"
          :icon="ArrowPathIcon"
          :loading="resetting"
          @click="confirmReset"
        >
          {{ $t('firewall.resetToDefaults') }}
        </VButton>
      </div>
    </VCard>

    <!-- Rules Table -->
    <VCard :padding="false">
      <VLoadingSkeleton v-if="loading" class="h-64" />

      <div v-else-if="rules.length === 0" class="text-center py-12">
        <ShieldCheckIcon class="w-16 h-16 mx-auto text-gray-400 mb-4" />
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
          {{ $t('firewall.noRules') }}
        </h2>
        <p class="text-gray-500 dark:text-gray-400 mb-4">
          {{ $t('firewall.noRulesDesc') }}
        </p>
        <VButton variant="primary" :icon="PlusIcon" @click="openAddModal">
          {{ $t('firewall.addFirstRule') }}
        </VButton>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('firewall.action') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('firewall.direction') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('firewall.protocol') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('firewall.port') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('firewall.source') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('firewall.comment') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('firewall.statusCol') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                {{ $t('common.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="rule in rules" :key="rule.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
              <td class="px-6 py-4 whitespace-nowrap">
                <VBadge :variant="getActionVariant(rule.action)">
                  {{ rule.action.toUpperCase() }}
                </VBadge>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                {{ rule.direction.toUpperCase() }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-mono">
                {{ rule.protocol?.toUpperCase() || 'ANY' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-mono">
                {{ rule.port || '-' }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white font-mono">
                {{ rule.source_ip || $t('firewall.anywhere') }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                <div class="flex items-center">
                  <span>{{ rule.comment || '-' }}</span>
                  <LockClosedIcon v-if="rule.is_essential" class="w-4 h-4 ml-2 text-yellow-500" :title="$t('firewall.essentialRule')" />
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <button
                  @click="toggleRule(rule)"
                  :disabled="togglingRule === rule.id"
                  :class="[
                    'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
                    rule.is_active ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'
                  ]"
                >
                  <span
                    :class="[
                      'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                      rule.is_active ? 'translate-x-5' : 'translate-x-0'
                    ]"
                  />
                </button>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right">
                <VButton
                  v-if="!rule.is_essential"
                  variant="ghost"
                  size="sm"
                  :icon="TrashIcon"
                  class="text-red-600 hover:text-red-800"
                  @click="confirmDelete(rule)"
                />
                <span v-else class="text-sm text-gray-400">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </VCard>

    <!-- Add Rule Modal -->
    <VModal v-model="showAddModal" :title="$t('firewall.addRule')">
      <form @submit.prevent="addRule">
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('firewall.action') }}
              </label>
              <select
                v-model="ruleForm.action"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
                required
              >
                <option value="allow">{{ $t('firewall.actionAllow') }}</option>
                <option value="deny">{{ $t('firewall.actionDeny') }}</option>
                <option value="limit">{{ $t('firewall.actionLimit') }}</option>
                <option value="reject">{{ $t('firewall.actionReject') }}</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('firewall.direction') }}
              </label>
              <select
                v-model="ruleForm.direction"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              >
                <option value="in">{{ $t('firewall.directionIn') }}</option>
                <option value="out">{{ $t('firewall.directionOut') }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('firewall.protocol') }}
              </label>
              <select
                v-model="ruleForm.protocol"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              >
                <option value="any">{{ $t('firewall.protocolAny') }}</option>
                <option value="tcp">TCP</option>
                <option value="udp">UDP</option>
              </select>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('firewall.port') }}
              </label>
              <VInput
                v-model="ruleForm.port"
                :placeholder="$t('firewall.portPlaceholder')"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('firewall.sourceIp') }}
            </label>
            <VInput
              v-model="ruleForm.source_ip"
              :placeholder="$t('firewall.sourceIpPlaceholder')"
            />
            <p class="mt-1 text-xs text-gray-500">{{ $t('firewall.sourceIpHint') }}</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('firewall.comment') }}
            </label>
            <VInput
              v-model="ruleForm.comment"
              :placeholder="$t('firewall.commentPlaceholder')"
            />
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showAddModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="adding">
            {{ $t('firewall.addRule') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Quick Action Modal -->
    <VModal v-model="showQuickModal" :title="quickActionTitle">
      <form @submit.prevent="executeQuickAction">
        <div class="space-y-4">
          <div v-if="quickActionType === 'allowPort'">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('firewall.port') }}
            </label>
            <VInput
              v-model="quickForm.port"
              :placeholder="$t('firewall.portPlaceholder')"
              required
            />
            <div class="mt-2">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('firewall.protocol') }}
              </label>
              <select
                v-model="quickForm.protocol"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              >
                <option value="tcp">TCP</option>
                <option value="udp">UDP</option>
                <option value="any">{{ $t('firewall.protocolAny') }}</option>
              </select>
            </div>
          </div>

          <div v-else>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('firewall.ipAddress') }}
            </label>
            <VInput
              v-model="quickForm.ip"
              placeholder="192.168.1.1"
              required
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('firewall.comment') }}
            </label>
            <VInput
              v-model="quickForm.comment"
              :placeholder="$t('firewall.commentPlaceholder')"
            />
          </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showQuickModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="executingQuick">
            {{ $t('common.confirm') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Delete Confirmation -->
    <VConfirmDialog
      v-model="showDeleteConfirm"
      :title="$t('firewall.deleteRule')"
      :message="$t('firewall.deleteRuleConfirm')"
      :loading="deleting"
      type="danger"
      @confirm="deleteRule"
    />

    <!-- Reset Confirmation -->
    <VConfirmDialog
      v-model="showResetConfirm"
      :title="$t('firewall.resetToDefaults')"
      :message="$t('firewall.resetConfirm')"
      :loading="resetting"
      type="danger"
      @confirm="resetFirewall"
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
  ShieldCheckIcon,
  ShieldExclamationIcon,
  ExclamationTriangleIcon,
  ArrowDownIcon,
  ArrowUpIcon,
  ListBulletIcon,
  PlusIcon,
  TrashIcon,
  ArrowPathIcon,
  LockClosedIcon
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

// State
const loading = ref(true)
const rules = ref([])
const firewallEnabled = ref(false)
const policies = ref({
  incoming: 'deny',
  outgoing: 'allow'
})

// Modal states
const showAddModal = ref(false)
const showQuickModal = ref(false)
const showDeleteConfirm = ref(false)
const showResetConfirm = ref(false)

// Action states
const togglingFirewall = ref(false)
const togglingRule = ref(null)
const adding = ref(false)
const deleting = ref(false)
const resetting = ref(false)
const executingQuick = ref(false)

// Form data
const ruleForm = ref({
  action: 'allow',
  direction: 'in',
  protocol: 'any',
  port: '',
  source_ip: '',
  comment: ''
})

const quickActionType = ref('')
const quickForm = ref({
  ip: '',
  port: '',
  protocol: 'tcp',
  comment: ''
})

const selectedRule = ref(null)

// Computed
const quickActionTitle = computed(() => {
  switch (quickActionType.value) {
    case 'allowPort':
      return t('firewall.quickAllowPort')
    case 'blockIp':
      return t('firewall.quickBlockIp')
    case 'allowIp':
      return t('firewall.quickAllowIp')
    default:
      return ''
  }
})

// Methods
function getActionVariant(action) {
  switch (action) {
    case 'allow':
      return 'success'
    case 'deny':
    case 'reject':
      return 'danger'
    case 'limit':
      return 'warning'
    default:
      return 'secondary'
  }
}

async function fetchStatus() {
  try {
    const response = await api.get('/firewall/status')
    firewallEnabled.value = response.data.data.enabled
    policies.value = response.data.data.default_policies || {}
  } catch (err) {
    console.error('Failed to fetch firewall status:', err)
  }
}

async function fetchRules() {
  loading.value = true
  try {
    const response = await api.get('/firewall/rules')
    rules.value = response.data.data || []
  } catch (err) {
    console.error('Failed to fetch firewall rules:', err)
  } finally {
    loading.value = false
  }
}

async function toggleFirewall() {
  togglingFirewall.value = true
  try {
    const endpoint = firewallEnabled.value ? '/firewall/disable' : '/firewall/enable'
    await api.post(endpoint)
    firewallEnabled.value = !firewallEnabled.value
    appStore.showToast({
      type: 'success',
      message: firewallEnabled.value ? t('firewall.enabledSuccess') : t('firewall.disabledSuccess')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('firewall.toggleError')
    })
  } finally {
    togglingFirewall.value = false
  }
}

async function toggleRule(rule) {
  togglingRule.value = rule.id
  try {
    const response = await api.put(`/firewall/rules/${rule.id}/toggle`)
    const index = rules.value.findIndex(r => r.id === rule.id)
    if (index !== -1) {
      rules.value[index] = response.data.data
    }
    appStore.showToast({
      type: 'success',
      message: response.data.data.is_active ? t('firewall.ruleActivated') : t('firewall.ruleDeactivated')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('firewall.toggleRuleError')
    })
  } finally {
    togglingRule.value = null
  }
}

function openAddModal() {
  ruleForm.value = {
    action: 'allow',
    direction: 'in',
    protocol: 'any',
    port: '',
    source_ip: '',
    comment: ''
  }
  showAddModal.value = true
}

async function addRule() {
  adding.value = true
  try {
    const response = await api.post('/firewall/rules', ruleForm.value)
    rules.value.push(response.data.data)
    showAddModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('firewall.ruleAdded')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('firewall.addRuleError')
    })
  } finally {
    adding.value = false
  }
}

function confirmDelete(rule) {
  selectedRule.value = rule
  showDeleteConfirm.value = true
}

async function deleteRule() {
  if (!selectedRule.value) return
  deleting.value = true
  try {
    await api.delete(`/firewall/rules/${selectedRule.value.id}`)
    rules.value = rules.value.filter(r => r.id !== selectedRule.value.id)
    showDeleteConfirm.value = false
    appStore.showToast({
      type: 'success',
      message: t('firewall.ruleDeleted')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('firewall.deleteRuleError')
    })
  } finally {
    deleting.value = false
  }
}

function confirmReset() {
  showResetConfirm.value = true
}

async function resetFirewall() {
  resetting.value = true
  try {
    await api.post('/firewall/reset')
    showResetConfirm.value = false
    appStore.showToast({
      type: 'success',
      message: t('firewall.resetSuccess')
    })
    await fetchRules()
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('firewall.resetError')
    })
  } finally {
    resetting.value = false
  }
}

function openQuickAction(type) {
  quickActionType.value = type
  quickForm.value = {
    ip: '',
    port: '',
    protocol: 'tcp',
    comment: ''
  }
  showQuickModal.value = true
}

async function executeQuickAction() {
  executingQuick.value = true
  try {
    let endpoint = ''
    let payload = {}

    switch (quickActionType.value) {
      case 'allowPort':
        endpoint = '/firewall/rules'
        payload = {
          action: 'allow',
          direction: 'in',
          protocol: quickForm.value.protocol,
          port: quickForm.value.port,
          comment: quickForm.value.comment || `Allow port ${quickForm.value.port}`
        }
        break
      case 'blockIp':
        endpoint = '/firewall/block-ip'
        payload = {
          ip: quickForm.value.ip,
          comment: quickForm.value.comment
        }
        break
      case 'allowIp':
        endpoint = '/firewall/allow-ip'
        payload = {
          ip: quickForm.value.ip,
          comment: quickForm.value.comment
        }
        break
    }

    const response = await api.post(endpoint, payload)
    if (response.data.data) {
      rules.value.push(response.data.data)
    }
    showQuickModal.value = false
    appStore.showToast({
      type: 'success',
      message: t('firewall.ruleAdded')
    })
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('firewall.addRuleError')
    })
  } finally {
    executingQuick.value = false
  }
}

// Lifecycle
onMounted(async () => {
  await Promise.all([
    fetchStatus(),
    fetchRules()
  ])
})
</script>
