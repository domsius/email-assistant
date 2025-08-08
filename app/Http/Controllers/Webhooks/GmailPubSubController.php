<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GmailPubSubController extends Controller
{
    /**
     * Handle incoming Pub/Sub push message from Google Cloud
     * 
     * Pub/Sub sends a POST request with:
     * {
     *   "message": {
     *     "attributes": {
     *       "key": "value"
     *     },
     *     "data": "base64-encoded-data",
     *     "messageId": "message-id",
     *     "message_id": "message-id",
     *     "publishTime": "2014-10-02T15:01:23.045123456Z",
     *     "publish_time": "2014-10-02T15:01:23.045123456Z"
     *   },
     *   "subscription": "projects/myproject/subscriptions/mysubscription"
     * }
     */
    public function handlePushNotification(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('Gmail Pub/Sub notification received', [
            'headers' => $request->headers->all(),
            'body' => $request->all(),
        ]);

        // Verify the push token if configured (optional but recommended)
        $pushToken = $request->query('token');
        if (config('services.google.pubsub_push_token')) {
            if ($pushToken !== config('services.google.pubsub_push_token')) {
                Log::warning('Gmail Pub/Sub: Invalid push token', ['token' => $pushToken]);
                return response('Unauthorized', 401);
            }
        }

        // Extract the Pub/Sub message
        $message = $request->input('message');
        
        if (!$message) {
            Log::error('Gmail Pub/Sub: No message in request');
            return response('Bad Request', 400);
        }

        // Decode the message data
        $data = isset($message['data']) ? base64_decode($message['data']) : null;
        
        if (!$data) {
            Log::error('Gmail Pub/Sub: No data in message');
            return response('Bad Request', 400);
        }

        // Parse the Gmail notification data
        $notificationData = json_decode($data, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Gmail Pub/Sub: Failed to parse notification data', [
                'data' => $data,
                'error' => json_last_error_msg(),
            ]);
            return response('Bad Request', 400);
        }

        Log::info('Gmail Pub/Sub: Parsed notification', [
            'notification' => $notificationData,
        ]);

        // Extract email address and history ID from the notification
        $emailAddress = $notificationData['emailAddress'] ?? null;
        $historyId = $notificationData['historyId'] ?? null;
        
        if (!$emailAddress) {
            Log::error('Gmail Pub/Sub: No email address in notification');
            return response('Bad Request', 400);
        }

        // Find the email account
        $emailAccount = EmailAccount::where('email_address', $emailAddress)
            ->where('provider', 'gmail')
            ->where('is_active', true)
            ->first();

        if (!$emailAccount) {
            Log::warning('Gmail Pub/Sub: Email account not found', [
                'email' => $emailAddress,
            ]);
            // Still return 200 to acknowledge the message
            return response('', 200);
        }

        // Update history ID if provided
        if ($historyId) {
            // Only update if the new history ID is greater than the current one
            if (!$emailAccount->gmail_history_id || $historyId > $emailAccount->gmail_history_id) {
                $emailAccount->gmail_history_id = $historyId;
                $emailAccount->save();
                
                Log::info('Gmail Pub/Sub: Updated history ID', [
                    'account_id' => $emailAccount->id,
                    'email' => $emailAddress,
                    'history_id' => $historyId,
                ]);
            }
        }

        // Dispatch sync job with high priority
        // Use history-based sync for efficiency
        SyncEmailAccountJob::dispatch($emailAccount, [
            'use_history' => true,
            'history_id' => $historyId,
        ])->onQueue('high-priority');

        Log::info('Gmail Pub/Sub: Sync job dispatched', [
            'account_id' => $emailAccount->id,
            'email' => $emailAddress,
        ]);

        // Return 200 OK to acknowledge receipt
        // This is important - Pub/Sub will retry if it doesn't get a 2xx response
        return response('', 200);
    }

    /**
     * Health check endpoint for Pub/Sub
     */
    public function health()
    {
        return response()->json(['status' => 'healthy'], 200);
    }
}