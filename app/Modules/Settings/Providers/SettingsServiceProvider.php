<?php

declare(strict_types=1);

namespace App\Modules\Settings\Providers;

use App\Modules\Settings\Mail\GmailOAuthTransport;
use App\Modules\Settings\Services\SettingsService;
use App\Services\SystemCommandExecutor;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class, function ($app) {
            return new SettingsService(
                $app->make(SystemCommandExecutor::class),
            );
        });
    }

    public function boot(): void
    {
        // Register custom Gmail OAuth mailer transport
        Mail::extend('gmail_oauth', function (array $config) {
            return new GmailOAuthTransport(
                username: $config['username'] ?? '',
                clientId: $config['client_id'] ?? '',
                clientSecret: $config['client_secret'] ?? '',
                refreshToken: $config['refresh_token'] ?? '',
            );
        });

        // After app boots, apply DB notification config overrides
        // so AlertEvaluator reads the latest values from system_settings
        $this->app->booted(function () {
            try {
                if (Schema::hasTable('system_settings')) {
                    $this->app->make(SettingsService::class)->applyNotificationConfigOverrides();
                }
            } catch (\Exception) {
                // Silently fail if DB not ready (e.g., during migration)
            }
        });
    }
}
