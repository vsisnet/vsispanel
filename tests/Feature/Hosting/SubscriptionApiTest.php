<?php

declare(strict_types=1);

use App\Modules\Auth\Models\User;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use App\Modules\Hosting\Services\QuotaService;
use App\Modules\Hosting\Services\SubscriptionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user
    $this->admin = User::factory()->create(['username' => 'adminuser']);
    $this->admin->assignRole('admin');

    // Create regular user with subscription
    $this->user = User::factory()->create(['username' => 'testuser']);
    $this->user->assignRole('user');

    $this->plan = Plan::factory()->active()->create();
    $this->subscription = Subscription::factory()->active()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
    ]);

    // Create another user for authorization tests
    $this->otherUser = User::factory()->create(['username' => 'otheruser']);
    $this->otherUser->assignRole('user');
});

describe('Subscription API - Index (Admin)', function () {
    test('admin can list all subscriptions', function () {
        Subscription::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/subscriptions');

        $response->assertOk()
            // 4 total: 1 from beforeEach + 3 new ones
            ->assertJsonCount(4, 'data');
    });

    test('admin can filter subscriptions by status', function () {
        Subscription::factory()->suspended()->count(2)->create();
        Subscription::factory()->active()->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/subscriptions?status=suspended');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('admin can filter subscriptions by plan', function () {
        $otherPlan = Plan::factory()->create();
        Subscription::factory()->create(['plan_id' => $otherPlan->id]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/subscriptions?plan_id={$this->plan->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('regular user cannot list all subscriptions', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/subscriptions');

        $response->assertForbidden();
    });
});

describe('Subscription API - Current (User)', function () {
    test('user can get their current subscription', function () {
        $this->mock(QuotaService::class, function ($mock) {
            $mock->shouldReceive('getActiveSubscription')
                ->once()
                ->andReturn($this->subscription);
        });

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/subscriptions/current');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->subscription->id);
    });

    test('user without subscription gets null', function () {
        $this->mock(QuotaService::class, function ($mock) {
            $mock->shouldReceive('getActiveSubscription')
                ->once()
                ->andReturn(null);
        });

        $response = $this->actingAs($this->otherUser)
            ->getJson('/api/v1/subscriptions/current');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    });

    test('unauthenticated user cannot get current subscription', function () {
        $response = $this->getJson('/api/v1/subscriptions/current');

        $response->assertUnauthorized();
    });
});

describe('Subscription API - Quota (User)', function () {
    test('user can get their quota usage', function () {
        $quotaData = [
            'domains' => ['used' => 2, 'limit' => 5, 'available' => 3],
            'databases' => ['used' => 1, 'limit' => 3, 'available' => 2],
            'disk' => ['used' => 512, 'limit' => 5120, 'available' => 4608],
        ];

        $this->mock(QuotaService::class, function ($mock) use ($quotaData) {
            $mock->shouldReceive('getQuotaUsage')
                ->once()
                ->andReturn($quotaData);
        });

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/subscriptions/quota');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.domains.used', 2);
    });
});

describe('Subscription API - Store (Admin)', function () {
    test('admin can create a subscription', function () {
        $newUser = User::factory()->create();
        $newSubscription = Subscription::factory()->create([
            'user_id' => $newUser->id,
            'plan_id' => $this->plan->id,
        ]);

        $this->mock(SubscriptionService::class, function ($mock) use ($newSubscription) {
            $mock->shouldReceive('createSubscription')
                ->once()
                ->andReturn($newSubscription);
        });

        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/subscriptions', [
                'user_id' => $newUser->id,
                'plan_id' => $this->plan->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subscription created successfully.');
    });

    test('user_id is required', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/subscriptions', [
                'plan_id' => $this->plan->id,
            ]);

        $response->assertStatus(422);
    });

    test('plan_id is required', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/subscriptions', [
                'user_id' => $this->user->id,
            ]);

        $response->assertStatus(422);
    });

    test('regular user cannot create subscriptions', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/subscriptions', [
                'user_id' => $this->otherUser->id,
                'plan_id' => $this->plan->id,
            ]);

        $response->assertForbidden();
    });
});

describe('Subscription API - Show', function () {
    test('admin can view any subscription', function () {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/subscriptions/{$this->subscription->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->subscription->id);
    });

    test('user can view their own subscription', function () {
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/subscriptions/{$this->subscription->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->subscription->id);
    });

    test('user cannot view other user subscription', function () {
        // Create another user's subscription
        $otherSubscription = Subscription::factory()->create([
            'user_id' => $this->otherUser->id,
            'plan_id' => $this->plan->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/subscriptions/{$otherSubscription->id}");

        $response->assertForbidden();
    });
});

describe('Subscription API - Change Plan (Admin)', function () {
    test('admin can change subscription plan', function () {
        $newPlan = Plan::factory()->create();

        $this->mock(SubscriptionService::class, function ($mock) {
            $mock->shouldReceive('changePlan')
                ->once()
                ->andReturn($this->subscription);
        });

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/subscriptions/{$this->subscription->id}/change-plan", [
                'plan_id' => $newPlan->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Plan changed successfully.');
    });

    test('plan_id is required for plan change', function () {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/subscriptions/{$this->subscription->id}/change-plan", []);

        $response->assertStatus(422);
    });
});

describe('Subscription API - Suspend (Admin)', function () {
    test('admin can suspend a subscription', function () {
        $this->mock(SubscriptionService::class, function ($mock) {
            $mock->shouldReceive('suspendSubscription')
                ->once()
                ->andReturn($this->subscription->fill(['status' => 'suspended']));
        });

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/subscriptions/{$this->subscription->id}/suspend", [
                'reason' => 'Non-payment',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subscription suspended successfully.');
    });

    test('reason is required for suspension', function () {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/subscriptions/{$this->subscription->id}/suspend", []);

        $response->assertStatus(422);
    });
});

describe('Subscription API - Unsuspend (Admin)', function () {
    test('admin can unsuspend a subscription', function () {
        $suspendedSubscription = Subscription::factory()->suspended()->create();

        $this->mock(SubscriptionService::class, function ($mock) use ($suspendedSubscription) {
            $mock->shouldReceive('unsuspendSubscription')
                ->once()
                ->andReturn($suspendedSubscription->fill(['status' => 'active']));
        });

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/subscriptions/{$suspendedSubscription->id}/unsuspend");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subscription unsuspended successfully.');
    });
});

describe('Subscription API - Cancel (Admin)', function () {
    test('admin can cancel a subscription', function () {
        $this->mock(SubscriptionService::class, function ($mock) {
            $mock->shouldReceive('cancelSubscription')
                ->once()
                ->andReturn($this->subscription->fill(['status' => 'cancelled']));
        });

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/subscriptions/{$this->subscription->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subscription cancelled successfully.');
    });
});

describe('Subscription API - Renew (Admin)', function () {
    test('admin can renew a subscription', function () {
        $this->mock(SubscriptionService::class, function ($mock) {
            $mock->shouldReceive('renewSubscription')
                ->once()
                ->andReturn($this->subscription);
        });

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/subscriptions/{$this->subscription->id}/renew", [
                'months' => 12,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Subscription renewed successfully.');
    });

    test('months is required for renewal', function () {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/subscriptions/{$this->subscription->id}/renew", []);

        $response->assertStatus(422);
    });

    test('months must be between 1 and 36', function () {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/subscriptions/{$this->subscription->id}/renew", [
                'months' => 48,
            ]);

        $response->assertStatus(422);
    });
});

describe('Subscription API - Statistics (Admin)', function () {
    test('admin can get subscription statistics', function () {
        $stats = [
            'total' => 10,
            'active' => 8,
            'suspended' => 1,
            'cancelled' => 1,
        ];

        $this->mock(SubscriptionService::class, function ($mock) use ($stats) {
            $mock->shouldReceive('getStatistics')
                ->once()
                ->andReturn($stats);
        });

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/subscriptions/statistics');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 10);
    });

    test('regular user cannot get statistics', function () {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/subscriptions/statistics');

        $response->assertForbidden();
    });
});
