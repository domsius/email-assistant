<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\EmailAccount;
use App\Models\EmailMetadata;
use App\Models\Topic;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EmailProcessingService
{
    private LanguageDetectionService $languageService;

    private ?array $temporaryEmailData = null;

    public function __construct(LanguageDetectionService $languageService)
    {
        $this->languageService = $languageService;
    }

    /**
     * Process email without storing content - only metadata
     */
    public function processEmailWithoutStorage(array $emailData, EmailAccount $account): ?EmailMetadata
    {
        try {
            // Check for duplicates using message ID
            $existingEmail = EmailMetadata::where('message_id', $emailData['message_id'])
                ->where('email_account_id', $account->id)
                ->first();

            if ($existingEmail) {
                return null; // Skip duplicate
            }

            // Find or create customer
            $customer = $this->findOrCreateCustomer($emailData, $account->company_id);

            // Process email content in memory
            $processedData = $this->processEmailContent($emailData);

            // Create metadata record without storing actual content
            $metadata = EmailMetadata::create([
                'email_account_id' => $account->id,
                'customer_id' => $customer->id,
                'message_id' => $emailData['message_id'],
                'thread_id' => $emailData['thread_id'] ?? null,
                'subject_hash' => hash('sha256', $emailData['subject']),
                'content_hash' => hash('sha256', $emailData['body_content']),
                'sender_email' => $emailData['sender_email'],
                'sender_name' => $emailData['sender_name'],
                'received_at' => $emailData['received_at'],
                'detected_language' => $processedData['language']['primary_language'],
                'language_confidence' => $processedData['language']['confidence'],
                'topic_id' => $processedData['topic_id'],
                'topic_confidence' => $processedData['topic_confidence'],
                'word_count' => $processedData['word_count'],
                'sentiment_score' => $processedData['sentiment_score'],
                'urgency_score' => $processedData['urgency_score'],
                'status' => 'pending',
                'processing_timestamp' => now(),
            ]);

            return $metadata;

        } catch (\Exception $e) {
            Log::error('Email processing failed', [
                'error' => $e->getMessage(),
                'email_id' => $emailData['message_id'] ?? 'unknown',
            ]);

            return null;
        }
    }

    /**
     * Temporarily store email content in memory/cache for current operations
     */
    public function storeTemporaryEmailContent(string $messageId, array $emailData, int $ttlSeconds = 300): void
    {
        $cacheKey = "temp_email_{$messageId}";
        Cache::put($cacheKey, $emailData, $ttlSeconds);
    }

    /**
     * Retrieve temporarily stored email content
     */
    public function getTemporaryEmailContent(string $messageId): ?array
    {
        $cacheKey = "temp_email_{$messageId}";

        return Cache::get($cacheKey);
    }

    /**
     * Clear temporary email content
     */
    public function clearTemporaryEmailContent(string $messageId): void
    {
        $cacheKey = "temp_email_{$messageId}";
        Cache::forget($cacheKey);
    }

    /**
     * Process email for viewing/replying without storing it
     */
    public function processEmailForViewing(EmailMetadata $metadata, EmailAccount $account): ?array
    {
        try {
            $factory = app(EmailProviderFactory::class);
            $provider = $factory->createProvider($account);

            if (! $provider->isAuthenticated()) {
                return null;
            }

            // Fetch single email from provider
            $emails = $provider->fetchEmails(1);

            foreach ($emails as $email) {
                if ($email['message_id'] === $metadata->message_id) {
                    // Store temporarily for current request
                    $this->temporaryEmailData = $email;

                    return $email;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to fetch email for viewing', [
                'error' => $e->getMessage(),
                'metadata_id' => $metadata->id,
            ]);

            return null;
        }
    }

    /**
     * Process email content without storing it
     */
    private function processEmailContent(array $emailData): array
    {
        $textToAnalyze = $emailData['subject'].' '.$emailData['body_content'];

        // Language detection
        $languageResult = $this->languageService->detectLanguage($textToAnalyze);

        // Word count
        $wordCount = str_word_count(strip_tags($emailData['body_content']));

        // Basic sentiment analysis (placeholder - implement real analysis)
        $sentimentScore = $this->analyzeSentiment($textToAnalyze);

        // Urgency detection (placeholder - implement real analysis)
        $urgencyScore = $this->detectUrgency($textToAnalyze);

        // Topic detection (placeholder - implement real analysis)
        $topicData = $this->detectTopic($textToAnalyze);

        return [
            'language' => $languageResult,
            'word_count' => $wordCount,
            'sentiment_score' => $sentimentScore,
            'urgency_score' => $urgencyScore,
            'topic_id' => $topicData['topic_id'],
            'topic_confidence' => $topicData['confidence'],
        ];
    }

    /**
     * Find or create customer
     */
    private function findOrCreateCustomer(array $emailData, int $companyId): Customer
    {
        return Customer::firstOrCreate(
            [
                'email' => $emailData['sender_email'],
                'company_id' => $companyId,
            ],
            [
                'name' => $emailData['sender_name'],
                'first_contact_at' => now(),
                'journey_stage' => 'initial',
            ]
        );
    }

    /**
     * Basic sentiment analysis (implement real analysis)
     */
    private function analyzeSentiment(string $text): float
    {
        // Placeholder - implement real sentiment analysis
        // Return value between -1 (negative) and 1 (positive)
        return 0.0;
    }

    /**
     * Urgency detection (implement real analysis)
     */
    private function detectUrgency(string $text): float
    {
        // Placeholder - implement real urgency detection
        // Return value between 0 (not urgent) and 1 (very urgent)
        $urgentKeywords = ['urgent', 'asap', 'immediately', 'critical', 'emergency'];
        $lowerText = strtolower($text);

        foreach ($urgentKeywords as $keyword) {
            if (str_contains($lowerText, $keyword)) {
                return 0.8;
            }
        }

        return 0.2;
    }

    /**
     * Topic detection (implement real analysis)
     */
    private function detectTopic(string $text): array
    {
        // Placeholder - implement real topic detection
        // For now, return null topic
        return [
            'topic_id' => null,
            'confidence' => null,
        ];
    }
}
