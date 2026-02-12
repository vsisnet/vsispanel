<?php

declare(strict_types=1);

namespace App\Modules\DNS\Models;

use App\Modules\Domain\Models\Domain;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DnsZone extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'domain_id',
        'zone_name',
        'serial',
        'refresh',
        'retry',
        'expire',
        'minimum_ttl',
        'primary_ns',
        'admin_email',
        'status',
    ];

    protected $casts = [
        'serial' => 'integer',
        'refresh' => 'integer',
        'retry' => 'integer',
        'expire' => 'integer',
        'minimum_ttl' => 'integer',
    ];

    /**
     * Get the domain that owns the DNS zone.
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the DNS records for the zone.
     */
    public function records(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }

    /**
     * Increment the zone serial number.
     */
    public function incrementSerial(): void
    {
        // Serial format: YYYYMMDDnn (date + sequence)
        $today = now()->format('Ymd');
        $currentSerial = (string) $this->serial;
        $currentDate = substr($currentSerial, 0, 8);

        if ($currentDate === $today) {
            // Same day, increment sequence
            $sequence = (int) substr($currentSerial, 8) + 1;
            $this->serial = (int) ($today . str_pad((string) $sequence, 2, '0', STR_PAD_LEFT));
        } else {
            // New day, reset sequence
            $this->serial = (int) ($today . '01');
        }

        $this->save();
    }

    /**
     * Scope for active zones.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get SOA record content.
     */
    public function getSoaContent(): string
    {
        $ns = rtrim($this->primary_ns, '.') . '.';
        $admin = rtrim($this->admin_email, '.') . '.';

        return sprintf(
            '%s %s %d %d %d %d %d',
            $ns,
            $admin,
            $this->serial,
            $this->refresh,
            $this->retry,
            $this->expire,
            $this->minimum_ttl
        );
    }
}
