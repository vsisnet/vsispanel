<?php

declare(strict_types=1);

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
});

describe('Dashboard API', function () {
    it('requires authentication for stats endpoint', function () {
        $this->getJson('/api/dashboard/stats')
            ->assertStatus(401);
    });

    it('requires authentication for metrics endpoint', function () {
        $this->getJson('/api/dashboard/metrics')
            ->assertStatus(401);
    });

    it('requires authentication for activity endpoint', function () {
        $this->getJson('/api/dashboard/activity')
            ->assertStatus(401);
    });

    it('requires authentication for system-info endpoint', function () {
        $this->getJson('/api/dashboard/system-info')
            ->assertStatus(401);
    });

    it('returns stats for authenticated user', function () {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'websites',
                    'databases',
                    'email_accounts',
                    'domains',
                    'disk',
                ],
            ]);
    });

    it('returns user count for admin only', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'users',
                ],
            ]);
    });

    it('does not return user count for regular user', function () {
        $user = User::factory()->create(['role' => 'user']);
        $user->assignRole('user');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('users', $response->json('data'));
    });

    it('returns metrics for authenticated user', function () {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/metrics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'cpu' => [
                        'percentage',
                        'load_1min',
                        'load_5min',
                        'load_15min',
                        'cores',
                        'history',
                    ],
                    'memory' => [
                        'percentage',
                        'used',
                        'total',
                        'history',
                    ],
                    'disk',
                ],
            ]);
    });

    it('returns activity for authenticated user', function () {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/activity');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    });

    it('returns limited system info for regular user', function () {
        $user = User::factory()->create(['role' => 'user']);
        $user->assignRole('user');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/system-info');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Regular users should not see full system info
        $this->assertArrayNotHasKey('services', $data);
    });

    it('returns full system info for admin', function () {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/dashboard/system-info');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'system' => [
                        'os',
                        'hostname',
                        'php_version',
                        'mysql_version',
                    ],
                    'services',
                ],
            ]);
    });

    it('returns realtime metrics for authenticated user', function () {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/dashboard/realtime');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'cpu_percentage',
                    'cpu_load',
                    'memory_percentage',
                    'memory_used',
                    'timestamp',
                ],
            ]);
    });
});
