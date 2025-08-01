<?php

namespace App\Services;

use App\DTOs\EmailDTO;
use App\Jobs\SyncEmailAccountJob;
use App\Models\EmailAccount;
use App\Models\EmailDraft;
use App\Repositories\EmailRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class EmailService
{
    public function __construct(
        private EmailRepository $emailRepository
    ) {}

    /**
     * Get paginated emails for inbox view
     */
    public function getInboxEmails(
        int $companyId,
        ?int $accountId = null,
        string $folder = 'inbox',
        ?string $search = null,
        int $perPage = 100,
        ?string $cursor = null,
        string $filter = 'all'
    ): array {
        // Handle drafts folder separately
        if ($folder === 'drafts') {
            return $this->getDraftEmails(
                $companyId,
                $accountId,
                $search,
                $perPage,
                $cursor
            );
        }

        // Handle trash folder - need to include both deleted emails and deleted drafts
        if ($folder === 'trash') {
            return $this->getTrashedItems(
                $companyId,
                $accountId,
                $search,
                $perPage,
                $cursor,
                $filter
            );
        }

        $emails = $this->emailRepository->getPaginatedEmails(
            $companyId,
            $accountId,
            $folder,
            $search,
            $perPage,
            $cursor,
            $filter
        );

        // Convert to DTOs
        $emailDTOs = $emails->map(fn ($email) => EmailDTO::fromModel($email));

        return [
            'data' => $emailDTOs->map(fn ($dto) => $dto->toArray())->toArray(),
            'links' => [
                'first' => $emails->url(1),
                'last' => $emails->url($emails->lastPage()),
                'prev' => $emails->previousPageUrl(),
                'next' => $emails->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $emails->currentPage(),
                'from' => $emails->firstItem(),
                'last_page' => $emails->lastPage(),
                'per_page' => $emails->perPage(),
                'to' => $emails->lastItem(),
                'total' => $emails->total(),
            ],
        ];
    }

    /**
     * Get paginated draft emails
     */
    private function getDraftEmails(
        int $companyId,
        ?int $accountId = null,
        ?string $search = null,
        int $perPage = 100,
        ?string $cursor = null
    ): array {
        $user = auth()->user();

        $query = EmailDraft::where('user_id', $user->id)
            ->where('is_deleted', false)
            ->with(['emailAccount', 'originalEmail']);

        // Filter by account if specified
        if ($accountId) {
            $query->where('email_account_id', $accountId);
        } else {
            // Get all accounts for the company
            $accountIds = EmailAccount::where('company_id', $companyId)
                ->pluck('id')
                ->toArray();
            $query->whereIn('email_account_id', $accountIds);
        }

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $searchTerm = '%'.$search.'%';
                $q->where('subject', 'like', $searchTerm)
                    ->orWhere('to', 'like', $searchTerm)
                    ->orWhere('body', 'like', $searchTerm);
            });
        }

        // Order by last saved date
        $drafts = $query->orderBy('last_saved_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $cursor ? (int) $cursor : null);

        // Only append necessary query parameters, excluding Inertia-specific ones
        $allowedParams = ['folder', 'filter', 'search', 'account', 'per_page'];
        $queryParams = [];

        foreach ($allowedParams as $param) {
            if (request()->has($param) && request()->get($param) !== null && request()->get($param) !== '') {
                $queryParams[$param] = request()->get($param);
            }
        }

        $drafts = $drafts->appends($queryParams);

        // Transform drafts to email-like structure
        $draftData = $drafts->map(function ($draft) {
            return [
                'id' => 'draft-'.$draft->id, // Prefix to distinguish from emails
                'subject' => $draft->subject ?: '(No subject)',
                'sender' => 'Draft',
                'senderEmail' => $draft->emailAccount->email_address ?? 'Unknown',
                'from' => $draft->emailAccount->email_address ?? 'Unknown',
                'to' => $draft->to,
                'content' => $draft->body,
                'snippet' => substr(strip_tags($draft->body), 0, 100).'...',
                'receivedAt' => $draft->last_saved_at->toIso8601String(),
                'date' => $draft->last_saved_at->toIso8601String(),
                'status' => 'processed',
                'isRead' => true, // Drafts are always "read"
                'isStarred' => false,
                'isSelected' => false,
                'emailAccountId' => $draft->email_account_id,
                'isDraft' => true,
                'draftId' => $draft->id,
                'action' => $draft->action,
                'originalEmail' => $draft->originalEmail ? [
                    'id' => $draft->originalEmail->id,
                    'subject' => $draft->originalEmail->subject,
                    'sender' => $draft->originalEmail->sender_name ?: explode('@', $draft->originalEmail->from_email)[0],
                    'senderEmail' => $draft->originalEmail->from_email,
                    'content' => $draft->originalEmail->body_html ?: $draft->originalEmail->body_plain,
                    'receivedAt' => $draft->originalEmail->received_at->toIso8601String(),
                ] : null,
            ];
        });

        return [
            'data' => $draftData->toArray(),
            'links' => [
                'first' => $drafts->url(1),
                'last' => $drafts->url($drafts->lastPage()),
                'prev' => $drafts->previousPageUrl(),
                'next' => $drafts->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $drafts->currentPage(),
                'from' => $drafts->firstItem(),
                'last_page' => $drafts->lastPage(),
                'per_page' => $drafts->perPage(),
                'to' => $drafts->lastItem(),
                'total' => $drafts->total(),
            ],
        ];
    }

    /**
     * Get paginated trashed items (emails and drafts)
     */
    private function getTrashedItems(
        int $companyId,
        ?int $accountId = null,
        ?string $search = null,
        int $perPage = 100,
        ?string $cursor = null,
        string $filter = 'all'
    ): array {
        $user = auth()->user();

        // Get trashed emails
        $emails = $this->emailRepository->getPaginatedEmails(
            $companyId,
            $accountId,
            'trash',
            $search,
            $perPage,
            $cursor,
            $filter
        );

        // Get trashed drafts
        $draftQuery = EmailDraft::where('user_id', $user->id)
            ->where('is_deleted', true)
            ->with(['emailAccount', 'originalEmail']);

        // Filter by account if specified
        if ($accountId) {
            $draftQuery->where('email_account_id', $accountId);
        } else {
            // Get all accounts for the company
            $accountIds = EmailAccount::where('company_id', $companyId)
                ->pluck('id')
                ->toArray();
            $draftQuery->whereIn('email_account_id', $accountIds);
        }

        // Apply search filter
        if ($search) {
            $draftQuery->where(function ($q) use ($search) {
                $searchTerm = '%'.$search.'%';
                $q->where('subject', 'like', $searchTerm)
                    ->orWhere('to', 'like', $searchTerm)
                    ->orWhere('body', 'like', $searchTerm);
            });
        }

        // Get all drafts (we'll paginate combined results later)
        $drafts = $draftQuery->orderBy('deleted_at', 'desc')->get();

        // Transform drafts to email-like structure
        $draftData = $drafts->map(function ($draft) {
            return [
                'id' => 'draft-'.$draft->id, // Prefix to distinguish from emails
                'subject' => $draft->subject ?: '(No subject)',
                'sender' => 'Draft',
                'senderEmail' => $draft->emailAccount->email_address ?? 'Unknown',
                'from' => $draft->emailAccount->email_address ?? 'Unknown',
                'to' => $draft->to,
                'content' => $draft->body,
                'snippet' => substr(strip_tags($draft->body), 0, 100).'...',
                'receivedAt' => $draft->deleted_at->toIso8601String(),
                'date' => $draft->deleted_at->toIso8601String(),
                'status' => 'processed',
                'isRead' => true, // Drafts are always "read"
                'isStarred' => false,
                'isSelected' => false,
                'emailAccountId' => $draft->email_account_id,
                'isDraft' => true,
                'draftId' => $draft->id,
                'action' => $draft->action,
                'isDeleted' => true,
                'deletedAt' => $draft->deleted_at->toIso8601String(),
            ];
        });

        // Combine emails and drafts
        $emailDTOs = $emails->map(fn ($email) => EmailDTO::fromModel($email));
        $allItems = collect($emailDTOs->map(fn ($dto) => $dto->toArray())->toArray())
            ->concat($draftData)
            ->sortByDesc('date')
            ->values();

        // Manual pagination of combined results
        $currentPage = $cursor ? (int) $cursor : 1;
        $total = $allItems->count();
        $lastPage = max(1, ceil($total / $perPage));
        $items = $allItems->forPage($currentPage, $perPage);

        return [
            'data' => $items->toArray(),
            'links' => [
                'first' => request()->fullUrlWithQuery(['page' => 1]),
                'last' => request()->fullUrlWithQuery(['page' => $lastPage]),
                'prev' => $currentPage > 1 ? request()->fullUrlWithQuery(['page' => $currentPage - 1]) : null,
                'next' => $currentPage < $lastPage ? request()->fullUrlWithQuery(['page' => $currentPage + 1]) : null,
            ],
            'meta' => [
                'current_page' => $currentPage,
                'from' => ($currentPage - 1) * $perPage + 1,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => min($currentPage * $perPage, $total),
                'total' => $total,
            ],
        ];
    }

    /**
     * Get folder counts
     */
    public function getFolderCounts(int $companyId, ?int $accountId = null): array
    {
        $counts = $this->emailRepository->getFolderCounts($companyId, $accountId);

        return $counts->toArray();
    }

    /**
     * Get email accounts for a company
     */
    public function getEmailAccounts(int $companyId): Collection
    {
        $accounts = EmailAccount::where('company_id', $companyId)
            ->with('aliases')
            ->get();
            
        // Transform the data to match the expected format
        return $accounts->map(function ($account) {
            return [
                'id' => $account->id,
                'email' => $account->email_address,
                'provider' => $account->provider,
                'is_active' => $account->is_active,
                'aliases' => $account->aliases->toArray(),
            ];
        });
    }

    /**
     * Sync email accounts
     */
    public function syncEmails(int $companyId, ?int $accountId = null): array
    {
        try {
            if ($accountId) {
                $account = EmailAccount::where('id', $accountId)
                    ->where('company_id', $companyId)
                    ->first();

                if ($account) {
                    SyncEmailAccountJob::dispatch($account);

                    return [
                        'success' => true,
                        'message' => "Sync initiated for {$account->email_address}",
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Email account not found',
                ];
            }

            $accounts = EmailAccount::where('company_id', $companyId)
                ->where('is_active', true)
                ->get();

            if ($accounts->isEmpty()) {
                return [
                    'success' => false,
                    'message' => 'No active email accounts to sync',
                ];
            }

            foreach ($accounts as $account) {
                SyncEmailAccountJob::dispatch($account);
            }

            return [
                'success' => true,
                'message' => "Sync initiated for {$accounts->count()} accounts",
            ];
        } catch (\Exception $e) {
            Log::error('Email sync failed', [
                'company_id' => $companyId,
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initiate sync',
            ];
        }
    }

    /**
     * Archive emails
     */
    public function archiveEmails(array $emailIds, int $companyId): array
    {
        try {
            if (empty($emailIds)) {
                return [
                    'success' => false,
                    'message' => 'No emails selected',
                ];
            }

            $count = $this->emailRepository->archiveEmails($emailIds, $companyId);

            if ($count === 0) {
                return [
                    'success' => false,
                    'message' => 'No emails found or unauthorized',
                ];
            }

            return [
                'success' => true,
                'message' => "{$count} email(s) archived successfully",
            ];
        } catch (\Exception $e) {
            Log::error('Failed to archive emails', [
                'email_ids' => $emailIds,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to archive emails',
            ];
        }
    }

    /**
     * Unarchive emails
     */
    public function unarchiveEmails(array $emailIds, int $companyId): array
    {
        try {
            if (empty($emailIds)) {
                return [
                    'success' => false,
                    'message' => 'No emails selected',
                ];
            }

            $count = $this->emailRepository->unarchiveEmails($emailIds, $companyId);

            if ($count === 0) {
                return [
                    'success' => false,
                    'message' => 'No emails found or unauthorized',
                ];
            }

            return [
                'success' => true,
                'message' => "{$count} email(s) moved to inbox",
            ];
        } catch (\Exception $e) {
            Log::error('Failed to unarchive emails', [
                'email_ids' => $emailIds,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to unarchive emails',
            ];
        }
    }

    /**
     * Move emails to spam/junk folder
     */
    public function moveToSpam(array $emailIds, int $companyId): array
    {
        try {
            if (empty($emailIds)) {
                return [
                    'success' => false,
                    'message' => 'No emails selected',
                ];
            }

            $count = $this->emailRepository->moveToSpam($emailIds, $companyId);

            if ($count === 0) {
                return [
                    'success' => false,
                    'message' => 'No emails found or unauthorized',
                ];
            }

            return [
                'success' => true,
                'message' => "{$count} email(s) moved to spam",
            ];
        } catch (\Exception $e) {
            Log::error('Failed to move emails to spam', [
                'email_ids' => $emailIds,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to move emails to spam',
            ];
        }
    }

    /**
     * Mark emails as not spam (move from spam to inbox)
     */
    public function markAsNotSpam(array $emailIds, int $companyId): array
    {
        try {
            if (empty($emailIds)) {
                return [
                    'success' => false,
                    'message' => 'No emails selected',
                ];
            }

            $count = $this->emailRepository->markAsNotSpam($emailIds, $companyId);

            if ($count === 0) {
                return [
                    'success' => false,
                    'message' => 'No emails found or unauthorized',
                ];
            }

            return [
                'success' => true,
                'message' => "{$count} email(s) moved to inbox",
            ];
        } catch (\Exception $e) {
            Log::error('Failed to mark emails as not spam', [
                'email_ids' => $emailIds,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to mark emails as not spam',
            ];
        }
    }

    /**
     * Delete emails (move to trash)
     */
    public function deleteEmails(array $emailIds, int $companyId): array
    {
        try {
            if (empty($emailIds)) {
                return [
                    'success' => false,
                    'message' => 'No emails selected',
                ];
            }

            // Separate draft IDs from regular email IDs
            $draftIds = [];
            $regularEmailIds = [];

            foreach ($emailIds as $id) {
                if (is_string($id) && str_starts_with($id, 'draft-')) {
                    // Extract numeric draft ID
                    $draftIds[] = (int) substr($id, 6);
                } else {
                    $regularEmailIds[] = $id;
                }
            }

            $deletedCount = 0;
            $messages = [];

            // Soft delete drafts (move to trash)
            if (! empty($draftIds)) {
                $user = auth()->user();
                $deletedDrafts = EmailDraft::whereIn('id', $draftIds)
                    ->where('user_id', $user->id)
                    ->where('is_deleted', false)
                    ->update([
                        'is_deleted' => true,
                        'deleted_at' => now(),
                    ]);

                $deletedCount += $deletedDrafts;
                if ($deletedDrafts > 0) {
                    $messages[] = "{$deletedDrafts} draft(s) deleted";
                }
            }

            // Delete regular emails
            if (! empty($regularEmailIds)) {
                $deletedEmails = $this->emailRepository->deleteEmails($regularEmailIds, $companyId);
                $deletedCount += $deletedEmails;
                if ($deletedEmails > 0) {
                    $messages[] = "{$deletedEmails} email(s) moved to trash";
                }
            }

            $count = $deletedCount;

            if ($count === 0) {
                return [
                    'success' => false,
                    'message' => 'No emails found or unauthorized',
                ];
            }

            // Combine messages for user feedback
            $message = ! empty($messages) ? implode(' and ', $messages) : "{$count} item(s) deleted";

            return [
                'success' => true,
                'message' => $message,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to delete emails', [
                'email_ids' => $emailIds,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete emails',
            ];
        }
    }

    /**
     * Restore emails from trash
     */
    public function restoreEmails(array $emailIds, int $companyId): array
    {
        try {
            if (empty($emailIds)) {
                return [
                    'success' => false,
                    'message' => 'No emails selected',
                ];
            }

            // Separate draft IDs from regular email IDs
            $draftIds = [];
            $regularEmailIds = [];

            foreach ($emailIds as $id) {
                if (is_string($id) && str_starts_with($id, 'draft-')) {
                    // Extract numeric draft ID
                    $draftIds[] = (int) substr($id, 6);
                } else {
                    $regularEmailIds[] = $id;
                }
            }

            $restoredCount = 0;
            $messages = [];

            // Restore drafts
            if (! empty($draftIds)) {
                $user = auth()->user();
                $restoredDrafts = EmailDraft::whereIn('id', $draftIds)
                    ->where('user_id', $user->id)
                    ->where('is_deleted', true)
                    ->update([
                        'is_deleted' => false,
                        'deleted_at' => null,
                    ]);

                $restoredCount += $restoredDrafts;
                if ($restoredDrafts > 0) {
                    $messages[] = "{$restoredDrafts} draft(s) restored";
                }
            }

            // Restore regular emails
            if (! empty($regularEmailIds)) {
                $restoredEmails = $this->emailRepository->restoreEmails($regularEmailIds, $companyId);
                $restoredCount += $restoredEmails;
                if ($restoredEmails > 0) {
                    $messages[] = "{$restoredEmails} email(s) restored";
                }
            }

            $count = $restoredCount;

            if ($count === 0) {
                return [
                    'success' => false,
                    'message' => 'No emails found or unauthorized',
                ];
            }

            return [
                'success' => true,
                'message' => implode(', ', $messages) ?: "{$count} item(s) restored",
            ];
        } catch (\Exception $e) {
            Log::error('Failed to restore emails', [
                'email_ids' => $emailIds,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to restore emails',
            ];
        }
    }

    /**
     * Permanently delete emails (hard delete from database)
     */
    public function permanentDelete(array $emailIds, int $companyId): array
    {
        try {
            if (empty($emailIds)) {
                return [
                    'success' => false,
                    'message' => 'No emails selected',
                ];
            }

            // Separate draft IDs from regular email IDs
            $draftIds = [];
            $regularEmailIds = [];

            foreach ($emailIds as $id) {
                if (is_string($id) && str_starts_with($id, 'draft-')) {
                    // Extract numeric draft ID
                    $draftIds[] = (int) substr($id, 6);
                } else {
                    $regularEmailIds[] = $id;
                }
            }

            $deletedCount = 0;
            $messages = [];

            // Permanently delete drafts
            if (! empty($draftIds)) {
                $user = auth()->user();
                $deletedDrafts = EmailDraft::whereIn('id', $draftIds)
                    ->where('user_id', $user->id)
                    ->forceDelete();

                $deletedCount += $deletedDrafts;
                if ($deletedDrafts > 0) {
                    $messages[] = "{$deletedDrafts} draft(s) permanently deleted";
                }
            }

            // Permanently delete regular emails
            if (! empty($regularEmailIds)) {
                $deletedEmails = $this->emailRepository->permanentDelete($regularEmailIds, $companyId);
                $deletedCount += $deletedEmails;
                if ($deletedEmails > 0) {
                    $messages[] = "{$deletedEmails} email(s) permanently deleted";
                }
            }

            $count = $deletedCount;

            if ($count === 0) {
                return [
                    'success' => false,
                    'message' => 'No emails found or unauthorized',
                ];
            }

            // Combine messages for user feedback
            $message = ! empty($messages) ? implode(' and ', $messages) : "{$count} item(s) permanently deleted";

            return [
                'success' => true,
                'message' => $message,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to permanently delete emails', [
                'email_ids' => $emailIds,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to permanently delete emails',
            ];
        }
    }

    /**
     * Toggle email star status
     */
    public function toggleStar(int $emailId, int $companyId): array
    {
        try {
            $email = $this->emailRepository->toggleStar($emailId, $companyId);

            if (! $email) {
                return [
                    'success' => false,
                    'message' => 'Email not found',
                ];
            }

            return [
                'success' => true,
                'message' => $email->is_starred ? 'Email starred' : 'Email unstarred',
                'isStarred' => $email->is_starred,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to toggle star status', [
                'email_id' => $emailId,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to toggle star status',
            ];
        }
    }

    /**
     * Toggle email read status
     */
    public function toggleRead(int $emailId, int $companyId): array
    {
        try {
            $email = $this->emailRepository->toggleRead($emailId, $companyId);

            if (! $email) {
                return [
                    'success' => false,
                    'message' => 'Email not found',
                ];
            }

            return [
                'success' => true,
                'message' => $email->is_read ? 'Email marked as read' : 'Email marked as unread',
                'isRead' => $email->is_read,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to toggle read status', [
                'email_id' => $emailId,
                'company_id' => $companyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to toggle read status',
            ];
        }
    }

    /**
     * Get email details with full content
     */
    public function getEmailDetails(int $emailId, int $companyId): ?array
    {
        $email = $this->emailRepository->getEmailWithContent($emailId, $companyId);

        if (! $email) {
            return null;
        }

        return [
            'id' => $email->id,
            'subject' => $email->subject,
            'from_email' => $email->from_email,
            'sender_name' => $email->sender_name,
            'to_recipients' => $email->to_recipients,
            'cc_recipients' => $email->cc_recipients,
            'bcc_recipients' => $email->bcc_recipients,
            'body_html' => $email->body_html,
            'body_plain' => $email->body_plain,
            'body_content' => $email->body_content,
            'received_at' => $email->received_at,
            'is_read' => $email->is_read,
            'is_starred' => $email->is_starred,
            'folder' => $email->folder,
            'attachments' => $email->attachments,
        ];
    }
}
