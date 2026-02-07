<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_health_check_returns_healthy_status(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'healthy',
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'version',
                    'environment',
                    'timestamp',
                ],
            ]);
    }

    public function test_basic_health_check_returns_correct_version(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(config('app.version', '1.0.0'), $data['version']);
        $this->assertEquals(config('app.env'), $data['environment']);
    }

    public function test_detailed_health_check_includes_all_checks(): void
    {
        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'version',
                    'environment',
                    'timestamp',
                    'checks' => [
                        'app' => ['status', 'message', 'details'],
                        'database' => ['status', 'message', 'details'],
                        'redis' => ['status', 'message', 'details'],
                        'storage' => ['status', 'message', 'details'],
                    ],
                ],
            ]);
    }

    public function test_detailed_health_check_app_includes_versions(): void
    {
        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(200);

        $checks = $response->json('data.checks');
        $this->assertArrayHasKey('php_version', $checks['app']['details']);
        $this->assertArrayHasKey('laravel_version', $checks['app']['details']);
        $this->assertArrayHasKey('debug_mode', $checks['app']['details']);
    }

    public function test_detailed_health_check_database_includes_latency(): void
    {
        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(200);

        $checks = $response->json('data.checks');
        $this->assertEquals('healthy', $checks['database']['status']);
        $this->assertArrayHasKey('latency_ms', $checks['database']['details']);
        $this->assertIsNumeric($checks['database']['details']['latency_ms']);
    }

    public function test_detailed_health_check_storage_includes_disk_info(): void
    {
        $response = $this->getJson('/api/health/detailed');

        $response->assertStatus(200);

        $checks = $response->json('data.checks');
        $this->assertArrayHasKey('free_space', $checks['storage']['details']);
        $this->assertArrayHasKey('total_space', $checks['storage']['details']);
        $this->assertArrayHasKey('used_percent', $checks['storage']['details']);
    }

    public function test_system_health_requires_authentication(): void
    {
        $response = $this->getJson('/api/health/system');

        $response->assertStatus(401);
    }

    public function test_system_health_returns_data_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/health/system');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'uptime',
                    'load',
                    'memory',
                    'disk',
                    'php_version',
                    'laravel_version',
                ],
            ]);
    }

    public function test_v1_health_endpoint_works(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'healthy',
                ],
            ]);
    }

    public function test_health_endpoint_accepts_json_header(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->get('/api/health');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_api_returns_json_for_non_json_accept(): void
    {
        $response = $this->withHeaders([
            'Accept' => 'text/html',
        ])->get('/api/health');

        // The ForceJsonResponse middleware should still return JSON
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json');
    }
}
