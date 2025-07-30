<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentChunk;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentProcessingService
{
    private const CHUNK_SIZE = 1000; // Characters per chunk

    private const CHUNK_OVERLAP = 200; // Overlap between chunks

    private ElasticsearchService $elasticsearch;

    private EmbeddingService $embeddingService;

    public function __construct(
        ElasticsearchService $elasticsearch,
        EmbeddingService $embeddingService
    ) {
        $this->elasticsearch = $elasticsearch;
        $this->embeddingService = $embeddingService;
    }

    /**
     * Process a document: extract text, chunk it, generate embeddings, and index
     */
    public function processDocument(Document $document): void
    {
        try {
            Log::info('Processing document', ['document_id' => $document->id]);

            // Update status to processing
            $document->update(['status' => 'processing']);

            // Extract text based on file type
            $text = $this->extractText($document);

            if (empty($text)) {
                throw new Exception('No text content extracted from document');
            }

            // Update document with extracted text
            $document->update([
                'content' => $text,
                'extracted_at' => now(),
            ]);

            // Create chunks
            $chunks = $this->createChunks($text);

            // Process each chunk
            foreach ($chunks as $index => $chunkText) {
                $this->processChunk($document, $chunkText, $index);
            }

            // Index document in Elasticsearch
            $this->elasticsearch->indexDocument($document);

            // Update status to processed
            $document->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

            Log::info('Document processed successfully', [
                'document_id' => $document->id,
                'chunks_created' => count($chunks),
            ]);

        } catch (Exception $e) {
            Log::error('Document processing failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Extract text from document based on file type
     */
    private function extractText(Document $document): string
    {
        $filePath = Storage::disk('local')->path($document->file_path);

        if (! file_exists($filePath)) {
            throw new Exception('Document file not found');
        }

        switch (strtolower(pathinfo($document->filename, PATHINFO_EXTENSION))) {
            case 'pdf':
                return $this->extractPdfText($filePath);

            case 'txt':
            case 'md':
                return file_get_contents($filePath);

            case 'doc':
            case 'docx':
                return $this->extractWordText($filePath);

            case 'csv':
                return $this->extractCsvText($filePath);

            case 'json':
                return $this->extractJsonText($filePath);

            default:
                throw new Exception('Unsupported file type: '.$document->mime_type);
        }
    }

    /**
     * Extract text from PDF
     */
    private function extractPdfText(string $filePath): string
    {
        try {
            $parser = new PdfParser;
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();

            // Clean up text
            $text = preg_replace('/\s+/', ' ', $text);
            $text = trim($text);

            return $text;
        } catch (Exception $e) {
            throw new Exception('Failed to extract PDF text: '.$e->getMessage());
        }
    }

    /**
     * Extract text from Word document
     */
    private function extractWordText(string $filePath): string
    {
        try {
            $phpWord = WordIOFactory::load($filePath);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText().' ';
                    }
                }
            }

            return trim($text);
        } catch (Exception $e) {
            throw new Exception('Failed to extract Word text: '.$e->getMessage());
        }
    }

    /**
     * Extract text from CSV
     */
    private function extractCsvText(string $filePath): string
    {
        try {
            $text = '';
            $handle = fopen($filePath, 'r');

            while (($data = fgetcsv($handle)) !== false) {
                $text .= implode(' ', $data)."\n";
            }

            fclose($handle);

            return trim($text);
        } catch (Exception $e) {
            throw new Exception('Failed to extract CSV text: '.$e->getMessage());
        }
    }

    /**
     * Extract text from JSON
     */
    private function extractJsonText(string $filePath): string
    {
        try {
            $json = file_get_contents($filePath);
            $data = json_decode($json, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON: '.json_last_error_msg());
            }

            // Convert JSON to readable text
            return $this->jsonToText($data);
        } catch (Exception $e) {
            throw new Exception('Failed to extract JSON text: '.$e->getMessage());
        }
    }

    /**
     * Convert JSON data to readable text
     */
    private function jsonToText($data, string $prefix = ''): string
    {
        $text = '';

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $text .= $prefix.$key.":\n";
                    $text .= $this->jsonToText($value, $prefix.'  ');
                } else {
                    $text .= $prefix.$key.': '.$value."\n";
                }
            }
        } else {
            $text .= $prefix.$data."\n";
        }

        return $text;
    }

    /**
     * Create chunks from text with overlap
     */
    private function createChunks(string $text): array
    {
        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);

        $currentChunk = '';
        $currentLength = 0;

        foreach ($sentences as $sentence) {
            $sentenceLength = strlen($sentence);

            // If adding this sentence would exceed chunk size, save current chunk
            if ($currentLength + $sentenceLength > self::CHUNK_SIZE && ! empty($currentChunk)) {
                $chunks[] = trim($currentChunk);

                // Start new chunk with overlap from previous chunk
                $overlap = $this->getOverlapText($currentChunk);
                $currentChunk = $overlap.' '.$sentence;
                $currentLength = strlen($currentChunk);
            } else {
                $currentChunk .= ' '.$sentence;
                $currentLength += $sentenceLength + 1;
            }
        }

        // Add the last chunk if it's not empty
        if (! empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Get overlap text from the end of a chunk
     */
    private function getOverlapText(string $chunk): string
    {
        if (strlen($chunk) <= self::CHUNK_OVERLAP) {
            return $chunk;
        }

        // Find a good break point near the overlap size
        $overlapStart = strlen($chunk) - self::CHUNK_OVERLAP;
        $breakPoint = strpos($chunk, ' ', $overlapStart);

        if ($breakPoint === false) {
            return substr($chunk, $overlapStart);
        }

        return substr($chunk, $breakPoint + 1);
    }

    /**
     * Process a single chunk
     */
    private function processChunk(Document $document, string $chunkText, int $index): void
    {
        // Generate embedding for the chunk
        $embedding = $this->embeddingService->generateEmbedding($chunkText);

        // Create chunk record
        $chunk = $document->chunks()->create([
            'chunk_index' => $index,
            'content' => $chunkText,
            'embedding' => json_encode($embedding),
            'metadata' => [
                'char_count' => strlen($chunkText),
                'word_count' => str_word_count($chunkText),
            ],
        ]);

        // Index chunk in Elasticsearch with embedding
        $this->elasticsearch->indexChunk($chunk, $embedding);
    }

    /**
     * Search documents using semantic search
     */
    public function searchDocuments(string $query, int $companyId, int $limit = 10): array
    {
        // Generate embedding for the query
        $queryEmbedding = $this->embeddingService->generateEmbedding($query);

        // Search in Elasticsearch
        return $this->elasticsearch->searchByVector($queryEmbedding, $companyId, $limit);
    }

    /**
     * Get relevant chunks for a query
     */
    public function getRelevantChunks(string $query, int $companyId, int $limit = 5): array
    {
        $searchResults = $this->searchDocuments($query, $companyId, $limit);

        $chunks = [];
        foreach ($searchResults as $result) {
            $chunk = DocumentChunk::find($result['chunk_id']);
            if ($chunk) {
                $chunks[] = [
                    'content' => $chunk->content,
                    'document' => $chunk->document,
                    'score' => $result['score'],
                ];
            }
        }

        return $chunks;
    }
}
