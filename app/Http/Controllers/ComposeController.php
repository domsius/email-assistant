<?php

namespace App\Http\Controllers;

use App\Models\EmailAccount;
use App\Models\EmailDraft;
use App\Models\EmailMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ComposeController extends Controller
{
    public function index(Request $request): Response
    {
        $action = $request->input('action', 'new');
        $emailId = $request->input('emailId');
        $draftId = $request->input('draftId');
        $originalEmail = null;
        $existingDraft = null;

        // If replying or forwarding, fetch the original email
        if ($emailId && in_array($action, ['reply', 'replyAll', 'forward'])) {
            $user = auth()->user();
            $originalEmail = EmailMessage::whereHas('emailAccount', function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            })->find($emailId);

            if (! $originalEmail) {
                abort(404, 'Email not found');
            }
        }

        // If loading a draft, fetch it
        if ($draftId) {
            $user = auth()->user();
            $existingDraft = EmailDraft::where('id', $draftId)
                ->where('user_id', $user->id)
                ->with(['originalEmail', 'emailAccount'])
                ->first();

            if (! $existingDraft) {
                abort(404, 'Draft not found');
            }

            // If the draft has an original email, load it
            if ($existingDraft->original_email_id) {
                $originalEmail = $existingDraft->originalEmail;
            }
        }

        // Prepare data based on action
        $data = [
            'action' => $existingDraft ? $existingDraft->action : $action,
            'to' => $existingDraft ? $existingDraft->to : '',
            'cc' => $existingDraft ? $existingDraft->cc : '',
            'bcc' => $existingDraft ? $existingDraft->bcc : '',
            'subject' => $existingDraft ? $existingDraft->subject : '',
            'body' => $existingDraft ? $existingDraft->body : '',
            'inReplyTo' => $existingDraft ? $existingDraft->in_reply_to : null,
            'references' => $existingDraft ? $existingDraft->references : null,
        ];

        if ($originalEmail) {
            switch ($action) {
                case 'reply':
                    $data['to'] = $originalEmail->from_email;
                    $data['subject'] = 'Re: '.preg_replace('/^(Re:\s*)+/i', '', $originalEmail->subject);
                    $data['inReplyTo'] = $originalEmail->message_id;
                    $data['references'] = $originalEmail->message_id;
                    $data['body'] = $this->formatReplyBody($originalEmail);
                    break;

                case 'replyAll':
                    $data['to'] = $originalEmail->from_email;
                    // TODO: Parse CC recipients from original email
                    $data['subject'] = 'Re: '.preg_replace('/^(Re:\s*)+/i', '', $originalEmail->subject);
                    $data['inReplyTo'] = $originalEmail->message_id;
                    $data['references'] = $originalEmail->message_id;
                    $data['body'] = $this->formatReplyBody($originalEmail);
                    break;

                case 'forward':
                    $data['subject'] = 'Fwd: '.preg_replace('/^(Fwd:\s*)+/i', '', $originalEmail->subject);
                    $data['body'] = $this->formatForwardBody($originalEmail);
                    break;
            }
        }

        return Inertia::render('compose', [
            'composeData' => $data,
            'originalEmail' => $originalEmail ? [
                'id' => $originalEmail->id,
                'subject' => $originalEmail->subject,
                'from' => $originalEmail->from_email,
                'date' => $originalEmail->received_at->toIso8601String(),
            ] : null,
            'draftId' => $existingDraft ? $existingDraft->id : null,
        ]);
    }

    private function formatReplyBody(EmailMessage $email): string
    {
        $date = $email->received_at->format('D, M j, Y \a\t g:i A');
        $from = $email->sender_name ?: $email->from_email;

        return "\n\n\n".
               "On {$date}, {$from} wrote:\n".
               '> '.str_replace("\n", "\n> ", strip_tags($email->body_plain ?: ''));
    }

    private function formatForwardBody(EmailMessage $email): string
    {
        $date = $email->received_at->format('D, M j, Y \a\t g:i A');
        $from = $email->sender_name ? "{$email->sender_name} <{$email->from_email}>" : $email->from_email;

        return '


'.
               '---------- Forwarded message ---------
'.
               "From: {$from}
".
               "Date: {$date}
".
               "Subject: {$email->subject}

".
               strip_tags($email->body_plain ?: '');
    }

    /**
     * Save or update a draft
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:email_drafts,id',
            'to' => 'nullable|string|max:500',
            'cc' => 'nullable|string|max:500',
            'bcc' => 'nullable|string|max:500',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'action' => 'nullable|string|in:new,reply,replyAll,forward,draft',
            'inReplyTo' => 'nullable|string',
            'references' => 'nullable|string',
            'originalEmailId' => 'nullable|integer|exists:email_messages,id',
            'emailAccountId' => 'nullable|integer|exists:email_accounts,id',
        ]);

        $user = auth()->user();

        // If emailAccountId not provided, get the user's company's default email account
        if (! isset($validated['emailAccountId'])) {
            $defaultAccount = EmailAccount::where('company_id', $user->company_id)
                ->where('is_active', true)
                ->first();
            if (! $defaultAccount) {
                return response()->json(['error' => 'No active email account found'], 422);
            }
            $validated['emailAccountId'] = $defaultAccount->id;
        }

        // Check if updating existing draft
        if (isset($validated['id'])) {
            $draft = EmailDraft::where('id', $validated['id'])
                ->where('user_id', $user->id)
                ->first();

            if (! $draft) {
                return response()->json(['error' => 'Draft not found'], 404);
            }
        } else {
            $draft = new EmailDraft;
            $draft->user_id = $user->id;
        }

        // Update draft fields
        $draft->email_account_id = $validated['emailAccountId'];
        $draft->to = $validated['to'] ?? '';
        $draft->cc = $validated['cc'] ?? '';
        $draft->bcc = $validated['bcc'] ?? '';
        $draft->subject = $validated['subject'] ?? '';
        $draft->body = $validated['body'] ?? '';
        $draft->action = $validated['action'] ?? 'new';
        $draft->in_reply_to = $validated['inReplyTo'] ?? null;
        $draft->references = $validated['references'] ?? null;
        $draft->original_email_id = $validated['originalEmailId'] ?? null;
        $draft->last_saved_at = now();

        $draft->save();

        return response()->json([
            'id' => $draft->id,
            'message' => 'Draft saved successfully',
            'lastSaved' => $draft->last_saved_at->toIso8601String(),
        ]);
    }

    /**
     * Get a draft by ID
     */
    public function getDraft(int $id): JsonResponse
    {
        $user = auth()->user();

        $draft = EmailDraft::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['originalEmail', 'emailAccount'])
            ->first();

        if (! $draft) {
            return response()->json(['error' => 'Draft not found'], 404);
        }

        return response()->json([
            'draft' => [
                'id' => $draft->id,
                'to' => $draft->to,
                'cc' => $draft->cc,
                'bcc' => $draft->bcc,
                'subject' => $draft->subject,
                'body' => $draft->body,
                'action' => $draft->action,
                'inReplyTo' => $draft->in_reply_to,
                'references' => $draft->references,
                'originalEmailId' => $draft->original_email_id,
                'emailAccountId' => $draft->email_account_id,
                'lastSaved' => $draft->last_saved_at?->toIso8601String(),
                'createdAt' => $draft->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Delete a draft
     */
    public function deleteDraft(int $id): JsonResponse
    {
        $user = auth()->user();

        $draft = EmailDraft::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $draft) {
            return response()->json(['error' => 'Draft not found'], 404);
        }

        $draft->delete();

        return response()->json(['message' => 'Draft deleted successfully']);
    }

    /**
     * Send an email
     */
    public function send(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'to' => 'required|string|max:500',
            'cc' => 'nullable|string|max:500',
            'bcc' => 'nullable|string|max:500',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'emailAccountId' => 'required|integer|exists:email_accounts,id',
            'draftId' => 'nullable|integer|exists:email_drafts,id',
            'inReplyTo' => 'nullable|string',
            'references' => 'nullable|string',
            'originalEmailId' => 'nullable|integer|exists:email_messages,id',
        ]);

        $user = auth()->user();

        // Verify the user owns the email account
        $emailAccount = EmailAccount::where('id', $validated['emailAccountId'])
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->first();

        if (! $emailAccount) {
            return back()->withErrors(['emailAccountId' => 'Email account not found or inactive']);
        }

        // Validate email addresses
        $toEmails = $this->parseEmailAddresses($validated['to']);
        $ccEmails = $validated['cc'] ? $this->parseEmailAddresses($validated['cc']) : [];
        $bccEmails = $validated['bcc'] ? $this->parseEmailAddresses($validated['bcc']) : [];

        if (empty($toEmails)) {
            return back()->withErrors(['to' => 'At least one valid recipient email address is required']);
        }

        // Prepare email data for the job
        $emailData = [
            'to' => $toEmails,
            'cc' => $ccEmails,
            'bcc' => $bccEmails,
            'subject' => $validated['subject'],
            'body' => $validated['body'],
            'inReplyTo' => $validated['inReplyTo'] ?? null,
            'references' => $validated['references'] ?? null,
            'originalEmailId' => $validated['originalEmailId'] ?? null,
        ];

        // Dispatch the send email job
        \App\Jobs\SendEmailJob::dispatch($emailAccount, $emailData, $validated['draftId'] ?? null);

        return redirect()->route('inbox', ['folder' => 'sent'])
            ->with('success', 'Email sent successfully!');
    }

    /**
     * Parse and validate email addresses
     */
    private function parseEmailAddresses(string $emails): array
    {
        $addresses = [];
        $parts = preg_split('/[,;]/', $emails);

        foreach ($parts as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $addresses[] = $email;
            }
        }

        return $addresses;
    }
}
