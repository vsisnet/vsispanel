<?php

declare(strict_types=1);

namespace App\Modules\SSL\Database\Factories;

use App\Modules\Domain\Models\Domain;
use App\Modules\SSL\Models\SslCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SslCertificate>
 */
class SslCertificateFactory extends Factory
{
    protected $model = SslCertificate::class;

    public function definition(): array
    {
        $issuedAt = $this->faker->dateTimeBetween('-6 months', '-1 month');
        $expiresAt = (clone $issuedAt)->modify('+90 days');

        return [
            'domain_id' => Domain::factory(),
            'type' => $this->faker->randomElement(['lets_encrypt', 'custom']),
            'status' => 'active',
            'certificate_path' => '/etc/ssl/vsispanel/' . $this->faker->domainName . '/cert.pem',
            'private_key_path' => '/etc/ssl/vsispanel/' . $this->faker->domainName . '/privkey.pem',
            'ca_bundle_path' => '/etc/ssl/vsispanel/' . $this->faker->domainName . '/chain.pem',
            'issuer' => $this->faker->randomElement(["Let's Encrypt", 'DigiCert', 'Comodo', 'GeoTrust']),
            'serial_number' => $this->faker->uuid(),
            'san' => [$this->faker->domainName],
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
            'auto_renew' => true,
            'renewal_attempts' => 0,
            'last_renewal_at' => null,
            'last_error' => null,
        ];
    }

    /**
     * Configure the certificate as Let's Encrypt type.
     */
    public function letsEncrypt(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'lets_encrypt',
            'issuer' => "Let's Encrypt",
            'auto_renew' => true,
        ]);
    }

    /**
     * Configure the certificate as custom type.
     */
    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'custom',
            'issuer' => $this->faker->randomElement(['DigiCert', 'Comodo', 'GeoTrust', 'Sectigo']),
            'auto_renew' => false,
        ]);
    }

    /**
     * Configure the certificate as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => now()->addMonths(2),
        ]);
    }

    /**
     * Configure the certificate as pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'certificate_path' => null,
            'private_key_path' => null,
            'issued_at' => null,
            'expires_at' => null,
        ]);
    }

    /**
     * Configure the certificate as failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'last_error' => 'Certificate issuance failed: DNS validation error',
        ]);
    }

    /**
     * Configure the certificate as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subDays(5),
        ]);
    }

    /**
     * Configure the certificate as expiring soon (within 14 days).
     */
    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'expires_at' => now()->addDays(10),
        ]);
    }

    /**
     * Configure the certificate as revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
        ]);
    }

    /**
     * Configure auto-renewal enabled.
     */
    public function autoRenewEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_renew' => true,
        ]);
    }

    /**
     * Configure auto-renewal disabled.
     */
    public function autoRenewDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_renew' => false,
        ]);
    }
}
