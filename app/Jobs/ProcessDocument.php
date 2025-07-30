<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\DocumentProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDocument implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Document $document
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentProcessingService $processingService): void
    {
        Log::info('Starting document processing job', [
            'document_id' => $this->document->id,
            'filename' => $this->document->filename,
        ]);

        try {
            $processingService->processDocument($this->document);

            Log::info('Document processing completed', [
                'document_id' => $this->document->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Document processing failed', [
                'document_id' => $this->document->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to let Laravel handle retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Document processing job failed permanently', [
            'document_id' => $this->document->id,
            'error' => $exception->getMessage(),
        ]);

        // Update document status to failed
        $this->document->update([
            'status' => 'failed',
            'error_message' => 'Processing failed: '.$exception->getMessage(),
        ]);
    }
}
