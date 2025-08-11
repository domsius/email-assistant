<?php

namespace App\Http\Controllers;

use App\Jobs\SyncEmailAccountJob;
use App\Models\Company;
use App\Models\EmailAccount;
use App\Services\EmailProviderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class EmailAccountController extends Controller
{
    public function __construct(
        private EmailProviderFactory $providerFactory
    ) {}

    public function index(): Response
    {
        $user = auth()->user();
        $companyId = $user->company_id;

        // If user doesn't have a company, ensure they get one
        if (! $companyId) {
            $defaultCompany = Company::first();
            if (! $defaultCompany) {
                $defaultCompany = Company::create([
                    'name' => 'Default Company',
                    'email_limit' => 1000,
                    'subscription_plan' => 'basic',
                    'is_active' => true,
                ]);
            }
            $user->company_id = $defaultCompany->id;
            $user->save();
            $companyId = $defaultCompany->id;
        }

        // Only show email accounts belonging to the current user
        $accounts = EmailAccount::where('company_id', $companyId)
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($account) {
                // Get email stats
                $totalEmails = $account->emailMessages()->count();
                $processedEmails = $account->emailMessages()->where('status', 'processed')->count();
                $pendingEmails = $account->emailMessages()->where('status', 'pending')->count();
                $lastEmail = $account->emailMessages()->latest('received_at')->first();

                return [
                    'id' => $account->id,
                    'email' => $account->email_address,
                    'provider' => $account->provider,
                    'status' => $account->sync_status === 'syncing' ? 'syncing' : ($account->is_active ? 'active' : 'inactive'),
                    'lastSyncAt' => $account->last_sync_at,
                    'createdAt' => $account->created_at,
                    'stats' => [
                        'totalEmails' => $totalEmails,
                        'processedEmails' => $processedEmails,
                        'pendingEmails' => $pendingEmails,
                        'lastEmailAt' => $lastEmail?->received_at,
                    ],
                    'settings' => [
                        'syncEnabled' => $account->is_active,
                        'syncFrequency' => 15, // minutes
                        'processAutomatically' => true,
                    ],
                    'syncProgress' => [
                        'status' => $account->sync_status,
                        'progress' => $account->sync_progress,
                        'total' => $account->sync_total,
                        'percentage' => $account->sync_total > 0
                            ? round(($account->sync_progress / $account->sync_total) * 100, 2)
                            : 0,
                        'error' => $account->sync_error,
                        'startedAt' => $account->sync_started_at,
                        'completedAt' => $account->sync_completed_at,
                    ],
                ];
            });

        return Inertia::render('email-accounts', [
            'accounts' => $accounts,
            'canAddMore' => $accounts->count() < 5,
            'maxAccounts' => 5,
        ]);
    }

    public function connect(Request $request, string $provider): RedirectResponse
    {
        if (! in_array($provider, ['gmail', 'outlook'])) {
            abort(404);
        }

        $user = auth()->user();
        $companyId = $user->company_id;

        // If user doesn't have a company, create one or assign to default
        if (! $companyId) {
            // Check if there's a default company
            $defaultCompany = Company::first();
            if (! $defaultCompany) {
                // Create a default company
                $defaultCompany = Company::create([
                    'name' => 'Default Company',
                    'email_limit' => 1000,
                    'subscription_plan' => 'basic',
                    'is_active' => true,
                ]);
            }

            // Assign user to the company
            $user->company_id = $defaultCompany->id;
            $user->save();
            $companyId = $defaultCompany->id;
        }

        // Check if this is a reconnection of an existing account
        $accountId = $request->input('account_id');
        if ($accountId) {
            $emailAccount = EmailAccount::where('id', $accountId)
                ->where('company_id', $companyId)
                ->where('user_id', $user->id)  // Ensure user owns this account
                ->first();

            if (! $emailAccount) {
                abort(404, 'Email account not found');
            }
        } else {
            // Create a unique temporary email account record that will be updated after OAuth
            $tempEmail = 'pending_'.time().'_'.auth()->id().'@'.$provider.'.com';
            $emailAccount = EmailAccount::create([
                'company_id' => $companyId,
                'user_id' => $user->id,  // Assign to current user
                'email_address' => $tempEmail,
                'provider' => $provider,
                'is_active' => false,
            ]);
        }

        try {
            $providerService = $this->providerFactory->createProvider($emailAccount);
            $redirectUri = url("/api/email-accounts/oauth/callback/{$provider}");

            // Store state for security
            $state = Str::random(40);



            $emailAccount->update([
                'oauth_state' => $state,
            ]);



            // Get OAuth URL with state parameter properly set
            $authUrl = $providerService->getAuthUrl($redirectUri, $state);

            // Redirect to OAuth URL
            return redirect($authUrl);
        } catch (\Exception $e) {
            Log::error('OAuth initiation failed', [
                'error' => $e->getMessage(),
                'provider' => $provider,
                'account_id' => $accountId ?? 'new',
            ]);

            return back()->withErrors(['provider' => 'Failed to initiate OAuth: '.$e->getMessage()]);
        }
    }

    public function remove(EmailAccount $emailAccount): RedirectResponse
    {
        // Ensure user owns this account
        $user = auth()->user();
        if ($emailAccount->company_id !== $user->company_id || $emailAccount->user_id !== $user->id) {
            abort(403);
        }

        $emailAccount->delete();

        return redirect('/email-accounts')->with('success', 'Email account removed successfully');
    }

    public function sync(EmailAccount $emailAccount): RedirectResponse
    {
        // Ensure user owns this account
        $user = auth()->user();
        if ($emailAccount->company_id !== $user->company_id || $emailAccount->user_id !== $user->id) {
            abort(403);
        }

        // Dispatch sync job with manual sync options
        SyncEmailAccountJob::dispatch($emailAccount, [
            'manual_sync' => true,
            'limit' => 25,
            'fetch_all' => false, // Only fetch new/unread emails for manual sync
        ]);

        return back()->with('success', 'Email sync initiated for '.$emailAccount->email_address);
    }
}
