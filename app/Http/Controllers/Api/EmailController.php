<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AnalyzeEmailRequest;
use App\Http\Requests\Api\GenerateResponseRequest;
use App\Models\Customer;
use App\Models\EmailMessage;
use App\Services\AIResponseService;
use App\Services\LanguageDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    public function __construct(
        private LanguageDetectionService $languageDetectionService,
        private AIResponseService $aiResponseService
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
     * Display the specified email message.
     */
    public function show(EmailMessage $email): JsonResponse
    {
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
        ]);

        $email->update($validated);

        return response()->json($email->fresh());
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

        try {
            // Generate AI response
            $result = $this->aiResponseService->generateResponse($email, auth()->user());

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
}
