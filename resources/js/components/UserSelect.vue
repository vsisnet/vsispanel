<template>
  <div v-if="authStore.isAdmin" class="mb-4">
    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
      {{ label || 'Assign to User' }}
    </label>
    <select
      :value="modelValue"
      @change="$emit('update:modelValue', $event.target.value)"
      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
    >
      <option value="">-- Current Admin --</option>
      <option v-for="user in users" :key="user.id" :value="user.id">
        {{ user.name || user.username || user.email }} ({{ user.role }})
      </option>
    </select>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/utils/api'

defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: '' }
})
defineEmits(['update:modelValue'])

const authStore = useAuthStore()
const users = ref([])

onMounted(async () => {
  if (authStore.isAdmin) {
    try {
      const { data } = await api.get('/users/select')
      users.value = data.data || data || []
    } catch (e) {
      console.error('Failed to load users:', e)
    }
  }
})
</script>
