<template>
  <VCard>
    <VEmptyState
      :icon="icon"
      :title="title"
      :description="description"
    >
      <template #action>
        <VBadge variant="info">Coming Soon</VBadge>
      </template>
    </VEmptyState>
  </VCard>
</template>

<script setup>
import VCard from '@/components/ui/VCard.vue'
import VEmptyState from '@/components/ui/VEmptyState.vue'
import VBadge from '@/components/ui/VBadge.vue'

defineProps({
  icon: {
    type: [Object, Function],
    required: true
  },
  title: {
    type: String,
    required: true
  },
  description: {
    type: String,
    default: 'This feature will be available in upcoming updates.'
  }
})
</script>
