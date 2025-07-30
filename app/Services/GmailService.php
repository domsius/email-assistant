<?php

namespace App\Services;

use App\Models\EmailAccount;
use Exception;
use Google\Client as GoogleClient;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Illuminate\Support\Facades\Log;

class GmailService implements EmailProviderInterface
{
    private GoogleClient $client;

    private Gmail $gmail;

    private EmailAccount $emailAccount;

    private ?string $nextPageToken = null;

    public function __construct(EmailAccount $emailAccount)
    {
        $this->emailAccount = $emailAccount;
        $this->initializeGoogleClient();
    }

    private function initializeGoogleClient(): void
    {
        $this->client = new GoogleClient;
        $this->client->setClientId(config('services.google.client_id'));
        $this->client->setClientSecret(config('services.google.client_secret'));
        $this->client->setScopes([
            Gmail::GMAIL_READONLY,
            Gmail::GMAIL_SEND,
            Gmail::GMAIL_COMPOSE,
        ]);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        if ($this->emailAccount->access_token) {
            $this->client->setAccessToken([
                'access_token' => $this->emailAccount->access_token,
                'refresh_token' => $this->emailAccount->refresh_token,
                'expires_in' => $this->emailAccount->token_expires_at?->diffInSeconds(now()),
            ]);

            if ($this->client->isAccessTokenExpired()) {
                $this->refreshToken();
            }
        }

        $this->gmail = new Gmail($this->client);
    }

    public function getAuthUrl(string $redirectUri, ?string $state = null): string
    {
        $this->client->setRedirectUri($redirectUri);
        if ($state) {
            $this->client->setState($state);
        }

        return $this->client->createAuthUrl();
    }

    public function handleCallback(string $code, string $redirectUri): bool
    {
        try {
            $this->client->setRedirectUri($redirectUri);
            $token = $this->client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                throw new Exception('OAuth error: '.$token['error_description'] ?? $token['error']);
            }

            $this->emailAccount->update([
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? $this->emailAccount->refresh_token,
                'token_expires_at' => now()->addSeconds($token['expires_in']),
                'is_active' => true,
                'last_sync_at' => now(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Gmail OAuth callback failed: '.$e->getMessage());

            return false;
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->emailAccount->access_token &&
               $this->emailAccount->refresh_token &&
               $this->emailAccount->is_active;
    }

    public function refreshToken(): bool
    {
        try {
            if (! $this->emailAccount->refresh_token) {
                return false;
            }

            $this->client->refreshToken($this->emailAccount->refresh_token);
            $token = $this->client->getAccessToken();

            $this->emailAccount->update([
                'access_token' => $token['access_token'],
                'token_expires_at' => now()->addSeconds($token['expires_in']),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Gmail token refresh failed: '.$e->getMessage());

            return false;
        }
    }

    public function fetchEmails(?int $limit = null, ?string $pageToken = null, bool $fetchAll = false): array
    {
        try {
            if (! $this->isAuthenticated()) {
                throw new Exception('Gmail account not authenticated');
            }

            // Use config value if limit not specified
            $limit = $limit ?? config('mail-sync.sync_email_limit', 200);

            $emails = [];
            $batchSize = 100; // Gmail API limit per request
            $totalFetched = 0;
            $currentPageToken = $pageToken;

            while ($totalFetched < $limit) {
                $requestLimit = min($batchSize, $limit - $totalFetched);

                $params = [
                    'maxResults' => $requestLimit,
                ];

                // Build base query
                $query = 'in:inbox';

                // Limit is now handled by maxResults parameter
                // No date filter - fetching most recent emails up to the limit

                // Add unread filter if not fetching all
                if (! $fetchAll) {
                    $query .= ' is:unread';
                }

                $params['q'] = $query;

                if ($currentPageToken) {
                    $params['pageToken'] = $currentPageToken;
                }

                // Get list of messages
                $response = $this->gmail->users_messages->listUsersMessages('me', $params);
                $messages = $response->getMessages();

                if (! $messages || empty($messages)) {
                    break; // No more messages
                }

                foreach ($messages as $message) {
                    $email = $this->processGmailMessage($message);
                    if ($email) {
                        $emails[] = $email;
                        $totalFetched++;
                    }
                }

                // Check if there are more pages
                $currentPageToken = $response->getNextPageToken();
                $this->nextPageToken = $currentPageToken;
                if (! $currentPageToken) {
                    break; // No more pages
                }

                Log::info('Fetched batch of emails', [
                    'batch_size' => count($messages),
                    'total_fetched' => $totalFetched,
                    'has_next_page' => ! empty($currentPageToken),
                    'fetch_all' => $fetchAll,
                ]);
            }

            Log::info('Gmail fetch completed', [
                'total_emails' => count($emails),
                'requested_limit' => $limit,
                'fetch_all' => $fetchAll,
            ]);

            return $emails;
        } catch (Exception $e) {
            Log::error('Gmail fetch emails failed: '.$e->getMessage());

            return [];
        }
    }

    private function processGmailMessage(Message $message): ?array
    {
        try {
            $fullMessage = $this->gmail->users_messages->get('me', $message->getId());
            $headers = $fullMessage->getPayload()->getHeaders();

            $subject = $this->getHeader($headers, 'Subject') ?? 'No Subject';
            $from = $this->getHeader($headers, 'From') ?? '';
            $date = $this->getHeader($headers, 'Date') ?? '';

            // Extract sender email and name
            preg_match('/([^<]+)?<?([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>?/', $from, $matches);
            $senderEmail = $matches[2] ?? '';
            $senderName = trim($matches[1] ?? '', ' "');

            if (! $senderEmail) {
                return null; // Skip emails without valid sender
            }

            // Get email body
            $bodyData = $this->extractBodyFromPayload($fullMessage->getPayload());

            return [
                'message_id' => $fullMessage->getId(),
                'thread_id' => $fullMessage->getThreadId(),
                'subject' => $subject,
                'sender_email' => $senderEmail,
                'sender_name' => $senderName ?: null,
                'body_content' => $bodyData['plain'],
                'body_html' => $bodyData['html'],
                'received_at' => $date ? \Carbon\Carbon::parse($date) : now(),
                'provider_data' => [
                    'gmail_id' => $fullMessage->getId(),
                    'labels' => $fullMessage->getLabelIds(),
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error processing Gmail message: '.$e->getMessage());

            return null;
        }
    }

    private function getHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $header) {
            if ($header->getName() === $name) {
                return $header->getValue();
            }
        }

        return null;
    }

    private function extractBodyFromPayload($payload): array
    {
        $plainBody = '';
        $htmlBody = '';

        Log::info('=== Gmail: Extracting body from payload ===', [
            'mime_type' => $payload->getMimeType(),
            'has_parts' => ! empty($payload->getParts()),
            'parts_count' => $payload->getParts() ? count($payload->getParts()) : 0,
        ]);

        if ($payload->getParts()) {
            foreach ($payload->getParts() as $part) {
                if ($part->getMimeType() === 'text/plain') {
                    $data = $part->getBody()->getData();
                    $plainBody = base64url_decode($data);
                } elseif ($part->getMimeType() === 'text/html') {
                    $data = $part->getBody()->getData();
                    $htmlBody = base64url_decode($data);
                    Log::info('Gmail: Found HTML part', [
                        'html_length' => strlen($htmlBody),
                        'has_style_tags' => str_contains($htmlBody, '<style'),
                        'preview' => substr($htmlBody, 0, 200),
                    ]);
                }

                // Handle multipart/alternative or multipart/related
                if ($part->getParts()) {
                    $nestedBodies = $this->extractBodyFromPayload($part);
                    if (! $plainBody && $nestedBodies['plain']) {
                        $plainBody = $nestedBodies['plain'];
                    }
                    if (! $htmlBody && $nestedBodies['html']) {
                        $htmlBody = $nestedBodies['html'];
                    }
                }
            }
        } else {
            // Single part message
            $data = $payload->getBody()->getData();
            if ($data) {
                $decodedData = base64url_decode($data);
                if ($payload->getMimeType() === 'text/html') {
                    $htmlBody = $decodedData;
                    $plainBody = strip_tags($decodedData);
                } else {
                    $plainBody = $decodedData;
                }
            }
        }

        // If we only have HTML, create plain text version
        if ($htmlBody && ! $plainBody) {
            $plainBody = strip_tags($htmlBody);
        }

        Log::info('=== Gmail: Final extracted content ===', [
            'has_html' => ! empty($htmlBody),
            'html_length' => strlen($htmlBody),
            'has_plain' => ! empty($plainBody),
            'plain_length' => strlen($plainBody),
            'html_has_styles' => str_contains($htmlBody, '<style'),
        ]);

        return [
            'plain' => $plainBody ?: 'No content',
            'html' => $htmlBody,
        ];
    }

    /**
     * Decode base64url encoded string (Gmail API uses base64url encoding)
     */
    private function base64url_decode(string $data): string
    {
        // Replace URL-safe characters with standard base64 characters
        $data = str_replace(['-', '_'], ['+', '/'], $data);

        // Add padding if necessary
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($data);
    }

    public function sendEmail(array $emailData): bool
    {
        try {
            if (! $this->isAuthenticated()) {
                throw new Exception('Gmail account not authenticated');
            }

            // Create the email message
            $message = new \Google\Service\Gmail\Message;

            // Build the email headers and body
            $emailContent = $this->buildEmailContent($emailData);
            $message->setRaw(base64url_encode($emailContent));

            // Send the email
            $result = $this->gmail->users_messages->send('me', $message);

            Log::info('Gmail email sent successfully', [
                'message_id' => $result->getId(),
                'to' => $emailData['to'],
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Gmail send email failed: '.$e->getMessage(), [
                'to' => $emailData['to'] ?? 'unknown',
                'subject' => $emailData['subject'] ?? 'unknown',
            ]);

            return false;
        }
    }

    private function buildEmailContent(array $emailData): string
    {
        $to = $emailData['to'];
        $subject = $emailData['subject'];
        $body = $emailData['body'];
        $fromEmail = $this->emailAccount->email_address;
        $fromName = $this->emailAccount->email_address; // Could be improved with user name

        $headers = [];
        $headers[] = "From: {$fromName} <{$fromEmail}>";
        $headers[] = "To: {$to}";

        // Add CC recipients if present
        if (! empty($emailData['cc'])) {
            $headers[] = "Cc: {$emailData['cc']}";
        }

        // Add BCC recipients if present
        if (! empty($emailData['bcc'])) {
            $headers[] = "Bcc: {$emailData['bcc']}";
        }

        $headers[] = "Subject: {$subject}";
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=utf-8';
        $headers[] = 'Content-Transfer-Encoding: quoted-printable';

        // Add In-Reply-To header if this is a reply
        if (isset($emailData['in_reply_to'])) {
            $headers[] = "In-Reply-To: <{$emailData['in_reply_to']}>";
        }

        // Add References header for threading
        if (isset($emailData['in_reply_to'])) {
            $headers[] = "References: <{$emailData['in_reply_to']}>";
        }

        $emailContent = implode("\r\n", $headers)."\r\n\r\n".quoted_printable_encode($body);

        return $emailContent;
    }

    public function getAccountInfo(): array
    {
        try {
            $profile = $this->gmail->users->getProfile('me');

            return [
                'email' => $profile->getEmailAddress(),
                'messages_total' => $profile->getMessagesTotal(),
                'threads_total' => $profile->getThreadsTotal(),
            ];
        } catch (Exception $e) {
            Log::error('Gmail get account info failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Save a draft in Gmail
     *
     * @param  string  $to  Email address to send to
     * @param  string  $subject  Email subject
     * @param  string  $body  Email body content
     * @param  string  $inReplyTo  Message ID this is replying to (optional)
     * @param  string  $threadId  Gmail thread ID (optional)
     * @return string|null Draft ID if successful, null if failed
     */
    public function saveDraft(string $to, string $subject, string $body, ?string $inReplyTo = null, ?string $threadId = null): ?string
    {
        try {
            if (! $this->isAuthenticated()) {
                throw new Exception('Gmail account not authenticated');
            }

            // Create the email message
            $message = new \Google\Service\Gmail\Message;

            // Build the raw email
            $rawMessage = "To: $to\r\n";
            $rawMessage .= "Subject: $subject\r\n";

            if ($inReplyTo) {
                $rawMessage .= "In-Reply-To: $inReplyTo\r\n";
                $rawMessage .= "References: $inReplyTo\r\n";
            }

            $rawMessage .= "Content-Type: text/plain; charset=utf-8\r\n";
            $rawMessage .= "\r\n";
            $rawMessage .= $body;

            // Encode the message
            $message->setRaw(base64url_encode($rawMessage));

            if ($threadId) {
                $message->setThreadId($threadId);
            }

            // Create the draft
            $draft = new \Google\Service\Gmail\Draft;
            $draft->setMessage($message);

            // Save the draft
            $createdDraft = $this->gmail->users_drafts->create('me', $draft);

            Log::info('Gmail draft created successfully', [
                'draft_id' => $createdDraft->getId(),
                'to' => $to,
                'subject' => $subject,
            ]);

            return $createdDraft->getId();

        } catch (Exception $e) {
            Log::error('Failed to create Gmail draft: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Get the next page token from the last fetch operation
     */
    public function getNextPageToken(): ?string
    {
        return $this->nextPageToken;
    }

    /**
     * Fetch email IDs only (for batch processing)
     */
    public function fetchEmailIds(?int $limit = null, ?string $pageToken = null, bool $fetchAll = false): array
    {
        if (! $this->isAuthenticated()) {
            return ['ids' => [], 'next_page_token' => null, 'success' => false];
        }

        try {
            // Use config value if limit not specified
            $limit = $limit ?? config('mail-sync.sync_email_limit', 200);
            $params = [
                'maxResults' => min($limit, 100),
                'includeSpamTrash' => false,
            ];

            // Build base query
            $query = 'in:inbox';

            // Limit is now handled by maxResults parameter
            // No date filter - fetching most recent emails up to the limit

            // Add unread filter if not fetching all
            if (! $fetchAll) {
                $query .= ' is:unread';
            }

            $params['q'] = $query;

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            // Get list of messages
            $response = $this->gmail->users_messages->listUsersMessages('me', $params);
            $messages = $response->getMessages() ?? [];

            $messageIds = [];
            foreach ($messages as $message) {
                $messageIds[] = $message->getId();
            }

            Log::info('Fetched Gmail message IDs', [
                'count' => count($messageIds),
                'has_next_page' => ! empty($response->getNextPageToken()),
            ]);

            return [
                'ids' => $messageIds,
                'next_page_token' => $response->getNextPageToken(),
                'success' => true,
            ];
        } catch (Exception $e) {
            Log::error('Failed to fetch Gmail message IDs: '.$e->getMessage());

            return ['ids' => [], 'next_page_token' => null, 'success' => false];
        }
    }

    public function processSingleEmail(string $messageId, array $options = []): ?array
    {
        if (! $this->isAuthenticated()) {
            return null;
        }

        try {
            $message = $this->gmail->users_messages->get('me', $messageId);

            return $this->processGmailMessage($message);
        } catch (\Exception $e) {
            Log::error('Failed to process single email', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

// Helper functions for base64url encoding/decoding
if (! function_exists('base64url_decode')) {
    function base64url_decode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

if (! function_exists('base64url_encode')) {
    function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
