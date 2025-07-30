<?php

namespace App\Console\Commands;

use App\Models\DraftResponse;
use App\Models\EmailAccount;
use App\Models\EmailMetadata;
use App\Services\AIResponseService;
use App\Services\EmailProcessingService;
use App\Services\EmailProviderFactory;
use App\Services\LanguageDetectionService;
use App\Services\TopicClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessIncomingEmailsMetadata extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:process-metadata';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process incoming emails without storing content (GDPR compliant)';

    protected EmailProviderFactory $providerFactory;

    protected EmailProcessingService $processingService;

    protected LanguageDetectionService $languageService;

    protected TopicClassificationService $topicService;

    protected AIResponseService $aiService;

    public function __construct(
        EmailProviderFactory $providerFactory,
        EmailProcessingService $processingService,
        LanguageDetectionService $languageService,
        TopicClassificationService $topicService,
        AIResponseService $aiService
    ) {
        parent::__construct();
        $this->providerFactory = $providerFactory;
        $this->processingService = $processingService;
        $this->languageService = $languageService;
        $this->topicService = $topicService;
        $this->aiService = $aiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accounts = EmailAccount::where('is_active', true)->get();

        if ($accounts->isEmpty()) {
            $this->info('No active email accounts to process.');

            return 0;
        }

        $totalProcessed = 0;

        foreach ($accounts as $account) {
            $this->info("Processing emails for: {$account->email_address}");

            try {
                $provider = $this->providerFactory->createProvider($account);

                if (! $provider->isAuthenticated()) {
                    $this->error("Account {$account->email_address} is not authenticated.");

                    continue;
                }

                // Fetch recent emails
                $emails = $provider->fetchEmails(50);
                $processedCount = 0;

                foreach ($emails as $emailData) {
                    // Check if already processed
                    $existingMetadata = EmailMetadata::where('email_account_id', $account->id)
                        ->where('message_id', $emailData['message_id'])
                        ->first();

                    if ($existingMetadata) {
                        continue;
                    }

                    // Process email without storing content
                    $metadata = $this->processingService->processEmailWithoutStorage($emailData, $account);

                    if (! $metadata) {
                        continue;
                    }

                    // Store email temporarily for AI processing
                    $this->processingService->storeTemporaryEmailContent(
                        $emailData['message_id'],
                        $emailData,
                        600 // 10 minutes
                    );

                    // Analyze and generate response
                    $this->analyzeEmailMetadata($metadata, $emailData);
                    $this->generateAndSaveDraftMetadata($metadata, $emailData, $provider);

                    // Clear temporary storage
                    $this->processingService->clearTemporaryEmailContent($emailData['message_id']);

                    $processedCount++;
                    $this->info("Processed email metadata: {$emailData['subject']}");
                }

                $this->info("Processed {$processedCount} emails for {$account->email_address}");
                $totalProcessed += $processedCount;

            } catch (\Exception $e) {
                Log::error('Email processing failed', [
                    'account' => $account->email_address,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Error processing {$account->email_address}: ".$e->getMessage());
            }
        }

        $this->info("Total emails processed: {$totalProcessed}");

        return 0;
    }

    private function analyzeEmailMetadata(EmailMetadata $metadata, array $emailData): void
    {
        try {
            // Language detection (already done in processing service)
            Log::info('Language detected for email', [
                'language' => $metadata->detected_language,
                'confidence' => $metadata->language_confidence,
                'text_length' => strlen($emailData['subject'].' '.$emailData['body_content']),
            ]);

            // Topic classification
            $topicResult = $this->topicService->classifyTopic(
                $emailData['subject'].' '.$emailData['body_content']
            );

            if ($topicResult) {
                $metadata->update([
                    'topic_id' => $topicResult['topic_id'],
                    'topic_confidence' => $topicResult['confidence'],
                ]);

                Log::info('Topic classified for email', [
                    'topic' => $topicResult['topic'],
                    'topic_id' => $topicResult['topic_id'],
                    'confidence' => $topicResult['confidence'],
                    'keywords' => $topicResult['keywords'] ?? [],
                    'text_length' => strlen($emailData['subject'].' '.$emailData['body_content']),
                ]);
            }

            // Emotion analysis can be added here if needed

        } catch (\Exception $e) {
            Log::error('Email analysis failed', [
                'metadata_id' => $metadata->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateAndSaveDraftMetadata(EmailMetadata $metadata, array $emailData, $provider): void
    {
        try {
            // Get conversation history if needed
            $conversationHistory = [];
            if ($metadata->thread_id) {
                $threadMetadata = EmailMetadata::where('thread_id', $metadata->thread_id)
                    ->where('id', '!=', $metadata->id)
                    ->orderBy('received_at', 'asc')
                    ->get();

                // For each metadata in thread, we'd need to fetch content temporarily
                // This is a limitation of the metadata-only approach
            }

            // Generate AI response
            $aiResponse = $this->aiService->generateResponse(
                $emailData['body_content'],
                $metadata->detected_language,
                $conversationHistory
            );

            if (! $aiResponse) {
                Log::warning('AI response generation failed', ['metadata_id' => $metadata->id]);

                return;
            }

            Log::info('AI response generated', [
                'email_id' => $metadata->id,
                'model' => $aiResponse['model'] ?? 'unknown',
                'tokens' => $aiResponse['tokens'] ?? 0,
                'language' => $metadata->detected_language,
                'has_conversation_context' => ! empty($conversationHistory),
                'thread_history_count' => count($conversationHistory),
            ]);

            // Generate template response
            $templateResponse = $this->generateTemplateResponse($metadata, $emailData);

            // Format the response
            $formattedResponse = $templateResponse."\n\n".$aiResponse['content'];

            // Save draft in provider
            $draftSubject = 'Re: '.$emailData['subject'];
            $draftId = $provider->saveDraft(
                $metadata->sender_email,
                $draftSubject,
                $formattedResponse,
                $metadata->message_id,
                $metadata->thread_id
            );

            if ($draftId) {
                // Save draft reference (without content)
                DraftResponse::create([
                    'email_message_id' => $metadata->id,
                    'content' => 'Content stored in email provider only',
                    'language' => $metadata->detected_language,
                    'tone' => 'professional',
                    'ai_model' => $aiResponse['model'] ?? 'gpt-4o-mini',
                    'tokens_used' => $aiResponse['tokens'] ?? 0,
                    'provider_draft_id' => $draftId,
                    'status' => 'draft',
                ]);

                Log::info('Draft created successfully in provider', [
                    'draft_id' => $draftId,
                    'to' => $metadata->sender_email,
                    'subject' => $draftSubject,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Draft generation failed', [
                'metadata_id' => $metadata->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateTemplateResponse(EmailMetadata $metadata, array $emailData): string
    {
        $greeting = $metadata->sender_name ? "Sveiki, {$metadata->sender_name}," : 'Sveiki,';

        return $greeting."\n\nDėkojame už Jūsų laišką.";
    }
}
