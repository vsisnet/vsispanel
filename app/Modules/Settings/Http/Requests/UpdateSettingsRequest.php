<?php

declare(strict_types=1);

namespace App\Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Frontend sends flat dot-notation keys like {"notifications.email.enabled": true}
        // but Laravel validation expects nested structure {"notifications": {"email": {"enabled": true}}}.
        // Convert flat keys to nested so validated() returns the data correctly.
        $nested = [];
        foreach ($this->all() as $key => $value) {
            data_set($nested, $key, $value);
        }
        $this->replace($nested);
    }

    public function rules(): array
    {
        return [
            'general.timezone' => ['sometimes', 'string', 'timezone'],
            'general.panel_name' => ['sometimes', 'string', 'max:100'],

            // Mail provider configuration
            'mail.provider' => ['sometimes', 'string', 'in:smtp,gmail,gmail_oauth,outlook,ses,sendmail'],
            'mail.from_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'mail.from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail.smtp_host' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail.smtp_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'mail.smtp_username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail.smtp_password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail.smtp_encryption' => ['sometimes', 'nullable', 'string', 'in:tls,ssl,none'],
            'mail.ses_key' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail.ses_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mail.ses_region' => ['sometimes', 'nullable', 'string', 'max:50'],

            // Notification channels
            'notifications.email.enabled' => ['sometimes', 'boolean'],
            'notifications.email.recipients' => ['sometimes', 'nullable', 'string', 'max:500'],
            'notifications.telegram.enabled' => ['sometimes', 'boolean'],
            'notifications.telegram.bot_token' => ['sometimes', 'nullable', 'string', 'max:200'],
            'notifications.telegram.chat_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notifications.slack.enabled' => ['sometimes', 'boolean'],
            'notifications.slack.webhook_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],
            'notifications.discord.enabled' => ['sometimes', 'boolean'],
            'notifications.discord.webhook_url' => ['sometimes', 'nullable', 'url:http,https', 'max:500'],

            // SSL settings
            'ssl.letsencrypt_email' => ['sometimes', 'nullable', 'email', 'max:255'],
        ];
    }
}
