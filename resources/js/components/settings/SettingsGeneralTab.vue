<template>
  <div class="space-y-6">
    <!-- Server Time -->
    <VCard>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        {{ $t('settings.serverTime') }}
      </h3>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Current Time -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('settings.currentTime') }}
          </label>
          <div class="flex items-center space-x-3">
            <div class="flex-1 px-3 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-gray-900 dark:text-white font-mono text-lg">
              {{ displayTime }}
            </div>
            <VButton
              variant="primary"
              size="sm"
              :loading="syncing"
              @click="syncTime"
            >
              {{ $t('settings.syncNow') }}
            </VButton>
          </div>
        </div>

        <!-- NTP Status -->
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('settings.ntpStatus') }}
          </label>
          <div class="flex items-center space-x-2 mt-2">
            <span
              :class="[
                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                serverTime?.ntp_enabled
                  ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                  : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
              ]"
            >
              {{ serverTime?.ntp_enabled ? $t('settings.ntpEnabled') : $t('settings.ntpDisabled') }}
            </span>
            <span
              v-if="serverTime?.ntp_synced"
              class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
            >
              {{ $t('settings.ntpSynced') }}
            </span>
          </div>
        </div>
      </div>
    </VCard>

    <!-- Timezone -->
    <VCard>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        {{ $t('settings.timezone') }}
      </h3>

      <div class="max-w-md">
        <div class="relative">
          <input
            v-model="timezoneSearch"
            type="text"
            :placeholder="$t('settings.timezoneSearch')"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 mb-2"
            @focus="showTimezoneDropdown = true"
          />
          <div
            v-if="showTimezoneDropdown && filteredTimezones.length > 0"
            class="absolute z-10 w-full max-h-60 overflow-auto bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-lg"
          >
            <button
              v-for="tz in filteredTimezones"
              :key="tz"
              class="w-full text-left px-3 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-900 dark:text-white"
              :class="{ 'bg-primary-50 dark:bg-primary-900/20 text-primary-600 dark:text-primary-400': tz === form.timezone }"
              @click="selectTimezone(tz)"
            >
              {{ tz }}
            </button>
          </div>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          {{ $t('settings.currentTimezone') }}: <span class="font-medium text-gray-900 dark:text-white">{{ form.timezone }}</span>
        </p>
      </div>
    </VCard>

    <!-- Panel Name -->
    <VCard>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
        {{ $t('settings.panelName') }}
      </h3>

      <div class="max-w-md">
        <input
          v-model="form.panel_name"
          type="text"
          :placeholder="$t('settings.panelNamePlaceholder')"
          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
        />
      </div>
    </VCard>

    <!-- Save Button -->
    <div class="flex justify-end">
      <VButton variant="primary" :loading="saving" @click="saveSettings">
        {{ $t('common.save') }}
      </VButton>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  serverTime: { type: Object, default: null },
})

const emit = defineEmits(['refresh'])

const { t } = useI18n()
const appStore = useAppStore()

// Form state
const form = ref({
  timezone: '',
  panel_name: '',
})
const saving = ref(false)
const syncing = ref(false)

// Timezone search
const timezoneSearch = ref('')
const showTimezoneDropdown = ref(false)
const allTimezones = ref([])

// Live clock
const displayTime = ref('')
let clockInterval = null

// Initialize form from props
watch(() => props.settings, (settings) => {
  if (settings?.general) {
    form.value.timezone = settings.general.timezone || 'UTC'
    form.value.panel_name = settings.general.panel_name || 'VSISPanel'
  }
}, { immediate: true })

// Live clock
function updateClock() {
  const tz = props.serverTime?.timezone || form.value.timezone || 'UTC'
  try {
    displayTime.value = new Date().toLocaleString('en-GB', {
      timeZone: tz,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: false,
    })
  } catch {
    displayTime.value = new Date().toLocaleString()
  }
}

// Timezone dropdown
const filteredTimezones = computed(() => {
  const search = timezoneSearch.value.toLowerCase()
  if (!search) return allTimezones.value.slice(0, 50)
  return allTimezones.value.filter(tz => tz.toLowerCase().includes(search)).slice(0, 50)
})

function selectTimezone(tz) {
  form.value.timezone = tz
  timezoneSearch.value = tz
  showTimezoneDropdown.value = false
}

async function fetchTimezones() {
  try {
    const { data } = await api.get('/settings/timezones')
    if (data.success) {
      const grouped = data.data
      allTimezones.value = Object.values(grouped).flat()
    }
  } catch (err) {
    console.error('Failed to fetch timezones:', err)
  }
}

async function syncTime() {
  syncing.value = true
  try {
    const { data } = await api.post('/settings/time/sync')
    if (data.success) {
      appStore.showToast({ type: 'success', message: t('settings.syncSuccess') })
      emit('refresh')
    }
  } catch (err) {
    appStore.showToast({
      type: 'error',
      message: err.response?.data?.error?.message || t('settings.syncError'),
    })
  } finally {
    syncing.value = false
  }
}

async function saveSettings() {
  saving.value = true
  try {
    const { data } = await api.put('/settings', {
      'general.timezone': form.value.timezone,
      'general.panel_name': form.value.panel_name,
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

// Close dropdown on outside click
function handleClickOutside(e) {
  if (!e.target.closest('.relative')) {
    showTimezoneDropdown.value = false
  }
}

onMounted(() => {
  fetchTimezones()
  updateClock()
  clockInterval = setInterval(updateClock, 1000)
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  if (clockInterval) clearInterval(clockInterval)
  document.removeEventListener('click', handleClickOutside)
})
</script>
