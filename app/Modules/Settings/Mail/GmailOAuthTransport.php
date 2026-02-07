<?php

declare(strict_types=1);

namespace App\Modules\Settings\Mail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\RawMessage;

class GmailOAuthTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $username,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        $accessToken = $this->refreshAccessToken();

        $transport = new EsmtpTransport('smtp.gmail.com', 587);
        $transport->setUsername($this->username);
        $transport->setPassword($accessToken);
        $transport->setAuthenticators([new XOAuth2Authenticator()]);

        $transport->send($message->getOriginalMessage(), Envelope::create($message->getOriginalMessage()));
    }

    private function refreshAccessToken(): string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $this->refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->ok()) {
            Log::error('Gmail OAuth token refresh failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Failed to refresh Gmail OAuth2 access token');
        }

        return $response->json('access_token');
    }

    public function __toString(): string
    {
        return sprintf('gmail+oauth://%s@smtp.gmail.com', $this->username);
    }
}
