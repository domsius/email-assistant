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
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Kiota\Authentication\PhpLeagueAccessTokenProvider;
use Microsoft\Kiota\Authentication\PhpLeagueAuthenticationProvider;

class OutlookService implements EmailProviderInterface
{
    private EmailAccount $emailAccount;

    private ?GraphServiceClient $graphClient = null;

    private GenericProvider $oauthProvider;
    
    private string $clientId;
    
    private string $clientSecret;

    public function __construct(EmailAccount $emailAccount)
    {
        $this->emailAccount = $emailAccount;
        $this->clientId = config('services.microsoft.client_id');
        $this->clientSecret = config('services.microsoft.client_secret');
        $this->initializeOAuthProvider();

        if ($this->emailAccount->access_token) {
            $this->initializeGraphClient();
        }
    }

    private function initializeOAuthProvider(): void
    {
        $this->oauthProvider = new GenericProvider([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
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
            Log::info('Starting Graph client initialization', [
                'has_token' => !empty($this->emailAccount->access_token),
                'token_expires_at' => $this->emailAccount->token_expires_at?->setTimezone('Europe/Vilnius')->toDateTimeString(),
                'now' => now()->setTimezone('Europe/Vilnius')->toDateTimeString(),
                'is_expired' => $this->emailAccount->token_expires_at?->isPast(),
            ]);

            // Skip token refresh check during initial setup after OAuth callback
            // The token we just received is valid for at least an hour
            if (!$this->graphClient && $this->emailAccount->token_expires_at) {
                // Check if token is actually expired (with a 5-minute buffer for clock skew)
                $expiryWithBuffer = $this->emailAccount->token_expires_at->copy()->subMinutes(5);
                if ($expiryWithBuffer->isPast()) {
                    Log::info('Token expired or expiring soon, attempting refresh', [
                        'expires_at' => $this->emailAccount->token_expires_at->toDateTimeString(),
                        'buffer_time' => $expiryWithBuffer->toDateTimeString(),
                        'now' => now()->toDateTimeString(),
                    ]);
                    $this->refreshToken();
                    return; // Return after refresh to avoid recursion
                }
            }

            // Ensure we have an access token
            if (!$this->emailAccount->access_token) {
                Log::error('Cannot initialize Graph client without access token');
                return;
            }

            Log::info('Creating token provider');

            // Create a custom token provider that returns our stored token
            $tokenProvider = new class($this->emailAccount->access_token) implements \Microsoft\Kiota\Authentication\AccessTokenProvider {
                private string $accessToken;
                
                public function __construct(string $accessToken) {
                    $this->accessToken = $accessToken;
                }
                
                public function getAuthorizationTokenAsync(string $url, array $additionalAuthenticationContext = []): \GuzzleHttp\Promise\PromiseInterface {
                    return \GuzzleHttp\Promise\Create::promiseFor($this->accessToken);
                }
                
                public function getAllowedHostsValidator(): \Microsoft\Kiota\Authentication\AllowedHostsValidator {
                    return new \Microsoft\Kiota\Authentication\AllowedHostsValidator(['graph.microsoft.com']);
                }
            };
            
            Log::info('Creating authentication provider');
            
            // Create authentication provider
            $authProvider = new \Microsoft\Kiota\Authentication\BaseBearerTokenAuthenticationProvider($tokenProvider);
            
            Log::info('Creating request adapter');
            
            // Create request adapter
            $requestAdapter = new \Microsoft\Graph\Core\GraphRequestAdapter($authProvider);
            
            Log::info('Creating Graph client');
            
            // Create the Graph client
            $this->graphClient = new GraphServiceClient($requestAdapter);
            
            Log::info('Graph client initialized successfully');
            
        } catch (Exception $e) {
            Log::error('Failed to initialize Microsoft Graph client', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw here, let the calling code handle null graphClient
            $this->graphClient = null;
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
            Log::info('Outlook handleCallback started', [
                'email_account_id' => $this->emailAccount->id,
                'redirect_uri' => $redirectUri,
                'code_length' => strlen($code),
                'client_id' => substr($this->clientId, 0, 10) . '...',
            ]);
            
            // Exchange authorization code for access token
            $accessToken = $this->oauthProvider->getAccessToken('authorization_code', [
                'code' => $code,
            ]);

            Log::info('Access token received', [
                'has_token' => !empty($accessToken->getToken()),
                'has_refresh' => !empty($accessToken->getRefreshToken()),
                'expires' => $accessToken->getExpires(),
            ]);

            // Store tokens - ensure expiry is properly set with timezone
            $expiresAt = null;
            if ($accessToken->getExpires()) {
                // The expires timestamp is in seconds since Unix epoch
                // Convert to Europe/Vilnius timezone
                $expiresAt = Carbon::createFromTimestamp($accessToken->getExpires(), 'UTC')
                    ->setTimezone('Europe/Vilnius');
                
                Log::info('Token expiry calculated', [
                    'expires_timestamp' => $accessToken->getExpires(),
                    'expires_at_utc' => Carbon::createFromTimestamp($accessToken->getExpires(), 'UTC')->toDateTimeString(),
                    'expires_at_vilnius' => $expiresAt->toDateTimeString(),
                    'now_vilnius' => now()->setTimezone('Europe/Vilnius')->toDateTimeString(),
                    'is_future' => $expiresAt->isFuture(),
                ]);
            }
            
            $this->emailAccount->update([
                'access_token' => $accessToken->getToken(),
                'refresh_token' => $accessToken->getRefreshToken(),
                'token_expires_at' => $expiresAt,
                'is_active' => true,
                'last_sync_at' => now(),
            ]);
            
            // Reload the model to ensure we have the latest data
            $this->emailAccount->refresh();

            // Initialize Graph client with new token
            $this->initializeGraphClient();
            
            // Get and update the actual email address right after authentication
            if ($this->graphClient) {
                try {
                    $user = $this->graphClient->me()->get()->wait();
                    $actualEmail = $user->getMail() ?? $user->getUserPrincipalName();
                    
                    if ($actualEmail && str_contains($this->emailAccount->email_address, 'pending_')) {
                        $this->emailAccount->update([
                            'email_address' => $actualEmail,
                            'sender_name' => $user->getDisplayName(),
                        ]);
                        Log::info('Updated Outlook email address', [
                            'old' => $this->emailAccount->email_address,
                            'new' => $actualEmail,
                        ]);
                    }
                } catch (Exception $e) {
                    Log::warning('Could not retrieve Outlook user info after auth: ' . $e->getMessage());
                }
            } else {
                Log::warning('Graph client not initialized, skipping email address update');
            }

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

            $expiresAt = null;
            if ($newAccessToken->getExpires()) {
                $expiresAt = Carbon::createFromTimestamp($newAccessToken->getExpires(), 'UTC')
                    ->setTimezone('Europe/Vilnius');
            }
            
            $this->emailAccount->update([
                'access_token' => $newAccessToken->getToken(),
                'refresh_token' => $newAccessToken->getRefreshToken() ?: $this->emailAccount->refresh_token,
                'token_expires_at' => $expiresAt,
            ]);

            // Reset graph client to force reinitialization
            $this->graphClient = null;

            return true;
        } catch (Exception $e) {
            Log::error('Outlook token refresh failed: '.$e->getMessage());

            return false;
        }
    }

    public function fetchEmails(?int $limit = null, ?string $pageToken = null, bool $fetchAll = false, ?string $folder = null): array
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
                'toRecipients',
                'ccRecipients',
                'bccRecipients',
                'hasAttachments',
                'importance',
                'categories',
                'flag',
            ];

            // Handle pagination if pageToken is provided
            if ($pageToken) {
                $requestConfig->queryParameters->skip = (int) $pageToken;
            }

            // Fetch messages based on folder
            if ($folder) {
                $messages = $this->fetchFromFolder($folder, $requestConfig);
            } else {
                // Default to inbox
                $messages = $this->graphClient->me()->mailFolders()->byMailFolderId('inbox')->messages()->get($requestConfig)->wait();
            }

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
                'folder' => $folder,
            ]);

            return $emails;
        } catch (Exception $e) {
            Log::error('Outlook fetch emails failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Fetch messages from a specific folder
     */
    private function fetchFromFolder(string $folder, $requestConfig)
    {
        // Map common folder names to Outlook well-known folder names
        $folderMap = [
            'inbox' => 'inbox',
            'sent' => 'sentitems',
            'drafts' => 'drafts',
            'trash' => 'deleteditems',
            'junk' => 'junkemail',
            'spam' => 'junkemail',
            'archive' => 'archive',
            'starred' => 'flagged',
            'important' => 'flagged',
        ];

        $outlookFolder = $folderMap[strtolower($folder)] ?? $folder;

        try {
            // Try to fetch from well-known folder
            return $this->graphClient->me()->mailFolders()->byMailFolderId($outlookFolder)->messages()->get($requestConfig)->wait();
        } catch (Exception $e) {
            // If well-known folder fails, try to find folder by display name
            $folders = $this->graphClient->me()->mailFolders()->get()->wait();
            
            if ($folders && $folders->getValue()) {
                foreach ($folders->getValue() as $mailFolder) {
                    if (strcasecmp($mailFolder->getDisplayName(), $folder) === 0) {
                        return $this->graphClient->me()->mailFolders()->byMailFolderId($mailFolder->getId())->messages()->get($requestConfig)->wait();
                    }
                }
            }
            
            // Default to inbox if folder not found
            Log::warning("Outlook folder not found: {$folder}, defaulting to inbox");
            return $this->graphClient->me()->mailFolders()->byMailFolderId('inbox')->messages()->get($requestConfig)->wait();
        }
    }

    /**
     * Get list of available folders
     */
    public function getFolders(): array
    {
        try {
            if (! $this->isAuthenticated() || ! $this->graphClient) {
                return [];
            }

            $folders = $this->graphClient->me()->mailFolders()->get()->wait();
            $folderList = [];

            if ($folders && $folders->getValue()) {
                foreach ($folders->getValue() as $folder) {
                    $folderList[] = [
                        'id' => $folder->getId(),
                        'name' => $folder->getDisplayName(),
                        'total_count' => $folder->getTotalItemCount(),
                        'unread_count' => $folder->getUnreadItemCount(),
                    ];
                }
            }

            return $folderList;
        } catch (Exception $e) {
            Log::error('Failed to fetch Outlook folders: '.$e->getMessage());
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

            // Process recipients
            $toRecipients = [];
            $ccRecipients = [];
            $bccRecipients = [];
            
            if ($message->getToRecipients()) {
                foreach ($message->getToRecipients() as $recipient) {
                    if ($recipient->getEmailAddress()) {
                        $toRecipients[] = $recipient->getEmailAddress()->getAddress();
                    }
                }
            }
            
            if ($message->getCcRecipients()) {
                foreach ($message->getCcRecipients() as $recipient) {
                    if ($recipient->getEmailAddress()) {
                        $ccRecipients[] = $recipient->getEmailAddress()->getAddress();
                    }
                }
            }
            
            if ($message->getBccRecipients()) {
                foreach ($message->getBccRecipients() as $recipient) {
                    if ($recipient->getEmailAddress()) {
                        $bccRecipients[] = $recipient->getEmailAddress()->getAddress();
                    }
                }
            }

            // Get categories (labels)
            $categories = $message->getCategories() ?? [];
            
            // Get importance
            $importance = $message->getImportance() ? $message->getImportance()->value : 'normal';
            
            // Check if flagged
            $isFlagged = $message->getFlag() && $message->getFlag()->getFlagStatus() && 
                        $message->getFlag()->getFlagStatus()->value === 'flagged';

            return [
                'message_id' => $message->getId(),
                'thread_id' => $message->getConversationId(),
                'subject' => $message->getSubject() ?? 'No Subject',
                'sender_email' => $senderEmail,
                'sender_name' => $senderName ?: null,
                'to_recipients' => implode(',', $toRecipients),
                'cc_recipients' => implode(',', $ccRecipients),
                'bcc_recipients' => implode(',', $bccRecipients),
                'body_content' => $bodyContent,
                'body_html' => $bodyHtml,
                'received_at' => $message->getReceivedDateTime() ? Carbon::parse($message->getReceivedDateTime()) : now(),
                'is_read' => $message->getIsRead() ?? false,
                'is_starred' => $isFlagged,
                'is_important' => $importance === 'high',
                'has_attachments' => $message->getHasAttachments() ?? false,
                'labels' => $categories,
                'provider_data' => [
                    'outlook_id' => $message->getId(),
                    'conversation_id' => $message->getConversationId(),
                    'importance' => $importance,
                    'categories' => $categories,
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
