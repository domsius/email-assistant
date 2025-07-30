<?php

namespace App\Services;

use App\Models\EmailAccount;
use InvalidArgumentException;

class EmailProviderFactory
{
    public function createProvider(EmailAccount $emailAccount): EmailProviderInterface
    {
        return match ($emailAccount->provider) {
            'gmail' => app(GmailService::class, ['emailAccount' => $emailAccount]),
            'outlook' => app(OutlookService::class, ['emailAccount' => $emailAccount]),
            default => throw new InvalidArgumentException("Unsupported email provider: {$emailAccount->provider}")
        };
    }

    public static function getSupportedProviders(): array
    {
        return [
            'gmail' => [
                'name' => 'Gmail',
                'icon' => 'gmail',
                'scopes' => ['https://www.googleapis.com/auth/gmail.readonly'],
                'oauth_url' => 'https://accounts.google.com/o/oauth2/auth',
            ],
            'outlook' => [
                'name' => 'Microsoft Outlook',
                'icon' => 'outlook',
                'scopes' => ['https://graph.microsoft.com/Mail.Read'],
                'oauth_url' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            ],
        ];
    }
}
