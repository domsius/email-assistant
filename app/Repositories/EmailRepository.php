<?php

namespace App\Repositories;

use App\DTOs\FolderCountsDTO;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class EmailRepository
{
    public function __construct(
        private EmailMessage $model
    ) {}

    /**
     * Get paginated emails for a company with optional filters
     */
    public function getPaginatedEmails(
        int $companyId,
        ?int $accountId = null,
        string $folder = 'inbox',
        ?string $search = null,
        int $perPage = 100,
        ?string $cursor = null,
        string $filter = 'all'
    ): LengthAwarePaginator {
        $query = $this->buildEmailQuery($companyId, $accountId, $folder);

        // Apply read/unread filter
        if ($filter === 'unread') {
            $query->where('is_read', false);
        }

        if ($search) {
            $query->where(function (Builder $q) use ($search) {
                $searchTerm = '%'.$search.'%';
                $q->where('subject', 'like', $searchTerm)
                    ->orWhere('from_email', 'like', $searchTerm)
                    ->orWhere('sender_name', 'like', $searchTerm)
                    ->orWhere('snippet', 'like', $searchTerm); // Changed from body_plain to snippet
            });
        }

        // Eager load relationships that are commonly used
        $query->with([
            'emailAccount:id,email_address',
            'topic:id,name',
            'attachments',
        ]);

        // Convert cursor (page number) to integer
        $page = $cursor ? (int) $cursor : null;

        // Log the query for debugging
        Log::info('Email query SQL:', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $paginator = $query->orderBy('received_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Log first email data
        if ($paginator->count() > 0) {
            $firstEmail = $paginator->first();
            Log::info('First paginated email:', [
                'id' => $firstEmail->id,
                'has_body_content' => ! empty($firstEmail->body_content),
                'has_body_html' => ! empty($firstEmail->body_html),
                'has_body_plain' => ! empty($firstEmail->body_plain),
                'body_content_length' => strlen($firstEmail->body_content ?? ''),
            ]);
        }

        // Only append necessary query parameters, excluding Inertia-specific ones
        $allowedParams = ['folder', 'filter', 'search', 'account', 'per_page'];
        $queryParams = [];

        foreach ($allowedParams as $param) {
            if (request()->has($param) && request()->get($param) !== null && request()->get($param) !== '') {
                $queryParams[$param] = request()->get($param);
            }
        }

        return $paginator->appends($queryParams);
    }

    /**
     * Get a single email with full content for viewing
     */
    public function getEmailWithContent(int $emailId, int $companyId): ?EmailMessage
    {
        return $this->model
            ->whereIn('email_account_id', function ($query) use ($companyId) {
                $query->select('id')
                    ->from('email_accounts')
                    ->where('company_id', $companyId);
            })
            ->with([
                'emailAccount:id,email_address',
                'topic:id,name',
                'attachments',
            ])
            ->find($emailId);
    }

    /**
     * Get folder counts optimized with single query
     */
    public function getFolderCounts(int $companyId, ?int $accountId = null): FolderCountsDTO
    {
        $accountIds = $this->getAccountIds($companyId, $accountId);

        // Get counts for non-deleted emails
        $counts = $this->model
            ->whereIn('email_account_id', $accountIds)
            ->selectRaw('
                COUNT(CASE WHEN folder = ? AND is_archived = 0 AND is_deleted = 0 THEN 1 END) as inbox,
                COUNT(CASE WHEN folder = ? AND is_archived = 0 AND is_deleted = 0 THEN 1 END) as drafts,
                COUNT(CASE WHEN folder = ? AND is_archived = 0 AND is_deleted = 0 THEN 1 END) as sent,
                COUNT(CASE WHEN folder = ? AND is_archived = 0 AND is_deleted = 0 THEN 1 END) as junk,
                COUNT(CASE WHEN is_archived = 1 AND is_deleted = 0 THEN 1 END) as archive,
                COUNT(CASE WHEN is_read = 0 AND is_deleted = 0 AND is_archived = 0 THEN 1 END) as unread,
                COUNT(CASE WHEN is_deleted = 0 AND is_archived = 0 AND (is_read = 1 OR folder != ?) THEN 1 END) as everything
            ', ['INBOX', 'DRAFTS', 'SENT', 'SPAM', 'INBOX'])
            ->first();

        // Get trash count (need to include soft-deleted records)
        $trashCount = $this->model
            ->withTrashed()
            ->whereIn('email_account_id', $accountIds)
            ->where('is_deleted', true)
            ->count();

        // Get real drafts count from EmailDraft model
        $user = auth()->user();
        $draftsCount = \App\Models\EmailDraft::where('user_id', $user->id)
            ->where('is_deleted', false)
            ->when($accountId, function ($query) use ($accountId) {
                return $query->where('email_account_id', $accountId);
            })
            ->when(! $accountId, function ($query) use ($accountIds) {
                return $query->whereIn('email_account_id', $accountIds);
            })
            ->count();

        return new FolderCountsDTO(
            inbox: $counts->inbox ?? 0,
            drafts: $draftsCount,
            sent: $counts->sent ?? 0,
            junk: $counts->junk ?? 0,
            trash: $trashCount,
            archive: $counts->archive ?? 0,
            unread: $counts->unread ?? 0,
            everything: $counts->everything ?? 0,
        );
    }

    /**
     * Archive emails
     */
    public function archiveEmails(array $emailIds, int $companyId): int
    {
        return $this->updateEmailsWithCompanyCheck($emailIds, $companyId, [
            'is_archived' => true,
            'archived_at' => now(),
        ]);
    }

    /**
     * Unarchive emails
     */
    public function unarchiveEmails(array $emailIds, int $companyId): int
    {
        return $this->updateEmailsWithCompanyCheck($emailIds, $companyId, [
            'is_archived' => false,
            'archived_at' => null,
        ]);
    }

    /**
     * Move emails to spam
     */
    public function moveToSpam(array $emailIds, int $companyId): int
    {
        return $this->updateEmailsWithCompanyCheck($emailIds, $companyId, [
            'folder' => 'SPAM',
            'is_archived' => false,
            'is_deleted' => false,
        ]);
    }

    /**
     * Mark emails as not spam (move from spam to inbox)
     */
    public function markAsNotSpam(array $emailIds, int $companyId): int
    {
        return $this->updateEmailsWithCompanyCheck($emailIds, $companyId, [
            'folder' => 'INBOX',
            'is_archived' => false,
            'is_deleted' => false,
        ]);
    }

    /**
     * Move emails to trash
     */
    public function deleteEmails(array $emailIds, int $companyId): int
    {
        // Sanitize email IDs
        $emailIds = array_map('intval', $emailIds);

        // First update the is_deleted flag
        $updated = $this->model
            ->whereIn('id', $emailIds)
            ->whereHas('emailAccount', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->update([
                'is_deleted' => true,
            ]);

        // Then soft delete the records
        $this->model
            ->whereIn('id', $emailIds)
            ->whereHas('emailAccount', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->delete();

        return $updated;
    }

    /**
     * Restore emails from trash
     */
    public function restoreEmails(array $emailIds, int $companyId): int
    {
        // Sanitize email IDs
        $emailIds = array_map('intval', $emailIds);

        // First restore the soft-deleted records
        $restored = $this->model
            ->withTrashed()
            ->whereIn('id', $emailIds)
            ->whereHas('emailAccount', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->restore();

        // Then update the is_deleted flag
        $this->model
            ->whereIn('id', $emailIds)
            ->whereHas('emailAccount', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->update([
                'is_deleted' => false,
            ]);

        return $restored;
    }

    /**
     * Permanently delete emails (hard delete from database)
     */
    public function permanentDelete(array $emailIds, int $companyId): int
    {
        // Sanitize email IDs
        $emailIds = array_map('intval', $emailIds);

        // Permanently delete records from database
        return $this->model
            ->withTrashed()
            ->whereIn('id', $emailIds)
            ->whereHas('emailAccount', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->forceDelete();
    }

    /**
     * Toggle star status
     */
    public function toggleStar(int $emailId, int $companyId): ?EmailMessage
    {
        $email = $this->findEmailForCompany($emailId, $companyId);

        if ($email) {
            $email->is_starred = ! $email->is_starred;
            $email->save();
        }

        return $email;
    }

    /**
     * Toggle read status
     */
    public function toggleRead(int $emailId, int $companyId): ?EmailMessage
    {
        $email = $this->findEmailForCompany($emailId, $companyId);

        if ($email) {
            $email->is_read = ! $email->is_read;
            $email->save();
        }

        return $email;
    }

    /**
     * Find a single email for a company
     */
    public function findEmailForCompany(int $emailId, int $companyId): ?EmailMessage
    {
        return $this->model
            ->where('id', $emailId)
            ->whereHas('emailAccount', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->first();
    }

    /**
     * Build base query for emails
     */
    private function buildEmailQuery(int $companyId, ?int $accountId, string $folder): Builder
    {
        $accountIds = $this->getAccountIds($companyId, $accountId);

        $query = $this->model->whereIn('email_account_id', $accountIds);

        switch ($folder) {
            case 'unread':
                // Show all unread emails from all folders except trash
                $query->where('is_read', false)
                    ->where('is_deleted', false)
                    ->where('is_archived', false);
                break;
            case 'everything':
                // Show all emails except inbox, unread, and trash
                $query->where('is_deleted', false)
                    ->where('is_archived', false)
                    ->where(function($q) {
                        $q->where('is_read', true)
                          ->orWhere('folder', '!=', 'INBOX');
                    });
                break;
            case 'archive':
                $query->where('is_archived', true)
                    ->where('is_deleted', false);
                break;
            case 'trash':
                $query->withTrashed()
                    ->where('is_deleted', true);
                break;
            case 'sent':
                $query->where('folder', 'SENT')
                    ->where('is_archived', false)
                    ->where('is_deleted', false);
                break;
            case 'drafts':
                $query->where('folder', 'DRAFTS')
                    ->where('is_archived', false)
                    ->where('is_deleted', false);
                break;
            case 'junk':
            case 'spam':
                $query->where('folder', 'SPAM')
                    ->where('is_archived', false)
                    ->where('is_deleted', false);
                break;
            case 'inbox':
            default:
                $query->where('folder', 'INBOX')
                    ->where('is_archived', false)
                    ->where('is_deleted', false);
                break;
        }

        return $query;
    }

    /**
     * Get account IDs for a company
     * ALL users (including admin) only see their own email accounts
     */
    private function getAccountIds(int $companyId, ?int $accountId): array
    {
        $user = auth()->user();
        
        if ($accountId) {
            // Verify the account belongs to the company and user has access
            $query = EmailAccount::where('id', $accountId)
                ->where('company_id', $companyId);
            
            // ALL users can only access their own accounts
            // Strict isolation - no access to unassigned accounts
            $query->where('user_id', $user->id);
            
            $exists = $query->exists();

            return $exists ? [$accountId] : [];
        }

        $query = EmailAccount::where('company_id', $companyId);
        
        // ALL users only see their own accounts
        // Strict isolation - even admins don't see other users' emails
        $query->where('user_id', $user->id);
        
        return $query->pluck('id')->toArray();
    }

    /**
     * Update emails with company authorization check
     */
    private function updateEmailsWithCompanyCheck(array $emailIds, int $companyId, array $updates): int
    {
        // Sanitize email IDs
        $emailIds = array_map('intval', $emailIds);

        return $this->model
            ->whereIn('id', $emailIds)
            ->whereHas('emailAccount', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->update($updates);
    }
}
