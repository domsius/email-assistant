<?php

use App\Http\Controllers\Api\EmailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(function () {
    // Email management routes
    Route::apiResource('emails', EmailController::class);
    Route::get('emails/status/{status}', [EmailController::class, 'byStatus']);

    // Email account management routes
    Route::get('email-accounts/providers', [App\Http\Controllers\Api\EmailAccountController::class, 'providers']);
    Route::post('email-accounts/oauth/initiate', [App\Http\Controllers\Api\EmailAccountController::class, 'initiateOAuth']);
    Route::post('email-accounts/{emailAccount}/sync', [App\Http\Controllers\Api\EmailAccountController::class, 'syncEmails']);
    Route::get('email-accounts/{emailAccount}/sync-status', [App\Http\Controllers\Api\EmailAccountController::class, 'syncStatus']);
    Route::get('email-accounts/{emailAccount}/sync-progress', [App\Http\Controllers\Api\EmailAccountController::class, 'syncProgress']);
    Route::post('email-accounts/{emailAccount}/test', [App\Http\Controllers\Api\EmailAccountController::class, 'testConnection']);
    Route::apiResource('email-accounts', App\Http\Controllers\Api\EmailAccountController::class);

    // AI Processing routes
    Route::post('emails/{email}/generate-response', [EmailController::class, 'generateResponse']);
    Route::post('emails/{email}/analyze', [EmailController::class, 'analyze']);
    
    // Attachment routes
    Route::get('emails/{email}/attachments/{attachment}', [EmailController::class, 'downloadAttachment']);
    Route::get('emails/{email}/inline/{contentId}', [EmailController::class, 'getInlineImage']);
    
    // Compose attachment routes
    Route::post('attachments/upload', [App\Http\Controllers\Api\AttachmentController::class, 'upload']);
    Route::delete('attachments/{tempId}', [App\Http\Controllers\Api\AttachmentController::class, 'remove']);

    // TODO: Add more API routes for other controllers
    // Route::apiResource('customers', CustomerController::class);
    // Route::apiResource('tasks', TaskController::class);
    // Route::apiResource('drafts', DraftController::class);
});

// OAuth callback routes (public - no auth required)
Route::get('/email-accounts/oauth/callback/{provider}', [App\Http\Controllers\Api\EmailAccountController::class, 'handleOAuthCallback']);

// Email sync routes (for inbox operations)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/emails/sync', function (Request $request) {
        // Trigger sync for selected account or all accounts
        $accountId = $request->input('accountId');

        if ($accountId) {
            $account = \App\Models\EmailAccount::where('id', $accountId)
                ->where('company_id', auth()->user()->company_id)
                ->first();

            if ($account) {
                \App\Jobs\SyncEmailAccountJob::dispatch($account);

                return response()->json(['message' => 'Sync initiated for '.$account->email_address]);
            }
        } else {
            // Sync all accounts
            $accounts = \App\Models\EmailAccount::where('company_id', auth()->user()->company_id)
                ->where('is_active', true)
                ->get();

            foreach ($accounts as $account) {
                \App\Jobs\SyncEmailAccountJob::dispatch($account);
            }

            return response()->json(['message' => 'Sync initiated for all accounts']);
        }

        return response()->json(['message' => 'No accounts to sync'], 404);
    });

    Route::post('/emails/archive', function (Request $request) {
        try {
            $emailIds = $request->input('emailIds', []);

            if (empty($emailIds)) {
                return response()->json(['message' => 'No emails selected'], 400);
            }

            $updatedCount = \App\Models\EmailMessage::whereIn('id', $emailIds)
                ->whereHas('emailAccount', function ($query) {
                    $query->where('company_id', auth()->user()->company_id);
                })
                ->update([
                    'is_archived' => true,
                    'archived_at' => now(),
                ]);

            if ($updatedCount === 0) {
                return response()->json(['message' => 'No emails found or unauthorized'], 404);
            }

            return response()->json([
                'message' => $updatedCount.' email(s) archived successfully',
                'count' => $updatedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to archive emails'], 500);
        }
    });

    Route::post('/emails/delete', function (Request $request) {
        try {
            $emailIds = $request->input('emailIds', []);

            if (empty($emailIds)) {
                return response()->json(['message' => 'No emails selected'], 400);
            }

            $deletedCount = \App\Models\EmailMessage::whereIn('id', $emailIds)
                ->whereHas('emailAccount', function ($query) {
                    $query->where('company_id', auth()->user()->company_id);
                })
                ->delete(); // This will soft delete due to SoftDeletes trait

            if ($deletedCount === 0) {
                return response()->json(['message' => 'No emails found or unauthorized'], 404);
            }

            return response()->json([
                'message' => $deletedCount.' email(s) deleted successfully',
                'count' => $deletedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete emails'], 500);
        }
    });

    Route::post('/emails/{emailId}/toggle-read', function ($emailId) {
        try {
            $email = \App\Models\EmailMessage::where('id', $emailId)
                ->where('email_account_id', function ($query) {
                    $query->select('id')
                        ->from('email_accounts')
                        ->where('company_id', auth()->user()->company_id);
                })
                ->firstOrFail();

            $email->is_read = ! $email->is_read;
            $email->save();

            return response()->json([
                'message' => $email->is_read ? 'Email marked as read' : 'Email marked as unread',
                'is_read' => $email->is_read,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Email not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to toggle read status'], 500);
        }
    });

    Route::post('/emails/{emailId}/toggle-star', function ($emailId) {
        try {
            $email = \App\Models\EmailMessage::where('id', $emailId)
                ->where('email_account_id', function ($query) {
                    $query->select('id')
                        ->from('email_accounts')
                        ->where('company_id', auth()->user()->company_id);
                })
                ->firstOrFail();

            $email->is_starred = ! $email->is_starred;
            $email->save();

            return response()->json([
                'message' => $email->is_starred ? 'Email starred' : 'Email unstarred',
                'is_starred' => $email->is_starred,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Email not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to toggle star status'], 500);
        }
    });
});
