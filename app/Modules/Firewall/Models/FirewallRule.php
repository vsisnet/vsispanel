<?php

declare(strict_types=1);

namespace App\Modules\Firewall\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FirewallRule extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'action',
        'direction',
        'protocol',
        'port',
        'source_ip',
        'destination_ip',
        'comment',
        'is_active',
        'is_essential',
        'priority',
        'ufw_rule_number',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_essential' => 'boolean',
        'priority' => 'integer',
        'ufw_rule_number' => 'integer',
    ];

    protected $attributes = [
        'is_active' => true,
        'is_essential' => false,
        'priority' => 100,
    ];

    // Action constants
    public const ACTION_ALLOW = 'allow';
    public const ACTION_DENY = 'deny';
    public const ACTION_LIMIT = 'limit';
    public const ACTION_REJECT = 'reject';

    // Direction constants
    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';

    // Protocol constants
    public const PROTOCOL_TCP = 'tcp';
    public const PROTOCOL_UDP = 'udp';
    public const PROTOCOL_ANY = 'any';

    /**
     * Get available actions
     */
    public static function getActions(): array
    {
        return [
            self::ACTION_ALLOW,
            self::ACTION_DENY,
            self::ACTION_LIMIT,
            self::ACTION_REJECT,
        ];
    }

    /**
     * Get available directions
     */
    public static function getDirections(): array
    {
        return [
            self::DIRECTION_IN,
            self::DIRECTION_OUT,
        ];
    }

    /**
     * Get available protocols
     */
    public static function getProtocols(): array
    {
        return [
            self::PROTOCOL_TCP,
            self::PROTOCOL_UDP,
            self::PROTOCOL_ANY,
        ];
    }

    /**
     * Build UFW command for this rule
     */
    public function buildUfwCommand(): string
    {
        $parts = ['ufw'];

        // Action
        $parts[] = $this->action;

        // Direction
        if ($this->direction === self::DIRECTION_OUT) {
            $parts[] = 'out';
        }

        // Protocol (if not 'any')
        if ($this->protocol && $this->protocol !== self::PROTOCOL_ANY) {
            $parts[] = 'proto';
            $parts[] = $this->protocol;
        }

        // Source IP
        if ($this->source_ip) {
            $parts[] = 'from';
            $parts[] = $this->source_ip;
        } else {
            $parts[] = 'from';
            $parts[] = 'any';
        }

        // Destination
        if ($this->destination_ip) {
            $parts[] = 'to';
            $parts[] = $this->destination_ip;
        } else {
            $parts[] = 'to';
            $parts[] = 'any';
        }

        // Port
        if ($this->port) {
            $parts[] = 'port';
            $parts[] = $this->port;
        }

        // Comment
        if ($this->comment) {
            $parts[] = 'comment';
            $parts[] = escapeshellarg($this->comment);
        }

        return implode(' ', $parts);
    }

    /**
     * Get display label for the rule
     */
    public function getDisplayLabel(): string
    {
        $parts = [];

        $parts[] = strtoupper($this->action);

        if ($this->direction === self::DIRECTION_OUT) {
            $parts[] = 'OUT';
        }

        if ($this->protocol && $this->protocol !== self::PROTOCOL_ANY) {
            $parts[] = strtoupper($this->protocol);
        }

        if ($this->port) {
            $parts[] = "port {$this->port}";
        }

        if ($this->source_ip && $this->source_ip !== 'any') {
            $parts[] = "from {$this->source_ip}";
        }

        return implode(' ', $parts);
    }

    /**
     * Scope for active rules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for essential rules
     */
    public function scopeEssential($query)
    {
        return $query->where('is_essential', true);
    }

    /**
     * Scope ordered by priority
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('priority')->orderBy('created_at');
    }
}
