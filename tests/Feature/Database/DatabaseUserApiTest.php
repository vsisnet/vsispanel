<?php

declare(strict_types=1);

use App\Modules\Auth\Models\User;
use App\Modules\Database\Models\ManagedDatabase;
use App\Modules\Database\Models\DatabaseUser;
use App\Modules\Database\Services\DatabaseService;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create user with subscription
    $this->user = User::factory()->create(['username' => 'testuser']);
    $this->user->assignRole('user');

    $plan = Plan::factory()->active()->create();
    $subscription = Subscription::factory()->active()->create([
        'user_id' => $this->user->id,
        'plan_id' => $plan->id,
    ]);

    // Create another user for authorization tests
    $this->otherUser = User::factory()->create(['username' => 'otheruser']);
    $this->otherUser->assignRole('user');

    // Create admin user
    $this->admin = User::factory()->create(['username' => 'adminuser']);
    $this->admin->assignRole('admin');
});

describe('Database User API - Index', function () {
    test('user can list their database users', function () {
        DatabaseUser::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        DatabaseUser::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/database-users');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('unauthenticated user cannot list database users', function () {
        $response = $this->getJson('/api/v1/database-users');
        $response->assertUnauthorized();
    });
});

describe('Database User API - Show', function () {
    test('user can view their database user', function () {
        $dbUser = DatabaseUser::factory()->create([
            'user_id' => $this->user->id,
            'username' => 'testuser_myuser',
            'original_username' => 'myuser',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/database-users/{$dbUser->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.original_username', 'myuser');
    });

    test('user cannot view other user database user', function () {
        $dbUser = DatabaseUser::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/database-users/{$dbUser->id}");

        $response->assertForbidden();
    });
});

describe('Database User API - Store', function () {
    test('user can create a database user', function () {
        $this->mock(DatabaseService::class, function ($mock) {
            $mock->shouldReceive('createDatabaseUser')
                ->once()
                ->andReturn(DatabaseUser::factory()->create([
                    'user_id' => $this->user->id,
                    'username' => 'testuser_newuser',
                    'original_username' => 'newuser',
                ]));
        });

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/database-users', [
                'username' => 'newuser',
                'password' => 'securePassword123',
                'host' => 'localhost',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Database user created successfully.');
    });

    test('username validation rejects invalid characters', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/database-users', [
                'username' => 'invalid-user!',
                'password' => 'securePassword123',
            ]);

        // Should return 422 for validation error
        $response->assertStatus(422);
    });

    test('password is required', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/database-users', [
                'username' => 'validuser',
            ]);

        $response->assertStatus(422);
    });
});

describe('Database User API - Delete', function () {
    test('user can delete their database user', function () {
        $dbUser = DatabaseUser::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->mock(DatabaseService::class, function ($mock) {
            $mock->shouldReceive('deleteDatabaseUser')->once();
        });

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/database-users/{$dbUser->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Database user deleted successfully.');
    });

    test('user cannot delete other user database user', function () {
        $dbUser = DatabaseUser::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/database-users/{$dbUser->id}");

        $response->assertForbidden();
    });
});

describe('Database User API - Change Password', function () {
    test('user can change password of their database user', function () {
        $dbUser = DatabaseUser::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->mock(DatabaseService::class, function ($mock) {
            $mock->shouldReceive('changeDatabaseUserPassword')->once();
        });

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/database-users/{$dbUser->id}/password", [
                'password' => 'newPassword123',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password changed successfully.');
    });

    test('password change requires minimum 8 characters', function () {
        $dbUser = DatabaseUser::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/database-users/{$dbUser->id}/password", [
                'password' => 'short',
            ]);

        $response->assertStatus(422);
    });
});

describe('Database User API - Grant Access', function () {
    test('grant access requires valid database', function () {
        $dbUser = DatabaseUser::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/database-users/{$dbUser->id}/grant", [
                'database_id' => 'invalid-uuid',
            ]);

        // Should fail validation
        $response->assertStatus(422);
    });

    test('user cannot grant access to other user database', function () {
        $dbUser = DatabaseUser::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $database = ManagedDatabase::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/database-users/{$dbUser->id}/grant", [
                'database_id' => $database->id,
            ]);

        $response->assertForbidden();
    });
});

describe('Database User API - Revoke Access', function () {
    test('admin can revoke access from database', function () {
        $dbUser = DatabaseUser::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $database = ManagedDatabase::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->mock(DatabaseService::class, function ($mock) {
            $mock->shouldReceive('revokeAccess')->once();
        });

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/database-users/{$dbUser->id}/revoke", [
                'database_id' => $database->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Access revoked successfully.');
    });
});

describe('Database User API - Privileges', function () {
    test('user can get available privileges', function () {
        $this->mock(DatabaseService::class, function ($mock) {
            $mock->shouldReceive('getAllPrivileges')
                ->once()
                ->andReturn([
                    'SELECT' => 'Read data from tables',
                    'INSERT' => 'Insert new rows into tables',
                ]);
            $mock->shouldReceive('getDefaultPrivileges')
                ->once()
                ->andReturn(['SELECT', 'INSERT', 'UPDATE', 'DELETE']);
        });

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/database-users/privileges');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['privileges', 'defaults'],
            ]);
    });
});
