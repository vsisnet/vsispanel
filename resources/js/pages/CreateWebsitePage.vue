<template>
  <div>
    <VBreadcrumb :items="[{ label: $t('nav.websites'), to: '/websites' }, { label: $t('websites.addDomain') }]" />

    <div class="max-w-2xl mx-auto mt-6">
      <VCard :title="$t('websites.addDomain')">
        <!-- Progress Steps -->
        <div class="mb-8">
          <div class="flex items-center justify-between">
            <template v-for="(step, index) in steps" :key="step.id">
              <div class="flex items-center">
                <div
                  :class="[
                    'w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors',
                    currentStep > index
                      ? 'bg-green-500 text-white'
                      : currentStep === index
                        ? 'bg-primary-500 text-white'
                        : 'bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400'
                  ]"
                >
                  <CheckIcon v-if="currentStep > index" class="w-5 h-5" />
                  <span v-else>{{ index + 1 }}</span>
                </div>
                <span
                  class="ml-3 text-sm font-medium hidden sm:inline"
                  :class="currentStep >= index ? 'text-gray-900 dark:text-white' : 'text-gray-500 dark:text-gray-400'"
                >
                  {{ $t(`websites.wizard.${step.id}`) }}
                </span>
              </div>
              <div
                v-if="index < steps.length - 1"
                class="flex-1 mx-4 h-0.5"
                :class="currentStep > index ? 'bg-green-500' : 'bg-gray-200 dark:bg-gray-700'"
              />
            </template>
          </div>
        </div>

        <!-- Step Content -->
        <div class="min-h-[200px]">
          <!-- Step 1: Domain Name -->
          <div v-if="currentStep === 0" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('websites.domainName') }}
              </label>
              <VInput
                v-model="form.name"
                :placeholder="$t('websites.domainPlaceholder')"
                :error="errors.name"
                @input="validateDomain"
              />
              <p v-if="errors.name" class="mt-1 text-sm text-red-500">{{ errors.name }}</p>
              <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $t('websites.wizard.domainHint') }}
              </p>
            </div>
          </div>

          <!-- Step 2: PHP Version -->
          <div v-if="currentStep === 1" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                {{ $t('php.version') }}
              </label>
              <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                <button
                  v-for="version in phpVersions"
                  :key="version"
                  type="button"
                  @click="form.php_version = version"
                  :class="[
                    'p-4 rounded-lg border-2 transition-colors text-center',
                    form.php_version === version
                      ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                      : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                  ]"
                >
                  <CodeBracketIcon class="w-8 h-8 mx-auto mb-2" :class="form.php_version === version ? 'text-primary-500' : 'text-gray-400'" />
                  <span class="font-medium" :class="form.php_version === version ? 'text-primary-600 dark:text-primary-400' : 'text-gray-900 dark:text-white'">
                    PHP {{ version }}
                  </span>
                  <span v-if="version === '8.3'" class="block text-xs text-green-500 mt-1">
                    {{ $t('websites.wizard.recommended') }}
                  </span>
                </button>
              </div>
            </div>
          </div>

          <!-- Step 3: Options -->
          <div v-if="currentStep === 2" class="space-y-4">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
              {{ $t('websites.wizard.optionsHint') }}
            </p>
            <label class="flex items-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
              <input type="checkbox" v-model="form.auto_ssl" class="w-5 h-5 text-primary-600 rounded border-gray-300 focus:ring-primary-500">
              <div class="ml-4">
                <span class="font-medium text-gray-900 dark:text-white">{{ $t('websites.wizard.autoSsl') }}</span>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('websites.wizard.autoSslDesc') }}</p>
              </div>
            </label>
            <label class="flex items-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
              <input type="checkbox" v-model="form.create_dns" class="w-5 h-5 text-primary-600 rounded border-gray-300 focus:ring-primary-500">
              <div class="ml-4">
                <span class="font-medium text-gray-900 dark:text-white">{{ $t('websites.wizard.createDns') }}</span>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('websites.wizard.createDnsDesc') }}</p>
              </div>
            </label>
            <label class="flex items-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800">
              <input type="checkbox" v-model="form.create_database" class="w-5 h-5 text-primary-600 rounded border-gray-300 focus:ring-primary-500">
              <div class="ml-4">
                <span class="font-medium text-gray-900 dark:text-white">{{ $t('websites.wizard.createDatabase') }}</span>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('websites.wizard.createDatabaseDesc') }}</p>
              </div>
            </label>
          </div>

          <!-- Step 4: Summary -->
          <div v-if="currentStep === 3" class="space-y-4">
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
              <h4 class="font-semibold text-gray-900 dark:text-white mb-4">{{ $t('websites.wizard.summary') }}</h4>
              <dl class="space-y-3">
                <div class="flex justify-between">
                  <dt class="text-gray-500 dark:text-gray-400">{{ $t('websites.domainName') }}</dt>
                  <dd class="font-medium text-gray-900 dark:text-white">{{ form.name }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-gray-500 dark:text-gray-400">{{ $t('php.version') }}</dt>
                  <dd class="font-medium text-gray-900 dark:text-white">PHP {{ form.php_version }}</dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-gray-500 dark:text-gray-400">{{ $t('websites.wizard.autoSsl') }}</dt>
                  <dd><VBadge :variant="form.auto_ssl ? 'success' : 'secondary'" size="sm">{{ form.auto_ssl ? $t('common.yes') : $t('common.no') }}</VBadge></dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-gray-500 dark:text-gray-400">{{ $t('websites.wizard.createDns') }}</dt>
                  <dd><VBadge :variant="form.create_dns ? 'success' : 'secondary'" size="sm">{{ form.create_dns ? $t('common.yes') : $t('common.no') }}</VBadge></dd>
                </div>
                <div class="flex justify-between">
                  <dt class="text-gray-500 dark:text-gray-400">{{ $t('websites.wizard.createDatabase') }}</dt>
                  <dd><VBadge :variant="form.create_database ? 'success' : 'secondary'" size="sm">{{ form.create_database ? $t('common.yes') : $t('common.no') }}</VBadge></dd>
                </div>
              </dl>
            </div>
            <div v-if="creating" class="text-center py-4">
              <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary-500 mb-2"></div>
              <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('websites.wizard.creatingDomain') }}</p>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="mt-8 flex justify-between">
          <VButton v-if="currentStep > 0" variant="secondary" :disabled="creating" @click="currentStep--">
            {{ $t('common.back') }}
          </VButton>
          <router-link v-else to="/websites">
            <VButton variant="secondary">{{ $t('common.cancel') }}</VButton>
          </router-link>

          <div class="flex space-x-3">
            <VButton v-if="currentStep < steps.length - 1" variant="primary" :disabled="!canProceed" @click="currentStep++">
              {{ $t('common.next') }}
            </VButton>
            <VButton v-else variant="primary" :loading="creating" :disabled="!canProceed" @click="createDomain">
              {{ $t('websites.createDomain') }}
            </VButton>
          </div>
        </div>
      </VCard>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useDomainsStore } from '@/stores/domains'
import { useAppStore } from '@/stores/app'
import VCard from '@/components/ui/VCard.vue'
import VInput from '@/components/ui/VInput.vue'
import VButton from '@/components/ui/VButton.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VBreadcrumb from '@/components/ui/VBreadcrumb.vue'
import { CheckIcon, CodeBracketIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const { t } = useI18n()
const domainsStore = useDomainsStore()
const appStore = useAppStore()

const steps = [{ id: 'domain' }, { id: 'php' }, { id: 'options' }, { id: 'summary' }]
const currentStep = ref(0)
const creating = ref(false)
const errors = ref({})
const phpVersions = ['7.4', '8.0', '8.1', '8.2', '8.3']

const form = ref({
  name: '',
  php_version: '8.3',
  auto_ssl: true,
  create_dns: true,
  create_database: false
})

const canProceed = computed(() => {
  if (currentStep.value === 0) return form.value.name && !errors.value.name
  return true
})

function validateDomain() {
  errors.value.name = ''
  const domain = form.value.name.trim()
  if (!domain) return
  const domainRegex = /^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/
  if (!domainRegex.test(domain)) {
    errors.value.name = t('websites.wizard.invalidDomain')
  }
}

async function createDomain() {
  creating.value = true
  try {
    const domain = await domainsStore.createDomain({
      name: form.value.name,
      php_version: form.value.php_version,
      auto_ssl: form.value.auto_ssl,
      create_dns: form.value.create_dns,
      create_database: form.value.create_database,
    })
    appStore.showToast({ type: 'success', message: t('websites.createSuccess') })
    router.push({ name: 'domain-detail', params: { id: domain.id } })
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('websites.createError') })
  } finally {
    creating.value = false
  }
}
</script>
