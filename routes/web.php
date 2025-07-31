<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('inbox', [\App\Http\Controllers\InboxController::class, 'index'])->name('inbox');

    // Email Accounts routes
    Route::get('email-accounts', [\App\Http\Controllers\EmailAccountController::class, 'index'])->name('email-accounts');
    Route::get('email-accounts/connect/{provider}', [\App\Http\Controllers\EmailAccountController::class, 'connect'])->name('email-accounts.connect');
    Route::delete('email-accounts/{emailAccount}', [\App\Http\Controllers\EmailAccountController::class, 'remove'])->name('email-accounts.remove');
    Route::post('email-accounts/{emailAccount}/sync', [\App\Http\Controllers\EmailAccountController::class, 'sync'])->name('email-accounts.sync');

    // Debug route - REMOVE IN PRODUCTION
    Route::get('/debug/emails', function () {
        $user = auth()->user();
        $emailAccounts = \App\Models\EmailAccount::where('company_id', $user->company_id)->pluck('id');

        $allEmails = \App\Models\EmailMessage::whereIn('email_account_id', $emailAccounts)
            ->select('id', 'subject', 'is_deleted', 'deleted_at', 'is_archived', 'archived_at')
            ->get();

        $deletedEmails = \App\Models\EmailMessage::whereIn('email_account_id', $emailAccounts)
            ->where('is_deleted', true)
            ->select('id', 'subject', 'is_deleted', 'deleted_at')
            ->get();

        $trashedEmails = \App\Models\EmailMessage::whereIn('email_account_id', $emailAccounts)
            ->onlyTrashed()
            ->select('id', 'subject', 'deleted_at')
            ->get();

        return response()->json([
            'total_emails' => $allEmails->count(),
            'deleted_emails_count' => $deletedEmails->count(),
            'soft_deleted_emails_count' => $trashedEmails->count(),
            'sample_emails' => $allEmails->take(5),
            'deleted_emails' => $deletedEmails,
            'soft_deleted_emails' => $trashedEmails,
        ]);
    });

    // Debug route to check raw email content - REMOVE IN PRODUCTION
    Route::get('/debug/email-content/{emailId}', function ($emailId) {
        $email = \App\Models\EmailMessage::find($emailId);
        if (! $email) {
            return response()->json(['error' => 'Email not found']);
        }

        return response()->json([
            'raw_body_html' => $email->body_html,
            'raw_body_content' => $email->body_content,
            'has_style_tags' => str_contains($email->body_html ?? '', '<style'),
            'first_500_chars' => substr($email->body_html ?? $email->body_content ?? '', 0, 500),
        ]);
    })->middleware(['auth', 'verified']);

    // Debug route to test delete - REMOVE IN PRODUCTION
    Route::get('/debug/test-delete/{emailId}', function ($emailId) {
        $email = \App\Models\EmailMessage::find($emailId);
        if (! $email) {
            return response()->json(['error' => 'Email not found']);
        }

        $before = [
            'is_deleted' => $email->is_deleted,
            'deleted_at' => $email->deleted_at,
        ];

        $email->is_deleted = true;
        $email->deleted_at = now();
        $email->save();

        $after = [
            'is_deleted' => $email->is_deleted,
            'deleted_at' => $email->deleted_at,
        ];

        return response()->json([
            'email_id' => $email->id,
            'subject' => $email->subject,
            'before' => $before,
            'after' => $after,
            'saved' => true,
        ]);
    });

    Route::get('knowledge-base', function () {
        return Inertia::render('knowledge-base', [
            'documents' => [],
            'stats' => [
                'totalDocuments' => 0,
                'processedDocuments' => 0,
                'totalChunks' => 0,
                'totalEmbeddings' => 0,
                'storageUsed' => 0,
                'storageLimit' => 1073741824,
            ],
        ]);
    })->name('knowledge-base');

    // Email operations
    Route::post('/emails/sync', [\App\Http\Controllers\EmailOperationsController::class, 'sync'])
        ->middleware('throttle:5,1')
        ->name('emails.sync');

    Route::post('/emails/archive', [\App\Http\Controllers\EmailOperationsController::class, 'archive'])->name('emails.archive');

    Route::post('/emails/spam', [\App\Http\Controllers\EmailOperationsController::class, 'moveToSpam'])->name('emails.spam');

    Route::post('/emails/not-spam', [\App\Http\Controllers\EmailOperationsController::class, 'notSpam'])->name('emails.not-spam');

    Route::post('/emails/delete', [\App\Http\Controllers\EmailOperationsController::class, 'delete'])->name('emails.delete');

    Route::post('/emails/{emailId}/toggle-read', [\App\Http\Controllers\EmailOperationsController::class, 'toggleRead'])->name('emails.toggle-read');

    Route::post('/emails/unarchive', [\App\Http\Controllers\EmailOperationsController::class, 'unarchive'])->name('emails.unarchive');

    Route::post('/emails/restore', [\App\Http\Controllers\EmailOperationsController::class, 'restore'])->name('emails.restore');

    Route::post('/emails/permanent-delete', [\App\Http\Controllers\EmailOperationsController::class, 'permanentDelete'])->name('emails.permanent-delete');

    Route::post('/emails/{emailId}/toggle-star', [\App\Http\Controllers\EmailOperationsController::class, 'toggleStar'])->name('emails.toggle-star');

    Route::get('/api/emails/{emailId}', [\App\Http\Controllers\EmailOperationsController::class, 'show'])->name('emails.show');
    
    // Inline image route (for authenticated session access)
    Route::get('/emails/{email}/inline/{contentId}', [\App\Http\Controllers\Api\EmailController::class, 'getInlineImage'])->name('emails.inline-image');
    
    // Attachment download route (for authenticated session access)
    Route::get('/emails/{email}/attachments/{attachment}/download', [\App\Http\Controllers\Api\EmailController::class, 'downloadAttachment'])->name('emails.attachment-download');

    // Compose route
    Route::get('compose', [\App\Http\Controllers\ComposeController::class, 'index'])->name('compose');

    // Draft routes
    Route::post('/drafts/save', [\App\Http\Controllers\ComposeController::class, 'saveDraft'])->name('drafts.save');
    Route::get('/drafts/{id}', [\App\Http\Controllers\ComposeController::class, 'getDraft'])->name('drafts.get');
    Route::delete('/drafts/{id}', [\App\Http\Controllers\ComposeController::class, 'deleteDraft'])->name('drafts.delete');
    Route::post('/emails/send', [\App\Http\Controllers\ComposeController::class, 'send'])->name('emails.send');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
