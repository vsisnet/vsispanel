<?php

declare(strict_types=1);

namespace App\Modules\DNS\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DnsRecord extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'dns_zone_id',
        'name',
        'type',
        'content',
        'ttl',
        'priority',
        'weight',
        'port',
        'disabled',
    ];

    protected $casts = [
        'ttl' => 'integer',
        'priority' => 'integer',
        'weight' => 'integer',
        'port' => 'integer',
        'disabled' => 'boolean',
    ];

    /**
     * Record types that require priority.
     */
    public const PRIORITY_TYPES = ['MX', 'SRV'];

    /**
     * Record types that require weight and port (SRV only).
     */
    public const SRV_EXTRA_FIELDS = ['SRV'];

    /**
     * Get the DNS zone that owns the record.
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(DnsZone::class, 'dns_zone_id');
    }

    /**
     * Alias for zone relationship.
     */
    public function dnsZone(): BelongsTo
    {
        return $this->zone();
    }

    /**
     * Scope for enabled records.
     */
    public function scopeEnabled($query)
    {
        return $query->where('disabled', false);
    }

    /**
     * Scope for records by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the full record name (FQDN).
     */
    public function getFullNameAttribute(): string
    {
        if ($this->name === '@' || $this->name === $this->zone->zone_name) {
            return $this->zone->zone_name;
        }

        return $this->name . '.' . $this->zone->zone_name;
    }

    /**
     * Check if record type requires priority.
     */
    public function requiresPriority(): bool
    {
        return in_array($this->type, self::PRIORITY_TYPES);
    }

    /**
     * Check if record is SRV type.
     */
    public function isSrvRecord(): bool
    {
        return $this->type === 'SRV';
    }

    /**
     * Format record for PowerDNS API.
     */
    public function toPowerDnsFormat(): array
    {
        $record = [
            'content' => $this->formatContent(),
            'disabled' => $this->disabled,
        ];

        return $record;
    }

    /**
     * Format content based on record type.
     */
    public function formatContent(): string
    {
        switch ($this->type) {
            case 'MX':
                return "{$this->priority} {$this->content}";
            case 'SRV':
                return "{$this->priority} {$this->weight} {$this->port} {$this->content}";
            case 'TXT':
                // Ensure TXT records are quoted
                if (!str_starts_with($this->content, '"')) {
                    return '"' . $this->content . '"';
                }
                return $this->content;
            default:
                return $this->content;
        }
    }
}
