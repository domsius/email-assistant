<?php

namespace App\Jobs;

use App\Models\EmailMessage;
use App\Services\AIResponseService;
use App\Services\LanguageDetectionService;
use App\Services\TopicClassificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120; // 2 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailMessage $emailMessage
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        LanguageDetectionService $languageService,
        TopicClassificationService $topicService,
        AIResponseService $aiService
    ): void {
        Log::info('Processing incoming email', [
            'email_id' => $this->emailMessage->id,
            'subject' => $this->emailMessage->subject,
        ]);

        try {
            // Step 1: Detect language
            $emailContent = $this->emailMessage->body_content ?? $this->emailMessage->subject;
            $language = $languageService->detectLanguage($emailContent);
            $this->emailMessage->update([
                'detected_language' => $language['primary_language'],
                'language_confidence' => $language['confidence'],
            ]);

            // Step 2: Classify topic
            $topic = $topicService->classifyTopic($this->emailMessage);
            if ($topic) {
                $this->emailMessage->update([
                    'topic_id' => $topic['topic_id'],
                    'topic_confidence' => $topic['confidence'],
                ]);
            }

            // Step 3: Analyze emotion/sentiment
            $emotion = $aiService->analyzeEmotion($this->emailMessage);
            $this->emailMessage->emotionAnalysis()->create([
                'sentiment' => $emotion['sentiment'],
                'emotion' => $emotion['emotion'],
                'urgency' => $emotion['urgency'],
                'confidence' => $emotion['confidence'],
            ]);

            // Step 4: Generate AI response draft
            $user = $this->emailMessage->emailAccount->company->users()->first();
            $response = $aiService->generateResponse($this->emailMessage, $user);

            $this->emailMessage->draftResponse()->create([
                'ai_generated_content' => $response['response'],
                'confidence_score' => $response['confidence'],
                'metadata' => $response['metadata'],
                'status' => 'draft',
            ]);

            // Step 5: Check for urgency and create tasks if needed
            if ($emotion['urgency'] === 'high') {
                $this->emailMessage->tasks()->create([
                    'title' => 'Urgent: Review email from '.$this->emailMessage->sender_name,
                    'description' => 'High urgency email requires immediate attention',
                    'priority' => 'high',
                    'due_date' => now()->addHours(2),
                    'assigned_to' => $user->id ?? null,
                ]);
            }

            // Step 6: Update email status
            $this->emailMessage->update(['status' => 'processed']);

            Log::info('Email processing completed', [
                'email_id' => $this->emailMessage->id,
                'language' => $language['primary_language'],
                'topic' => $topic['topic_name'] ?? 'none',
                'sentiment' => $emotion['sentiment'],
                'urgency' => $emotion['urgency'],
            ]);

        } catch (\Exception $e) {
            Log::error('Email processing failed', [
                'email_id' => $this->emailMessage->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update status to failed
            $this->emailMessage->update(['status' => 'failed']);

            // Re-throw to let Laravel handle retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Email processing job failed permanently', [
            'email_id' => $this->emailMessage->id,
            'error' => $exception->getMessage(),
        ]);

        // Update email status to indicate permanent failure
        $this->emailMessage->update([
            'status' => 'failed',
            'error_message' => 'Processing failed: '.$exception->getMessage(),
        ]);
    }
}
