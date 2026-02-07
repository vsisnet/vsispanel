<template>
  <div :class="skeletonClasses" class="animate-pulse bg-gray-200 dark:bg-gray-700 rounded"></div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  width: {
    type: String,
    default: 'full'
  },
  height: {
    type: String,
    default: '4'
  },
  rounded: {
    type: String,
    default: 'md',
    validator: (value) => ['none', 'sm', 'md', 'lg', 'full'].includes(value)
  }
})

const widthClasses = {
  full: 'w-full',
  '3/4': 'w-3/4',
  '1/2': 'w-1/2',
  '1/4': 'w-1/4',
  '1/3': 'w-1/3',
  '2/3': 'w-2/3'
}

const heightClasses = {
  '2': 'h-2',
  '3': 'h-3',
  '4': 'h-4',
  '5': 'h-5',
  '6': 'h-6',
  '8': 'h-8',
  '10': 'h-10',
  '12': 'h-12',
  '16': 'h-16',
  '20': 'h-20',
  '24': 'h-24',
  '32': 'h-32',
  '40': 'h-40',
  '48': 'h-48'
}

const roundedClasses = {
  none: 'rounded-none',
  sm: 'rounded-sm',
  md: 'rounded',
  lg: 'rounded-lg',
  full: 'rounded-full'
}

const skeletonClasses = computed(() => [
  widthClasses[props.width] || `w-[${props.width}]`,
  heightClasses[props.height] || `h-[${props.height}]`,
  roundedClasses[props.rounded]
])
</script>
