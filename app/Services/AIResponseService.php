<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\EmailMessage;
use App\Models\GlobalAIPrompt;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use OpenAI;

class AIResponseService extends BaseService
{
    private $client;

    private string $model;
    
    private ?float $customTemperature = null;
    
    private ?int $customMaxTokens = null;

    public function __construct()
    {
        parent::__construct();

        $apiKey = config('services.openai.api_key');

        if (! $apiKey) {
            throw new Exception('OpenAI API key not configured');
        }

        $this->client = OpenAI::client($apiKey);
        $this->model = config('services.openai.model', 'gpt-4o-mini');
    }

    /**
     * Generate an AI response for an email
     *
     * @return array ['response' => string, 'confidence' => float, 'metadata' => array]
     */
    public function generateResponse(EmailMessage $emailMessage, ?User $user = null): array
    {
        return $this->executeWithRetry(
            function () use ($emailMessage, $user) {
                // Build context for the AI
                $context = $this->buildContext($emailMessage, $user);

                // Enhance context with RAG if available
                $ragService = App::make(RAGService::class);
                $ragResult = $ragService->enhanceContext($emailMessage, $context['user_message']);
                $enhancedContext = $ragResult['enhanced_context'];
                $sources = $ragResult['sources'];

                // Create the system prompt with global admin prompts
                $systemPrompt = $this->createSystemPrompt($emailMessage, $user);

                // Apply global admin prompt if available
                $globalPrompt = $this->getGlobalPrompt($emailMessage, ! empty($sources));
                if ($globalPrompt) {
                    $systemPrompt = $this->mergeWithGlobalPrompt($systemPrompt, $globalPrompt);
                }

                // Add RAG instructions to system prompt
                if (! empty($sources)) {
                    $systemPrompt .= $ragService->getRAGSystemPrompt();
                }

                // Measure API call performance
                $apiResult = $this->measurePerformance(function () use ($systemPrompt, $enhancedContext) {
                    $params = [
                        'model' => $this->model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $enhancedContext],
                        ],
                        'temperature' => $this->customTemperature ?? 0.7,
                        'max_tokens' => $this->customMaxTokens ?? 1000,
                    ];
                    
                    // Reset custom values after use
                    $this->customTemperature = null;
                    $this->customMaxTokens = null;
                    
                    return $this->client->chat()->create($params);
                }, 'OpenAI API call');

                $completion = $apiResult['result'];
                $response = $completion->choices[0]->message->content;

                // Add citations if sources were used
                if (! empty($sources)) {
                    $citations = $ragService->formatCitations($sources);
                    $response .= $citations;
                }

                // Log AI response generation for GDPR compliance
                AuditService::logAIResponse($emailMessage, [
                    'model' => $this->model,
                    'tokens_used' => $completion->usage->totalTokens ?? 0,
                    'language' => $emailMessage->detected_language,
                    'knowledge_sources_used' => count($sources),
                    'api_duration_ms' => $apiResult['duration_ms'],
                ]);

                return [
                    'response' => $response,
                    'confidence' => $this->calculateConfidence($emailMessage, $response),
                    'metadata' => [
                        'model' => $this->model,
                        'tokens_used' => $completion->usage->totalTokens ?? 0,
                        'language' => $emailMessage->detected_language,
                        'topic' => $emailMessage->topic?->name,
                        'has_conversation_context' => $context['has_conversation_context'],
                        'thread_history_count' => count($context['thread_history'] ?? []),
                        'knowledge_sources' => $sources,
                        'api_duration_ms' => $apiResult['duration_ms'],
                    ],
                ];
            },
            'AI response generation',
            ['email_id' => $emailMessage->id],
            3, // max retries
            2000 // retry delay in ms
        ) ?? $this->getFallbackResponseArray($emailMessage);
    }

    /**
     * Get fallback response as array
     */
    private function getFallbackResponseArray(EmailMessage $emailMessage): array
    {
        return [
            'response' => $this->getFallbackResponse($emailMessage),
            'confidence' => 0.3,
            'metadata' => [
                'fallback' => true,
            ],
        ];
    }

    /**
     * Build context for the AI from email and customer data
     */
    private function buildContext(EmailMessage $emailMessage, ?User $user): array
    {
        $customer = $emailMessage->customer;

        // Get thread history if available
        $threadHistory = [];
        if ($emailMessage->thread_id) {
            // Get all emails in this thread, including draft responses
            $threadEmails = EmailMessage::where('thread_id', $emailMessage->thread_id)
                ->where('id', '!=', $emailMessage->id)
                ->with(['draftResponse', 'customer', 'emotionAnalysis', 'language'])
                ->orderBy('received_at', 'asc')
                ->get();

            foreach ($threadEmails as $email) {
                $threadHistory[] = [
                    'type' => 'received',
                    'date' => $email->received_at->format('Y-m-d H:i'),
                    'from' => $email->sender_name ?? $email->sender_email,
                    'subject' => $email->subject,
                    'content' => $email->body_content,
                ];

                // Include any draft response that was sent
                if ($email->draftResponse && $email->draftResponse->status === 'sent') {
                    $threadHistory[] = [
                        'type' => 'sent',
                        'date' => $email->draftResponse->updated_at->format('Y-m-d H:i'),
                        'to' => $email->sender_email,
                        'subject' => 'Re: '.$email->subject,
                        'content' => $email->draftResponse->ai_generated_content,
                    ];
                }
            }
        }

        // If no thread history, get recent interactions with this customer
        if (empty($threadHistory) && $customer) {
            $recentEmails = EmailMessage::where('customer_id', $customer->id)
                ->where('id', '!=', $emailMessage->id)
                ->with(['draftResponse', 'emotionAnalysis', 'language'])
                ->orderBy('received_at', 'desc')
                ->limit(5)
                ->get()
                ->reverse(); // Reverse to get chronological order

            foreach ($recentEmails as $email) {
                $threadHistory[] = [
                    'type' => 'received',
                    'date' => $email->received_at->format('Y-m-d H:i'),
                    'from' => $email->sender_name ?? $email->sender_email,
                    'subject' => $email->subject,
                    'content' => substr($email->body_content, 0, 500).(strlen($email->body_content) > 500 ? '...' : ''),
                ];

                if ($email->draftResponse) {
                    $threadHistory[] = [
                        'type' => 'sent',
                        'date' => $email->draftResponse->updated_at->format('Y-m-d H:i'),
                        'to' => $email->sender_email,
                        'subject' => 'Re: '.$email->subject,
                        'content' => substr($email->draftResponse->ai_generated_content, 0, 500).(strlen($email->draftResponse->ai_generated_content) > 500 ? '...' : ''),
                    ];
                }
            }
        }

        // Build the conversation context for AI
        $conversationContext = '';
        if (! empty($threadHistory)) {
            $conversationContext = "\n\n=== CONVERSATION HISTORY ===\n";
            foreach ($threadHistory as $msg) {
                if ($msg['type'] === 'received') {
                    $conversationContext .= "\n[{$msg['date']}] FROM: {$msg['from']}\n";
                } else {
                    $conversationContext .= "\n[{$msg['date']}] TO: {$msg['to']} (Our Response)\n";
                }
                $conversationContext .= "Subject: {$msg['subject']}\n";
                $conversationContext .= "---\n{$msg['content']}\n---\n";
            }
            $conversationContext .= "\n=== CURRENT EMAIL ===\n";
        }

        // Build the user message for AI
        $userMessage = $conversationContext;
        $userMessage .= "Email from: {$emailMessage->sender_name} <{$emailMessage->sender_email}>\n";
        $userMessage .= "Subject: {$emailMessage->subject}\n";
        $userMessage .= "Content:\n{$emailMessage->body_content}\n";

        return [
            'user_message' => $userMessage,
            'customer_name' => $customer?->name ?? $emailMessage->sender_name,
            'customer_email' => $emailMessage->sender_email,
            'language' => $emailMessage->detected_language,
            'topic' => $emailMessage->topic?->name,
            'thread_history' => $threadHistory,
            'has_conversation_context' => ! empty($threadHistory),
        ];
    }

    /**
     * Create system prompt based on user preferences and email context
     */
    private function createSystemPrompt(EmailMessage $emailMessage, ?User $user): string
    {
        $language = $this->getLanguageName($emailMessage->detected_language);
        $companyName = $emailMessage->emailAccount->company->name ?? 'the company';
        $context = $this->buildContext($emailMessage, $user);

        // Base prompt
        $prompt = "You are an AI assistant helping to draft email responses for {$companyName}. ";
        $prompt .= "You are a PROFESSIONAL {$language} language expert specializing in formal-friendly business communication. ";

        // Language instruction
        $prompt .= "\n\nCRITICAL LANGUAGE REQUIREMENTS:\n";
        $prompt .= "1. You MUST write ONLY in {$language} language\n";
        $prompt .= "2. Use professional {$language} with proper grammar and spelling\n";
        $prompt .= "3. Use a formal-friendly tone appropriate for business communication\n";
        $prompt .= "4. Use appropriate professional terminology\n";
        $prompt .= "5. Follow the cultural norms for formality in {$language}\n";

        // Conversation context awareness
        if ($context['has_conversation_context']) {
            $prompt .= "\n\nIMPORTANT: You have access to the FULL CONVERSATION HISTORY above. ";
            $prompt .= 'Use this context to maintain continuity and reference previous discussions. ';
            $prompt .= 'Acknowledge any previous commitments or promises made in earlier responses. ';
            $prompt .= 'Build upon the existing conversation naturally. ';
        }

        // User preferences if available
        if ($user) {
            if ($user->ai_writing_style) {
                $prompt .= "Writing style preference: {$user->ai_writing_style}. ";
            }
            if ($user->ai_tone_preferences) {
                $prompt .= "Tone preferences: {$user->ai_tone_preferences}. ";
            }
        }

        // Topic-specific instructions
        $topicInstructions = $this->getTopicInstructions($emailMessage->topic?->name);
        if ($topicInstructions) {
            $prompt .= $topicInstructions;
        }

        // Lithuanian-specific email guidelines
        $prompt .= "\n\nLITHUANIAN EMAIL WRITING GUIDELINES:\n";
        $prompt .= "1. Start with appropriate greeting: 'Laba diena', 'Sveiki', or 'Gerbiamas/a [Name]' for formal\n";
        $prompt .= "2. Use professional Lithuanian that software developers would appreciate\n";
        $prompt .= "3. Be clear and concise while maintaining politeness\n";
        $prompt .= "4. Use proper Lithuanian IT terminology (e.g., 'aplikacija', 'sistema', 'kodas', 'duomenų bazė')\n";
        $prompt .= "5. End with appropriate closing: 'Pagarbiai', 'Linkėjimai', or 'Geriausi linkėjimai'\n";
        $prompt .= "6. Maintain formal-friendly balance typical in Lithuanian tech companies\n";

        // General instructions
        $prompt .= "\n\nGENERAL GUIDELINES:\n";
        $prompt .= "1. ANALYZE the email content carefully and respond SPECIFICALLY to what is being asked\n";
        $prompt .= "2. Address ALL questions or concerns raised in the email\n";
        $prompt .= "3. If discussing technical topics, use accurate Lithuanian technical terms\n";
        $prompt .= "4. If the email asks about products/services, provide helpful information\n";
        $prompt .= "5. If you need more information to help properly, politely ask for it in Lithuanian\n";
        $prompt .= "6. Do not make promises about specific timelines unless certain\n";
        $prompt .= "7. Make the response feel personal and tailored to their specific inquiry\n";

        if ($context['has_conversation_context']) {
            $prompt .= "8. Reference the conversation history when relevant to show continuity\n";
            $prompt .= "9. If following up on previous topics, acknowledge what was discussed before\n";
        }

        $prompt .= "\n\nREMEMBER: Write ONLY in Lithuanian with professional software developer communication style!";

        return $prompt;
    }

    /**
     * Get topic-specific instructions
     */
    private function getTopicInstructions(?string $topic): string
    {
        $instructions = [
            'Support' => 'This is a support request. Focus on being helpful and providing solutions. ',
            'Sales' => 'This is a sales inquiry. Be informative about products/services without being pushy. ',
            'Billing' => 'This is about billing. Be precise with financial information and offer clear next steps. ',
            'Urgent' => 'This is marked as urgent. Acknowledge the urgency and provide immediate actionable response. ',
            'General' => 'This is a general inquiry. Be helpful and direct them to appropriate resources if needed. ',
        ];

        return $instructions[$topic] ?? '';
    }

    /**
     * Convert language code to full name
     */
    private function getLanguageName(string $code): string
    {
        $languages = [
            'en' => 'English',
            'lt' => 'Lithuanian',
            'ru' => 'Russian',
            'pl' => 'Polish',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'it' => 'Italian',
            'lv' => 'Latvian',
            'et' => 'Estonian',
            'fi' => 'Finnish',
            'sv' => 'Swedish',
            'no' => 'Norwegian',
            'da' => 'Danish',
        ];

        return $languages[$code] ?? 'English';
    }

    /**
     * Calculate confidence score for the response
     */
    private function calculateConfidence(EmailMessage $emailMessage, string $response): float
    {
        $confidence = 0.7; // Base confidence

        // Adjust based on language detection confidence
        if ($emailMessage->language_confidence) {
            $confidence *= $emailMessage->language_confidence;
        }

        // Adjust based on topic confidence
        if ($emailMessage->topic_confidence) {
            $confidence *= $emailMessage->topic_confidence;
        }

        // Simple check if response seems appropriate length
        $responseLength = strlen($response);
        if ($responseLength < 50) {
            $confidence *= 0.7; // Too short
        } elseif ($responseLength > 2000) {
            $confidence *= 0.8; // Too long
        }

        return min(0.95, max(0.1, $confidence));
    }

    /**
     * Get fallback response when AI fails
     */
    private function getFallbackResponse(EmailMessage $emailMessage): string
    {
        $templates = [
            'en' => "Dear {name},\n\nThank you for your email. We have received your message and will respond as soon as possible.\n\nBest regards,\nCustomer Service Team",
            'lt' => "Gerb. {name},\n\nDėkojame už jūsų laišką. Mes gavome jūsų žinutę ir atsakysime kaip galima greičiau.\n\nPagarbiai,\nKlientų aptarnavimo komanda",
            'ru' => "Уважаемый(ая) {name},\n\nСпасибо за ваше письмо. Мы получили ваше сообщение и ответим как можно скорее.\n\nС уважением,\nСлужба поддержки клиентов",
            'pl' => "Szanowny/a {name},\n\nDziękujemy za Twoją wiadomość. Otrzymaliśmy ją i odpowiemy tak szybko, jak to możliwe.\n\nZ poważaniem,\nZespół obsługi klienta",
            'de' => "Sehr geehrte(r) {name},\n\nVielen Dank für Ihre E-Mail. Wir haben Ihre Nachricht erhalten und werden so schnell wie möglich antworten.\n\nMit freundlichen Grüßen,\nKundenservice-Team",
        ];

        $language = $emailMessage->detected_language ?? 'en';
        $template = $templates[$language] ?? $templates['en'];
        $customerName = $emailMessage->customer?->name ?? $emailMessage->sender_name ?? 'Customer';

        return str_replace('{name}', $customerName, $template);
    }

    /**
     * Get global prompt if configured by admin
     */
    private function getGlobalPrompt(EmailMessage $emailMessage, bool $hasRAGSources): ?GlobalAIPrompt
    {
        $companyId = $emailMessage->emailAccount->company_id;
        
        // If RAG sources are available, prioritize RAG-enhanced prompt
        if ($hasRAGSources) {
            $ragPrompt = GlobalAIPrompt::getActiveRAGPromptForCompany($companyId);
            if ($ragPrompt) {
                return $ragPrompt;
            }
        }
        
        // Fall back to general global prompt
        return GlobalAIPrompt::getActivePromptForCompany($companyId);
    }

    /**
     * Merge system prompt with global admin prompt
     */
    private function mergeWithGlobalPrompt(string $systemPrompt, GlobalAIPrompt $globalPrompt): string
    {
        $mergedPrompt = "";
        
        // Add global prompt header
        $mergedPrompt .= "=== COMPANY GLOBAL AI INSTRUCTIONS ===\n";
        $mergedPrompt .= $globalPrompt->prompt_content . "\n";
        $mergedPrompt .= "=== END OF GLOBAL INSTRUCTIONS ===\n\n";
        
        // Add the original system prompt
        $mergedPrompt .= $systemPrompt;
        
        // Apply any custom settings from global prompt
        if ($globalPrompt->settings) {
            $settings = $globalPrompt->settings;
            
            // Add any additional instructions based on settings
            if (isset($settings['temperature'])) {
                // Temperature will be used in the API call
                $this->customTemperature = $settings['temperature'];
            }
            
            if (isset($settings['max_tokens'])) {
                $this->customMaxTokens = $settings['max_tokens'];
            }
            
            if (isset($settings['additional_instructions'])) {
                $mergedPrompt .= "\n\nADDITIONAL INSTRUCTIONS:\n";
                $mergedPrompt .= $settings['additional_instructions'];
            }
        }
        
        return $mergedPrompt;
    }

    /**
     * Analyze email for emotion/sentiment
     */
    public function analyzeEmotion(EmailMessage $emailMessage): array
    {
        try {
            $completion = $this->client->chat()->create([
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Analyze the emotional tone of the following email. Respond with a JSON object containing: {"sentiment": "positive|neutral|negative", "emotion": "happy|angry|frustrated|confused|satisfied|neutral", "urgency": "high|medium|low", "confidence": 0.0-1.0}',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Subject: {$emailMessage->subject}\n\n{$emailMessage->body_content}",
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 100,
            ]);

            $response = $completion->choices[0]->message->content;
            $analysis = json_decode($response, true);

            if (! $analysis) {
                throw new Exception('Invalid JSON response from AI');
            }

            return $analysis;

        } catch (Exception $e) {
            Log::error('Emotion analysis failed', [
                'error' => $e->getMessage(),
                'email_id' => $emailMessage->id,
            ]);

            return [
                'sentiment' => 'neutral',
                'emotion' => 'neutral',
                'urgency' => 'medium',
                'confidence' => 0.3,
            ];
        }
    }
}
