<template>
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center">
    <div class="text-center">
      <p class="text-6xl font-bold text-primary-600">404</p>
      <h1 class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">
        {{ $t('errors.notFound') }}
      </h1>
      <p class="mt-2 text-gray-500 dark:text-gray-400">
        The page you're looking for doesn't exist.
      </p>
      <div class="mt-6">
        <VButton variant="primary" @click="goHome">
          {{ $t('common.back') }} to Dashboard
        </VButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router'
import VButton from '@/components/ui/VButton.vue'

const router = useRouter()

function goHome() {
  router.push({ name: 'dashboard' })
}
</script>
