<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user;
    protected Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        // Create admin user
        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);
        $this->admin->assignRole('admin');

        // Create regular user
        $this->user = User::factory()->create([
            'role' => 'user',
        ]);
        $this->user->assignRole('user');

        // Create plan and subscription for user
        $plan = Plan::factory()->create();
        $this->subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);
    }

    public function test_admin_can_list_all_domains(): void
    {
        // Create domains for different users
        Domain::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/domains');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'status', 'php_version'],
                ],
            ]);
    }

    public function test_user_can_only_list_own_domains(): void
    {
        // Create domains for the user
        Domain::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
        ]);

        // Create domains for another user
        $anotherUser = User::factory()->create(['role' => 'user']);
        $anotherUser->assignRole('user');
        $anotherPlan = Plan::factory()->create();
        $anotherSub = Subscription::factory()->create([
            'user_id' => $anotherUser->id,
            'plan_id' => $anotherPlan->id,
        ]);
        Domain::factory()->count(3)->create([
            'user_id' => $anotherUser->id,
            'subscription_id' => $anotherSub->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/domains');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_user_can_create_domain(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/domains', [
                'name' => 'example.com',
                'php_version' => '8.3',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'example.com');

        $this->assertDatabaseHas('domains', [
            'name' => 'example.com',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_domain_name_must_be_unique(): void
    {
        Domain::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'name' => 'existing.com',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/domains', [
                'name' => 'existing.com',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_domain_name_must_be_valid_format(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/domains', [
                'name' => 'invalid domain',
            ]);

        $response->assertStatus(422);
    }

    public function test_user_can_view_own_domain(): void
    {
        $domain = Domain::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/domains/{$domain->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $domain->id);
    }

    public function test_user_cannot_view_others_domain(): void
    {
        $anotherUser = User::factory()->create(['role' => 'user']);
        $anotherUser->assignRole('user');
        $anotherPlan = Plan::factory()->create();
        $anotherSub = Subscription::factory()->create([
            'user_id' => $anotherUser->id,
            'plan_id' => $anotherPlan->id,
        ]);
        $domain = Domain::factory()->create([
            'user_id' => $anotherUser->id,
            'subscription_id' => $anotherSub->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/domains/{$domain->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_update_own_domain(): void
    {
        $domain = Domain::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'php_version' => '8.1',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/domains/{$domain->id}", [
                'php_version' => '8.3',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.php_version', '8.3');

        $this->assertDatabaseHas('domains', [
            'id' => $domain->id,
            'php_version' => '8.3',
        ]);
    }

    public function test_user_can_delete_own_domain(): void
    {
        $domain = Domain::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/domains/{$domain->id}");

        $response->assertStatus(200);

        // Soft deleted
        $this->assertSoftDeleted('domains', ['id' => $domain->id]);
    }

    public function test_admin_can_suspend_domain(): void
    {
        $domain = Domain::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/domains/{$domain->id}/suspend", [
                'reason' => 'Test suspension',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'suspended');
    }

    public function test_user_cannot_suspend_own_domain(): void
    {
        $domain = Domain::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/domains/{$domain->id}/suspend");

        $response->assertStatus(403);
    }

    public function test_admin_can_unsuspend_domain(): void
    {
        $domain = Domain::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $this->subscription->id,
            'status' => 'suspended',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/domains/{$domain->id}/unsuspend");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_unauthenticated_user_cannot_access_domains(): void
    {
        $response = $this->getJson('/api/v1/domains');

        $response->assertStatus(401);
    }
}
