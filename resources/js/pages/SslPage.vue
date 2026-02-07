<template>
  <PlaceholderPage
    :icon="LockClosedIcon"
    :title="$t('nav.ssl')"
    description="Manage your SSL certificates here."
  />
</template>

<script setup>
import PlaceholderPage from './PlaceholderPage.vue'
import { LockClosedIcon } from '@heroicons/vue/24/outline'
</script>
