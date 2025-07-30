<?php

namespace App\Services;

interface EmailProviderInterface
{
    /**
     * Fetch emails from the provider
     */
    public function fetchEmails(int $limit = 50, ?string $pageToken = null, bool $fetchAll = false): array;

    /**
     * Get OAuth authorization URL
     */
    public function getAuthUrl(string $redirectUri, ?string $state = null): string;

    /**
     * Handle OAuth callback and store tokens
     */
    public function handleCallback(string $code, string $redirectUri): bool;

    /**
     * Check if the account is properly authenticated
     */
    public function isAuthenticated(): bool;

    /**
     * Refresh access token if needed
     */
    public function refreshToken(): bool;

    /**
     * Send email through the provider
     */
    public function sendEmail(array $emailData): bool;

    /**
     * Get account info
     */
    public function getAccountInfo(): array;

    /**
     * Save a draft in the email provider
     */
    public function saveDraft(string $to, string $subject, string $body, ?string $inReplyTo = null, ?string $threadId = null): ?string;

    /**
     * Process a single email by its message ID
     */
    public function processSingleEmail(string $messageId, array $options = []): ?array;
}
