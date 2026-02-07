<template>
  <div class="p-6">
    <!-- Header with Breadcrumb -->
    <div class="mb-6">
      <nav class="flex mb-2" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-1 md:space-x-3">
          <li class="inline-flex items-center">
            <router-link to="/backup" class="inline-flex items-center text-sm font-medium text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400">
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
              </svg>
              {{ $t('backup.title') }}
            </router-link>
          </li>
          <li>
            <div class="flex items-center">
              <svg class="w-5 h-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
              </svg>
              <span class="ml-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ isEditing ? $t('backup.editConfig') : $t('backup.addConfig') }}
              </span>
            </div>
          </li>
        </ol>
      </nav>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
        {{ isEditing ? $t('backup.editConfig') : $t('backup.addConfig') }}
      </h1>
      <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
        {{ $t('backup.configDescription') }}
      </p>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="flex justify-center py-12">
      <svg class="animate-spin h-8 w-8 text-primary-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
      </svg>
    </div>

    <!-- Form -->
    <form v-else @submit.prevent="saveConfig" class="space-y-6">
      <!-- Basic Information Card -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.basicInfo') }}</h3>
        </div>
        <div class="px-4 py-5 sm:p-6 space-y-4">
          <!-- Config Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.configName') }} *</label>
            <input
              v-model="configForm.name"
              type="text"
              :placeholder="$t('backup.configNamePlaceholder')"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
              required
            />
          </div>

          <!-- Backup Type - Full or Customize -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $t('backup.backupType') }}</label>
            <div class="grid grid-cols-2 gap-3">
              <!-- Full Backup Option -->
              <label
                :class="[
                  configForm.backup_mode === 'full'
                    ? 'border-primary-500 ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900'
                    : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                  'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none'
                ]"
              >
                <input type="radio" v-model="configForm.backup_mode" value="full" class="sr-only" />
                <div class="flex flex-col items-center text-center w-full">
                  <svg class="w-8 h-8 mb-2 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                  </svg>
                  <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.typeFull') }}</span>
                  <span class="block text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $t('backup.typeFullDesc') }}</span>
                </div>
              </label>

              <!-- Customize Option -->
              <label
                :class="[
                  configForm.backup_mode === 'customize'
                    ? 'border-primary-500 ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900'
                    : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                  'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none'
                ]"
              >
                <input type="radio" v-model="configForm.backup_mode" value="customize" class="sr-only" />
                <div class="flex flex-col items-center text-center w-full">
                  <svg class="w-8 h-8 mb-2 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                  </svg>
                  <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.typeCustomize') }}</span>
                  <span class="block text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $t('backup.typeCustomizeDesc') }}</span>
                </div>
              </label>
            </div>

            <!-- Customize Options (shown when customize is selected) -->
            <div v-if="configForm.backup_mode === 'customize'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ $t('backup.selectBackupItems') }}</label>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <label
                  v-for="item in customizeOptions"
                  :key="item.value"
                  :class="[
                    configForm.backup_items.includes(item.value)
                      ? 'border-primary-500 ring-2 ring-primary-500 bg-white dark:bg-gray-600'
                      : 'border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700',
                    'relative flex cursor-pointer rounded-lg border p-3 focus:outline-none'
                  ]"
                >
                  <input
                    type="checkbox"
                    :value="item.value"
                    v-model="configForm.backup_items"
                    class="sr-only"
                  />
                  <div class="flex items-center w-full">
                    <component :is="item.icon" class="w-5 h-5 mr-2 text-gray-600 dark:text-gray-400" />
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ item.label }}</span>
                    <svg v-if="configForm.backup_items.includes(item.value)" class="w-5 h-5 ml-auto text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                  </div>
                </label>
              </div>
              <p v-if="configForm.backup_mode === 'customize' && configForm.backup_items.length === 0" class="mt-2 text-sm text-red-600 dark:text-red-400">
                {{ $t('backup.selectAtLeastOne') }}
              </p>
            </div>
          </div>

          <!-- Status Toggle -->
          <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.activeConfig') }}</label>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.activeConfigHint') }}</p>
            </div>
            <button
              type="button"
              @click="configForm.is_active = !configForm.is_active"
              :class="[
                configForm.is_active ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600',
                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2'
              ]"
            >
              <span
                :class="[
                  configForm.is_active ? 'translate-x-5' : 'translate-x-0',
                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out'
                ]"
              />
            </button>
          </div>
        </div>
      </div>

      <!-- Destination Card -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.destination') }}</h3>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $t('backup.destinationMultipleHint') }}</p>
        </div>
        <div class="px-4 py-5 sm:p-6 space-y-4">
          <!-- Destination Selection - Multiple checkboxes -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ $t('backup.selectDestinations') }} *</label>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              <!-- Local Storage Option -->
              <label
                :class="[
                  configForm.destinations.includes('local')
                    ? 'border-primary-500 ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900'
                    : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                  'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none'
                ]"
              >
                <input
                  type="checkbox"
                  value="local"
                  v-model="configForm.destinations"
                  class="sr-only"
                />
                <div class="flex items-center w-full">
                  <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                  </div>
                  <div class="ml-3 flex-1">
                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ $t('backup.destinationLocal') }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $t('backup.localStorageDesc') }}</span>
                  </div>
                  <svg v-if="configForm.destinations.includes('local')" class="w-5 h-5 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                </div>
              </label>

              <!-- Connected Storage Remotes -->
              <label
                v-for="remote in connectedRemotes"
                :key="remote.id"
                :class="[
                  configForm.destinations.includes(`remote:${remote.id}`)
                    ? 'border-primary-500 ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-900'
                    : 'border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700',
                  'relative flex cursor-pointer rounded-lg border p-4 focus:outline-none'
                ]"
              >
                <input
                  type="checkbox"
                  :value="`remote:${remote.id}`"
                  v-model="configForm.destinations"
                  class="sr-only"
                />
                <div class="flex items-center w-full">
                  <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                  </div>
                  <div class="ml-3 flex-1">
                    <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ remote.display_name }}</span>
                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ remote.type_label }}</span>
                  </div>
                  <svg v-if="configForm.destinations.includes(`remote:${remote.id}`)" class="w-5 h-5 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                </div>
              </label>
            </div>

            <!-- No remotes warning -->
            <div v-if="connectedRemotes.length === 0" class="mt-3 p-3 bg-yellow-50 dark:bg-yellow-900 rounded-md">
              <p class="text-sm text-yellow-800 dark:text-yellow-200">
                {{ $t('backup.noConnectedRemotes') }}
                <router-link to="/backup?tab=remotes" class="font-medium underline">{{ $t('backup.configureRemotes') }}</router-link>
              </p>
            </div>

            <!-- Validation message -->
            <p v-if="configForm.destinations.length === 0" class="mt-2 text-sm text-red-600 dark:text-red-400">
              {{ $t('backup.selectAtLeastOneDestination') }}
            </p>
          </div>

          <!-- Local Path Config (shown when local is selected) -->
          <div v-if="configForm.destinations.includes('local')" class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.localPath') }}</label>
            <input
              v-model="configForm.destination_config.path"
              type="text"
              :placeholder="$t('backup.localPathPlaceholder')"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
            />
          </div>

          <!-- Repository Password -->
          <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t('backup.repositoryPassword') }} *</label>
            <input
              v-model="configForm.destination_config.password"
              type="password"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white sm:text-sm"
              :required="!isEditing"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('backup.repositoryPasswordHint') }}</p>
          </div>
        </div>
      </div>

      <!-- Schedule Card -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="px-4 py-5 sm:px-6 border-b border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $t('backup.scheduleAndRetention') }}</h3>
        </div>
        <div class="px-4 py-5 sm:p-6 space-y-6">
          <!-- Schedule Configuration -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">{{ $t('backup.schedule') }}</label>

            <div class="flex flex-wrap items-center gap-3">
              <!-- Schedule Type Dropdown -->
              <select
                v-model="configForm.schedule"
                class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
              >
                <option value="">{{ $t('backup.scheduleNone') }}</option>
                <option value="daily">{{ $t('backup.scheduleDaily') }}</option>
                <option value="n_days">{{ $t('backup.scheduleNDays') }}</option>
                <option value="hourly">{{ $t('backup.scheduleHourly') }}</option>
                <option value="n_hours">{{ $t('backup.scheduleNHours') }}</option>
                <option value="n_minutes">{{ $t('backup.scheduleNMinutes') }}</option>
                <option value="weekly">{{ $t('backup.scheduleWeekly') }}</option>
                <option value="monthly">{{ $t('backup.scheduleMonthly') }}</option>
                <option value="custom">{{ $t('backup.scheduleCustom') }}</option>
              </select>

              <!-- Interval Value (for n_days, n_hours, n_minutes) -->
              <template v-if="['n_days', 'n_hours', 'n_minutes'].includes(configForm.schedule)">
                <input
                  v-model.number="configForm.schedule_interval"
                  type="number"
                  min="1"
                  :max="configForm.schedule === 'n_minutes' ? 59 : (configForm.schedule === 'n_hours' ? 23 : 365)"
                  class="w-20 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
                />
                <span class="text-sm text-gray-600 dark:text-gray-400">
                  {{ configForm.schedule === 'n_days' ? $t('backup.days') : (configForm.schedule === 'n_hours' ? $t('backup.hours') : $t('backup.minutes')) }}
                </span>
              </template>

              <!-- Time Picker (for daily, n_days, weekly, monthly) -->
              <template v-if="['daily', 'n_days', 'weekly', 'monthly'].includes(configForm.schedule)">
                <input
                  v-model="configForm.schedule_time"
                  type="time"
                  class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
                />
              </template>

              <!-- Minute picker (for hourly, n_hours) -->
              <template v-if="['hourly', 'n_hours'].includes(configForm.schedule)">
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $t('backup.atMinute') }}</span>
                <input
                  v-model.number="configForm.schedule_minute"
                  type="number"
                  min="0"
                  max="59"
                  class="w-20 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
                />
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $t('backup.minutes') }}</span>
              </template>

              <!-- Day of Week (for weekly) -->
              <template v-if="configForm.schedule === 'weekly'">
                <select
                  v-model="configForm.schedule_day"
                  class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
                >
                  <option value="0">{{ $t('backup.sunday') }}</option>
                  <option value="1">{{ $t('backup.monday') }}</option>
                  <option value="2">{{ $t('backup.tuesday') }}</option>
                  <option value="3">{{ $t('backup.wednesday') }}</option>
                  <option value="4">{{ $t('backup.thursday') }}</option>
                  <option value="5">{{ $t('backup.friday') }}</option>
                  <option value="6">{{ $t('backup.saturday') }}</option>
                </select>
              </template>

              <!-- Day of Month (for monthly) -->
              <template v-if="configForm.schedule === 'monthly'">
                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $t('backup.onDay') }}</span>
                <select
                  v-model="configForm.schedule_day"
                  class="rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm"
                >
                  <option v-for="day in 28" :key="day" :value="day.toString()">{{ day }}</option>
                  <option value="last">{{ $t('backup.lastDay') }}</option>
                </select>
              </template>

              <!-- Schedule Summary -->
              <span v-if="configForm.schedule && configForm.schedule !== 'custom'" class="text-sm text-gray-500 dark:text-gray-400 flex items-center">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ scheduleSummary }}
              </span>

            </div>

            <!-- Custom Cron Expression -->
            <div v-if="configForm.schedule === 'custom'" class="mt-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">{{ $t('backup.cronExpression') }}</label>
              <input
                v-model="configForm.schedule_cron"
                type="text"
                placeholder="0 2 * * *"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-600 dark:border-gray-500 dark:text-white text-sm font-mono"
              />
              <p class="mt-2 text-xs text-gray-400">
                {{ $t('backup.cronHelp') }} (minute hour day month weekday)
              </p>
            </div>
          </div>

          <!-- Retention Policy -->
          <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">{{ $t('backup.retention') }}</label>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
              <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $t('backup.keepLast') }}</label>
                <input v-model.number="configForm.retention_policy.keep_last" type="number" min="1" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $t('backup.keepDaily') }}</label>
                <input v-model.number="configForm.retention_policy.keep_daily" type="number" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $t('backup.keepWeekly') }}</label>
                <input v-model.number="configForm.retention_policy.keep_weekly" type="number" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $t('backup.keepMonthly') }}</label>
                <input v-model.number="configForm.retention_policy.keep_monthly" type="number" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $t('backup.keepYearly') }}</label>
                <input v-model.number="configForm.retention_policy.keep_yearly" type="number" min="0" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white text-sm" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center justify-end space-x-3">
        <router-link
          to="/backup"
          class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600"
        >
          {{ $t('common.cancel') }}
        </router-link>
        <button
          type="submit"
          :disabled="isSaving || !isFormValid"
          class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg v-if="isSaving" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isSaving ? $t('common.loading') : $t('common.save') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, h } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import { useAppStore } from '@/stores/app'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const appStore = useAppStore()

const showToast = (message, type = 'info') => {
  appStore.showToast({ type, message })
}

// Check if editing existing config
const isEditing = computed(() => !!route.params.id)
const isLoading = ref(false)
const isSaving = ref(false)
const storageRemotes = ref([])

// Only show remotes that have been successfully tested
const connectedRemotes = computed(() => {
  return storageRemotes.value.filter(remote => remote.last_test_result === true)
})

// Icon components as render functions
const IconFiles = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z' })
])

const IconDatabase = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4' })
])

const IconEmail = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' })
])

const IconConfig = () => h('svg', { fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z' }),
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z' })
])

// Customize options for backup items
const customizeOptions = computed(() => [
  { value: 'files', label: t('backup.typeFiles'), icon: IconFiles },
  { value: 'databases', label: t('backup.typeDatabases'), icon: IconDatabase },
  { value: 'emails', label: t('backup.typeEmails'), icon: IconEmail },
  { value: 'config', label: t('backup.typeConfig'), icon: IconConfig }
])

// Days of week for display
const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
const daysOfWeekVi = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7']

// Schedule summary
const scheduleSummary = computed(() => {
  const schedule = configForm.value.schedule
  const time = configForm.value.schedule_time || '02:00'
  const day = configForm.value.schedule_day
  const interval = configForm.value.schedule_interval || 1
  const minute = configForm.value.schedule_minute || 0

  if (!schedule) return ''

  switch (schedule) {
    case 'daily':
      return t('backup.scheduleSummaryDaily', { time })
    case 'n_days':
      return t('backup.scheduleSummaryNDays', { days: interval, time })
    case 'hourly':
      return t('backup.scheduleSummaryHourly', { minute })
    case 'n_hours':
      return t('backup.scheduleSummaryNHours', { hours: interval, minute })
    case 'n_minutes':
      return t('backup.scheduleSummaryNMinutes', { minutes: interval })
    case 'weekly':
      const dayName = daysOfWeek[parseInt(day || '0')]
      return t('backup.scheduleSummaryWeekly', { day: dayName, time })
    case 'monthly':
      const dayNum = day === 'last' ? t('backup.lastDay') : day || '1'
      return t('backup.scheduleSummaryMonthly', { day: dayNum, time })
    default:
      return ''
  }
})

// Form data
const defaultConfigForm = {
  name: '',
  backup_mode: 'full', // 'full' or 'customize'
  backup_items: [], // ['files', 'databases', 'emails', 'config']
  destinations: ['local'], // ['local', 'remote:uuid1', 'remote:uuid2']
  destination_config: {
    path: '/var/backups/vsispanel',
    password: ''
  },
  schedule: '',
  schedule_time: '02:00',
  schedule_day: '0',
  schedule_interval: 1, // For n_days, n_hours, n_minutes
  schedule_minute: 0, // For hourly, n_hours
  schedule_cron: '',
  retention_policy: {
    keep_last: 5,
    keep_daily: 7,
    keep_weekly: 4,
    keep_monthly: 3,
    keep_yearly: 1
  },
  is_active: true
}

const configForm = ref({ ...defaultConfigForm, backup_items: [], destinations: ['local'] })

// Form validation
const isFormValid = computed(() => {
  if (!configForm.value.name) return false
  if (configForm.value.destinations.length === 0) return false
  if (configForm.value.backup_mode === 'customize' && configForm.value.backup_items.length === 0) return false
  return true
})

// Fetch storage remotes
const fetchStorageRemotes = async () => {
  try {
    const response = await api.get('/storage-remotes')
    if (response.data.success) {
      storageRemotes.value = response.data.data
    }
  } catch (error) {
    console.error('Failed to fetch storage remotes:', error)
  }
}

// Fetch existing config for editing
const fetchConfig = async (id) => {
  isLoading.value = true
  try {
    const response = await api.get(`/backup-configs/${id}`)
    if (response.data.success) {
      const config = response.data.data

      // Convert old format to new format
      let backupMode = 'full'
      let backupItems = []
      if (config.type === 'full') {
        backupMode = 'full'
      } else {
        backupMode = 'customize'
        backupItems = [config.type]
      }

      // Convert destinations
      let destinations = []
      if (config.destination_type === 'local') {
        destinations.push('local')
      } else if (config.destination_type === 'rclone' && config.storage_remote_id) {
        destinations.push(`remote:${config.storage_remote_id}`)
      }
      // Handle multiple destinations if stored
      if (config.destinations && Array.isArray(config.destinations)) {
        destinations = config.destinations
      }

      // Parse schedule fields based on schedule type
      let scheduleInterval = 1
      let scheduleMinute = 0
      let scheduleDay = config.schedule_day || '0'
      let scheduleTime = config.schedule_time || '02:00'

      // For interval-based schedules, parse interval from schedule_day
      if (['n_days', 'n_hours', 'n_minutes'].includes(config.schedule)) {
        scheduleInterval = parseInt(config.schedule_day) || 1
        scheduleDay = '0'
      }

      // For hourly schedules, parse minute from schedule_time
      if (['hourly', 'n_hours'].includes(config.schedule)) {
        const timeParts = (config.schedule_time || '00:00').split(':')
        scheduleMinute = parseInt(timeParts[1]) || 0
      }

      configForm.value = {
        name: config.name,
        backup_mode: backupMode,
        backup_items: config.backup_items || backupItems,
        destinations: destinations.length > 0 ? destinations : ['local'],
        destination_config: { ...config.destination_config, password: '' },
        schedule: config.schedule || '',
        schedule_time: scheduleTime,
        schedule_day: scheduleDay,
        schedule_interval: scheduleInterval,
        schedule_minute: scheduleMinute,
        schedule_cron: config.schedule_cron || '',
        retention_policy: config.retention_policy || { ...defaultConfigForm.retention_policy },
        is_active: config.is_active
      }
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
    router.push('/backup')
  } finally {
    isLoading.value = false
  }
}

// Save config
const saveConfig = async () => {
  if (!isFormValid.value) return

  isSaving.value = true
  try {
    // Encode schedule fields based on schedule type
    let scheduleDay = configForm.value.schedule_day
    let scheduleTime = configForm.value.schedule_time

    // For interval-based schedules, store interval in schedule_day
    if (['n_days', 'n_hours', 'n_minutes'].includes(configForm.value.schedule)) {
      scheduleDay = String(configForm.value.schedule_interval || 1)
    }

    // For hourly schedules, store minute in schedule_time format
    if (['hourly', 'n_hours'].includes(configForm.value.schedule)) {
      const minute = String(configForm.value.schedule_minute || 0).padStart(2, '0')
      scheduleTime = `00:${minute}`
    }

    // Convert form data to API format
    const payload = {
      name: configForm.value.name,
      type: configForm.value.backup_mode === 'full' ? 'full' : 'custom',
      backup_items: configForm.value.backup_mode === 'full' ? ['files', 'databases', 'emails', 'config'] : configForm.value.backup_items,
      destinations: configForm.value.destinations,
      destination_config: configForm.value.destination_config,
      schedule: configForm.value.schedule,
      schedule_time: scheduleTime,
      schedule_day: scheduleDay,
      schedule_cron: configForm.value.schedule_cron,
      retention_policy: configForm.value.retention_policy,
      is_active: configForm.value.is_active,
      // Legacy fields for backward compatibility
      destination_type: configForm.value.destinations.includes('local') ? 'local' : 'rclone',
      storage_remote_id: configForm.value.destinations.find(d => d.startsWith('remote:'))?.replace('remote:', '') || null
    }

    let response
    if (isEditing.value) {
      response = await api.put(`/backup-configs/${route.params.id}`, payload)
    } else {
      response = await api.post('/backup-configs', payload)
    }

    if (response.data.success) {
      showToast(isEditing.value ? t('backup.configUpdated') : t('backup.configCreated'), 'success')
      router.push('/backup')
    }
  } catch (error) {
    showToast(error.response?.data?.error?.message || t('common.error'), 'error')
  } finally {
    isSaving.value = false
  }
}

onMounted(async () => {
  await fetchStorageRemotes()
  if (isEditing.value) {
    await fetchConfig(route.params.id)
  }
})
</script>
