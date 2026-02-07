<template>
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <!-- Logo -->
      <div class="text-center">
        <div class="flex items-center justify-center">
          <div class="w-12 h-12 bg-primary-600 rounded-lg flex items-center justify-center">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
            </svg>
          </div>
        </div>
        <h1 class="mt-4 text-3xl font-bold text-gray-900 dark:text-white">VSISPanel</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Web Hosting Control Panel</p>
      </div>

      <!-- Main Content -->
      <div class="bg-white dark:bg-gray-800 shadow-xl rounded-lg p-8">
        <slot />
      </div>

      <!-- Footer -->
      <div class="text-center text-sm text-gray-500 dark:text-gray-400">
        <p>&copy; {{ new Date().getFullYear() }} VSISPanel. All rights reserved.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
</script>
