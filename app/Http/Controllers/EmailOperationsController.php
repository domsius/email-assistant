<?php

namespace App\Http\Controllers;

use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailOperationsController extends Controller
{
    public function __construct(
        private EmailService $emailService
    ) {}

    /**
     * Sync email accounts
     */
    public function sync(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $accountId = $request->input('accountId');

        $result = $this->emailService->syncEmails(
            $user->company_id,
            $accountId ? (int) $accountId : null
        );

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Archive emails
     */
    public function archive(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $emailIds = $request->input('emailIds', []);

        $result = $this->emailService->archiveEmails($emailIds, $user->company_id);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Move emails to spam/junk folder
     */
    public function moveToSpam(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $emailIds = $request->input('emailIds', []);

        $result = $this->emailService->moveToSpam($emailIds, $user->company_id);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Unarchive emails
     */
    public function unarchive(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $emailIds = $request->input('emailIds', []);

        $result = $this->emailService->unarchiveEmails($emailIds, $user->company_id);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Delete emails (move to trash)
     */
    public function delete(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $emailIds = $request->input('emailIds', []);

        $result = $this->emailService->deleteEmails($emailIds, $user->company_id);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Restore emails from trash
     */
    public function restore(Request $request): RedirectResponse
    {
        $user = auth()->user();
        $emailIds = $request->input('emailIds', []);

        $result = $this->emailService->restoreEmails($emailIds, $user->company_id);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Toggle star status
     */
    public function toggleStar(int $emailId): RedirectResponse
    {
        $user = auth()->user();

        $result = $this->emailService->toggleStar($emailId, $user->company_id);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Toggle read status
     */
    public function toggleRead(int $emailId): RedirectResponse
    {
        $user = auth()->user();

        $result = $this->emailService->toggleRead($emailId, $user->company_id);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Get email details
     */
    public function show(int $emailId): JsonResponse
    {
        $user = auth()->user();

        $email = $this->emailService->getEmailDetails($emailId, $user->company_id);

        if (! $email) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        return response()->json($email);
    }
}
