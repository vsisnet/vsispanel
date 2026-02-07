<template>
  <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <!-- Sidebar -->
    <Sidebar />

    <!-- Top Navbar -->
    <TopNavbar />

    <!-- Main Content -->
    <main
      :class="[
        'pt-14 min-h-screen transition-all duration-300',
        sidebarCollapsed ? 'pl-16' : 'pl-64'
      ]"
    >
      <div class="p-6">
        <!-- Breadcrumb -->
        <VBreadcrumb v-if="showBreadcrumb" class="mb-4" />

        <!-- Page Content -->
        <slot />
      </div>

      <!-- Footer -->
      <footer class="px-6 py-4 text-center text-xs text-gray-400 dark:text-gray-600">
        <router-link to="/about" class="hover:text-gray-600 dark:hover:text-gray-400 transition-colors">
          Powered by VSISPanel v1.0.0
        </router-link>
      </footer>
    </main>

    <!-- Toast Container -->
    <VToastContainer />
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'
import Sidebar from './Sidebar.vue'
import TopNavbar from './TopNavbar.vue'
import VBreadcrumb from '@/components/ui/VBreadcrumb.vue'
import VToastContainer from '@/components/ui/VToastContainer.vue'

const route = useRoute()
const appStore = useAppStore()

const sidebarCollapsed = computed(() => appStore.sidebarCollapsed)
const showBreadcrumb = computed(() => route.name !== 'dashboard')
</script>
