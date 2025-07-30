<?php

namespace App\Console\Commands;

use App\Models\DraftResponse;
use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Services\AIResponseService;
use App\Services\EmailProviderFactory;
use App\Services\LanguageDetectionService;
use App\Services\TopicClassificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessIncomingEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process incoming emails, analyze them, generate AI responses, and save drafts';

    protected EmailProviderFactory $providerFactory;

    protected LanguageDetectionService $languageService;

    protected TopicClassificationService $topicService;

    protected AIResponseService $aiService;

    public function __construct(
        EmailProviderFactory $providerFactory,
        LanguageDetectionService $languageService,
        TopicClassificationService $topicService,
        AIResponseService $aiService
    ) {
        parent::__construct();
        $this->providerFactory = $providerFactory;
        $this->languageService = $languageService;
        $this->topicService = $topicService;
        $this->aiService = $aiService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting email processing...');

        // Get all active email accounts
        $emailAccounts = EmailAccount::where('is_active', true)->get();

        if ($emailAccounts->isEmpty()) {
            $this->info('No active email accounts found.');

            return;
        }

        $totalProcessed = 0;

        foreach ($emailAccounts as $account) {
            $this->info("Processing emails for: {$account->email_address}");

            try {
                $processed = $this->processAccountEmails($account);
                $totalProcessed += $processed;
                $this->info("Processed {$processed} emails for {$account->email_address}");
            } catch (\Exception $e) {
                $this->error("Error processing {$account->email_address}: ".$e->getMessage());
                Log::error('Email processing error', [
                    'account_id' => $account->id,
                    'email' => $account->email_address,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $this->info("Total emails processed: {$totalProcessed}");
    }

    private function processAccountEmails(EmailAccount $account): int
    {
        $provider = $this->providerFactory->createProvider($account);

        // Fetch new emails (unread from inbox)
        $emails = $provider->fetchEmails(50);
        $processed = 0;

        foreach ($emails as $emailData) {
            try {
                // Check if we've already processed this email
                $existingMessage = EmailMessage::where('email_account_id', $account->id)
                    ->where('message_id', $emailData['message_id'])
                    ->first();

                if ($existingMessage) {
                    $this->line("Skipping already processed email: {$emailData['subject']}");

                    continue;
                }

                // Save the email message
                $emailMessage = $this->saveEmailMessage($account, $emailData);

                // Analyze the email
                $this->analyzeEmail($emailMessage);

                // Generate AI response and save as draft
                $this->generateAndSaveDraft($emailMessage, $provider);

                $processed++;

            } catch (\Exception $e) {
                $this->error('Error processing email: '.$e->getMessage());
                Log::error('Individual email processing error', [
                    'email_data' => $emailData,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }

    private function saveEmailMessage(EmailAccount $account, array $emailData): EmailMessage
    {
        // Map fields from provider response to expected format
        $fromEmail = $emailData['sender_email'] ?? $emailData['from_email'] ?? '';
        $fromName = $emailData['sender_name'] ?? $emailData['from_name'] ?? null;
        $body = $emailData['body_content'] ?? $emailData['body'] ?? '';
        $receivedAt = $emailData['received_at'] ?? $emailData['date'] ?? now();

        // Find or create customer
        $customer = $account->company->customers()->firstOrCreate(
            ['email' => $fromEmail],
            [
                'name' => $fromName ?? $fromEmail,
                'preferred_language_id' => null, // Will be set after language detection
            ]
        );

        // Create email message
        return EmailMessage::create([
            'email_account_id' => $account->id,
            'customer_id' => $customer->id,
            'message_id' => $emailData['message_id'],
            'thread_id' => $emailData['thread_id'] ?? null,
            'subject' => $emailData['subject'],
            'body_content' => $body,
            'sender_email' => $fromEmail,
            'sender_name' => $fromName,
            'received_at' => $receivedAt,
            'status' => 'pending',
        ]);
    }

    private function analyzeEmail(EmailMessage $emailMessage): void
    {
        // Detect language
        $languageResult = $this->languageService->detectLanguage($emailMessage->body_content);
        if ($languageResult) {
            $emailMessage->detected_language = $languageResult['primary_language'];
            $emailMessage->language_confidence = $languageResult['confidence'];

            // Update customer's preferred language if confidence is high
            if ($languageResult['confidence'] > 0.8 && $emailMessage->customer) {
                $language = \App\Models\Language::where('code', $languageResult['primary_language'])->first();
                if ($language) {
                    $emailMessage->customer->update(['preferred_language_id' => $language->id]);
                }
            }
        }

        // Classify topic
        $topicResult = $this->topicService->classifyTopic(
            $emailMessage->subject,
            $emailMessage->body_content
        );
        if ($topicResult) {
            $emailMessage->topic_id = $topicResult['topic_id'];
            $emailMessage->topic_confidence = $topicResult['confidence'];

            // Set priority based on topic
            $topic = \App\Models\Topic::find($topicResult['topic_id']);
            if ($topic && $topic->name === 'Urgent') {
                $emailMessage->priority = 'high';
            }
        }

        $emailMessage->save();
    }

    private function generateAndSaveDraft(EmailMessage $emailMessage, $provider): void
    {
        $responseBody = '';
        $aiResponse = [];
        $emotionAnalysis = null;

        try {
            // Generate AI response
            $aiResponse = $this->aiService->generateResponse($emailMessage);
            $responseBody = $aiResponse['response'];
        } catch (\Exception $e) {
            $this->error('AI generation failed, using template: '.$e->getMessage());
            // Fallback to template
            $responseBody = $this->generateTemplateResponse($emailMessage);
            $aiResponse = [
                'confidence' => 0.3,
                'metadata' => ['fallback' => true, 'error' => $e->getMessage()],
            ];
        }

        // Try to analyze emotion separately (don't let it fail the whole process)
        try {
            $emotionAnalysis = $this->aiService->analyzeEmotion($emailMessage);

            // Save emotion analysis
            if ($emotionAnalysis) {
                \App\Models\EmotionAnalysis::create([
                    'email_message_id' => $emailMessage->id,
                    'sentiment' => $emotionAnalysis['sentiment'],
                    'emotion' => $emotionAnalysis['emotion'],
                    'confidence_score' => $emotionAnalysis['confidence'],
                    'analysis_data' => $emotionAnalysis,
                ]);

                // Update priority based on urgency
                if (isset($emotionAnalysis['urgency']) && $emotionAnalysis['urgency'] === 'high') {
                    $emailMessage->update(['priority' => 'high']);
                }
            }
        } catch (\Exception $e) {
            $this->line('Emotion analysis failed (continuing): '.$e->getMessage());
        }

        // Generate subject line
        $subject = 'Re: '.$emailMessage->subject;

        // Save draft to email provider
        $draftId = $provider->saveDraft(
            $emailMessage->sender_email,
            $subject,
            $responseBody,
            $emailMessage->message_id,
            $emailMessage->thread_id
        );

        if ($draftId) {
            // Save draft response in database
            DraftResponse::create([
                'email_message_id' => $emailMessage->id,
                'ai_generated_content' => $responseBody,
                'response_context' => array_merge(
                    [
                        'language' => $emailMessage->detected_language,
                        'topic' => $emailMessage->topic?->name,
                        'customer_name' => $emailMessage->customer?->name,
                        'emotion' => $emotionAnalysis ?? null,
                        'thread_id' => $emailMessage->thread_id,
                        'has_conversation_context' => $aiResponse['metadata']['has_conversation_context'] ?? false,
                    ],
                    $aiResponse['metadata'] ?? []
                ),
                'status' => 'pending',
                'provider_draft_id' => $draftId,
                'ai_confidence_score' => $aiResponse['confidence'] ?? 0.7,
                'template_used' => $aiResponse['metadata']['fallback'] ?? false ? 'fallback_template' : 'ai_generated',
            ]);

            // Mark email as replied
            $emailMessage->update(['is_replied' => true]);

            $this->info("AI draft saved for: {$emailMessage->subject} (confidence: {$aiResponse['confidence']})");
        } else {
            $this->error("Failed to save draft for: {$emailMessage->subject}");
        }
    }

    private function generateTemplateResponse(EmailMessage $emailMessage): string
    {
        // Get appropriate language greeting
        $greetings = [
            'en' => 'Hello',
            'lt' => 'Sveiki',
            'ru' => 'Здравствуйте',
            'pl' => 'Dzień dobry',
            'de' => 'Guten Tag',
        ];

        $language = $emailMessage->detected_language ?? 'en';
        $greeting = $greetings[$language] ?? $greetings['en'];
        $customerName = $emailMessage->customer?->name ?? 'there';

        // Basic template response
        $templates = [
            'en' => "{$greeting} {$customerName},\n\nThank you for your email. We have received your message and will respond as soon as possible.\n\nBest regards,\nAI Secretary",
            'lt' => "{$greeting} {$customerName},\n\nDėkojame už jūsų laišką. Mes gavome jūsų žinutę ir atsakysime kaip galima greičiau.\n\nPagarbiai,\nAI Sekretorė",
            'ru' => "{$greeting} {$customerName},\n\nСпасибо за ваше письмо. Мы получили ваше сообщение и ответим как можно скорее.\n\nС уважением,\nAI Секретарь",
            'pl' => "{$greeting} {$customerName},\n\nDziękujemy za Twoją wiadomość. Otrzymaliśmy ją i odpowiemy tak szybko, jak to możliwe.\n\nZ poważaniem,\nAI Sekretarka",
            'de' => "{$greeting} {$customerName},\n\nVielen Dank für Ihre E-Mail. Wir haben Ihre Nachricht erhalten und werden so schnell wie möglich antworten.\n\nMit freundlichen Grüßen,\nAI Sekretärin",
        ];

        return $templates[$language] ?? $templates['en'];
    }
}
