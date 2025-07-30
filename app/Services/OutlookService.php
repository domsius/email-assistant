<?php

namespace App\Services;

use App\Models\EmailAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\GenericProvider;
use Microsoft\Graph\Generated\Models\Message;
use Microsoft\Graph\Generated\Users\Item\MailFolders\Item\Messages\MessagesRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\GraphServiceClient;

class OutlookService implements EmailProviderInterface
{
    private EmailAccount $emailAccount;

    private ?GraphServiceClient $graphClient = null;

    private GenericProvider $oauthProvider;

    public function __construct(EmailAccount $emailAccount)
    {
        $this->emailAccount = $emailAccount;
        $this->initializeOAuthProvider();

        if ($this->emailAccount->access_token) {
            $this->initializeGraphClient();
        }
    }

    private function initializeOAuthProvider(): void
    {
        $this->oauthProvider = new GenericProvider([
            'clientId' => config('services.microsoft.client_id'),
            'clientSecret' => config('services.microsoft.client_secret'),
            'redirectUri' => config('services.microsoft.redirect'),
            'urlAuthorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
            'urlAccessToken' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'urlResourceOwnerDetails' => '',
            'scopes' => 'offline_access openid profile email Mail.Read Mail.ReadWrite Mail.Send',
        ]);
    }

    private function initializeGraphClient(): void
    {
        try {
            // Check if token is expired and refresh if needed
            if ($this->emailAccount->token_expires_at && $this->emailAccount->token_expires_at->isPast()) {
                $this->refreshToken();
            }

            // Create a simple token credential provider
            $tokenRequestContext = new class($this->emailAccount->access_token)
            {
                private string $token;

                public function __construct(string $token)
                {
                    $this->token = $token;
                }

                public function getToken(): string
                {
                    return $this->token;
                }
            };

            // Create the GraphServiceClient with the access token
            $this->graphClient = GraphServiceClient::createWithAuthenticationProvider(
                new class($this->emailAccount->access_token) implements \Microsoft\Kiota\Authentication\AccessTokenProvider
                {
                    private string $token;

                    public function __construct(string $token)
                    {
                        $this->token = $token;
                    }

                    public function getAuthorizationTokenAsync(string $uri, array $additionalAuthenticationContext = []): \GuzzleHttp\Promise\PromiseInterface
                    {
                        return \GuzzleHttp\Promise\Create::promiseFor($this->token);
                    }

                    public function getAllowedHostsValidator(): \Microsoft\Kiota\Authentication\AllowedHostsValidator
                    {
                        return new \Microsoft\Kiota\Authentication\AllowedHostsValidator;
                    }
                }
            );
        } catch (Exception $e) {
            Log::error('Failed to initialize Microsoft Graph client: '.$e->getMessage());
            throw $e;
        }
    }

    public function getAuthUrl(string $redirectUri, ?string $state = null): string
    {
        $options = [
            'scope' => 'offline_access openid profile email Mail.Read Mail.ReadWrite Mail.Send',
        ];

        if ($state) {
            $options['state'] = $state;
        }

        return $this->oauthProvider->getAuthorizationUrl($options);
    }

    public function handleCallback(string $code, string $redirectUri): bool
    {
        try {
            // Exchange authorization code for access token
            $accessToken = $this->oauthProvider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            // Store tokens
            $this->emailAccount->update([
                'access_token' => $accessToken->getToken(),
                'refresh_token' => $accessToken->getRefreshToken(),
                'token_expires_at' => $accessToken->getExpires() ? Carbon::createFromTimestamp($accessToken->getExpires()) : null,
                'is_active' => true,
                'last_sync_at' => now(),
            ]);

            // Initialize Graph client with new token
            $this->initializeGraphClient();

            return true;
        } catch (Exception $e) {
            Log::error('Outlook OAuth callback failed: '.$e->getMessage());

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

            $newAccessToken = $this->oauthProvider->getAccessToken('refresh_token', [
                'refresh_token' => $this->emailAccount->refresh_token,
            ]);

            $this->emailAccount->update([
                'access_token' => $newAccessToken->getToken(),
                'refresh_token' => $newAccessToken->getRefreshToken() ?: $this->emailAccount->refresh_token,
                'token_expires_at' => $newAccessToken->getExpires() ? Carbon::createFromTimestamp($newAccessToken->getExpires()) : null,
            ]);

            // Reinitialize Graph client with new token
            $this->initializeGraphClient();

            return true;
        } catch (Exception $e) {
            Log::error('Outlook token refresh failed: '.$e->getMessage());

            return false;
        }
    }

    public function fetchEmails(?int $limit = null, ?string $pageToken = null, bool $fetchAll = false): array
    {
        try {
            if (! $this->isAuthenticated() || ! $this->graphClient) {
                throw new Exception('Outlook account not authenticated');
            }

            // Use config value if limit not specified
            $limit = $limit ?? config('mail-sync.sync_email_limit', 200);

            $emails = [];

            // Create query parameters
            $requestConfig = new MessagesRequestBuilderGetRequestConfiguration;
            $requestConfig->queryParameters = new MessagesRequestBuilderGetQueryParameters;

            // Build filter query
            $filters = [];

            // No date filter - fetching most recent emails up to the limit
            // Emails will be ordered by receivedDateTime desc

            // Add unread filter if not fetching all
            if (! $fetchAll) {
                $filters[] = 'isRead eq false';
            }

            // Combine filters with AND
            if (! empty($filters)) {
                $requestConfig->queryParameters->filter = implode(' and ', $filters);
            }

            $requestConfig->queryParameters->orderby = ['receivedDateTime desc'];
            $requestConfig->queryParameters->top = $limit;
            $requestConfig->queryParameters->select = [
                'id',
                'conversationId',
                'subject',
                'from',
                'receivedDateTime',
                'body',
                'isRead',
            ];

            // Handle pagination if pageToken is provided
            if ($pageToken) {
                $requestConfig->queryParameters->skip = (int) $pageToken;
            }

            // Fetch messages from inbox
            $messages = $this->graphClient->me()->messages()->get($requestConfig)->wait();

            if ($messages && $messages->getValue()) {
                foreach ($messages->getValue() as $message) {
                    $email = $this->processOutlookMessage($message);
                    if ($email) {
                        $emails[] = $email;
                    }
                }
            }

            Log::info('Outlook fetch completed', [
                'total_emails' => count($emails),
                'requested_limit' => $limit,
                'fetch_all' => $fetchAll,
                'page_token' => $pageToken,
            ]);

            return $emails;
        } catch (Exception $e) {
            Log::error('Outlook fetch emails failed: '.$e->getMessage());

            return [];
        }
    }

    private function processOutlookMessage(Message $message): ?array
    {
        try {
            $from = $message->getFrom();
            $senderEmail = $from ? $from->getEmailAddress()->getAddress() : '';
            $senderName = $from ? $from->getEmailAddress()->getName() : '';

            if (! $senderEmail) {
                return null; // Skip emails without valid sender
            }

            $body = $message->getBody();
            $bodyContent = $body ? $body->getContent() : '';
            $bodyHtml = '';

            // Handle different content types
            if ($body) {
                if ($body->getContentType()->value === 'html') {
                    $bodyHtml = $bodyContent;
                    $bodyContent = strip_tags($bodyContent);
                } elseif ($body->getContentType()->value === 'text') {
                    // Plain text message
                    $bodyContent = $bodyContent;
                }
            }

            return [
                'message_id' => $message->getId(),
                'thread_id' => $message->getConversationId(),
                'subject' => $message->getSubject() ?? 'No Subject',
                'sender_email' => $senderEmail,
                'sender_name' => $senderName ?: null,
                'body_content' => $bodyContent,
                'body_html' => $bodyHtml,
                'received_at' => $message->getReceivedDateTime() ? Carbon::parse($message->getReceivedDateTime()) : now(),
                'provider_data' => [
                    'outlook_id' => $message->getId(),
                    'conversation_id' => $message->getConversationId(),
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error processing Outlook message: '.$e->getMessage());

            return null;
        }
    }

    public function sendEmail(array $emailData): bool
    {
        try {
            if (! $this->isAuthenticated() || ! $this->graphClient) {
                throw new Exception('Outlook account not authenticated');
            }

            $message = new Message;
            $message->setSubject($emailData['subject']);

            // Set body
            $body = new \Microsoft\Graph\Generated\Models\ItemBody;
            $body->setContentType(new \Microsoft\Graph\Generated\Models\BodyType('text'));
            $body->setContent($emailData['body']);
            $message->setBody($body);

            // Set threading headers for replies
            if (! empty($emailData['in_reply_to'])) {
                // Note: Microsoft Graph API handles threading differently than traditional email headers
                // The conversationId should be set if this is part of an existing conversation
                // For now, we'll set the internetMessageHeaders
                $headers = [];

                $inReplyToHeader = new \Microsoft\Graph\Generated\Models\InternetMessageHeader;
                $inReplyToHeader->setName('In-Reply-To');
                $inReplyToHeader->setValue('<'.$emailData['in_reply_to'].'>');
                $headers[] = $inReplyToHeader;

                if (! empty($emailData['references'])) {
                    $referencesHeader = new \Microsoft\Graph\Generated\Models\InternetMessageHeader;
                    $referencesHeader->setName('References');
                    $referencesHeader->setValue('<'.$emailData['references'].'>');
                    $headers[] = $referencesHeader;
                }

                $message->setInternetMessageHeaders($headers);
            }

            // Set TO recipients
            $toRecipients = [];
            $toAddresses = explode(',', $emailData['to']);
            foreach ($toAddresses as $address) {
                $recipient = new \Microsoft\Graph\Generated\Models\Recipient;
                $emailAddress = new \Microsoft\Graph\Generated\Models\EmailAddress;
                $emailAddress->setAddress(trim($address));
                $recipient->setEmailAddress($emailAddress);
                $toRecipients[] = $recipient;
            }
            $message->setToRecipients($toRecipients);

            // Set CC recipients if present
            if (! empty($emailData['cc'])) {
                $ccRecipients = [];
                $ccAddresses = explode(',', $emailData['cc']);
                foreach ($ccAddresses as $address) {
                    $recipient = new \Microsoft\Graph\Generated\Models\Recipient;
                    $emailAddress = new \Microsoft\Graph\Generated\Models\EmailAddress;
                    $emailAddress->setAddress(trim($address));
                    $recipient->setEmailAddress($emailAddress);
                    $ccRecipients[] = $recipient;
                }
                $message->setCcRecipients($ccRecipients);
            }

            // Set BCC recipients if present
            if (! empty($emailData['bcc'])) {
                $bccRecipients = [];
                $bccAddresses = explode(',', $emailData['bcc']);
                foreach ($bccAddresses as $address) {
                    $recipient = new \Microsoft\Graph\Generated\Models\Recipient;
                    $emailAddress = new \Microsoft\Graph\Generated\Models\EmailAddress;
                    $emailAddress->setAddress(trim($address));
                    $recipient->setEmailAddress($emailAddress);
                    $bccRecipients[] = $recipient;
                }
                $message->setBccRecipients($bccRecipients);
            }

            // Send the email
            $this->graphClient->me()->sendMail()->post(
                new \Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody(
                    message: $message,
                    saveToSentItems: true
                )
            )->wait();

            Log::info('Outlook email sent successfully', [
                'to' => $emailData['to'],
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Outlook send email failed: '.$e->getMessage(), [
                'to' => $emailData['to'] ?? 'unknown',
                'subject' => $emailData['subject'] ?? 'unknown',
            ]);

            return false;
        }
    }

    public function getAccountInfo(): array
    {
        try {
            if (! $this->isAuthenticated() || ! $this->graphClient) {
                return [];
            }

            $user = $this->graphClient->me()->get()->wait();

            return [
                'email' => $user->getMail() ?? $user->getUserPrincipalName(),
                'name' => $user->getDisplayName(),
                'id' => $user->getId(),
            ];
        } catch (Exception $e) {
            Log::error('Outlook get account info failed: '.$e->getMessage());

            return [];
        }
    }

    public function saveDraft(string $to, string $subject, string $body, ?string $inReplyTo = null, ?string $threadId = null): ?string
    {
        try {
            if (! $this->isAuthenticated() || ! $this->graphClient) {
                throw new Exception('Outlook account not authenticated');
            }

            $message = new Message;
            $message->setSubject($subject);

            // Set body
            $messageBody = new \Microsoft\Graph\Generated\Models\ItemBody;
            $messageBody->setContentType(new \Microsoft\Graph\Generated\Models\BodyType('text'));
            $messageBody->setContent($body);
            $message->setBody($messageBody);

            // Set recipient
            $recipient = new \Microsoft\Graph\Generated\Models\Recipient;
            $emailAddress = new \Microsoft\Graph\Generated\Models\EmailAddress;
            $emailAddress->setAddress($to);
            $recipient->setEmailAddress($emailAddress);
            $message->setToRecipients([$recipient]);

            // Set conversation/thread ID if replying
            if ($threadId) {
                $message->setConversationId($threadId);
            }

            // Create draft
            $draft = $this->graphClient->me()->messages()->post($message)->wait();

            Log::info('Outlook draft created successfully', [
                'draft_id' => $draft->getId(),
                'to' => $to,
                'subject' => $subject,
            ]);

            return $draft->getId();

        } catch (Exception $e) {
            Log::error('Failed to create Outlook draft: '.$e->getMessage());

            return null;
        }
    }

    public function processSingleEmail(string $messageId, array $options = []): ?array
    {
        if (! $this->isAuthenticated()) {
            return null;
        }

        try {
            $message = $this->graphClient->me()->messages()->byMessageId($messageId)->get()->wait();

            return [
                'message_id' => $message->getId(),
                'thread_id' => $message->getConversationId(),
                'subject' => $message->getSubject() ?? 'No Subject',
                'sender_email' => $message->getFrom()->getEmailAddress()->getAddress(),
                'sender_name' => $message->getFrom()->getEmailAddress()->getName() ?? '',
                'date' => $message->getReceivedDateTime()->format('c'),
                'body_html' => $message->getBody()->getContent(),
                'labels' => $message->getCategories() ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process single Outlook email', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
