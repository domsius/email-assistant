<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncEmailsRequest;
use App\Models\EmailAccount;
use App\Services\EmailProviderFactory;
use App\Services\EmailSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailAccountController extends Controller
{
    public function __construct(
        private EmailProviderFactory $providerFactory,
        private EmailSyncService $syncService
    ) {}

    /**
     * Display a listing of email accounts for the authenticated user's company.
     */
    public function index(): JsonResponse
    {
        $accounts = EmailAccount::where('company_id', auth()->user()->company_id)
            ->with(['company'])
            ->get();

        return response()->json($accounts);
    }

    /**
     * Get supported email providers
     */
    public function providers(): JsonResponse
    {
        $providers = EmailProviderFactory::getSupportedProviders();

        return response()->json($providers);
    }

    /**
     * Initiate OAuth flow for email provider
     */
    public function initiateOAuth(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:gmail,outlook',
            'email_address' => 'required|email',
        ]);

        $companyId = auth()->user()->company_id;

        // Check if account already exists
        $existingAccount = EmailAccount::where('company_id', $companyId)
            ->where('email_address', $request->email_address)
            ->where('provider', $request->provider)
            ->first();

        if ($existingAccount && $existingAccount->is_active) {
            return response()->json([
                'error' => 'Email account already connected and active',
            ], 422);
        }

        // Create or update email account
        $emailAccount = EmailAccount::updateOrCreate(
            [
                'company_id' => $companyId,
                'email_address' => $request->email_address,
                'provider' => $request->provider,
            ],
            [
                'is_active' => false, // Will be activated after successful OAuth
            ]
        );

        // Generate OAuth URL
        $redirectUri = url("/api/email-accounts/oauth/callback/{$request->provider}");

        try {
            Log::info('Creating OAuth provider for account', [
                'account_id' => $emailAccount->id,
                'provider' => $request->provider,
                'email' => $request->email_address,
            ]);

            $provider = $this->providerFactory->createProvider($emailAccount);
            $authUrl = $provider->getAuthUrl($redirectUri);

            // Store state for security
            $state = Str::random(40);

            Log::info('Storing OAuth state', [
                'account_id' => $emailAccount->id,
                'state' => $state,
                'provider' => $request->provider,
            ]);

            $emailAccount->update([
                'oauth_state' => $state,
            ]);

            Log::info('OAuth state stored successfully', [
                'account_id' => $emailAccount->id,
                'state_in_db' => $emailAccount->fresh()->oauth_state,
            ]);

            return response()->json([
                'auth_url' => $authUrl.'&state='.$state,
                'account_id' => $emailAccount->id,
            ]);
        } catch (\Exception $e) {
            Log::error('OAuth initiation failed', [
                'error' => $e->getMessage(),
                'provider' => $request->provider,
                'account_id' => $emailAccount->id ?? 'new',
            ]);

            return response()->json([
                'error' => 'Failed to initiate OAuth: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle OAuth callback
     */
    public function handleOAuthCallback(Request $request, string $provider)
    {
        Log::info('OAuth callback received', [
            'provider' => $provider,
            'code' => substr($request->code ?? 'missing', 0, 20).'...',
            'state' => $request->state ?? 'missing',
            'all_params' => $request->all(),
        ]);

        $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        Log::info('Looking for email account by OAuth state', [
            'provider' => $provider,
            'state' => $request->state,
        ]);

        // Debug: Check all accounts with oauth_state
        $allAccountsWithState = EmailAccount::whereNotNull('oauth_state')->get(['id', 'email_address', 'provider', 'oauth_state']);
        Log::info('All accounts with oauth_state in database', [
            'accounts' => $allAccountsWithState->toArray(),
        ]);

        // Find email account by state
        $emailAccount = EmailAccount::where('oauth_state', $request->state)
            ->where('provider', $provider)
            ->first();

        Log::info('Email account found for OAuth', [
            'account_id' => $emailAccount->id ?? 'not found',
            'email' => $emailAccount->email_address ?? 'not found',
            'state' => $request->state,
            'found' => $emailAccount ? 'yes' : 'no',
        ]);

        if (! $emailAccount) {
            return redirect('/email-accounts')->withErrors(['oauth' => 'Invalid OAuth state']);
        }

        try {
            $providerService = $this->providerFactory->createProvider($emailAccount);
            $redirectUri = url("/api/email-accounts/oauth/callback/{$provider}");

            $success = $providerService->handleCallback($request->code, $redirectUri);

            if ($success) {
                // Get the actual email address from the authenticated account
                $accountInfo = $providerService->getAccountInfo();
                $actualEmail = $accountInfo['email'] ?? $emailAccount->email_address;

                // Check if this email is already connected for this company
                $existingAccount = EmailAccount::where('company_id', $emailAccount->company_id)
                    ->where('email_address', $actualEmail)
                    ->where('provider', $provider)
                    ->where('id', '!=', $emailAccount->id)
                    ->first();

                if ($existingAccount && $existingAccount->is_active) {
                    // Delete the temporary account and redirect with error
                    $emailAccount->delete();

                    return redirect('/email-accounts')->withErrors(['oauth' => 'This email account is already connected']);
                }

                // Update the email account with the actual email address and clear OAuth state
                $emailAccount->update([
                    'email_address' => $actualEmail,
                    'oauth_state' => null,
                ]);

                Log::info('OAuth successful, dispatching initial email sync job', [
                    'account_id' => $emailAccount->id,
                    'email' => $actualEmail,
                ]);

                // Dispatch initial full sync job
                \App\Jobs\InitialEmailSyncJob::dispatch($emailAccount);

                return redirect('/email-accounts')->with('success', 'Email account connected successfully! Emails are being synced in the background.');
            } else {
                // Clear OAuth state on failure but keep the account for retry
                $emailAccount->update(['oauth_state' => null]);

                return redirect('/email-accounts')->withErrors(['oauth' => 'OAuth callback failed']);
            }
        } catch (\Exception $e) {
            Log::error('OAuth callback error', [
                'error' => $e->getMessage(),
                'provider' => $provider,
                'trace' => $e->getTraceAsString(),
            ]);

            // Delete the failed temporary account
            if ($emailAccount) {
                $emailAccount->delete();
            }

            return redirect('/email-accounts')->withErrors(['oauth' => 'OAuth callback error: '.$e->getMessage()]);
        }
    }

    /**
     * Get sync progress for an email account
     */
    public function syncProgress(EmailAccount $emailAccount): JsonResponse
    {
        // Ensure user can access this account
        if ($emailAccount->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        return response()->json([
            'sync_status' => $emailAccount->sync_status,
            'sync_progress' => $emailAccount->sync_progress,
            'sync_total' => $emailAccount->sync_total,
            'sync_error' => $emailAccount->sync_error,
            'sync_started_at' => $emailAccount->sync_started_at,
            'sync_completed_at' => $emailAccount->sync_completed_at,
            'percentage' => $emailAccount->sync_total > 0 
                ? round(($emailAccount->sync_progress / $emailAccount->sync_total) * 100, 2) 
                : 0,
        ]);
    }

    /**
     * Sync emails from a specific account
     */
    public function syncEmails(SyncEmailsRequest $request, EmailAccount $emailAccount): JsonResponse
    {
        $validated = $request->validated();

        // Ensure user can access this account
        if ($emailAccount->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $options = [
            'limit' => $validated['limit'] ?? 50,
            'batch_size' => $validated['batch_size'] ?? 10,
            'page_token' => $validated['page_token'] ?? null,
        ];

        $result = $this->syncService->syncEmails($emailAccount, $options);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'processed' => $result['processed'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
                'has_more' => $result['has_more'],
                'next_page_token' => $result['next_page_token'] ?? null,
                'last_sync_at' => $emailAccount->fresh()->last_sync_at,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'error' => $result['error'],
                'processed' => $result['processed'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors'],
            ], 500);
        }
    }

    /**
     * Test email account connection
     */
    public function testConnection(EmailAccount $emailAccount): JsonResponse
    {
        // Ensure user can access this account
        if ($emailAccount->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        try {
            $provider = $this->providerFactory->createProvider($emailAccount);

            if (! $provider->isAuthenticated()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account not authenticated',
                ], 422);
            }

            $accountInfo = $provider->getAccountInfo();

            return response()->json([
                'success' => true,
                'account_info' => $accountInfo,
                'last_sync_at' => $emailAccount->last_sync_at,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Connection test failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sync status for an email account
     */
    public function syncStatus(EmailAccount $emailAccount): JsonResponse
    {
        // Ensure user can access this account
        if ($emailAccount->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $status = $this->syncService->getSyncStatus($emailAccount);

        return response()->json($status);
    }

    /**
     * Remove an email account
     */
    public function destroy(EmailAccount $emailAccount): JsonResponse
    {
        // Ensure user can access this account
        if ($emailAccount->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $emailAccount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Email account removed successfully',
        ]);
    }
}
