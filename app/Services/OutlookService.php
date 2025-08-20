<?php

namespace App\Services;

use App\Models\EmailAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Client\Provider\GenericProvider;
use Microsoft\Graph\Generated\Models\Message;
use Microsoft\Graph\GraphServiceClient;
use GuzzleHttp\Client as GuzzleClient;

class OutlookService implements EmailProviderInterface
{
    private EmailAccount $emailAccount;

    private ?GraphServiceClient $graphClient = null;
    
    private ?GuzzleClient $httpClient = null;

    private GenericProvider $oauthProvider;
    
    private string $clientId;
    
    private string $clientSecret;
    
    private ?string $nextPageToken = null;

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
            'scopes' => 'offline_access openid profile email User.Read Mail.Read Mail.ReadWrite Mail.Send',
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
            if (!$this->httpClient && $this->emailAccount->token_expires_at) {
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

            Log::info('Creating HTTP client for Graph API');

            // Create a simple Guzzle HTTP client for direct API calls
            $this->httpClient = new GuzzleClient([
                'base_uri' => 'https://graph.microsoft.com/v1.0/',
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->emailAccount->access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);
            
            // We're not using graphClient anymore, just httpClient
            // Set graphClient to a dummy object to satisfy the interface checks
            // This is a workaround since we're using httpClient directly now
            
            Log::info('Graph HTTP client initialized successfully');
            
        } catch (Exception $e) {
            Log::error('Failed to initialize Microsoft Graph client', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Don't throw here, let the calling code handle null graphClient
            $this->httpClient = null;
            $this->graphClient = null;
        }
    }

    public function getAuthUrl(string $redirectUri, ?string $state = null): string
    {
        $options = [
            'scope' => 'offline_access openid profile email User.Read Mail.Read Mail.ReadWrite Mail.Send',
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
            if ($this->httpClient) {
                try {
                    // Make a direct HTTP call to get user info
                    $response = $this->httpClient->get('me');
                    $user = json_decode($response->getBody()->getContents(), true);
                    
                    $actualEmail = $user['mail'] ?? $user['userPrincipalName'] ?? null;
                    $displayName = $user['displayName'] ?? null;
                    
                    if ($actualEmail && str_contains($this->emailAccount->email_address, 'pending_')) {
                        $this->emailAccount->update([
                            'email_address' => $actualEmail,
                            'sender_name' => $displayName,
                        ]);
                        Log::info('Updated Outlook email address', [
                            'old' => $this->emailAccount->email_address,
                            'new' => $actualEmail,
                            'display_name' => $displayName,
                        ]);
                    }
                } catch (Exception $e) {
                    Log::warning('Could not retrieve Outlook user info after auth: ' . $e->getMessage());
                }
                
                // Set up webhook subscription for real-time notifications
                try {
                    $webhookService = new \App\Services\OutlookWebhookService();
                    $subscriptionId = $webhookService->createSubscription($this->emailAccount);
                    if ($subscriptionId) {
                        Log::info('Outlook webhook subscription created after auth', [
                            'account_id' => $this->emailAccount->id,
                            'subscription_id' => $subscriptionId,
                        ]);
                    } else {
                        Log::warning('Failed to create Outlook webhook subscription after auth', [
                            'account_id' => $this->emailAccount->id,
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Error setting up Outlook webhook after auth: ' . $e->getMessage(), [
                        'account_id' => $this->emailAccount->id,
                    ]);
                }
            } else {
                Log::warning('HTTP client not initialized, skipping email address update');
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
    
    private function isGraphClientInitialized(): bool
    {
        return $this->httpClient !== null;
    }

    public function refreshToken(): bool
    {
        try {
            if (! $this->emailAccount->refresh_token) {
                Log::warning('No refresh token available for account', [
                    'account_id' => $this->emailAccount->id,
                ]);
                return false;
            }

            Log::info('Attempting to refresh Outlook token', [
                'account_id' => $this->emailAccount->id,
                'has_refresh_token' => !empty($this->emailAccount->refresh_token),
            ]);

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

            // Reset http client to force reinitialization
            $this->httpClient = null;
            
            Log::info('Outlook token refreshed successfully', [
                'account_id' => $this->emailAccount->id,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Outlook token refresh failed: '.$e->getMessage(), [
                'account_id' => $this->emailAccount->id,
                'error_class' => get_class($e),
            ]);

            return false;
        }
    }

    public function fetchEmails(?int $limit = null, ?string $pageToken = null, bool $fetchAll = false, ?string $folder = null): array
    {
        try {
            if (! $this->isAuthenticated() || ! $this->isGraphClientInitialized()) {
                // Try to initialize if not already done
                if (!$this->isGraphClientInitialized()) {
                    $this->initializeGraphClient();
                }
                
                if (!$this->isGraphClientInitialized()) {
                    throw new Exception('Outlook account not authenticated or Graph client not initialized');
                }
            }

            // Use config value if limit not specified
            $limit = $limit ?? config('mail-sync.sync_email_limit', 200);
            
            $emails = [];
            $batchSize = 50; // Microsoft Graph API typical page size
            $totalFetched = 0;
            $currentPageToken = $pageToken;

            while ($totalFetched < $limit) {
                $requestLimit = min($batchSize, $limit - $totalFetched);
                
                // Build query parameters for the API call
                $queryParams = [
                    '$top' => $requestLimit,
                    '$orderby' => 'receivedDateTime desc',
                    '$select' => 'id,conversationId,subject,from,receivedDateTime,body,isRead,toRecipients,ccRecipients,bccRecipients,hasAttachments,importance,categories,flag',
                ];

                // Add filter for unread emails if not fetching all
                if (!$fetchAll) {
                    $queryParams['$filter'] = 'isRead eq false';
                }

                // Handle pagination
                // The pageToken is the skip value (number of items to skip)
                if ($currentPageToken !== null && $currentPageToken !== '') {
                    $queryParams['$skip'] = (int) $currentPageToken;
                }

                // Determine the endpoint based on folder
                $endpoint = 'me/mailFolders/inbox/messages';
                if ($folder) {
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
                    $endpoint = "me/mailFolders/{$outlookFolder}/messages";
                }

                // Make the API call
                $response = $this->httpClient->get($endpoint, [
                    'query' => $queryParams,
                ]);

                $data = json_decode($response->getBody()->getContents(), true);
                $batchEmails = [];

                if (isset($data['value']) && is_array($data['value'])) {
                    foreach ($data['value'] as $message) {
                        // Check if we've reached the limit
                        if ($totalFetched >= $limit) {
                            break 2; // Break out of both foreach and while loops
                        }
                        
                        $email = $this->processOutlookMessageArray($message);
                        if ($email) {
                            $emails[] = $email;
                            $batchEmails[] = $email;
                            $totalFetched++;
                        }
                    }
                }
                
                // Handle Microsoft Graph API pagination
                if (isset($data['@odata.nextLink'])) {
                    // Extract the skip value from the next link
                    // The nextLink looks like: https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages?$skip=10&...
                    $nextLink = $data['@odata.nextLink'];
                    parse_str(parse_url($nextLink, PHP_URL_QUERY), $nextQueryParams);
                    $currentPageToken = isset($nextQueryParams['$skip']) ? (string)$nextQueryParams['$skip'] : null;
                    $this->nextPageToken = $currentPageToken;
                } else {
                    // No more emails available
                    $this->nextPageToken = null;
                    break;
                }
                
                // If we got no emails in this batch, stop to avoid infinite loop
                if (empty($batchEmails)) {
                    break;
                }
            }

            Log::info('Outlook fetch completed', [
                'total_emails' => count($emails),
                'requested_limit' => $limit,
                'fetch_all' => $fetchAll,
                'page_token' => $pageToken,
                'next_page_token' => $this->nextPageToken,
                'folder' => $folder,
            ]);

            return $emails;
        } catch (Exception $e) {
            Log::error('Outlook fetch emails failed: '.$e->getMessage());
            return [];
        }
    }

    /**
     * Process Outlook message from API JSON response
     */
    private function processOutlookMessageArray(array $message): ?array
    {
        try {
            $senderEmail = $message['from']['emailAddress']['address'] ?? '';
            $senderName = $message['from']['emailAddress']['name'] ?? '';

            if (!$senderEmail) {
                return null; // Skip emails without valid sender
            }

            $bodyContent = $message['body']['content'] ?? '';
            $bodyHtml = '';

            // Handle different content types
            if (isset($message['body']['contentType'])) {
                if ($message['body']['contentType'] === 'html') {
                    $bodyHtml = $bodyContent;
                    $bodyContent = strip_tags($bodyContent);
                } elseif ($message['body']['contentType'] === 'text') {
                    // Plain text message
                    $bodyContent = $bodyContent;
                }
            }

            // Process recipients
            $toRecipients = [];
            $ccRecipients = [];
            $bccRecipients = [];
            
            if (isset($message['toRecipients']) && is_array($message['toRecipients'])) {
                foreach ($message['toRecipients'] as $recipient) {
                    if (isset($recipient['emailAddress']['address'])) {
                        $toRecipients[] = $recipient['emailAddress']['address'];
                    }
                }
            }
            
            if (isset($message['ccRecipients']) && is_array($message['ccRecipients'])) {
                foreach ($message['ccRecipients'] as $recipient) {
                    if (isset($recipient['emailAddress']['address'])) {
                        $ccRecipients[] = $recipient['emailAddress']['address'];
                    }
                }
            }
            
            if (isset($message['bccRecipients']) && is_array($message['bccRecipients'])) {
                foreach ($message['bccRecipients'] as $recipient) {
                    if (isset($recipient['emailAddress']['address'])) {
                        $bccRecipients[] = $recipient['emailAddress']['address'];
                    }
                }
            }

            // Get categories (labels)
            $categories = $message['categories'] ?? [];
            
            // Get importance
            $importance = $message['importance'] ?? 'normal';
            
            // Check if flagged
            $isFlagged = isset($message['flag']['flagStatus']) && 
                        $message['flag']['flagStatus'] === 'flagged';

            return [
                'message_id' => $message['id'],
                'thread_id' => $message['conversationId'] ?? null,
                'subject' => $message['subject'] ?? 'No Subject',
                'sender_email' => $senderEmail,
                'sender_name' => $senderName ?: null,
                'to_recipients' => implode(',', $toRecipients),
                'cc_recipients' => implode(',', $ccRecipients),
                'bcc_recipients' => implode(',', $bccRecipients),
                'body_content' => $bodyContent,
                'body_html' => $bodyHtml,
                'received_at' => isset($message['receivedDateTime']) ? Carbon::parse($message['receivedDateTime']) : now(),
                'is_read' => $message['isRead'] ?? false,
                'is_starred' => $isFlagged,
                'is_important' => $importance === 'high',
                'has_attachments' => $message['hasAttachments'] ?? false,
                'labels' => $categories,
                'provider_data' => [
                    'outlook_id' => $message['id'],
                    'conversation_id' => $message['conversationId'] ?? null,
                    'importance' => $importance,
                    'categories' => $categories,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error processing Outlook message array: '.$e->getMessage());
            return null;
        }
    }

    /**
     * Fetch messages from a specific folder (legacy method - not used with HTTP client)
     */
    private function fetchFromFolder(string $folder, $requestConfig)
    {
        // This method is no longer used with the HTTP client approach
        // Keeping it for compatibility but it won't work without GraphServiceClient
        Log::warning('fetchFromFolder called but not implemented for HTTP client');
        return null;
    }

    /**
     * Get list of available folders
     */
    public function getFolders(): array
    {
        try {
            if (! $this->isAuthenticated() || ! $this->isGraphClientInitialized()) {
                return [];
            }

            // Make HTTP request to get folders
            $response = $this->httpClient->get('me/mailFolders');
            $data = json_decode($response->getBody()->getContents(), true);
            
            $folderList = [];
            if (isset($data['value']) && is_array($data['value'])) {
                foreach ($data['value'] as $folder) {
                    $folderList[] = [
                        'id' => $folder['id'],
                        'name' => $folder['displayName'],
                        'total_count' => $folder['totalItemCount'] ?? 0,
                        'unread_count' => $folder['unreadItemCount'] ?? 0,
                    ];
                }
            }

            return $folderList;
        } catch (Exception $e) {
            Log::error('Failed to fetch Outlook folders: '.$e->getMessage());
            return [];
        }
    }

    /**
     * Process Outlook message object (legacy method for SDK)
     */
    private function processOutlookMessage(Message $message): ?array
    {
        // Legacy method kept for compatibility
        // This won't be called when using HTTP client
        Log::warning('processOutlookMessage called but using HTTP client instead');
        return null;
    }

    public function sendEmail(array $emailData): bool
    {
        try {
            if (! $this->isAuthenticated() || ! $this->isGraphClientInitialized()) {
                throw new Exception('Outlook account not authenticated');
            }

            // Build the email message data
            // Check if body contains HTML
            $contentType = preg_match('/<[^>]+>/', $emailData['body']) ? 'html' : 'text';
            
            $messageData = [
                'subject' => $emailData['subject'],
                'body' => [
                    'contentType' => $contentType,
                    'content' => $emailData['body'],
                ],
                'toRecipients' => [],
            ];

            // Add TO recipients
            $toAddresses = explode(',', $emailData['to']);
            foreach ($toAddresses as $address) {
                $messageData['toRecipients'][] = [
                    'emailAddress' => [
                        'address' => trim($address),
                    ],
                ];
            }

            // Add CC recipients if present
            if (!empty($emailData['cc'])) {
                $messageData['ccRecipients'] = [];
                $ccAddresses = explode(',', $emailData['cc']);
                foreach ($ccAddresses as $address) {
                    $messageData['ccRecipients'][] = [
                        'emailAddress' => [
                            'address' => trim($address),
                        ],
                    ];
                }
            }

            // Add BCC recipients if present
            if (!empty($emailData['bcc'])) {
                $messageData['bccRecipients'] = [];
                $bccAddresses = explode(',', $emailData['bcc']);
                foreach ($bccAddresses as $address) {
                    $messageData['bccRecipients'][] = [
                        'emailAddress' => [
                            'address' => trim($address),
                        ],
                    ];
                }
            }

            // Send the email via HTTP API
            $response = $this->httpClient->post('me/sendMail', [
                'json' => [
                    'message' => $messageData,
                    'saveToSentItems' => true,
                ],
            ]);

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
            if (! $this->isAuthenticated() || ! $this->isGraphClientInitialized()) {
                if (!$this->isGraphClientInitialized()) {
                    $this->initializeGraphClient();
                }
                
                if (!$this->isGraphClientInitialized()) {
                    return [];
                }
            }

            $response = $this->httpClient->get('me');
            $user = json_decode($response->getBody()->getContents(), true);

            return [
                'email' => $user['mail'] ?? $user['userPrincipalName'] ?? '',
                'name' => $user['displayName'] ?? '',
                'id' => $user['id'] ?? '',
            ];
        } catch (Exception $e) {
            Log::error('Outlook get account info failed: '.$e->getMessage());
            return [];
        }
    }

    public function saveDraft(string $to, string $subject, string $body, ?string $inReplyTo = null, ?string $threadId = null): ?string
    {
        try {
            if (! $this->isAuthenticated() || ! $this->isGraphClientInitialized()) {
                throw new Exception('Outlook account not authenticated');
            }

            $messageData = [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'text',
                    'content' => $body,
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $to,
                        ],
                    ],
                ],
            ];

            // Set conversation/thread ID if replying
            if ($threadId) {
                $messageData['conversationId'] = $threadId;
            }

            // Create draft via HTTP API
            $response = $this->httpClient->post('me/messages', [
                'json' => $messageData,
            ]);

            $draft = json_decode($response->getBody()->getContents(), true);

            Log::info('Outlook draft created successfully', [
                'draft_id' => $draft['id'] ?? null,
                'to' => $to,
                'subject' => $subject,
            ]);

            return $draft['id'] ?? null;

        } catch (Exception $e) {
            Log::error('Failed to create Outlook draft: '.$e->getMessage());
            return null;
        }
    }

    public function processSingleEmail(string $messageId, array $options = []): ?array
    {
        if (! $this->isAuthenticated() || ! $this->isGraphClientInitialized()) {
            return null;
        }

        try {
            $response = $this->httpClient->get("me/messages/{$messageId}");
            $message = json_decode($response->getBody()->getContents(), true);

            return [
                'message_id' => $message['id'],
                'thread_id' => $message['conversationId'] ?? null,
                'subject' => $message['subject'] ?? 'No Subject',
                'sender_email' => $message['from']['emailAddress']['address'] ?? '',
                'sender_name' => $message['from']['emailAddress']['name'] ?? '',
                'date' => $message['receivedDateTime'] ?? '',
                'body_html' => $message['body']['content'] ?? '',
                'labels' => $message['categories'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Failed to process single Outlook email', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

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
}