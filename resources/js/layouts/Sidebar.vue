<template>
  <aside
    :class="[
      'fixed inset-y-0 left-0 z-30 flex flex-col bg-gray-900 dark:bg-gray-950 transition-all duration-300 ease-in-out',
      collapsed ? 'w-16' : 'w-64'
    ]"
  >
    <!-- Logo -->
    <div class="flex items-center h-14 px-4 border-b border-gray-800">
      <div class="flex items-center space-x-3">
        <div class="w-8 h-8 bg-primary-600 rounded-lg flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
          </svg>
        </div>
        <span v-if="!collapsed" class="text-lg font-bold text-white">VSISPanel</span>
      </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-2">
      <div v-for="group in navigationGroups" :key="group.name" class="mb-1">
        <!-- Collapsed mode: Show items without group headers -->
        <template v-if="collapsed">
          <ul class="space-y-1 px-2">
            <li v-for="item in group.items" :key="item.name">
              <router-link
                v-if="shouldShowItem(item)"
                :to="{ name: item.route }"
                :class="[
                  'relative flex items-center justify-center px-3 py-2 rounded-lg transition-colors group',
                  isActive(item.route)
                    ? 'bg-primary-600 text-white'
                    : 'text-gray-400 hover:bg-gray-800 hover:text-white'
                ]"
                :title="$t(item.label)"
              >
                <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                <!-- Tooltip -->
                <div
                  class="absolute left-14 px-2 py-1 bg-gray-800 text-white text-sm rounded opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all whitespace-nowrap z-50 shadow-lg"
                >
                  {{ $t(item.label) }}
                </div>
              </router-link>
            </li>
          </ul>
        </template>

        <!-- Expanded mode: Show collapsible groups -->
        <template v-else>
          <!-- Group Header (Clickable) -->
          <button
            @click="toggleGroup(group.name)"
            class="w-full flex items-center justify-between px-4 py-2.5 text-left hover:bg-gray-800/50 transition-colors"
          >
            <span class="text-sm font-medium text-gray-300">
              {{ $t(group.label) }}
            </span>
            <ChevronDownIcon
              :class="[
                'w-4 h-4 text-gray-500 transition-transform duration-200',
                isGroupExpanded(group.name) ? '' : '-rotate-90'
              ]"
            />
          </button>

          <!-- Group Items (Collapsible) -->
          <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 max-h-0"
            enter-to-class="opacity-100 max-h-96"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 max-h-96"
            leave-to-class="opacity-0 max-h-0"
          >
            <ul v-show="isGroupExpanded(group.name)" class="space-y-0.5 px-2 pb-2 overflow-hidden">
              <li v-for="item in group.items" :key="item.name">
                <router-link
                  v-if="shouldShowItem(item)"
                  :to="{ name: item.route }"
                  :class="[
                    'flex items-center px-3 py-2 rounded-lg transition-colors',
                    isActive(item.route)
                      ? 'bg-primary-600 text-white'
                      : 'text-gray-400 hover:bg-gray-800 hover:text-white'
                  ]"
                >
                  <component :is="item.icon" class="w-5 h-5 flex-shrink-0" />
                  <span class="ml-3 text-sm">{{ $t(item.label) }}</span>
                </router-link>
              </li>
            </ul>
          </Transition>
        </template>
      </div>
    </nav>

    <!-- Collapse Toggle -->
    <div class="border-t border-gray-800 p-2">
      <button
        @click="toggleCollapse"
        class="w-full flex items-center justify-center px-3 py-2 text-gray-400 hover:bg-gray-800 hover:text-white rounded-lg transition-colors"
        :title="collapsed ? $t('sidebar.expand') : $t('sidebar.collapse')"
      >
        <ChevronLeftIcon v-if="!collapsed" class="w-5 h-5" />
        <ChevronRightIcon v-else class="w-5 h-5" />
      </button>
    </div>
  </aside>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useAuthStore } from '@/stores/auth'
import {
  HomeIcon,
  GlobeAltIcon,
  CircleStackIcon,
  FolderIcon,
  EnvelopeIcon,
  ServerStackIcon,
  LockClosedIcon,
  ShieldCheckIcon,
  CloudArrowUpIcon,
  ChartBarIcon,
  ClockIcon,
  CommandLineIcon,
  UsersIcon,
  CubeIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ChevronDownIcon,
  ArrowUpTrayIcon,
  UserCircleIcon,
  Cog6ToothIcon,
  WrenchScrewdriverIcon,
  ClipboardDocumentListIcon,
  PuzzlePieceIcon,
  BuildingOffice2Icon,
  Squares2X2Icon,
  BellAlertIcon,
  RocketLaunchIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const appStore = useAppStore()
const authStore = useAuthStore()

const collapsed = computed(() => appStore.sidebarCollapsed)

// Track expanded/collapsed state for each group
const expandedGroups = ref({})

// Navigation groups configuration
const navigationGroups = [
  {
    name: 'main',
    label: 'navGroups.main',
    items: [
      { name: 'dashboard', route: 'dashboard', label: 'nav.dashboard', icon: HomeIcon }
    ]
  },
  {
    name: 'websites',
    label: 'navGroups.websites',
    items: [
      { name: 'websites', route: 'websites', label: 'nav.websites', icon: GlobeAltIcon, permission: 'domains.view' },
      { name: 'databases', route: 'databases', label: 'nav.databases', icon: CircleStackIcon, permission: 'databases.view' },
      { name: 'files', route: 'files', label: 'nav.fileManager', icon: FolderIcon, permission: 'files.view' },
      { name: 'ftp', route: 'ftp', label: 'nav.ftp', icon: ArrowUpTrayIcon, permission: 'ftp.view' }
    ]
  },
  {
    name: 'security',
    label: 'navGroups.security',
    items: [
      { name: 'ssl', route: 'ssl', label: 'nav.ssl', icon: LockClosedIcon, permission: 'ssl.view' },
      { name: 'firewall', route: 'firewall', label: 'nav.firewall', icon: ShieldCheckIcon, permission: 'firewall.view' },
      { name: 'security', route: 'security', label: 'nav.security', icon: WrenchScrewdriverIcon, permission: 'security.view' },
      { name: 'backup', route: 'backup', label: 'nav.backup', icon: CloudArrowUpIcon, permission: 'backup.view' }
    ]
  },
  {
    name: 'server',
    label: 'navGroups.server',
    items: [
      { name: 'app-manager', route: 'app-manager', label: 'nav.appManager', icon: Squares2X2Icon, permission: 'monitoring.view' },
      { name: 'monitoring', route: 'monitoring', label: 'nav.monitoring', icon: ChartBarIcon, permission: 'monitoring.view' },
      { name: 'alerts', route: 'alerts', label: 'nav.alerts', icon: BellAlertIcon, permission: 'monitoring.view' },
      { name: 'email', route: 'email', label: 'nav.email', icon: EnvelopeIcon, permission: 'mail.view' },
      { name: 'dns', route: 'dns', label: 'nav.dns', icon: ServerStackIcon, permission: 'dns.view' },
      { name: 'cron', route: 'cron', label: 'nav.cronJobs', icon: ClockIcon, permission: 'cron.view' },
      { name: 'terminal', route: 'terminal', label: 'nav.terminal', icon: CommandLineIcon, permission: 'terminal.access' },
      { name: 'marketplace', route: 'marketplace', label: 'nav.marketplace', icon: RocketLaunchIcon },
      { name: 'tasks', route: 'tasks', label: 'nav.tasks', icon: ClipboardDocumentListIcon, permission: 'tasks.view' }
    ]
  },
  {
    name: 'admin',
    label: 'navGroups.admin',
    items: [
      { name: 'users', route: 'users', label: 'nav.users', icon: UsersIcon, permission: 'users.view' },
      { name: 'hosting', route: 'hosting', label: 'nav.hosting', icon: CubeIcon, permission: 'hosting.view' },
      { name: 'reseller', route: 'reseller', label: 'nav.reseller', icon: BuildingOffice2Icon, permission: 'reseller.view' },
      { name: 'settings', route: 'settings', label: 'nav.settings', icon: Cog6ToothIcon }
    ]
  }
]

// Initialize expanded state from localStorage or default all expanded
function initExpandedGroups() {
  const saved = localStorage.getItem('vsispanel_sidebar_groups')
  if (saved) {
    try {
      expandedGroups.value = JSON.parse(saved)
    } catch {
      // Default: expand all groups
      setAllGroupsExpanded()
    }
  } else {
    // Default: expand all groups
    setAllGroupsExpanded()
  }
}

function setAllGroupsExpanded() {
  navigationGroups.forEach(group => {
    expandedGroups.value[group.name] = true
  })
}

function saveExpandedGroups() {
  localStorage.setItem('vsispanel_sidebar_groups', JSON.stringify(expandedGroups.value))
}

function isGroupExpanded(groupName) {
  return expandedGroups.value[groupName] !== false
}

function toggleGroup(groupName) {
  expandedGroups.value[groupName] = !isGroupExpanded(groupName)
  saveExpandedGroups()
}

function isActive(routeName) {
  return route.name === routeName
}

function shouldShowItem(item) {
  if (!item.permission) return true
  return authStore.hasPermission(item.permission)
}

function toggleCollapse() {
  appStore.toggleSidebar()
}

// Auto-expand group when navigating to a route within it
watch(() => route.name, (newRouteName) => {
  if (!collapsed.value && newRouteName) {
    for (const group of navigationGroups) {
      const hasActiveItem = group.items.some(item => item.route === newRouteName)
      if (hasActiveItem && !isGroupExpanded(group.name)) {
        expandedGroups.value[group.name] = true
        saveExpandedGroups()
        break
      }
    }
  }
})

onMounted(() => {
  initExpandedGroups()
})
</script>
