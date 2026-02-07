<?php

declare(strict_types=1);

namespace App\Modules\Mail\Services;

use App\Models\User;
use App\Modules\Domain\Models\Domain;
use App\Modules\Mail\Models\MailAccount;
use App\Modules\Mail\Models\MailAlias;
use App\Modules\Mail\Models\MailDomain;
use App\Modules\Mail\Models\MailForward;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MailAccountService
{
    public function __construct(
        protected PostfixService $postfixService,
        protected DovecotService $dovecotService,
        protected MailSecurityService $securityService
    ) {}

    /**
     * Enable mail for a domain.
     */
    public function enableMailForDomain(Domain $domain, array $options = []): MailDomain
    {
        // Check if already enabled
        $existing = MailDomain::where('domain_id', $domain->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($domain, $options) {
            // Create mail domain
            $mailDomain = MailDomain::create([
                'domain_id' => $domain->id,
                'is_active' => true,
                'max_accounts' => $options['max_accounts'] ?? 100,
                'default_quota_mb' => $options['default_quota_mb'] ?? config('vsispanel.mail.default_quota', 1024),
            ]);

            // Add domain to Postfix
            $this->postfixService->addDomain($domain->name);

            // Generate DKIM if enabled
            if ($options['enable_dkim'] ?? true) {
                $dkim = $this->securityService->generateDKIM($domain->name);
                $mailDomain->update([
                    'dkim_enabled' => true,
                    'dkim_selector' => $dkim['selector'],
                    'dkim_private_key' => $dkim['private_key'] ?? null,
                    'dkim_public_key' => $dkim['public_key'],
                ]);
            }

            return $mailDomain;
        });
    }

    /**
     * Disable mail for a domain.
     */
    public function disableMailForDomain(MailDomain $mailDomain): void
    {
        DB::transaction(function () use ($mailDomain) {
            $domainName = $mailDomain->domain->name;

            // Remove all accounts from Postfix/Dovecot
            foreach ($mailDomain->accounts as $account) {
                $this->postfixService->removeMailbox($account->email);
                $this->dovecotService->deleteMailbox($account->email);
            }

            // Remove domain from Postfix
            $this->postfixService->removeDomain($domainName);

            // Delete DKIM keys
            if ($mailDomain->dkim_enabled) {
                $this->securityService->deleteDKIM($domainName);
            }

            // Delete mail domain (will cascade delete accounts, forwards, aliases)
            $mailDomain->delete();
        });
    }

    /**
     * Create a mail account.
     */
    public function createAccount(User $user, MailDomain $mailDomain, array $data): MailAccount
    {
        // Check account limit
        if (!$mailDomain->canCreateAccount()) {
            throw new RuntimeException("Maximum number of email accounts reached for this domain.");
        }

        $domainName = $mailDomain->domain->name;
        $email = "{$data['username']}@{$domainName}";
        $password = $data['password'];
        $quotaMb = $data['quota_mb'] ?? $mailDomain->default_quota_mb;

        // Check if email already exists
        if (MailAccount::where('email', $email)->exists()) {
            throw new RuntimeException("Email address already exists.");
        }

        return DB::transaction(function () use ($user, $mailDomain, $email, $password, $quotaMb, $data) {
            // Create in Postfix
            $this->postfixService->addMailbox($email, $password);

            // Create in Dovecot with quota
            $this->dovecotService->createMailbox($email, $password, $quotaMb);

            // Create database record
            $account = MailAccount::create([
                'mail_domain_id' => $mailDomain->id,
                'user_id' => $user->id,
                'email' => $email,
                'username' => $data['username'],
                'password_hash' => $this->dovecotService->hashPassword($password),
                'quota_mb' => $quotaMb,
                'status' => 'active',
            ]);

            return $account;
        });
    }

    /**
     * Delete a mail account.
     */
    public function deleteAccount(MailAccount $account): void
    {
        DB::transaction(function () use ($account) {
            // Remove from Postfix
            $this->postfixService->removeMailbox($account->email);

            // Remove from Dovecot
            $this->dovecotService->deleteMailbox($account->email);

            // Remove forwards from Postfix
            foreach ($account->forwards as $forward) {
                $this->postfixService->removeAlias($account->email);
            }

            // Delete database record
            $account->delete();
        });
    }

    /**
     * Change account password.
     */
    public function changePassword(MailAccount $account, string $newPassword): void
    {
        DB::transaction(function () use ($account, $newPassword) {
            // Update in Dovecot
            $this->dovecotService->changePassword($account->email, $newPassword);

            // Update hash in database
            $account->update([
                'password_hash' => $this->dovecotService->hashPassword($newPassword),
            ]);
        });
    }

    /**
     * Suspend a mail account.
     */
    public function suspendAccount(MailAccount $account): void
    {
        // Note: Actual suspension could involve modifying Dovecot userdb
        // For now, we track status in database and can implement Dovecot blocking later
        $account->update(['status' => 'suspended']);
    }

    /**
     * Unsuspend a mail account.
     */
    public function unsuspendAccount(MailAccount $account): void
    {
        $account->update(['status' => 'active']);
    }

    /**
     * Set auto-responder for an account.
     */
    public function setAutoResponder(
        MailAccount $account,
        string $subject,
        string $message,
        ?string $startAt = null,
        ?string $endAt = null
    ): void {
        $account->update([
            'auto_responder_enabled' => true,
            'auto_responder_subject' => $subject,
            'auto_responder_message' => $message,
            'auto_responder_start_at' => $startAt,
            'auto_responder_end_at' => $endAt,
        ]);

        // Note: Actual auto-responder implementation would require Sieve rules
        // This can be implemented via DovecotService in a more complete setup
    }

    /**
     * Disable auto-responder.
     */
    public function disableAutoResponder(MailAccount $account): void
    {
        $account->update([
            'auto_responder_enabled' => false,
        ]);
    }

    /**
     * Add forwarding to an account.
     */
    public function addForwarding(MailAccount $account, string $forwardTo, bool $keepCopy = true): MailForward
    {
        // Check if forward already exists
        $existing = MailForward::where('mail_account_id', $account->id)
            ->where('forward_to', $forwardTo)
            ->first();

        if ($existing) {
            throw new RuntimeException("Forward to this address already exists.");
        }

        return DB::transaction(function () use ($account, $forwardTo, $keepCopy) {
            // Add to Postfix
            $this->postfixService->addForwarding($account->email, $forwardTo, $keepCopy);

            // Create database record
            return MailForward::create([
                'mail_account_id' => $account->id,
                'forward_to' => $forwardTo,
                'keep_copy' => $keepCopy,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Remove forwarding.
     */
    public function removeForwarding(MailForward $forward): void
    {
        DB::transaction(function () use ($forward) {
            $account = $forward->mailAccount;

            // Rebuild Postfix forwards (remove this one)
            $activeForwards = $account->forwards()->where('id', '!=', $forward->id)->active()->get();

            if ($activeForwards->isEmpty()) {
                // No more forwards, remove alias
                $this->postfixService->removeAlias($account->email);
            } else {
                // Rebuild with remaining forwards
                $destinations = $activeForwards->pluck('forward_to')->toArray();
                if ($forward->keep_copy) {
                    array_unshift($destinations, $account->email);
                }
                $this->postfixService->addAlias($account->email, implode(',', $destinations));
            }

            $forward->delete();
        });
    }

    /**
     * Set account quota.
     */
    public function setQuota(MailAccount $account, int $quotaMb): void
    {
        DB::transaction(function () use ($account, $quotaMb) {
            // Update in Dovecot
            $this->dovecotService->setQuota($account->email, $quotaMb);

            // Update database
            $account->update(['quota_mb' => $quotaMb]);
        });
    }

    /**
     * Get account usage statistics.
     */
    public function getUsageStats(MailAccount $account): array
    {
        $info = $this->dovecotService->getMailboxInfo($account->email);

        // Update quota_used in database
        $account->updateQuotaUsed($info['quota_used']);

        return [
            'email' => $account->email,
            'status' => $account->status,
            'quota_mb' => $account->quota_mb,
            'quota_used_mb' => $account->quota_used_mb,
            'quota_percent' => $account->quota_usage_percent,
            'message_count' => $info['message_count'],
            'unread_count' => $info['unread_count'],
            'last_login' => $account->last_login_at,
            'forwards_count' => $account->forwards()->active()->count(),
            'auto_responder_active' => $account->isAutoResponderActive(),
        ];
    }

    /**
     * Create an alias.
     */
    public function createAlias(MailDomain $mailDomain, string $source, string $destination): MailAlias
    {
        $domainName = $mailDomain->domain->name;
        $sourceAddress = str_contains($source, '@') ? $source : "{$source}@{$domainName}";

        // Check if alias already exists
        $existing = MailAlias::where('mail_domain_id', $mailDomain->id)
            ->where('source_address', $sourceAddress)
            ->first();

        if ($existing) {
            throw new RuntimeException("Alias already exists.");
        }

        return DB::transaction(function () use ($mailDomain, $sourceAddress, $destination) {
            // Add to Postfix
            $this->postfixService->addAlias($sourceAddress, $destination);

            // Create database record
            return MailAlias::create([
                'mail_domain_id' => $mailDomain->id,
                'source_address' => $sourceAddress,
                'destination_address' => $destination,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Delete an alias.
     */
    public function deleteAlias(MailAlias $alias): void
    {
        DB::transaction(function () use ($alias) {
            $this->postfixService->removeAlias($alias->source_address);
            $alias->delete();
        });
    }

    /**
     * Set catch-all for a domain.
     */
    public function setCatchAll(MailDomain $mailDomain, ?string $destination): void
    {
        $domainName = $mailDomain->domain->name;
        $catchAllAddress = "@{$domainName}";

        DB::transaction(function () use ($mailDomain, $catchAllAddress, $destination) {
            if ($destination) {
                $this->postfixService->addAlias($catchAllAddress, $destination);
                $mailDomain->update(['catch_all_address' => $destination]);
            } else {
                $this->postfixService->removeAlias($catchAllAddress);
                $mailDomain->update(['catch_all_address' => null]);
            }
        });
    }
}
