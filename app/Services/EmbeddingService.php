<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI;

class EmbeddingService
{
    private $client;

    private string $model = 'text-embedding-3-small';

    public function __construct()
    {
        $apiKey = config('services.openai.api_key');

        if (! $apiKey) {
            throw new Exception('OpenAI API key not configured');
        }

        $this->client = OpenAI::client($apiKey);
    }

    /**
     * Generate embedding for a text
     *
     * @return array Vector embedding
     */
    public function generateEmbedding(string $text): array
    {
        // Check cache first
        $cacheKey = 'embedding:'.md5($text);
        $cached = Cache::get($cacheKey);

        if ($cached) {
            Log::debug('Using cached embedding', ['text_length' => strlen($text)]);

            return $cached;
        }

        try {
            // Trim text to avoid exceeding token limits
            $text = $this->prepareText($text);

            $response = $this->client->embeddings()->create([
                'model' => $this->model,
                'input' => $text,
            ]);

            $embedding = $response->embeddings[0]->embedding;

            // Cache the embedding for 24 hours
            Cache::put($cacheKey, $embedding, 86400);

            Log::info('Generated embedding', [
                'model' => $this->model,
                'text_length' => strlen($text),
                'vector_dimensions' => count($embedding),
            ]);

            return $embedding;

        } catch (Exception $e) {
            Log::error('Embedding generation failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);

            throw new Exception('Failed to generate embedding: '.$e->getMessage());
        }
    }

    /**
     * Generate embeddings for multiple texts (batch)
     *
     * @return array Array of embeddings
     */
    public function generateEmbeddings(array $texts): array
    {
        $embeddings = [];
        $uncachedTexts = [];
        $uncachedIndices = [];

        // Check cache for each text
        foreach ($texts as $index => $text) {
            $cacheKey = 'embedding:'.md5($text);
            $cached = Cache::get($cacheKey);

            if ($cached) {
                $embeddings[$index] = $cached;
            } else {
                $uncachedTexts[] = $this->prepareText($text);
                $uncachedIndices[] = $index;
            }
        }

        // Generate embeddings for uncached texts
        if (! empty($uncachedTexts)) {
            try {
                $response = $this->client->embeddings()->create([
                    'model' => $this->model,
                    'input' => $uncachedTexts,
                ]);

                // Map embeddings back to original indices
                foreach ($response->embeddings as $i => $embeddingData) {
                    $originalIndex = $uncachedIndices[$i];
                    $embedding = $embeddingData->embedding;

                    $embeddings[$originalIndex] = $embedding;

                    // Cache the embedding
                    $cacheKey = 'embedding:'.md5($texts[$originalIndex]);
                    Cache::put($cacheKey, $embedding, 86400);
                }

                Log::info('Generated batch embeddings', [
                    'model' => $this->model,
                    'batch_size' => count($uncachedTexts),
                    'cached_count' => count($texts) - count($uncachedTexts),
                ]);

            } catch (Exception $e) {
                Log::error('Batch embedding generation failed', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($uncachedTexts),
                ]);

                throw new Exception('Failed to generate embeddings: '.$e->getMessage());
            }
        }

        // Sort by original index
        ksort($embeddings);

        return array_values($embeddings);
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @return float Similarity score between -1 and 1
     */
    public function cosineSimilarity(array $vector1, array $vector2): float
    {
        if (count($vector1) !== count($vector2)) {
            throw new Exception('Vectors must have the same dimensions');
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $magnitude1 += $vector1[$i] * $vector1[$i];
            $magnitude2 += $vector2[$i] * $vector2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Find most similar vectors from a list
     *
     * @param  array  $vectors  Array of vectors to compare against
     * @param  int  $topK  Number of top results to return
     * @return array Array of indices and scores sorted by similarity
     */
    public function findMostSimilar(array $queryVector, array $vectors, int $topK = 5): array
    {
        $similarities = [];

        foreach ($vectors as $index => $vector) {
            $similarity = $this->cosineSimilarity($queryVector, $vector);
            $similarities[] = [
                'index' => $index,
                'score' => $similarity,
            ];
        }

        // Sort by similarity score (descending)
        usort($similarities, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Return top K results
        return array_slice($similarities, 0, $topK);
    }

    /**
     * Prepare text for embedding generation
     */
    private function prepareText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim to max tokens (approximately 8191 tokens for text-embedding-3-small)
        // Rough estimate: 1 token ≈ 4 characters
        $maxChars = 30000;
        if (strlen($text) > $maxChars) {
            $text = substr($text, 0, $maxChars).'...';
        }

        return trim($text);
    }

    /**
     * Get embedding model info
     */
    public function getModelInfo(): array
    {
        return [
            'model' => $this->model,
            'dimensions' => $this->model === 'text-embedding-3-small' ? 1536 : 3072,
            'max_tokens' => 8191,
        ];
    }
}
