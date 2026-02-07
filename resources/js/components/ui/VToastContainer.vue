<template>
  <div class="fixed bottom-4 right-4 z-50 space-y-2">
    <TransitionGroup
      enter-active-class="transition ease-out duration-300"
      enter-from-class="transform translate-x-full opacity-0"
      enter-to-class="transform translate-x-0 opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="transform translate-x-0 opacity-100"
      leave-to-class="transform translate-x-full opacity-0"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        :class="toastClasses(toast.type)"
      >
        <div class="flex items-start">
          <component :is="getIcon(toast.type)" :class="iconClasses(toast.type)" />
          <div class="ml-3 flex-1">
            <p class="text-sm font-medium">{{ toast.message }}</p>
          </div>
          <button
            @click="removeToast(toast.id)"
            class="ml-4 inline-flex text-gray-400 hover:text-gray-500 focus:outline-none"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAppStore } from '@/stores/app'
import {
  CheckCircleIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline'

const appStore = useAppStore()

const toasts = computed(() => appStore.toasts)

function removeToast(id) {
  appStore.removeToast(id)
}

function getIcon(type) {
  const icons = {
    success: CheckCircleIcon,
    error: ExclamationCircleIcon,
    warning: ExclamationTriangleIcon,
    info: InformationCircleIcon
  }
  return icons[type] || InformationCircleIcon
}

function toastClasses(type) {
  const baseClasses = 'w-80 p-4 rounded-lg shadow-lg'
  const typeClasses = {
    success: 'bg-green-50 dark:bg-green-900/50 text-green-800 dark:text-green-200',
    error: 'bg-red-50 dark:bg-red-900/50 text-red-800 dark:text-red-200',
    warning: 'bg-yellow-50 dark:bg-yellow-900/50 text-yellow-800 dark:text-yellow-200',
    info: 'bg-blue-50 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200'
  }
  return `${baseClasses} ${typeClasses[type] || typeClasses.info}`
}

function iconClasses(type) {
  const baseClasses = 'w-5 h-5 flex-shrink-0'
  const typeClasses = {
    success: 'text-green-500',
    error: 'text-red-500',
    warning: 'text-yellow-500',
    info: 'text-blue-500'
  }
  return `${baseClasses} ${typeClasses[type] || typeClasses.info}`
}
</script>
