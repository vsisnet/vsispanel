<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $t('reseller.title') }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('reseller.description') }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
      <nav class="flex space-x-4">
        <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id"
          :class="['px-4 py-2.5 text-sm font-medium border-b-2 transition-colors',
            activeTab === tab.id
              ? 'border-primary-500 text-primary-600 dark:text-primary-400'
              : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300']">
          {{ tab.label }}
        </button>
      </nav>
    </div>

    <!-- Customers Tab -->
    <div v-if="activeTab === 'customers'">
      <!-- Overview Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <VCard v-for="stat in overviewStats" :key="stat.label" class="p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ stat.label }}</p>
          <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ stat.value }}</p>
        </VCard>
      </div>

      <VCard>
        <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t('reseller.customers') }}</h3>
          <VButton @click="showCustomerForm = true">
            <PlusIcon class="w-4 h-4 mr-1" /> {{ $t('reseller.addCustomer') }}
          </VButton>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.name') }}</th>
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.email') }}</th>
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.username') }}</th>
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.plan') }}</th>
                <th class="text-center py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.status') }}</th>
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.created') }}</th>
                <th class="text-right py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="customer in customers" :key="customer.id" class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                <td class="py-3 px-4 text-gray-900 dark:text-white font-medium">{{ customer.name }}</td>
                <td class="py-3 px-4 text-gray-600 dark:text-gray-300">{{ customer.email }}</td>
                <td class="py-3 px-4 text-gray-600 dark:text-gray-300 font-mono text-xs">{{ customer.username }}</td>
                <td class="py-3 px-4 text-gray-600 dark:text-gray-300">
                  {{ customer.subscriptions?.[0]?.plan?.name || '--' }}
                </td>
                <td class="py-3 px-4 text-center">
                  <span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium', statusClass(customer.status)]">
                    {{ customer.status }}
                  </span>
                </td>
                <td class="py-3 px-4 text-xs text-gray-500">{{ formatDate(customer.created_at) }}</td>
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button v-if="customer.status === 'active'" @click="suspendCustomer(customer)"
                      class="p-1.5 text-yellow-500 hover:bg-yellow-50 dark:hover:bg-yellow-900/20 rounded" :title="$t('reseller.suspend')">
                      <PauseIcon class="w-4 h-4" />
                    </button>
                    <button v-if="customer.status === 'suspended'" @click="unsuspendCustomer(customer)"
                      class="p-1.5 text-green-500 hover:bg-green-50 dark:hover:bg-green-900/20 rounded" :title="$t('reseller.unsuspend')">
                      <PlayIcon class="w-4 h-4" />
                    </button>
                    <button @click="impersonateCustomer(customer)"
                      class="p-1.5 text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded" :title="$t('reseller.impersonate')">
                      <UserIcon class="w-4 h-4" />
                    </button>
                    <button @click="confirmTerminate(customer)"
                      class="p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded" :title="$t('reseller.terminate')">
                      <TrashIcon class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="customers.length === 0">
                <td colspan="7" class="text-center py-8 text-gray-400">{{ $t('reseller.noCustomers') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </VCard>
    </div>

    <!-- Branding Tab -->
    <div v-if="activeTab === 'branding'">
      <VCard class="p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">{{ $t('reseller.brandingSettings') }}</h3>
        <form @submit.prevent="saveBranding" class="space-y-4 max-w-2xl">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('reseller.companyName') }}</label>
            <input v-model="brandingForm.company_name" type="text"
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('reseller.primaryColor') }}</label>
            <div class="flex items-center gap-3">
              <input v-model="brandingForm.primary_color" type="color" class="w-10 h-10 rounded border border-gray-300 cursor-pointer" />
              <input v-model="brandingForm.primary_color" type="text" class="w-32 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-mono text-sm" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('reseller.supportEmail') }}</label>
            <input v-model="brandingForm.support_email" type="email"
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('reseller.supportUrl') }}</label>
            <input v-model="brandingForm.support_url" type="url" placeholder="https://support.example.com"
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('reseller.nameservers') }}</label>
            <div class="space-y-2">
              <div v-for="(ns, i) in brandingForm.nameservers" :key="i" class="flex gap-2">
                <input v-model="brandingForm.nameservers[i]" type="text" placeholder="ns1.example.com"
                  class="flex-1 border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-mono text-sm" />
                <button type="button" @click="brandingForm.nameservers.splice(i, 1)" class="text-red-500 hover:text-red-700 p-2">
                  <XMarkIcon class="w-4 h-4" />
                </button>
              </div>
              <button v-if="brandingForm.nameservers.length < 4" type="button" @click="brandingForm.nameservers.push('')"
                class="text-sm text-primary-600 hover:text-primary-700">
                + {{ $t('reseller.addNameserver') }}
              </button>
            </div>
          </div>
          <div class="pt-2">
            <VButton type="submit" :loading="savingBranding">{{ $t('common.save') }}</VButton>
          </div>
        </form>
      </VCard>
    </div>

    <!-- Reports Tab -->
    <div v-if="activeTab === 'reports'">
      <!-- Growth Chart -->
      <VCard class="p-4 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t('reseller.customerGrowth') }}</h3>
          <select v-model="growthPeriod" @change="loadGrowth"
            class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
            <option value="3m">3 {{ $t('reseller.months') }}</option>
            <option value="6m">6 {{ $t('reseller.months') }}</option>
            <option value="12m">12 {{ $t('reseller.months') }}</option>
          </select>
        </div>
        <apexchart v-if="growthChartOptions" type="bar" height="280" :options="growthChartOptions" :series="growthChartSeries" />
      </VCard>

      <!-- Customer Breakdown -->
      <VCard>
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t('reseller.customerBreakdown') }}</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-700">
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.name') }}</th>
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.email') }}</th>
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.plan') }}</th>
                <th class="text-center py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.domains') }}</th>
                <th class="text-center py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.status') }}</th>
                <th class="text-left py-3 px-4 text-gray-500 dark:text-gray-400 font-medium">{{ $t('reseller.created') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in breakdownCustomers" :key="c.id" class="border-b border-gray-100 dark:border-gray-800">
                <td class="py-3 px-4 text-gray-900 dark:text-white">{{ c.name }}</td>
                <td class="py-3 px-4 text-gray-600 dark:text-gray-300">{{ c.email }}</td>
                <td class="py-3 px-4 text-gray-600 dark:text-gray-300">{{ c.plan }}</td>
                <td class="py-3 px-4 text-center text-gray-600 dark:text-gray-300">{{ c.domains }}</td>
                <td class="py-3 px-4 text-center">
                  <span :class="['inline-flex px-2 py-0.5 text-xs rounded-full font-medium', statusClass(c.status)]">
                    {{ c.status }}
                  </span>
                </td>
                <td class="py-3 px-4 text-xs text-gray-500">{{ formatDate(c.created_at) }}</td>
              </tr>
              <tr v-if="breakdownCustomers.length === 0">
                <td colspan="6" class="text-center py-8 text-gray-400">{{ $t('reseller.noCustomers') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </VCard>
    </div>

    <!-- Create Customer Modal -->
    <VModal v-model="showCustomerForm" :title="$t('reseller.addCustomer')" size="lg">
      <form @submit.prevent="createCustomer" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('reseller.name') }} *</label>
            <input v-model="customerForm.name" type="text" required
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('reseller.username') }} *</label>
            <input v-model="customerForm.username" type="text" required pattern="[a-zA-Z0-9_-]+"
              class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white font-mono" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('reseller.email') }} *</label>
          <input v-model="customerForm.email" type="email" required
            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('reseller.password') }} *</label>
          <input v-model="customerForm.password" type="password" required minlength="8"
            class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-900 dark:text-white" />
        </div>
      </form>
      <template #footer>
        <div class="flex justify-end gap-3">
          <VButton variant="secondary" @click="showCustomerForm = false">{{ $t('common.cancel') }}</VButton>
          <VButton @click="createCustomer" :loading="creatingCustomer">{{ $t('common.save') }}</VButton>
        </div>
      </template>
    </VModal>

    <!-- Confirm Terminate Dialog -->
    <VConfirmDialog
      v-model="showTerminateConfirm"
      :title="$t('reseller.terminateCustomer')"
      :message="$t('reseller.terminateConfirm')"
      variant="danger"
      @confirm="doTerminate"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import { useAuthStore } from '@/stores/auth'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VModal from '@/components/ui/VModal.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import {
  PlusIcon,
  PauseIcon,
  PlayIcon,
  UserIcon,
  TrashIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()
const authStore = useAuthStore()

const activeTab = ref('customers')
const tabs = computed(() => [
  { id: 'customers', label: t('reseller.customers') },
  { id: 'branding', label: t('reseller.branding') },
  { id: 'reports', label: t('reseller.reports') },
])

// Customers
const customers = ref([])
const overview = ref({ total_customers: 0, active_customers: 0, suspended_customers: 0, terminated_customers: 0 })
const showCustomerForm = ref(false)
const creatingCustomer = ref(false)
const customerForm = ref({ name: '', email: '', username: '', password: '' })
const showTerminateConfirm = ref(false)
const terminatingCustomer = ref(null)

const overviewStats = computed(() => [
  { label: t('reseller.totalCustomers'), value: overview.value.total_customers },
  { label: t('reseller.activeCustomers'), value: overview.value.active_customers },
  { label: t('reseller.suspendedCustomers'), value: overview.value.suspended_customers },
  { label: t('reseller.terminatedCustomers'), value: overview.value.terminated_customers },
])

// Branding
const brandingForm = reactive({
  company_name: '',
  primary_color: '#1A5276',
  support_email: '',
  support_url: '',
  nameservers: [''],
})
const savingBranding = ref(false)

// Reports
const growthPeriod = ref('12m')
const growthData = ref([])
const breakdownCustomers = ref([])
const growthChartOptions = ref(null)
const growthChartSeries = ref([])

function statusClass(status) {
  return {
    active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    suspended: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    terminated: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  }[status] || 'bg-gray-100 text-gray-700'
}

function formatDate(d) {
  if (!d) return '--'
  return new Date(d).toLocaleDateString()
}

async function loadCustomers() {
  try {
    const { data } = await api.get('/reseller/customers')
    if (data.success) customers.value = data.data
  } catch (e) { /* interceptor */ }
}

async function loadOverview() {
  try {
    const { data } = await api.get('/reseller/reports/overview')
    if (data.success) overview.value = data.data
  } catch (e) { /* interceptor */ }
}

async function createCustomer() {
  creatingCustomer.value = true
  try {
    await api.post('/reseller/customers', customerForm.value)
    appStore.showToast({ type: 'success', message: t('reseller.customerCreated') })
    showCustomerForm.value = false
    customerForm.value = { name: '', email: '', username: '', password: '' }
    await loadCustomers()
    await loadOverview()
  } catch (e) { /* interceptor */ }
  creatingCustomer.value = false
}

async function suspendCustomer(customer) {
  try {
    await api.post(`/reseller/customers/${customer.id}/suspend`)
    appStore.showToast({ type: 'success', message: t('reseller.customerSuspended') })
    await loadCustomers()
    await loadOverview()
  } catch (e) { /* interceptor */ }
}

async function unsuspendCustomer(customer) {
  try {
    await api.post(`/reseller/customers/${customer.id}/unsuspend`)
    appStore.showToast({ type: 'success', message: t('reseller.customerUnsuspended') })
    await loadCustomers()
    await loadOverview()
  } catch (e) { /* interceptor */ }
}

function confirmTerminate(customer) {
  terminatingCustomer.value = customer
  showTerminateConfirm.value = true
}

async function doTerminate() {
  if (!terminatingCustomer.value) return
  try {
    await api.post(`/reseller/customers/${terminatingCustomer.value.id}/terminate`)
    appStore.showToast({ type: 'success', message: t('reseller.customerTerminated') })
    await loadCustomers()
    await loadOverview()
  } catch (e) { /* interceptor */ }
  terminatingCustomer.value = null
}

async function impersonateCustomer(customer) {
  try {
    const { data } = await api.post(`/reseller/customers/${customer.id}/impersonate`)
    if (data.success) {
      // Store original token and switch to impersonation
      localStorage.setItem('vsispanel_original_token', authStore.token)
      authStore.token = data.data.token
      localStorage.setItem('vsispanel_token', data.data.token)
      await authStore.fetchUser()
      appStore.showToast({ type: 'info', message: data.message })
      window.location.href = '/dashboard'
    }
  } catch (e) { /* interceptor */ }
}

// Branding
async function loadBranding() {
  try {
    const { data } = await api.get('/reseller/branding')
    if (data.success && data.data) {
      Object.assign(brandingForm, {
        company_name: data.data.company_name || '',
        primary_color: data.data.primary_color || '#1A5276',
        support_email: data.data.support_email || '',
        support_url: data.data.support_url || '',
        nameservers: data.data.nameservers || [''],
      })
    }
  } catch (e) { /* interceptor */ }
}

async function saveBranding() {
  savingBranding.value = true
  try {
    const payload = { ...brandingForm }
    payload.nameservers = payload.nameservers.filter(ns => ns.trim())
    await api.put('/reseller/branding', payload)
    appStore.showToast({ type: 'success', message: t('common.saved') })
  } catch (e) { /* interceptor */ }
  savingBranding.value = false
}

// Reports
async function loadGrowth() {
  try {
    const { data } = await api.get('/reseller/reports/growth', { params: { period: growthPeriod.value } })
    if (data.success) {
      growthData.value = data.data
      updateGrowthChart()
    }
  } catch (e) { /* interceptor */ }
}

async function loadBreakdown() {
  try {
    const { data } = await api.get('/reseller/reports/customers')
    if (data.success) breakdownCustomers.value = data.data
  } catch (e) { /* interceptor */ }
}

function updateGrowthChart() {
  const isDark = document.documentElement.classList.contains('dark')

  growthChartOptions.value = {
    chart: { toolbar: { show: false }, background: 'transparent' },
    theme: { mode: isDark ? 'dark' : 'light' },
    xaxis: {
      categories: growthData.value.map(d => d.month),
    },
    yaxis: { min: 0 },
    colors: ['#1A5276'],
    dataLabels: { enabled: false },
    plotOptions: { bar: { borderRadius: 4 } },
  }
  growthChartSeries.value = [{
    name: t('reseller.newCustomers'),
    data: growthData.value.map(d => d.count),
  }]
}

onMounted(async () => {
  await Promise.all([loadCustomers(), loadOverview(), loadBranding(), loadGrowth(), loadBreakdown()])
})
</script>
