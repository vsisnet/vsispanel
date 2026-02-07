<?php

declare(strict_types=1);

use App\Modules\Auth\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Modules\Hosting\Models\Plan;
use App\Modules\Hosting\Models\Subscription;
use App\Modules\SSL\Models\SslCertificate;
use App\Modules\SSL\Services\SslService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Create admin user
    $this->admin = User::factory()->create(['username' => 'adminuser']);
    $this->admin->assignRole('admin');

    // Create regular user with subscription and domain
    $this->user = User::factory()->create(['username' => 'testuser']);
    $this->user->assignRole('user');

    $this->plan = Plan::factory()->active()->create();
    $this->subscription = Subscription::factory()->active()->create([
        'user_id' => $this->user->id,
        'plan_id' => $this->plan->id,
    ]);

    $this->domain = Domain::factory()->create([
        'user_id' => $this->user->id,
        'subscription_id' => $this->subscription->id,
        'name' => 'example.com',
    ]);

    // Create another user for authorization tests
    $this->otherUser = User::factory()->create(['username' => 'otheruser']);
    $this->otherUser->assignRole('user');

    $this->otherSubscription = Subscription::factory()->active()->create([
        'user_id' => $this->otherUser->id,
        'plan_id' => $this->plan->id,
    ]);

    $this->otherDomain = Domain::factory()->create([
        'user_id' => $this->otherUser->id,
        'subscription_id' => $this->otherSubscription->id,
        'name' => 'other.com',
    ]);
});

describe('SSL API - Index', function () {
    test('admin can list all SSL certificates', function () {
        SslCertificate::factory()->count(3)->create([
            'domain_id' => $this->domain->id,
        ]);
        SslCertificate::factory()->count(2)->create([
            'domain_id' => $this->otherDomain->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/ssl');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    test('user can list only their SSL certificates', function () {
        SslCertificate::factory()->count(3)->create([
            'domain_id' => $this->domain->id,
        ]);
        SslCertificate::factory()->count(2)->create([
            'domain_id' => $this->otherDomain->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/ssl');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('can filter by status', function () {
        SslCertificate::factory()->active()->count(2)->create([
            'domain_id' => $this->domain->id,
        ]);
        SslCertificate::factory()->expired()->create([
            'domain_id' => $this->domain->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/ssl?status=active');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('can filter by type', function () {
        SslCertificate::factory()->letsEncrypt()->count(2)->create([
            'domain_id' => $this->domain->id,
        ]);
        SslCertificate::factory()->custom()->create([
            'domain_id' => $this->domain->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/ssl?type=lets_encrypt');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('unauthenticated user cannot list certificates', function () {
        $response = $this->getJson('/api/v1/ssl');

        $response->assertUnauthorized();
    });
});

describe('SSL API - Issue Let\'s Encrypt', function () {
    test('user can request Let\'s Encrypt certificate for their domain', function () {
        $certificate = SslCertificate::factory()->letsEncrypt()->active()->create([
            'domain_id' => $this->domain->id,
        ]);

        $this->mock(SslService::class, function ($mock) use ($certificate) {
            $mock->shouldReceive('issueLetsEncrypt')
                ->once()
                ->andReturn($certificate);
        });

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/domains/{$this->domain->id}/letsencrypt");

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Let\'s Encrypt certificate issued successfully.');
    });

    test('user cannot issue certificate for another user\'s domain', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/domains/{$this->otherDomain->id}/letsencrypt");

        $response->assertForbidden();
    });

    test('admin can issue certificate for any domain', function () {
        $certificate = SslCertificate::factory()->letsEncrypt()->active()->create([
            'domain_id' => $this->otherDomain->id,
        ]);

        $this->mock(SslService::class, function ($mock) use ($certificate) {
            $mock->shouldReceive('issueLetsEncrypt')
                ->once()
                ->andReturn($certificate);
        });

        $response = $this->actingAs($this->admin)
            ->postJson("/api/v1/ssl/domains/{$this->otherDomain->id}/letsencrypt");

        $response->assertCreated();
    });
});

describe('SSL API - Upload Custom Certificate', function () {
    test('user can upload custom certificate for their domain', function () {
        $certificate = SslCertificate::factory()->custom()->active()->create([
            'domain_id' => $this->domain->id,
        ]);

        $this->mock(SslService::class, function ($mock) use ($certificate) {
            $mock->shouldReceive('uploadCustomCert')
                ->once()
                ->andReturn($certificate);
        });

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/domains/{$this->domain->id}/custom", [
                'certificate' => "-----BEGIN CERTIFICATE-----\nMIIC...\n-----END CERTIFICATE-----",
                'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIE...\n-----END PRIVATE KEY-----",
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Custom certificate uploaded successfully.');
    });

    test('certificate field is required', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/domains/{$this->domain->id}/custom", [
                'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIE...\n-----END PRIVATE KEY-----",
            ]);

        $response->assertStatus(422);
    });

    test('private_key field is required', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/domains/{$this->domain->id}/custom", [
                'certificate' => "-----BEGIN CERTIFICATE-----\nMIIC...\n-----END CERTIFICATE-----",
            ]);

        $response->assertStatus(422);
    });

    test('certificate must be in PEM format', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/domains/{$this->domain->id}/custom", [
                'certificate' => 'invalid certificate',
                'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIE...\n-----END PRIVATE KEY-----",
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJson(fn ($json) => $json
                ->has('error.errors.certificate')
                ->etc()
            );
    });

    test('private key must be in PEM format', function () {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/domains/{$this->domain->id}/custom", [
                'certificate' => "-----BEGIN CERTIFICATE-----\nMIIC...\n-----END CERTIFICATE-----",
                'private_key' => 'invalid key',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJson(fn ($json) => $json
                ->has('error.errors.private_key')
                ->etc()
            );
    });
});

describe('SSL API - Show', function () {
    test('user can view their SSL certificate', function () {
        $certificate = SslCertificate::factory()->create([
            'domain_id' => $this->domain->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/ssl/{$certificate->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $certificate->id);
    });

    test('user cannot view another user\'s certificate', function () {
        $certificate = SslCertificate::factory()->create([
            'domain_id' => $this->otherDomain->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/ssl/{$certificate->id}");

        $response->assertForbidden();
    });

    test('admin can view any certificate', function () {
        $certificate = SslCertificate::factory()->create([
            'domain_id' => $this->otherDomain->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/ssl/{$certificate->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $certificate->id);
    });
});

describe('SSL API - Certificate Info', function () {
    test('user can get certificate info', function () {
        $certificate = SslCertificate::factory()->active()->create([
            'domain_id' => $this->domain->id,
        ]);

        $certInfo = [
            'subject' => 'example.com',
            'issuer' => "Let's Encrypt",
            'valid_from' => now()->subMonth()->toIso8601String(),
            'valid_to' => now()->addMonths(2)->toIso8601String(),
            'serial_number' => '1234567890',
        ];

        $this->mock(SslService::class, function ($mock) use ($certInfo) {
            $mock->shouldReceive('getCertificateInfo')
                ->once()
                ->andReturn($certInfo);
        });

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/ssl/{$certificate->id}/info");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.certificate_details.subject', 'example.com');
    });
});

describe('SSL API - Renew', function () {
    test('user can renew Let\'s Encrypt certificate', function () {
        $certificate = SslCertificate::factory()->letsEncrypt()->active()->create([
            'domain_id' => $this->domain->id,
        ]);

        $this->mock(SslService::class, function ($mock) use ($certificate) {
            $mock->shouldReceive('renewCertificate')
                ->once()
                ->andReturn($certificate);
        });

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/{$certificate->id}/renew");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Certificate renewed successfully.');
    });

    test('user cannot renew another user\'s certificate', function () {
        $certificate = SslCertificate::factory()->letsEncrypt()->create([
            'domain_id' => $this->otherDomain->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/{$certificate->id}/renew");

        $response->assertForbidden();
    });
});

describe('SSL API - Toggle Auto-Renew', function () {
    test('user can toggle auto-renew on their certificate', function () {
        $certificate = SslCertificate::factory()->letsEncrypt()->autoRenewEnabled()->create([
            'domain_id' => $this->domain->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/{$certificate->id}/toggle-auto-renew");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $certificate->refresh();
        expect($certificate->auto_renew)->toBeFalse();
    });

    test('user cannot toggle auto-renew on another user\'s certificate', function () {
        $certificate = SslCertificate::factory()->create([
            'domain_id' => $this->otherDomain->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/ssl/{$certificate->id}/toggle-auto-renew");

        $response->assertForbidden();
    });
});

describe('SSL API - Delete', function () {
    test('user can delete their certificate', function () {
        $certificate = SslCertificate::factory()->create([
            'domain_id' => $this->domain->id,
        ]);

        $this->mock(SslService::class, function ($mock) {
            $mock->shouldReceive('revokeCertificate')
                ->once();
        });

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/ssl/{$certificate->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Certificate revoked and deleted successfully.');
    });

    test('user cannot delete another user\'s certificate', function () {
        $certificate = SslCertificate::factory()->create([
            'domain_id' => $this->otherDomain->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/ssl/{$certificate->id}");

        $response->assertForbidden();
    });

    test('admin can delete any certificate', function () {
        $certificate = SslCertificate::factory()->create([
            'domain_id' => $this->otherDomain->id,
        ]);

        $this->mock(SslService::class, function ($mock) {
            $mock->shouldReceive('revokeCertificate')
                ->once();
        });

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/ssl/{$certificate->id}");

        $response->assertOk();
    });
});

describe('SSL API - Check Expiry', function () {
    test('admin can check expiring certificates', function () {
        SslCertificate::factory()->expiringSoon()->count(2)->create([
            'domain_id' => $this->domain->id,
        ]);
        SslCertificate::factory()->active()->create([
            'domain_id' => $this->domain->id,
            'expires_at' => now()->addMonths(2),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/ssl/check-expiry?days=14');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    });

    test('user can check their expiring certificates', function () {
        SslCertificate::factory()->expiringSoon()->create([
            'domain_id' => $this->domain->id,
        ]);
        SslCertificate::factory()->expiringSoon()->create([
            'domain_id' => $this->otherDomain->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/ssl/check-expiry?days=14');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });
});
