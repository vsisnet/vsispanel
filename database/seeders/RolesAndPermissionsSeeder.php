<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions grouped by module
        $permissions = [
            // Server module
            'server.view',
            'server.manage',
            'server.services.restart',

            // Domain module
            'domains.view',
            'domains.create',
            'domains.edit',
            'domains.delete',
            'domains.manage-all',

            // Hosting module - Plans (admin only)
            'plans.view',
            'plans.create',
            'plans.edit',
            'plans.delete',

            // Hosting module - Subscriptions
            'subscriptions.view',
            'subscriptions.create',
            'subscriptions.edit',
            'subscriptions.delete',
            'subscriptions.suspend',
            'subscriptions.renew',
            'subscriptions.manage-all',

            // Database module
            'databases.view',
            'databases.create',
            'databases.edit',
            'databases.delete',
            'databases.manage-all',

            // Database Users module
            'database_users.view',
            'database_users.create',
            'database_users.edit',
            'database_users.delete',
            'database_users.manage-all',

            // Mail module
            'mail.view',
            'mail.create',
            'mail.delete',
            'mail.manage-all',

            // DNS module
            'dns.view',
            'dns.edit',
            'dns.manage-all',

            // SSL module
            'ssl.view',
            'ssl.create',
            'ssl.edit',
            'ssl.delete',
            'ssl.renew',
            'ssl.manage-all',

            // File Manager module
            'files.view',
            'files.edit',
            'files.upload',

            // FTP module
            'ftp.view',
            'ftp.create',
            'ftp.delete',

            // Backup module
            'backup.view',
            'backup.create',
            'backup.restore',
            'backup.manage-all',

            // Firewall module
            'firewall.view',
            'firewall.manage',

            // Monitoring module
            'monitoring.view',
            'monitoring.manage',

            // Cron module
            'cron.view',
            'cron.create',
            'cron.edit',
            'cron.delete',

            // Users module
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.impersonate',

            // Reseller module
            'reseller.manage-customers',
            'reseller.manage-plans',
            'reseller.view-reports',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // Create roles and assign permissions
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'sanctum']);
        $resellerRole = Role::create(['name' => 'reseller', 'guard_name' => 'sanctum']);
        $userRole = Role::create(['name' => 'user', 'guard_name' => 'sanctum']);

        // Admin gets all permissions
        $adminRole->givePermissionTo(Permission::all());

        // Reseller permissions
        $resellerPermissions = [
            // Subscriptions - view own
            'subscriptions.view',

            // Domains - full access to own
            'domains.view',
            'domains.create',
            'domains.edit',
            'domains.delete',

            // Databases - full access to own
            'databases.view',
            'databases.create',
            'databases.edit',
            'databases.delete',

            // Database Users - full access to own
            'database_users.view',
            'database_users.create',
            'database_users.edit',
            'database_users.delete',

            // Mail - full access to own
            'mail.view',
            'mail.create',
            'mail.delete',

            // DNS - edit own
            'dns.view',
            'dns.edit',

            // SSL - manage own
            'ssl.view',
            'ssl.create',
            'ssl.edit',
            'ssl.delete',
            'ssl.renew',

            // Files - full access
            'files.view',
            'files.edit',
            'files.upload',

            // FTP - full access to own
            'ftp.view',
            'ftp.create',
            'ftp.delete',

            // Backup - view, create, restore (not manage-all)
            'backup.view',
            'backup.create',
            'backup.restore',

            // Cron - full access to own
            'cron.view',
            'cron.create',
            'cron.edit',
            'cron.delete',

            // Reseller specific
            'reseller.manage-customers',
            'reseller.manage-plans',
            'reseller.view-reports',

            // Users - view only (own customers)
            'users.view',
        ];
        $resellerRole->givePermissionTo($resellerPermissions);

        // User permissions (end-user/customer)
        $userPermissions = [
            // Subscriptions - view own
            'subscriptions.view',

            // Domains - full CRUD on own domains (not manage-all)
            'domains.view',
            'domains.create',
            'domains.edit',
            'domains.delete',

            // Databases - view, create, edit (not delete, not manage-all)
            'databases.view',
            'databases.create',
            'databases.edit',

            // Database Users - view, create, edit (not delete, not manage-all)
            'database_users.view',
            'database_users.create',
            'database_users.edit',

            // Mail - view, create (not delete, not manage-all)
            'mail.view',
            'mail.create',

            // DNS - view, edit
            'dns.view',
            'dns.edit',

            // SSL - view, create, edit, delete, renew
            'ssl.view',
            'ssl.create',
            'ssl.edit',
            'ssl.delete',
            'ssl.renew',

            // Files - full access
            'files.view',
            'files.edit',
            'files.upload',

            // FTP - view, create
            'ftp.view',
            'ftp.create',

            // Backup - view, create (not restore, not manage-all)
            'backup.view',
            'backup.create',

            // Cron - view, create, edit (not delete)
            'cron.view',
            'cron.create',
            'cron.edit',
        ];
        $userRole->givePermissionTo($userPermissions);

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('- Admin: all permissions');
        $this->command->info('- Reseller: ' . count($resellerPermissions) . ' permissions');
        $this->command->info('- User: ' . count($userPermissions) . ' permissions');
    }
}
