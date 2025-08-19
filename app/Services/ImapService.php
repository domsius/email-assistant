<?php

namespace App\Services;

use App\Models\EmailAccount;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Message;

class ImapService implements EmailProviderInterface
{
    private EmailAccount $emailAccount;
    private ?Client $client = null;
    private ClientManager $manager;

    public function __construct(EmailAccount $emailAccount)
    {
        $this->emailAccount = $emailAccount;
        $this->manager = new ClientManager();
        
        if ($this->emailAccount->imap_host) {
            $this->initializeImapClient();
        }
    }

    private function initializeImapClient(): void
    {
        try {
            $config = [
                'host' => $this->emailAccount->imap_host,
                'port' => $this->emailAccount->imap_port ?? 993,
                'encryption' => $this->emailAccount->imap_encryption ?? 'ssl',
                'validate_cert' => $this->emailAccount->imap_validate_cert ?? true,
                'username' => $this->emailAccount->email_address,
                'password' => $this->emailAccount->imap_password,
                'protocol' => 'imap',
                'authentication' => 'login',
            ];

            $this->client = $this->manager->make($config);
            
            // Test connection
            if ($this->emailAccount->is_active) {
                $this->client->connect();
            }
        } catch (Exception $e) {
            Log::error('Failed to initialize IMAP client: ' . $e->getMessage());
            $this->client = null;
        }
    }

    public function getAuthUrl(string $redirectUri, ?string $state = null): string
    {
        // IMAP doesn't use OAuth, return empty string
        return '';
    }

    public function handleCallback(string $code, string $redirectUri): bool
    {
        // IMAP doesn't use OAuth callbacks
        // Connection is established directly with username/password
        return $this->testConnection();
    }

    public function isAuthenticated(): bool
    {
        return $this->emailAccount->imap_host && 
               $this->emailAccount->imap_password && 
               $this->emailAccount->is_active;
    }

    public function refreshToken(): bool
    {
        // IMAP doesn't use tokens, just test the connection
        return $this->testConnection();
    }

    private function testConnection(): bool
    {
        try {
            if (!$this->client) {
                $this->initializeImapClient();
            }

            if ($this->client) {
                $this->client->connect();
                $this->client->disconnect();
                
                $this->emailAccount->update([
                    'is_active' => true,
                    'last_sync_at' => now(),
                ]);
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            Log::error('IMAP connection test failed: ' . $e->getMessage());
            
            $this->emailAccount->update([
                'is_active' => false,
                'sync_error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    public function fetchEmails(?int $limit = null, ?string $pageToken = null, bool $fetchAll = false, ?string $folder = null): array
    {
        try {
            if (!$this->isAuthenticated() || !$this->client) {
                throw new Exception('IMAP account not authenticated');
            }

            $this->client->connect();
            
            // Use config value if limit not specified
            $limit = $limit ?? config('mail-sync.sync_email_limit', 200);
            
            // Select folder
            $folderName = $this->mapFolder($folder ?? 'INBOX');
            $folder = $this->client->getFolder($folderName);
            
            if (!$folder) {
                throw new Exception("Folder not found: {$folderName}");
            }

            // Build query
            $query = $folder->messages();
            
            // Add filters
            if (!$fetchAll) {
                $query = $query->unseen();
            }
            
            // Apply limit
            $query = $query->limit($limit);
            
            // Handle pagination if pageToken is provided (offset)
            if ($pageToken) {
                $query = $query->setPage((int)$pageToken);
            }
            
            // Fetch messages
            $messages = $query->get();
            
            $emails = [];
            foreach ($messages as $message) {
                $email = $this->processImapMessage($message);
                if ($email) {
                    $emails[] = $email;
                }
            }
            
            $this->client->disconnect();
            
            Log::info('IMAP fetch completed', [
                'total_emails' => count($emails),
                'requested_limit' => $limit,
                'fetch_all' => $fetchAll,
                'folder' => $folderName,
            ]);
            
            return $emails;
        } catch (Exception $e) {
            Log::error('IMAP fetch emails failed: ' . $e->getMessage());
            
            if ($this->client && $this->client->isConnected()) {
                $this->client->disconnect();
            }
            
            return [];
        }
    }

    private function mapFolder(string $folder): string
    {
        // Map common folder names to IMAP folder names
        $folderMap = [
            'inbox' => 'INBOX',
            'sent' => 'Sent',
            'drafts' => 'Drafts',
            'trash' => 'Trash',
            'junk' => 'Junk',
            'spam' => 'Spam',
            'archive' => 'Archive',
            'starred' => 'Starred',
            'important' => 'Important',
            'all' => 'All Mail',
        ];

        return $folderMap[strtolower($folder)] ?? $folder;
    }

    private function processImapMessage(Message $message): ?array
    {
        try {
            $from = $message->getFrom();
            $senderEmail = $from[0]->mail ?? '';
            $senderName = $from[0]->personal ?? '';
            
            if (!$senderEmail) {
                return null; // Skip emails without valid sender
            }

            // Get message body
            $bodyHtml = $message->getHTMLBody();
            $bodyPlain = $message->getTextBody();
            $bodyContent = $bodyPlain ?: strip_tags($bodyHtml);

            // Get recipients
            $toRecipients = [];
            foreach ($message->getTo() as $recipient) {
                $toRecipients[] = $recipient->mail;
            }
            
            $ccRecipients = [];
            foreach ($message->getCc() as $recipient) {
                $ccRecipients[] = $recipient->mail;
            }
            
            $bccRecipients = [];
            foreach ($message->getBcc() as $recipient) {
                $bccRecipients[] = $recipient->mail;
            }

            // Get flags and labels
            $flags = $message->getFlags();
            $isRead = in_array('Seen', $flags);
            $isStarred = in_array('Flagged', $flags);
            $isDraft = in_array('Draft', $flags);
            $isAnswered = in_array('Answered', $flags);
            
            // Get message headers
            $messageId = $message->getMessageId();
            $inReplyTo = $message->getInReplyTo();
            $references = $message->getReferences();

            return [
                'message_id' => $messageId,
                'thread_id' => $references ?: $messageId, // Use references as thread ID or fall back to message ID
                'subject' => $message->getSubject() ?? 'No Subject',
                'sender_email' => $senderEmail,
                'sender_name' => $senderName ?: null,
                'to_recipients' => implode(',', $toRecipients),
                'cc_recipients' => implode(',', $ccRecipients),
                'bcc_recipients' => implode(',', $bccRecipients),
                'body_content' => $bodyContent,
                'body_html' => $bodyHtml,
                'body_plain' => $bodyPlain,
                'received_at' => Carbon::parse($message->getDate()),
                'is_read' => $isRead,
                'is_starred' => $isStarred,
                'is_draft' => $isDraft,
                'is_answered' => $isAnswered,
                'has_attachments' => $message->hasAttachments(),
                'labels' => $flags,
                'provider_data' => [
                    'imap_uid' => $message->getUid(),
                    'imap_message_id' => $messageId,
                    'in_reply_to' => $inReplyTo,
                    'references' => $references,
                    'flags' => $flags,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Error processing IMAP message: ' . $e->getMessage());
            return null;
        }
    }

    public function sendEmail(array $emailData): bool
    {
        try {
            if (!$this->emailAccount->smtp_host) {
                throw new Exception('SMTP configuration not found');
            }

            $transport = (new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                $this->emailAccount->smtp_host,
                $this->emailAccount->smtp_port ?? 587,
                $this->emailAccount->smtp_encryption === 'tls'
            ))
            ->setUsername($this->emailAccount->email_address)
            ->setPassword($this->emailAccount->smtp_password ?? $this->emailAccount->imap_password);

            $mailer = new \Symfony\Component\Mailer\Mailer($transport);

            $email = (new \Symfony\Component\Mime\Email())
                ->from($this->emailAccount->email_address)
                ->to(...explode(',', $emailData['to']))
                ->subject($emailData['subject'])
                ->html($emailData['body']);

            if (!empty($emailData['cc'])) {
                $email->cc(...explode(',', $emailData['cc']));
            }

            if (!empty($emailData['bcc'])) {
                $email->bcc(...explode(',', $emailData['bcc']));
            }

            // Set threading headers for replies
            if (!empty($emailData['in_reply_to'])) {
                $email->getHeaders()
                    ->addTextHeader('In-Reply-To', '<' . $emailData['in_reply_to'] . '>')
                    ->addTextHeader('References', '<' . ($emailData['references'] ?? $emailData['in_reply_to']) . '>');
            }

            $mailer->send($email);

            // Save to sent folder if IMAP is available
            if ($this->client) {
                try {
                    $this->client->connect();
                    $sentFolder = $this->client->getFolder('Sent');
                    if ($sentFolder) {
                        $sentFolder->appendMessage(
                            $email->toString(),
                            ['Seen'],
                            Carbon::now()
                        );
                    }
                    $this->client->disconnect();
                } catch (Exception $e) {
                    Log::warning('Failed to save email to Sent folder: ' . $e->getMessage());
                }
            }

            Log::info('IMAP/SMTP email sent successfully', [
                'to' => $emailData['to'],
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('IMAP/SMTP send email failed: ' . $e->getMessage(), [
                'to' => $emailData['to'] ?? 'unknown',
                'subject' => $emailData['subject'] ?? 'unknown',
            ]);

            return false;
        }
    }

    public function getAccountInfo(): array
    {
        try {
            if (!$this->isAuthenticated() || !$this->client) {
                return [];
            }

            $this->client->connect();
            
            // Get quota information if available
            $quota = null;
            try {
                $quota = $this->client->getQuota();
            } catch (Exception $e) {
                // Quota might not be supported
            }

            $info = [
                'email' => $this->emailAccount->email_address,
                'name' => $this->emailAccount->sender_name ?? '',
                'host' => $this->emailAccount->imap_host,
                'folders' => [],
            ];

            // Get folder list
            $folders = $this->client->getFolders();
            foreach ($folders as $folder) {
                $info['folders'][] = [
                    'name' => $folder->name,
                    'full_name' => $folder->full_name,
                    'messages' => $folder->messages()->count(),
                    'unseen' => $folder->messages()->unseen()->count(),
                ];
            }

            if ($quota) {
                $info['quota'] = [
                    'usage' => $quota['usage'] ?? 0,
                    'limit' => $quota['limit'] ?? 0,
                ];
            }

            $this->client->disconnect();

            return $info;
        } catch (Exception $e) {
            Log::error('IMAP get account info failed: ' . $e->getMessage());
            return [];
        }
    }

    public function getFolders(): array
    {
        try {
            if (!$this->isAuthenticated() || !$this->client) {
                return [];
            }

            $this->client->connect();
            
            $folderList = [];
            $folders = $this->client->getFolders();
            
            foreach ($folders as $folder) {
                $folderList[] = [
                    'id' => $folder->full_name,
                    'name' => $folder->name,
                    'full_name' => $folder->full_name,
                    'total_count' => $folder->messages()->count(),
                    'unread_count' => $folder->messages()->unseen()->count(),
                    'has_children' => $folder->hasChildren(),
                ];
            }

            $this->client->disconnect();

            return $folderList;
        } catch (Exception $e) {
            Log::error('Failed to fetch IMAP folders: ' . $e->getMessage());
            return [];
        }
    }

    public function saveDraft(string $to, string $subject, string $body, ?string $inReplyTo = null, ?string $threadId = null): ?string
    {
        try {
            if (!$this->isAuthenticated() || !$this->client) {
                throw new Exception('IMAP account not authenticated');
            }

            $this->client->connect();
            
            // Create email message
            $email = (new \Symfony\Component\Mime\Email())
                ->from($this->emailAccount->email_address)
                ->to(...explode(',', $to))
                ->subject($subject)
                ->html($body);

            // Set threading headers for replies
            if ($inReplyTo) {
                $email->getHeaders()
                    ->addTextHeader('In-Reply-To', '<' . $inReplyTo . '>')
                    ->addTextHeader('References', '<' . ($threadId ?? $inReplyTo) . '>');
            }

            // Save to drafts folder
            $draftsFolder = $this->client->getFolder('Drafts');
            if ($draftsFolder) {
                $uid = $draftsFolder->appendMessage(
                    $email->toString(),
                    ['Draft'],
                    Carbon::now()
                );
                
                $this->client->disconnect();
                
                Log::info('IMAP draft created successfully', [
                    'uid' => $uid,
                    'to' => $to,
                    'subject' => $subject,
                ]);

                return (string)$uid;
            }

            $this->client->disconnect();
            
            throw new Exception('Drafts folder not found');

        } catch (Exception $e) {
            Log::error('Failed to create IMAP draft: ' . $e->getMessage());
            
            if ($this->client && $this->client->isConnected()) {
                $this->client->disconnect();
            }
            
            return null;
        }
    }

    public function processSingleEmail(string $messageId, array $options = []): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        try {
            $this->client->connect();
            
            // Search for message by ID
            $folder = $this->client->getFolder($options['folder'] ?? 'INBOX');
            $message = $folder->messages()->whereMessageId($messageId)->first();
            
            if (!$message) {
                // Try searching by UID if message ID search fails
                $message = $folder->messages()->whereUid($messageId)->first();
            }
            
            if ($message) {
                $result = $this->processImapMessage($message);
                $this->client->disconnect();
                return $result;
            }
            
            $this->client->disconnect();
            return null;
            
        } catch (Exception $e) {
            Log::error('Failed to process single IMAP email', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            
            if ($this->client && $this->client->isConnected()) {
                $this->client->disconnect();
            }
            
            return null;
        }
    }
}