<template>
  <VModal
    :model-value="isVisible"
    :title="title"
    size="sm"
    @update:model-value="handleClose"
  >
    <div class="text-center">
      <div
        :class="[
          'mx-auto w-12 h-12 rounded-full flex items-center justify-center',
          iconBgClass
        ]"
      >
        <component :is="iconComponent" :class="['w-6 h-6', iconClass]" />
      </div>
      <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
        {{ message }}
      </p>
    </div>

    <template #footer>
      <div class="flex justify-end space-x-3">
        <VButton variant="secondary" @click="cancel">
          {{ cancelText }}
        </VButton>
        <VButton :variant="confirmVariant" :loading="loading" @click="confirm">
          {{ confirmText }}
        </VButton>
      </div>
    </template>
  </VModal>
</template>

<script setup>
import { computed } from 'vue'
import VModal from './VModal.vue'
import VButton from './VButton.vue'
import {
  ExclamationTriangleIcon,
  TrashIcon,
  QuestionMarkCircleIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  // Support both v-model and v-model:show
  modelValue: {
    type: Boolean,
    default: undefined
  },
  show: {
    type: Boolean,
    default: undefined
  },
  title: {
    type: String,
    default: 'Confirm'
  },
  message: {
    type: String,
    required: true
  },
  // Support both 'type' and 'variant' props
  type: {
    type: String,
    default: undefined,
    validator: (value) => ['warning', 'danger', 'info'].includes(value)
  },
  variant: {
    type: String,
    default: undefined,
    validator: (value) => ['warning', 'danger', 'info'].includes(value)
  },
  confirmText: {
    type: String,
    default: 'Confirm'
  },
  cancelText: {
    type: String,
    default: 'Cancel'
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue', 'update:show', 'confirm', 'cancel'])

// Computed to support both v-model and v-model:show
const isVisible = computed(() => props.modelValue ?? props.show ?? false)

// Computed to support both type and variant
const dialogType = computed(() => props.type ?? props.variant ?? 'warning')

const iconComponent = computed(() => {
  const icons = {
    warning: ExclamationTriangleIcon,
    danger: TrashIcon,
    info: QuestionMarkCircleIcon
  }
  return icons[dialogType.value]
})

const iconBgClass = computed(() => {
  const classes = {
    warning: 'bg-yellow-100 dark:bg-yellow-900/30',
    danger: 'bg-red-100 dark:bg-red-900/30',
    info: 'bg-blue-100 dark:bg-blue-900/30'
  }
  return classes[dialogType.value]
})

const iconClass = computed(() => {
  const classes = {
    warning: 'text-yellow-600 dark:text-yellow-400',
    danger: 'text-red-600 dark:text-red-400',
    info: 'text-blue-600 dark:text-blue-400'
  }
  return classes[dialogType.value]
})

const confirmVariant = computed(() => {
  return dialogType.value === 'danger' ? 'danger' : 'primary'
})

function handleClose(value) {
  emit('update:modelValue', value)
  emit('update:show', value)
}

function confirm() {
  emit('confirm')
}

function cancel() {
  handleClose(false)
  emit('cancel')
}
</script>
