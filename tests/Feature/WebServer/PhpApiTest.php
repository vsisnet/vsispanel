<?php

declare(strict_types=1);

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use App\Modules\WebServer\Services\PhpFpmService;
use App\Services\CommandResult;
use App\Services\SystemCommandExecutor;
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

    $this->domain = Domain::factory()->create([
        'user_id' => $this->user->id,
        'subscription_id' => $subscription->id,
        'name' => 'example.com',
        'php_version' => '8.3',
        'status' => 'active',
        'web_server_type' => 'nginx',
    ]);

    // Mock the SystemCommandExecutor for all tests
    $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
    $mockExecutor->shouldReceive('execute')->andReturn(new CommandResult(true, 0, '', '', 0.1));
    $mockExecutor->shouldReceive('executeAsRoot')->andReturn(new CommandResult(true, 0, 'active', '', 0.1));

    $this->app->instance(SystemCommandExecutor::class, $mockExecutor);
});

describe('PHP Versions API', function () {
    test('authenticated user can get php versions', function () {
        $response = $this->actingAs($this->user)->getJson('/api/v1/php/versions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'versions',
                ],
            ]);
    });

    test('unauthenticated user cannot get php versions', function () {
        $response = $this->getJson('/api/v1/php/versions');

        $response->assertStatus(401);
    });
});

describe('PHP Info API', function () {
    test('authenticated user can get php info', function () {
        // Mock PHP info responses
        $mockExecutor = Mockery::mock(SystemCommandExecutor::class);
        $mockExecutor->shouldReceive('execute')
            ->with('test', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));
        $mockExecutor->shouldReceive('executeAsRoot')
            ->andReturn(new CommandResult(true, 0, 'active', '', 0.1));
        $mockExecutor->shouldReceive('execute')
            ->with('php8.3', ['-v'])
            ->andReturn(new CommandResult(true, 0, 'PHP 8.3.12', '', 0.1));
        $mockExecutor->shouldReceive('execute')
            ->with('php8.3', ['-m'])
            ->andReturn(new CommandResult(true, 0, 'curl\nmbstring', '', 0.1));
        $mockExecutor->shouldReceive('execute')
            ->with('php8.3', ['-i'])
            ->andReturn(new CommandResult(true, 0, 'memory_limit => 256M', '', 0.1));
        $mockExecutor->shouldReceive('execute')
            ->with('php8.3', Mockery::any())
            ->andReturn(new CommandResult(true, 0, '', '', 0.1));

        $this->app->instance(SystemCommandExecutor::class, $mockExecutor);

        $response = $this->actingAs($this->user)->getJson('/api/v1/php/8.3/info');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'version',
                ],
            ]);
    });
});

describe('PHP Settings API', function () {
    test('user can get php settings for own domain', function () {
        $response = $this->actingAs($this->user)->getJson("/api/v1/domains/{$this->domain->id}/php-settings");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'domain',
                    'php_version',
                    'settings',
                    'defaults',
                ],
            ]);
    });

    test('user cannot get php settings for others domain', function () {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');

        $response = $this->actingAs($otherUser)->getJson("/api/v1/domains/{$this->domain->id}/php-settings");

        $response->assertStatus(403);
    });

    test('user can update php settings for own domain', function () {
        $response = $this->actingAs($this->user)->putJson("/api/v1/domains/{$this->domain->id}/php-settings", [
            'memory_limit' => '512M',
            'upload_max_filesize' => '128M',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    });

    test('invalid php settings are rejected', function () {
        $response = $this->actingAs($this->user)->putJson("/api/v1/domains/{$this->domain->id}/php-settings", [
            'memory_limit' => 'invalid_value',
        ]);

        $response->assertStatus(422);
    });
});

describe('PHP Version Switch API', function () {
    test('user can switch php version for own domain', function () {
        $response = $this->actingAs($this->user)->putJson("/api/v1/domains/{$this->domain->id}/php-version", [
            'php_version' => '8.1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->domain->refresh();
        expect($this->domain->php_version)->toBe('8.1');
    });

    test('invalid php version is rejected', function () {
        $response = $this->actingAs($this->user)->putJson("/api/v1/domains/{$this->domain->id}/php-version", [
            'php_version' => '5.6',
        ]);

        $response->assertStatus(422);
    });

    test('user cannot switch php version for others domain', function () {
        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');

        $response = $this->actingAs($otherUser)->putJson("/api/v1/domains/{$this->domain->id}/php-version", [
            'php_version' => '8.1',
        ]);

        $response->assertStatus(403);
    });
});
