<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GmailWebhookController extends Controller
{
    /**
     * Handle incoming Gmail push notification
     * 
     * Gmail sends a POST request with:
     * - X-Goog-Channel-ID: The channel ID
     * - X-Goog-Channel-Token: The channel token for verification
     * - X-Goog-Resource-ID: The resource ID
     * - X-Goog-Resource-State: The resource state (e.g., "exists", "sync")
     * - X-Goog-Message-Number: Message number (incrementing)
     * 
     * Body contains:
     * {
     *   "message": {
     *     "data": "base64-encoded-data",
     *     "messageId": "message-id",
     *     "publishTime": "timestamp"
     *   }
     * }
     */
    public function handleNotification(Request $request)
    {
        // Get headers
        $channelId = $request->header('X-Goog-Channel-ID');
        $channelToken = $request->header('X-Goog-Channel-Token');
        $resourceState = $request->header('X-Goog-Resource-State');
        $messageNumber = $request->header('X-Goog-Message-Number');
        
        Log::info('Gmail webhook received', [
            'channel_id' => $channelId,
            'resource_state' => $resourceState,
            'message_number' => $messageNumber,
        ]);

        // Verify the channel token matches what we expect
        // The token should match the email account ID or a secure token we generate
        $emailAccount = EmailAccount::where('gmail_watch_token', $channelToken)
            ->where('provider', 'gmail')
            ->first();

        if (!$emailAccount) {
            Log::warning('Gmail webhook: Invalid channel token', [
                'token' => $channelToken,
                'channel_id' => $channelId,
            ]);
            return response('Unauthorized', 401);
        }

        // Handle the notification based on resource state
        switch ($resourceState) {
            case 'sync':
                // Initial sync message when watch is set up
                Log::info('Gmail webhook: Sync message received', [
                    'account_id' => $emailAccount->id,
                    'email' => $emailAccount->email,
                ]);
                break;
                
            case 'exists':
                // Mailbox has changes
                Log::info('Gmail webhook: Mailbox changes detected', [
                    'account_id' => $emailAccount->id,
                    'email' => $emailAccount->email,
                ]);
                
                // Decode the message data if present
                if ($request->has('message.data')) {
                    $data = base64_decode($request->input('message.data'));
                    $decodedData = json_decode($data, true);
                    
                    Log::info('Gmail webhook: Message data', [
                        'account_id' => $emailAccount->id,
                        'data' => $decodedData,
                    ]);
                    
                    // Extract history ID if available
                    $historyId = $decodedData['historyId'] ?? null;
                    if ($historyId) {
                        $emailAccount->gmail_history_id = $historyId;
                        $emailAccount->save();
                    }
                }
                
                // Dispatch sync job with high priority
                SyncEmailAccountJob::dispatch($emailAccount)
                    ->onQueue('high-priority');
                    
                break;
                
            default:
                Log::warning('Gmail webhook: Unknown resource state', [
                    'state' => $resourceState,
                    'account_id' => $emailAccount->id,
                ]);
        }

        // Return 200 OK to acknowledge receipt
        return response('', 200);
    }

    /**
     * Verify webhook endpoint for initial setup
     * Gmail may call this to verify the endpoint is valid
     */
    public function verify(Request $request)
    {
        // Return the challenge if provided (for initial verification)
        if ($request->has('challenge')) {
            return response($request->input('challenge'), 200)
                ->header('Content-Type', 'text/plain');
        }
        
        return response('OK', 200);
    }
}