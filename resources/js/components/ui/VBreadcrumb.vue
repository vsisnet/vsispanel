<template>
  <nav class="flex mb-4" aria-label="Breadcrumb">
    <ol class="inline-flex items-center space-x-1 md:space-x-2">
      <li class="inline-flex items-center">
        <router-link
          :to="{ name: 'dashboard' }"
          class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
          <HomeIcon class="w-4 h-4 mr-1" />
          {{ $t('nav.dashboard') }}
        </router-link>
      </li>
      <li v-for="(crumb, index) in resolvedBreadcrumbs" :key="index" class="inline-flex items-center">
        <ChevronRightIcon class="w-4 h-4 text-gray-400 mx-1" />
        <router-link
          v-if="crumb.to"
          :to="crumb.to"
          class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
          {{ crumb.label }}
        </router-link>
        <span v-else class="text-sm font-medium text-gray-700 dark:text-gray-200">
          {{ crumb.label }}
        </span>
      </li>
    </ol>
  </nav>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { HomeIcon, ChevronRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  items: {
    type: Array,
    default: null
  }
})

const route = useRoute()
const { t } = useI18n()

const resolvedBreadcrumbs = computed(() => {
  if (props.items) return props.items

  const crumbs = []
  if (route.meta.title) {
    crumbs.push({ label: t(route.meta.title), to: null })
  }
  return crumbs
})
</script>
