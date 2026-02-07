<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('alerts.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('alerts.description') }}</p>
      </div>
      <div class="flex gap-2">
        <VButton variant="secondary" @click="activeTab = 'templates'">
          <DocumentDuplicateIcon class="w-4 h-4 mr-1" /> {{ $t('alerts.useTemplate') }}
        </VButton>
        <VButton @click="openAlertForm()">
          <PlusIcon class="w-4 h-4 mr-1" /> {{ $t('alerts.addAlert') }}
        </VButton>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <VCard v-for="stat in summaryCards" :key="stat.key" class="p-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ stat.label }}</p>
        <p class="text-2xl font-bold mt-1" :class="stat.color">{{ stat.value }}</p>
      </VCard>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
      <nav class="-mb-px flex space-x-6">
        <button v-for="tab in tabs" :key="tab.id" @click="switchTab(tab.id)"
          :class="['flex items-center gap-1.5 py-3 px-1 border-b-2 text-sm font-medium transition-colors',
            activeTab === tab.id
              ? 'border-primary-500 text-primary-600 dark:text-primary-400'
              : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300']">
          <component :is="tab.icon" class="w-4 h-4" />
          {{ $t(tab.label) }}
        </button>
      </nav>
    </div>

    <!-- Rules Tab -->
    <div v-if="activeTab === 'rules'">
      <!-- Category Filter -->
      <div class="flex flex-wrap gap-2 mb-4">
        <button v-for="cat in categories" :key="cat.value" @click="filterCategory = cat.value"
          :class="['px-3 py-1.5 text-xs rounded-full border transition-colors',
            filterCategory === cat.value
              ? 'bg-primary-100 border-primary-500 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
              : 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-gray-400']">
          {{ cat.label }}
        </button>
      </div>

      <!-- Rules List -->
      <div class="space-y-3">
        <VCard v-for="rule in filteredRules" :key="rule.id" class="p-4">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 flex-1 min-w-0">
              <button @click="toggleAlert(rule)" class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
                :class="rule.is_active ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600'">
                <span :class="['pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform', rule.is_active ? 'translate-x-4' : 'translate-x-0']" />
              </button>
              <span :class="['px-2 py-0.5 text-xs font-medium rounded-full', severityClass(rule.severity)]">
                {{ $t('alerts.' + rule.severity) }}
              </span>
              <span class="px-2 py-0.5 text-xs rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                {{ $t('alerts.' + rule.category) }}
              </span>
              <div class="min-w-0">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ rule.name }}</p>
                <p class="text-xs text-gray-500">
                  {{ $t('alerts.metrics.' + rule.metric) }}
                  {{ rule.condition }} {{ rule.threshold }}
                  <span v-if="rule.config?.service_name"> ({{ rule.config.service_name }})</span>
                  <span v-if="rule.config?.days_before"> ({{ rule.config.days_before }} {{ $t('alerts.days') }})</span>
                </p>
              </div>
            </div>
            <div class="flex items-center gap-3 ml-4">
              <span v-if="rule.last_triggered_at" class="text-xs text-gray-400">
                {{ $t('alerts.lastTriggered') }}: {{ formatDate(rule.last_triggered_at) }}
              </span>
              <div class="flex items-center gap-1">
                <span v-for="ch in (rule.notification_channels || [])" :key="ch"
                  class="text-xs px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
                  {{ ch }}
                </span>
              </div>
              <button @click="openAlertForm(rule)" class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <PencilIcon class="w-4 h-4" />
              </button>
              <button @click="confirmDelete(rule)" class="p-1.5 text-gray-400 hover:text-red-500">
                <TrashIcon class="w-4 h-4" />
              </button>
            </div>
          </div>
        </VCard>
      </div>
      <div v-if="filteredRules.length === 0" class="text-center py-12 text-gray-400">
        {{ $t('alerts.noRules') }}
      </div>
    </div>

    <!-- History Tab -->
    <div v-if="activeTab === 'history'">
      <div class="flex gap-3 mb-4">
        <select v-model="historyFilter.severity"
          class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
          <option value="">{{ $t('alerts.allSeverities') }}</option>
          <option value="info">Info</option>
          <option value="warning">Warning</option>
          <option value="critical">Critical</option>
        </select>
        <select v-model="historyFilter.status"
          class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
          <option value="">{{ $t('alerts.allStatuses') }}</option>
          <option value="triggered">{{ $t('alerts.triggered') }}</option>
          <option value="acknowledged">{{ $t('alerts.acknowledged') }}</option>
          <option value="resolved">{{ $t('alerts.resolved') }}</option>
        </select>
      </div>

      <VCard>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('alerts.time') }}</th>
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('alerts.severity') }}</th>
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('alerts.ruleName') }}</th>
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('alerts.message') }}</th>
                <th class="text-center py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('alerts.statusLabel') }}</th>
                <th class="text-right py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('alerts.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="h in filteredHistory" :key="h.id" class="border-b border-gray-100 dark:border-gray-800">
                <td class="py-3 px-4 text-gray-500 text-xs whitespace-nowrap">{{ formatDate(h.triggered_at) }}</td>
                <td class="py-3 px-4">
                  <span :class="['px-2 py-0.5 text-xs font-medium rounded-full', severityClass(h.severity)]">
                    {{ h.severity }}
                  </span>
                </td>
                <td class="py-3 px-4 text-gray-900 dark:text-white">{{ h.rule?.name || '--' }}</td>
                <td class="py-3 px-4 text-gray-600 dark:text-gray-400 max-w-[300px] truncate">{{ h.message }}</td>
                <td class="py-3 px-4 text-center">
                  <span :class="['px-2 py-0.5 text-xs rounded-full', statusClass(h.status)]">
                    {{ $t('alerts.' + h.status) }}
                  </span>
                </td>
                <td class="py-3 px-4 text-right">
                  <button v-if="h.status === 'triggered'" @click="acknowledgeAlert(h)" class="text-xs text-blue-600 hover:underline mr-2">
                    {{ $t('alerts.acknowledge') }}
                  </button>
                  <button v-if="h.status !== 'resolved'" @click="resolveAlert(h)" class="text-xs text-green-600 hover:underline">
                    {{ $t('alerts.resolve') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="filteredHistory.length === 0" class="text-center py-8 text-gray-400">
          {{ $t('alerts.noHistory') }}
        </div>
      </VCard>
    </div>

    <!-- Templates Tab -->
    <div v-if="activeTab === 'templates'">
      <div v-for="cat in templateCategories" :key="cat" class="mb-6">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3 uppercase tracking-wider">{{ $t('alerts.' + cat) }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
          <VCard v-for="tmpl in templatesByCategory(cat)" :key="tmpl.id" class="p-4">
            <div class="flex items-start justify-between">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <span :class="['px-2 py-0.5 text-xs font-medium rounded-full', severityClass(tmpl.severity)]">
                    {{ tmpl.severity }}
                  </span>
                  <p class="text-sm font-medium text-gray-900 dark:text-white">{{ tmpl.name }}</p>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ tmpl.description }}</p>
                <p class="text-xs text-gray-400 mt-2">
                  {{ $t('alerts.metrics.' + tmpl.metric) }} {{ tmpl.condition }} {{ tmpl.threshold }}
                </p>
              </div>
              <VButton size="sm" @click="activateTemplate(tmpl)">{{ $t('alerts.activate') }}</VButton>
            </div>
          </VCard>
        </div>
      </div>
      <div v-if="templates.length === 0" class="text-center py-12 text-gray-400">
        {{ $t('alerts.noTemplates') }}
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <VModal v-model="showForm" :title="editingRule ? $t('alerts.editAlert') : $t('alerts.addAlert')" size="lg">
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('alerts.name') }}</label>
          <input v-model="form.name" type="text" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('alerts.category') }}</label>
            <select v-model="form.category" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
              <option value="resource">{{ $t('alerts.resource') }}</option>
              <option value="service">{{ $t('alerts.service') }}</option>
              <option value="security">{{ $t('alerts.security') }}</option>
              <option value="backup">{{ $t('alerts.backup') }}</option>
              <option value="ssl">{{ $t('alerts.ssl') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('alerts.severity') }}</label>
            <select v-model="form.severity" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
              <option value="info">Info</option>
              <option value="warning">Warning</option>
              <option value="critical">Critical</option>
            </select>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('alerts.metric') }}</label>
          <select v-model="form.metric" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
            <optgroup :label="$t('alerts.resource')">
              <option value="cpu">{{ $t('alerts.metrics.cpu') }}</option>
              <option value="memory">{{ $t('alerts.metrics.memory') }}</option>
              <option value="disk">{{ $t('alerts.metrics.disk') }}</option>
            </optgroup>
            <optgroup :label="$t('alerts.service')">
              <option value="service_down">{{ $t('alerts.metrics.service_down') }}</option>
            </optgroup>
            <optgroup :label="$t('alerts.security')">
              <option value="ssh_brute_force">{{ $t('alerts.metrics.ssh_brute_force') }}</option>
              <option value="panel_intrusion">{{ $t('alerts.metrics.panel_intrusion') }}</option>
            </optgroup>
            <optgroup :label="$t('alerts.backup')">
              <option value="backup_failed">{{ $t('alerts.metrics.backup_failed') }}</option>
            </optgroup>
            <optgroup :label="$t('alerts.ssl')">
              <option value="ssl_expiry">{{ $t('alerts.metrics.ssl_expiry') }}</option>
            </optgroup>
          </select>
        </div>
        <!-- Conditional: service_name -->
        <div v-if="form.metric === 'service_down'">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('alerts.serviceName') }}</label>
          <select v-model="form.config.service_name" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
            <option v-for="svc in managedServices" :key="svc" :value="svc">{{ svc }}</option>
          </select>
        </div>
        <!-- Conditional: days_before -->
        <div v-if="form.metric === 'ssl_expiry'">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('alerts.daysBefore') }}</label>
          <input v-model.number="form.config.days_before" type="number" min="1" max="365"
            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
        </div>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('alerts.condition') }}</label>
            <select v-model="form.condition" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
              <option value="above">{{ $t('alerts.above') }}</option>
              <option value="below">{{ $t('alerts.below') }}</option>
              <option value="equals">{{ $t('alerts.equals') }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('alerts.threshold') }}</label>
            <input v-model.number="form.threshold" type="number" min="0" step="0.1"
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('alerts.cooldown') }}</label>
            <input v-model.number="form.cooldown_minutes" type="number" min="1" max="1440"
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('alerts.channels') }}</label>
          <div class="flex gap-4">
            <label v-for="ch in ['email', 'telegram', 'slack', 'discord']" :key="ch" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
              <input type="checkbox" :value="ch" v-model="form.notification_channels" class="rounded border-gray-300 dark:border-gray-600" />
              {{ ch }}
            </label>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <VButton variant="secondary" @click="showForm = false">{{ $t('common.cancel') }}</VButton>
          <VButton @click="saveAlert" :loading="saving">{{ editingRule ? $t('common.save') : $t('alerts.addAlert') }}</VButton>
        </div>
      </template>
    </VModal>

    <!-- Confirm Dialog -->
    <VConfirmDialog v-model="showConfirm" :title="$t('alerts.deleteAlert')" :message="$t('alerts.confirmDelete')"
      @confirm="deleteAlert" />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VModal from '@/components/ui/VModal.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  DocumentDuplicateIcon,
  ShieldExclamationIcon,
  BellAlertIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

const { t } = useI18n()

const tabs = [
  { id: 'rules', label: 'alerts.rules', icon: ShieldExclamationIcon },
  { id: 'history', label: 'alerts.history', icon: ClockIcon },
  { id: 'templates', label: 'alerts.templates', icon: DocumentDuplicateIcon },
]

const activeTab = ref('rules')
const alertRules = ref([])
const alertHistory = ref([])
const templates = ref([])
const summary = reactive({ total_rules: 0, active_rules: 0, unresolved_alerts: 0, critical_unresolved: 0, last_24h: 0 })
const filterCategory = ref('')
const historyFilter = reactive({ severity: '', status: '' })
const showForm = ref(false)
const editingRule = ref(null)
const saving = ref(false)
const showConfirm = ref(false)
let deleteTarget = null

const managedServices = ['nginx', 'mysql', 'redis-server', 'php8.3-fpm', 'postfix', 'dovecot', 'named', 'fail2ban', 'vsispanel-web', 'vsispanel-horizon']

const form = ref(getDefaultForm())

function getDefaultForm() {
  return {
    name: '', category: 'resource', severity: 'warning', metric: 'cpu',
    condition: 'above', threshold: 90, cooldown_minutes: 15,
    notification_channels: ['email'], config: {},
  }
}

const categories = computed(() => [
  { value: '', label: t('alerts.allCategories') },
  { value: 'resource', label: t('alerts.resource') },
  { value: 'service', label: t('alerts.service') },
  { value: 'security', label: t('alerts.security') },
  { value: 'backup', label: t('alerts.backup') },
  { value: 'ssl', label: t('alerts.ssl') },
])

const summaryCards = computed(() => [
  { key: 'total', label: t('alerts.totalRules'), value: summary.total_rules, color: 'text-gray-900 dark:text-white' },
  { key: 'active', label: t('alerts.activeAlerts'), value: summary.unresolved_alerts, color: summary.unresolved_alerts > 0 ? 'text-yellow-600' : 'text-gray-900 dark:text-white' },
  { key: 'critical', label: t('alerts.criticalAlerts'), value: summary.critical_unresolved, color: summary.critical_unresolved > 0 ? 'text-red-600' : 'text-gray-900 dark:text-white' },
  { key: 'last24h', label: t('alerts.last24h'), value: summary.last_24h, color: 'text-gray-900 dark:text-white' },
])

const filteredRules = computed(() => {
  if (!filterCategory.value) return alertRules.value
  return alertRules.value.filter(r => r.category === filterCategory.value)
})

const filteredHistory = computed(() => {
  return alertHistory.value.filter(h => {
    if (historyFilter.severity && h.severity !== historyFilter.severity) return false
    if (historyFilter.status && h.status !== historyFilter.status) return false
    return true
  })
})

const templateCategories = computed(() => {
  return [...new Set(templates.value.map(t => t.category))]
})

function templatesByCategory(cat) {
  return templates.value.filter(t => t.category === cat)
}

function severityClass(sev) {
  return {
    info: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    warning: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    critical: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  }[sev] || 'bg-gray-100 text-gray-700'
}

function statusClass(status) {
  return {
    triggered: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    acknowledged: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    resolved: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  }[status] || 'bg-gray-100 text-gray-700'
}

function formatDate(d) {
  if (!d) return '--'
  return new Date(d).toLocaleString()
}

function openAlertForm(rule = null) {
  editingRule.value = rule
  if (rule) {
    form.value = {
      name: rule.name,
      category: rule.category || 'resource',
      severity: rule.severity || 'warning',
      metric: rule.metric,
      condition: rule.condition,
      threshold: rule.threshold,
      cooldown_minutes: rule.cooldown_minutes,
      notification_channels: rule.notification_channels || ['email'],
      config: rule.config || {},
    }
  } else {
    form.value = getDefaultForm()
  }
  showForm.value = true
}

async function saveAlert() {
  saving.value = true
  try {
    if (editingRule.value) {
      await api.put(`/monitoring/alerts/${editingRule.value.id}`, form.value)
    } else {
      await api.post('/monitoring/alerts', form.value)
    }
    showForm.value = false
    loadData()
  } catch (e) { /* interceptor */ }
  saving.value = false
}

async function toggleAlert(rule) {
  try {
    await api.post(`/monitoring/alerts/${rule.id}/toggle`)
    loadRules()
  } catch (e) { /* interceptor */ }
}

function confirmDelete(rule) {
  deleteTarget = rule
  showConfirm.value = true
}

async function deleteAlert() {
  if (!deleteTarget) return
  try {
    await api.delete(`/monitoring/alerts/${deleteTarget.id}`)
    loadData()
  } catch (e) { /* interceptor */ }
  deleteTarget = null
}

async function acknowledgeAlert(h) {
  try {
    await api.post(`/monitoring/alerts/history/${h.id}/acknowledge`)
    loadHistory()
    loadSummary()
  } catch (e) { /* interceptor */ }
}

async function resolveAlert(h) {
  try {
    await api.post(`/monitoring/alerts/history/${h.id}/resolve`)
    loadHistory()
    loadSummary()
  } catch (e) { /* interceptor */ }
}

async function activateTemplate(tmpl) {
  try {
    await api.post(`/monitoring/alerts/from-template/${tmpl.id}`, {
      notification_channels: ['email'],
    })
    loadData()
    activeTab.value = 'rules'
  } catch (e) { /* interceptor */ }
}

async function loadSummary() {
  try {
    const { data } = await api.get('/monitoring/alerts/summary')
    if (data.success) Object.assign(summary, data.data)
  } catch (e) { /* interceptor */ }
}

async function loadRules() {
  try {
    const { data } = await api.get('/monitoring/alerts')
    if (data.success) alertRules.value = data.data
  } catch (e) { /* interceptor */ }
}

async function loadHistory() {
  try {
    const { data } = await api.get('/monitoring/alerts/history')
    if (data.success) alertHistory.value = data.data
  } catch (e) { /* interceptor */ }
}

async function loadTemplates() {
  try {
    const { data } = await api.get('/monitoring/alerts/templates')
    if (data.success) templates.value = data.data
  } catch (e) { /* interceptor */ }
}

function loadData() {
  loadSummary()
  loadRules()
  loadHistory()
}

function switchTab(id) {
  activeTab.value = id
  if (id === 'templates' && templates.value.length === 0) loadTemplates()
}

onMounted(() => {
  loadData()
  loadTemplates()
})
</script>
