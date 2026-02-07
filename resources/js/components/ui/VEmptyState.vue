<template>
  <div class="text-center py-12">
    <component
      v-if="icon"
      :is="icon"
      class="mx-auto w-12 h-12 text-gray-400 dark:text-gray-500"
    />
    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">
      {{ title }}
    </h3>
    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
      {{ description }}
    </p>
    <div v-if="$slots.action" class="mt-6">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  icon: {
    type: [Object, Function],
    default: null
  },
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    default: ''
  }
})
</script>
