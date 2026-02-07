<template>
  <div class="p-6">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        {{ $t('security.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ $t('security.description') }}
      </p>
    </div>

    <!-- Tabs -->
    <div class="mb-6">
      <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
          <button
            @click="activeTab = 'overview'"
            :class="[
              activeTab === 'overview'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
            ]"
          >
            {{ $t('security.overview') }}
          </button>
          <button
            @click="activeTab = 'audit'"
            :class="[
              activeTab === 'audit'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
            ]"
          >
            {{ $t('security.auditLog') }}
          </button>
          <button
            @click="activeTab = 'fail2ban'"
            :class="[
              activeTab === 'fail2ban'
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300',
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm'
            ]"
          >
            {{ $t('security.fail2banTab') }}
          </button>
        </nav>
      </div>
    </div>

    <!-- Overview Tab -->
    <div v-if="activeTab === 'overview'">
      <!-- Security Score Card -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-1">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('security.securityScore') }}</h3>
              <button
                @click="recalculateScore"
                :disabled="isRecalculating"
                class="text-sm text-primary-600 hover:text-primary-700 dark:text-primary-400"
              >
                {{ isRecalculating ? $t('security.recalculating') : $t('security.recalculate') }}
              </button>
            </div>

            <div class="flex items-center justify-center mb-4">
              <div class="relative">
                <svg class="w-32 h-32 transform -rotate-90">
                  <circle
                    cx="64"
                    cy="64"
                    r="56"
                    stroke="currentColor"
                    stroke-width="8"
                    fill="none"
                    class="text-gray-200 dark:text-gray-700"
                  />
                  <circle
                    cx="64"
                    cy="64"
                    r="56"
                    stroke="currentColor"
                    stroke-width="8"
                    fill="none"
                    :stroke-dasharray="scoreCircumference"
                    :stroke-dashoffset="scoreOffset"
                    :class="scoreColor"
                    stroke-linecap="round"
                  />
                </svg>
                <div class="absolute inset-0 flex items-center justify-center flex-col">
                  <span class="text-3xl font-bold" :class="scoreTextColor">{{ scoreData.score }}</span>
                  <span class="text-lg font-semibold" :class="scoreTextColor">{{ scoreData.grade }}</span>
                </div>
              </div>
            </div>

            <p class="text-center text-sm text-gray-500 dark:text-gray-400">{{ $t('security.scoreDescription') }}</p>
            <p v-if="scoreData.calculated_at" class="text-center text-xs text-gray-400 dark:text-gray-500 mt-2">
              {{ $t('security.lastCalculated') }}: {{ formatDate(scoreData.calculated_at) }}
            </p>
          </div>
        </div>

        <!-- Activity Stats -->
        <div class="lg:col-span-2">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('security.activityStats') }}</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">{{ $t('security.last30Days') }}</p>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
              <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ activityStats.total_activities }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('security.totalActivities') }}</p>
              </div>
              <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ activityStats.login_attempts }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('security.loginAttempts') }}</p>
              </div>
              <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">{{ activityStats.failed_logins }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('security.failedLogins') }}</p>
              </div>
              <div class="text-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ activityStats.security_events }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('security.securityEvents') }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Security Checks -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('security.checksTitle') }}</h3>
          </div>
          <div class="divide-y divide-gray-200 dark:divide-gray-700">
            <div v-for="(check, key) in scoreData.checks" :key="key" class="px-4 py-4">
              <div class="flex items-center justify-between">
                <div class="flex items-center">
                  <div :class="[
                    check.passed ? 'bg-green-100 dark:bg-green-900' : 'bg-yellow-100 dark:bg-yellow-900',
                    'flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center'
                  ]">
                    <svg v-if="check.passed" class="w-5 h-5 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    <svg v-else class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <div class="ml-3">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ check.name }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      {{ check.passed ? $t('security.checkPassed') : $t('security.checkFailed') }}
                    </p>
                  </div>
                </div>
                <div class="text-right">
                  <span :class="[
                    check.passed ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400',
                    'text-sm font-semibold'
                  ]">
                    {{ check.score }}/{{ check.max_score }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recommendations -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('security.recommendationsTitle') }}</h3>
          </div>
          <div v-if="scoreData.recommendations && scoreData.recommendations.length > 0" class="divide-y divide-gray-200 dark:divide-gray-700 max-h-96 overflow-y-auto">
            <div v-for="(rec, index) in scoreData.recommendations" :key="index" class="px-4 py-4">
              <div class="flex items-start">
                <span :class="[
                  getPriorityClass(rec.priority),
                  'inline-flex items-center px-2 py-0.5 rounded text-xs font-medium'
                ]">
                  {{ getPriorityLabel(rec.priority) }}
                </span>
              </div>
              <p class="mt-2 text-sm text-gray-900 dark:text-white">{{ rec.message }}</p>
              <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ rec.action }}</p>
            </div>
          </div>
          <div v-else class="p-6 text-center">
            <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t('security.noRecommendations') }}</p>
          </div>
        </div>
      </div>

      <!-- Recent Security Events -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('security.recentActivity') }}</h3>
        </div>
        <div v-if="recentEvents.length > 0" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.timestamp') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.user') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.action') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.ipAddress') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="event in recentEvents" :key="event.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(event.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  {{ event.user?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getSeverityClass(event.severity)">
                    {{ getActionLabel(event.action) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ event.ip_address || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="p-6 text-center text-gray-500 dark:text-gray-400">
          {{ $t('security.noActivity') }}
        </div>
      </div>
    </div>

    <!-- Audit Log Tab -->
    <div v-if="activeTab === 'audit'">
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
          <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('security.auditLogTitle') }}</h3>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('security.auditLogDescription') }}</p>
            </div>
            <div class="flex items-center space-x-2">
              <select
                v-model="filters.module"
                class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
              >
                <option value="">{{ $t('security.allModules') }}</option>
                <option v-for="mod in availableModules" :key="mod" :value="mod">{{ mod }}</option>
              </select>
              <select
                v-model="filters.action"
                class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
              >
                <option value="">{{ $t('security.allActions') }}</option>
                <option v-for="act in availableActions" :key="act" :value="act">{{ getActionLabel(act) }}</option>
              </select>
              <button
                @click="clearFilters"
                class="px-3 py-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
              >
                {{ $t('security.clearFilters') }}
              </button>
            </div>
          </div>
        </div>

        <div v-if="auditLogs.length > 0" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-700">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.timestamp') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.user') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.action') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.module') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.details') }}</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.ipAddress') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-for="log in auditLogs" :key="log.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ formatDate(log.created_at) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                  {{ log.user?.name || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span :class="getSeverityClass(log.severity)">
                    {{ getActionLabel(log.action) }}
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ log.module }}
                </td>
                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                  {{ log.description || '-' }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ log.ip_address || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="p-6 text-center text-gray-500 dark:text-gray-400">
          {{ $t('security.noLogs') }}
        </div>

        <!-- Pagination -->
        <div v-if="auditLogsMeta.total > auditLogsMeta.per_page" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <p class="text-sm text-gray-700 dark:text-gray-300">
              {{ $t('common.showing') }} {{ ((auditLogsMeta.current_page - 1) * auditLogsMeta.per_page) + 1 }}
              {{ $t('common.to') }} {{ Math.min(auditLogsMeta.current_page * auditLogsMeta.per_page, auditLogsMeta.total) }}
              {{ $t('common.of') }} {{ auditLogsMeta.total }}
            </p>
            <div class="flex space-x-2">
              <button
                @click="fetchAuditLogs(auditLogsMeta.current_page - 1)"
                :disabled="auditLogsMeta.current_page <= 1"
                class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50"
              >
                {{ $t('common.previous') }}
              </button>
              <button
                @click="fetchAuditLogs(auditLogsMeta.current_page + 1)"
                :disabled="auditLogsMeta.current_page >= auditLogsMeta.last_page"
                class="px-3 py-1 rounded border border-gray-300 dark:border-gray-600 disabled:opacity-50"
              >
                {{ $t('common.next') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Fail2Ban Tab -->
    <div v-if="activeTab === 'fail2ban'">
      <!-- Not Installed State -->
      <div v-if="!fail2banStatus.installed" class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
        <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">{{ $t('security.fail2banNotInstalled') }}</h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t('security.fail2banNotInstalledDesc') }}</p>
        <button
          @click="installFail2ban"
          :disabled="isInstalling"
          class="mt-4 inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50"
        >
          <svg v-if="isInstalling" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isInstalling ? $t('security.installing') : $t('security.installFail2ban') }}
        </button>
      </div>

      <!-- Installed State -->
      <div v-else>
        <!-- Status & Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <!-- Status Card -->
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('security.fail2banStatus') }}</p>
                <p class="mt-1 text-2xl font-semibold" :class="fail2banStatus.running ? 'text-green-600' : 'text-red-600'">
                  {{ fail2banStatus.running ? $t('security.fail2banRunning') : $t('security.fail2banStopped') }}
                </p>
              </div>
              <div :class="[fail2banStatus.running ? 'bg-green-100 dark:bg-green-900' : 'bg-red-100 dark:bg-red-900', 'p-3 rounded-full']">
                <svg class="h-6 w-6" :class="fail2banStatus.running ? 'text-green-600' : 'text-red-600'" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
            <div class="mt-4 flex space-x-2">
              <button
                v-if="!fail2banStatus.running"
                @click="startFail2ban"
                :disabled="isPerformingAction"
                class="flex-1 px-3 py-1.5 text-xs font-medium text-white bg-green-600 hover:bg-green-700 rounded disabled:opacity-50"
              >
                {{ $t('security.fail2banStart') }}
              </button>
              <button
                v-if="fail2banStatus.running"
                @click="stopFail2ban"
                :disabled="isPerformingAction"
                class="flex-1 px-3 py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded disabled:opacity-50"
              >
                {{ $t('security.fail2banStop') }}
              </button>
              <button
                @click="restartFail2ban"
                :disabled="isPerformingAction || !fail2banStatus.running"
                class="flex-1 px-3 py-1.5 text-xs font-medium text-white bg-yellow-600 hover:bg-yellow-700 rounded disabled:opacity-50"
              >
                {{ $t('security.fail2banRestart') }}
              </button>
            </div>
          </div>

          <!-- Active Jails -->
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('security.activeJails') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ fail2banStatus.jail_count || 0 }}</p>
              </div>
              <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-full">
                <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Banned IPs -->
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('security.totalBannedIps') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ bannedIpsMeta.total }}</p>
              </div>
              <div class="bg-red-100 dark:bg-red-900 p-3 rounded-full">
                <svg class="h-6 w-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Whitelisted IPs -->
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('security.whitelistedIps') }}</p>
                <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ whitelist.length }}</p>
              </div>
              <div class="bg-green-100 dark:bg-green-900 p-3 rounded-full">
                <svg class="h-6 w-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
              </div>
            </div>
          </div>

          <!-- Manual Ban Card -->
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">{{ $t('security.manualBan') }}</p>
            <div class="space-y-2">
              <input
                v-model="manualBanIp"
                type="text"
                :placeholder="$t('security.enterIpAddress')"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              />
              <select
                v-model="manualBanJail"
                class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              >
                <option value="">{{ $t('security.selectJail') }}</option>
                <option v-for="jail in jails" :key="jail.name" :value="jail.name">{{ jail.name }}</option>
              </select>
              <button
                @click="banIp"
                :disabled="!manualBanIp || !manualBanJail || isPerformingAction"
                class="w-full px-3 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded disabled:opacity-50"
              >
                {{ $t('security.banIp') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Jails Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('security.jailsTitle') }}</h3>
            <button
              @click="showAddJailModal = true"
              class="inline-flex items-center px-3 py-1.5 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
            >
              <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              {{ $t('security.addJail') }}
            </button>
          </div>
          <div v-if="jails.length > 0" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.jailName') }}</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.jailStatus') }}</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.currentlyBanned') }}</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.totalBanned') }}</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.bantime') }}</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.maxretry') }}</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('common.actions') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="jail in jails" :key="jail.name">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">{{ jail.name }}</td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span :class="[
                      jail.enabled ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
                      'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium'
                    ]">
                      {{ jail.enabled ? $t('security.jailEnabled') : $t('security.jailDisabled') }}
                    </span>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ jail.currently_banned }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ jail.total_banned }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDuration(jail.bantime) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ jail.maxretry }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <div class="flex space-x-3">
                      <button
                        @click="openJailConfig(jail)"
                        :disabled="isPerformingAction"
                        class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300 disabled:opacity-50"
                      >
                        {{ $t('security.configureJail') }}
                      </button>
                      <button
                        v-if="jail.enabled"
                        @click="disableJail(jail.name)"
                        :disabled="isPerformingAction"
                        class="text-yellow-600 hover:text-yellow-900 dark:text-yellow-400 dark:hover:text-yellow-300 disabled:opacity-50"
                      >
                        {{ $t('security.disable') }}
                      </button>
                      <button
                        v-else
                        @click="enableJail(jail.name)"
                        :disabled="isPerformingAction"
                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 disabled:opacity-50"
                      >
                        {{ $t('security.enable') }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-else class="p-6 text-center text-gray-500 dark:text-gray-400">
            {{ $t('security.noJailsDesc') }}
          </div>
        </div>

        <!-- Whitelist Section -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('security.whitelistTitle') }}</h3>
          </div>
          <div class="p-4">
            <div class="flex space-x-2 mb-4">
              <input
                v-model="whitelistIp"
                type="text"
                :placeholder="$t('security.enterIpOrCidr')"
                class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
              />
              <button
                @click="addToWhitelist"
                :disabled="!whitelistIp || isPerformingAction"
                class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md disabled:opacity-50"
              >
                {{ $t('security.addToWhitelist') }}
              </button>
            </div>
            <div v-if="whitelist.length > 0" class="divide-y divide-gray-200 dark:divide-gray-700">
              <div v-for="ip in whitelist" :key="ip" class="flex items-center justify-between py-2">
                <span class="text-sm font-mono text-gray-900 dark:text-white">{{ ip }}</span>
                <button
                  v-if="ip !== '127.0.0.1/8' && ip !== '::1'"
                  @click="removeFromWhitelist(ip)"
                  :disabled="isPerformingAction"
                  class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 text-sm disabled:opacity-50"
                >
                  {{ $t('common.remove') }}
                </button>
                <span v-else class="text-xs text-gray-400">{{ $t('security.systemDefault') }}</span>
              </div>
            </div>
            <div v-else class="text-center py-4 text-gray-500 dark:text-gray-400">
              {{ $t('security.noWhitelistedIps') }}
            </div>
          </div>
        </div>

        <!-- Banned IPs Table -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
          <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('security.bannedIpsTitle') }}</h3>
              <div class="flex items-center space-x-3">
                <div class="relative">
                  <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <input
                    v-model="bannedIpSearch"
                    type="text"
                    :placeholder="$t('security.searchIp')"
                    class="pl-9 pr-3 py-1.5 text-sm border border-gray-300 rounded-md focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white w-48"
                  />
                </div>
                <div class="flex items-center space-x-2">
                  <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t('common.perPage') }}:</span>
                  <select
                    v-model="bannedIpsMeta.per_page"
                    @change="fetchBannedIps(1)"
                    class="text-sm border border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  >
                    <option :value="10">10</option>
                    <option :value="20">20</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
          <div v-if="filteredBannedIps.length > 0" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.ip') }}</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.jail') }}</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('security.bantime') }}</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">{{ $t('common.actions') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <tr v-for="(ban, index) in filteredBannedIps" :key="`${ban.ip}-${ban.jail}-${index}`">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-white">{{ ban.ip }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ ban.jail }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ formatDuration(ban.bantime) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <button
                      @click="unbanIp(ban.ip, ban.jail)"
                      :disabled="isPerformingAction"
                      class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                    >
                      {{ $t('security.unbanIp') }}
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
            <!-- Pagination -->
            <div v-if="bannedIpsMeta.last_page > 1" class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
              <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ $t('common.showingOf', { from: (bannedIpsMeta.current_page - 1) * bannedIpsMeta.per_page + 1, to: Math.min(bannedIpsMeta.current_page * bannedIpsMeta.per_page, bannedIpsMeta.total), total: bannedIpsMeta.total }) }}
              </div>
              <div class="flex space-x-2">
                <button
                  @click="fetchBannedIps(bannedIpsMeta.current_page - 1)"
                  :disabled="bannedIpsMeta.current_page <= 1"
                  class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ $t('common.previous') }}
                </button>
                <span class="px-3 py-1 text-sm text-gray-700 dark:text-gray-300">
                  {{ bannedIpsMeta.current_page }} / {{ bannedIpsMeta.last_page }}
                </span>
                <button
                  @click="fetchBannedIps(bannedIpsMeta.current_page + 1)"
                  :disabled="bannedIpsMeta.current_page >= bannedIpsMeta.last_page"
                  class="px-3 py-1 text-sm border border-gray-300 rounded-md hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {{ $t('common.next') }}
                </button>
              </div>
            </div>
          </div>
          <div v-else class="p-6 text-center">
            <template v-if="bannedIpSearch && bannedIps.length > 0">
              <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t('security.noSearchResults') }}</p>
            </template>
            <template v-else>
              <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t('security.noBannedIpsDesc') }}</p>
            </template>
          </div>
        </div>
      </div>

      <!-- Jail Config Modal -->
      <div v-if="showJailConfigModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
          <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showJailConfigModal = false"></div>
          <div class="relative inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div>
              <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">{{ $t('security.jailConfig') }}: {{ selectedJail?.name }}</h3>
              <div class="mt-4 space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.bantime') }} ({{ $t('security.seconds') }})</label>
                  <input
                    v-model.number="jailConfigForm.bantime"
                    type="number"
                    min="60"
                    max="604800"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.findtime') }} ({{ $t('security.seconds') }})</label>
                  <input
                    v-model.number="jailConfigForm.findtime"
                    type="number"
                    min="60"
                    max="604800"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.maxretry') }}</label>
                  <input
                    v-model.number="jailConfigForm.maxretry"
                    type="number"
                    min="1"
                    max="100"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  />
                </div>
              </div>
            </div>
            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
              <button
                @click="saveJailConfig"
                :disabled="isPerformingAction"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:col-start-2 sm:text-sm disabled:opacity-50"
              >
                {{ $t('common.save') }}
              </button>
              <button
                @click="showJailConfigModal = false"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:col-start-1 sm:text-sm"
              >
                {{ $t('common.cancel') }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Add Jail Modal -->
      <div v-if="showAddJailModal" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
          <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" @click="showAddJailModal = false"></div>
          <div class="relative inline-block px-4 pt-5 pb-4 overflow-hidden text-left align-bottom transition-all transform bg-white dark:bg-gray-800 rounded-lg shadow-xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <div>
              <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white">{{ $t('security.addNewJail') }}</h3>
              <div class="mt-4 space-y-4">
                <!-- Jail Preset Selector -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.jailPreset') }} *</label>
                  <select
                    v-model="selectedJailPreset"
                    @change="applyJailPreset"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                  >
                    <option value="">{{ $t('security.selectJailPreset') }}</option>
                    <option v-for="preset in jailPresets" :key="preset.name" :value="preset.name">
                      {{ preset.label }}
                    </option>
                    <option value="custom">{{ $t('security.customJail') }}</option>
                  </select>
                  <p v-if="selectedJailPreset && selectedJailPreset !== 'custom'" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ currentPresetDescription }}
                  </p>
                </div>

                <!-- Custom fields (only shown for custom) -->
                <template v-if="selectedJailPreset === 'custom'">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.jailName') }} *</label>
                    <input
                      v-model="newJailForm.name"
                      type="text"
                      :placeholder="$t('security.jailNamePlaceholder')"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.port') }} *</label>
                    <input
                      v-model="newJailForm.port"
                      type="text"
                      :placeholder="$t('security.portPlaceholder')"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.filter') }}</label>
                    <input
                      v-model="newJailForm.filter"
                      type="text"
                      :placeholder="$t('security.filterPlaceholder')"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.logpath') }} *</label>
                    <input
                      v-model="newJailForm.logpath"
                      type="text"
                      :placeholder="$t('security.logpathPlaceholder')"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    />
                  </div>
                </template>

                <!-- Max Retry dropdown -->
                <div class="grid grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.maxretry') }}</label>
                    <select
                      v-model.number="newJailForm.maxretry"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    >
                      <option :value="3">3</option>
                      <option :value="5">5</option>
                      <option :value="10">10</option>
                      <option :value="15">15</option>
                      <option :value="20">20</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.findtime') }}</label>
                    <select
                      v-model.number="newJailForm.findtime"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    >
                      <option :value="300">5 {{ $t('security.minutes') }}</option>
                      <option :value="600">10 {{ $t('security.minutes') }}</option>
                      <option :value="1800">30 {{ $t('security.minutes') }}</option>
                      <option :value="3600">1 {{ $t('security.hours') }}</option>
                      <option :value="86400">24 {{ $t('security.hours') }}</option>
                    </select>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('security.bantime') }}</label>
                    <select
                      v-model.number="newJailForm.bantime"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                    >
                      <option :value="600">10 {{ $t('security.minutes') }}</option>
                      <option :value="1800">30 {{ $t('security.minutes') }}</option>
                      <option :value="3600">1 {{ $t('security.hours') }}</option>
                      <option :value="86400">24 {{ $t('security.hours') }}</option>
                      <option :value="604800">7 {{ $t('security.days') }}</option>
                      <option :value="-1">{{ $t('security.permanent') }}</option>
                    </select>
                  </div>
                </div>

                <div class="flex items-center">
                  <input
                    v-model="newJailForm.enabled"
                    type="checkbox"
                    class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded dark:bg-gray-700 dark:border-gray-600"
                  />
                  <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $t('security.enableJailAfterCreate') }}</label>
                </div>
              </div>
            </div>
            <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
              <button
                @click="createJail"
                :disabled="isPerformingAction || !isNewJailValid"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-primary-600 text-base font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:col-start-2 sm:text-sm disabled:opacity-50"
              >
                {{ $t('security.createJail') }}
              </button>
              <button
                @click="showAddJailModal = false"
                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-700 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 sm:mt-0 sm:col-start-1 sm:text-sm"
              >
                {{ $t('common.cancel') }}
              </button>
            </div>
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

const activeTab = ref('overview')
const isRecalculating = ref(false)

const scoreData = ref({
  score: 0,
  grade: '-',
  checks: {},
  recommendations: [],
  calculated_at: null
})

const activityStats = ref({
  total_activities: 0,
  login_attempts: 0,
  failed_logins: 0,
  security_events: 0
})

const recentEvents = ref([])
const auditLogs = ref([])
const auditLogsMeta = ref({
  current_page: 1,
  last_page: 1,
  per_page: 50,
  total: 0
})

const filters = ref({
  module: '',
  action: ''
})

const availableModules = ref([])
const availableActions = ref([])

// Fail2Ban state
const fail2banStatus = ref({
  installed: false,
  running: false,
  jail_count: 0,
  jails: []
})
const jails = ref([])
const availableJails = ref([])
const bannedIps = ref([])
const bannedIpsMeta = ref({
  current_page: 1,
  per_page: 20,
  total: 0,
  last_page: 1
})
const whitelist = ref([])
const isInstalling = ref(false)
const isPerformingAction = ref(false)
const manualBanIp = ref('')
const manualBanJail = ref('')
const showJailConfigModal = ref(false)
const showAddJailModal = ref(false)
const showWhitelistModal = ref(false)
const selectedJail = ref(null)
const bannedIpSearch = ref('')
const selectedJailPreset = ref('')
const whitelistIp = ref('')
const jailConfigForm = ref({
  bantime: 3600,
  findtime: 600,
  maxretry: 5
})
const newJailForm = ref({
  name: '',
  port: '',
  filter: '',
  logpath: '',
  maxretry: 5,
  findtime: 600,
  bantime: 3600,
  enabled: true
})

const scoreCircumference = 2 * Math.PI * 56
const scoreOffset = computed(() => {
  const progress = scoreData.value.score / 100
  return scoreCircumference - (progress * scoreCircumference)
})

const scoreColor = computed(() => {
  const score = scoreData.value.score
  if (score >= 80) return 'text-green-500'
  if (score >= 60) return 'text-yellow-500'
  if (score >= 40) return 'text-orange-500'
  return 'text-red-500'
})

const scoreTextColor = computed(() => {
  const score = scoreData.value.score
  if (score >= 80) return 'text-green-600 dark:text-green-400'
  if (score >= 60) return 'text-yellow-600 dark:text-yellow-400'
  if (score >= 40) return 'text-orange-600 dark:text-orange-400'
  return 'text-red-600 dark:text-red-400'
})

const fetchOverview = async () => {
  try {
    const response = await api.get('/security/overview')
    if (response.data.success) {
      scoreData.value = response.data.data.score
      activityStats.value = response.data.data.activity_stats
      recentEvents.value = response.data.data.recent_events
    }
  } catch (error) {
    console.error('Failed to fetch overview:', error)
  }
}

const recalculateScore = async () => {
  isRecalculating.value = true
  try {
    const response = await api.post('/security/score/recalculate')
    if (response.data.success) {
      scoreData.value = response.data.data
      showToast(t('security.scoreRecalculated'), 'success')
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isRecalculating.value = false
  }
}

const fetchAuditLogs = async (page = 1) => {
  try {
    const params = { page, per_page: 50 }
    if (filters.value.module) params.module = filters.value.module
    if (filters.value.action) params.action = filters.value.action

    const response = await api.get('/security/audit-logs', { params })
    if (response.data.success) {
      auditLogs.value = response.data.data
      auditLogsMeta.value = response.data.meta
    }
  } catch (error) {
    console.error('Failed to fetch audit logs:', error)
  }
}

const fetchFilterOptions = async () => {
  try {
    const [modulesRes, actionsRes] = await Promise.all([
      api.get('/security/audit-logs/modules'),
      api.get('/security/audit-logs/actions')
    ])
    if (modulesRes.data.success) availableModules.value = modulesRes.data.data
    if (actionsRes.data.success) availableActions.value = actionsRes.data.data
  } catch (error) {
    console.error('Failed to fetch filter options:', error)
  }
}

const clearFilters = () => {
  filters.value = { module: '', action: '' }
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString()
}

const getActionLabel = (action) => {
  const labels = {
    create: t('security.actionCreate'),
    update: t('security.actionUpdate'),
    delete: t('security.actionDelete'),
    login: t('security.actionLogin'),
    logout: t('security.actionLogout'),
    login_failed: t('security.actionLoginFailed'),
    password_reset: t('security.actionPasswordReset'),
    '2fa_enabled': t('security.action2faEnabled'),
    '2fa_disabled': t('security.action2faDisabled'),
    permission_change: t('security.actionPermissionChange'),
    settings_change: t('security.actionSettingsChange'),
    backup_created: t('security.actionBackupCreated'),
    backup_restored: t('security.actionBackupRestored'),
    service_start: t('security.actionServiceStart'),
    service_stop: t('security.actionServiceStop'),
    service_restart: t('security.actionServiceRestart'),
    firewall_change: t('security.actionFirewallChange'),
    ban_ip: t('security.actionBanIp'),
    unban_ip: t('security.actionUnbanIp')
  }
  return labels[action] || action
}

const getSeverityClass = (severity) => {
  const classes = {
    info: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
    success: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    warning: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    danger: 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
  }
  return classes[severity] || classes.info
}

const getPriorityClass = (priority) => {
  const classes = {
    1: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
    2: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
    3: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
    4: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
  }
  return classes[priority] || classes[4]
}

const getPriorityLabel = (priority) => {
  const labels = {
    1: t('security.priorityCritical'),
    2: t('security.priorityHigh'),
    3: t('security.priorityMedium'),
    4: t('security.priorityLow')
  }
  return labels[priority] || labels[4]
}

// Fail2Ban methods
const fetchFail2banStatus = async () => {
  try {
    const response = await api.get('/security/fail2ban/status')
    if (response.data.success) {
      fail2banStatus.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch Fail2Ban status:', error)
  }
}

const fetchJails = async () => {
  try {
    const response = await api.get('/security/fail2ban/jails')
    if (response.data.success) {
      jails.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch jails:', error)
  }
}

const fetchBannedIps = async (page = 1) => {
  try {
    const response = await api.get('/security/fail2ban/banned-ips', {
      params: { page, per_page: bannedIpsMeta.value.per_page }
    })
    if (response.data.success) {
      bannedIps.value = response.data.data
      bannedIpsMeta.value = response.data.meta
    }
  } catch (error) {
    console.error('Failed to fetch banned IPs:', error)
  }
}

const fetchWhitelist = async () => {
  try {
    const response = await api.get('/security/fail2ban/whitelist')
    if (response.data.success) {
      whitelist.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch whitelist:', error)
  }
}

const fetchAvailableJails = async () => {
  try {
    const response = await api.get('/security/fail2ban/jails/available')
    if (response.data.success) {
      availableJails.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch available jails:', error)
  }
}

const addToWhitelist = async () => {
  if (!whitelistIp.value) return
  isPerformingAction.value = true
  try {
    const response = await api.post('/security/fail2ban/whitelist', {
      ip: whitelistIp.value
    })
    if (response.data.success) {
      showToast(t('security.ipWhitelisted'), 'success')
      whitelistIp.value = ''
      await fetchWhitelist()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const removeFromWhitelist = async (ip) => {
  isPerformingAction.value = true
  try {
    const response = await api.delete('/security/fail2ban/whitelist', {
      data: { ip }
    })
    if (response.data.success) {
      showToast(t('security.ipRemovedFromWhitelist'), 'success')
      await fetchWhitelist()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const enableJail = async (jailName) => {
  isPerformingAction.value = true
  try {
    const response = await api.post(`/security/fail2ban/jails/${jailName}/enable`)
    if (response.data.success) {
      showToast(t('security.jailEnabled'), 'success')
      await fetchJails()
      await fetchAvailableJails()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const disableJail = async (jailName) => {
  isPerformingAction.value = true
  try {
    const response = await api.post(`/security/fail2ban/jails/${jailName}/disable`)
    if (response.data.success) {
      showToast(t('security.jailDisabled'), 'success')
      await fetchJails()
      await fetchAvailableJails()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const createJail = async () => {
  isPerformingAction.value = true
  try {
    const response = await api.post('/security/fail2ban/jails', newJailForm.value)
    if (response.data.success) {
      showToast(t('security.jailCreated'), 'success')
      showAddJailModal.value = false
      selectedJailPreset.value = ''
      newJailForm.value = {
        name: '',
        port: '',
        filter: '',
        logpath: '',
        maxretry: 5,
        findtime: 600,
        bantime: 3600,
        enabled: true
      }
      await fetchJails()
      await fetchAvailableJails()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const deleteJail = async (jailName) => {
  if (!confirm(t('security.confirmDeleteJail'))) return
  isPerformingAction.value = true
  try {
    const response = await api.delete(`/security/fail2ban/jails/${jailName}`)
    if (response.data.success) {
      showToast(t('security.jailDeleted'), 'success')
      await fetchJails()
      await fetchAvailableJails()
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const installFail2ban = async () => {
  isInstalling.value = true
  try {
    const response = await api.post('/security/fail2ban/install')
    if (response.data.success) {
      showToast(t('security.fail2banInstalled'), 'success')
      await fetchFail2banStatus()
      await fetchJails()
    } else {
      showToast(response.data.error?.message || t('security.fail2banInstallError'), 'error')
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('security.fail2banInstallError'), 'error')
  } finally {
    isInstalling.value = false
  }
}

const startFail2ban = async () => {
  isPerformingAction.value = true
  try {
    const response = await api.post('/security/fail2ban/start')
    if (response.data.success) {
      showToast(t('security.fail2banStarted'), 'success')
      await fetchFail2banStatus()
    } else {
      showToast(response.data.message || t('security.fail2banActionError'), 'error')
    }
  } catch (error) {
    showToast(t('security.fail2banActionError'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const stopFail2ban = async () => {
  isPerformingAction.value = true
  try {
    const response = await api.post('/security/fail2ban/stop')
    if (response.data.success) {
      showToast(t('security.fail2banStoppedSuccess'), 'success')
      await fetchFail2banStatus()
    } else {
      showToast(response.data.message || t('security.fail2banActionError'), 'error')
    }
  } catch (error) {
    showToast(t('security.fail2banActionError'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const restartFail2ban = async () => {
  isPerformingAction.value = true
  try {
    const response = await api.post('/security/fail2ban/restart')
    if (response.data.success) {
      showToast(t('security.fail2banRestarted'), 'success')
      await fetchFail2banStatus()
      await fetchJails()
    } else {
      showToast(response.data.error?.message || t('security.fail2banActionError'), 'error')
    }
  } catch (error) {
    showToast(t('security.fail2banActionError'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const banIp = async () => {
  if (!manualBanIp.value || !manualBanJail.value) return
  isPerformingAction.value = true
  try {
    const response = await api.post('/security/fail2ban/ban', {
      ip: manualBanIp.value,
      jail: manualBanJail.value
    })
    if (response.data.success) {
      showToast(t('security.ipBanned'), 'success')
      manualBanIp.value = ''
      manualBanJail.value = ''
      await fetchBannedIps()
      await fetchJails()
    } else {
      showToast(response.data.error?.message || t('security.banError'), 'error')
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('security.banError'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const unbanIp = async (ip, jail) => {
  isPerformingAction.value = true
  try {
    const response = await api.post('/security/fail2ban/unban', { ip, jail })
    if (response.data.success) {
      showToast(t('security.ipUnbanned'), 'success')
      await fetchBannedIps()
      await fetchJails()
    } else {
      showToast(response.data.error?.message || t('security.unbanError'), 'error')
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('security.unbanError'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const openJailConfig = (jail) => {
  selectedJail.value = jail
  jailConfigForm.value = {
    bantime: jail.bantime || 3600,
    findtime: jail.findtime || 600,
    maxretry: jail.maxretry || 5
  }
  showJailConfigModal.value = true
}

const saveJailConfig = async () => {
  if (!selectedJail.value) return
  isPerformingAction.value = true
  try {
    const response = await api.put(`/security/fail2ban/jails/${selectedJail.value.name}/config`, jailConfigForm.value)
    if (response.data.success) {
      showToast(t('security.jailConfigUpdated'), 'success')
      showJailConfigModal.value = false
      await fetchJails()
    } else {
      showToast(response.data.error?.message || t('security.jailConfigError'), 'error')
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('security.jailConfigError'), 'error')
  } finally {
    isPerformingAction.value = false
  }
}

const formatDuration = (seconds) => {
  if (!seconds) return '-'
  if (seconds === -1) return t('security.permanent')
  if (seconds < 60) return `${seconds}s`
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m`
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`
  return `${Math.floor(seconds / 86400)}d`
}

// Jail presets
const jailPresets = [
  {
    name: 'sshd',
    label: 'SSH (sshd)',
    description: t('security.presetSshDesc'),
    port: 'ssh',
    filter: 'sshd',
    logpath: '/var/log/auth.log',
    maxretry: 5,
    findtime: 600,
    bantime: 3600
  },
  {
    name: 'nginx-http-auth',
    label: 'Nginx HTTP Auth',
    description: t('security.presetNginxAuthDesc'),
    port: 'http,https',
    filter: 'nginx-http-auth',
    logpath: '/var/log/nginx/error.log',
    maxretry: 5,
    findtime: 600,
    bantime: 3600
  },
  {
    name: 'nginx-botsearch',
    label: 'Nginx Bot Search',
    description: t('security.presetNginxBotDesc'),
    port: 'http,https',
    filter: 'nginx-botsearch',
    logpath: '/var/log/nginx/access.log',
    maxretry: 10,
    findtime: 600,
    bantime: 86400
  },
  {
    name: 'nginx-limit-req',
    label: 'Nginx Rate Limit',
    description: t('security.presetNginxLimitDesc'),
    port: 'http,https',
    filter: 'nginx-limit-req',
    logpath: '/var/log/nginx/error.log',
    maxretry: 10,
    findtime: 600,
    bantime: 3600
  },
  {
    name: 'mysql-auth',
    label: 'MySQL Auth',
    description: t('security.presetMysqlDesc'),
    port: '3306',
    filter: 'mysqld-auth',
    logpath: '/var/log/mysql/error.log',
    maxretry: 5,
    findtime: 600,
    bantime: 3600
  },
  {
    name: 'postfix-auth',
    label: 'Postfix SMTP Auth',
    description: t('security.presetPostfixDesc'),
    port: 'smtp,465,submission',
    filter: 'postfix',
    logpath: '/var/log/mail.log',
    maxretry: 5,
    findtime: 600,
    bantime: 3600
  },
  {
    name: 'dovecot',
    label: 'Dovecot IMAP/POP3',
    description: t('security.presetDovecotDesc'),
    port: 'pop3,pop3s,imap,imaps',
    filter: 'dovecot',
    logpath: '/var/log/mail.log',
    maxretry: 5,
    findtime: 600,
    bantime: 3600
  }
]

const currentPresetDescription = computed(() => {
  const preset = jailPresets.find(p => p.name === selectedJailPreset.value)
  return preset?.description || ''
})

const filteredBannedIps = computed(() => {
  if (!bannedIpSearch.value) return bannedIps.value
  const search = bannedIpSearch.value.toLowerCase().trim()
  return bannedIps.value.filter(ban =>
    ban.ip.toLowerCase().includes(search) ||
    ban.jail.toLowerCase().includes(search)
  )
})

const isNewJailValid = computed(() => {
  if (selectedJailPreset.value === 'custom') {
    return newJailForm.value.name && newJailForm.value.port && newJailForm.value.logpath
  }
  return !!selectedJailPreset.value
})

const applyJailPreset = () => {
  if (selectedJailPreset.value === 'custom' || !selectedJailPreset.value) {
    newJailForm.value = {
      name: '',
      port: '',
      filter: '',
      logpath: '',
      maxretry: 5,
      findtime: 600,
      bantime: 3600,
      enabled: true
    }
    return
  }
  const preset = jailPresets.find(p => p.name === selectedJailPreset.value)
  if (preset) {
    newJailForm.value = {
      name: preset.name,
      port: preset.port,
      filter: preset.filter,
      logpath: preset.logpath,
      maxretry: preset.maxretry,
      findtime: preset.findtime,
      bantime: preset.bantime,
      enabled: true
    }
  }
}

watch([() => filters.value.module, () => filters.value.action], () => {
  fetchAuditLogs(1)
})

watch(activeTab, (newTab) => {
  if (newTab === 'audit' && auditLogs.value.length === 0) {
    fetchAuditLogs()
    fetchFilterOptions()
  }
  if (newTab === 'fail2ban') {
    fetchFail2banStatus()
    fetchJails()
    fetchBannedIps()
    fetchWhitelist()
    fetchAvailableJails()
  }
})

onMounted(() => {
  fetchOverview()
})
</script>
