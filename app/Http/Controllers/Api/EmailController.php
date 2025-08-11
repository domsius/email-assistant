<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AnalyzeEmailRequest;
use App\Http\Requests\Api\GenerateResponseRequest;
use App\Models\Customer;
use App\Models\EmailAttachment;
use App\Models\EmailDraft;
use App\Models\EmailMessage;
use App\Services\AIResponseService;
use App\Services\AttachmentStorageService;
use App\Services\LanguageDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EmailController extends Controller
{
    public function __construct(
        private LanguageDetectionService $languageDetectionService,
        private AIResponseService $aiResponseService,
        private AttachmentStorageService $attachmentStorage
    ) {}

    /**
     * Display a listing of email messages.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = auth()->user()->company_id;

        $emails = EmailMessage::whereHas('emailAccount', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
            ->with(['customer', 'topic', 'emotionAnalysis', 'draftResponse'])
            ->orderBy('received_at', 'desc')
            ->paginate(20);

        return response()->json($emails);
    }

    /**
     * Store a newly created email message.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_account_id' => 'required|exists:email_accounts,id',
            'sender_email' => 'required|email',
            'sender_name' => 'nullable|string',
            'subject' => 'required|string|max:500',
            'body_content' => 'required|string',
            'message_id' => 'nullable|string',
            'thread_id' => 'nullable|string',
        ]);

        // Detect language
        $languageDetection = $this->languageDetectionService->detectLanguage($validated['body_content']);

        // Find or create customer
        $customer = Customer::firstOrCreate(
            [
                'email' => $validated['sender_email'],
                'company_id' => auth()->user()->company_id,
            ],
            [
                'name' => $validated['sender_name'],
                'preferred_language' => $languageDetection['primary_language'],
                'first_contact_at' => now(),
                'journey_stage' => 'initial',
            ]
        );

        // Create email message
        $email = EmailMessage::create([
            ...$validated,
            'customer_id' => $customer->id,
            'detected_language' => $languageDetection['primary_language'],
            'language_confidence' => $languageDetection['confidence'],
            'received_at' => now(),
            'status' => 'pending',
        ]);

        // Update customer interaction
        $customer->updateLastInteraction();

        return response()->json($email->load(['customer', 'topic']), 201);
    }

    /**
     * Display the specified email message or draft.
     */
    public function show($id): JsonResponse
    {
        // Check if this is a draft ID
        if (is_string($id) && str_starts_with($id, 'draft-')) {
            return $this->showDraft((int) substr($id, 6));
        }

        // Handle regular email
        $email = EmailMessage::findOrFail($id);

        // Ensure user can access this email (same company)
        if ($email->emailAccount->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        // Load full content for single email view
        $email->load([
            'customer',
            'topic',
            'emotionAnalysis',
            'draftResponse',
            'tasks',
        ]);

        // Return DTO format with full content
        $dto = \App\DTOs\EmailDTO::fromModel($email);

        return response()->json($dto->toArray());
    }

    /**
     * Display the specified draft with original email data.
     */
    private function showDraft(int $draftId): JsonResponse
    {
        $user = auth()->user();

        $draft = EmailDraft::where('id', $draftId)
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->with(['emailAccount', 'originalEmail'])
            ->first();

        if (! $draft) {
            abort(404, 'Draft not found');
        }

        // Ensure user can access this draft (check email account company)
        if ($draft->emailAccount->company_id !== $user->company_id) {
            abort(403);
        }

        // Build response with draft data and original email
        $response = [
            'id' => 'draft-'.$draft->id,
            'subject' => $draft->subject,
            'to' => $draft->to,
            'cc' => $draft->cc,
            'bcc' => $draft->bcc,
            'body_html' => $draft->body,
            'body_plain' => strip_tags($draft->body),
            'body_content' => $draft->body,
            'recipients' => $draft->to ? explode(',', $draft->to) : [],
            'cc_recipients' => $draft->cc ? explode(',', $draft->cc) : [],
            'bcc_recipients' => $draft->bcc ? explode(',', $draft->bcc) : [],
            'action' => $draft->action,
            'isDraft' => true,
            'draftId' => $draft->id,
            'originalEmail' => null,
        ];

        // Include original email data if this is a reply/forward
        if ($draft->originalEmail) {
            $originalEmail = $draft->originalEmail;
            $originalEmailDto = \App\DTOs\EmailDTO::fromModel($originalEmail);
            $response['originalEmail'] = $originalEmailDto->toArray();
        }

        return response()->json($response);
    }

    /**
     * Update the specified email message.
     */
    public function update(Request $request, EmailMessage $email): JsonResponse
    {
        // Ensure user can access this email (same company)
        if ($email->emailAccount->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'in:pending,processed,ignored',
            'topic_id' => 'nullable|exists:topics,id',
            'is_read' => 'nullable|boolean',
        ]);

        $email->update($validated);

        return response()->json($email->fresh());
    }
    
    /**
     * Mark an email as read
     */
    public function markAsRead(EmailMessage $email): JsonResponse
    {
        // Ensure user can access this email (same company)
        if ($email->emailAccount->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $email->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Email marked as read',
            'email_id' => $email->id,
        ]);
    }
    
    /**
     * Mark an email as unread
     */
    public function markAsUnread(EmailMessage $email): JsonResponse
    {
        // Ensure user can access this email (same company)
        if ($email->emailAccount->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $email->update(['is_read' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Email marked as unread',
            'email_id' => $email->id,
        ]);
    }

    /**
     * Get emails by status
     */
    public function byStatus(string $status): JsonResponse
    {
        $companyId = auth()->user()->company_id;

        $emails = EmailMessage::whereHas('emailAccount', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
            ->where('status', $status)
            ->with(['customer', 'topic', 'emotionAnalysis'])
            ->orderBy('received_at', 'desc')
            ->paginate(20);

        return response()->json($emails);
    }

    /**
     * Generate AI response for email
     */
    public function generateResponse(GenerateResponseRequest $request, EmailMessage $email): JsonResponse
    {
        $validated = $request->validated();
        $user = Auth::user();

        try {
            // Generate AI response
            $result = $this->aiResponseService->generateResponse($email, $user);

            // For now, return the response directly without saving as draft
            return response()->json([
                'response' => $result['response'],
                'confidence' => $result['confidence'],
                'metadata' => $result['metadata'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'meta' => [
                    'status' => 'error',
                    'message' => 'Failed to generate response: '.$e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Generate partial AI text at cursor position
     */
    public function generatePartial(Request $request, EmailMessage $email): JsonResponse
    {
        $validated = $request->validate([
            'context' => 'required|string|max:5000',
            'tone' => 'nullable|string',
            'style' => 'nullable|string',
        ]);

        $user = Auth::user();

        try {
            // Generate partial text based on context
            $result = $this->aiResponseService->generatePartialText(
                $email,
                $validated['context'],
                $user,
                $validated['tone'] ?? 'professional',
                $validated['style'] ?? 'conversational'
            );

            return response()->json([
                'text' => $result['text'],
                'confidence' => $result['confidence'] ?? 0.8,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'meta' => [
                    'status' => 'error',
                    'message' => 'Failed to generate text: '.$e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Generate AI text for new compose (no email context)
     */
    public function generateText(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'context' => 'required|string|max:5000',
            'subject' => 'nullable|string|max:255',
            'recipient' => 'nullable|string|max:255',
            'tone' => 'nullable|string',
            'style' => 'nullable|string',
        ]);

        $user = Auth::user();

        try {
            // Generate text based on context only
            $result = $this->aiResponseService->generateTextFromContext(
                $validated['context'],
                $validated['subject'] ?? null,
                $validated['recipient'] ?? null,
                $user,
                $validated['tone'] ?? 'professional',
                $validated['style'] ?? 'conversational'
            );

            return response()->json([
                'text' => $result['text'],
                'confidence' => $result['confidence'] ?? 0.8,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'meta' => [
                    'status' => 'error',
                    'message' => 'Failed to generate text: '.$e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Analyze email for various attributes
     */
    public function analyze(AnalyzeEmailRequest $request, EmailMessage $email): JsonResponse
    {
        $validated = $request->validated();
        $analysisTypes = $validated['analysis_types'] ?? ['sentiment', 'urgency', 'topic'];

        try {
            $results = [];

            if (in_array('sentiment', $analysisTypes) || in_array('urgency', $analysisTypes)) {
                $emotionAnalysis = $this->aiResponseService->analyzeEmotion($email);

                if (in_array('sentiment', $analysisTypes)) {
                    $results['sentiment'] = [
                        'value' => $emotionAnalysis['sentiment'],
                        'emotion' => $emotionAnalysis['emotion'],
                        'confidence' => $emotionAnalysis['confidence'],
                    ];
                }

                if (in_array('urgency', $analysisTypes)) {
                    $results['urgency'] = [
                        'level' => $emotionAnalysis['urgency'],
                        'confidence' => $emotionAnalysis['confidence'],
                    ];
                }

                // Store emotion analysis
                $email->emotionAnalysis()->updateOrCreate(
                    ['email_message_id' => $email->id],
                    [
                        'sentiment' => $emotionAnalysis['sentiment'],
                        'emotion' => $emotionAnalysis['emotion'],
                        'urgency' => $emotionAnalysis['urgency'],
                        'confidence' => $emotionAnalysis['confidence'],
                    ]
                );
            }

            if (in_array('language', $analysisTypes)) {
                $languageResult = $this->languageDetectionService->detectLanguage($email->body_content);
                $results['language'] = $languageResult;

                // Update language if changed
                if ($email->detected_language !== $languageResult['primary_language']) {
                    $email->update([
                        'detected_language' => $languageResult['primary_language'],
                        'language_confidence' => $languageResult['confidence'],
                    ]);
                }
            }

            return response()->json([
                'data' => $results,
                'meta' => [
                    'status' => 'success',
                    'email_id' => $email->id,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'meta' => [
                    'status' => 'error',
                    'message' => 'Analysis failed: '.$e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Download an email attachment
     */
    public function downloadAttachment($email, $attachment)
    {
        // Resolve models if IDs are passed
        if (!($email instanceof EmailMessage)) {
            $email = EmailMessage::findOrFail($email);
        }
        if (!($attachment instanceof EmailAttachment)) {
            $attachment = EmailAttachment::findOrFail($attachment);
        }
        
        Log::info('Download attachment requested', [
            'email_id' => $email->id,
            'attachment_id' => $attachment->id,
            'user_id' => auth()->id(),
            'user_company' => auth()->user() ? auth()->user()->company_id : 'not-authenticated',
            'email_company' => $email->emailAccount->company_id,
        ]);
        
        // Ensure the attachment belongs to the email
        if ($attachment->email_message_id !== $email->id) {
            Log::error('Attachment does not belong to email', [
                'attachment_email_id' => $attachment->email_message_id,
                'requested_email_id' => $email->id,
            ]);
            abort(404);
        }

        // Ensure user has access to this email
        if ($email->emailAccount->company_id !== auth()->user()->company_id) {
            Log::error('User does not have access to email', [
                'user_company' => auth()->user()->company_id,
                'email_company' => $email->emailAccount->company_id,
            ]);
            abort(403);
        }

        // Check if we have a storage path
        if (! $attachment->storage_path) {
            Log::error('No storage path for attachment', ['attachment_id' => $attachment->id]);
            abort(404, 'Attachment file not found');
        }

        Log::info('Attempting to get stream', ['storage_path' => $attachment->storage_path]);
        
        // Get the file stream
        $stream = $this->attachmentStorage->getStream($attachment->storage_path);

        if (! $stream) {
            Log::error('Could not get stream for attachment', ['storage_path' => $attachment->storage_path]);
            abort(404, 'Attachment file not found');
        }

        Log::info('Streaming attachment', [
            'filename' => $attachment->filename,
            'size' => $attachment->size,
            'content_type' => $attachment->content_type,
        ]);

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
        }, 200, [
            'Content-Type' => $attachment->content_type ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$attachment->filename.'"',
            'Content-Length' => $attachment->size,
        ]);
    }

    /**
     * Get inline image by content ID
     */
    public function getInlineImage(EmailMessage $email, string $contentId)
    {
        try {
            // Load the email account relationship if not already loaded
            if (! $email->relationLoaded('emailAccount')) {
                $email->load('emailAccount');
            }

            // Ensure user has access to this email
            if ($email->emailAccount->company_id !== auth()->user()->company_id) {
                abort(403);
            }

            // Find attachment by content ID
            $attachment = $email->attachments()
                ->where('content_id', $contentId)
                ->orWhere('content_id', 'like', '%'.$contentId.'%')
                ->first();

            if (! $attachment) {
                Log::warning('Inline image attachment not found', [
                    'email_id' => $email->id,
                    'content_id' => $contentId,
                    'total_attachments' => $email->attachments()->count(),
                    'attachment_content_ids' => $email->attachments()->pluck('content_id')->toArray(),
                ]);

                // Return a placeholder image when attachment not found
                return response()->stream(function () {
                    // A simple red placeholder image
                    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAE0lEQVR42mP8/58BCiBFTAwMAJ+tCv6TvdXOAAAAAElFTkSuQmCC');
                    echo $image;
                }, 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=60',
                ]);
            }

            // Check if we have a storage path
            if (! $attachment->storage_path) {
                Log::warning('Inline image has no storage path', [
                    'email_id' => $email->id,
                    'attachment_id' => $attachment->id,
                    'content_id' => $contentId,
                    'filename' => $attachment->filename,
                ]);

                // Return a placeholder if no file stored
                return response()->stream(function () {
                    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
                    echo $image;
                }, 200, [
                    'Content-Type' => 'image/png',
                    'Cache-Control' => 'public, max-age=3600',
                ]);
            }

            // Get the file content
            $content = $this->attachmentStorage->getContent($attachment->storage_path);

            if (! $content) {
                Log::error('Failed to read inline image file', [
                    'email_id' => $email->id,
                    'attachment_id' => $attachment->id,
                    'content_id' => $contentId,
                    'storage_path' => $attachment->storage_path,
                    'disk' => config('mail.attachments.disk', 'local'),
                    'base_path' => storage_path('app'),
                    'full_path' => storage_path('app/'.$attachment->storage_path),
                ]);

                abort(404, 'Image file not found');
            }

            return response($content, 200, [
                'Content-Type' => $attachment->content_type ?? 'image/png',
                'Cache-Control' => 'public, max-age=3600',
                'Content-Length' => strlen($content),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get inline image', [
                'email_id' => $email->id,
                'content_id' => $contentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return a generic error placeholder image
            return response()->stream(function () {
                $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
                echo $image;
            }, 500, [
                'Content-Type' => 'image/png',
            ]);
        }
    }
}
