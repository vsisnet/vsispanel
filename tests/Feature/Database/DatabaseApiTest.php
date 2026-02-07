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

describe('Database API - Index', function () {
    test('user can list their databases', function () {
        // Create databases for user
        ManagedDatabase::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Create database for other user
        ManagedDatabase::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/databases');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('user can filter databases by status', function () {
        ManagedDatabase::factory()->active()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        ManagedDatabase::factory()->deleted()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/databases?status=active');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('unauthenticated user cannot list databases', function () {
        $response = $this->getJson('/api/v1/databases');

        $response->assertUnauthorized();
    });
});

describe('Database API - Show', function () {
    test('user can view their database', function () {
        $database = ManagedDatabase::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'testuser_mydb',
            'original_name' => 'mydb',
        ]);

        // Mock getDatabaseSize to avoid actual MySQL query
        $this->mock(DatabaseService::class, function ($mock) use ($database) {
            $mock->shouldReceive('getDatabaseSize')
                ->with(\Mockery::on(fn($db) => $db->id === $database->id))
                ->andReturn(1024);
        });

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/databases/{$database->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.original_name', 'mydb');
    });

    test('user cannot view other user database', function () {
        $database = ManagedDatabase::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/databases/{$database->id}");

        $response->assertForbidden();
    });

    test('admin can view any database', function () {
        $database = ManagedDatabase::factory()->create([
            'user_id' => $this->user->id,
        ]);

        // Mock getDatabaseSize for admin
        $this->mock(DatabaseService::class, function ($mock) {
            $mock->shouldReceive('getDatabaseSize')
                ->andReturn(1024);
        });

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/databases/{$database->id}");

        $response->assertOk();
    });
});

describe('Database API - Store', function () {
    test('user can create a database', function () {
        $this->mock(DatabaseService::class, function ($mock) {
            $mock->shouldReceive('createDatabase')
                ->once()
                ->andReturn(ManagedDatabase::factory()->create([
                    'user_id' => $this->user->id,
                    'name' => 'testuser_newdb',
                    'original_name' => 'newdb',
                ]));
        });

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/databases', [
                'name' => 'newdb',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Database created successfully.');
    });

    test('database name validation rejects invalid characters', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/databases', [
                'name' => 'invalid-name!',
            ]);

        // Should return 422 for validation error
        $response->assertStatus(422);
    });

    test('database name must start with letter', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/databases', [
                'name' => '123invalid',
            ]);

        $response->assertStatus(422);
    });
});

describe('Database API - Delete', function () {
    test('user can delete their database', function () {
        $database = ManagedDatabase::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->mock(DatabaseService::class, function ($mock) use ($database) {
            $mock->shouldReceive('deleteDatabase')
                ->once()
                ->with(\Mockery::on(fn($db) => $db->id === $database->id));
        });

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/databases/{$database->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Database deleted successfully.');
    });

    test('user cannot delete other user database', function () {
        $database = ManagedDatabase::factory()->create([
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/databases/{$database->id}");

        $response->assertForbidden();
    });
});

describe('Database API - Backup', function () {
    test('user can create backup of their database', function () {
        $database = ManagedDatabase::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->mock(DatabaseService::class, function ($mock) {
            $mock->shouldReceive('backupDatabase')
                ->once()
                ->andReturn('/var/backups/vsispanel/databases/testuser_mydb_20260204.sql.gz');
        });

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/databases/{$database->id}/backup");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['backup_path']]);
    });
});

describe('Database API - Tables', function () {
    test('user can view database tables', function () {
        $database = ManagedDatabase::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->mock(DatabaseService::class, function ($mock) {
            $mock->shouldReceive('getDatabaseTables')
                ->once()
                ->andReturn([
                    [
                        'name' => 'users',
                        'engine' => 'InnoDB',
                        'rows' => 100,
                        'data_size' => 16384,
                        'index_size' => 8192,
                        'total_size' => 24576,
                    ],
                ]);
        });

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/databases/{$database->id}/tables");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'users');
    });
});
