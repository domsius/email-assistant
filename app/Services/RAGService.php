<?php

namespace App\Services;

use App\Models\EmailMessage;
use Exception;
use Illuminate\Support\Facades\Log;

class RAGService
{
    private DocumentProcessingService $documentService;

    private EmbeddingService $embeddingService;

    private string $contextPrompt;

    public function __construct(
        DocumentProcessingService $documentService,
        EmbeddingService $embeddingService
    ) {
        $this->documentService = $documentService;
        $this->embeddingService = $embeddingService;
    }

    /**
     * Enhance AI response context with knowledge base information
     *
     * @return array ['enhanced_context' => string, 'sources' => array]
     */
    public function enhanceContext(EmailMessage $email, string $baseContext): array
    {
        try {
            $companyId = $email->emailAccount->company_id;

            // Extract key information from email
            $query = $this->extractQueryFromEmail($email);

            // Search knowledge base for relevant information
            $relevantChunks = $this->documentService->getRelevantChunks($query, $companyId, 5);

            if (empty($relevantChunks)) {
                Log::debug('No relevant knowledge base content found', [
                    'email_id' => $email->id,
                    'query' => $query,
                ]);

                return [
                    'enhanced_context' => $baseContext,
                    'sources' => [],
                ];
            }

            // Build enhanced context
            $enhancedContext = $this->buildEnhancedContext($baseContext, $relevantChunks);

            // Extract sources for citation
            $sources = $this->extractSources($relevantChunks);

            Log::info('Context enhanced with knowledge base', [
                'email_id' => $email->id,
                'chunks_used' => count($relevantChunks),
                'sources' => count($sources),
            ]);

            return [
                'enhanced_context' => $enhancedContext,
                'sources' => $sources,
            ];

        } catch (Exception $e) {
            Log::error('RAG enhancement failed', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to base context if enhancement fails
            return [
                'enhanced_context' => $baseContext,
                'sources' => [],
            ];
        }
    }

    /**
     * Extract search query from email content
     */
    private function extractQueryFromEmail(EmailMessage $email): string
    {
        // Combine subject and key parts of body for search
        $query = $email->subject.' ';

        // Extract first 500 characters of body or questions
        $body = $email->body_content;

        // Look for questions in the email
        preg_match_all('/[^.!?]*\?/', $body, $questions);
        if (! empty($questions[0])) {
            $query .= implode(' ', array_slice($questions[0], 0, 3));
        } else {
            // Use first part of email if no questions found
            $query .= substr($body, 0, 500);
        }

        return trim($query);
    }

    /**
     * Build enhanced context with knowledge base information
     */
    private function buildEnhancedContext(string $baseContext, array $relevantChunks): string
    {
        $knowledgeContext = "\n\n=== RELEVANT KNOWLEDGE BASE INFORMATION ===\n";
        $knowledgeContext .= "The following information from your knowledge base may be relevant:\n\n";

        foreach ($relevantChunks as $index => $chunk) {
            $documentTitle = $chunk['document']->title;
            $content = $chunk['content'];
            $score = round($chunk['score'], 3);

            $knowledgeContext .= "---\n";
            $knowledgeContext .= "Source: {$documentTitle} (Relevance: {$score})\n";
            $knowledgeContext .= "{$content}\n";
            $knowledgeContext .= "---\n\n";
        }

        $knowledgeContext .= "=== END OF KNOWLEDGE BASE INFORMATION ===\n\n";
        $knowledgeContext .= 'IMPORTANT: Use the above knowledge base information to provide accurate and specific answers. ';
        $knowledgeContext .= 'Reference the source documents when using this information. ';
        $knowledgeContext .= "If the knowledge base doesn't contain relevant information, you can still answer based on general knowledge.\n\n";

        return $baseContext.$knowledgeContext;
    }

    /**
     * Extract sources for citation
     */
    private function extractSources(array $relevantChunks): array
    {
        $sources = [];
        $seenDocuments = [];

        foreach ($relevantChunks as $chunk) {
            $document = $chunk['document'];

            // Avoid duplicate sources
            if (in_array($document->id, $seenDocuments)) {
                continue;
            }

            $sources[] = [
                'id' => $document->id,
                'title' => $document->title,
                'filename' => $document->filename,
                'relevance_score' => $chunk['score'],
                'chunk_preview' => substr($chunk['content'], 0, 200).'...',
            ];

            $seenDocuments[] = $document->id;
        }

        return $sources;
    }

    /**
     * Format citations for inclusion in response
     */
    public function formatCitations(array $sources): string
    {
        if (empty($sources)) {
            return '';
        }

        $citations = "\n\n---\n📚 Sources:\n";

        foreach ($sources as $index => $source) {
            $num = $index + 1;
            $citations .= "{$num}. {$source['title']} ({$source['filename']})\n";
        }

        return $citations;
    }

    /**
     * Search knowledge base directly
     */
    public function searchKnowledgeBase(string $query, int $companyId, int $limit = 10): array
    {
        try {
            return $this->documentService->searchDocuments($query, $companyId, $limit);
        } catch (Exception $e) {
            Log::error('Knowledge base search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get system prompt enhancement for RAG
     */
    public function getRAGSystemPrompt(): string
    {
        return "\n\nYou have access to a knowledge base that may contain relevant information. ".
               'When knowledge base information is provided, use it to give accurate and specific answers. '.
               'Always cite your sources when using information from the knowledge base. '.
               "If the knowledge base doesn't contain relevant information, you can answer based on general knowledge, ".
               "but make it clear that the information is not from the company's knowledge base.";
    }
}
