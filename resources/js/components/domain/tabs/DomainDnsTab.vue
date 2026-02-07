<template>
  <VCard>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
        {{ $t('domainDetail.dnsRecords') }}
      </h3>
      <VButton variant="primary" size="sm" :icon="PlusIcon" @click="showAddRecordModal = true">
        {{ $t('dns.addRecord') }}
      </VButton>
    </div>

    <VLoadingSkeleton v-if="loading" class="h-48" />

    <template v-else-if="records.length > 0">
      <div class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                {{ $t('dns.type') }}
              </th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                {{ $t('dns.name') }}
              </th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                {{ $t('dns.content') }}
              </th>
              <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                TTL
              </th>
              <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                {{ $t('common.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
            <tr v-for="record in records" :key="record.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
              <td class="px-4 py-3">
                <VBadge :variant="getTypeVariant(record.type)" size="sm">
                  {{ record.type }}
                </VBadge>
              </td>
              <td class="px-4 py-3 font-mono text-sm text-gray-900 dark:text-white">
                {{ record.name }}
              </td>
              <td class="px-4 py-3 font-mono text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                {{ record.content }}
              </td>
              <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                {{ record.ttl }}
              </td>
              <td class="px-4 py-3 text-right">
                <div class="flex items-center justify-end space-x-1">
                  <VButton variant="ghost" size="sm" :icon="PencilIcon" @click="editRecord(record)" />
                  <VButton
                    variant="ghost"
                    size="sm"
                    :icon="TrashIcon"
                    class="text-red-500"
                    @click="confirmDelete(record)"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <VEmptyState
      v-else
      :title="$t('dns.noRecords')"
      :description="$t('dns.noRecordsDesc')"
      icon="GlobeAltIcon"
    >
      <VButton variant="primary" :icon="PlusIcon" @click="showAddRecordModal = true">
        {{ $t('dns.addFirstRecord') }}
      </VButton>
    </VEmptyState>

    <!-- Coming Soon Notice -->
    <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
      <div class="flex items-center">
        <InformationCircleIcon class="w-5 h-5 text-blue-500 mr-3" />
        <p class="text-sm text-blue-700 dark:text-blue-300">
          {{ $t('dns.comingSoon') }}
        </p>
      </div>
    </div>
  </VCard>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VEmptyState from '@/components/ui/VEmptyState.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  InformationCircleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  domain: {
    type: Object,
    required: true
  }
})

const { t } = useI18n()
const appStore = useAppStore()

// State
const records = ref([])
const loading = ref(false)
const showAddRecordModal = ref(false)

// Methods
function getTypeVariant(type) {
  switch (type) {
    case 'A': return 'primary'
    case 'AAAA': return 'info'
    case 'CNAME': return 'success'
    case 'MX': return 'warning'
    case 'TXT': return 'secondary'
    default: return 'secondary'
  }
}

async function fetchRecords() {
  loading.value = true
  try {
    // DNS module will be implemented in Phase 3
    // For now, just set empty array
    records.value = []
  } catch (err) {
    console.error('Failed to fetch DNS records:', err)
  } finally {
    loading.value = false
  }
}

function editRecord(record) {
  appStore.showToast({
    type: 'info',
    message: t('dns.comingSoon')
  })
}

function confirmDelete(record) {
  appStore.showToast({
    type: 'info',
    message: t('dns.comingSoon')
  })
}

onMounted(() => {
  fetchRecords()
})
</script>
