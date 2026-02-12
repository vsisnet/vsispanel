<template>
  <div>
    <!-- Page Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('settings.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $t('settings.description') }}
      </p>
    </div>

    <!-- Loading State -->
    <VLoadingSkeleton v-if="loading" class="h-96" />

    <!-- Content -->
    <template v-else>
      <!-- Tab Navigation -->
      <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="-mb-px flex space-x-8">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="switchTab(tab.id)"
            :class="[
              'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === tab.id
                ? 'border-primary-500 text-primary-600 dark:text-primary-400'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'
            ]"
          >
            <component :is="tab.icon" class="w-5 h-5 inline-block mr-2 -mt-0.5" />
            {{ $t(`settings.tabs.${tab.id}`) }}
          </button>
        </nav>
      </div>

      <!-- Tab Content -->
      <KeepAlive>
        <component
          :is="activeTabComponent"
          :settings="settings"
          :server-time="serverTime"
          @refresh="fetchSettings"
        />
      </KeepAlive>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, markRaw } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/utils/api'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import SettingsGeneralTab from '@/components/settings/SettingsGeneralTab.vue'
import SettingsNotificationsTab from '@/components/settings/SettingsNotificationsTab.vue'
import SettingsSslTab from '@/components/settings/SettingsSslTab.vue'
import { Cog6ToothIcon, BellIcon, LockClosedIcon, KeyIcon } from '@heroicons/vue/24/outline'
import SettingsApiTokensTab from '@/components/settings/SettingsApiTokensTab.vue'

const route = useRoute()
const router = useRouter()

const validTabs = ['general', 'notifications', 'ssl', 'api-tokens']

const tabs = [
  { id: 'general', icon: markRaw(Cog6ToothIcon) },
  { id: 'notifications', icon: markRaw(BellIcon) },
  { id: 'ssl', icon: markRaw(LockClosedIcon) },
  { id: 'api-tokens', icon: markRaw(KeyIcon) },
]

const tabComponents = {
  general: markRaw(SettingsGeneralTab),
  notifications: markRaw(SettingsNotificationsTab),
  ssl: markRaw(SettingsSslTab),
  'api-tokens': markRaw(SettingsApiTokensTab),
}

// State
const loading = ref(true)
const settings = ref({})
const serverTime = ref(null)
const activeTab = ref(getInitialTab())

function getInitialTab() {
  const tabFromQuery = route.query.tab
  if (tabFromQuery && validTabs.includes(tabFromQuery)) {
    return tabFromQuery
  }
  return 'general'
}

const activeTabComponent = computed(() => tabComponents[activeTab.value] || tabComponents.general)

function switchTab(tabId) {
  activeTab.value = tabId
  router.replace({
    query: { ...route.query, tab: tabId === 'general' ? undefined : tabId }
  })
}

async function fetchSettings() {
  try {
    const { data } = await api.get('/settings')
    if (data.success) {
      settings.value = data.data.settings || {}
      serverTime.value = data.data.server_time || null
    }
  } catch (err) {
    console.error('Failed to fetch settings:', err)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>
