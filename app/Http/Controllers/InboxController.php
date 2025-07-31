<?php

namespace App\Http\Controllers;

use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class InboxController extends Controller
{
    public function __construct(
        private EmailService $emailService
    ) {}

    public function index(Request $request): Response
    {


        // Validate input parameters
        $validated = $request->validate([
            'account' => 'nullable|integer|exists:email_accounts,id',
            'folder' => 'nullable|string|in:inbox,drafts,sent,junk,trash,archive',
            'search' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|in:5,10,25,50,100',
            'filter' => 'nullable|string|in:all,unread',
        ]);

        $user = auth()->user();

        // Check if user has a company_id
        if (! $user->company_id) {
            Log::warning('User does not have a company_id', ['user_id' => $user->id]);

            // Return empty inbox if no company
            return Inertia::render('inbox', [
                'emails' => [],
                'emailAccounts' => [],
                'selectedAccount' => null,
                'folders' => [
                    'inbox' => 0,
                    'drafts' => 0,
                    'sent' => 0,
                    'junk' => 0,
                    'trash' => 0,
                    'archive' => 0,
                ],
                'currentFolder' => 'inbox',
                'currentFilter' => 'all',
                'error' => 'No company associated with your account. Please contact support.',
            ]);
        }

        $selectedAccountId = $validated['account'] ?? null;
        $folder = $validated['folder'] ?? 'inbox';
        $search = $validated['search'] ?? null;
        $page = $validated['page'] ?? 1;
        $perPage = $validated['per_page'] ?? 5;


        // Get filter from query params or validated data
        $filter = $request->query('filter') ?? $validated['filter'] ?? 'all';


        try {
            // Get paginated emails
            $emailsData = $this->emailService->getInboxEmails(
                companyId: $user->company_id,
                accountId: $selectedAccountId ? (int) $selectedAccountId : null,
                folder: $folder,
                search: $search,
                perPage: $perPage,
                cursor: (string) $page,
                filter: $filter
            );

            // Get email accounts
            $emailAccounts = $this->emailService->getEmailAccounts($user->company_id);

            // Get folder counts
            $folders = $this->emailService->getFolderCounts(
                $user->company_id,
                $selectedAccountId ? (int) $selectedAccountId : null
            );



            return Inertia::render('inbox', [
                'emails' => $emailsData['data'],
                'emailAccounts' => $emailAccounts,
                'selectedAccount' => $selectedAccountId ? (int) $selectedAccountId : null,
                'folders' => $folders,
                'currentFolder' => $folder,
                'currentFilter' => $filter,
                'pagination' => [
                    'links' => $emailsData['links'],
                    'meta' => $emailsData['meta'],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to load inbox', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('inbox', [
                'emails' => [],
                'emailAccounts' => [],
                'selectedAccount' => null,
                'folders' => [
                    'inbox' => 0,
                    'drafts' => 0,
                    'sent' => 0,
                    'junk' => 0,
                    'trash' => 0,
                    'archive' => 0,
                ],
                'currentFolder' => $folder,
                'currentFilter' => $filter,
                'error' => 'Failed to load emails. Please try again.',
            ]);
        }
    }
}
