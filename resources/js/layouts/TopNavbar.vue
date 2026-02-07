<template>
  <header
    :class="[
      'fixed top-0 right-0 z-20 h-14 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-4 transition-all duration-300',
      sidebarCollapsed ? 'left-16' : 'left-64'
    ]"
  >
    <!-- Left Section -->
    <div class="flex items-center space-x-4">
      <!-- Hamburger Menu -->
      <button
        @click="toggleSidebar"
        class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
      >
        <Bars3Icon class="w-5 h-5" />
      </button>

      <!-- Search -->
      <div class="relative hidden md:block">
        <input
          type="text"
          :placeholder="$t('common.search') + '... (Ctrl+K)'"
          class="w-64 pl-10 pr-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 border-0 rounded-lg focus:ring-2 focus:ring-primary-500 dark:text-gray-200 dark:placeholder-gray-400"
          @keydown.ctrl.k.prevent="focusSearch"
          ref="searchInput"
        />
        <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
      </div>
    </div>

    <!-- Right Section -->
    <div class="flex items-center space-x-3">
      <!-- Language Switcher -->
      <Menu as="div" class="relative">
        <MenuButton class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
          <LanguageIcon class="w-5 h-5" />
        </MenuButton>
        <transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <MenuItems class="absolute right-0 mt-2 w-32 origin-top-right bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
            <div class="py-1">
              <MenuItem v-slot="{ active }">
                <button
                  @click="changeLocale('vi')"
                  :class="[
                    active ? 'bg-gray-100 dark:bg-gray-700' : '',
                    locale === 'vi' ? 'text-primary-600' : 'text-gray-700 dark:text-gray-200',
                    'flex items-center w-full px-4 py-2 text-sm'
                  ]"
                >
                  <span class="mr-2">🇻🇳</span> Tiếng Việt
                </button>
              </MenuItem>
              <MenuItem v-slot="{ active }">
                <button
                  @click="changeLocale('en')"
                  :class="[
                    active ? 'bg-gray-100 dark:bg-gray-700' : '',
                    locale === 'en' ? 'text-primary-600' : 'text-gray-700 dark:text-gray-200',
                    'flex items-center w-full px-4 py-2 text-sm'
                  ]"
                >
                  <span class="mr-2">🇺🇸</span> English
                </button>
              </MenuItem>
            </div>
          </MenuItems>
        </transition>
      </Menu>

      <!-- Dark Mode Toggle -->
      <button
        @click="toggleDarkMode"
        class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
      >
        <SunIcon v-if="darkMode" class="w-5 h-5" />
        <MoonIcon v-else class="w-5 h-5" />
      </button>

      <!-- Notifications -->
      <Menu as="div" class="relative">
        <MenuButton class="relative p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
          <BellIcon class="w-5 h-5" />
          <span
            v-if="notificationCount > 0"
            class="absolute top-1 right-1 w-4 h-4 bg-red-500 text-white text-xs rounded-full flex items-center justify-center"
          >
            {{ notificationCount > 9 ? '9+' : notificationCount }}
          </span>
        </MenuButton>
        <transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <MenuItems class="absolute right-0 mt-2 w-80 origin-top-right bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
            <div class="p-4">
              <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</h3>
              <div v-if="notifications.length === 0" class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400 py-4">
                {{ $t('common.noData') }}
              </div>
              <div v-else class="mt-2 space-y-2 max-h-64 overflow-y-auto">
                <div
                  v-for="notification in notifications.slice(0, 5)"
                  :key="notification.id"
                  class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer"
                >
                  <p class="text-sm text-gray-700 dark:text-gray-200">{{ notification.message }}</p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ notification.createdAt }}</p>
                </div>
              </div>
            </div>
          </MenuItems>
        </transition>
      </Menu>

      <!-- User Menu -->
      <Menu as="div" class="relative">
        <MenuButton class="flex items-center space-x-2 p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
          <div class="w-8 h-8 bg-primary-600 rounded-full flex items-center justify-center">
            <span class="text-sm font-medium text-white">{{ userInitials }}</span>
          </div>
          <div class="hidden md:block text-left">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">{{ authStore.userName }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ authStore.userRole }}</p>
          </div>
          <ChevronDownIcon class="w-4 h-4 text-gray-500 hidden md:block" />
        </MenuButton>
        <transition
          enter-active-class="transition ease-out duration-100"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-75"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <MenuItems class="absolute right-0 mt-2 w-48 origin-top-right bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
            <div class="py-1">
              <MenuItem v-slot="{ active }">
                <router-link
                  :to="{ name: 'profile' }"
                  :class="[
                    active ? 'bg-gray-100 dark:bg-gray-700' : '',
                    'flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200'
                  ]"
                >
                  <UserIcon class="w-4 h-4 mr-3" />
                  {{ $t('nav.profile') }}
                </router-link>
              </MenuItem>
              <MenuItem v-slot="{ active }">
                <router-link
                  :to="{ name: 'settings' }"
                  :class="[
                    active ? 'bg-gray-100 dark:bg-gray-700' : '',
                    'flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-200'
                  ]"
                >
                  <Cog6ToothIcon class="w-4 h-4 mr-3" />
                  {{ $t('nav.settings') }}
                </router-link>
              </MenuItem>
              <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
              <MenuItem v-slot="{ active }">
                <button
                  @click="logout"
                  :class="[
                    active ? 'bg-gray-100 dark:bg-gray-700' : '',
                    'flex items-center w-full px-4 py-2 text-sm text-red-600 dark:text-red-400'
                  ]"
                >
                  <ArrowRightOnRectangleIcon class="w-4 h-4 mr-3" />
                  {{ $t('auth.logout') }}
                </button>
              </MenuItem>
            </div>
          </MenuItems>
        </transition>
      </Menu>
    </div>
  </header>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue'
import { useAppStore } from '@/stores/app'
import { useAuthStore } from '@/stores/auth'
import {
  Bars3Icon,
  MagnifyingGlassIcon,
  BellIcon,
  SunIcon,
  MoonIcon,
  LanguageIcon,
  UserIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
  ChevronDownIcon
} from '@heroicons/vue/24/outline'

const router = useRouter()
const appStore = useAppStore()
const authStore = useAuthStore()

const searchInput = ref(null)

const sidebarCollapsed = computed(() => appStore.sidebarCollapsed)
const darkMode = computed(() => appStore.darkMode)
const locale = computed(() => appStore.locale)
const notifications = computed(() => appStore.notifications)
const notificationCount = computed(() => appStore.notificationCount)

const userInitials = computed(() => {
  const name = authStore.userName
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2)
})

function toggleSidebar() {
  appStore.toggleSidebar()
}

function toggleDarkMode() {
  appStore.toggleDarkMode()
}

function changeLocale(newLocale) {
  appStore.changeLocale(newLocale)
}

function focusSearch() {
  searchInput.value?.focus()
}

async function logout() {
  await authStore.logout()
  router.push({ name: 'login' })
}
</script>
