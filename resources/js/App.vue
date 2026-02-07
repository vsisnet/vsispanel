<template>
  <component :is="layout">
    <router-view />
  </component>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const route = useRoute()

const layout = computed(() => {
  if (route.meta.layout === 'auth') {
    return 'div' // AuthLayout is used inside LoginPage
  }
  return route.meta.requiresAuth ? AppLayout : 'div'
})
</script>
