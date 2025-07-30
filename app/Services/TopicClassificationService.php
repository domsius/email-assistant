<?php

namespace App\Services;

use App\Models\Topic;
use Illuminate\Support\Facades\Log;

class TopicClassificationService
{
    // Define topic keywords for classification
    private array $topicKeywords = [
        'support' => [
            'keywords' => ['error', 'bug', 'issue', 'problem', 'help', 'support', 'technical', 'broken', 'not working', 'troubleshoot', 'fix', 'assistance', 'difficulty'],
            'priority' => 'high',
        ],
        'sales' => [
            'keywords' => ['price', 'pricing', 'quote', 'proposal', 'purchase', 'buy', 'sale', 'demo', 'trial', 'subscription', 'plan', 'upgrade', 'business', 'partnership'],
            'priority' => 'high',
        ],
        'billing' => [
            'keywords' => ['invoice', 'payment', 'billing', 'charge', 'refund', 'subscription', 'account', 'balance', 'receipt', 'transaction', 'credit card', 'paypal'],
            'priority' => 'high',
        ],
        'urgent' => [
            'keywords' => ['urgent', 'asap', 'emergency', 'critical', 'immediately', 'deadline', 'rush', 'priority', 'time sensitive', 'quick'],
            'priority' => 'critical',
        ],
        'general' => [
            'keywords' => ['general', 'information', 'question', 'inquiry', 'feedback', 'suggestion', 'comment'],
            'priority' => 'medium',
        ],
    ];

    /**
     * Classify email topic based on content
     *
     * @return array Returns array with 'topic_id', 'confidence', 'detected_keywords'
     */
    public function classifyTopic(string $subject, string $body): array
    {
        try {
            // Combine and clean text for analysis
            $text = $this->cleanText($subject.' '.$body);

            if (strlen($text) < 10) {
                return [
                    'topic_id' => $this->getGeneralTopicId(),
                    'confidence' => 0.1,
                    'detected_keywords' => [],
                ];
            }

            // Score each topic
            $scores = [];
            $allDetectedKeywords = [];

            foreach ($this->topicKeywords as $topicName => $data) {
                $result = $this->calculateTopicScore($text, $data['keywords']);
                $scores[$topicName] = $result['score'];

                if (! empty($result['keywords'])) {
                    $allDetectedKeywords[$topicName] = $result['keywords'];
                }
            }

            // Find the best matching topic
            $bestTopic = array_search(max($scores), $scores);
            $maxScore = max($scores);

            // If no strong match, default to general
            if ($maxScore < 1) {
                $bestTopic = 'general';
                $confidence = 0.3;
            } else {
                // Calculate confidence based on score
                $confidence = min(0.2 + ($maxScore * 0.15), 0.95);
            }

            $topicId = $this->getTopicId($bestTopic);
            $detectedKeywords = $allDetectedKeywords[$bestTopic] ?? [];

            Log::info('Topic classified for email', [
                'topic' => $bestTopic,
                'topic_id' => $topicId,
                'confidence' => $confidence,
                'keywords' => $detectedKeywords,
                'text_length' => strlen($text),
            ]);

            return [
                'topic_id' => $topicId,
                'confidence' => round($confidence, 2),
                'detected_keywords' => $detectedKeywords,
            ];

        } catch (\Exception $e) {
            Log::error('Topic classification failed', [
                'error' => $e->getMessage(),
                'subject_length' => strlen($subject),
                'body_length' => strlen($body),
            ]);

            return [
                'topic_id' => $this->getGeneralTopicId(),
                'confidence' => 0.1,
                'detected_keywords' => [],
            ];
        }
    }

    /**
     * Calculate topic score based on keyword matches
     */
    private function calculateTopicScore(string $text, array $keywords): array
    {
        $text = strtolower($text);
        $score = 0;
        $foundKeywords = [];

        foreach ($keywords as $keyword) {
            $keyword = strtolower($keyword);

            if (strpos($text, $keyword) !== false) {
                // Give higher score for exact word matches
                if (preg_match('/\b'.preg_quote($keyword, '/').'\b/', $text)) {
                    $score += 2;
                } else {
                    $score += 1;
                }
                $foundKeywords[] = $keyword;
            }
        }

        return [
            'score' => $score,
            'keywords' => $foundKeywords,
        ];
    }

    /**
     * Clean text for analysis
     */
    private function cleanText(string $text): string
    {
        // Remove email headers and quoted content
        $lines = explode("\n", $text);
        $cleanLines = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Skip email headers
            if (preg_match('/^(From|To|Subject|Date|CC|BCC):/i', $line)) {
                continue;
            }

            // Skip quoted content
            if (str_starts_with($line, '>')) {
                continue;
            }

            // Skip signature delimiters
            if (preg_match('/^--\s*$/', $line)) {
                break;
            }

            $cleanLines[] = $line;
        }

        $cleanText = implode(' ', $cleanLines);
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        return trim($cleanText);
    }

    /**
     * Get topic ID by name
     */
    private function getTopicId(string $topicName): ?int
    {
        $topic = Topic::where('slug', $topicName)->first();

        return $topic ? $topic->id : $this->getGeneralTopicId();
    }

    /**
     * Get general topic ID as fallback
     */
    private function getGeneralTopicId(): ?int
    {
        $topic = Topic::where('slug', 'general')->first();

        return $topic ? $topic->id : null;
    }

    /**
     * Seed default topics if they don't exist
     */
    public function seedDefaultTopics(int $companyId = 1): void
    {
        $defaultTopics = [
            [
                'company_id' => $companyId,
                'name' => 'Support',
                'slug' => 'support',
                'description' => 'Technical support and troubleshooting requests',
                'color' => '#ef4444', // red
                'priority' => 'high',
                'keywords' => json_encode(['error', 'bug', 'issue', 'problem', 'help', 'support', 'technical']),
                'priority_weight' => 90,
                'estimated_response_hours' => 4,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Sales',
                'slug' => 'sales',
                'description' => 'Sales inquiries, quotes, and business proposals',
                'color' => '#22c55e', // green
                'priority' => 'high',
                'keywords' => json_encode(['price', 'pricing', 'quote', 'proposal', 'purchase', 'buy', 'sale', 'demo']),
                'priority_weight' => 85,
                'estimated_response_hours' => 8,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Billing',
                'slug' => 'billing',
                'description' => 'Payment issues, invoices, and account billing',
                'color' => '#f59e0b', // yellow
                'priority' => 'high',
                'keywords' => json_encode(['invoice', 'payment', 'billing', 'charge', 'refund', 'subscription']),
                'priority_weight' => 80,
                'estimated_response_hours' => 12,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'Urgent',
                'slug' => 'urgent',
                'description' => 'Time-sensitive and critical matters',
                'color' => '#dc2626', // dark red
                'priority' => 'critical',
                'keywords' => json_encode(['urgent', 'asap', 'emergency', 'critical', 'immediately', 'deadline']),
                'priority_weight' => 100,
                'estimated_response_hours' => 1,
                'is_active' => true,
            ],
            [
                'company_id' => $companyId,
                'name' => 'General',
                'slug' => 'general',
                'description' => 'General inquiries and correspondence',
                'color' => '#6b7280', // gray
                'priority' => 'medium',
                'keywords' => json_encode(['general', 'information', 'question', 'inquiry', 'feedback']),
                'priority_weight' => 50,
                'estimated_response_hours' => 24,
                'is_active' => true,
            ],
        ];

        foreach ($defaultTopics as $topicData) {
            Topic::firstOrCreate(
                [
                    'slug' => $topicData['slug'],
                    'company_id' => $companyId,
                ],
                $topicData
            );
        }

        Log::info('Default topics seeded for company', ['company_id' => $companyId]);
    }

    /**
     * Get all available topics
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAvailableTopics()
    {
        return Topic::orderBy('priority', 'desc')->orderBy('name')->get();
    }
}
