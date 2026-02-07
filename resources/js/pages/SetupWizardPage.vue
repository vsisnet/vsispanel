<template>
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900 py-10 px-4">
    <div class="max-w-2xl mx-auto">
      <!-- Header -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
          {{ $t('setup.title') }}
        </h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('setup.description') }}
        </p>
      </div>

      <!-- Step Indicator -->
      <div class="mb-8">
        <div class="flex items-center justify-between">
          <template v-for="(step, index) in steps" :key="index">
            <!-- Step Circle -->
            <div class="flex flex-col items-center relative z-10">
              <div
                :class="[
                  'w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold border-2 transition-colors duration-200',
                  index < currentStep
                    ? 'bg-green-500 border-green-500 text-white'
                    : index === currentStep
                      ? 'bg-blue-600 border-blue-600 text-white'
                      : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400'
                ]"
              >
                <CheckIcon v-if="index < currentStep" class="w-5 h-5" />
                <span v-else>{{ index + 1 }}</span>
              </div>
              <span
                :class="[
                  'mt-2 text-xs font-medium whitespace-nowrap',
                  index < currentStep
                    ? 'text-green-600 dark:text-green-400'
                    : index === currentStep
                      ? 'text-blue-600 dark:text-blue-400'
                      : 'text-gray-400 dark:text-gray-500'
                ]"
              >
                {{ $t(step.label) }}
              </span>
            </div>

            <!-- Connector Line -->
            <div
              v-if="index < steps.length - 1"
              :class="[
                'flex-1 h-0.5 mx-2 mb-6 transition-colors duration-200',
                index < currentStep
                  ? 'bg-green-500'
                  : 'bg-gray-300 dark:bg-gray-600'
              ]"
            />
          </template>
        </div>
      </div>

      <!-- Card Container -->
      <div class="bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6 sm:p-8">

        <!-- Step 1: System Requirements -->
        <div v-if="currentStep === 0">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
            {{ $t('setup.requirements.title') }}
          </h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('setup.requirements.description') }}
          </p>

          <!-- Loading State -->
          <div v-if="requirementsLoading" class="flex items-center justify-center py-12">
            <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <span class="ml-3 text-gray-600 dark:text-gray-400">{{ $t('setup.requirements.checking') }}</span>
          </div>

          <!-- Requirements List -->
          <div v-else class="space-y-3">
            <div
              v-for="(req, index) in requirements"
              :key="index"
              :class="[
                'flex items-center justify-between p-3 rounded-lg border',
                req.passed
                  ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20'
                  : 'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/20'
              ]"
            >
              <div class="flex items-center">
                <CheckCircleIcon v-if="req.passed" class="w-5 h-5 text-green-500 mr-3 flex-shrink-0" />
                <XCircleIcon v-else class="w-5 h-5 text-red-500 mr-3 flex-shrink-0" />
                <div>
                  <p :class="[
                    'text-sm font-medium',
                    req.passed ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300'
                  ]">
                    {{ req.name }}
                  </p>
                  <p v-if="req.note" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    {{ req.note }}
                  </p>
                </div>
              </div>
              <span :class="[
                'text-xs font-mono px-2 py-1 rounded',
                req.passed
                  ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'
                  : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
              ]">
                {{ req.current || (req.passed ? $t('setup.requirements.ok') : $t('setup.requirements.missing')) }}
              </span>
            </div>

            <!-- Error Message -->
            <div v-if="requirementsError" class="mt-4 p-3 bg-red-50 dark:bg-red-900/30 rounded-lg">
              <p class="text-sm text-red-600 dark:text-red-400">{{ requirementsError }}</p>
            </div>

            <!-- Retry Button -->
            <button
              v-if="requirementsError"
              @click="checkRequirements"
              class="mt-2 text-sm text-blue-600 dark:text-blue-400 hover:underline"
            >
              {{ $t('setup.requirements.retry') }}
            </button>
          </div>
        </div>

        <!-- Step 2: Database Configuration -->
        <div v-if="currentStep === 1">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
            {{ $t('setup.database.title') }}
          </h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('setup.database.description') }}
          </p>

          <form @submit.prevent class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('setup.database.host') }}
                </label>
                <input
                  v-model="dbForm.host"
                  type="text"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('setup.database.port') }}
                </label>
                <input
                  v-model="dbForm.port"
                  type="number"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('setup.database.name') }}
              </label>
              <input
                v-model="dbForm.database"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('setup.database.username') }}
              </label>
              <input
                v-model="dbForm.username"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('setup.database.password') }}
              </label>
              <input
                v-model="dbForm.password"
                type="password"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
              />
            </div>

            <!-- Test Connection Button -->
            <div class="flex items-center gap-3">
              <button
                @click="testDatabase"
                :disabled="dbTesting"
                class="inline-flex items-center px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 dark:text-blue-400 dark:bg-blue-900/30 dark:border-blue-800 dark:hover:bg-blue-900/50 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                <svg v-if="dbTesting" class="animate-spin -ml-0.5 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                {{ dbTesting ? $t('setup.database.testing') : $t('setup.database.testConnection') }}
              </button>

              <span v-if="dbTestResult === 'success'" class="flex items-center text-sm text-green-600 dark:text-green-400">
                <CheckCircleIcon class="w-4 h-4 mr-1" />
                {{ $t('setup.database.connectionSuccess') }}
              </span>
              <span v-else-if="dbTestResult === 'error'" class="flex items-center text-sm text-red-600 dark:text-red-400">
                <XCircleIcon class="w-4 h-4 mr-1" />
                {{ dbTestError }}
              </span>
            </div>
          </form>
        </div>

        <!-- Step 3: Admin Account -->
        <div v-if="currentStep === 2">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
            {{ $t('setup.admin.title') }}
          </h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('setup.admin.description') }}
          </p>

          <form @submit.prevent class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('setup.admin.name') }}
              </label>
              <input
                v-model="adminForm.name"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                :placeholder="$t('setup.admin.namePlaceholder')"
              />
              <p v-if="adminErrors.name" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ adminErrors.name }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('setup.admin.email') }}
              </label>
              <input
                v-model="adminForm.email"
                type="email"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                :placeholder="$t('setup.admin.emailPlaceholder')"
              />
              <p v-if="adminErrors.email" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ adminErrors.email }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('setup.admin.password') }}
              </label>
              <input
                v-model="adminForm.password"
                type="password"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                :placeholder="$t('setup.admin.passwordPlaceholder')"
              />
              <p v-if="adminErrors.password" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ adminErrors.password }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('setup.admin.confirmPassword') }}
              </label>
              <input
                v-model="adminForm.password_confirmation"
                type="password"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                :placeholder="$t('setup.admin.confirmPasswordPlaceholder')"
              />
              <p v-if="adminErrors.password_confirmation" class="mt-1 text-xs text-red-500 dark:text-red-400">{{ adminErrors.password_confirmation }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('setup.admin.timezone') }}
                </label>
                <select
                  v-model="adminForm.timezone"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                >
                  <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                    {{ tz.label }}
                  </option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('setup.admin.language') }}
                </label>
                <select
                  v-model="adminForm.language"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                >
                  <option value="vi">Tiếng Việt</option>
                  <option value="en">English</option>
                </select>
              </div>
            </div>
          </form>
        </div>

        <!-- Step 4: Server Configuration -->
        <div v-if="currentStep === 3">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
            {{ $t('setup.server.title') }}
          </h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
            {{ $t('setup.server.description') }}
          </p>

          <form @submit.prevent class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('setup.server.hostname') }}
              </label>
              <input
                v-model="serverForm.hostname"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                :placeholder="$t('setup.server.hostnamePlaceholder')"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('setup.server.ip') }}
              </label>
              <input
                v-model="serverForm.ip"
                type="text"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm"
                :placeholder="$t('setup.server.ipPlaceholder')"
              />
            </div>

            <!-- Service Toggles -->
            <div class="mt-6">
              <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                {{ $t('setup.server.services') }}
              </h3>
              <div class="space-y-3">
                <label class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center">
                    <EnvelopeIcon class="w-5 h-5 text-gray-500 dark:text-gray-400 mr-3" />
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $t('setup.server.enableMail') }}</p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('setup.server.enableMailDesc') }}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="serverForm.enable_mail = !serverForm.enable_mail"
                    :class="[
                      'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800',
                      serverForm.enable_mail ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-600'
                    ]"
                    role="switch"
                    :aria-checked="serverForm.enable_mail"
                  >
                    <span
                      :class="[
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                        serverForm.enable_mail ? 'translate-x-5' : 'translate-x-0'
                      ]"
                    />
                  </button>
                </label>

                <label class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center">
                    <GlobeAltIcon class="w-5 h-5 text-gray-500 dark:text-gray-400 mr-3" />
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $t('setup.server.enableDns') }}</p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('setup.server.enableDnsDesc') }}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="serverForm.enable_dns = !serverForm.enable_dns"
                    :class="[
                      'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800',
                      serverForm.enable_dns ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-600'
                    ]"
                    role="switch"
                    :aria-checked="serverForm.enable_dns"
                  >
                    <span
                      :class="[
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                        serverForm.enable_dns ? 'translate-x-5' : 'translate-x-0'
                      ]"
                    />
                  </button>
                </label>

                <label class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                  <div class="flex items-center">
                    <ArrowUpTrayIcon class="w-5 h-5 text-gray-500 dark:text-gray-400 mr-3" />
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $t('setup.server.enableFtp') }}</p>
                      <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t('setup.server.enableFtpDesc') }}</p>
                    </div>
                  </div>
                  <button
                    type="button"
                    @click="serverForm.enable_ftp = !serverForm.enable_ftp"
                    :class="[
                      'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800',
                      serverForm.enable_ftp ? 'bg-blue-600' : 'bg-gray-200 dark:bg-gray-600'
                    ]"
                    role="switch"
                    :aria-checked="serverForm.enable_ftp"
                  >
                    <span
                      :class="[
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                        serverForm.enable_ftp ? 'translate-x-5' : 'translate-x-0'
                      ]"
                    />
                  </button>
                </label>
              </div>
            </div>
          </form>
        </div>

        <!-- Step 5: Complete -->
        <div v-if="currentStep === 4">
          <!-- Installing State -->
          <div v-if="installing" class="text-center py-8">
            <svg class="animate-spin h-12 w-12 text-blue-600 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
              {{ $t('setup.complete.installing') }}
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('setup.complete.installingDesc') }}
            </p>

            <!-- Progress Steps -->
            <div class="mt-6 space-y-2 text-left max-w-sm mx-auto">
              <div v-for="(task, index) in installTasks" :key="index" class="flex items-center text-sm">
                <CheckCircleIcon v-if="task.done" class="w-4 h-4 text-green-500 mr-2 flex-shrink-0" />
                <svg v-else-if="task.active" class="animate-spin h-4 w-4 text-blue-500 mr-2 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <div v-else class="w-4 h-4 rounded-full border-2 border-gray-300 dark:border-gray-600 mr-2 flex-shrink-0" />
                <span :class="[
                  task.done ? 'text-green-600 dark:text-green-400' : task.active ? 'text-blue-600 dark:text-blue-400' : 'text-gray-400 dark:text-gray-500'
                ]">
                  {{ $t(task.label) }}
                </span>
              </div>
            </div>
          </div>

          <!-- Success State -->
          <div v-else-if="installSuccess" class="text-center py-8">
            <div class="setup-success-animation mb-6">
              <div class="w-20 h-20 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center mx-auto">
                <CheckCircleIcon class="w-12 h-12 text-green-500" />
              </div>
            </div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
              {{ $t('setup.complete.successTitle') }}
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
              {{ $t('setup.complete.successDesc') }}
            </p>
            <button
              @click="goToLogin"
              class="inline-flex items-center px-6 py-3 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors"
            >
              {{ $t('setup.complete.goToLogin') }}
            </button>
          </div>

          <!-- Error State -->
          <div v-else-if="installError" class="text-center py-8">
            <div class="w-20 h-20 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mx-auto mb-6">
              <XCircleIcon class="w-12 h-12 text-red-500" />
            </div>
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
              {{ $t('setup.complete.errorTitle') }}
            </h2>
            <p class="text-sm text-red-600 dark:text-red-400 mb-6">
              {{ installError }}
            </p>
            <button
              @click="runInstallation"
              class="inline-flex items-center px-6 py-3 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition-colors"
            >
              {{ $t('setup.complete.retry') }}
            </button>
          </div>
        </div>

        <!-- Navigation Buttons -->
        <div v-if="currentStep < 4 || (!installing && !installSuccess && !installError)" class="mt-8 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 pt-6">
          <button
            v-if="currentStep > 0"
            @click="prevStep"
            class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors"
          >
            <ArrowLeftIcon class="w-4 h-4 mr-2" />
            {{ $t('common.back') }}
          </button>
          <div v-else />

          <button
            @click="nextStep"
            :disabled="!canProceed"
            :class="[
              'inline-flex items-center px-6 py-2 text-sm font-semibold rounded-lg transition-colors',
              canProceed
                ? 'text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800'
                : 'text-gray-400 bg-gray-100 dark:text-gray-500 dark:bg-gray-700 cursor-not-allowed'
            ]"
          >
            {{ currentStep === 3 ? $t('setup.finishAndInstall') : $t('common.next') }}
            <ArrowRightIcon class="w-4 h-4 ml-2" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/utils/api'
import {
  CheckIcon,
  CheckCircleIcon,
  XCircleIcon,
  ArrowLeftIcon,
  ArrowRightIcon,
  ArrowUpTrayIcon,
  EnvelopeIcon,
  GlobeAltIcon,
} from '@heroicons/vue/24/outline'

const router = useRouter()
const { t } = useI18n()

// Steps definition
const steps = [
  { label: 'setup.steps.requirements' },
  { label: 'setup.steps.database' },
  { label: 'setup.steps.admin' },
  { label: 'setup.steps.server' },
  { label: 'setup.steps.complete' },
]

const currentStep = ref(0)

// Step 1: Requirements
const requirementsLoading = ref(false)
const requirements = ref([])
const allRequirementsPassed = ref(false)
const requirementsError = ref('')

// Step 2: Database
const dbForm = reactive({
  host: '127.0.0.1',
  port: 3306,
  database: 'vsispanel',
  username: 'root',
  password: '',
})
const dbTesting = ref(false)
const dbTestResult = ref(null) // null, 'success', 'error'
const dbTestError = ref('')

// Step 3: Admin
const adminForm = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  timezone: 'Asia/Ho_Chi_Minh',
  language: 'vi',
})
const adminErrors = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

// Step 4: Server
const serverForm = reactive({
  hostname: '',
  ip: '',
  enable_mail: true,
  enable_dns: true,
  enable_ftp: true,
})

// Step 5: Installation
const installing = ref(false)
const installSuccess = ref(false)
const installError = ref('')
const installTasks = ref([
  { label: 'setup.complete.taskDatabase', done: false, active: false },
  { label: 'setup.complete.taskAdmin', done: false, active: false },
  { label: 'setup.complete.taskServer', done: false, active: false },
])

// Timezones
const timezones = [
  { value: 'Asia/Ho_Chi_Minh', label: '(UTC+07:00) Ho Chi Minh' },
  { value: 'Asia/Bangkok', label: '(UTC+07:00) Bangkok' },
  { value: 'Asia/Singapore', label: '(UTC+08:00) Singapore' },
  { value: 'Asia/Tokyo', label: '(UTC+09:00) Tokyo' },
  { value: 'Asia/Shanghai', label: '(UTC+08:00) Shanghai' },
  { value: 'Asia/Seoul', label: '(UTC+09:00) Seoul' },
  { value: 'Asia/Kolkata', label: '(UTC+05:30) Kolkata' },
  { value: 'Asia/Dubai', label: '(UTC+04:00) Dubai' },
  { value: 'Europe/London', label: '(UTC+00:00) London' },
  { value: 'Europe/Paris', label: '(UTC+01:00) Paris' },
  { value: 'Europe/Berlin', label: '(UTC+01:00) Berlin' },
  { value: 'Europe/Moscow', label: '(UTC+03:00) Moscow' },
  { value: 'America/New_York', label: '(UTC-05:00) New York' },
  { value: 'America/Chicago', label: '(UTC-06:00) Chicago' },
  { value: 'America/Denver', label: '(UTC-07:00) Denver' },
  { value: 'America/Los_Angeles', label: '(UTC-08:00) Los Angeles' },
  { value: 'America/Sao_Paulo', label: '(UTC-03:00) Sao Paulo' },
  { value: 'Australia/Sydney', label: '(UTC+11:00) Sydney' },
  { value: 'Pacific/Auckland', label: '(UTC+13:00) Auckland' },
  { value: 'UTC', label: '(UTC+00:00) UTC' },
]

// Computed: can proceed to next step
const canProceed = computed(() => {
  switch (currentStep.value) {
    case 0:
      return allRequirementsPassed.value
    case 1:
      return dbTestResult.value === 'success'
    case 2:
      return validateAdmin()
    case 3:
      return serverForm.hostname.trim() !== '' && serverForm.ip.trim() !== ''
    default:
      return false
  }
})

function validateAdmin() {
  return (
    adminForm.name.trim() !== '' &&
    adminForm.email.trim() !== '' &&
    adminForm.password.trim() !== '' &&
    adminForm.password === adminForm.password_confirmation &&
    adminForm.password.length >= 8
  )
}

function validateAdminWithErrors() {
  let valid = true
  adminErrors.name = ''
  adminErrors.email = ''
  adminErrors.password = ''
  adminErrors.password_confirmation = ''

  if (!adminForm.name.trim()) {
    adminErrors.name = t('setup.admin.nameRequired')
    valid = false
  }

  if (!adminForm.email.trim()) {
    adminErrors.email = t('setup.admin.emailRequired')
    valid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(adminForm.email)) {
    adminErrors.email = t('setup.admin.emailInvalid')
    valid = false
  }

  if (!adminForm.password.trim()) {
    adminErrors.password = t('setup.admin.passwordRequired')
    valid = false
  } else if (adminForm.password.length < 8) {
    adminErrors.password = t('setup.admin.passwordMinLength')
    valid = false
  }

  if (adminForm.password !== adminForm.password_confirmation) {
    adminErrors.password_confirmation = t('setup.admin.passwordMismatch')
    valid = false
  }

  return valid
}

// Step navigation
function prevStep() {
  if (currentStep.value > 0) {
    currentStep.value--
  }
}

function nextStep() {
  if (!canProceed.value) return

  if (currentStep.value === 2) {
    if (!validateAdminWithErrors()) return
  }

  if (currentStep.value < steps.length - 1) {
    currentStep.value++

    if (currentStep.value === 4) {
      runInstallation()
    }
  }
}

// Step 1: Check requirements
async function checkRequirements() {
  requirementsLoading.value = true
  requirementsError.value = ''

  try {
    const { data } = await api.post('/setup/check-requirements')
    if (data.success) {
      requirements.value = data.data.requirements || []
      allRequirementsPassed.value = data.data.all_passed || false
    } else {
      requirementsError.value = data.error?.message || t('setup.requirements.checkFailed')
    }
  } catch (error) {
    requirementsError.value = error.response?.data?.error?.message || t('setup.requirements.checkFailed')
  } finally {
    requirementsLoading.value = false
  }
}

// Step 2: Test database connection
async function testDatabase() {
  dbTesting.value = true
  dbTestResult.value = null
  dbTestError.value = ''

  try {
    const { data } = await api.post('/setup/test-database', {
      host: dbForm.host,
      port: dbForm.port,
      database: dbForm.database,
      username: dbForm.username,
      password: dbForm.password,
    })

    if (data.success) {
      dbTestResult.value = 'success'
    } else {
      dbTestResult.value = 'error'
      dbTestError.value = data.error?.message || t('setup.database.connectionFailed')
    }
  } catch (error) {
    dbTestResult.value = 'error'
    dbTestError.value = error.response?.data?.error?.message || t('setup.database.connectionFailed')
  } finally {
    dbTesting.value = false
  }
}

// Step 5: Run installation
async function runInstallation() {
  installing.value = true
  installSuccess.value = false
  installError.value = ''

  // Reset tasks
  installTasks.value.forEach((task) => {
    task.done = false
    task.active = false
  })

  try {
    // Task 1: Configure database
    installTasks.value[0].active = true
    await api.post('/setup/configure', {
      host: dbForm.host,
      port: dbForm.port,
      database: dbForm.database,
      username: dbForm.username,
      password: dbForm.password,
    })
    installTasks.value[0].done = true
    installTasks.value[0].active = false

    // Task 2: Create admin
    installTasks.value[1].active = true
    await api.post('/setup/create-admin', {
      name: adminForm.name,
      email: adminForm.email,
      password: adminForm.password,
      password_confirmation: adminForm.password_confirmation,
      timezone: adminForm.timezone,
      language: adminForm.language,
    })
    installTasks.value[1].done = true
    installTasks.value[1].active = false

    // Task 3: Finalize server config
    installTasks.value[2].active = true
    await api.post('/setup/finalize', {
      hostname: serverForm.hostname,
      ip: serverForm.ip,
      enable_mail: serverForm.enable_mail,
      enable_dns: serverForm.enable_dns,
      enable_ftp: serverForm.enable_ftp,
    })
    installTasks.value[2].done = true
    installTasks.value[2].active = false

    installing.value = false
    installSuccess.value = true
  } catch (error) {
    installing.value = false
    installError.value = error.response?.data?.error?.message || t('setup.complete.installFailed')
  }
}

// Go to login
function goToLogin() {
  router.push('/login')
}

// Initialize
onMounted(() => {
  checkRequirements()
  serverForm.ip = window.location.hostname
})
</script>

<style scoped>
.setup-success-animation {
  animation: successBounce 0.6s ease-out;
}

@keyframes successBounce {
  0% {
    transform: scale(0);
    opacity: 0;
  }
  50% {
    transform: scale(1.1);
  }
  100% {
    transform: scale(1);
    opacity: 1;
  }
}
</style>
