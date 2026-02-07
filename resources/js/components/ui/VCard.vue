<template>
  <div :class="cardClasses">
    <div v-if="$slots.header || title" class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
      <slot name="header">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ title }}</h3>
          <div class="flex items-center gap-2">
            <slot name="headerRight" />
            <div v-if="$slots.actions">
              <slot name="actions" />
            </div>
          </div>
        </div>
        <p v-if="subtitle" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ subtitle }}</p>
      </slot>
    </div>

    <div :class="bodyClasses">
      <slot />
    </div>

    <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  title: {
    type: String,
    default: ''
  },
  subtitle: {
    type: String,
    default: ''
  },
  padding: {
    type: Boolean,
    default: true
  },
  shadow: {
    type: String,
    default: 'md',
    validator: (value) => ['none', 'sm', 'md', 'lg', 'xl'].includes(value)
  }
})

const shadowClasses = {
  none: '',
  sm: 'shadow-sm',
  md: 'shadow',
  lg: 'shadow-lg',
  xl: 'shadow-xl'
}

const cardClasses = computed(() => [
  'bg-white dark:bg-gray-800 rounded-lg overflow-hidden',
  shadowClasses[props.shadow]
])

const bodyClasses = computed(() => [
  props.padding ? 'p-6' : ''
])
</script>
