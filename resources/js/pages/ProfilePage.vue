<template>
  <PlaceholderPage
    :icon="UserIcon"
    :title="$t('nav.profile')"
    description="Manage your profile settings here."
  />
</template>

<script setup>
import PlaceholderPage from './PlaceholderPage.vue'
import { UserIcon } from '@heroicons/vue/24/outline'
</script>
