<template>
  <div class="space-y-6">
    <p class="text-sm text-gray-500 dark:text-gray-400">
      {{ $t('settings.notifications.description') }}
    </p>

    <!-- Mail Provider Configuration -->
    <VCard>
      <div class="flex items-center space-x-3 mb-4">
        <div class="w-10 h-10 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
          <ServerStackIcon class="w-5 h-5 text-gray-600 dark:text-gray-400" />
        </div>
        <div>
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $t('settings.mail.title') }}
          </h3>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ $t('settings.mail.description') }}
          </p>
        </div>
      </div>

      <div class="space-y-4">
        <!-- Provider Selection -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('settings.mail.provider') }}
          </label>
          <select
            v-model="mailForm.provider"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            @change="onProviderChange"
          >
            <option v-for="p in providers" :key="p.value" :value="p.value">
              {{ p.label }}
            </option>
          </select>
        </div>

        <!-- From Address / Name (shown for all providers except gmail_oauth) -->
        <div v-if="mailForm.provider !== 'gmail_oauth'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('settings.mail.fromAddress') }}
            </label>
            <input
              v-model="mailForm.from_address"
              type="email"
              placeholder="noreply@example.com"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('settings.mail.fromName') }}
            </label>
            <input
              v-model="mailForm.from_name"
              type="text"
              placeholder="VSISPanel"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            />
          </div>
        </div>

        <!-- Gmail OAuth2 Settings -->
        <template v-if="mailForm.provider === 'gmail_oauth'">
          <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
              {{ $t('settings.mail.gmailOauthSettings') }}
            </h4>

            <!-- OAuth Status -->
            <div class="rounded-lg border p-4 mb-4" :class="gmailOAuth.authorized
              ? 'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20'
              : 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800'
            ">
              <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                  <!-- Google icon -->
                  <svg class="w-6 h-6" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                  </svg>
                  <div>
                    <p v-if="gmailOAuth.authorized" class="text-sm font-medium text-green-800 dark:text-green-300">
                      {{ $t('settings.mail.gmailAuthorized') }}
                    </p>
                    <p v-else class="text-sm font-medium text-gray-700 dark:text-gray-300">
                      {{ $t('settings.mail.gmailNotAuthorized') }}
                    </p>
                    <p v-if="gmailOAuth.email" class="text-xs text-green-600 dark:text-green-400">
                      {{ gmailOAuth.email }}
                    </p>
                  </div>
                </div>
                <div class="flex items-center space-x-2">
                  <VButton
                    v-if="!gmailOAuth.authorized"
                    variant="primary"
                    size="sm"
                    :loading="authorizing"
                    @click="authorizeGmail"
                  >
                    {{ $t('settings.mail.gmailAuthorize') }}
                  </VButton>
                  <template v-else>
                    <VButton
                      variant="secondary"
                      size="sm"
                      :loading="authorizing"
                      @click="authorizeGmail"
                    >
                      {{ $t('settings.mail.gmailReauthorize') }}
                    </VButton>
                    <VButton
                      variant="danger"
                      size="sm"
                      :loading="revoking"
                      @click="revokeGmail"
                    >
                      {{ $t('settings.mail.gmailRevoke') }}
                    </VButton>
                  </template>
                </div>
              </div>
            </div>

            <!-- From Name (only, address is from Gmail account) -->
            <div v-if="gmailOAuth.authorized" class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('settings.mail.fromAddress') }}
                </label>
                <input
                  :value="gmailOAuth.email"
                  type="email"
                  disabled
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 cursor-not-allowed"
                />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  {{ $t('settings.mail.gmailFromHint') }}
                </p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('settings.mail.fromName') }}
                </label>
                <input
                  v-model="mailForm.from_name"
                  type="text"
                  placeholder="VSISPanel"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
                />
              </div>
            </div>

            <div v-if="!gmailOAuth.proxy_configured" class="rounded-lg bg-amber-50 dark:bg-amber-900/20 p-3 text-sm text-amber-800 dark:text-amber-300">
              {{ $t('settings.mail.gmailProxyNotConfigured') }}
            </div>
          </div>
        </template>

        <!-- SMTP Settings (for smtp, gmail, outlook) -->
        <template v-if="showSmtpFields">
          <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
              {{ $t('settings.mail.smtpSettings') }}
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('settings.mail.smtpHost') }}
                </label>
                <input
                  v-model="mailForm.smtp_host"
                  type="text"
                  :placeholder="smtpHostPlaceholder"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('settings.mail.smtpPort') }}
                </label>
                <input
                  v-model.number="mailForm.smtp_port"
                  type="number"
                  placeholder="587"
                  min="1"
                  max="65535"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('settings.mail.smtpUsername') }}
                </label>
                <input
                  v-model="mailForm.smtp_username"
                  type="text"
                  :placeholder="smtpUserPlaceholder"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('settings.mail.smtpPassword') }}
                </label>
                <input
                  v-model="mailForm.smtp_password"
                  type="password"
                  placeholder="••••••••"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('settings.mail.smtpEncryption') }}
                </label>
                <select
                  v-model="mailForm.smtp_encryption"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
                >
                  <option value="tls">TLS</option>
                  <option value="ssl">SSL</option>
                  <option value="none">{{ $t('settings.mail.noEncryption') }}</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Provider hint for Gmail/Outlook -->
          <div v-if="mailForm.provider === 'gmail'" class="rounded-lg bg-amber-50 dark:bg-amber-900/20 p-3 text-sm text-amber-800 dark:text-amber-300">
            {{ $t('settings.mail.gmailHint') }}
          </div>
          <div v-if="mailForm.provider === 'outlook'" class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-3 text-sm text-blue-800 dark:text-blue-300">
            {{ $t('settings.mail.outlookHint') }}
          </div>
        </template>

        <!-- SES Settings -->
        <template v-if="mailForm.provider === 'ses'">
          <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
              {{ $t('settings.mail.sesSettings') }}
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('settings.mail.sesKey') }}
                </label>
                <input
                  v-model="mailForm.ses_key"
                  type="text"
                  placeholder="AKIAIOSFODNN7EXAMPLE"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('settings.mail.sesSecret') }}
                </label>
                <input
                  v-model="mailForm.ses_secret"
                  type="password"
                  placeholder="••••••••"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {{ $t('settings.mail.sesRegion') }}
                </label>
                <select
                  v-model="mailForm.ses_region"
                  class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
                >
                  <option v-for="r in sesRegions" :key="r" :value="r">{{ r }}</option>
                </select>
              </div>
            </div>
          </div>
        </template>

        <!-- Sendmail note -->
        <div v-if="mailForm.provider === 'sendmail'" class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 text-sm text-gray-600 dark:text-gray-400">
          {{ $t('settings.mail.sendmailHint') }}
        </div>

        <!-- Save Mail Config (not shown for gmail_oauth since it's handled via OAuth flow) -->
        <div v-if="mailForm.provider !== 'gmail_oauth'" class="flex justify-end pt-2">
          <VButton variant="primary" size="sm" :loading="savingMail" @click="saveMailConfig">
            {{ $t('common.save') }}
          </VButton>
        </div>
        <!-- For gmail_oauth, only save from_name -->
        <div v-if="mailForm.provider === 'gmail_oauth' && gmailOAuth.authorized" class="flex justify-end pt-2">
          <VButton variant="primary" size="sm" :loading="savingMail" @click="saveGmailFromName">
            {{ $t('common.save') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Email Channel -->
    <VCard>
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
            <EnvelopeIcon class="w-5 h-5 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ $t('settings.notifications.email') }}
            </h3>
          </div>
        </div>
        <button
          @click="form.email.enabled = !form.email.enabled"
          :class="[
            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
            form.email.enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-600'
          ]"
        >
          <span :class="[
            'inline-block h-4 w-4 rounded-full bg-white transition-transform',
            form.email.enabled ? 'translate-x-6' : 'translate-x-1'
          ]" />
        </button>
      </div>

      <div v-if="form.email.enabled" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('settings.notifications.emailRecipients') }}
          </label>
          <input
            v-model="form.email.recipients"
            type="text"
            placeholder="admin@example.com, alerts@example.com"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
          />
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            {{ $t('settings.notifications.emailRecipientsHint') }}
          </p>
        </div>
        <div class="flex justify-end">
          <VButton variant="secondary" size="sm" :loading="testing === 'email'" @click="testChannel('email')">
            {{ $t('settings.notifications.test') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Telegram Channel -->
    <VCard>
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 rounded-lg bg-sky-100 dark:bg-sky-900/30 flex items-center justify-center">
            <svg class="w-5 h-5 text-sky-600 dark:text-sky-400" viewBox="0 0 24 24" fill="currentColor">
              <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ $t('settings.notifications.telegram') }}
            </h3>
          </div>
        </div>
        <button
          @click="form.telegram.enabled = !form.telegram.enabled"
          :class="[
            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
            form.telegram.enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-600'
          ]"
        >
          <span :class="[
            'inline-block h-4 w-4 rounded-full bg-white transition-transform',
            form.telegram.enabled ? 'translate-x-6' : 'translate-x-1'
          ]" />
        </button>
      </div>

      <div v-if="form.telegram.enabled" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('settings.notifications.telegramBotToken') }}
          </label>
          <input
            v-model="form.telegram.bot_token"
            type="text"
            placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
          />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('settings.notifications.telegramChatId') }}
          </label>
          <input
            v-model="form.telegram.chat_id"
            type="text"
            placeholder="-1001234567890"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
          />
        </div>
        <div class="flex justify-end">
          <VButton variant="secondary" size="sm" :loading="testing === 'telegram'" @click="testChannel('telegram')">
            {{ $t('settings.notifications.test') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Slack Channel -->
    <VCard>
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
            <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" viewBox="0 0 24 24" fill="currentColor">
              <path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zM6.313 15.165a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zM8.834 6.313a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312zM18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zM17.688 8.834a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312zM15.165 18.956a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zM15.165 17.688a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z"/>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ $t('settings.notifications.slack') }}
            </h3>
          </div>
        </div>
        <button
          @click="form.slack.enabled = !form.slack.enabled"
          :class="[
            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
            form.slack.enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-600'
          ]"
        >
          <span :class="[
            'inline-block h-4 w-4 rounded-full bg-white transition-transform',
            form.slack.enabled ? 'translate-x-6' : 'translate-x-1'
          ]" />
        </button>
      </div>

      <div v-if="form.slack.enabled" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('settings.notifications.slackWebhookUrl') }}
          </label>
          <input
            v-model="form.slack.webhook_url"
            type="url"
            placeholder="https://hooks.slack.com/services/T00000000/B00000000/XXXX"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
          />
        </div>
        <div class="flex justify-end">
          <VButton variant="secondary" size="sm" :loading="testing === 'slack'" @click="testChannel('slack')">
            {{ $t('settings.notifications.test') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Discord Channel -->
    <VCard>
      <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
            <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" viewBox="0 0 24 24" fill="currentColor">
              <path d="M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189z"/>
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ $t('settings.notifications.discord') }}
            </h3>
          </div>
        </div>
        <button
          @click="form.discord.enabled = !form.discord.enabled"
          :class="[
            'relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
            form.discord.enabled ? 'bg-primary-600' : 'bg-gray-300 dark:bg-gray-600'
          ]"
        >
          <span :class="[
            'inline-block h-4 w-4 rounded-full bg-white transition-transform',
            form.discord.enabled ? 'translate-x-6' : 'translate-x-1'
          ]" />
        </button>
      </div>

      <div v-if="form.discord.enabled" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('settings.notifications.discordWebhookUrl') }}
          </label>
          <input
            v-model="form.discord.webhook_url"
            type="url"
            placeholder="https://discord.com/api/webhooks/..."
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
          />
        </div>
        <div class="flex justify-end">
          <VButton variant="secondary" size="sm" :loading="testing === 'discord'" @click="testChannel('discord')">
            {{ $t('settings.notifications.test') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Save Notification Channels Button -->
    <div class="flex justify-end">
      <VButton variant="primary" :loading="saving" @click="saveSettings">
        {{ $t('common.save') }}
      </VButton>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import { EnvelopeIcon, ServerStackIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const appStore = useAppStore()

// Provider presets
const providerPresets = {
  gmail: { smtp_host: 'smtp.gmail.com', smtp_port: 587, smtp_encryption: 'tls' },
  outlook: { smtp_host: 'smtp.office365.com', smtp_port: 587, smtp_encryption: 'tls' },
}

const providers = computed(() => [
  { value: 'smtp', label: t('settings.mail.providerSmtp') },
  { value: 'gmail', label: t('settings.mail.providerGmail') },
  { value: 'gmail_oauth', label: t('settings.mail.providerGmailOauth') },
  { value: 'outlook', label: t('settings.mail.providerOutlook') },
  { value: 'ses', label: t('settings.mail.providerSes') },
  { value: 'sendmail', label: t('settings.mail.providerSendmail') },
])

const sesRegions = [
  'us-east-1', 'us-east-2', 'us-west-1', 'us-west-2',
  'af-south-1', 'ap-east-1', 'ap-south-1', 'ap-southeast-1',
  'ap-southeast-2', 'ap-northeast-1', 'ap-northeast-2', 'ap-northeast-3',
  'ca-central-1', 'eu-central-1', 'eu-west-1', 'eu-west-2',
  'eu-west-3', 'eu-south-1', 'eu-north-1', 'me-south-1', 'sa-east-1',
]

// Mail provider form
const mailForm = ref({
  provider: 'smtp',
  from_address: '',
  from_name: '',
  smtp_host: '',
  smtp_port: 587,
  smtp_username: '',
  smtp_password: '',
  smtp_encryption: 'tls',
  ses_key: '',
  ses_secret: '',
  ses_region: 'us-east-1',
})
const savingMail = ref(false)

// Gmail OAuth state
const gmailOAuth = ref({
  proxy_configured: false,
  authorized: false,
  email: '',
})
const authorizing = ref(false)
const revoking = ref(false)

// Notification channels form
const form = ref({
  email: { enabled: false, recipients: '' },
  telegram: { enabled: false, bot_token: '', chat_id: '' },
  slack: { enabled: false, webhook_url: '' },
  discord: { enabled: false, webhook_url: '' },
})
const saving = ref(false)
const testing = ref(null)

// Computed
const showSmtpFields = computed(() => ['smtp', 'gmail', 'outlook'].includes(mailForm.value.provider))

const smtpHostPlaceholder = computed(() => {
  if (mailForm.value.provider === 'gmail') return 'smtp.gmail.com'
  if (mailForm.value.provider === 'outlook') return 'smtp.office365.com'
  return 'mail.example.com'
})

const smtpUserPlaceholder = computed(() => {
  if (mailForm.value.provider === 'gmail') return 'you@gmail.com'
  if (mailForm.value.provider === 'outlook') return 'you@outlook.com'
  return 'username'
})

// Initialize forms from props
watch(() => props.settings, (settings) => {
  if (settings?.mail) {
    const m = settings.mail
    mailForm.value.provider = m.provider || 'smtp'
    mailForm.value.from_address = m.from_address || ''
    mailForm.value.from_name = m.from_name || ''
    mailForm.value.smtp_host = m.smtp_host || ''
    mailForm.value.smtp_port = m.smtp_port ? Number(m.smtp_port) : 587
    mailForm.value.smtp_username = m.smtp_username || ''
    mailForm.value.smtp_password = m.smtp_password || ''
    mailForm.value.smtp_encryption = m.smtp_encryption || 'tls'
    mailForm.value.ses_key = m.ses_key || ''
    mailForm.value.ses_secret = m.ses_secret || ''
    mailForm.value.ses_region = m.ses_region || 'us-east-1'
  }

  if (settings?.notifications) {
    const n = settings.notifications
    form.value.email.enabled = n['email.enabled'] ?? false
    form.value.email.recipients = n['email.recipients'] ?? ''
    form.value.telegram.enabled = n['telegram.enabled'] ?? false
    form.value.telegram.bot_token = n['telegram.bot_token'] ?? ''
    form.value.telegram.chat_id = n['telegram.chat_id'] ?? ''
    form.value.slack.enabled = n['slack.enabled'] ?? false
    form.value.slack.webhook_url = n['slack.webhook_url'] ?? ''
    form.value.discord.enabled = n['discord.enabled'] ?? false
    form.value.discord.webhook_url = n['discord.webhook_url'] ?? ''
  }
}, { immediate: true })

// Provider change handler - apply presets
function onProviderChange() {
  const preset = providerPresets[mailForm.value.provider]
  if (preset) {
    mailForm.value.smtp_host = preset.smtp_host
    mailForm.value.smtp_port = preset.smtp_port
    mailForm.value.smtp_encryption = preset.smtp_encryption
  }
  // Fetch Gmail OAuth status when switching to gmail_oauth
  if (mailForm.value.provider === 'gmail_oauth') {
    fetchGmailOAuthStatus()
  }
}

// Gmail OAuth functions
async function fetchGmailOAuthStatus() {
  try {
    const { data } = await api.get('/settings/mail/gmail/status')
    if (data.success) {
      gmailOAuth.value = data.data
    }
  } catch (err) {
    console.error('Failed to fetch Gmail OAuth status:', err)
  }
}

async function authorizeGmail() {
  authorizing.value = true
  try {
    const { data } = await api.post('/settings/mail/gmail/authorize')
    if (data.success && data.data.auth_url) {
      // Open OAuth popup
      const w = 600, h = 700
      const left = (screen.width - w) / 2
      const top = (screen.height - h) / 2
      window.open(
        data.data.auth_url,
        'gmail_oauth_popup',
        `width=${w},height=${h},left=${left},top=${top},scrollbars=yes`
      )
    }
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('settings.mail.gmailAuthError'),
    })
  } finally {
    authorizing.value = false
  }
}

async function revokeGmail() {
  revoking.value = true
  try {
    const { data } = await api.post('/settings/mail/gmail/revoke')
    if (data.success) {
      gmailOAuth.value.authorized = false
      gmailOAuth.value.email = ''
      appStore.showToast({ type: 'success', message: t('settings.mail.gmailRevokeSuccess') })
      emit('refresh')
    }
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('settings.saveError'),
    })
  } finally {
    revoking.value = false
  }
}

// Save mail provider configuration
async function saveMailConfig() {
  savingMail.value = true
  try {
    const payload = {
      'mail.provider': mailForm.value.provider,
      'mail.from_address': mailForm.value.from_address,
      'mail.from_name': mailForm.value.from_name,
    }

    if (showSmtpFields.value) {
      payload['mail.smtp_host'] = mailForm.value.smtp_host
      payload['mail.smtp_port'] = mailForm.value.smtp_port
      payload['mail.smtp_username'] = mailForm.value.smtp_username
      payload['mail.smtp_password'] = mailForm.value.smtp_password
      payload['mail.smtp_encryption'] = mailForm.value.smtp_encryption
    }

    if (mailForm.value.provider === 'ses') {
      payload['mail.ses_key'] = mailForm.value.ses_key
      payload['mail.ses_secret'] = mailForm.value.ses_secret
      payload['mail.ses_region'] = mailForm.value.ses_region
    }

    const { data } = await api.put('/settings', payload)
    if (data.success) {
      appStore.showToast({ type: 'success', message: t('settings.saveSuccess') })
      emit('refresh')
    }
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('settings.saveError'),
    })
  } finally {
    savingMail.value = false
  }
}

// Save just from_name for gmail_oauth
async function saveGmailFromName() {
  savingMail.value = true
  try {
    const { data } = await api.put('/settings', {
      'mail.from_name': mailForm.value.from_name,
    })
    if (data.success) {
      appStore.showToast({ type: 'success', message: t('settings.saveSuccess') })
    }
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('settings.saveError'),
    })
  } finally {
    savingMail.value = false
  }
}

// Save notification channel settings
async function saveSettings() {
  saving.value = true
  try {
    const { data } = await api.put('/settings', {
      'notifications.email.enabled': form.value.email.enabled,
      'notifications.email.recipients': form.value.email.recipients,
      'notifications.telegram.enabled': form.value.telegram.enabled,
      'notifications.telegram.bot_token': form.value.telegram.bot_token,
      'notifications.telegram.chat_id': form.value.telegram.chat_id,
      'notifications.slack.enabled': form.value.slack.enabled,
      'notifications.slack.webhook_url': form.value.slack.webhook_url,
      'notifications.discord.enabled': form.value.discord.enabled,
      'notifications.discord.webhook_url': form.value.discord.webhook_url,
    })
    if (data.success) {
      appStore.showToast({ type: 'success', message: t('settings.saveSuccess') })
      emit('refresh')
    }
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('settings.saveError'),
    })
  } finally {
    saving.value = false
  }
}

// Test notification channel
async function testChannel(channel) {
  testing.value = channel
  try {
    // Save current values first so the test uses them
    await api.put('/settings', {
      [`notifications.${channel}.enabled`]: form.value[channel].enabled,
      ...getChannelPayload(channel),
    })
    const { data } = await api.post('/settings/notifications/test', { channel })
    if (data.success) {
      appStore.showToast({ type: 'success', message: t('settings.notifications.testSuccess') })
    }
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('settings.notifications.testError'),
    })
  } finally {
    testing.value = null
  }
}

function getChannelPayload(channel) {
  const f = form.value[channel]
  switch (channel) {
    case 'email': return { 'notifications.email.recipients': f.recipients }
    case 'telegram': return { 'notifications.telegram.bot_token': f.bot_token, 'notifications.telegram.chat_id': f.chat_id }
    case 'slack': return { 'notifications.slack.webhook_url': f.webhook_url }
    case 'discord': return { 'notifications.discord.webhook_url': f.webhook_url }
    default: return {}
  }
}

// Handle OAuth callback from URL params
function handleOAuthCallback() {
  const gmailAuth = route.query.gmail_auth
  const gmailError = route.query.gmail_error

  if (gmailAuth === 'success') {
    appStore.showToast({ type: 'success', message: t('settings.mail.gmailAuthSuccess') })
    fetchGmailOAuthStatus()
    emit('refresh')
    // Clean URL
    router.replace({ path: '/settings', query: { tab: 'notifications' } })
  } else if (gmailAuth === 'error') {
    appStore.showToast({
      type: 'error',
      message: gmailError || t('settings.mail.gmailAuthError'),
    })
    router.replace({ path: '/settings', query: { tab: 'notifications' } })
  }
}

onMounted(() => {
  handleOAuthCallback()
  // Fetch Gmail OAuth status if currently on gmail_oauth provider
  if (mailForm.value.provider === 'gmail_oauth') {
    fetchGmailOAuthStatus()
  }
})
</script>
