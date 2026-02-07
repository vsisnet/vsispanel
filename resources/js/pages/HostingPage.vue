<template>
  <div class="p-6">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        {{ $t('hosting.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ $t('hosting.description') }}
      </p>
    </div>

    <!-- Tabs -->
    <div class="mb-6">
      <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
          <button
            @click="activeTab = 'plans'"
            :class="[
              activeTab === 'plans'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
            ]"
          >
            {{ $t('hosting.plans') }}
          </button>
          <button
            @click="activeTab = 'subscriptions'"
            :class="[
              activeTab === 'subscriptions'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
            ]"
          >
            {{ $t('hosting.subscriptions') }}
          </button>
        </nav>
      </div>
    </div>

    <!-- Plans Tab -->
    <div v-if="activeTab === 'plans'">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('hosting.plansTitle') }}</h3>
          <button
            @click="openPlanModal()"
            class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
          >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ $t('hosting.addPlan') }}
          </button>
        </div>

        <div v-if="isLoadingPlans" class="p-6 text-center">
          <svg class="animate-spin mx-auto h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <div v-else-if="plans.length === 0" class="p-6 text-center">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $t('hosting.noPlans') }}</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('hosting.noPlansDesc') }}</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('hosting.planName') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('hosting.limits') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('hosting.subscribers') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('common.status') }}</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="plan in plans" :key="plan.id">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ plan.name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ plan.slug }}</div>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div class="flex flex-wrap gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                      {{ formatLimit(plan.limits.disk) }} {{ $t('hosting.disk') }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                      {{ plan.limits.domains }} {{ $t('hosting.domains') }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300">
                      {{ plan.limits.databases }} {{ $t('hosting.databases') }}
                    </span>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ plan.subscriptions_count || 0 }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="[
                    plan.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium'
                  ]">
                    {{ plan.is_active ? $t('common.active') : $t('common.inactive') }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <button
                      @click="openPlanModal(plan)"
                      class="text-primary-600 hover:text-primary-900 dark:text-primary-400"
                    >
                      {{ $t('common.edit') }}
                    </button>
                    <button
                      v-if="plan.is_active"
                      @click="togglePlanStatus(plan, false)"
                      :disabled="isPerformingAction"
                      class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 disabled:opacity-50"
                    >
                      {{ $t('hosting.deactivate') }}
                    </button>
                    <button
                      v-else
                      @click="togglePlanStatus(plan, true)"
                      :disabled="isPerformingAction"
                      class="text-green-600 hover:text-green-900 dark:text-green-400 disabled:opacity-50"
                    >
                      {{ $t('hosting.activate') }}
                    </button>
                    <button
                      @click="clonePlan(plan)"
                      :disabled="isPerformingAction"
                      class="text-gray-600 hover:text-gray-900 dark:text-gray-400 disabled:opacity-50"
                    >
                      {{ $t('hosting.clone') }}
                    </button>
                    <button
                      @click="deletePlan(plan)"
                      :disabled="isPerformingAction || (plan.subscriptions_count && plan.subscriptions_count > 0)"
                      class="text-red-600 hover:text-red-900 dark:text-red-400 disabled:opacity-50"
                      :title="plan.subscriptions_count > 0 ? $t('hosting.cannotDeleteWithSubscribers') : ''"
                    >
                      {{ $t('common.delete') }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Subscriptions Tab -->
    <div v-if="activeTab === 'subscriptions'">
      <!-- Statistics -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('hosting.totalSubscriptions') }}</p>
          <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ subscriptionStats.total || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('hosting.activeSubscriptions') }}</p>
          <p class="mt-1 text-2xl font-semibold text-green-600">{{ subscriptionStats.active || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('hosting.suspendedSubscriptions') }}</p>
          <p class="mt-1 text-2xl font-semibold text-yellow-600">{{ subscriptionStats.suspended || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('hosting.expiredSubscriptions') }}</p>
          <p class="mt-1 text-2xl font-semibold text-red-600">{{ subscriptionStats.expired || 0 }}</p>
        </div>
      </div>

      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('hosting.subscriptionsTitle') }}</h3>
          <button
            @click="openSubscriptionModal()"
            class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
          >
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            {{ $t('hosting.addSubscription') }}
          </button>
        </div>

        <div v-if="isLoadingSubscriptions" class="p-6 text-center">
          <svg class="animate-spin mx-auto h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
        </div>

        <div v-else-if="subscriptions.length === 0" class="p-6 text-center">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">{{ $t('hosting.noSubscriptions') }}</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('hosting.noSubscriptionsDesc') }}</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('hosting.user') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('hosting.plan') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('common.status') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('hosting.expiresAt') }}</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('common.actions') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="subscription in subscriptions" :key="subscription.id">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="flex items-center">
                    <div class="flex-shrink-0 h-8 w-8 bg-primary-600 rounded-full flex items-center justify-center">
                      <span class="text-white text-sm font-medium">{{ getUserInitials(subscription.user) }}</span>
                    </div>
                    <div class="ml-3">
                      <div class="text-sm font-medium text-gray-900 dark:text-white">{{ subscription.user?.name }}</div>
                      <div class="text-xs text-gray-500 dark:text-gray-400">{{ subscription.user?.email }}</div>
                    </div>
                  </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  {{ subscription.plan?.name }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getSubscriptionStatusClass(subscription.status)">
                    {{ getSubscriptionStatusLabel(subscription.status) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ subscription.expires_at ? formatDate(subscription.expires_at) : $t('hosting.noExpiry') }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <div class="flex justify-end space-x-2">
                    <button
                      v-if="subscription.status === 'active'"
                      @click="suspendSubscription(subscription)"
                      :disabled="isPerformingAction"
                      class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 disabled:opacity-50"
                    >
                      {{ $t('hosting.suspend') }}
                    </button>
                    <button
                      v-if="subscription.status === 'suspended'"
                      @click="unsuspendSubscription(subscription)"
                      :disabled="isPerformingAction"
                      class="text-green-600 hover:text-green-900 dark:text-green-400 disabled:opacity-50"
                    >
                      {{ $t('hosting.unsuspend') }}
                    </button>
                    <button
                      @click="openChangePlanModal(subscription)"
                      :disabled="isPerformingAction"
                      class="text-primary-600 hover:text-primary-900 dark:text-primary-400 disabled:opacity-50"
                    >
                      {{ $t('hosting.changePlan') }}
                    </button>
                    <button
                      v-if="subscription.status !== 'cancelled'"
                      @click="cancelSubscription(subscription)"
                      :disabled="isPerformingAction"
                      class="text-red-600 hover:text-red-900 dark:text-red-400 disabled:opacity-50"
                    >
                      {{ $t('hosting.cancel') }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div v-if="subscriptionsMeta.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('common.showingOf', { from: (subscriptionsMeta.current_page - 1) * subscriptionsMeta.per_page + 1, to: Math.min(subscriptionsMeta.current_page * subscriptionsMeta.per_page, subscriptionsMeta.total), total: subscriptionsMeta.total }) }}
            </div>
            <div class="flex space-x-2">
              <button
                @click="fetchSubscriptions(subscriptionsMeta.current_page - 1)"
                :disabled="subscriptionsMeta.current_page <= 1"
                class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ $t('common.previous') }}
              </button>
              <span class="px-3 py-1 text-sm text-gray-700 dark:text-gray-300">
                {{ subscriptionsMeta.current_page }} / {{ subscriptionsMeta.last_page }}
              </span>
              <button
                @click="fetchSubscriptions(subscriptionsMeta.current_page + 1)"
                :disabled="subscriptionsMeta.current_page >= subscriptionsMeta.last_page"
                class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {{ $t('common.next') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Plan Modal -->
    <div v-if="showPlanModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showPlanModal = false"></div>
        <div class="relative inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
          <div>
            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
              {{ editingPlan ? $t('hosting.editPlan') : $t('hosting.addPlan') }}
            </h3>
            <div class="mt-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.planName') }} *</label>
                <input
                  v-model="planForm.name"
                  type="text"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.description') }}</label>
                <textarea
                  v-model="planForm.description"
                  rows="2"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                ></textarea>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.diskLimit') }} (MB)</label>
                  <input
                    v-model.number="planForm.disk_limit"
                    type="number"
                    min="0"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                  <p class="mt-1 text-xs text-gray-500">0 = {{ $t('hosting.unlimited') }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.bandwidthLimit') }} (MB)</label>
                  <input
                    v-model.number="planForm.bandwidth_limit"
                    type="number"
                    min="0"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                  <p class="mt-1 text-xs text-gray-500">0 = {{ $t('hosting.unlimited') }}</p>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.domainsLimit') }}</label>
                  <input
                    v-model.number="planForm.domains_limit"
                    type="number"
                    min="0"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.subdomainsLimit') }}</label>
                  <input
                    v-model.number="planForm.subdomains_limit"
                    type="number"
                    min="0"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                </div>
              </div>
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.databasesLimit') }}</label>
                  <input
                    v-model.number="planForm.databases_limit"
                    type="number"
                    min="0"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.emailLimit') }}</label>
                  <input
                    v-model.number="planForm.email_accounts_limit"
                    type="number"
                    min="0"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.ftpLimit') }}</label>
                  <input
                    v-model.number="planForm.ftp_accounts_limit"
                    type="number"
                    min="0"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                </div>
              </div>
              <div class="flex items-center">
                <input
                  v-model="planForm.is_active"
                  type="checkbox"
                  class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                />
                <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('hosting.activePlan') }}</label>
              </div>
            </div>
          </div>
          <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
            <button
              @click="savePlan"
              :disabled="isPerformingAction || !planForm.name"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:col-start-2 sm:text-sm disabled:opacity-50"
            >
              {{ isPerformingAction ? $t('common.loading') : $t('common.save') }}
            </button>
            <button
              @click="showPlanModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:col-start-1 sm:text-sm"
            >
              {{ $t('common.cancel') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Subscription Modal -->
    <div v-if="showSubscriptionModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showSubscriptionModal = false"></div>
        <div class="relative inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
          <div>
            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">{{ $t('hosting.addSubscription') }}</h3>
            <div class="mt-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.selectUser') }} *</label>
                <select
                  v-model="subscriptionForm.user_id"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="">{{ $t('hosting.selectUser') }}</option>
                  <option v-for="user in users" :key="user.id" :value="user.id">
                    {{ user.name }} ({{ user.email }})
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.selectPlan') }} *</label>
                <select
                  v-model="subscriptionForm.plan_id"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                >
                  <option value="">{{ $t('hosting.selectPlan') }}</option>
                  <option v-for="plan in activePlans" :key="plan.id" :value="plan.id">
                    {{ plan.name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.expiresAt') }}</label>
                <input
                  v-model="subscriptionForm.expires_at"
                  type="date"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                />
                <p class="mt-1 text-xs text-gray-500">{{ $t('hosting.leaveEmptyForNoExpiry') }}</p>
              </div>
            </div>
          </div>
          <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
            <button
              @click="saveSubscription"
              :disabled="isPerformingAction || !subscriptionForm.user_id || !subscriptionForm.plan_id"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:col-start-2 sm:text-sm disabled:opacity-50"
            >
              {{ isPerformingAction ? $t('common.loading') : $t('common.save') }}
            </button>
            <button
              @click="showSubscriptionModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:col-start-1 sm:text-sm"
            >
              {{ $t('common.cancel') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Change Plan Modal -->
    <div v-if="showChangePlanModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showChangePlanModal = false"></div>
        <div class="relative inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
          <div>
            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">{{ $t('hosting.changePlan') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
              {{ $t('hosting.currentPlan') }}: {{ selectedSubscription?.plan?.name }}
            </p>
            <div class="mt-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('hosting.newPlan') }} *</label>
              <select
                v-model="newPlanId"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              >
                <option value="">{{ $t('hosting.selectPlan') }}</option>
                <option v-for="plan in activePlans" :key="plan.id" :value="plan.id" :disabled="plan.id === selectedSubscription?.plan?.id">
                  {{ plan.name }} {{ plan.id === selectedSubscription?.plan?.id ? '(' + $t('hosting.current') + ')' : '' }}
                </option>
              </select>
            </div>
          </div>
          <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
            <button
              @click="changePlan"
              :disabled="isPerformingAction || !newPlanId"
              class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:col-start-2 sm:text-sm disabled:opacity-50"
            >
              {{ isPerformingAction ? $t('common.loading') : $t('hosting.changePlan') }}
            </button>
            <button
              @click="showChangePlanModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:col-start-1 sm:text-sm"
            >
              {{ $t('common.cancel') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import { useAppStore } from '@/stores/app'

const { t } = useI18n()
const appStore = useAppStore()

const showToast = (message, type = 'info') => {
  appStore.showToast({ type, message })
}

const activeTab = ref('plans')

// Plans state
const plans = ref([])
const isLoadingPlans = ref(false)
const showPlanModal = ref(false)
const editingPlan = ref(null)
const planForm = ref({
  name: '',
  description: '',
  disk_limit: 1024,
  bandwidth_limit: 10240,
  domains_limit: 5,
  subdomains_limit: 10,
  databases_limit: 5,
  email_accounts_limit: 10,
  ftp_accounts_limit: 5,
  is_active: true
})

// Subscriptions state
const subscriptions = ref([])
const subscriptionStats = ref({})
const subscriptionsMeta = ref({
  current_page: 1,
  per_page: 15,
  total: 0,
  last_page: 1
})
const isLoadingSubscriptions = ref(false)
const showSubscriptionModal = ref(false)
const showChangePlanModal = ref(false)
const selectedSubscription = ref(null)
const newPlanId = ref('')
const subscriptionForm = ref({
  user_id: '',
  plan_id: '',
  expires_at: ''
})

// Users for subscription creation
const users = ref([])

const isPerformingAction = ref(false)

const activePlans = computed(() => plans.value.filter(p => p.is_active))

const fetchPlans = async () => {
  isLoadingPlans.value = true
  try {
    const response = await api.get('/plans')
    if (response.data.success !== false) {
      plans.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch plans:', error)
  } finally {
    isLoadingPlans.value = false
  }
}

const fetchSubscriptions = async (page = 1) => {
  isLoadingSubscriptions.value = true
  try {
    const response = await api.get('/subscriptions', {
      params: { page, per_page: subscriptionsMeta.value.per_page }
    })
    if (response.data.success !== false) {
      subscriptions.value = response.data.data
      subscriptionsMeta.value = response.data.meta || subscriptionsMeta.value
    }
  } catch (error) {
    console.error('Failed to fetch subscriptions:', error)
  } finally {
    isLoadingSubscriptions.value = false
  }
}

const fetchSubscriptionStats = async () => {
  try {
    const response = await api.get('/subscriptions/statistics')
    if (response.data.success) {
      subscriptionStats.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch subscription stats:', error)
  }
}

const fetchUsers = async () => {
  try {
    const response = await api.get('/users')
    if (response.data.success !== false) {
      users.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch users:', error)
  }
}

const openPlanModal = (plan = null) => {
  editingPlan.value = plan
  if (plan) {
    planForm.value = {
      name: plan.name,
      description: plan.description || '',
      disk_limit: plan.limits.disk,
      bandwidth_limit: plan.limits.bandwidth,
      domains_limit: plan.limits.domains,
      subdomains_limit: plan.limits.subdomains,
      databases_limit: plan.limits.databases,
      email_accounts_limit: plan.limits.email_accounts,
      ftp_accounts_limit: plan.limits.ftp_accounts,
      is_active: plan.is_active
    }
  } else {
    planForm.value = {
      name: '',
      description: '',
      disk_limit: 1024,
      bandwidth_limit: 10240,
      domains_limit: 5,
      subdomains_limit: 10,
      databases_limit: 5,
      email_accounts_limit: 10,
      ftp_accounts_limit: 5,
      is_active: true
    }
  }
  showPlanModal.value = true
}

const savePlan = async () => {
  isPerformingAction.value = true
  try {
    let response
    if (editingPlan.value) {
      response = await api.put(`/plans/${editingPlan.value.id}`, planForm.value)
    } else {
      response = await api.post('/plans', planForm.value)
    }
    if (response.data.success) {
      showToast(editingPlan.value ? t('hosting.planUpdated') : t('hosting.planCreated'), 'success')
      showPlanModal.value = false
      await fetchPlans()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const togglePlanStatus = async (plan, activate) => {
  isPerformingAction.value = true
  try {
    const endpoint = activate ? `/plans/${plan.id}/activate` : `/plans/${plan.id}/deactivate`
    const response = await api.post(endpoint)
    if (response.data.success) {
      showToast(activate ? t('hosting.planActivated') : t('hosting.planDeactivated'), 'success')
      await fetchPlans()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const clonePlan = async (plan) => {
  const name = prompt(t('hosting.enterCloneName'), `${plan.name} (Copy)`)
  if (!name) return

  isPerformingAction.value = true
  try {
    const response = await api.post(`/plans/${plan.id}/clone`, { name })
    if (response.data.success) {
      showToast(t('hosting.planCloned'), 'success')
      await fetchPlans()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const deletePlan = async (plan) => {
  if (!confirm(t('hosting.confirmDeletePlan'))) return

  isPerformingAction.value = true
  try {
    const response = await api.delete(`/plans/${plan.id}`)
    if (response.data.success) {
      showToast(t('hosting.planDeleted'), 'success')
      await fetchPlans()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const openSubscriptionModal = () => {
  subscriptionForm.value = {
    user_id: '',
    plan_id: '',
    expires_at: ''
  }
  showSubscriptionModal.value = true
}

const saveSubscription = async () => {
  isPerformingAction.value = true
  try {
    const response = await api.post('/subscriptions', subscriptionForm.value)
    if (response.data.success) {
      showToast(t('hosting.subscriptionCreated'), 'success')
      showSubscriptionModal.value = false
      await fetchSubscriptions()
      await fetchSubscriptionStats()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const openChangePlanModal = (subscription) => {
  selectedSubscription.value = subscription
  newPlanId.value = ''
  showChangePlanModal.value = true
}

const changePlan = async () => {
  if (!selectedSubscription.value || !newPlanId.value) return

  isPerformingAction.value = true
  try {
    const response = await api.post(`/subscriptions/${selectedSubscription.value.id}/change-plan`, {
      plan_id: newPlanId.value
    })
    if (response.data.success) {
      showToast(t('hosting.planChanged'), 'success')
      showChangePlanModal.value = false
      await fetchSubscriptions()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const suspendSubscription = async (subscription) => {
  const reason = prompt(t('hosting.enterSuspensionReason'))
  if (reason === null) return

  isPerformingAction.value = true
  try {
    const response = await api.post(`/subscriptions/${subscription.id}/suspend`, { reason })
    if (response.data.success) {
      showToast(t('hosting.subscriptionSuspended'), 'success')
      await fetchSubscriptions()
      await fetchSubscriptionStats()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const unsuspendSubscription = async (subscription) => {
  isPerformingAction.value = true
  try {
    const response = await api.post(`/subscriptions/${subscription.id}/unsuspend`)
    if (response.data.success) {
      showToast(t('hosting.subscriptionUnsuspended'), 'success')
      await fetchSubscriptions()
      await fetchSubscriptionStats()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const cancelSubscription = async (subscription) => {
  if (!confirm(t('hosting.confirmCancelSubscription'))) return

  isPerformingAction.value = true
  try {
    const response = await api.post(`/subscriptions/${subscription.id}/cancel`)
    if (response.data.success) {
      showToast(t('hosting.subscriptionCancelled'), 'success')
      await fetchSubscriptions()
      await fetchSubscriptionStats()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const formatLimit = (limit) => {
  if (!limit || limit === 0) return t('hosting.unlimited')
  if (limit >= 1024) return `${(limit / 1024).toFixed(1)} GB`
  return `${limit} MB`
}

const formatDate = (date) => {
  return new Date(date).toLocaleDateString()
}

const getUserInitials = (user) => {
  if (!user?.name) return '?'
  return user.name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
}

const getSubscriptionStatusClass = (status) => {
  const classes = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    suspended: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    expired: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
  }
  return `${classes[status] || classes.expired} inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium`
}

const getSubscriptionStatusLabel = (status) => {
  const labels = {
    active: t('common.active'),
    suspended: t('common.suspended'),
    cancelled: t('hosting.cancelled'),
    expired: t('hosting.expired')
  }
  return labels[status] || status
}

watch(activeTab, (newTab) => {
  if (newTab === 'subscriptions' && subscriptions.value.length === 0) {
    fetchSubscriptions()
    fetchSubscriptionStats()
    fetchUsers()
  }
})

onMounted(() => {
  fetchPlans()
})
</script>
