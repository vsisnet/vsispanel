<?php

declare(strict_types=1);

namespace App\Modules\SSL\Policies;

use App\Modules\Auth\Models\User;
use App\Modules\Base\Policies\ModulePolicy;
use App\Modules\SSL\Models\SslCertificate;
use Illuminate\Database\Eloquent\Model;

class SslCertificatePolicy extends ModulePolicy
{
    protected string $permissionPrefix = 'ssl';

    /**
     * Determine whether the user can view any SSL certificates.
     * Admin can see all, regular users can only see their own via domain ownership.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo("{$this->permissionPrefix}.view");
    }

    /**
     * Determine whether the user can view the SSL certificate.
     */
    public function view(User $user, Model $model): bool
    {
        /** @var SslCertificate $model */
        if (!$user->hasPermissionTo("{$this->permissionPrefix}.view")) {
            return false;
        }

        return $this->checkOwnership($user, $model);
    }

    /**
     * Determine whether the user can create SSL certificates.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo("{$this->permissionPrefix}.create");
    }

    /**
     * Determine whether the user can update the SSL certificate.
     */
    public function update(User $user, Model $model): bool
    {
        /** @var SslCertificate $model */
        if (!$user->hasPermissionTo("{$this->permissionPrefix}.edit")) {
            return false;
        }

        return $this->checkOwnership($user, $model);
    }

    /**
     * Determine whether the user can delete the SSL certificate.
     */
    public function delete(User $user, Model $model): bool
    {
        /** @var SslCertificate $model */
        if (!$user->hasPermissionTo("{$this->permissionPrefix}.delete")) {
            return false;
        }

        return $this->checkOwnership($user, $model);
    }

    /**
     * Determine whether the user can renew the SSL certificate.
     */
    public function renew(User $user, SslCertificate $certificate): bool
    {
        if (!$user->hasPermissionTo("{$this->permissionPrefix}.renew")) {
            return false;
        }

        return $this->checkOwnership($user, $certificate);
    }

    /**
     * Check ownership through the domain relationship.
     */
    protected function checkOwnership(User $user, Model $model): bool
    {
        /** @var SslCertificate $model */
        // Admin can access all
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if user owns the domain
        $domain = $model->domain;
        if (!$domain) {
            return false;
        }

        // Compare as strings to handle UUID type differences
        return (string) $domain->user_id === (string) $user->id;
    }
}
