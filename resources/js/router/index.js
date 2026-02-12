import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Lazy load pages
const LoginPage = () => import('@/pages/LoginPage.vue')
const DashboardPage = () => import('@/pages/DashboardPage.vue')
const WebsitesPage = () => import('@/pages/WebsitesPage.vue')
const DomainDetailPage = () => import('@/pages/DomainDetailPage.vue')
const DatabasesPage = () => import('@/pages/DatabasesPage.vue')
const FileManagerPage = () => import('@/pages/FileManagerPage.vue')
const EmailPage = () => import('@/pages/EmailPage.vue')
const DnsPage = () => import('@/pages/DnsPage.vue')
const FtpPage = () => import('@/pages/FtpPage.vue')
const SslPage = () => import('@/pages/SslPage.vue')
const FirewallPage = () => import('@/pages/FirewallPage.vue')
const SecurityPage = () => import('@/pages/SecurityPage.vue')
const BackupPage = () => import('@/pages/BackupPage.vue')
const BackupConfigPage = () => import('@/pages/BackupConfigPage.vue')
const RestorePage = () => import('@/pages/RestorePage.vue')
const MonitoringPage = () => import('@/pages/MonitoringPage.vue')
const CronPage = () => import('@/pages/CronPage.vue')
const TerminalPage = () => import('@/pages/TerminalPage.vue')
const TaskPage = () => import('@/pages/TaskPage.vue')
const HostingPage = () => import('@/pages/HostingPage.vue')
const ResellerPage = () => import('@/pages/ResellerPage.vue')
const MarketplacePage = () => import('@/pages/MarketplacePage.vue')
const AppManagerPage = () => import('@/pages/AppManagerPage.vue')
const AppManagerDetailPage = () => import('@/pages/AppManagerDetailPage.vue')
const AlertsPage = () => import('@/pages/AlertsPage.vue')
const UsersPage = () => import('@/pages/UsersPage.vue')
const SettingsPage = () => import('@/pages/SettingsPage.vue')
const ProfilePage = () => import('@/pages/ProfilePage.vue')
const NotFoundPage = () => import('@/pages/NotFoundPage.vue')
const SetupWizardPage = () => import('@/pages/SetupWizardPage.vue')
const AboutPage = () => import('@/pages/AboutPage.vue')
const MigrationPage = () => import('@/pages/MigrationPage.vue')
const ForgotPasswordPage = () => import('@/pages/ForgotPasswordPage.vue')
const ResetPasswordPage = () => import('@/pages/ResetPasswordPage.vue')

const routes = [
  // Setup wizard (before installation)
  {
    path: '/setup',
    name: 'setup',
    component: SetupWizardPage,
    meta: { layout: 'auth' }
  },

  // Public routes
  {
    path: '/login',
    name: 'login',
    component: LoginPage,
    meta: { guest: true, layout: 'auth' }
  },
  {
    path: "/forgot-password",
    name: "forgot-password",
    component: ForgotPasswordPage,
    meta: { guest: true, layout: "auth" }
  },
  {
    path: "/reset-password/:token",
    name: "reset-password",
    component: ResetPasswordPage,
    meta: { guest: true, layout: "auth" }
  },

  // Protected routes
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardPage,
    meta: { requiresAuth: true, title: 'nav.dashboard' }
  },
  {
    path: '/websites',
    name: 'websites',
    component: WebsitesPage,
    meta: { requiresAuth: true, title: 'nav.websites', permission: 'domains.view' }
  },
  {
    path: '/websites/:id',
    name: 'domain-detail',
    component: DomainDetailPage,
    meta: { requiresAuth: true, title: 'nav.websites', permission: 'domains.view' }
  },
  {
    path: '/databases',
    name: 'databases',
    component: DatabasesPage,
    meta: { requiresAuth: true, title: 'nav.databases', permission: 'databases.view' }
  },
  {
    path: '/files',
    name: 'files',
    component: FileManagerPage,
    meta: { requiresAuth: true, title: 'nav.fileManager', permission: 'files.view' }
  },
  {
    path: '/email',
    name: 'email',
    component: EmailPage,
    meta: { requiresAuth: true, title: 'nav.email', permission: 'mail.view' }
  },
  {
    path: '/dns',
    name: 'dns',
    component: DnsPage,
    meta: { requiresAuth: true, title: 'nav.dns', permission: 'dns.view' }
  },
  {
    path: '/ftp',
    name: 'ftp',
    component: FtpPage,
    meta: { requiresAuth: true, title: 'nav.ftp', permission: 'ftp.view' }
  },
  {
    path: '/ssl',
    name: 'ssl',
    component: SslPage,
    meta: { requiresAuth: true, title: 'nav.ssl', permission: 'ssl.view' }
  },
  {
    path: '/firewall',
    name: 'firewall',
    component: FirewallPage,
    meta: { requiresAuth: true, title: 'nav.firewall', permission: 'firewall.view' }
  },
  {
    path: '/security',
    name: 'security',
    component: SecurityPage,
    meta: { requiresAuth: true, title: 'security.title', permission: 'security.view' }
  },
  {
    path: '/backup',
    name: 'backup',
    component: BackupPage,
    meta: { requiresAuth: true, title: 'nav.backup', permission: 'backup.view' }
  },
  {
    path: '/backup/config/new',
    name: 'backup-config-new',
    component: BackupConfigPage,
    meta: { requiresAuth: true, title: 'backup.addConfig', permission: 'backup.manage' }
  },
  {
    path: '/backup/config/:id/edit',
    name: 'backup-config-edit',
    component: BackupConfigPage,
    meta: { requiresAuth: true, title: 'backup.editConfig', permission: 'backup.manage' }
  },
  {
    path: '/backup/:id/restore',
    name: 'backup-restore',
    component: RestorePage,
    meta: { requiresAuth: true, title: 'backup.restore', permission: 'backup.manage' }
  },
  {
    path: '/app-manager',
    name: 'app-manager',
    component: AppManagerPage,
    meta: { requiresAuth: true, title: 'nav.appManager', permission: 'monitoring.view' }
  },
  {
    path: '/app-manager/:slug',
    name: 'app-manager-detail',
    component: AppManagerDetailPage,
    meta: { requiresAuth: true, title: 'nav.appManager', permission: 'monitoring.view' }
  },
  {
    path: '/monitoring',
    name: 'monitoring',
    component: MonitoringPage,
    meta: { requiresAuth: true, title: 'nav.monitoring', permission: 'monitoring.view' }
  },
  {
    path: '/alerts',
    name: 'alerts',
    component: AlertsPage,
    meta: { requiresAuth: true, title: 'nav.alerts', permission: 'monitoring.view' }
  },
  {
    path: '/cron',
    name: 'cron',
    component: CronPage,
    meta: { requiresAuth: true, title: 'nav.cronJobs', permission: 'cron.view' }
  },
  {
    path: '/terminal',
    name: 'terminal',
    component: TerminalPage,
    meta: { requiresAuth: true, title: 'nav.terminal', permission: 'terminal.access' }
  },
  {
    path: '/tasks',
    name: 'tasks',
    component: TaskPage,
    meta: { requiresAuth: true, title: 'nav.tasks', permission: 'tasks.view' }
  },

  // Admin routes
  {
    path: '/users',
    name: 'users',
    component: UsersPage,
    meta: { requiresAuth: true, title: 'nav.users', permission: 'users.view' }
  },
  {
    path: '/hosting',
    name: 'hosting',
    component: HostingPage,
    meta: { requiresAuth: true, title: 'hosting.title', permission: 'hosting.view' }
  },
  {
    path: '/reseller',
    name: 'reseller',
    component: ResellerPage,
    meta: { requiresAuth: true, title: 'reseller.title', permission: 'reseller.view' }
  },
  {
    path: '/marketplace',
    name: 'marketplace',
    component: MarketplacePage,
    meta: { requiresAuth: true, title: 'marketplace.title' }
  },

  // Settings & Profile
  {
    path: '/settings',
    name: 'settings',
    component: SettingsPage,
    meta: { requiresAuth: true, title: 'nav.settings' }
  },
  {
    path: '/profile',
    name: 'profile',
    component: ProfilePage,
    meta: { requiresAuth: true, title: 'nav.profile' }
  },

  // Migration
  {
    path: '/migration',
    name: 'migration',
    component: MigrationPage,
    meta: { requiresAuth: true, title: 'migration.title' }
  },

  // About
  {
    path: '/about',
    name: 'about',
    component: AboutPage,
    meta: { requiresAuth: true, title: 'about.title' }
  },

  // 404
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: NotFoundPage,
    meta: { title: 'errors.notFound' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()

  // Try to fetch user if we have a token but no user data
  if (authStore.token && !authStore.user) {
    try {
      await authStore.fetchUser()
    } catch (error) {
      // Token is invalid, redirect to login
      if (to.meta.requiresAuth) {
        return next({ name: 'login', query: { redirect: to.fullPath } })
      }
    }
  }

  // Check if route requires authentication
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  // Check if route is for guests only (login page)
  if (to.meta.guest && authStore.isAuthenticated) {
    return next({ name: 'dashboard' })
  }

  // Check permissions
  if (to.meta.permission && !authStore.hasPermission(to.meta.permission)) {
    // User doesn't have required permission
    return next({ name: 'dashboard' })
  }

  next()
})

export default router
