<template>
  <div>
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
        {{ $t('dns.title') }}
      </h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $t('dns.description') }}
      </p>
    </div>

    <!-- Zone Selector + Create Button -->
    <VCard class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            {{ $t('dns.zoneName') }}
          </label>
          <select
            v-model="selectedZoneId"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
            @change="onZoneChange"
          >
            <option value="">{{ $t('common.noData') }}</option>
            <option v-for="zone in zones" :key="zone.id" :value="zone.id">
              {{ zone.zone_name }}
            </option>
          </select>
        </div>
        <div class="sm:self-end">
          <VButton variant="primary" :icon="PlusIcon" @click="showCreateZoneModal = true">
            {{ $t('dns.createZone') }}
          </VButton>
        </div>
      </div>
    </VCard>

    <!-- Loading State -->
    <VLoadingSkeleton v-if="loading" class="h-96" />

    <!-- No Zone Selected -->
    <VCard v-else-if="!selectedZoneId" class="text-center py-12">
      <GlobeAltIcon class="w-16 h-16 mx-auto text-gray-400 mb-4" />
      <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
        {{ $t('dns.noZones') }}
      </h2>
      <p class="text-gray-500 dark:text-gray-400 mb-4">
        {{ $t('dns.noZonesDesc') }}
      </p>
      <VButton variant="primary" @click="showCreateZoneModal = true">
        {{ $t('dns.createFirst') }}
      </VButton>
    </VCard>

    <!-- Zone Content -->
    <template v-else-if="selectedZone">
      <!-- Zone Info Card -->
      <VCard class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ selectedZone.zone_name }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">
              {{ $t('dns.serial') }}: {{ selectedZone.serial }} |
              {{ $t('dns.recordsCount') }}: {{ records.length }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <VButton variant="secondary" size="sm" @click="showTemplateModal = true">
              <DocumentDuplicateIcon class="w-4 h-4 mr-1" />
              {{ $t('dns.templates') }}
            </VButton>
            <VButton variant="secondary" size="sm" @click="openImportModal">
              <ArrowUpTrayIcon class="w-4 h-4 mr-1" />
              {{ $t('dns.import') }}
            </VButton>
            <VButton variant="secondary" size="sm" @click="exportZone">
              <ArrowDownTrayIcon class="w-4 h-4 mr-1" />
              {{ $t('dns.export') }}
            </VButton>
            <VButton variant="secondary" size="sm" @click="openCloneModal" :disabled="otherZones.length === 0">
              <CloneIcon class="w-4 h-4 mr-1" />
              {{ $t('dns.clone') }}
            </VButton>
            <VButton variant="warning" size="sm" @click="confirmResetZone">
              <ArrowPathIcon class="w-4 h-4 mr-1" />
              {{ $t('dns.reset') }}
            </VButton>
            <VButton variant="danger" size="sm" @click="confirmDeleteZone">
              <TrashIcon class="w-4 h-4" />
            </VButton>
          </div>
        </div>
      </VCard>

      <!-- Bulk Actions Bar -->
      <div
        v-if="selectedRecords.length > 0"
        class="mb-4 p-3 bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 rounded-lg flex items-center justify-between"
      >
        <span class="text-sm font-medium text-primary-700 dark:text-primary-300">
          {{ $t('dns.selectedRecords', { count: selectedRecords.length }) }}
        </span>
        <div class="flex items-center gap-2">
          <VButton variant="secondary" size="sm" @click="selectedRecords = []">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton variant="danger" size="sm" @click="confirmBulkDelete">
            <TrashIcon class="w-4 h-4 mr-1" />
            {{ $t('dns.bulkDelete') }}
          </VButton>
        </div>
      </div>

      <!-- Records Actions -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <VInput
          v-model="search"
          :placeholder="$t('dns.searchRecords')"
          class="w-full sm:w-64"
        />
        <VButton variant="primary" :icon="PlusIcon" @click="openAddRecordModal">
          {{ $t('dns.addRecord') }}
        </VButton>
      </div>

      <!-- Records Table -->
      <VCard>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
              <tr>
                <th class="w-12 px-4 py-3">
                  <input
                    type="checkbox"
                    :checked="allSelected"
                    :indeterminate="selectedRecords.length > 0 && !allSelected"
                    @change="toggleSelectAll"
                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('dns.type') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('dns.name') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('dns.content') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('dns.ttl') }}
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('dns.status') }}
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                  {{ $t('common.actions') }}
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
              <tr v-if="filteredRecords.length === 0">
                <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                  {{ $t('dns.noRecords') }}
                </td>
              </tr>
              <tr v-for="record in filteredRecords" :key="record.id" class="hover:bg-gray-50 dark:hover:bg-gray-800">
                <td class="w-12 px-4 py-4">
                  <input
                    v-if="record.type !== 'SOA'"
                    type="checkbox"
                    :checked="selectedRecords.includes(record.id)"
                    @change="toggleRecordSelection(record.id)"
                    class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <VBadge :variant="getTypeVariant(record.type)">
                    {{ record.type }}
                  </VBadge>
                </td>
                <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-gray-900 dark:text-white">
                  {{ record.name }}
                </td>
                <td class="px-6 py-4 max-w-xs truncate font-mono text-sm text-gray-500 dark:text-gray-400" :title="record.content">
                  <span v-if="record.priority !== null" class="text-purple-600 dark:text-purple-400">{{ record.priority }} </span>
                  {{ record.content }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                  {{ record.ttl }}s
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <button
                    @click="toggleRecord(record)"
                    :class="[
                      'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2',
                      !record.disabled ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600'
                    ]"
                    :disabled="record.type === 'SOA'"
                  >
                    <span
                      :class="[
                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                        !record.disabled ? 'translate-x-5' : 'translate-x-0'
                      ]"
                    ></span>
                  </button>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right">
                  <div class="flex items-center justify-end space-x-2">
                    <VButton
                      v-if="record.type !== 'SOA'"
                      variant="secondary"
                      size="sm"
                      @click="editRecord(record)"
                    >
                      <PencilIcon class="w-4 h-4" />
                    </VButton>
                    <VButton
                      v-if="record.type !== 'SOA'"
                      variant="danger"
                      size="sm"
                      @click="confirmDeleteRecord(record)"
                    >
                      <TrashIcon class="w-4 h-4" />
                    </VButton>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </VCard>
    </template>

    <!-- Create Zone Modal -->
    <VModal v-model="showCreateZoneModal" :title="$t('dns.createZone')">
      <form @submit.prevent="createZone">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('websites.domain') }}
            </label>
            <select
              v-model="newZone.domain_id"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              required
            >
              <option value="">{{ $t('common.noData') }}</option>
              <option v-for="domain in domainsWithoutZones" :key="domain.id" :value="domain.id">
                {{ domain.name }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Server IP
            </label>
            <VInput
              v-model="newZone.server_ip"
              placeholder="192.168.1.1"
              pattern="^((25[0-5]|(2[0-4]|1\d|[1-9]|)\d)\.?\b){4}$"
              :title="$t('dns.ipHint')"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              {{ $t('dns.ipHint') }}
            </p>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showCreateZoneModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="creatingZone">
            {{ $t('common.create') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Add/Edit Record Modal -->
    <VModal v-model="showRecordModal" :title="editingRecord ? $t('dns.editRecord') : $t('dns.addRecord')">
      <form @submit.prevent="saveRecord">
        <div class="space-y-4">
          <div v-if="!editingRecord">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('dns.type') }}
            </label>
            <select
              v-model="recordForm.type"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              required
            >
              <option value="A">{{ $t('dns.types.A') }}</option>
              <option value="AAAA">{{ $t('dns.types.AAAA') }}</option>
              <option value="CNAME">{{ $t('dns.types.CNAME') }}</option>
              <option value="MX">{{ $t('dns.types.MX') }}</option>
              <option value="TXT">{{ $t('dns.types.TXT') }}</option>
              <option value="NS">{{ $t('dns.types.NS') }}</option>
              <option value="SRV">{{ $t('dns.types.SRV') }}</option>
              <option value="CAA">{{ $t('dns.types.CAA') }}</option>
            </select>
          </div>
          <div v-else class="mb-2">
            <VBadge :variant="getTypeVariant(recordForm.type)">{{ recordForm.type }}</VBadge>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('dns.name') }}
            </label>
            <VInput
              v-model="recordForm.name"
              :placeholder="$t('dns.namePlaceholder')"
              required
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('dns.content') }}
            </label>
            <VInput
              v-if="recordForm.type !== 'TXT'"
              v-model="recordForm.content"
              :placeholder="getContentPlaceholder(recordForm.type)"
              required
            />
            <textarea
              v-else
              v-model="recordForm.content"
              :placeholder="getContentPlaceholder(recordForm.type)"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              rows="3"
              required
            ></textarea>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('dns.ttl') }}
              </label>
              <VInput
                v-model.number="recordForm.ttl"
                type="number"
                min="60"
                max="86400"
              />
            </div>
            <div v-if="['MX', 'SRV'].includes(recordForm.type)">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('dns.priority') }}
              </label>
              <VInput
                v-model.number="recordForm.priority"
                type="number"
                min="0"
                max="65535"
              />
            </div>
          </div>
          <div v-if="recordForm.type === 'SRV'" class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('dns.weight') }}
              </label>
              <VInput
                v-model.number="recordForm.weight"
                type="number"
                min="0"
                max="65535"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                {{ $t('dns.port') }}
              </label>
              <VInput
                v-model.number="recordForm.port"
                type="number"
                min="0"
                max="65535"
              />
            </div>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showRecordModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="savingRecord">
            {{ $t('common.save') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Template Modal -->
    <VModal v-model="showTemplateModal" :title="$t('dns.applyTemplate')">
      <div class="space-y-4">
        <div v-if="templates.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
          {{ $t('dns.noTemplates') }}
        </div>
        <div
          v-for="template in templates"
          :key="template.name"
          @click="selectedTemplate = template.name"
          :class="[
            'p-4 border rounded-lg cursor-pointer transition-colors',
            selectedTemplate === template.name
              ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
              : 'border-gray-200 dark:border-gray-700 hover:border-primary-300'
          ]"
        >
          <h4 class="font-medium text-gray-900 dark:text-white">{{ template.label }}</h4>
          <p class="text-sm text-gray-500 dark:text-gray-400">{{ template.description }}</p>
          <p class="text-xs text-gray-400 mt-1">{{ template.records_count }} records</p>
        </div>
      </div>
      <div class="mt-6 flex justify-end gap-3">
        <VButton type="button" variant="secondary" @click="showTemplateModal = false">
          {{ $t('common.cancel') }}
        </VButton>
        <VButton
          variant="primary"
          :loading="applyingTemplate"
          :disabled="!selectedTemplate"
          @click="applyTemplate"
        >
          {{ $t('dns.applyTemplate') }}
        </VButton>
      </div>
    </VModal>

    <!-- Delete Zone Confirmation -->
    <VConfirmDialog
      v-model="showDeleteZoneConfirm"
      :title="$t('dns.deleteZone')"
      :message="$t('dns.deleteConfirm')"
      :loading="deletingZone"
      @confirm="deleteZone"
    />

    <!-- Delete Record Confirmation -->
    <VConfirmDialog
      v-model="showDeleteRecordConfirm"
      :title="$t('dns.deleteRecord')"
      :message="$t('dns.deleteRecordConfirm')"
      :loading="deletingRecord"
      @confirm="deleteRecord"
    />

    <!-- Import Zone Modal -->
    <VModal v-model="showImportModal" :title="$t('dns.importZone')">
      <form @submit.prevent="importZone">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('dns.zoneFileContent') }}
            </label>
            <textarea
              v-model="importForm.zone_file"
              :placeholder="$t('dns.zoneFilePlaceholder')"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 font-mono text-sm"
              rows="12"
              required
            ></textarea>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              {{ $t('dns.zoneFileHint') }}
            </p>
          </div>
          <div class="flex items-center">
            <input
              id="import-replace"
              v-model="importForm.replace"
              type="checkbox"
              class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            />
            <label for="import-replace" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
              {{ $t('dns.replaceExisting') }}
            </label>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showImportModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="importing">
            {{ $t('dns.import') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Clone Zone Modal -->
    <VModal v-model="showCloneModal" :title="$t('dns.cloneZone')">
      <form @submit.prevent="cloneZone">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              {{ $t('dns.targetZone') }}
            </label>
            <select
              v-model="cloneForm.target_zone_id"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500"
              required
            >
              <option value="">{{ $t('dns.selectTargetZone') }}</option>
              <option v-for="zone in otherZones" :key="zone.id" :value="zone.id">
                {{ zone.zone_name }}
              </option>
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              {{ $t('dns.cloneHint') }}
            </p>
          </div>
          <div class="flex items-center">
            <input
              id="clone-replace"
              v-model="cloneForm.replace"
              type="checkbox"
              class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
            />
            <label for="clone-replace" class="ml-2 text-sm text-gray-700 dark:text-gray-300">
              {{ $t('dns.replaceExisting') }}
            </label>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <VButton type="button" variant="secondary" @click="showCloneModal = false">
            {{ $t('common.cancel') }}
          </VButton>
          <VButton type="submit" variant="primary" :loading="cloning" :disabled="!cloneForm.target_zone_id">
            {{ $t('dns.clone') }}
          </VButton>
        </div>
      </form>
    </VModal>

    <!-- Reset Zone Confirmation -->
    <VConfirmDialog
      v-model="showResetConfirm"
      :title="$t('dns.resetZone')"
      :message="$t('dns.resetConfirm')"
      :loading="resetting"
      variant="warning"
      @confirm="resetZone"
    />

    <!-- Bulk Delete Confirmation -->
    <VConfirmDialog
      v-model="showBulkDeleteConfirm"
      :title="$t('dns.bulkDelete')"
      :message="$t('dns.bulkDeleteConfirm', { count: selectedRecords.length })"
      :loading="bulkDeleting"
      @confirm="bulkDeleteRecords"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'
import api from '@/utils/api'
import VCard from '@/components/ui/VCard.vue'
import VButton from '@/components/ui/VButton.vue'
import VInput from '@/components/ui/VInput.vue'
import VBadge from '@/components/ui/VBadge.vue'
import VModal from '@/components/ui/VModal.vue'
import VConfirmDialog from '@/components/ui/VConfirmDialog.vue'
import VLoadingSkeleton from '@/components/ui/VLoadingSkeleton.vue'
import {
  PlusIcon,
  TrashIcon,
  PencilIcon,
  GlobeAltIcon,
  DocumentDuplicateIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  DocumentDuplicateIcon as CloneIcon,
  ArrowPathIcon,
  CheckIcon
} from '@heroicons/vue/24/outline'

const { t } = useI18n()
const appStore = useAppStore()

// State
const loading = ref(false)
const zones = ref([])
const domains = ref([])
const records = ref([])
const templates = ref([])
const selectedZoneId = ref('')
const search = ref('')

// Modals
const showCreateZoneModal = ref(false)
const showRecordModal = ref(false)
const showTemplateModal = ref(false)
const showDeleteZoneConfirm = ref(false)
const showDeleteRecordConfirm = ref(false)
const showImportModal = ref(false)
const showCloneModal = ref(false)
const showResetConfirm = ref(false)
const showBulkDeleteConfirm = ref(false)

// Form states
const creatingZone = ref(false)
const savingRecord = ref(false)
const deletingZone = ref(false)
const deletingRecord = ref(false)
const applyingTemplate = ref(false)
const importing = ref(false)
const cloning = ref(false)
const resetting = ref(false)
const bulkDeleting = ref(false)

// Bulk selection
const selectedRecords = ref([])
const selectAll = ref(false)

// Form data
const newZone = ref({ domain_id: '', server_ip: '' })
const recordForm = ref({
  name: '@',
  type: 'A',
  content: '',
  ttl: 3600,
  priority: null,
  weight: null,
  port: null
})
const editingRecord = ref(null)
const recordToDelete = ref(null)
const selectedTemplate = ref('')
const importForm = ref({ zone_file: '', replace: false })
const cloneForm = ref({ target_zone_id: '', replace: false })

// Computed
const selectedZone = computed(() => zones.value.find(z => z.id === selectedZoneId.value))

const domainsWithoutZones = computed(() => {
  const zoneNames = zones.value.map(z => z.zone_name)
  return domains.value.filter(d => !zoneNames.includes(d.name))
})

const filteredRecords = computed(() => {
  if (!search.value) return records.value
  const s = search.value.toLowerCase()
  return records.value.filter(r =>
    r.name.toLowerCase().includes(s) ||
    r.content.toLowerCase().includes(s) ||
    r.type.toLowerCase().includes(s)
  )
})

// Selectable records (exclude SOA)
const selectableRecords = computed(() => filteredRecords.value.filter(r => r.type !== 'SOA'))

// Other zones (for clone target)
const otherZones = computed(() => zones.value.filter(z => z.id !== selectedZoneId.value))

// All selectable records selected?
const allSelected = computed(() => {
  if (selectableRecords.value.length === 0) return false
  return selectableRecords.value.every(r => selectedRecords.value.includes(r.id))
})

// Methods
function getTypeVariant(type) {
  const variants = {
    A: 'primary',
    AAAA: 'primary',
    CNAME: 'success',
    MX: 'purple',
    TXT: 'warning',
    NS: 'secondary',
    SRV: 'info',
    CAA: 'danger',
    SOA: 'secondary',
    PTR: 'secondary'
  }
  return variants[type] || 'secondary'
}

function getContentPlaceholder(type) {
  const placeholders = {
    A: '192.168.1.1',
    AAAA: '2001:db8::1',
    CNAME: 'target.example.com.',
    MX: 'mail.example.com.',
    TXT: 'v=spf1 mx ~all',
    NS: 'ns1.example.com.',
    SRV: 'target.example.com.',
    CAA: '0 issue letsencrypt.org'
  }
  return placeholders[type] || ''
}

async function fetchZones() {
  try {
    const response = await api.get('/dns/zones')
    zones.value = response.data.data
    if (zones.value.length > 0 && !selectedZoneId.value) {
      selectedZoneId.value = zones.value[0].id
      await fetchRecords()
    }
  } catch (err) {
    console.error('Failed to fetch zones:', err)
  }
}

async function fetchDomains() {
  try {
    const response = await api.get('/domains')
    domains.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch domains:', err)
  }
}

async function fetchRecords() {
  if (!selectedZoneId.value) {
    records.value = []
    return
  }

  loading.value = true
  try {
    const response = await api.get(`/dns/zones/${selectedZoneId.value}/records`)
    records.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch records:', err)
  } finally {
    loading.value = false
  }
}

async function fetchTemplates() {
  try {
    const response = await api.get('/dns/templates')
    templates.value = response.data.data
  } catch (err) {
    console.error('Failed to fetch templates:', err)
  }
}

async function createZone() {
  creatingZone.value = true
  try {
    const response = await api.post('/dns/zones', newZone.value)
    appStore.showToast({ type: 'success', message: t('dns.createSuccess') })
    showCreateZoneModal.value = false
    newZone.value = { domain_id: '', server_ip: '' }
    await fetchZones()
    selectedZoneId.value = response.data.data.id
    await fetchRecords()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('dns.createError') })
  } finally {
    creatingZone.value = false
  }
}

function confirmDeleteZone() {
  showDeleteZoneConfirm.value = true
}

async function deleteZone() {
  deletingZone.value = true
  try {
    await api.delete(`/dns/zones/${selectedZoneId.value}`)
    appStore.showToast({ type: 'success', message: t('dns.deleteSuccess') })
    showDeleteZoneConfirm.value = false
    selectedZoneId.value = ''
    records.value = []
    await fetchZones()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('dns.deleteError') })
  } finally {
    deletingZone.value = false
  }
}

function openAddRecordModal() {
  editingRecord.value = null
  recordForm.value = {
    name: '@',
    type: 'A',
    content: '',
    ttl: 3600,
    priority: null,
    weight: null,
    port: null
  }
  showRecordModal.value = true
}

function editRecord(record) {
  editingRecord.value = record
  recordForm.value = {
    name: record.name,
    type: record.type,
    content: record.content,
    ttl: record.ttl,
    priority: record.priority,
    weight: record.weight,
    port: record.port
  }
  showRecordModal.value = true
}

async function saveRecord() {
  savingRecord.value = true
  try {
    if (editingRecord.value) {
      await api.put(`/dns/records/${editingRecord.value.id}`, recordForm.value)
      appStore.showToast({ type: 'success', message: t('dns.recordUpdated') })
    } else {
      await api.post(`/dns/zones/${selectedZoneId.value}/records`, recordForm.value)
      appStore.showToast({ type: 'success', message: t('dns.recordCreated') })
    }
    showRecordModal.value = false
    await fetchRecords()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('dns.recordError') })
  } finally {
    savingRecord.value = false
  }
}

function confirmDeleteRecord(record) {
  recordToDelete.value = record
  showDeleteRecordConfirm.value = true
}

async function deleteRecord() {
  deletingRecord.value = true
  try {
    await api.delete(`/dns/records/${recordToDelete.value.id}`)
    appStore.showToast({ type: 'success', message: t('dns.recordDeleted') })
    showDeleteRecordConfirm.value = false
    await fetchRecords()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('dns.deleteError') })
  } finally {
    deletingRecord.value = false
  }
}

async function toggleRecord(record) {
  if (record.type === 'SOA') return

  try {
    await api.put(`/dns/records/${record.id}/toggle`)
    appStore.showToast({
      type: 'success',
      message: record.disabled ? t('dns.toggleEnabled') : t('dns.toggleDisabled')
    })
    await fetchRecords()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  }
}

async function exportZone() {
  try {
    const response = await api.get(`/dns/zones/${selectedZoneId.value}/export`)
    const blob = new Blob([response.data.data.content], { type: 'text/plain' })
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = response.data.data.filename
    a.click()
    window.URL.revokeObjectURL(url)
    appStore.showToast({ type: 'success', message: t('dns.exportSuccess') })
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('common.error') })
  }
}

async function applyTemplate() {
  if (!selectedTemplate.value) return

  applyingTemplate.value = true
  try {
    await api.post(`/dns/zones/${selectedZoneId.value}/apply-template`, {
      template: selectedTemplate.value,
      variables: {
        server_ip: selectedZone.value?.domain?.server_ip || ''
      }
    })
    appStore.showToast({ type: 'success', message: t('dns.templateApplied') })
    showTemplateModal.value = false
    selectedTemplate.value = ''
    await fetchRecords()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('dns.templateError') })
  } finally {
    applyingTemplate.value = false
  }
}

function onZoneChange() {
  fetchRecords()
  selectedRecords.value = []
}

// Bulk selection methods
function toggleSelectAll() {
  if (allSelected.value) {
    selectedRecords.value = []
  } else {
    selectedRecords.value = selectableRecords.value.map(r => r.id)
  }
}

function toggleRecordSelection(recordId) {
  const index = selectedRecords.value.indexOf(recordId)
  if (index > -1) {
    selectedRecords.value.splice(index, 1)
  } else {
    selectedRecords.value.push(recordId)
  }
}

function confirmBulkDelete() {
  if (selectedRecords.value.length === 0) return
  showBulkDeleteConfirm.value = true
}

async function bulkDeleteRecords() {
  bulkDeleting.value = true
  try {
    const response = await api.post(`/dns/zones/${selectedZoneId.value}/bulk-delete`, {
      record_ids: selectedRecords.value
    })
    appStore.showToast({
      type: 'success',
      message: t('dns.bulkDeleteSuccess', { count: response.data.data.deleted_count })
    })
    showBulkDeleteConfirm.value = false
    selectedRecords.value = []
    await fetchRecords()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('dns.bulkDeleteError') })
  } finally {
    bulkDeleting.value = false
  }
}

// Import zone
function openImportModal() {
  importForm.value = { zone_file: '', replace: false }
  showImportModal.value = true
}

async function importZone() {
  importing.value = true
  try {
    const response = await api.post(`/dns/zones/${selectedZoneId.value}/import`, importForm.value)
    appStore.showToast({
      type: 'success',
      message: t('dns.importSuccess', { count: response.data.data.imported_count })
    })
    showImportModal.value = false
    await fetchRecords()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('dns.importError') })
  } finally {
    importing.value = false
  }
}

// Clone zone
function openCloneModal() {
  cloneForm.value = { target_zone_id: '', replace: false }
  showCloneModal.value = true
}

async function cloneZone() {
  if (!cloneForm.value.target_zone_id) return

  cloning.value = true
  try {
    const response = await api.post(`/dns/zones/${selectedZoneId.value}/clone`, cloneForm.value)
    appStore.showToast({
      type: 'success',
      message: t('dns.cloneSuccess', { count: response.data.data.cloned_count })
    })
    showCloneModal.value = false
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('dns.cloneError') })
  } finally {
    cloning.value = false
  }
}

// Reset zone
function confirmResetZone() {
  showResetConfirm.value = true
}

async function resetZone() {
  resetting.value = true
  try {
    await api.post(`/dns/zones/${selectedZoneId.value}/reset`)
    appStore.showToast({ type: 'success', message: t('dns.resetSuccess') })
    showResetConfirm.value = false
    selectedRecords.value = []
    await fetchRecords()
  } catch (err) {
    appStore.showToast({ type: 'error', message: err.response?.data?.error?.message || t('dns.resetError') })
  } finally {
    resetting.value = false
  }
}

// Lifecycle
onMounted(async () => {
  loading.value = true
  await Promise.all([
    fetchZones(),
    fetchDomains(),
    fetchTemplates()
  ])
  loading.value = false
})
</script>
