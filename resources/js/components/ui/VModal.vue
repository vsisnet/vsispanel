<template>
  <TransitionRoot appear :show="isVisible" as="template">
    <Dialog as="div" class="relative z-50" @close="close">
      <TransitionChild
        as="template"
        enter="duration-300 ease-out"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="duration-200 ease-in"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black/50 dark:bg-black/70" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center">
          <TransitionChild
            as="template"
            enter="duration-300 ease-out"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="duration-200 ease-in"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel :class="panelClasses">
              <!-- Header -->
              <div v-if="title || $slots.header" class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                <slot name="header">
                  <div class="flex items-center justify-between">
                    <DialogTitle as="h3" class="text-lg font-semibold text-gray-900 dark:text-white">
                      {{ title }}
                    </DialogTitle>
                    <button
                      v-if="closable"
                      @click="close"
                      class="p-1 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                    >
                      <XMarkIcon class="w-5 h-5" />
                    </button>
                  </div>
                </slot>
              </div>

              <!-- Body -->
              <div class="px-6 py-4 flex-1 min-h-0 overflow-y-auto">
                <slot />
              </div>

              <!-- Footer -->
              <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 flex-shrink-0">
                <slot name="footer" />
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import { computed } from 'vue'
import {
  TransitionRoot,
  TransitionChild,
  Dialog,
  DialogPanel,
  DialogTitle
} from '@headlessui/vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

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
    default: ''
  },
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg', 'xl', 'full'].includes(value)
  },
  closable: {
    type: Boolean,
    default: true
  },
  closeOnClickOutside: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits(['update:modelValue', 'update:show', 'close'])

// Computed to support both v-model and v-model:show
const isVisible = computed(() => props.modelValue ?? props.show ?? false)

const sizeClasses = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  full: 'max-w-4xl'
}

const panelClasses = computed(() => [
  'w-full transform rounded-lg bg-white dark:bg-gray-800 text-left align-middle shadow-xl transition-all max-h-[90vh] flex flex-col overflow-hidden',
  sizeClasses[props.size]
])

function close() {
  if (props.closeOnClickOutside) {
    emit('update:modelValue', false)
    emit('update:show', false)
    emit('close')
  }
}
</script>
