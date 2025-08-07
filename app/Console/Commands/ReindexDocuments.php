<?php

namespace App\Console\Commands;

use App\Models\DocumentChunk;
use App\Services\ElasticsearchService;
use App\Services\DocumentProcessingService;
use Illuminate\Console\Command;

class ReindexDocuments extends Command
{
    protected $signature = 'documents:reindex {--company=}';
    protected $description = 'Reindex all document chunks into Elasticsearch';

    public function handle(ElasticsearchService $elasticsearchService, DocumentProcessingService $documentProcessingService)
    {
        $this->info('Starting document reindexing...');
        
        $query = DocumentChunk::with('document');
        
        if ($companyId = $this->option('company')) {
            $query->whereHas('document', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }
        
        $chunks = $query->get();
        $total = $chunks->count();
        
        if ($total === 0) {
            $this->info('No document chunks found to reindex.');
            return Command::SUCCESS;
        }
        
        $this->info("Found {$total} chunks to reindex.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();
        
        $success = 0;
        $failed = 0;
        
        foreach ($chunks as $chunk) {
            try {
                // Get or generate embedding
                $embedding = $chunk->embedding 
                    ? json_decode($chunk->embedding, true) 
                    : $documentProcessingService->generateEmbedding($chunk->content);
                
                // Index the chunk
                $elasticsearchService->indexChunk($chunk, $embedding);
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $this->error("\nFailed to index chunk {$chunk->id}: " . $e->getMessage());
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        
        $this->info("Reindexing complete! Success: {$success}, Failed: {$failed}");
        
        return Command::SUCCESS;
    }
}