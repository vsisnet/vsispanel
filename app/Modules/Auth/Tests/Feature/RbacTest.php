<?php

declare(strict_types=1);

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear permission cache
    $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

    // Ensure roles exist
    if (!Role::where('name', 'admin')->where('guard_name', 'sanctum')->exists()) {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }
});

test('admin user has all permissions', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'status' => 'active',
    ]);
    $admin->assignRole('admin');

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->hasPermissionTo('domains.view', 'sanctum'))->toBeTrue();
    expect($admin->hasPermissionTo('domains.create', 'sanctum'))->toBeTrue();
    expect($admin->hasPermissionTo('domains.edit', 'sanctum'))->toBeTrue();
    expect($admin->hasPermissionTo('domains.delete', 'sanctum'))->toBeTrue();
    expect($admin->hasPermissionTo('domains.manage-all', 'sanctum'))->toBeTrue();
    expect($admin->hasPermissionTo('users.impersonate', 'sanctum'))->toBeTrue();
    expect($admin->hasPermissionTo('server.manage', 'sanctum'))->toBeTrue();
});

test('reseller user has limited permissions', function () {
    $reseller = User::factory()->create([
        'role' => 'reseller',
        'status' => 'active',
    ]);
    $reseller->assignRole('reseller');

    expect($reseller->isReseller())->toBeTrue();
    expect($reseller->hasPermissionTo('domains.view', 'sanctum'))->toBeTrue();
    expect($reseller->hasPermissionTo('domains.create', 'sanctum'))->toBeTrue();
    expect($reseller->hasPermissionTo('reseller.manage-customers', 'sanctum'))->toBeTrue();

    // Reseller should NOT have these permissions
    expect($reseller->hasPermissionTo('domains.manage-all', 'sanctum'))->toBeFalse();
    expect($reseller->hasPermissionTo('users.impersonate', 'sanctum'))->toBeFalse();
    expect($reseller->hasPermissionTo('server.manage', 'sanctum'))->toBeFalse();
    expect($reseller->hasPermissionTo('firewall.manage', 'sanctum'))->toBeFalse();
});

test('regular user has minimal permissions', function () {
    $user = User::factory()->create([
        'role' => 'user',
        'status' => 'active',
    ]);
    $user->assignRole('user');

    expect($user->isUser())->toBeTrue();
    expect($user->hasPermissionTo('domains.view', 'sanctum'))->toBeTrue();
    expect($user->hasPermissionTo('domains.create', 'sanctum'))->toBeTrue();
    expect($user->hasPermissionTo('files.view', 'sanctum'))->toBeTrue();

    // User should NOT have these permissions
    expect($user->hasPermissionTo('domains.delete', 'sanctum'))->toBeFalse();
    expect($user->hasPermissionTo('domains.manage-all', 'sanctum'))->toBeFalse();
    expect($user->hasPermissionTo('users.view', 'sanctum'))->toBeFalse();
    expect($user->hasPermissionTo('reseller.manage-customers', 'sanctum'))->toBeFalse();
});

test('admin can manage all users', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $admin->assignRole('admin');

    $reseller = User::factory()->create(['role' => 'reseller', 'status' => 'active']);
    $user = User::factory()->create(['role' => 'user', 'status' => 'active']);

    expect($admin->canManage($reseller))->toBeTrue();
    expect($admin->canManage($user))->toBeTrue();
    expect($admin->canView($reseller))->toBeTrue();
    expect($admin->canView($user))->toBeTrue();
});

test('reseller can only manage their customers', function () {
    $reseller = User::factory()->create(['role' => 'reseller', 'status' => 'active']);
    $reseller->assignRole('reseller');

    $ownCustomer = User::factory()->create([
        'role' => 'user',
        'parent_id' => $reseller->id,
        'status' => 'active',
    ]);

    $otherCustomer = User::factory()->create([
        'role' => 'user',
        'status' => 'active',
    ]);

    expect($reseller->canManage($ownCustomer))->toBeTrue();
    expect($reseller->canManage($otherCustomer))->toBeFalse();
    expect($reseller->canView($ownCustomer))->toBeTrue();
    expect($reseller->canView($otherCustomer))->toBeFalse();
});

test('user cannot manage anyone', function () {
    $user1 = User::factory()->create(['role' => 'user', 'status' => 'active']);
    $user1->assignRole('user');

    $user2 = User::factory()->create(['role' => 'user', 'status' => 'active']);

    expect($user1->canManage($user2))->toBeFalse();
    expect($user1->canManage($user1))->toBeFalse(); // Cannot even manage themselves
    expect($user1->canView($user2))->toBeFalse();
    expect($user1->canView($user1))->toBeTrue(); // Can view themselves
});

test('accessibleBy scope returns correct users for admin', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $admin->assignRole('admin');

    User::factory()->count(3)->create(['role' => 'user', 'status' => 'active']);

    $accessibleUsers = User::accessibleBy($admin)->count();
    expect($accessibleUsers)->toBeGreaterThanOrEqual(4); // admin + 3 users
});

test('accessibleBy scope returns only customers for reseller', function () {
    $reseller = User::factory()->create(['role' => 'reseller', 'status' => 'active']);
    $reseller->assignRole('reseller');

    // Create customers for this reseller
    User::factory()->count(2)->create([
        'role' => 'user',
        'parent_id' => $reseller->id,
        'status' => 'active',
    ]);

    // Create other users
    User::factory()->count(3)->create(['role' => 'user', 'status' => 'active']);

    $accessibleUsers = User::accessibleBy($reseller)->get();
    expect($accessibleUsers->count())->toBe(2);
    expect($accessibleUsers->every(fn($u) => (string)$u->parent_id === (string)$reseller->id))->toBeTrue();
});

test('accessibleBy scope returns only self for regular user', function () {
    $user = User::factory()->create(['role' => 'user', 'status' => 'active']);
    $user->assignRole('user');

    User::factory()->count(5)->create(['role' => 'user', 'status' => 'active']);

    $accessibleUsers = User::accessibleBy($user)->get();
    expect($accessibleUsers->count())->toBe(1);
    expect((string)$accessibleUsers->first()->id)->toBe((string)$user->id);
});

test('role helpers work correctly', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $reseller = User::factory()->create(['role' => 'reseller']);
    $user = User::factory()->create(['role' => 'user']);

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isReseller())->toBeFalse();
    expect($admin->isUser())->toBeFalse();

    expect($reseller->isAdmin())->toBeFalse();
    expect($reseller->isReseller())->toBeTrue();
    expect($reseller->isUser())->toBeFalse();

    expect($user->isAdmin())->toBeFalse();
    expect($user->isReseller())->toBeFalse();
    expect($user->isUser())->toBeTrue();
});

test('user parent relationship works', function () {
    $reseller = User::factory()->create(['role' => 'reseller']);
    $customer = User::factory()->create([
        'role' => 'user',
        'parent_id' => $reseller->id,
    ]);

    expect((string)$customer->parent->id)->toBe((string)$reseller->id);
    expect($customer->hasParent())->toBeTrue();
    expect($reseller->hasParent())->toBeFalse();
    expect($reseller->customers->count())->toBe(1);
    expect((string)$reseller->customers->first()->id)->toBe((string)$customer->id);
});
