<?php

namespace App\Services;

use App\Models\EmailAccount;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OutlookWebhookService
{
    /**
     * Create a webhook subscription for an Outlook account
     */
    public function createSubscription(EmailAccount $emailAccount): ?string
    {
        try {
            if (!$emailAccount->access_token) {
                Log::warning('Cannot create Outlook subscription without access token', [
                    'account_id' => $emailAccount->id,
                ]);
                return null;
            }

            $notificationUrl = config('app.url') . '/api/webhooks/outlook';
            
            // Generate a unique client state for security validation
            $clientState = $this->generateClientState($emailAccount);
            
            $subscriptionData = [
                'changeType' => 'created,updated',
                'notificationUrl' => $notificationUrl,
                'resource' => 'me/messages',
                'expirationDateTime' => now()->addDays(2)->toIso8601String(), // Max 3 days for messages
                'clientState' => $clientState,
            ];

            $response = Http::withToken($emailAccount->access_token)
                ->post('https://graph.microsoft.com/v1.0/subscriptions', $subscriptionData);

            if ($response->successful()) {
                $subscription = $response->json();
                
                // Store subscription details in the email account
                $emailAccount->update([
                    'outlook_subscription_id' => $subscription['id'],
                    'outlook_subscription_expires_at' => Carbon::parse($subscription['expirationDateTime']),
                    'outlook_webhook_token' => $clientState,
                ]);

                Log::info('Outlook webhook subscription created', [
                    'account_id' => $emailAccount->id,
                    'subscription_id' => $subscription['id'],
                    'expires_at' => $subscription['expirationDateTime'],
                ]);

                return $subscription['id'];
            }

            Log::error('Failed to create Outlook subscription', [
                'response' => $response->json(),
                'status' => $response->status(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Error creating Outlook webhook subscription: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Renew an existing subscription before it expires
     */
    public function renewSubscription(EmailAccount $emailAccount): bool
    {
        if (!$emailAccount->outlook_subscription_id) {
            return $this->createSubscription($emailAccount) !== null;
        }

        try {
            $renewalData = [
                'expirationDateTime' => now()->addDays(2)->toIso8601String(),
            ];

            $response = Http::withToken($emailAccount->access_token)
                ->patch("https://graph.microsoft.com/v1.0/subscriptions/{$emailAccount->outlook_subscription_id}", $renewalData);

            if ($response->successful()) {
                $subscription = $response->json();
                
                $emailAccount->update([
                    'outlook_subscription_expires_at' => Carbon::parse($subscription['expirationDateTime']),
                ]);

                Log::info('Outlook subscription renewed', [
                    'account_id' => $emailAccount->id,
                    'subscription_id' => $emailAccount->outlook_subscription_id,
                    'new_expiry' => $subscription['expirationDateTime'],
                ]);

                return true;
            }

            // If renewal fails (subscription might be deleted), try creating a new one
            if ($response->status() === 404) {
                Log::info('Subscription not found, creating new one', [
                    'account_id' => $emailAccount->id,
                ]);
                
                $emailAccount->update([
                    'outlook_subscription_id' => null,
                    'outlook_subscription_expires_at' => null,
                ]);
                
                return $this->createSubscription($emailAccount) !== null;
            }

            Log::error('Failed to renew Outlook subscription', [
                'response' => $response->json(),
                'status' => $response->status(),
            ]);

            return false;
        } catch (Exception $e) {
            Log::error('Error renewing Outlook subscription: ' . $e->getMessage(), [
                'account_id' => $emailAccount->id,
            ]);
            return false;
        }
    }

    /**
     * Delete a subscription
     */
    public function deleteSubscription(EmailAccount $emailAccount): bool
    {
        if (!$emailAccount->outlook_subscription_id) {
            return true;
        }

        try {
            $response = Http::withToken($emailAccount->access_token)
                ->delete("https://graph.microsoft.com/v1.0/subscriptions/{$emailAccount->outlook_subscription_id}");

            if ($response->successful() || $response->status() === 404) {
                $emailAccount->update([
                    'outlook_subscription_id' => null,
                    'outlook_subscription_expires_at' => null,
                    'outlook_webhook_token' => null,
                ]);

                Log::info('Outlook subscription deleted', [
                    'account_id' => $emailAccount->id,
                ]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error('Error deleting Outlook subscription: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate a secure client state for webhook validation
     */
    private function generateClientState(EmailAccount $emailAccount): string
    {
        return hash('sha256', $emailAccount->id . $emailAccount->email_address . config('app.key'));
    }

    /**
     * Validate the client state from a webhook notification
     */
    public function validateClientState(EmailAccount $emailAccount, string $clientState): bool
    {
        $expectedState = $this->generateClientState($emailAccount);
        return hash_equals($expectedState, $clientState);
    }
}