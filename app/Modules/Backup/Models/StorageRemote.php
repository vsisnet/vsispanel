<?php

declare(strict_types=1);

namespace App\Modules\Backup\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorageRemote extends Model
{
    use HasUuids;

    protected $table = 'storage_remotes';

    protected $fillable = [
        'name',
        'display_name',
        'type',
        'config',
        'is_active',
        'last_tested_at',
        'last_test_result',
    ];

    protected $casts = [
        'config' => 'encrypted:array',
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
        'last_test_result' => 'boolean',
    ];

    protected $hidden = [
        'config',
    ];

    /**
     * Type constants
     */
    public const TYPE_FTP = 'ftp';
    public const TYPE_SFTP = 'sftp';
    public const TYPE_GOOGLE_DRIVE = 'drive';
    public const TYPE_ONEDRIVE = 'onedrive';
    public const TYPE_DROPBOX = 'dropbox';
    public const TYPE_S3 = 's3';
    public const TYPE_B2 = 'b2';
    public const TYPE_WEBDAV = 'webdav';

    /**
     * Get all available types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_FTP => 'FTP',
            self::TYPE_SFTP => 'SFTP',
            self::TYPE_GOOGLE_DRIVE => 'Google Drive',
            self::TYPE_ONEDRIVE => 'OneDrive',
            self::TYPE_DROPBOX => 'Dropbox',
            self::TYPE_S3 => 'Amazon S3',
            self::TYPE_B2 => 'Backblaze B2',
            self::TYPE_WEBDAV => 'WebDAV',
        ];
    }

    /**
     * Get rclone remote name
     */
    public function getRcloneRemoteName(): string
    {
        return 'vsispanel_' . $this->name;
    }

    /**
     * Get rclone remote path for backups
     */
    public function getBackupPath(string $subPath = ''): string
    {
        $basePath = $this->config['path'] ?? '/backups';
        $basePath = trim($basePath, '/');

        if ($subPath) {
            $subPath = trim($subPath, '/');
            return "{$this->getRcloneRemoteName()}:{$basePath}/{$subPath}";
        }

        return "{$this->getRcloneRemoteName()}:{$basePath}";
    }

    /**
     * Get backup configurations using this remote
     */
    public function backupConfigs(): HasMany
    {
        return $this->hasMany(BackupConfig::class, 'storage_remote_id');
    }

    /**
     * Scope for active remotes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }
}
