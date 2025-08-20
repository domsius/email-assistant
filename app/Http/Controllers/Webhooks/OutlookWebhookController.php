<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use App\Services\OutlookWebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OutlookWebhookController extends Controller
{
    public function __construct(
        private OutlookWebhookService $webhookService
    ) {}

    /**
     * Handle Microsoft Graph webhook validation and notifications
     */
    public function handleNotification(Request $request)
    {
        // Handle validation token (initial setup)
        if ($request->has('validationToken')) {
            $validationToken = $request->input('validationToken');
            
            Log::info('Outlook webhook validation requested', [
                'token' => substr($validationToken, 0, 20) . '...',
            ]);
            
            // Return validation token as plain text
            return response($validationToken, 200)
                ->header('Content-Type', 'text/plain');
        }

        // Handle actual notifications
        $notifications = $request->input('value', []);
        
        Log::info('Outlook webhook notifications received', [
            'count' => count($notifications),
        ]);
        
        foreach ($notifications as $notification) {
            $this->processNotification($notification);
        }

        return response('', 200);
    }

    /**
     * Process a single notification
     */
    private function processNotification(array $notification)
    {
        $subscriptionId = $notification['subscriptionId'] ?? null;
        $changeType = $notification['changeType'] ?? null;
        $resource = $notification['resource'] ?? null;
        $clientState = $notification['clientState'] ?? null;

        if (!$subscriptionId) {
            Log::warning('Outlook webhook: No subscription ID in notification');
            return;
        }

        Log::info('Processing Outlook webhook notification', [
            'subscription_id' => $subscriptionId,
            'change_type' => $changeType,
            'resource' => $resource,
        ]);

        // Find the email account by subscription ID
        $emailAccount = EmailAccount::where('outlook_subscription_id', $subscriptionId)
            ->where('provider', 'outlook')
            ->first();

        if (!$emailAccount) {
            Log::warning('Outlook webhook: Unknown subscription ID', [
                'subscription_id' => $subscriptionId,
            ]);
            return;
        }

        // Validate client state for security
        if ($clientState && !$this->webhookService->validateClientState($emailAccount, $clientState)) {
            Log::warning('Outlook webhook: Invalid client state', [
                'account_id' => $emailAccount->id,
                'subscription_id' => $subscriptionId,
            ]);
            return;
        }

        // Handle different change types
        switch ($changeType) {
            case 'created':
                $this->handleNewEmail($emailAccount, $resource);
                break;
                
            case 'updated':
                $this->handleUpdatedEmail($emailAccount, $resource);
                break;

            default:
                Log::info('Outlook webhook: Unhandled change type', [
                    'change_type' => $changeType,
                    'account_id' => $emailAccount->id,
                ]);
        }
    }

    /**
     * Handle new email notification
     */
    private function handleNewEmail(EmailAccount $emailAccount, ?string $resource)
    {
        Log::info('Outlook webhook: New email detected', [
            'account_id' => $emailAccount->id,
            'email' => $emailAccount->email_address,
            'resource' => $resource,
        ]);

        // Extract message ID from resource if available
        // Resource format: users/{user-id}/messages/{message-id}
        $messageId = null;
        if ($resource && preg_match('/messages\/(.+)$/', $resource, $matches)) {
            $messageId = $matches[1];
        }

        // Dispatch sync job with webhook flag
        SyncEmailAccountJob::dispatch($emailAccount, [
            'webhook_sync' => true,
            'limit' => 20, // Fetch recent emails
            'fetch_all' => false, // Only unread
            'specific_message_id' => $messageId, // If we have a specific message ID
        ])
            ->onQueue('high-priority');
    }

    /**
     * Handle updated email notification (e.g., read status change)
     */
    private function handleUpdatedEmail(EmailAccount $emailAccount, ?string $resource)
    {
        Log::info('Outlook webhook: Email updated', [
            'account_id' => $emailAccount->id,
            'email' => $emailAccount->email_address,
            'resource' => $resource,
        ]);

        // For updates, we might want to sync just that specific email
        $messageId = null;
        if ($resource && preg_match('/messages\/(.+)$/', $resource, $matches)) {
            $messageId = $matches[1];
        }

        if ($messageId) {
            // Dispatch a job to update just this specific email
            SyncEmailAccountJob::dispatch($emailAccount, [
                'webhook_sync' => true,
                'specific_message_id' => $messageId,
                'update_only' => true, // Just update existing email
            ])
                ->onQueue('high-priority');
        }
    }
}