<?php

namespace App\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;
use Illuminate\Support\Facades\Log;

class ElasticsearchService
{
    private Client $client;

    private string $documentsIndex = 'documents';

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->setHosts([config('services.elasticsearch.host', 'http://elasticsearch:9200')])
            ->build();
    }

    /**
     * Initialize the documents index with proper mappings
     */
    public function initializeIndex(): void
    {
        try {
            // Delete index if exists (for development)
            if ($this->client->indices()->exists(['index' => $this->documentsIndex])->asBool()) {
                $this->client->indices()->delete(['index' => $this->documentsIndex]);
            }

            // Create index with mappings
            $this->client->indices()->create([
                'index' => $this->documentsIndex,
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0,
                        'analysis' => [
                            'analyzer' => [
                                'default' => [
                                    'type' => 'standard',
                                ],
                            ],
                        ],
                    ],
                    'mappings' => [
                        'properties' => [
                            'document_id' => ['type' => 'integer'],
                            'chunk_id' => ['type' => 'integer'],
                            'company_id' => ['type' => 'integer'],
                            'title' => ['type' => 'text'],
                            'content' => ['type' => 'text'],
                            'embedding' => [
                                'type' => 'dense_vector',
                                'dims' => 1536, // OpenAI ada-002 embeddings dimension
                                'index' => true,
                                'similarity' => 'cosine',
                            ],
                            'metadata' => ['type' => 'object'],
                            'created_at' => ['type' => 'date'],
                        ],
                    ],
                ],
            ]);

            Log::info('Elasticsearch index initialized successfully');
        } catch (Exception $e) {
            Log::error('Failed to initialize Elasticsearch index: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Index a document chunk
     */
    public function indexChunk($chunkOrData, ?array $embedding = null): string
    {
        try {
            // Handle both DocumentChunk object and array data
            if (is_object($chunkOrData) && method_exists($chunkOrData, 'getAttribute')) {
                // It's a DocumentChunk model
                $chunk = $chunkOrData;
                $document = $chunk->document;

                $data = [
                    'document_id' => $document->id,
                    'chunk_id' => $chunk->id,
                    'company_id' => $document->company_id,
                    'title' => $document->title,
                    'content' => $chunk->content,
                    'embedding' => $embedding ?? json_decode($chunk->embedding, true),
                    'metadata' => array_merge($document->metadata ?? [], $chunk->metadata ?? []),
                    'created_at' => $chunk->created_at->toIso8601String(),
                ];
            } else {
                // It's already formatted data
                $data = $chunkOrData;
            }

            $response = $this->client->index([
                'index' => $this->documentsIndex,
                'body' => $data,
            ]);

            return $response['_id'];
        } catch (Exception $e) {
            Log::error('Failed to index chunk: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Search documents using vector similarity
     */
    public function vectorSearch(array $embedding, int $companyId, int $limit = 5): array
    {
        try {
            $response = $this->client->search([
                'index' => $this->documentsIndex,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['company_id' => $companyId]],
                            ],
                        ],
                    ],
                    'knn' => [
                        'field' => 'embedding',
                        'query_vector' => $embedding,
                        'k' => $limit,
                        'num_candidates' => $limit * 2,
                    ],
                    'size' => $limit,
                ],
            ]);

            return $this->formatSearchResults($response);
        } catch (Exception $e) {
            Log::error('Vector search failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Hybrid search combining text and vector search
     */
    public function hybridSearch(string $query, array $embedding, int $companyId, int $limit = 5): array
    {
        try {
            $response = $this->client->search([
                'index' => $this->documentsIndex,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                ['term' => ['company_id' => $companyId]],
                                [
                                    'multi_match' => [
                                        'query' => $query,
                                        'fields' => ['title^2', 'content'],
                                        'type' => 'best_fields',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'knn' => [
                        'field' => 'embedding',
                        'query_vector' => $embedding,
                        'k' => $limit,
                        'num_candidates' => $limit * 2,
                        'boost' => 0.5,
                    ],
                    'size' => $limit,
                ],
            ]);

            return $this->formatSearchResults($response);
        } catch (Exception $e) {
            Log::error('Hybrid search failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a document from the index
     */
    public function deleteDocument(string $id): void
    {
        try {
            $this->client->delete([
                'index' => $this->documentsIndex,
                'id' => $id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete document: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete all documents for a company
     */
    public function deleteByCompany(int $companyId): void
    {
        try {
            $this->client->deleteByQuery([
                'index' => $this->documentsIndex,
                'body' => [
                    'query' => [
                        'term' => ['company_id' => $companyId],
                    ],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete company documents: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Format search results
     */
    private function formatSearchResults(array $response): array
    {
        $results = [];

        foreach ($response['hits']['hits'] as $hit) {
            $results[] = [
                'id' => $hit['_id'],
                'score' => $hit['_score'],
                'document_id' => $hit['_source']['document_id'] ?? null,
                'chunk_id' => $hit['_source']['chunk_id'] ?? null,
                'title' => $hit['_source']['title'] ?? '',
                'content' => $hit['_source']['content'] ?? '',
                'metadata' => $hit['_source']['metadata'] ?? [],
            ];
        }

        return $results;
    }

    /**
     * Check if Elasticsearch is available
     */
    public function isAvailable(): bool
    {
        try {
            $response = $this->client->info();

            return ! empty($response['version']);
        } catch (Exception $e) {
            Log::error('Elasticsearch ping failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Index a complete document (wrapper for consistency)
     */
    public function indexDocument($document): void
    {
        // This is handled by indexChunk for each chunk of the document
        // Keeping this method for interface consistency
        Log::info('Document indexing handled through chunks', [
            'document_id' => $document->id,
        ]);
    }

    /**
     * Search by vector (alias for vectorSearch)
     */
    public function searchByVector(array $queryVector, int $companyId, int $limit = 10): array
    {
        return $this->vectorSearch($queryVector, $companyId, $limit);
    }
}
