<?php

declare(strict_types=1);

use App\Modules\Auth\Models\User;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Services\PlanService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user
    $this->admin = User::factory()->create(['username' => 'adminuser']);
    $this->admin->assignRole('admin');

    // Create regular user
    $this->user = User::factory()->create(['username' => 'testuser']);
    $this->user->assignRole('user');
});

describe('Plan API - Index', function () {
    test('admin can list all plans', function () {
        Plan::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/plans');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('admin can filter plans by active status', function () {
        Plan::factory()->active()->count(2)->create();
        Plan::factory()->inactive()->create();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/plans?active=1');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('admin can search plans by name', function () {
        Plan::factory()->create(['name' => 'Starter Plan']);
        Plan::factory()->create(['name' => 'Business Plan']);
        Plan::factory()->create(['name' => 'Enterprise']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/plans?search=Plan');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('regular user cannot list plans via admin route', function () {
        Plan::factory()->count(3)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/plans');

        $response->assertForbidden();
    });
});

describe('Plan API - Available', function () {
    test('any authenticated user can get available plans', function () {
        Plan::factory()->active()->count(2)->create();
        Plan::factory()->inactive()->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/plans/available');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    });

    test('unauthenticated user cannot get available plans', function () {
        $response = $this->getJson('/api/v1/plans/available');

        $response->assertUnauthorized();
    });
});

describe('Plan API - Store', function () {
    test('admin can create a plan', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/plans', [
                'name' => 'New Test Plan',
                'disk_limit' => 5120,
                'bandwidth_limit' => 51200,
                'domains_limit' => 5,
                'databases_limit' => 3,
                'email_accounts_limit' => 10,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Plan created successfully.');

        // Verify plan was created in database
        $this->assertDatabaseHas('plans', [
            'name' => 'New Test Plan',
            'disk_limit' => 5120,
        ]);
    });

    test('regular user cannot create a plan', function () {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/plans', [
                'name' => 'New Plan',
                'disk_limit' => 5120,
                'bandwidth_limit' => 51200,
                'domains_limit' => 5,
                'databases_limit' => 3,
                'email_accounts_limit' => 10,
            ]);

        $response->assertForbidden();
    });

    test('plan name is required', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/plans', [
                'disk_limit' => 5120,
                'bandwidth_limit' => 51200,
                'domains_limit' => 5,
                'databases_limit' => 3,
                'email_accounts_limit' => 10,
            ]);

        $response->assertStatus(422);
    });
});

describe('Plan API - Show', function () {
    test('admin can view a plan', function () {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/plans/{$plan->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $plan->id);
    });
});

describe('Plan API - Update', function () {
    test('admin can update a plan', function () {
        $plan = Plan::factory()->create(['name' => 'Old Name']);

        $this->mock(PlanService::class, function ($mock) use ($plan) {
            $mock->shouldReceive('updatePlan')
                ->once()
                ->andReturn($plan->fill(['name' => 'Updated Name']));
        });

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/plans/{$plan->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Plan updated successfully.');
    });

    test('regular user cannot update a plan', function () {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/plans/{$plan->id}", [
                'name' => 'Updated Name',
            ]);

        $response->assertForbidden();
    });
});

describe('Plan API - Delete', function () {
    test('admin can delete a plan', function () {
        $plan = Plan::factory()->create();

        $this->mock(PlanService::class, function ($mock) {
            $mock->shouldReceive('deletePlan')
                ->once();
        });

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/plans/{$plan->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Plan deleted successfully.');
    });

    test('regular user cannot delete a plan', function () {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/plans/{$plan->id}");

        $response->assertForbidden();
    });
});

describe('Plan API - Activate/Deactivate', function () {
    test('admin can activate a plan', function () {
        $plan = Plan::factory()->inactive()->create();

        $this->mock(PlanService::class, function ($mock) use ($plan) {
            $mock->shouldReceive('activatePlan')
                ->once()
                ->andReturn($plan->fill(['is_active' => true]));
        });

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/plans/{$plan->id}/activate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Plan activated successfully.');
    });

    test('admin can deactivate a plan', function () {
        $plan = Plan::factory()->active()->create();

        $this->mock(PlanService::class, function ($mock) use ($plan) {
            $mock->shouldReceive('deactivatePlan')
                ->once()
                ->andReturn($plan->fill(['is_active' => false]));
        });

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/plans/{$plan->id}/deactivate");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Plan deactivated successfully.');
    });
});

describe('Plan API - Clone', function () {
    test('admin can clone a plan', function () {
        $plan = Plan::factory()->create(['name' => 'Original Plan']);
        $clonedPlan = Plan::factory()->create(['name' => 'Cloned Plan']);

        $this->mock(PlanService::class, function ($mock) use ($clonedPlan) {
            $mock->shouldReceive('clonePlan')
                ->once()
                ->andReturn($clonedPlan);
        });

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/plans/{$plan->id}/clone", [
                'name' => 'Cloned Plan',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Plan cloned successfully.');
    });

    test('clone requires a name', function () {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/plans/{$plan->id}/clone", []);

        $response->assertStatus(422);
    });
});
